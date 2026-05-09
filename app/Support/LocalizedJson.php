<?php

namespace App\Support;

/**
 * DB-də JSON translatable sahələr üçün vahid format: az, en, ru açarları.
 */
final class LocalizedJson
{
    public const LOCALES = ['az', 'en', 'ru'];

    /**
     * Məhsul adı: CSV AZ + RU; EN üçün `config/gun_az_en.php` (by_ru / by_az), yoxdursa latın AZ və ya RU.
     *
     * @return array{az: string, en: string, ru: string}
     */
    public static function productName(?string $nameAz, ?string $nameRu): array
    {
        $az = trim((string) ($nameAz ?? ''));
        $ru = trim((string) ($nameRu ?? ''));
        if ($az === '') {
            $az = $ru;
        }
        if ($ru === '') {
            $ru = $az;
        }

        $cfg = config('gun_az_en', []);
        $byRu = is_array($cfg) ? ($cfg['by_ru'] ?? []) : [];
        $byAz = is_array($cfg) ? ($cfg['by_az'] ?? []) : [];
        if (! is_array($byRu)) {
            $byRu = [];
        }
        if (! is_array($byAz)) {
            $byAz = [];
        }

        $en = $byRu[$ru] ?? $byAz[$az] ?? '';
        if ($en === '') {
            $en = self::fallbackEnglishProductName($az, $ru);
        }

        return ['az' => $az, 'en' => $en, 'ru' => $ru];
    }

    /**
     * Kateqoriya: emoji çıxarılmış RU başlıq (məs. «Молочные») → az/en/ru.
     *
     * @return array{az: string, en: string, ru: string}
     */
    public static function categoryName(string $strippedRussianTitle): array
    {
        $key = trim($strippedRussianTitle);
        $cfg = config('gun_az_en', []);
        $map = is_array($cfg) ? ($cfg['category'] ?? []) : [];
        if (! is_array($map)) {
            $map = [];
        }
        if ($key !== '' && isset($map[$key]) && is_array($map[$key])) {
            $t = $map[$key];
            $a = trim((string) ($t['az'] ?? ''));
            $e = trim((string) ($t['en'] ?? ''));
            $r = trim((string) ($t['ru'] ?? ''));

            return [
                'az' => $a !== '' ? $a : $key,
                'en' => $e !== '' ? $e : $key,
                'ru' => $r !== '' ? $r : $key,
            ];
        }

        return self::sameTriple($key);
    }

    private static function fallbackEnglishProductName(string $az, string $ru): string
    {
        if ($az !== '' && preg_match('/^[\p{Latin}\p{Common}0-9\s\-\.,%\'’]+$/u', $az)) {
            return $az;
        }

        return $az !== '' ? $az : $ru;
    }

    /**
     * Vahid ölçü/vahid sətri üçün (eyni mətn 3 dildə, tərcümə sonradan redaktə oluna bilər).
     *
     * @return array{az: array{label: string, variant: string}, en: array{label: string, variant: string}, ru: array{label: string, variant: string}}
     */
    public static function unitTriple(string $unitLabel, string $variant): array
    {
        $u = ['label' => $unitLabel, 'variant' => $variant];

        return ['az' => $u, 'en' => $u, 'ru' => $u];
    }

    /**
     * Kateqoriya kimi bir dildə gələn mətn üçün (3 açarda eyni mətn).
     *
     * @return array{az: string, en: string, ru: string}
     */
    public static function sameTriple(string $text): array
    {
        $t = trim($text);

        return ['az' => $t, 'en' => $t, 'ru' => $t];
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return array{az: string, en: string, ru: string}|null
     */
    public static function normalizeFlatName(?array $decoded): ?array
    {
        if ($decoded === null) {
            return null;
        }
        $az = isset($decoded['az']) ? trim((string) $decoded['az']) : '';
        $en = isset($decoded['en']) ? trim((string) $decoded['en']) : '';
        $ru = isset($decoded['ru']) ? trim((string) $decoded['ru']) : '';
        if ($en === '') {
            $en = $az !== '' ? $az : $ru;
        }
        if ($az === '') {
            $az = $ru !== '' ? $ru : $en;
        }
        if ($ru === '') {
            $ru = $az !== '' ? $az : $en;
        }

        return ['az' => $az, 'en' => $en, 'ru' => $ru];
    }

    /**
     * Köhnə unit JSON (label/variant və ya tək az) → az/en/ru.
     *
     * @param  array<string, mixed>|null  $decoded
     * @return array<string, mixed>|null
     */
    public static function normalizeUnit(?array $decoded): ?array
    {
        if ($decoded === null) {
            return null;
        }

        // Filament flat form / köhnə DB: az|en|ru string + üst səviyyədə variant
        $stringLabels = [];
        $onlyStringLocales = true;
        foreach (self::LOCALES as $loc) {
            if (! array_key_exists($loc, $decoded)) {
                continue;
            }
            $v = $decoded[$loc];
            if (is_string($v)) {
                $stringLabels[$loc] = trim($v);
            } elseif (is_array($v)) {
                $onlyStringLocales = false;

                break;
            }
        }
        if ($onlyStringLocales && $stringLabels !== []) {
            $variantTop = trim((string) ($decoded['variant'] ?? ''));
            $nestedFromStrings = [];
            foreach (self::LOCALES as $loc) {
                $nestedFromStrings[$loc] = [
                    'label' => $stringLabels[$loc] ?? '',
                    'variant' => $variantTop,
                ];
            }

            return self::normalizeUnitNested($nestedFromStrings);
        }

        if (isset($decoded['az'], $decoded['en'], $decoded['ru'])
            && is_array($decoded['az'])
            && is_array($decoded['en'])
            && is_array($decoded['ru'])) {
            return self::normalizeUnitNested($decoded);
        }
        if (isset($decoded['az']) && is_array($decoded['az']) && (! isset($decoded['en']) || ! isset($decoded['ru']))) {
            $u = $decoded['az'];

            return self::unitTriple(
                (string) ($u['label'] ?? ''),
                (string) ($u['variant'] ?? '')
            );
        }
        if (isset($decoded['label']) || isset($decoded['variant'])) {
            $label = (string) ($decoded['label'] ?? '');
            $variant = (string) ($decoded['variant'] ?? '');

            return self::unitTriple($label, $variant);
        }

        return self::unitTriple('', '');
    }

    /**
     * @param  array{az?: array, en?: array, ru?: array}  $decoded
     * @return array{az: array{label: string, variant: string}, en: array{label: string, variant: string}, ru: array{label: string, variant: string}}
     */
    private static function normalizeUnitNested(array $decoded): array
    {
        $pick = function (string $k) use ($decoded): array {
            $n = $decoded[$k] ?? [];
            if (! is_array($n)) {
                return ['label' => '', 'variant' => ''];
            }

            return [
                'label' => (string) ($n['label'] ?? ''),
                'variant' => (string) ($n['variant'] ?? ''),
            ];
        };

        $az = $pick('az');
        $en = $pick('en');
        $ru = $pick('ru');
        if ($en['label'] === '' && $en['variant'] === '') {
            $en = $az['label'] !== '' || $az['variant'] !== '' ? $az : $ru;
        }
        if ($ru['label'] === '' && $ru['variant'] === '') {
            $ru = $az['label'] !== '' || $az['variant'] !== '' ? $az : $en;
        }
        if ($az['label'] === '' && $az['variant'] === '') {
            $az = $ru['label'] !== '' || $ru['variant'] !== '' ? $ru : $en;
        }

        return ['az' => $az, 'en' => $en, 'ru' => $ru];
    }
}
