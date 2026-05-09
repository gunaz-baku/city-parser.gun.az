<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesParserRunLifecycle;
use App\Services\ScraperService;
use App\Support\PriceSourceLinks;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ParseBinaSourceJob implements ShouldQueue
{
    use Batchable, Dispatchable, HandlesParserRunLifecycle, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(
        public int $parserRunId,
        public int $priceSourceId,
    ) {
        $this->onConnection('redis');
        $this->onQueue('parser-bina');
    }

    public function handle(ScraperService $scraper): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $stage = 'init';

        if (! Schema::hasTable('source_price_results')) {
            throw new \RuntimeException('source_price_results mövcud deyil.');
        }

        $select = ['id', 'position_id'];
        if (Schema::hasColumn('price_sources', 'source_type')) {
            $select[] = 'source_type';
        }
        if (Schema::hasColumn('price_sources', 'links_json')) {
            $select[] = 'links_json';
        }
        if (Schema::hasColumn('price_sources', 'options_json')) {
            $select[] = 'options_json';
        }
        if (Schema::hasColumn('price_sources', 'source_url')) {
            $select[] = 'source_url';
        }
        if (Schema::hasColumn('price_sources', 'source_config')) {
            $select[] = 'source_config';
        }

        $source = DB::table('price_sources')
            ->where('id', $this->priceSourceId)
            ->select($select)
            ->first();

        if ($source === null) {
            throw new \RuntimeException("price_source tapılmadı: {$this->priceSourceId}");
        }

        if (Schema::hasColumn('price_sources', 'source_type') && (string) ($source->source_type ?? '') !== 'bina') {
            throw new \RuntimeException('ParseBinaSourceJob yalnız price_sources.source_type = bina sətirləri üçün nəzərdə tutulub.');
        }

        $stage = 'resolve_links';
        $links = property_exists($source, 'links_json')
            ? PriceSourceLinks::decode($source->links_json)
            : [];

        $binaUrls = PriceSourceLinks::binaUrls($links);

        if (property_exists($source, 'source_url')) {
            $legacy = trim((string) ($source->source_url ?? ''));
            if ($legacy !== '' && str_contains(mb_strtolower($legacy), 'bina.az')) {
                $binaUrls[] = $legacy;
            }
        }

        /** @var list<string> $binaUrlsUnique */
        $binaUrlsUnique = [];
        $seen = [];
        foreach ($binaUrls as $u) {
            $k = mb_strtolower($u);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $binaUrlsUnique[] = $u;
        }
        $binaUrls = $binaUrlsUnique;

        if ($binaUrls === []) {
            throw new \RuntimeException('Bina links_json-da bina.az URL yoxdur (və ya legacy source_url boşdur).');
        }

        $stage = 'resolve_config';
        $config = [];
        if (property_exists($source, 'source_config') && $source->source_config !== null && $source->source_config !== '') {
            $decoded = json_decode((string) $source->source_config, true);
            $config = is_array($decoded) ? $decoded : [];
        }
        if (property_exists($source, 'options_json') && $source->options_json !== null && $source->options_json !== '') {
            $decodedOpt = is_string($source->options_json)
                ? json_decode($source->options_json, true)
                : (is_array($source->options_json) ? $source->options_json : null);
            if (is_array($decodedOpt) && $decodedOpt !== []) {
                $config = array_merge($config, $decodedOpt);
            }
        }

        try {
            $stage = 'fetch_listings';
            $listingRows = [];
            foreach ($binaUrls as $listingUrl) {
                $mode = $this->resolveBinaModeForUrl($listingUrl, $config);
                $part = $scraper->fetchBinaListings($listingUrl, $mode);
                foreach ($part as $row) {
                    $row['_bina_mode'] = $mode;
                    $listingRows[] = $row;
                }
            }

            if ($listingRows === []) {
                throw new \RuntimeException('Bina listing boşdur (bütün linklər üzrə HTML).');
            }

            $stage = 'write_results';
            $tz = (string) config('parsers.snapshot_timezone', 'Asia/Baku');
            $date = now($tz)->toDateString();
            $positionId = (int) $source->position_id;

            $this->deleteBinaSourcePriceResultsForPositionDate($positionId, $date);

            $this->insertBinaSourceResults(
                $this->parserRunId,
                $positionId,
                (int) $source->id,
                $listingRows,
                $date
            );
        } catch (Throwable $e) {
            $ctx = [
                'job' => 'ParseBinaSourceJob',
                'stage' => $stage,
                'listing_urls' => $binaUrls,
                'config' => $config,
            ];
            $this->recordParserRunError(
                $this->parserRunId,
                'parse_bina_source',
                $e->getMessage(),
                (int) $source->position_id,
                $this->priceSourceId,
                $e::class,
                $this->buildThrowableContext($e, $ctx),
            );
            throw $e;
        }
    }

    /**
     * @param  list<array{external_item_id: string, zone: string, location_id: string|null, price_total: float|null, area_m2: float|null, unit_value: float}>  $listingRows
     */
    private function insertBinaSourceResults(
        int $parserRunId,
        int $positionId,
        int $sourceId,
        array $listingRows,
        string $date,
    ): void {
        $now = now();
        $byKey = [];
        foreach ($listingRows as $row) {
            $id = mb_substr((string) ($row['external_item_id'] ?? ''), 0, 64);
            if ($id === '') {
                $id = 'row-'.substr(md5((string) json_encode($row)), 0, 20);
            }
            $row['external_item_id'] = $id;
            $byKey[$id] = $row;
        }

        $batch = [];
        foreach ($byKey as $row) {
            $batch[] = [
                'parser_run_id' => $parserRunId,
                'position_id' => $positionId,
                'source_id' => $sourceId,
                'result_date' => $date,
                'external_item_id' => $row['external_item_id'],
                'title' => mb_substr((string) ($row['zone'] ?? ''), 0, 500),
                'raw_price' => $row['price_total'],
                'raw_area' => $row['area_m2'],
                'normalized_price' => $row['unit_value'],
                'currency' => 'AZN',
                'is_outlier' => false,
                'is_valid' => true,
                'raw_payload' => json_encode([
                    'mode' => (string) ($row['_bina_mode'] ?? 'sale'),
                    'zone' => $row['zone'] ?? null,
                    'location_id' => $row['location_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 250) as $chunk) {
            DB::table('source_price_results')->insert($chunk);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveBinaModeForUrl(string $url, array $config): string
    {
        if (isset($config['mode']) && in_array((string) $config['mode'], ['rent', 'sale'], true)) {
            return (string) $config['mode'];
        }

        $lower = mb_strtolower($url);

        return str_contains($lower, '/kiraye/') || str_contains($lower, 'kiraye')
            ? 'rent'
            : 'sale';
    }
}
