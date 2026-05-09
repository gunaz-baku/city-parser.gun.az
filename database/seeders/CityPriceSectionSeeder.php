<?php

namespace Database\Seeders;

use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CityPriceSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('city_price_section_tabs') || ! Schema::hasTable('city_price_section_items')) {
            $this->command?->warn('city_price_section_* tables are missing; skipping CityPriceSectionSeeder.');

            return;
        }

        PriceCategoryHierarchy::sync();

        $catIdBySlug = DB::table('price_categories')->pluck('id', 'slug')->map(fn ($v) => (int) $v)->all();

        $productsRootId = $catIdBySlug['products'] ?? null;
        $foodCategoryIds = $productsRootId ? $this->descendantCategoryIds((int) $productsRootId) : [];

        $childcareRootId = $catIdBySlug['childcare'] ?? null;
        $childrenCategoryIds = $childcareRootId ? $this->descendantCategoryIds((int) $childcareRootId) : [];

        $medicineCategoryIds = [];
        if (isset($catIdBySlug['medicine'])) {
            $medicineCategoryIds[] = (int) $catIdBySlug['medicine'];
            $medicineCategoryIds = array_values(array_unique($medicineCategoryIds));
        }

        $realEstateRootId = $catIdBySlug['real-estate'] ?? null;
        $excludeCategoryIdsForGeneral = $realEstateRootId !== null
            ? $this->descendantCategoryIds((int) $realEstateRootId)
            : [];

        $now = now();

        $defaults = [
            [
                'route_slug' => 'food',
                'name' => ['az' => 'Ümumi', 'en' => 'General', 'ru' => 'Общее'],
                'sort_order' => 0,
            ],
            [
                'route_slug' => 'products',
                'name' => ['az' => 'Məhsullar', 'en' => 'Products', 'ru' => 'Продукты'],
                'sort_order' => 1,
            ],
            [
                'route_slug' => 'childcare',
                'name' => ['az' => 'Uşaqlar', 'en' => 'Children', 'ru' => 'Дети'],
                'sort_order' => 2,
            ],
            [
                'route_slug' => 'medicine',
                'name' => ['az' => 'Tibb', 'en' => 'Medicine', 'ru' => 'Медицина'],
                'sort_order' => 3,
            ],
        ];

        foreach ($defaults as $d) {
            $slug = (string) $d['route_slug'];
            DB::table('city_price_section_tabs')->updateOrInsert(
                ['route_slug' => $slug],
                [
                    'name' => json_encode($d['name'], JSON_UNESCAPED_UNICODE),
                    'sort_order' => (int) $d['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $tabIdByRouteSlug = DB::table('city_price_section_tabs')
            ->pluck('id', 'route_slug')
            ->map(fn ($v) => (int) $v)
            ->all();

        $defaultTabIds = [];
        foreach (['food', 'products', 'childcare', 'medicine'] as $routeSlug) {
            if (isset($tabIdByRouteSlug[$routeSlug])) {
                $defaultTabIds[] = (int) $tabIdByRouteSlug[$routeSlug];
            }
        }
        if ($defaultTabIds !== []) {
            DB::table('city_price_section_items')
                ->whereIn('tab_id', $defaultTabIds)
                ->delete();
        }

        $generalQuery = DB::table('price_positions')
            ->where('is_active', true)
            ->where('parser_type', '!=', 'bina');
        if ($excludeCategoryIdsForGeneral !== []) {
            $generalQuery->whereNotIn('category_id', $excludeCategoryIdsForGeneral);
        }
        $generalIds = $generalQuery
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(12)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $productsCatIds = array_values(array_unique($foodCategoryIds));
        $productsIds = $productsCatIds !== []
            ? $this->activePositionIdsForCategories($productsCatIds, 18)
            : [];

        $childrenIds = $childrenCategoryIds !== []
            ? $this->activePositionIdsForCategories($childrenCategoryIds, 12)
            : [];

        $medicineIds = $medicineCategoryIds !== []
            ? $this->activePositionIdsForCategories($medicineCategoryIds, 12)
            : [];

        $inserted = 0;
        $inserted += $this->insertItems((int) ($tabIdByRouteSlug['food'] ?? 0), $generalIds, $now);
        $inserted += $this->insertItems((int) ($tabIdByRouteSlug['products'] ?? 0), $productsIds, $now);
        $inserted += $this->insertItems((int) ($tabIdByRouteSlug['childcare'] ?? 0), $childrenIds, $now);
        $inserted += $this->insertItems((int) ($tabIdByRouteSlug['medicine'] ?? 0), $medicineIds, $now);

        $this->command?->info("CityPriceSection seeded (items={$inserted}).");
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function activePositionIdsForCategories(array $categoryIds, int $limit): array
    {
        return DB::table('price_positions')
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->where('parser_type', '!=', 'bina')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function descendantCategoryIds(int $rootId): array
    {
        return $rootId >= 1 ? [(int) $rootId] : [];
    }

    /**
     * @param  list<int>  $positionIds
     */
    private function insertItems(int $tabId, array $positionIds, $now): int
    {
        if ($tabId < 1 || $positionIds === []) {
            return 0;
        }

        $rows = [];
        $seen = [];
        foreach ($positionIds as $i => $posId) {
            $posId = (int) $posId;
            if ($posId < 1 || isset($seen[$posId])) {
                continue;
            }
            $seen[$posId] = true;
            $rows[] = [
                'tab_id' => $tabId,
                'position_id' => $posId,
                'sort_order' => (int) $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('city_price_section_items')->insert($rows);

        return count($rows);
    }
}
