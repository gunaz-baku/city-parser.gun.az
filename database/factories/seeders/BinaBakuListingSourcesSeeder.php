<?php

namespace Database\Seeders;

use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * config('parsers.bina.listings') üzrə Bakı üçün bina.az listing URL-lərini
 * price_positions + price_sources-a yazır.
 */
class BinaBakuListingSourcesSeeder extends Seeder
{
    /** @var array<string, bool> */
    private array $usedBinaSlugs = [];

    public function run(): void
    {
        $listings = config('parsers.bina.listings', []);
        if (! is_array($listings) || $listings === []) {
            $this->command?->warn('parsers.bina.listings boşdur.');

            return;
        }

        PriceCategoryHierarchy::sync();

        $cityId = $this->ensureBakuCityId();

        $this->usedBinaSlugs = [];

        $rentUnitId = $this->ensureUnitRow('bina_rent_per_month', '₼/ay', '₼/ay');
        $saleUnitId = $this->ensureUnitRow('bina_sale_per_sqm', '₼/m²', '₼/m²');

        $sort = 0;
        foreach ($listings as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = (string) ($row['code'] ?? '');
            $url = trim((string) ($row['url'] ?? ''));
            $mode = (string) ($row['mode'] ?? 'sale');
            $title = trim((string) ($row['title'] ?? $code));

            if ($code === '' || $url === '') {
                continue;
            }

            $localizedTitle = $this->localizedBinaTitle($code, $mode, $title);

            $categoryId = $this->binaCategoryIdForMode($mode);

            $sort += 10;
            $slug = $this->allocateUniqueBinaListingSlug($localizedTitle['en'] ?? $title, $code);

            $unitId = $mode === 'rent' ? $rentUnitId : $saleUnitId;
            $unitRow = DB::table('units')->where('id', $unitId)->first();
            $unitShort = '';
            if ($unitRow !== null) {
                $unitShort = trim((string) ($unitRow->short_name ?? $unitRow->code ?? ''));
            }

            $positionPayload = [
                'city_id' => $cityId,
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => json_encode($localizedTitle, JSON_UNESCAPED_UNICODE),
                'unit' => json_encode([
                    'az' => $unitShort,
                    'en' => $unitShort,
                    'ru' => $unitShort,
                ], JSON_UNESCAPED_UNICODE),
                'unit_id' => $unitId,
                'parser_type' => 'bina',
                'is_active' => true,
                'sort_order' => $sort,
                'include_in_dolma_index' => false,
                'updated_at' => now(),
            ];

            $existingPos = DB::table('price_positions')->where('code', $code)->first();
            if ($existingPos === null) {
                $positionPayload['code'] = $code;
                $positionPayload['created_at'] = now();
                DB::table('price_positions')->insert($positionPayload);
            } else {
                DB::table('price_positions')->where('id', $existingPos->id)->update($positionPayload);
            }

            $positionId = (int) DB::table('price_positions')->where('code', $code)->value('id');
            if ($positionId < 1) {
                continue;
            }

            $config = json_encode(['mode' => $mode], JSON_UNESCAPED_UNICODE);

            $sourcePayload = [
                'source_name' => 'Bina.az listing',
                'source_url' => $url,
                'source_config' => $config,
                'external_source_id' => null,
                'is_active' => true,
                'priority' => 100,
                'updated_at' => now(),
            ];

            $existingSrc = DB::table('price_sources')
                ->where('position_id', $positionId)
                ->where('source_type', 'bina')
                ->first();

            if ($existingSrc === null) {
                $sourcePayload['position_id'] = $positionId;
                $sourcePayload['source_type'] = 'bina';
                $sourcePayload['created_at'] = now();
                DB::table('price_sources')->insert($sourcePayload);
            } else {
                DB::table('price_sources')->where('id', $existingSrc->id)->update($sourcePayload);
            }
        }

        $this->command?->info('Bina Bakı listing mənbələri yeniləndi: ' . count($listings) . ' mövqe.');
    }

