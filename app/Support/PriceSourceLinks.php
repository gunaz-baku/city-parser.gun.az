<?php

namespace App\Support;

/**
 * price_sources.links_json — URL siyahısı (CSV və ya admin).
 */
final class PriceSourceLinks
{
    /**
     * @return list<string>
     */
    public static function decode(mixed $linksJson): array
    {
        if (is_array($linksJson)) {
            return self::normalizeStringList($linksJson);
        }

        if ($linksJson === null || $linksJson === '') {
            return [];
        }

        if (is_string($linksJson)) {
            $decoded = json_decode($linksJson, true);

            return is_array($decoded) ? self::normalizeStringList($decoded) : [];
        }

        return [];
    }

    /**
     * @param  array<mixed>  $items
     * @return list<string>
     */
    private static function normalizeStringList(array $items): array
    {
        $out = [];
        foreach ($items as $v) {
            if (! is_string($v)) {
                continue;
            }
            $s = trim($v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    public static function isWoltUrl(string $url): bool
    {
        return str_contains(mb_strtolower($url), 'wolt.com');
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    public static function woltUrls(array $urls): array
    {
        return array_values(array_filter($urls, static fn (string $u): bool => self::isWoltUrl($u)));
    }

    /**
     * @param  list<string>  $urls
     */
    public static function hasWoltUrl(array $urls): bool
    {
        return self::woltUrls($urls) !== [];
    }

    /**
     * @param  list<string>  $urls
     */
    public static function firstBinaListingUrl(array $urls): ?string
    {
        $all = self::binaUrls($urls);

        return $all[0] ?? null;
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    public static function binaUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $u) {
            if (! is_string($u)) {
                continue;
            }
            $s = trim($u);
            if ($s !== '' && str_contains(mb_strtolower($s), 'bina.az')) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * price_positions.name JSON → qısa başlıq (source_name əvəzi).
     *
     * @param  array<string, string>|null  $name
     */
    public static function titleFromPositionName(?array $name): ?string
    {
        if ($name === null || $name === []) {
            return null;
        }

        foreach (['en', 'az', 'ru'] as $k) {
            if (isset($name[$k]) && is_string($name[$k])) {
                $s = trim($name[$k]);
                if ($s !== '') {
                    return mb_substr($s, 0, 500);
                }
            }
        }

        return null;
    }
}
