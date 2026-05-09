<?php

namespace App\Http\Support;

use App\Models\BasketDefinition;
use App\Support\SyntheticCity;
use App\Models\BasketItem;
use App\Models\BasketSnapshot;
use App\Models\City;
use App\Models\ParserRun;
use App\Models\ParserRunError;
use App\Models\PriceCategory;
use App\Models\PricePosition;
use App\Models\PriceSnapshot;
use App\Models\Unit;
use App\Support\LocalizedJson;

final class AdminApiPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function cityExtras(City $city, string $locale): array
    {
        return [
            'name_label' => AdminApiLabels::translated(is_array($city->name) ? $city->name : null, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function priceCategoryExtras(PriceCategory $category, string $locale): array
    {
        return [
            'name_label' => AdminApiLabels::translated(is_array($category->name) ? $category->name : null, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pricePositionExtras(PricePosition $position, string $locale): array
    {
        $unitTriple = self::legacyUnitTripleFromPosition($position);

        return [
            'name_label' => AdminApiLabels::translated(is_array($position->name) ? $position->name : null, $locale),
            'category_label' => AdminApiLabels::category($position->relationLoaded('category') ? $position->category : null, $locale),
            'city_label' => AdminApiLabels::translated(SyntheticCity::NAME, $locale).' ('.SyntheticCity::CODE.')',
            'city_code' => SyntheticCity::CODE,
            'unit' => $unitTriple,
            'unit_label' => self::flatUnitLabelFromTriple($unitTriple),
            'unit_size' => $position->unit_size !== null ? round((float) $position->unit_size, 4) : null,
        ];
    }

    /**
     * @return array{az: array{label: string, variant: string}, en: array{label: string, variant: string}, ru: array{label: string, variant: string}}
     */
    public static function legacyUnitTripleFromPosition(PricePosition $position): array
    {
        $mu = $position->relationLoaded('measurementUnit') ? $position->measurementUnit : null;
        if ($mu instanceof Unit) {
            $label = trim((string) ($mu->short_name ?? ''));
            if ($label === '') {
                $label = trim((string) ($mu->name ?? ''));
            }

            return LocalizedJson::unitTriple($label, '');
        }

        $decoded = $position->unit;
        if (is_array($decoded)) {
            $normalized = LocalizedJson::normalizeUnit($decoded);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return LocalizedJson::unitTriple('', '');
    }

    /**
     * @param  array{az: array{label: string, variant: string}, en: array{label: string, variant: string}, ru: array{label: string, variant: string}}  $unitTriple
     */
    public static function flatUnitLabelFromTriple(array $unitTriple): string
    {
        $en = $unitTriple['en'] ?? null;
        if (! is_array($en)) {
            return '';
        }
        $label = trim((string) ($en['label'] ?? ''));
        $variant = trim((string) ($en['variant'] ?? ''));
        if ($label === '') {
            return $variant;
        }
        if ($variant === '') {
            return $label;
        }

        return $label.' — '.$variant;
    }

    /**
     * @return array<string, mixed>
     */
    public static function basketDefinitionExtras(BasketDefinition $basket, string $locale): array
    {
        return [
            'name_label' => AdminApiLabels::translated(is_array($basket->name) ? $basket->name : null, $locale),
            'icon_url' => $basket->getFirstMediaUrl('badge_icon') ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function basketItemExtras(BasketItem $item, string $locale): array
    {
        $basketLabel = null;
        if ($item->relationLoaded('basket') && $item->basket instanceof BasketDefinition) {
            $basketLabel = AdminApiLabels::basket($item->basket, $locale);
        }

        return [
            'basket_label' => $basketLabel,
            'position_label' => AdminApiLabels::position($item->relationLoaded('position') ? $item->position : null, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function basketSnapshotExtras(BasketSnapshot $snapshot, string $locale): array
    {
        return [
            'basket_label' => AdminApiLabels::basket($snapshot->relationLoaded('basket') ? $snapshot->basket : null, $locale),
            'city_label' => AdminApiLabels::translated(SyntheticCity::NAME, $locale).' ('.SyntheticCity::CODE.')',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parserRunExtras(ParserRun $run, string $locale): array
    {
        return [
            'city_label' => AdminApiLabels::translated(SyntheticCity::NAME, $locale).' ('.SyntheticCity::CODE.')',
            'run_label' => AdminApiLabels::parserRun($run),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function priceSnapshotExtras(PriceSnapshot $snapshot, string $locale): array
    {
        return [
            'position_label' => AdminApiLabels::position($snapshot->relationLoaded('position') ? $snapshot->position : null, $locale),
            'city_label' => AdminApiLabels::translated(SyntheticCity::NAME, $locale).' ('.SyntheticCity::CODE.')',
            'parser_run_label' => AdminApiLabels::parserRun($snapshot->relationLoaded('parserRun') ? $snapshot->parserRun : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parserRunErrorExtras(ParserRunError $row, string $locale): array
    {
        $ctx = is_array($row->error_context) ? $row->error_context : [];
        $urlHint = null;
        foreach (['url', 'primary_url', 'successful_url'] as $k) {
            if (isset($ctx[$k]) && is_string($ctx[$k]) && trim($ctx[$k]) !== '') {
                $urlHint = trim($ctx[$k]);
                break;
            }
        }
        if ($urlHint === null && isset($ctx['urls_tried']) && is_array($ctx['urls_tried']) && $ctx['urls_tried'] !== []) {
            $first = $ctx['urls_tried'][0] ?? null;
            $urlHint = is_string($first) ? $first : null;
        }

        return [
            'position_label' => AdminApiLabels::position($row->relationLoaded('position') ? $row->position : null, $locale),
            'parser_run_label' => AdminApiLabels::parserRun($row->relationLoaded('parserRun') ? $row->parserRun : null),
            'url_hint' => $urlHint,
        ];
    }
}
