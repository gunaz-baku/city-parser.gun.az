<?php

namespace App\Services\Scraper;

use Symfony\Component\DomCrawler\Crawler;

class WoltHtmlPriceExtractor
{
    private const MODAL_CLASS_SUBSTRING = 'cb_ModalBase_ScrollContainer';

    /** Footer-də cəmi (bəzi hallarda yalnız bu görünür) */
    private const DATA_TEST_TOTAL_PRICE = 'product-modal.total-price';

    /** product-modal blokundakı qiymət sinifləri (BEM / modul adları üçün altstrinq) */
    private const PRICE_CLASS_DISCOUNTED = 'discounted-price';

    private const PRICE_CLASS_ORIGINAL = 'original-price';

    private const PRICE_CLASS_UNIT = 'unit-price';

    private const PRODUCT_MODAL_SUBSTRING = 'product-modal';

    /**
     * Modal tipi:
     * - product-modal.original-price (əsas götürülür), product-modal.unit-price (fallback).
     * Qeyd: discount qiyməti intentionally ignor edilir.
     */
    private const DATA_TEST_DISCOUNTED_PRICE = 'product-modal.discounted-price';

    private const DATA_TEST_MAIN_PRICE = 'product-modal.price';

    private const DATA_TEST_ORIGINAL_PRICE = 'product-modal.original-price';

    private const DATA_TEST_UNIT_PRICE = 'product-modal.unit-price';

    /**
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    public function extractMergedPrices(string $html, string $url): array
    {
        $modalHtml = $this->extractProductModalHtml($html);
        $fromModal = $modalHtml !== null
            ? $this->extractProductModalPrices($modalHtml)
            : ['discounted_price' => null, 'original_price' => null, 'unit_price' => null];
        $fromJsonLd = $this->extractPricesFromJsonLd($html);
        $itemId = $this->parseWoltItemIdFromUrl($url);
        $fromEmbedded = $itemId !== null
            ? $this->extractPricesFromEmbeddedVenueMenu($html, $itemId)
            : ['discounted_price' => null, 'original_price' => null, 'unit_price' => null];

        return $this->mergeWoltPriceSources(
            $this->mergeWoltPriceSources($fromModal, $fromJsonLd),
            $fromEmbedded
        );
    }

    /**
     * @param  array{discounted_price: ?string, original_price: ?string, unit_price: ?string}  $prices
     */
    public function pickPrimaryPrice(array $prices): ?float
    {
        foreach (['original_price', 'unit_price'] as $key) {
            $v = $prices[$key] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            if (is_numeric($v)) {
                return round((float) $v, 4);
            }
        }

        return null;
    }

    public function parseItemIdFromUrl(?string $url): ?string
    {
        return $this->parseWoltItemIdFromUrl($url);
    }

    private function extractProductModalPrices(string $modalOuterHtml): array
    {
        $crawler = new Crawler($modalOuterHtml);
        $scope = $this->scopeProductModal($crawler);

        $hasDiscountedNode = $crawler->filter('[data-test-id="'.self::DATA_TEST_DISCOUNTED_PRICE.'"]')->count() > 0;

        if ($hasDiscountedNode) {
            $original = $this->extractPriceFromDataTestId($crawler, self::DATA_TEST_ORIGINAL_PRICE)
                ?? $this->extractPriceByClassSubstring($scope, self::PRICE_CLASS_ORIGINAL);
        } else {
            $original = $this->extractPriceFromDataTestId($crawler, self::DATA_TEST_MAIN_PRICE)
                ?? $this->extractPriceFromDataTestId($crawler, self::DATA_TEST_TOTAL_PRICE);
        }

        $unit = $this->extractPriceFromDataTestId($crawler, self::DATA_TEST_UNIT_PRICE)
            ?? $this->extractPriceByClassSubstring($scope, self::PRICE_CLASS_UNIT);

        return [
            'discounted_price' => null,
            'original_price' => $original,
            'unit_price' => $unit,
        ];
    }

