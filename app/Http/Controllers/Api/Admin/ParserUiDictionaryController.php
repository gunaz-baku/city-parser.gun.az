<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLabels;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\PriceCategory;
use App\Models\PricePosition;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GunAz parser Filament üçün birləşmiş istinad məlumatı (kateqoriyalar, mövqelər).
 */
class ParserUiDictionaryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);

        $priceCategories = AdminApiReferenceCache::priceCategoriesCollection()
            ->map(static function (PriceCategory $c) use ($locale): array {
                return array_merge($c->toArray(), AdminApiPresenter::priceCategoryExtras($c, $locale));
            })
            ->values()
            ->all();

        $units = AdminApiReferenceCache::unitsCollection()
            ->map(static function (Unit $u): array {
                return array_merge($u->toArray(), [
                    'option_label' => $u->displayLabel(),
                ]);
            })
            ->values()
            ->all();
        $cities = AdminApiReferenceCache::citiesCollection()
            ->map(static function (\App\Models\City $city) use ($locale): array {
                return array_merge($city->toArray(), AdminApiPresenter::cityExtras($city, $locale));
            })
            ->values()
            ->all();

        $positionsQuery = PricePosition::query()
            ->with(['category:id,code,slug,name', 'measurementUnit:id,code,name,short_name'])
            ->orderBy('sort_order')
            ->orderBy('id');

        $total = (clone $positionsQuery)->count();
        $positions = $positionsQuery
            ->limit(3000)
            ->get()
            ->map(static function (PricePosition $p) use ($locale): array {
                return array_merge($p->toArray(), AdminApiPresenter::pricePositionExtras($p, $locale));
            })
            ->values()
            ->all();

        return response()->json([
            'locale' => $locale,
            'price_categories' => $priceCategories,
            'units' => $units,
            'price_units' => $units,
            'cities' => $cities,
            'price_positions' => $positions,
            'meta' => [
                'positions_total' => $total,
                'positions_returned' => count($positions),
                'positions_truncated' => $total > count($positions),
            ],
        ]);
    }
}
