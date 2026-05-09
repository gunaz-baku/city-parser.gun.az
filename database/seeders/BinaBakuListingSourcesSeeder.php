<?php

namespace Database\Seeders;

use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * config('parsers.bina.listings') üzrə Bakı üçün bina.az listing URL-lərini
 * price_positions + price_sources-a yazır (yeni sxem: slug, links_json, source_type=bina).
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

        $this->usedBinaSlugs = [];

        $rentUnitId = $this->ensureUnitRow('bina_rent_per_month', '₼/ay', '₼/ay');
        $saleUnitId = $this->ensureUnitRow('bina_sale_per_sqm', '₼/m²', '₼/m²');

        $sort = 0;
        foreach ($listings as $row) {
            if (! is_array($row)) {
                continue;
            }

            $listingCode = (string) ($row['code'] ?? '');
            $url = trim((string) ($row['url'] ?? ''));
            $mode = (string) ($row['mode'] ?? 'sale');
            $title = trim((string) ($row['title'] ?? $listingCode));

            if ($listingCode === '' || $url === '') {
                continue;
            }

            $localizedTitle = $this->localizedBinaTitle($listingCode, $mode, $title);

            $categoryId = $this->binaCategoryIdForMode($mode);

            $sort += 10;
            $slug = $this->allocateUniqueBinaListingSlug($localizedTitle['en'] ?? $title, $listingCode);

            $unitId = $mode === 'rent' ? $rentUnitId : $saleUnitId;

            $positionPayload = [
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => json_encode($localizedTitle, JSON_UNESCAPED_UNICODE),
                'unit_id' => $unitId,
                'unit_size' => null,
                'parser_type' => 'bina',
                'is_active' => true,
                'sort_order' => $sort,
                'updated_at' => now(),
            ];

            $existingPos = DB::table('price_positions')->where('slug', $slug)->first();
            if ($existingPos === null) {
                $positionPayload['created_at'] = now();
                DB::table('price_positions')->insert($positionPayload);
            } else {
                DB::table('price_positions')->where('id', $existingPos->id)->update($positionPayload);
            }

            $positionId = (int) DB::table('price_positions')->where('slug', $slug)->value('id');
            if ($positionId < 1) {
                continue;
            }

            $sourcePayload = [
                'links_json' => json_encode([$url], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'priority' => 100,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('price_sources', 'source_type')) {
                $sourcePayload['source_type'] = 'bina';
            }

            $existingSrc = DB::table('price_sources')
                ->where('position_id', $positionId)
                ->where('source_type', 'bina')
                ->first();

            if ($existingSrc === null) {
                $sourcePayload['position_id'] = $positionId;
                $sourcePayload['created_at'] = now();
                DB::table('price_sources')->insert($sourcePayload);
            } else {
                DB::table('price_sources')->where('id', $existingSrc->id)->update($sourcePayload);
            }
        }

        $this->command?->info('Bina Bakı listing mənbələri yeniləndi: '.count($listings).' mövqe.');
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

        return [
            'az' => $fallbackTitle,
            'en' => $fallbackTitle,
            'ru' => $fallbackTitle,
        ];
    }

    /**
     * İngilis başlıq/koddan slug; rəqəmsiz; təkrar olanda hərf suffiksi.
     */
    private function allocateUniqueBinaListingSlug(string $title, string $listingCode): string
    {
        $base = Str::slug(trim($title), '-', 'en');
        if ($base === '') {
            $base = Str::slug($listingCode, '-', 'en');
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
            $candidate = Str::limit($base.'-'.$suffix, 180, '');
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

    private function binaCategoryIdForMode(string $mode): int
    {
        $realEstateId = DB::table('price_categories')->where('slug', 'real-estate')->value('id');
        if ($realEstateId === null) {
            throw new \RuntimeException('Kateqoriya tapılmadı: real-estate. Əvvəl PriceCategoryHierarchySeeder işlədin.');
        }
        // Hierarchy removed: both rent and sale are tracked under the same category.
        return (int) $realEstateId;
    }
}
