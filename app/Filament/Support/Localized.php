<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Operator UI: always pick the **English** (`en`) string from translated JSON / arrays.
 * Other locales are stored for the site API but are not used in Filament labels/tables.
 */
final class Localized
{
    public const DEFAULT_LOCALE = 'en';

    private const EMPTY = '—';

    /**
     * Display string for lists, selects, and tables: **English only**.
     *
     * Accepts:
     * - `array` translation map (e.g. `name` cast) — uses `en` (case-insensitive key)
     * - JSON **object** string — decoded, then `en` is read
     * - Plain scalar string — returned as-is (not treated as a locale map)
     * - `Model` — if it has `name` as array, uses that; otherwise falls back to empty placeholder
     *
     * @param  array<string, mixed>|mixed  $value
     */
    public static function en(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        if ($value instanceof Model) {
            $nameEn = $value->getAttribute('name_en');
            if (is_string($nameEn) && trim($nameEn) !== '') {
                return trim($nameEn);
            }

            return self::en($value->getAttribute('name'));
        }

        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '') {
                return self::EMPTY;
            }
            if (str_starts_with($trim, '{') || str_starts_with($trim, '[')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    return self::englishFromMap($decoded);
                }
            }

            return $trim;
        }

        if (is_array($value)) {
            return self::englishFromMap($value);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return self::EMPTY;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private static function englishFromMap(array $map): string
    {
        $normalized = self::normalizeLocaleKeys($map);
        $en = $normalized[self::DEFAULT_LOCALE] ?? null;

        if (is_string($en)) {
            $en = trim($en);
            if ($en !== '') {
                return $en;
            }
        }

        $az = $normalized['az'] ?? null;
        if (is_string($az)) {
            $az = trim($az);
            if ($az !== '') {
                return $az;
            }
        }

        return self::EMPTY;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private static function normalizeLocaleKeys(array $map): array
    {
        $out = [];
        foreach ($map as $key => $val) {
            if (! is_string($key)) {
                continue;
            }
            $out[strtolower($key)] = $val;
        }

        return $out;
    }
}
