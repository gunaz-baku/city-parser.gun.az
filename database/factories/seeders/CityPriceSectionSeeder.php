<?php

namespace Database\Seeders;

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

        $cities = DB::table('cities')->get(['id', 'code']);
        if ($cities->isEmpty()) {
            $this->command?->warn('No cities found; skipping CityPriceSectionSeeder.');

            return;
        }

        // Category groups (by code). Keep this robust: fall back to empty sets if categories don't exist.
        $catIdByCode = DB::table('price_categories')->pluck('id', 'code')->map(fn ($v) => (int) $v)->all();

        $foodRootId = $catIdByCode['food_products'] ?? null;
        $householdRootId = $catIdByCode['household'] ?? null;

        $foodCategoryIds = $foodRootId ? $this->descendantCategoryIds((int) $foodRootId) : [];
        $householdCategoryIds = $householdRootId ? $this->descendantCategoryIds((int) $householdRootId) : [];

        $childrenCategoryIds = [];
        foreach (['childcare', 'childcare_products'] as $code) {
            if (isset($catIdByCode[$code])) {
                $childrenCategoryIds[] = (int) $catIdByCode[$code];
            }
        }
        $childrenCategoryIds = array_values(array_unique(array_merge(
            $childrenCategoryIds,
            $childrenCategoryIds !== [] ? $this->descendantCategoryIds($childrenCategoryIds[0]) : []
        )));

        $medicineCategoryIds = [];
        foreach (['medicine'] as $code) {
            if (isset($catIdByCode[$code])) {
                $medicineCategoryIds[] = (int) $catIdByCode[$code];
            }
        }

        $now = now();

        foreach ($cities as $city) {
            $cityId = (int) $city->id;
            $cityCode = (string) ($city->code ?? '');

            // Ensure default tabs exist for this city.
            $defaults = [
                [
                    'code' => 'general',
                    'route_slug' => 'food',
                    'name' => ['az' => 'Ümumi', 'en' => 'General', 'ru' => 'Общее'],
                    'sort_order' => 0,
                ],
                [
                    'code' => 'products',
                    'route_slug' => 'products',
                    'name' => ['az' => 'Məhsullar', 'en' => 'Products', 'ru' => 'Продукты'],
                    'sort_order' => 1,
                ],
                [
                    'code' => 'children',
                    'route_slug' => 'childcare',
                    'name' => ['az' => 'Uşaqlar', 'en' => 'Children', 'ru' => 'Дети'],
                    'sort_order' => 2,
                ],
                [
                    'code' => 'medicine',
                    'route_slug' => 'medicine',
                    'name' => ['az' => 'Tibb', 'en' => 'Medicine', 'ru' => 'Медицина'],
                    'sort_order' => 3,
                ],
            ];

            foreach ($defaults as $d) {
                DB::table('city_price_section_tabs')->updateOrInsert(
                    ['city_id' => $cityId, 'code' => $d['code']],
                    [
                        'route_slug' => $d['route_slug'],
                        'name' => json_encode($d['name'], JSON_UNESCAPED_UNICODE),
                        'sort_order' => (int) $d['sort_order'],
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            $tabIdByCode = DB::table('city_price_section_tabs')
                ->where('city_id', $cityId)
                ->pluck('id', 'code')
                ->map(fn ($v) => (int) $v)
                ->all();

            // Replace items for these default tabs only (keeps custom tabs, if any).
            $defaultTabIds = [];
            foreach (['general', 'products', 'children', 'medicine'] as $c) {
                if (isset($tabIdByCode[$c])) {
                    $defaultTabIds[] = (int) $tabIdByCode[$c];
                }
            }
            if ($defaultTabIds !== []) {
                DB::table('city_price_section_items')
                    ->where('city_id', $cityId)
                    ->whereIn('tab_id', $defaultTabIds)
                    ->delete();
            }

            // Build item lists.
            $generalIds = DB::table('price_positions')
                ->where('city_id', $cityId)
                ->where('is_active', true)
                ->where('include_in_dolma_index', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(12)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();

            if ($generalIds === []) {
                $generalIds = DB::table('price_positions')
                    ->where('city_id', $cityId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(12)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }

            $productsCatIds = array_values(array_unique(array_merge($foodCategoryIds, $householdCategoryIds)));
            $productsIds = $productsCatIds !== []
                ? DB::table('price_positions')
                    ->where('city_id', $cityId)
                    ->where('is_active', true)
                    ->whereIn('category_id', $productsCatIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(18)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
                : [];

            $childrenIds = $childrenCategoryIds !== []
                ? DB::table('price_positions')
                    ->where('city_id', $cityId)
                    ->where('is_active', true)
                    ->whereIn('category_id', $childrenCategoryIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(12)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
                : [];

            $medicineIds = $medicineCategoryIds !== []
                ? DB::table('price_positions')
                    ->where('city_id', $cityId)
                    ->where('is_active', true)
                    ->whereIn('category_id', $medicineCategoryIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(12)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all()
                : [];

            $inserted = 0;
            $inserted += $this->insertItems($cityId, (int) ($tabIdByCode['general'] ?? 0), $generalIds, $now);
            $inserted += $this->insertItems($cityId, (int) ($tabIdByCode['products'] ?? 0), $productsIds, $now);
            $inserted += $this->insertItems($cityId, (int) ($tabIdByCode['children'] ?? 0), $childrenIds, $now);
            $inserted += $this->insertItems($cityId, (int) ($tabIdByCode['medicine'] ?? 0), $medicineIds, $now);

            $this->command?->info("CityPriceSection seeded for city={$cityCode} (items={$inserted}).");
        }
    }

    /**
     * @return list<int>
     */
    private function descendantCategoryIds(int $rootId): array
    {
        $ids = [];
        $queue = [$rootId];

        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id === null) {
                break;
            }
            $id = (int) $id;
            if ($id < 1 || in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;

            $children = DB::table('price_categories')
                ->where('parent_id', $id)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            foreach ($children as $cid) {
                $queue[] = (int) $cid;
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $positionIds
     */
    private function insertItems(int $cityId, int $tabId, array $positionIds, $now): int
    {
        if ($cityId < 1 || $tabId < 1 || $positionIds === []) {
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
                'city_id' => $cityId,
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

