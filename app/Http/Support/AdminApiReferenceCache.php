<?php

namespace App\Http\Support;

use App\Models\BasketDefinition;
use App\Models\City;
use App\Models\PriceCategory;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Tez-tez dəyişməyən admin API siyahıları üçün qısa TTL keş.
 *
 * Keşdə Eloquent saxlanmır — yalnız massiv; əks halda unserialize ilə
 * __PHP_Incomplete_Class xətası və deploy uyğunsuzluğu yaranır.
 */
final class AdminApiReferenceCache
{
    public const PRICE_CATEGORIES_TTL_SECONDS = 600;

    public const BASKET_DEFINITIONS_INDEX_TTL_SECONDS = 300;

    private const KEY_PRICE_CATEGORIES = 'admin_api:v2:price_categories:with_parent_payload';

    private const KEY_BASKET_DEFINITIONS = 'admin_api:v2:basket_definitions:with_counts_payload';

    private const KEY_UNITS = 'admin_api:v2:units:active_payload';
    private const KEY_CITIES = 'admin_api:v2:cities:active_payload';

    /** @deprecated cleared by forgetUnits() */
    private const KEY_PRICE_UNITS = 'admin_api:v2:price_units:active_payload';

    /** @deprecated */
    private const LEGACY_KEY_PRICE_CATEGORIES = 'admin_api:v1:price_categories:all_with_parent';

    /** @deprecated */
    private const LEGACY_KEY_BASKETS = 'admin_api:v1:basket_definitions:all_with_counts';
    

    /**
     * @return Collection<int, BasketDefinition>
     */
    public static function basketDefinitionsCollection(): Collection
    {
        $payload = Cache::get(self::KEY_BASKET_DEFINITIONS);
        if (! self::isListOfArrays($payload)) {
            $payload = BasketDefinition::query()
                ->withCount('basketItems')
                ->orderBy('id')
                ->get()
                ->map(static fn (BasketDefinition $b): array => $b->getAttributes())
                ->values()
                ->all();

            Cache::put(self::KEY_BASKET_DEFINITIONS, $payload, now()->addSeconds(self::BASKET_DEFINITIONS_INDEX_TTL_SECONDS));
        }

        return BasketDefinition::hydrate($payload)->values()->each(static function (BasketDefinition $b): void {
            $b->syncOriginal();
        });
    }

    /**
     * @return Collection<int, PriceCategory>
     */
    public static function priceCategoriesCollection(): Collection
    {
        $payload = Cache::get(self::KEY_PRICE_CATEGORIES);
        if (! self::isListOfCategoryCacheRows($payload)) {
            $payload = PriceCategory::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(static function (PriceCategory $category): array {
                    return [
                        'attributes' => $category->getAttributes(),
                    ];
                })
                ->values()
                ->all();

            Cache::put(self::KEY_PRICE_CATEGORIES, $payload, now()->addSeconds(self::PRICE_CATEGORIES_TTL_SECONDS));
        }

        return collect($payload)->map(static function (array $row): PriceCategory {
            $category = (new PriceCategory)->newFromBuilder($row['attributes']);
            $category->exists = true;
            $category->syncOriginal();

            return $category;
        })->values();
    }

    public static function forgetPriceCategories(): void
    {
        Cache::forget(self::KEY_PRICE_CATEGORIES);
        Cache::forget(self::LEGACY_KEY_PRICE_CATEGORIES);
    }

    /**
     * @return Collection<int, Unit>
     *
     * Keşlənmir: vahidlər admin UI / SQL ilə tez-tez silinə bilər; mass delete
     * Eloquent hadisələrini işlətmir və köhnə keş qalardı.
     */
    public static function unitsCollection(): Collection
    {
        return Unit::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->values()
            ->each(static function (Unit $u): void {
                $u->syncOriginal();
            });
    }

    public static function forgetUnits(): void
    {
        Cache::forget(self::KEY_UNITS);
        Cache::forget(self::KEY_PRICE_UNITS);
    }

    public static function forgetBasketDefinitionsIndex(): void
    {
        Cache::forget(self::KEY_BASKET_DEFINITIONS);
        Cache::forget(self::LEGACY_KEY_BASKETS);
    }

    /**
     * @return Collection<int, City>
     */
    public static function citiesCollection(): Collection
    {
        $payload = Cache::get(self::KEY_CITIES);
        if (! self::isListOfArrays($payload)) {
            $payload = City::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(static fn (City $city): array => $city->getAttributes())
                ->values()
                ->all();

            Cache::put(self::KEY_CITIES, $payload, now()->addSeconds(self::PRICE_CATEGORIES_TTL_SECONDS));
        }

        return City::hydrate($payload)->values()->each(static function (City $city): void {
            $city->syncOriginal();
        });
    }

    public static function forgetCities(): void
    {
        Cache::forget(self::KEY_CITIES);
    }

    private static function isListOfArrays(mixed $payload): bool
    {
        if ($payload === null || ! is_array($payload)) {
            return false;
        }

        if (! array_is_list($payload)) {
            return false;
        }

        if ($payload === []) {
            return true;
        }

        foreach ($payload as $row) {
            if (! is_array($row)) {
                return false;
            }
        }

        return true;
    }

    private static function isListOfCategoryCacheRows(mixed $payload): bool
    {
        if ($payload === null || ! is_array($payload)) {
            return false;
        }

        if (! array_is_list($payload)) {
            return false;
        }

        if ($payload === []) {
            return true;
        }

        foreach ($payload as $row) {
            if (! is_array($row) || ! array_key_exists('attributes', $row) || ! is_array($row['attributes'])) {
                return false;
            }
        }

        return true;
    }
}
