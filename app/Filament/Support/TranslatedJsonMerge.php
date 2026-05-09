<?php

namespace App\Filament\Support;

use Illuminate\Support\Arr;

final class TranslatedJsonMerge
{
    /**
     * Read en/az/ru from form state (nested or dotted keys), merge into $existing, write to $data[$key], strip dotted keys.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergeLocaleKey(array $data, ?array $existing, string $key): array
    {
        $base = is_array($existing) ? $existing : [];

        foreach (['en', 'az', 'ru'] as $locale) {
            $path = "{$key}.{$locale}";
            $val = data_get($data, $path);

            if ($val === null && array_key_exists($path, $data)) {
                $val = $data[$path];
            }

            if (! is_string($val)) {
                continue;
            }

            if ($val === '') {
                unset($base[$locale]);
            } else {
                $base[$locale] = $val;
            }

            Arr::forget($data, $path);
        }

        $data[$key] = $base;

        return $data;
    }

    /**
     * Merge `{$arrayKey}_en`, `{$arrayKey}_az`, `{$arrayKey}_ru` into `$data[$arrayKey]` (locale JSON) and remove the flat keys.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergeFlatJsonLocales(array $data, ?array $existing, string $arrayKey): array
    {
        $base = is_array($existing) ? $existing : [];

        foreach (['en' => '_en', 'az' => '_az', 'ru' => '_ru'] as $locale => $suffix) {
            $flatKey = $arrayKey.$suffix;
            if (! array_key_exists($flatKey, $data)) {
                continue;
            }

            $val = $data[$flatKey];
            unset($data[$flatKey]);

            if (! is_string($val)) {
                continue;
            }

            if (trim($val) === '') {
                unset($base[$locale]);
            } else {
                $base[$locale] = $val;
            }
        }

        $data[$arrayKey] = $base;

        return $data;
    }
}