    /**
     * Yalnız [data-test-id="product-modal.discounted-price"] — iç span-lardan məbləğ (etiket mətni ilə qarışmır).
     */
    private function extractPriceFromDiscountedPriceNode(Crawler $crawler): ?string
    {
        $nodes = $crawler->filter('[data-test-id="'.self::DATA_TEST_DISCOUNTED_PRICE.'"]');
        if ($nodes->count() === 0) {
            return null;
        }

        $root = $nodes->eq(0);
        $spans = $root->filter('span');
        for ($i = $spans->count() - 1; $i >= 0; $i--) {
            $parsed = $this->parsePriceToDecimalString($spans->eq($i)->text(''));
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $this->parsePriceToDecimalString($root->text(''));
    }

    private function scopeProductModal(Crawler $crawler): Crawler
    {
        $scoped = $crawler->filter('[data-test-id="product-modal"]')->first();
        if ($scoped->count() > 0) {
            return $scoped;
        }

        $scoped = $crawler->filter('[class*="'.self::PRODUCT_MODAL_SUBSTRING.'"]')->first();
        if ($scoped->count() > 0) {
            return $scoped;
        }

        return $crawler;
    }

    private function extractPriceFromDataTestId(Crawler $crawler, string $testId): ?string
    {
        $nodes = $crawler->filter('[data-test-id="'.$testId.'"]');
        if ($nodes->count() === 0) {
            return null;
        }

        return $this->parsePriceToDecimalString($nodes->eq(0)->text(''));
    }

    private function extractPriceByClassSubstring(Crawler $scope, string $classSubstring): ?string
    {
        $nodes = $scope->filter('[class*="'.$classSubstring.'"]');
        if ($nodes->count() === 0) {
            return null;
        }

        $text = $nodes->eq(0)->text('');

        return $this->parsePriceToDecimalString($text);
    }

    private function parsePriceToDecimalString(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

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
                return (string) round((float) $normalized, 4);
            }
        }

