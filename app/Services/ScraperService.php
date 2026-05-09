<?php

namespace App\Services;

use App\Services\Scraper\BinaGraphqlListingFetcher;
use App\Services\Scraper\BinaHtmlListingParser;
use App\Services\Scraper\WoltHtmlPriceExtractor;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class ScraperService
{
    public function __construct(
        private WoltHtmlPriceExtractor $woltExtractor,
        private BinaHtmlListingParser $binaParser,
        private BinaGraphqlListingFetcher $binaGraphqlFetcher,
    ) {}

    public function fetchWoltProduct(string $url): ?float
    {
        $details = $this->fetchWoltProductDetails($url);

        return $details['normalized'] ?? null;
    }

    /**
     * @return array{normalized: float, raw_price: float, merged: array{discounted_price: ?string, original_price: ?string, unit_price: ?string}, extraction?: string}|null
     */
    public function fetchWoltProductDetails(string $url): ?array
    {
        $html = $this->fetchWoltHtml($url);
        if ($html === null || $html === '') {
            return null;
        }

        return $this->extractPriceFromHtml($html, $url, []);
    }

    /**
     * Konfiqasiyaya əsasən qiymət: `price_selector` → CSS, yoxdursa Wolt modal/JSON-LD pipeline.
     *
     * @param  array<string, mixed>  $config
     * @return array{normalized: float, raw_price: float, merged: array{discounted_price: ?string, original_price: ?string, unit_price: ?string}, extraction: string}|null
     */
    public function fetchPrice(string $url, array $config = []): ?array
    {
        $html = $this->fetchWoltHtml($url);
        if ($html === null || $html === '') {
            return null;
        }

        return $this->extractPriceFromHtml($html, $url, $config);
    }

    /**
     * Wolt mənbəsi üçün sınanacaq URL siyahısı (əsas + fallback_urls + fallback_variants + url_template).
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function expandWoltFetchUrls(string $sourceUrl, ?string $columnVariant, array $config): array
    {
        $urls = [];
        $base = trim($sourceUrl);

        if ($base !== '') {
            $urls[] = $this->injectVariantPlaceholder($base, $columnVariant);
        }

        foreach ($config['fallback_urls'] ?? [] as $fu) {
            $u = trim((string) $fu);
            if ($u !== '') {
                $urls[] = $this->injectVariantPlaceholder($u, $columnVariant);
            }
        }

        $template = isset($config['url_template']) ? trim((string) $config['url_template']) : '';
        foreach ($config['fallback_variants'] ?? [] as $fv) {
            $fvStr = (string) $fv;
            if ($template !== '' && (str_contains($template, '{variant}') || str_contains($template, '{VARIANT}'))) {
                $urls[] = $this->injectVariantPlaceholder($template, $fvStr);

                continue;
            }

            if ($base !== '' && (str_contains($base, '{variant}') || str_contains($base, '{VARIANT}'))) {
                $urls[] = $this->injectVariantPlaceholder($base, $fvStr);

                continue;
            }

            if ($template !== '') {
                $urls[] = $this->appendVariantQuery($template, $fvStr, $config);

                continue;
            }

            if ($base !== '') {
                $urls[] = $this->appendVariantQuery($base, $fvStr, $config);
            }
        }

        $out = [];
        foreach ($urls as $u) {
            if ($u !== '' && ! in_array($u, $out, true)) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /**
     * @return list<array{external_item_id: string, zone: string, location_id: string|null, price_total: float|null, area_m2: float|null, unit_value: float}>
     */
    public function fetchBinaListings(string $url, string $mode = 'sale'): array
    {
        $maxPages = (int) config('scraper.bina.max_pages', 3);
        $maxCards = (int) config('scraper.bina.max_item_cards', 90);

        if ($maxCards <= 0) {
            return [];
        }

        if (config('scraper.bina.use_graphql', true)) {
            try {
                $gqlRows = $this->binaGraphqlFetcher->fetchListings($url, $mode, $maxCards, $maxPages);
                if ($gqlRows !== []) {
                    return array_slice($gqlRows, 0, $maxCards);
                }
            } catch (Throwable $e) {
                Log::warning('Bina GraphQL listing uğursuz, HTML fallback', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $listingRows = [];
        $fetchedHtmlParts = [];
        $baseUrl = rtrim($url, '/');
        $sourceStartedAt = microtime(true);

        for ($page = 1; $page <= $maxPages; $page++) {
            if (count($listingRows) >= $maxCards) {
                break;
            }
            if ((microtime(true) - $sourceStartedAt) > 1800) {
                throw new \RuntimeException('Bina source timeout exceeded 30 minutes');
            }

            $pageUrl = $page === 1 ? $baseUrl : $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'page='.$page;

            $response = Http::timeout(1800)
                ->retry(1, 250)
                ->withHeaders($this->binaHttpHeaders())
                ->get($pageUrl);

            if ($response->failed()) {
                throw new \RuntimeException("HTTP {$response->status()} ({$pageUrl})");
            }

            $body = $response->body();
            $fetchedHtmlParts[] = $body;

            $remaining = $maxCards - count($listingRows);
            $chunk = $this->binaParser->parseHtmlPage($body, $mode, $remaining);
            foreach ($chunk as $row) {
                $listingRows[] = $row;
            }

            if (count($chunk) === 0) {
                break;
            }
        }

        if ($listingRows === [] && $fetchedHtmlParts !== []) {
            $legacyIdx = 0;
            foreach ($fetchedHtmlParts as $part) {
                $legacy = $this->binaParser->parseLegacyFallback($part, $mode, 0, $legacyIdx);
                $legacyIdx += 10000;
                foreach ($legacy as $row) {
                    $listingRows[] = $row;
                    if (count($listingRows) >= $maxCards) {
                        break 2;
                    }
                }
            }
        }

        return array_slice($listingRows, 0, $maxCards);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{normalized: float, raw_price: float, merged: array{discounted_price: ?string, original_price: ?string, unit_price: ?string}, extraction: string}|null
     */
    private function extractPriceFromHtml(string $html, string $url, array $config): ?array
    {
        $selector = $config['price_selector'] ?? null;
        if (is_string($selector) && trim($selector) !== '') {
            $css = trim($selector);
            $fromCss = $this->extractPriceWithCssSelector($html, $css);
            if ($fromCss !== null) {
                $p = round($fromCss, 4);

                return [
                    'normalized' => $p,
                    'raw_price' => $p,
                    'merged' => [
                        'discounted_price' => null,
                        'original_price' => (string) $p,
                        'unit_price' => null,
                    ],
                    'extraction' => 'css:'.$css,
                ];
            }
        }

        $merged = $this->woltExtractor->extractMergedPrices($html, $url);
        $primary = $this->woltExtractor->pickPrimaryPrice($merged);
        if ($primary === null) {
            return null;
        }

        $rawMain = $merged['original_price'] ?? $merged['unit_price'];
        $rawPrice = $rawMain !== null && $rawMain !== '' && is_numeric($rawMain)
            ? round((float) $rawMain, 4)
            : $primary;

        return [
            'normalized' => $primary,
            'raw_price' => $rawPrice,
            'merged' => $merged,
            'extraction' => 'wolt_pipeline',
        ];
    }

    private function extractPriceWithCssSelector(string $html, string $selector): ?float
    {
        try {
            $crawler = new Crawler($html);
            $node = $crawler->filter($selector)->first();
        } catch (Throwable) {
            return null;
        }

        if ($node->count() === 0) {
            return null;
        }

        $text = $node->text('');

        return $this->parseMoneyFromText($text);
    }

    private function parseMoneyFromText(string $text): ?float
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d{1,3}(?:[\s,.]\d{3})*(?:[,.]\d{1,2})?)/u', $text, $m)) {
            $raw = $m[1];
            $normalized = str_replace([' ', ','], ['', '.'], $raw);
            if (substr_count($normalized, '.') > 1) {
                $normalized = str_replace('.', '', substr($normalized, 0, strrpos($normalized, '.'))).substr($normalized, strrpos($normalized, '.'));
            }
            if (is_numeric($normalized)) {
                return round((float) $normalized, 4);
            }
        }

        return null;
    }

    private function injectVariantPlaceholder(string $url, ?string $variant): string
    {
        if ($variant === null || $variant === '') {
            return $url;
        }

        $enc = rawurlencode($variant);

        return str_replace(['{variant}', '{VARIANT}'], [$enc, $enc], $url);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function appendVariantQuery(string $url, string $variantValue, array $config): string
    {
        $key = (string) ($config['variant_query_key'] ?? 'variant');
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.rawurlencode($key).'='.rawurlencode($variantValue);
    }

    private function fetchWoltHtml(string $url): ?string
    {
        $useWebDriver = (bool) config('scraper.wolt.use_webdriver', true);

        if ($useWebDriver) {
            try {
                return $this->fetchHtmlViaWebDriver($url);
            } catch (Throwable $e) {
                Log::warning('scraper.wolt.webdriver_failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                if (! (bool) config('scraper.wolt.http_fallback', true)) {
                    throw $e;
                }
            }
        }

        $response = Http::timeout(120)
            ->retry(1, 250)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'az-AZ,az;q=0.9,en;q=0.8,ru;q=0.7',
            ])
            ->get($url);

        return $response->successful() ? $response->body() : null;
    }

    private function fetchHtmlViaWebDriver(string $url): string
    {
        $serverUrl = (string) config('scraper.chrome_driver_url', 'http://127.0.0.1:9515');
        $wait = (int) config('scraper.wolt.page_wait_seconds', 2);

        $options = new ChromeOptions;
        $options->addArguments([
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ]);

        $capabilities = $options->toCapabilities();
        $driver = RemoteWebDriver::create($serverUrl, $capabilities);

        try {
            $driver->get($url);
            if ($wait > 0) {
                sleep($wait);
            }

            return $driver->getPageSource();
        } finally {
            $driver->quit();
        }
    }

    /**
     * @return array<string, string>
     */
    private function binaHttpHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'az-AZ,az;q=0.9,en;q=0.8,ru;q=0.7',
        ];
    }
}
