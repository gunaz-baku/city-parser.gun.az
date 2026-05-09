<?php

namespace App\Services\Scraper;

use Illuminate\Support\Facades\Http;

/**
 * bina.az listing səhifələri Next.js-dir; SSR HTML-də kartlar yoxdur.
 * Rəsmi GraphQL: POST https://bina.az/graphql — itemsConnection.
 */
class BinaGraphqlListingFetcher
{
    /**
     * @return list<array{external_item_id: string, zone: string, location_id: string|null, price_total: float|null, area_m2: float|null, unit_value: float}>
     */
    public function fetchListings(string $listingUrl, string $mode, int $maxCards, int $maxPages): array
    {
        $filter = $this->parseListingFilterFromUrl($listingUrl);
        if ($filter === null) {
            return [];
        }

        $effectiveMode = $filter['leased'] ? 'rent' : 'sale';

        $endpoint = rtrim((string) config('scraper.bina.graphql_url', 'https://bina.az/graphql'), '/');
        $out = [];
        $after = null;

        for ($page = 0; $page < $maxPages; $page++) {
            if (count($out) >= $maxCards) {
                break;
            }

            $first = min(25, $maxCards - count($out));
            if ($first <= 0) {
                break;
            }

            $payload = $this->buildItemsQuery($filter, $first, $after);
            $response = Http::timeout(120)
                ->retry(1, 400)
                ->withHeaders($this->headers())
                ->asJson()
                ->post($endpoint, $payload);

            if ($response->failed()) {
                throw new \RuntimeException("Bina GraphQL HTTP {$response->status()}");
            }

            $json = $response->json();
            if (! is_array($json)) {
                break;
            }
            if (isset($json['errors']) && is_array($json['errors']) && $json['errors'] !== []) {
                $msg = $json['errors'][0]['message'] ?? 'GraphQL error';

                throw new \RuntimeException('Bina GraphQL: '.$msg);
            }

            $conn = $json['data']['itemsConnection'] ?? null;
            if (! is_array($conn)) {
                break;
            }

            $edges = $conn['edges'] ?? [];
            if (! is_array($edges)) {
                break;
            }

            foreach ($edges as $edge) {
                if (! is_array($edge)) {
                    continue;
                }
                $node = $edge['node'] ?? null;
                if (! is_array($node)) {
                    continue;
                }
                $row = $this->mapNodeToRow($node, $effectiveMode);
                if ($row !== null) {
                    $out[] = $row;
                }
                if (count($out) >= $maxCards) {
                    break 2;
                }
            }

            $pageInfo = $conn['pageInfo'] ?? [];
            $hasNext = is_array($pageInfo) && ($pageInfo['hasNextPage'] ?? false);
            $endCursor = is_array($pageInfo) ? ($pageInfo['endCursor'] ?? null) : null;
            if (! $hasNext || $endCursor === null || $endCursor === '') {
                break;
            }
            $after = (string) $endCursor;
        }

        return $out;
    }

    /**
     * @return array{cityId: string, categoryId: string, roomIds: list<string>, leased: bool}|null
     */
    public function parseListingFilterFromUrl(string $url): ?array
    {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? strtolower((string) $parts['path']) : '';
        if ($path === '') {
            return null;
        }

        if (! preg_match('#^/([^/]+)/#', $path, $cm)) {
            return null;
        }
        $citySlug = $cm[1];
        $cityId = match ($citySlug) {
            'baki' => '1',
            default => null,
        };
        if ($cityId === null) {
            return null;
        }

        if (preg_match('#/5-otaqli/?$#u', $path)) {
            $roomIds = ['5+'];
        } elseif (preg_match('#/(\d)-otaqli/?$#u', $path, $rm)) {
            $roomIds = [(string) $rm[1]];
        } else {
            return null;
        }

        if (str_contains($path, '/kiraye/')) {
            return [
                'cityId' => $cityId,
                'categoryId' => '1',
                'roomIds' => $roomIds,
                'leased' => true,
            ];
        }

        if (str_contains($path, 'kohne-tikili')) {
            $categoryId = '3';
        } elseif (str_contains($path, 'yeni-tikili')) {
            $categoryId = '2';
        } else {
            return null;
        }

        return [
            'cityId' => $cityId,
            'categoryId' => $categoryId,
            'roomIds' => $roomIds,
            'leased' => false,
        ];
    }

    /**
     * @param  array{cityId: string, categoryId: string, roomIds: list<string>, leased: bool}  $filter
     * @return array{query: string, variables?: array<string, mixed>}
     */
    private function buildItemsQuery(array $filter, int $first, ?string $after): array
    {
        $leased = $filter['leased'] ? 'true' : 'false';
        $rooms = array_map(static fn (string $r) => json_encode($r, JSON_UNESCAPED_UNICODE), $filter['roomIds']);
        $roomsIn = implode(', ', $rooms);

        $afterArg = $after === null ? '' : ', after: '.json_encode($after, JSON_UNESCAPED_UNICODE);

        $query = <<<GQL
            query {
              itemsConnection(
                filter: {
                  cityId: "{$filter['cityId']}"
                  categoryId: "{$filter['categoryId']}"
                  roomIds: [{$roomsIn}]
                  leased: {$leased}
                }
                first: {$first}{$afterArg}
              ) {
                pageInfo { endCursor hasNextPage }
                edges {
                  node {
                    id
                    price { total }
                    area { value }
                    location { name }
                  }
                }
              }
            }
            GQL;

        return ['query' => $query];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{external_item_id: string, zone: string, location_id: null, price_total: float|null, area_m2: float|null, unit_value: float}|null
     */
    private function mapNodeToRow(array $node, string $mode): ?array
    {
        $id = isset($node['id']) ? (string) $node['id'] : '';
        if ($id === '') {
            return null;
        }

        $priceNode = is_array($node['price'] ?? null) ? $node['price'] : [];
        $areaNode = is_array($node['area'] ?? null) ? $node['area'] : [];
        $locNode = is_array($node['location'] ?? null) ? $node['location'] : [];

        $priceTotal = isset($priceNode['total']) ? (float) $priceNode['total'] : null;
        if ($priceTotal === null || $priceTotal <= 0) {
            return null;
        }

        $area = isset($areaNode['value']) ? (float) $areaNode['value'] : null;
        $zone = isset($locNode['name']) ? trim((string) $locNode['name']) : '';
        if ($zone === '') {
            $zone = 'Naməlum';
        }

        if ($mode === 'rent') {
            return [
                'external_item_id' => $id,
                'zone' => $zone,
                'location_id' => null,
                'price_total' => round($priceTotal, 2),
                'area_m2' => $area !== null && $area > 0 ? round($area, 2) : null,
                'unit_value' => round($priceTotal, 2),
            ];
        }

        if ($area === null || $area <= 0) {
            return null;
        }

        return [
            'external_item_id' => $id,
            'zone' => $zone,
            'location_id' => null,
            'price_total' => round($priceTotal, 2),
            'area_m2' => round($area, 2),
            'unit_value' => round($priceTotal / $area, 2),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => 'parser.gun.az/1.0 (Bina GraphQL)',
            'Accept' => 'application/json',
        ];
    }
}
