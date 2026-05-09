<?php

namespace App\Http\Support;

use App\Models\BasketDefinition;
use App\Models\ParserRun;
use App\Models\PriceCategory;
use App\Models\PricePosition;

final class AdminApiLabels
{
    /**
     * @param  array<string, mixed>|null  $dict
     */
    public static function translated(?array $dict, string $locale): string
    {
        if (! is_array($dict)) {
            return '';
        }

        foreach ([$locale, 'az', 'en', 'ru'] as $loc) {
            $v = $dict[$loc] ?? null;
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        foreach ($dict as $v) {
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        return '';
    }

    public static function basket(?BasketDefinition $basket, string $locale): ?string
    {
        if ($basket === null) {
            return null;
        }

        $name = self::translated(is_array($basket->name) ? $basket->name : null, $locale);

        return $name !== '' ? $name : ('#'.(int) $basket->id);
    }

    public static function category(?PriceCategory $category, string $locale): ?string
    {
        if ($category === null) {
            return null;
        }

        $name = self::translated(is_array($category->name) ? $category->name : null, $locale);
        $slug = trim((string) ($category->slug ?? ''));

        if ($name !== '' && $slug !== '') {
            return $name.' ('.$slug.')';
        }

        return $name !== '' ? $name : ($slug !== '' ? $slug : null);
    }

    public static function position(?PricePosition $position, string $locale): ?string
    {
        if ($position === null) {
            return null;
        }

        $name = self::translated(is_array($position->name) ? $position->name : null, $locale);
        $slug = trim((string) ($position->slug ?? ''));

        if ($name !== '' && $slug !== '') {
            return $name.' ('.$slug.')';
        }

        return $name !== '' ? $name : ($slug !== '' ? $slug : null);
    }

    public static function parserRun(?ParserRun $run): ?string
    {
        if ($run === null) {
            return null;
        }

        $id = (int) $run->id;
        $type = trim((string) ($run->parser_type ?? ''));
        $status = trim((string) ($run->status ?? ''));

        $parts = ['#'.$id];
        if ($type !== '') {
            $parts[] = $type;
        }
        if ($status !== '') {
            $parts[] = $status;
        }

        return implode(' · ', $parts);
    }
}
