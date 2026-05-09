<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * price_categories cədvəli üçün sabit kateqoriyalar.
 */
final class PriceCategoryHierarchy
{
    /**
     * Kateqoriyaları idempotent yazır (slug üzrə yenilənir / əlavə olunur).
     */
    /**
     * Sabit iyerarxiya açarı (məs. food_dairy) → DB-də saxlanan `slug` (məs. dairy).
     * Köhnə seed/config dəyərləri üçün.
     */
    public static function slugForDefinitionKey(string $definitionKey): ?string
    {
        $defs = self::definitionsInOrder();
        if (! isset($defs[$definitionKey]['slug'])) {
            return null;
        }

        return (string) $defs[$definitionKey]['slug'];
    }

    public static function sync(): void
    {
        $now = now();

        foreach (self::definitionsInOrder() as $slugKey => $meta) {
            self::upsertCategory($meta['slug'], $meta, $now);
        }
    }

    /**
     * @return array<string, array{slug: string, name: array{az: string, en: string, ru: string}, sort_order: int}>
     */
    private static function definitionsInOrder(): array
    {
        return [
            'food_products' => [
                'slug' => 'products',
                'name' => ['az' => 'Qida məhsulları', 'en' => 'Food products', 'ru' => 'Продукты питания'],
                'sort_order' => 10,
            ],
            'real_estate' => [
                'slug' => 'real-estate',
                'name' => ['az' => 'Daşınmaz əmlak', 'en' => 'Real estate', 'ru' => 'Недвижимость'],
                'sort_order' => 20,
            ],
            'restaurants' => [
                'slug' => 'restaurants',
                'name' => ['az' => 'Restoranlar', 'en' => 'Restaurants', 'ru' => 'Рестораны'],
                'sort_order' => 30,
            ],
            'medicine' => [
                'slug' => 'medicine',
                'name' => ['az' => 'Tibb (dərmanlar)', 'en' => 'Medicine', 'ru' => 'Медицина (лекарства)'],
                'sort_order' => 40,
            ],
            'childcare' => [
                'slug' => 'childcare',
                'name' => ['az' => 'Uşaq baxımı', 'en' => 'Childcare', 'ru' => 'Уход за детьми'],
                'sort_order' => 50,
            ],
        ];
    }

    /**
     * @param  array{slug: string, name: array{az: string, en: string, ru: string}, sort_order: int}  $meta
     */
    private static function upsertCategory(string $slug, array $meta, Carbon $now): void
    {
        $nameJson = json_encode(
            LocalizedJson::normalizeFlatName($meta['name']) ?? $meta['name'],
            JSON_UNESCAPED_UNICODE
        );

        $row = DB::table('price_categories')->where('slug', $slug)->first();
        $payload = [
            'slug' => $meta['slug'],
            'name' => $nameJson,
            'icon' => null,
            'sort_order' => $meta['sort_order'],
            'is_active' => true,
            'updated_at' => $now,
        ];

        if ($row === null) {
            // Backward-compat: older DBs still have a required `code` column.
            if (Schema::hasColumn('price_categories', 'code')) {
                $payload['code'] = mb_substr($meta['slug'], 0, 100);
            }
            $payload['created_at'] = $now;
            DB::table('price_categories')->insert($payload);
        } else {
            DB::table('price_categories')->where('id', $row->id)->update($payload);
        }
    }
}