    /**
     * 4 və 5p halları satışda 4+ otaqlı kimi,
     * kirayədə 4 və 5 halları 4+ otaqlı kimi birləşdirilir.
     */
    private function localizedBinaTitle(string $code, string $mode, string $fallbackTitle): array
    {
        $map = [
            'bina_baku_sale_new_1' => [
                'az' => 'Yeni tikili 1 otaqlı, 1 m² üçün qiymət',
                'en' => 'New building 1-room, price per 1 m²',
                'ru' => 'Новостройка, 1-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_new_2' => [
                'az' => 'Yeni tikili 2 otaqlı, 1 m² üçün qiymət',
                'en' => 'New building 2-room, price per 1 m²',
                'ru' => 'Новостройка, 2-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_new_3' => [
                'az' => 'Yeni tikili 3 otaqlı, 1 m² üçün qiymət',
                'en' => 'New building 3-room, price per 1 m²',
                'ru' => 'Новостройка, 3-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_new_4' => [
                'az' => 'Yeni tikili 4+ otaqlı, 1 m² üçün qiymət',
                'en' => 'New building 4+ rooms, price per 1 m²',
                'ru' => 'Новостройка, 4+ комнат, цена за 1 м²',
            ],
            'bina_baku_sale_new_5p' => [
                'az' => 'Yeni tikili 4+ otaqlı, 1 m² üçün qiymət',
                'en' => 'New building 4+ rooms, price per 1 m²',
                'ru' => 'Новостройка, 4+ комнат, цена за 1 м²',
            ],

            'bina_baku_sale_old_1' => [
                'az' => 'Köhnə tikili 1 otaqlı, 1 m² üçün qiymət',
                'en' => 'Old building 1-room, price per 1 m²',
                'ru' => 'Старый фонд, 1-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_old_2' => [
                'az' => 'Köhnə tikili 2 otaqlı, 1 m² üçün qiymət',
                'en' => 'Old building 2-room, price per 1 m²',
                'ru' => 'Старый фонд, 2-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_old_3' => [
                'az' => 'Köhnə tikili 3 otaqlı, 1 m² üçün qiymət',
                'en' => 'Old building 3-room, price per 1 m²',
                'ru' => 'Старый фонд, 3-комнатная, цена за 1 м²',
            ],
            'bina_baku_sale_old_4' => [
                'az' => 'Köhnə tikili 4+ otaqlı, 1 m² üçün qiymət',
                'en' => 'Old building 4+ rooms, price per 1 m²',
                'ru' => 'Старый фонд, 4+ комнат, цена за 1 м²',
            ],
            'bina_baku_sale_old_5p' => [
                'az' => 'Köhnə tikili 4+ otaqlı, 1 m² üçün qiymət',
                'en' => 'Old building 4+ rooms, price per 1 m²',
                'ru' => 'Старый фонд, 4+ комнат, цена за 1 м²',
            ],

            'bina_baku_rent_1' => [
                'az' => '1 otaqlı mənzil, aylıq kirayə qiyməti',
                'en' => '1-room apartment, monthly rent price',
                'ru' => '1-комнатная квартира, ежемесячная аренда',
            ],
            'bina_baku_rent_2' => [
                'az' => '2 otaqlı mənzil, aylıq kirayə qiyməti',
                'en' => '2-room apartment, monthly rent price',
                'ru' => '2-комнатная квартира, ежемесячная аренда',
            ],
            'bina_baku_rent_3' => [
                'az' => '3 otaqlı mənzil, aylıq kirayə qiyməti',
                'en' => '3-room apartment, monthly rent price',
                'ru' => '3-комнатная квартира, ежемесячная аренда',
            ],
            'bina_baku_rent_4' => [
                'az' => '4+ otaqlı mənzil, aylıq kirayə qiyməti',
                'en' => '4+ room apartment, monthly rent price',
                'ru' => 'Квартира 4+ комнат, ежемесячная аренда',
            ],
            'bina_baku_rent_5' => [
                'az' => '4+ otaqlı mənzil, aylıq kirayə qiyməti',
                'en' => '4+ room apartment, monthly rent price',
                'ru' => 'Квартира 4+ комнат, ежемесячная аренда',
            ],
        ];

        if (isset($map[$code])) {
            return $map[$code];
        }

        // Fallback: əsas dillər boş qalmasın
        return [
            'az' => $fallbackTitle,
            'en' => $fallbackTitle,
            'ru' => $fallbackTitle,
        ];
    }

    /**
     * İngilis başlıq/koddan slug; rəqəmsiz; təkrar olanda hərf suffiksi.
     */
    private function allocateUniqueBinaListingSlug(string $title, string $code): string
    {
        $base = Str::slug(trim($title), '-', 'en');
        if ($base === '') {
            $base = Str::slug($code, '-', 'en');
        }
        $base = preg_replace('/\p{Nd}+/u', '', $base) ?? $base;
        $base = preg_replace('/-+/u', '-', $base) ?? $base;
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'bina-listing';
        }
        $base = Str::limit($base, 170, '');

        $candidate = $base;
        while (isset($this->usedBinaSlugs[$candidate])) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= chr(random_int(97, 122));
            }
            $candidate = Str::limit($base . '-' . $suffix, 180, '');
        }
        $this->usedBinaSlugs[$candidate] = true;

        return $candidate;
    }

    private function ensureUnitRow(string $code, string $name, string $shortName): int
    {
        $existing = DB::table('units')->where('code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $now = now();

        return (int) DB::table('units')->insertGetId([
            'code' => $code,
            'name' => $name,
            'short_name' => $shortName,
            'unit_type' => 'count',
            'base_unit' => null,
            'multiplier' => null,
            'sort_order' => 200,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureBakuCityId(): int
    {
        $id = DB::table('cities')->where('code', 'baku')->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $name = json_encode(
            ['az' => 'Bakı', 'en' => 'Baku', 'ru' => 'Баку'],
            JSON_UNESCAPED_UNICODE
        );

        return (int) DB::table('cities')->insertGetId([
            'code' => 'baku',
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function binaCategoryIdForMode(string $mode): int
    {
        $code = strtolower($mode) === 'rent' ? 'real_estate_rent' : 'real_estate_sale';
        $id = DB::table('price_categories')->where('code', $code)->value('id');
        if ($id === null) {
            throw new \RuntimeException(
                'Kateqoriya tapılmadı: ' . $code . '. Əvvəl PriceCategoryHierarchySeeder işlədin.'
            );
        }

        return (int) $id;
    }
}