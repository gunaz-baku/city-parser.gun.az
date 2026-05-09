<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesParserRunLifecycle;
use App\Services\Scraper\WoltHtmlPriceExtractor;
use App\Services\ScraperService;
use App\Support\PriceSourceLinks;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ParseWoltSourceJob implements ShouldQueue
{
    use Batchable, Dispatchable, HandlesParserRunLifecycle, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public int $parserRunId,
        public int $priceSourceId,
    ) {
        $this->onConnection('redis');
        $this->onQueue('parser-wolt');
    }

    public function handle(ScraperService $scraper, WoltHtmlPriceExtractor $extractor): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $stage = 'init';

        if (! Schema::hasTable('source_price_results')) {
            throw new \RuntimeException('source_price_results mövcud deyil.');
        }

        $select = [
            'price_sources.id as source_id',
            'price_positions.id as position_id',
        ];

        if (Schema::hasColumn('price_positions', 'name')) {
            $select[] = 'price_positions.name as position_name';
        }

        if (Schema::hasColumn('price_sources', 'links_json')) {
            $select[] = 'price_sources.links_json';
        }

        if (Schema::hasColumn('price_sources', 'options_json')) {
            $select[] = 'price_sources.options_json';
        }

        if (Schema::hasColumn('price_sources', 'source_type')) {
            $select[] = 'price_sources.source_type';
        }

        if (Schema::hasColumn('price_sources', 'source_url')) {
            $select[] = 'price_sources.source_url';
        }

        if (Schema::hasColumn('price_sources', 'source_name')) {
            $select[] = 'price_sources.source_name';
        }

        if (Schema::hasColumn('price_sources', 'source_config')) {
            $select[] = 'price_sources.source_config';
        }

        $row = DB::table('price_sources')
            ->join('price_positions', 'price_positions.id', '=', 'price_sources.position_id')
            ->where('price_sources.id', $this->priceSourceId)
            ->select($select)
            ->first();

        if ($row === null) {
            throw new \RuntimeException("price_source tapılmadı: {$this->priceSourceId}");
        }

        if (Schema::hasColumn('price_sources', 'source_type') && (string) ($row->source_type ?? '') !== 'wolt') {
            $this->logBrandSkipped($row, 'ParseWoltSourceJob yalnız price_sources.source_type = wolt sətirləri üçün nəzərdə tutulub.', []);

            return;
        }

        $stage = 'resolve_config';
        $config = [];
        if (property_exists($row, 'source_config') && $row->source_config !== null && $row->source_config !== '') {
            $decoded = json_decode((string) $row->source_config, true);
            $config = is_array($decoded) ? $decoded : [];
        }
        if (property_exists($row, 'options_json') && $row->options_json !== null && $row->options_json !== '') {
            $decodedOpt = is_string($row->options_json)
                ? json_decode($row->options_json, true)
                : (is_array($row->options_json) ? $row->options_json : null);
            if (is_array($decodedOpt) && $decodedOpt !== []) {
                $config = array_merge($config, $decodedOpt);
            }
        }

        $columnVariant = null;
        if (isset($config['variant']) && is_string($config['variant']) && trim($config['variant']) !== '') {
            $columnVariant = trim((string) $config['variant']);
        }

        $stage = 'resolve_links';
        $links = property_exists($row, 'links_json')
            ? PriceSourceLinks::decode($row->links_json)
            : [];

        if (property_exists($row, 'source_url')) {
            $legacy = trim((string) ($row->source_url ?? ''));
            if ($legacy !== '' && PriceSourceLinks::isWoltUrl($legacy)) {
                $links[] = $legacy;
            }
        }

        /** @var list<string> $woltPrimaries sıra saxlanılır, eyni URL təkrarlanmasın */
        $woltPrimaries = [];
        $seenPrimary = [];
        foreach ($links as $rawLink) {
            if (! is_string($rawLink)) {
                continue;
            }
            $primaryUrl = trim($rawLink);
            if ($primaryUrl === '' || ! PriceSourceLinks::isWoltUrl($primaryUrl)) {
                continue;
            }
            $k = mb_strtolower($primaryUrl);
            if (isset($seenPrimary[$k])) {
                continue;
            }
            $seenPrimary[$k] = true;
            $woltPrimaries[] = $primaryUrl;
        }

        if ($woltPrimaries === []) {
            $this->logBrandSkipped($row, 'links_json-da Wolt URL yoxdur (və ya legacy source_url boşdur).', []);

            return;
        }

        try {
            $tz = (string) config('parsers.snapshot_timezone', 'Asia/Baku');
            $date = now($tz)->toDateString();

            /** @var list<array{details: array, successfulUrl: string, primaryUrl: string, tried: list<string>}> */
            $successes = [];
            /** @var list<array{primary: string, urls_tried: list<string>}> */
            $attemptLog = [];
            /** @var list<array{primary: string, urls_tried: list<string>}> */
            $failedPrimaries = [];

            $stage = 'fetch_prices';
            foreach ($woltPrimaries as $primaryUrl) {
                $tried = [];
                $details = null;
                $successfulUrl = null;

                $expanded = $scraper->expandWoltFetchUrls($primaryUrl, $columnVariant, $config);
                foreach ($expanded as $u) {
                    $tried[] = $u;
                    try {
                        $details = $scraper->fetchPrice($u, $config);
                    } catch (Throwable $e) {
                        $ctx = [
                            'job' => 'ParseWoltSourceJob',
                            'stage' => 'fetch_price',
                            'primary_url' => $primaryUrl,
                            'url' => $u,
                            'variant' => $columnVariant,
                        ];
                        $this->recordParserRunError(
                            $this->parserRunId,
                            'wolt_url_exception',
                            "Wolt URL parse zamanı exception: {$u}",
                            (int) $row->position_id,
                            $this->priceSourceId,
                            $e::class,
                            $this->buildThrowableContext($e, $ctx),
                        );
                        $details = null;
                    }
                    if ($details !== null) {
                        $successfulUrl = $u;

                        break;
                    }
                }

                $attemptLog[] = ['primary' => $primaryUrl, 'urls_tried' => $tried];

                if ($details !== null && $successfulUrl !== null) {
                    $successes[] = [
                        'details' => $details,
                        'successfulUrl' => $successfulUrl,
                        'primaryUrl' => $primaryUrl,
                        'tried' => $tried,
                    ];
                } else {
                    $failedPrimaries[] = ['primary' => $primaryUrl, 'urls_tried' => $tried];
                }
            }

            foreach ($failedPrimaries as $fail) {
                $this->recordParserRunError(
                    $this->parserRunId,
                    'wolt_primary_failed',
                    'Wolt linkindən qiymət çıxarmaq alınmadı (primary + expanded urls).',
                    (int) $row->position_id,
                    $this->priceSourceId,
                    null,
                    [
                        'job' => 'ParseWoltSourceJob',
                        'stage' => 'fetch_prices',
                        'source_name' => property_exists($row, 'source_name') ? ($row->source_name ?? null) : null,
                        'primary_url' => $fail['primary'] ?? null,
                        'urls_tried' => $fail['urls_tried'] ?? [],
                        'variant' => $columnVariant,
                    ],
                );
            }

            if ($successes === []) {
                $positionName = $this->decodePositionName($row);
                $analysis = $this->analyzeNoPriceFailure($attemptLog, $columnVariant, $config);
                $this->recordParserRunError(
                    $this->parserRunId,
                    'wolt_brand_no_price',
                    $analysis['message'],
                    (int) $row->position_id,
                    $this->priceSourceId,
                    null,
                    [
                        'job' => 'ParseWoltSourceJob',
                        'stage' => $stage,
                        'source_name' => property_exists($row, 'source_name') ? ($row->source_name ?? null) : null,
                        'position_title' => PriceSourceLinks::titleFromPositionName($positionName),
                        'variant' => $columnVariant,
                        'config' => $config,
                        'attempts' => $attemptLog,
                        'analysis' => $analysis,
                    ]
                );
                Log::info('ParseWoltSourceJob: brand skipped (no price)', [
                    'source_id' => $this->priceSourceId,
                    'source_name' => property_exists($row, 'source_name') ? $row->source_name : null,
                ]);

                return;
            }

            $stage = 'write_results';
            $this->deleteWoltSourcePriceResultsForSourceAndDate((int) $row->position_id, (int) $row->source_id, $date);

            $positionName = $this->decodePositionName($row);
            $baseTitle = PriceSourceLinks::titleFromPositionName($positionName)
                ?? (property_exists($row, 'source_name') && $row->source_name !== null ? mb_substr((string) $row->source_name, 0, 500) : null);

            $now = now();
            $rows = [];

            foreach ($successes as $hit) {
                $details = $hit['details'];
                $successfulUrl = $hit['successfulUrl'];
                $primaryUrl = $hit['primaryUrl'];

                $payloadMeta = [
                    'source_name' => property_exists($row, 'source_name') ? $row->source_name : null,
                    'primary_link' => $primaryUrl,
                    'successful_url' => $successfulUrl,
                    'urls_tried' => $hit['tried'],
                    'extraction' => $details['extraction'] ?? 'wolt_pipeline',
                    'merged' => $details['merged'],
                ];

                $rows[] = [
                    'parser_run_id' => $this->parserRunId,
                    'position_id' => (int) $row->position_id,
                    'source_id' => (int) $row->source_id,
                    'result_date' => $date,
                    'external_item_id' => $extractor->parseItemIdFromUrl($successfulUrl),
                    'title' => $baseTitle,
                    'raw_price' => $details['raw_price'],
                    'raw_area' => null,
                    'normalized_price' => $details['normalized'],
                    'currency' => 'AZN',
                    'is_outlier' => false,
                    'is_valid' => true,
                    'raw_payload' => json_encode($payloadMeta, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('source_price_results')->insert($chunk);
            }
        } catch (Throwable $e) {
            $ctx = [
                'job' => 'ParseWoltSourceJob',
                'stage' => $stage,
                'source_name' => property_exists($row, 'source_name') ? ($row->source_name ?? null) : null,
                'variant' => $columnVariant,
                'config' => $config,
                'wolt_primary_urls' => $woltPrimaries ?? [],
            ];
            $this->recordParserRunError(
                $this->parserRunId,
                'parse_wolt_source',
                $e->getMessage(),
                (int) $row->position_id,
                $this->priceSourceId,
                $e::class,
                $this->buildThrowableContext($e, $ctx),
            );
            throw $e;
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function decodePositionName(object $row): ?array
    {
        if (! property_exists($row, 'position_name') || $row->position_name === null || $row->position_name === '') {
            return null;
        }

        $decoded = json_decode((string) $row->position_name, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $urls
     */
    private function logBrandSkipped(object $row, string $reason, array $urls): void
    {
        $positionName = $this->decodePositionName($row);
        $this->recordParserRunError(
            $this->parserRunId,
            'wolt_brand_skipped',
            $reason,
            (int) $row->position_id,
            $this->priceSourceId,
            null,
            [
                'source_name' => property_exists($row, 'source_name') ? ($row->source_name ?? null) : null,
                'position_title' => PriceSourceLinks::titleFromPositionName($positionName),
                'urls' => $urls,
            ]
        );
    }

    /**
     * @param  list<array{primary: string, urls_tried: list<string>}>  $attemptLog
     * @param  array<string, mixed>  $config
     * @return array{message: string, hints: list<string>, stats: array<string, mixed>}
     */
    private function analyzeNoPriceFailure(array $attemptLog, ?string $columnVariant, array $config): array
    {
        $allTried = [];
        $primaryCount = 0;
        $triedCount = 0;
        $withItemId = 0;
        $withVariantPlaceholder = 0;

        foreach ($attemptLog as $a) {
            $primaryCount++;
            foreach (($a['urls_tried'] ?? []) as $u) {
                $triedCount++;
                $allTried[] = (string) $u;
                if (preg_match('/itemid-[a-f0-9]{24}\\b/i', (string) $u)) {
                    $withItemId++;
                }
                if (str_contains((string) $u, '{variant}') || str_contains((string) $u, '{VARIANT}')) {
                    $withVariantPlaceholder++;
                }
            }
        }

        $hints = [];
        $hints[] = 'Wolt səhifəsi HTTP 403/404 verə bilər və ya bot qorumasına düşə bilər (HTML boş qayıdır).';
        $hints[] = 'Səhifə SSR/HTML strukturunu dəyişə bilər; modal JSON-LD/embedded menyüdən qiymət tapılmır.';
        $hints[] = 'Məhsul mövcud olmaya bilər (venue-də silinib) və ya link başqa şəhərə/venue-ə aiddir.';
        if ($withItemId === 0) {
            $hints[] = 'URL-lərdə `itemid-...` yoxdur; embedded venue menu pipeline daha zəif işləyə bilər.';
        }
        if ($withVariantPlaceholder > 0) {
            $hints[] = 'URL-də `{variant}` placeholder qalıb; `variant` və ya `url_template` düzgün apply olunmayıb.';
        }
        if (($config['price_selector'] ?? null) !== null) {
            $hints[] = '`price_selector` verilibsə, selector səhv ola bilər (node tapılmır).';
        }

        $stats = [
            'primary_url_count' => $primaryCount,
            'total_urls_tried' => $triedCount,
            'urls_with_itemid' => $withItemId,
            'urls_with_variant_placeholder' => $withVariantPlaceholder,
            'variant' => $columnVariant,
        ];

        $msg = 'Qiymət tapılmadı: bütün Wolt URL cəhdlərində HTML-dən qiymət çıxarma mümkün olmadı.';
        if ($primaryCount > 0) {
            $msg .= " (primary={$primaryCount}, tried={$triedCount})";
        }

        return [
            'message' => $msg,
            'hints' => $hints,
            'stats' => $stats,
        ];
    }
}