        return null;
    }

    /**
     * @param  array{discounted_price: ?string, original_price: ?string, unit_price: ?string}  $prices
     */
    private function allPricesEmpty(array $prices): bool
    {
        return ($prices['original_price'] ?? null) === null
            && ($prices['unit_price'] ?? null) === null;
    }

    /**
     * Modal + JSON-LD: hər sahə üçün əvvəl modal, boşdursa schema.org (SSR çox vaxt yalnız JSON-LD verir).
     *
     * @param  array{discounted_price: ?string, original_price: ?string, unit_price: ?string}  $fromModal
     * @param  array{discounted_price: ?string, original_price: ?string, unit_price: ?string}  $fromJsonLd
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    private function mergeWoltPriceSources(array $fromModal, array $fromJsonLd): array
    {
        $pick = static function (?string $a, ?string $b): ?string {
            return $a !== null && $a !== '' ? $a : $b;
        };

        return [
            'discounted_price' => null,
            'original_price' => $pick($fromModal['original_price'] ?? null, $fromJsonLd['original_price'] ?? null),
            'unit_price' => $pick($fromModal['unit_price'] ?? null, $fromJsonLd['unit_price'] ?? null),
        ];
    }

    /**
     * URL-dən itemid-XXXXXXXX (24 hex Mongo id).
     */
    private function parseWoltItemIdFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (preg_match('/itemid-([a-f0-9]{24})\b/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Dehidratə olunmuş venue menyüsü (query-state / böyük JSON) — məhsul obyekti:
     * "price" və "original_price" tam ədəd (1 AZN = 100 vahid).
     *
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    private function extractPricesFromEmbeddedVenueMenu(string $html, string $itemId): array
    {
        $empty = [
            'discounted_price' => null,
            'original_price' => null,
            'unit_price' => null,
        ];

        $needle = '"id":"'.$itemId.'"';
        $pos = strpos($html, $needle);
        if ($pos === false) {
            return $empty;
        }

        $nextItemPos = strpos($html, '}},{"id":"', $pos);
        $slice = $nextItemPos !== false
            ? substr($html, $pos, $nextItemPos - $pos + 2)
            : substr($html, $pos, 120000);

        if (! preg_match('/"price":(\d+)/', $slice, $pm)) {
            return $empty;
        }

        $priceMinor = (int) $pm[1];
        $origMinor = null;
        if (preg_match('/"original_price":(\d+)/', $slice, $om)) {
            $origMinor = (int) $om[1];
        }

        $unitMinor = null;
        if (preg_match('/"unit_price":\{"base":\d+,"original_price":(?:null|\d+),"price":(\d+),/', $slice, $um)) {
            $unitMinor = (int) $um[1];
        }

        if ($origMinor !== null && $origMinor > $priceMinor) {
            return [
                'original_price' => $this->woltMinorUnitsToDecimalString($origMinor),
                'unit_price' => $unitMinor !== null ? $this->woltMinorUnitsToDecimalString($unitMinor) : null,
                'discounted_price' => null,
            ];
        }

        return [
            'discounted_price' => null,
            'original_price' => $this->woltMinorUnitsToDecimalString($priceMinor),
            'unit_price' => $unitMinor !== null ? $this->woltMinorUnitsToDecimalString($unitMinor) : null,
        ];
    }

    private function woltMinorUnitsToDecimalString(int $minor): string
    {
        return (string) round($minor / 100, 4);
    }

    /**
     * Wolt səhifəsindəki <script type="application/ld+json"> — Product.offers (SEO üçün həmişə təxminən var).
     * Endirim: offers.price = endirimli, UnitPriceSpecification + StrikeThroughPrice = köhnə qiymət.
     *
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    private function extractPricesFromJsonLd(string $html): array
    {
        $empty = [
            'discounted_price' => null,
            'original_price' => null,
            'unit_price' => null,
        ];

        if (! preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return $empty;
        }

        foreach ($matches[1] as $raw) {
            $data = json_decode(trim($raw), true);
            if (! is_array($data)) {
                continue;
            }

            $product = $this->jsonLdFindProductNode($data);
            if ($product === null) {
                continue;
            }

            $offers = $product['offers'] ?? null;
            if (! is_array($offers)) {
                continue;
            }

            $mapped = $this->mapWoltJsonLdOffersBlock($offers);
            if (! $this->allPricesEmpty($mapped)) {
                return $mapped;
            }
        }

        return $empty;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonLdFindProductNode(array $data): ?array
    {
        $type = $data['@type'] ?? null;
        if ($type === 'Product' || (is_array($type) && in_array('Product', $type, true))) {
            return $data;
        }

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $found = $this->jsonLdFindProductNode($node);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offers
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    private function mapWoltJsonLdOffersBlock(array $offers): array
    {
        if ($offers !== [] && array_is_list($offers)) {
            foreach ($offers as $item) {
                if (is_array($item)) {
                    $m = $this->mapWoltJsonLdSingleOffer($item);
                    if (! $this->allPricesEmpty($m)) {
                        return $m;
                    }
                }
            }

            return [
                'discounted_price' => null,
                'original_price' => null,
                'unit_price' => null,
            ];
        }

        return $this->mapWoltJsonLdSingleOffer($offers);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{discounted_price: ?string, original_price: ?string, unit_price: ?string}
     */
    private function mapWoltJsonLdSingleOffer(array $offer): array
    {
        $mainRaw = $offer['price'] ?? null;
        $main = $this->normalizeScalarPriceToDecimalString($mainRaw);

        $strikeOriginal = null;
        $unitFromSpec = null;
        $spec = $offer['priceSpecification'] ?? null;

        $specList = [];
        if (is_array($spec)) {
            $specList = isset($spec['@type']) ? [$spec] : (array_is_list($spec) ? $spec : [$spec]);
        }

        foreach ($specList as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pType = (string) ($row['priceType'] ?? '');
            if (str_ends_with($pType, 'StrikeThroughPrice')) {
                $strikeOriginal = $this->normalizeScalarPriceToDecimalString($row['price'] ?? null);
            }
            $u = $this->tryExtractUnitPriceFromJsonLdSpec($row);
            if ($u !== null) {
                $unitFromSpec = $u;
            }
        }

        if ($strikeOriginal !== null && $main !== null) {
            return [
                'original_price' => $strikeOriginal,
                'unit_price' => $unitFromSpec,
                'discounted_price' => null,
            ];
        }

        return [
            'discounted_price' => null,
            'original_price' => $main,
            'unit_price' => $unitFromSpec,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function tryExtractUnitPriceFromJsonLdSpec(array $spec): ?string
    {
        $pType = (string) ($spec['priceType'] ?? '');
        if (str_contains($pType, 'UnitPrice') && ! str_ends_with($pType, 'StrikeThroughPrice')) {
            return $this->normalizeScalarPriceToDecimalString($spec['price'] ?? null);
        }

        return null;
    }

    private function normalizeScalarPriceToDecimalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (string) round((float) $value, 4);
        }

        return $this->parsePriceToDecimalString((string) $value);
    }

    /**
     * Modal HTML: əvvəlcə Wolt-un stabil data-test kökü (aside/div product-modal),
     * yoxdursa köhnə ScrollContainer (məs. cb_ModalBase_ScrollContainer_954).
     */
    private function extractProductModalHtml(string $html): ?string
    {
        $crawler = new Crawler($html);

        $byTestId = $crawler->filter('[data-test-id="product-modal"]')->first();
        if ($byTestId->count() > 0) {
            return $byTestId->outerHtml();
        }

        $scroll = $crawler->filter('[class*="'.self::MODAL_CLASS_SUBSTRING.'"]');
        if ($scroll->count() === 0) {
            return null;
        }

        $inner = $scroll->eq(0)->filter('[data-test-id="product-modal"]')->first();
        if ($inner->count() > 0) {
            return $inner->outerHtml();
        }

        return $scroll->eq(0)->outerHtml();
    }
}
