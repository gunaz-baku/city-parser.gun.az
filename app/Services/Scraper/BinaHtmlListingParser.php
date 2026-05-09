<?php

namespace App\Services\Scraper;

use Symfony\Component\DomCrawler\Crawler;

class BinaHtmlListingParser
{
    private const ZONE_CLASS_A = 'fbvGTp';

    private const ZONE_CLASS_B = 'klEgBo';

    /**
     * @return list<array{external_item_id: string, zone: string, location_id: string|null, price_total: float|null, area_m2: float|null, unit_value: float}>
     */
    public function parseHtmlPage(string $html, string $mode, int $limit): array
    {
        return $this->extractItemCardRows($html, $mode, $limit);
    }

    /**
     * @return list<array{external_item_id: string, zone: string, location_id: null, price_total: float|null, area_m2: float|null, unit_value: float}>
     */
    public function parseLegacyFallback(string $html, string $mode, int $positionId, int $seed): array
    {
        return $this->listingRowsFromHtmlLegacy($html, $mode, $positionId, $seed);
    }

    private function extractItemCardRows(string $html, string $mode, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $crawler = new Crawler($html);
        
        $cards = $crawler->filter('div[data-cy="item-card"]');

        $n = min($cards->count(), $limit);
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $card = $cards->eq($i);
            $priceText = $card->filter('[data-cy="item-card-price-full"]')->first();
            if ($priceText->count() === 0) {
                continue;
            }

            $price = $this->parsePriceFullText($priceText->text(''));
            if ($price === null || $price <= 0) {
                continue;
            }

            // $zone = $this->normalizeZoneLabel($this->extractZoneFromCard($card));
            $extId = $this->extractBinaItemIdFromCard($card);
            if ($extId === '') {
                $extId = 'html-'.substr(md5($card->html('')), 0, 24);
            }

            if ($mode === 'rent') {
                $out[] = [
                    'external_item_id' => $extId,
                    // 'zone' => $zone,
                    'location_id' => null,
                    'price_total' => round($price, 2),
                    'area_m2' => null,
                    'unit_value' => round($price, 2),
                ];

                continue;
            }

            $area = $this->extractAreaSquareMetersFromCardHtml($card->html(''));
            if ($area === null || $area <= 0) {
                continue;
            }

            $out[] = [
                'external_item_id' => $extId,
                // 'zone' => $zone,
                'location_id' => null,
                'price_total' => round($price, 2),
                'area_m2' => round($area, 2),
                'unit_value' => round($price / $area, 2),
            ];
        }

        return $out;
    }

    private function extractBinaItemIdFromCard(Crawler $card): string
    {
        $links = $card->filter('a[href*="/items/"]');
        if ($links->count() > 0) {
            $href = (string) $links->first()->attr('href');
            if (preg_match('#/items/(\d+)#', $href, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    // private function extractZoneFromCard(Crawler $card): string
    // {
    //     $nodes = $card->filter('span.'.self::ZONE_CLASS_A.'.'.self::ZONE_CLASS_B);
    //     if ($nodes->count() > 0) {
    //         return trim($nodes->first()->text(''));
    //     }

    //     $fallback = $card->filterXPath('//span[contains(@class, "'.self::ZONE_CLASS_A.'") and contains(@class, "'.self::ZONE_CLASS_B.'")]');
    //     if ($fallback->count() > 0) {
    //         return trim($fallback->first()->text(''));
    //     }

    //     return '';
    // }

    // private function normalizeZoneLabel(string $zone): string
    // {
    //     $z = trim(preg_replace('/\s+/u', ' ', $zone) ?? '') ?? '';

    //     return $z !== '' ? $z : 'Naməlum';
    // }

    private function extractAreaSquareMetersFromCardHtml(string $cardHtml): ?float
    {
        if (preg_match('/(\d{1,4}(?:[.,]\d{1,2})?)\s*m(?:²|2)/iu', $cardHtml, $m)) {
            return $this->scalarToFloat($m[1] ?? '');
        }

        return null;
    }

    private function parsePriceFullText(string $text): ?float
    {
        $t = html_entity_decode($text, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/[^\d,.\s\x{00A0}]/u', '', $t) ?? $t;
        $t = str_replace(["\xc2\xa0", ' ', "\t"], '', $t);
        $t = str_replace('.', '', $t);
        $t = str_replace(',', '.', $t);

        if ($t === '' || ! is_numeric($t)) {
            return null;
        }

        return (float) $t;
    }

    private function scalarToFloat(string $raw): ?float
    {
        $normalized = str_replace([' ', '.'], ['', ''], $raw);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function listingRowsFromHtmlLegacy(string $html, string $mode, int $positionId, int $seed): array
    {
        $out = [];

        preg_match_all('/(\d{1,3}(?:[\s.]\d{3})*(?:,\d{1,2})?)\s*(?:AZN|₼)/iu', $html, $priceMatches);
        $prices = $this->toFloatArrayFromRawStrings($priceMatches[1] ?? []);

        if ($mode === 'rent') {
            foreach ($prices as $i => $price) {
                if ($price <= 0) {
                    continue;
                }
                $out[] = [
                    'external_item_id' => 'legacy-r-'.$positionId.'-'.$seed.'-'.$i,
                    'zone' => 'Naməlum',
                    'location_id' => null,
                    'price_total' => round($price, 2),
                    'area_m2' => null,
                    'unit_value' => round($price, 2),
                ];
            }

            return $out;
        }

        preg_match_all('/(\d{1,4}(?:[.,]\d{1,2})?)\s*m(?:²|2)/iu', $html, $areaMatches);
        $areas = $this->toFloatArrayFromRawStrings($areaMatches[1] ?? []);

        $count = min(count($prices), count($areas));
        for ($i = 0; $i < $count; $i++) {
            $price = $prices[$i];
            $area = $areas[$i];
            if ($price > 0 && $area > 0) {
                $out[] = [
                    'external_item_id' => 'legacy-s-'.$positionId.'-'.$seed.'-'.$i,
                    'zone' => 'Naməlum',
                    'location_id' => null,
                    'price_total' => round($price, 2),
                    'area_m2' => round($area, 2),
                    'unit_value' => round($price / $area, 2),
                ];
            }
        }

        return $out;
    }

    private function toFloatArrayFromRawStrings(array $inputs): array
    {
        $out = [];
        foreach ($inputs as $raw) {
            $normalized = str_replace([' ', '.'], ['', ''], (string) $raw);
            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized)) {
                $out[] = (float) $normalized;
            }
        }

        return $out;
    }
}
