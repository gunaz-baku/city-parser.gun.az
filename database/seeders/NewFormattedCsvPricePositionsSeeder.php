<?php

namespace Database\Seeders;

use App\Models\PricePosition;
use App\Models\PriceSource;
use App\Support\LocalizedJson;
use App\Support\PriceCategoryHierarchy;
use App\Support\PriceSourceLinks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `new_formatted.csv` → qida/məişət mövqeləri; ardınca sabit Bakı bina.az daşınmaz əmlak indeksləri
 * ({@see self::binaRealEstateListingDefinitions()}) əlavə olunur (real-estate / sale|rent, bir və ya çox URL).
 */
class NewFormattedCsvPricePositionsSeeder extends Seeder
{
    /** @var array<string, bool> */
    private array $usedBinaListingSlugs = [];

    public function run(): void
    {
        $path = base_path('new_formatted.csv');
        if (! is_file($path)) {
            $this->command?->warn("new_formatted.csv tapılmadı: {$path}");

            return;
        }

        PriceCategoryHierarchy::sync();

        // This seeder is the canonical importer for `new_formatted.csv`.
        // Clear previous CSV-imported rows so slug/name fixes don't leave duplicates behind.
        if (Schema::hasTable('price_positions')) {
            if (Schema::hasTable('price_sources')) {
                DB::table('price_sources')->whereIn('source_type', ['wolt', 'bina'])->delete();
            }
            DB::table('price_positions')->delete();
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            $this->command?->warn("new_formatted.csv açıla bilmədi: {$path}");

            return;
        }

        $header = fgetcsv($fh);
        if (! is_array($header) || $header === []) {
            fclose($fh);
            $this->command?->warn('new_formatted.csv boşdur və ya header oxunmadı.');

            return;
        }

        /** @var array<string, int> $col */
        $col = [];
        foreach ($header as $idx => $h) {
            $key = trim((string) $h);
            if ($key !== '') {
                $col[$key] = (int) $idx;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        /** @var array<string, array{id: int, short_name: string, code: string}> */
        $unitByToken = [];

        $productDict = $this->productDictionary();

        while (($row = fgetcsv($fh)) !== false) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $csvCategory = trim((string) ($row[$col['Kateqoriya'] ?? 0] ?? ''));
            $productRaw = trim((string) ($row[$col['Məhsul'] ?? 1] ?? ''));
            $csvUnit = trim((string) ($row[$col['Unit'] ?? 2] ?? ''));
            $linksRaw = (string) ($row[$col['Links {JSON}'] ?? 3] ?? '');

            if ($productRaw === '') {
                $skipped++;
                continue;
            }

            $categoryId = $this->ensureCsvCategory($csvCategory);
            if ($categoryId === null) {
                $skipped++;
                continue;
            }

            [$size, $unitToken] = $this->parseUnit($csvUnit);
            $unitMeta = $this->resolveUnitMeta($unitToken, $unitByToken);
            $unitId = $unitMeta !== null ? $unitMeta['id'] : null;

            $dictKey = $this->resolveProductDictionaryKey($productRaw, $size, $unitToken);
            $productName = $dictKey !== null && isset($productDict[$dictKey])
                ? (LocalizedJson::normalizeFlatName($productDict[$dictKey]) ?? $productDict[$dictKey])
                : $this->translateProductName($productRaw);

            $slugBase = $dictKey !== null && isset($productDict[$dictKey])
                ? $dictKey
                : Str::slug((string) ($productName['en'] ?? $productRaw));
            if ($slugBase === '') {
                $slugBase = 'item';
            }
            $slug = mb_substr($slugBase, 0, 180);

            // ensure slug uniqueness (global)
            $slugExists = DB::table('price_positions')
                ->where('slug', $slug)
                ->exists();

            if ($slugExists) {
                $suffix = 2;
                while (DB::table('price_positions')
                    ->where('slug', $slugBase.'-'.$suffix)
                    ->exists()
                ) {
                    $suffix++;
                }
                $slug = mb_substr($slugBase.'-'.$suffix, 0, 180);
            }

            $links = $this->decodeLinksJson($linksRaw);

            $parserType = $this->inferParserType($links);

            $unitLabel = $unitMeta !== null ? $unitMeta['short_name'] : ($unitToken !== '' ? $unitToken : '');

            $payload = [
                'category_id' => $categoryId,
                'slug' => mb_substr($slug, 0, 180),
                'name' => $productName,
                'unit_size' => $size,
                'unit_id' => $unitId,
                'parser_type' => $parserType,
                'is_active' => true,
                'sort_order' => 1000,
            ];

            $existing = PricePosition::query()
                ->where('slug', $payload['slug'])
                ->first();

            if ($existing === null) {
                $position = PricePosition::query()->create($payload);
                $created++;
            } else {
                $existing->fill($payload);
                $existing->save();
                $position = $existing;
                $updated++;
            }

            $this->syncLinksToPriceSources((int) $position->id, $productRaw, $links);
        }

        fclose($fh);

        $this->seedExplicitWoltPositions();

        $binaCreated = $this->seedBinaRealEstateListingsAfterCsv();
        $this->resequencePricePositionSortOrderFromOne();

        $this->command?->info(
            "new_formatted.csv import: created={$created}, updated={$updated}, skipped={$skipped}; bina_real_estate={$binaCreated}"
        );
    }

    private function resequencePricePositionSortOrderFromOne(): void
    {
        $i = 1;

        DB::table('price_positions')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) use (&$i): void {
                foreach ($rows as $row) {
                    DB::table('price_positions')
                        ->where('id', (int) $row->id)
                        ->update(['sort_order' => $i]);
                    $i++;
                }
            });
    }

    private function seedExplicitWoltPositions(): void
    {
        $productsId = DB::table('price_categories')->where('slug', 'products')->value('id');
        if ($productsId === null) {
            return;
        }

        $gUnitId = DB::table('units')->where('code', 'g')->value('id');
        if ($gUnitId === null) {
            return;
        }

        $payload = [
            'category_id' => (int) $productsId,
            'slug' => 'uzum-yarpagi',
            'name' => [
                'az' => 'Üzüm Yarpağı',
                'en' => 'Grape Leaf',
                'ru' => 'Виноградный Лист',
            ],
            'unit_size' => 640,
            'unit_id' => (int) $gUnitId,
            'parser_type' => 'wolt',
            'is_active' => true,
            'sort_order' => 1000,
        ];

        $position = PricePosition::query()
            ->where('slug', 'uzum-yarpagi')
            ->first();

        if ($position === null) {
            $position = PricePosition::query()->create($payload);
        } else {
            $position->fill($payload);
            $position->save();
        }

        $this->syncLinksToPriceSources((int) $position->id, 'Üzüm Yarpağı', [
            'https://wolt.com/ru/aze/baku/venue/wolt-market-nasimi/bizim-tarla-uzum-yarpagi-640q-itemid-679747c0c59beb1eed19416e?search=%C3%9Cz%C3%BCm%20yarpa%C4%9F',
            'https://wolt.com/ru/aze/baku/venue/market11/bizim-tarla-640-qr-uzum-yarpagi-suse-qabda-itemid-c3fa32ca6ddbf353f493e6f4?search=%C3%9Cz%C3%BCm%20yarpa%C4%9F',
        ]);
    }

    /**
     * Bakı bina.az listingləri (CSV-dən ayrı, sabit siyahı). `url` tək string və ya bir neçə səhifə URL-i ola bilər
     * (məs. 4 və 5 otaqlı birləşdirilmiş indeks).
     *
     * @return list<array{
     *     category: array{az: string, en: string, ru: string},
     *     position: array{az: string, en: string, ru: string},
     *     source: string,
     *     url: string|list<string>
     * }>
     */
    private function binaRealEstateListingDefinitions(): array
    {
        return [
            [
                'category' => [
                    'az' => 'Alqı-satqı yeni tikili 1 otaqlı',
                    'en' => 'Sale new building 1 room',
                    'ru' => 'Продажа новостройки 1-комнатная',
                ],
                'position' => [
                    'az' => '1 otaqlı',
                    'en' => '1 room',
                    'ru' => '1-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/1-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı yeni tikili 2 otaqlı',
                    'en' => 'Sale new building 2 rooms',
                    'ru' => 'Продажа новостройки 2-комнатная',
                ],
                'position' => [
                    'az' => '2 otaqlı',
                    'en' => '2 rooms',
                    'ru' => '2-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/2-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı yeni tikili 3 otaqlı',
                    'en' => 'Sale new building 3 rooms',
                    'ru' => 'Продажа новостройки 3-комнатная',
                ],
                'position' => [
                    'az' => '3 otaqlı',
                    'en' => '3 rooms',
                    'ru' => '3-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/3-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı yeni tikili 4+ otaqlı',
                    'en' => 'Sale new building 4+ rooms',
                    'ru' => 'Продажа новостройки 4+ комнатная',
                ],
                'position' => [
                    'az' => '4+ otaqlı',
                    'en' => '4+ rooms',
                    'ru' => '4+ комнатная',
                ],
                'source' => 'bina.az',
                'url' => [
                    'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/4-otaqli',
                    'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/5-otaqli',
                ],
            ],

            [
                'category' => [
                    'az' => 'Alqı-satqı köhnə tikili 1 otaqlı',
                    'en' => 'Sale old building 1 room',
                    'ru' => 'Продажа вторичного жилья 1-комнатная',
                ],
                'position' => [
                    'az' => '1 otaqlı',
                    'en' => '1 room',
                    'ru' => '1-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/1-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı köhnə tikili 2 otaqlı',
                    'en' => 'Sale old building 2 rooms',
                    'ru' => 'Продажа вторичного жилья 2-комнатная',
                ],
                'position' => [
                    'az' => '2 otaqlı',
                    'en' => '2 rooms',
                    'ru' => '2-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/2-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı köhnə tikili 3 otaqlı',
                    'en' => 'Sale old building 3 rooms',
                    'ru' => 'Продажа вторичного жилья 3-комнатная',
                ],
                'position' => [
                    'az' => '3 otaqlı',
                    'en' => '3 rooms',
                    'ru' => '3-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/3-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Alqı-satqı köhnə tikili 4+ otaqlı',
                    'en' => 'Sale old building 4+ rooms',
                    'ru' => 'Продажа вторичного жилья 4+ комнатная',
                ],
                'position' => [
                    'az' => '4+ otaqlı',
                    'en' => '4+ rooms',
                    'ru' => '4+ комнатная',
                ],
                'source' => 'bina.az',
                'url' => [
                    'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/4-otaqli',
                    'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/5-otaqli',
                ],
            ],

            [
                'category' => [
                    'az' => 'Kirayə 1 otaqlı',
                    'en' => 'Rent 1 room',
                    'ru' => 'Аренда 1-комнатная',
                ],
                'position' => [
                    'az' => '1 otaqlı',
                    'en' => '1 room',
                    'ru' => '1-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/kiraye/menziller/1-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Kirayə 2 otaqlı',
                    'en' => 'Rent 2 rooms',
                    'ru' => 'Аренда 2-комнатная',
                ],
                'position' => [
                    'az' => '2 otaqlı',
                    'en' => '2 rooms',
                    'ru' => '2-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/kiraye/menziller/2-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Kirayə 3 otaqlı',
                    'en' => 'Rent 3 rooms',
                    'ru' => 'Аренда 3-комнатная',
                ],
                'position' => [
                    'az' => '3 otaqlı',
                    'en' => '3 rooms',
                    'ru' => '3-комнатная',
                ],
                'source' => 'bina.az',
                'url' => 'https://bina.az/baki/kiraye/menziller/3-otaqli',
            ],
            [
                'category' => [
                    'az' => 'Kirayə 4+ otaqlı',
                    'en' => 'Rent 4+ rooms',
                    'ru' => 'Аренда 4+ комнатная',
                ],
                'position' => [
                    'az' => '4+ otaqlı',
                    'en' => '4+ rooms',
                    'ru' => '4+ комнатная',
                ],
                'source' => 'bina.az',
                'url' => [
                    'https://bina.az/baki/kiraye/menziller/4-otaqli',
                    'https://bina.az/baki/kiraye/menziller/5-otaqli',
                ],
            ],
        ];
    }

    /**
     * CSV importu bitdikdən sonra bina.az mövqeləri əlavə olunur (real-estate / sale|rent, çoxlu URL).
     */
    private function seedBinaRealEstateListingsAfterCsv(): int
    {
        PriceCategoryHierarchy::sync();

        $this->usedBinaListingSlugs = [];

        $rentUnitId = $this->ensureBinaListingUnitRow('bina_rent_per_month', '₼/ay', '₼/ay');
        $saleUnitId = $this->ensureBinaListingUnitRow('bina_sale_per_sqm', '₼/m²', '₼/m²');

        $definitions = $this->binaRealEstateListingDefinitions();
        $sortBase = 100_000;
        $n = 0;

        foreach ($definitions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $urls = $this->normalizeBinaDefinitionUrls($row['url'] ?? null);
            if ($urls === []) {
                continue;
            }

            $mode = $this->inferBinaModeFromUrls($urls);
            $categoryId = $this->binaLeafCategoryIdForMode($mode);
            $unitId = $mode === 'rent' ? $rentUnitId : $saleUnitId;

            $name = $this->mergeBinaCategoryPositionNames(
                (array) ($row['category'] ?? []),
                (array) ($row['position'] ?? [])
            );
            $name = LocalizedJson::normalizeFlatName($name) ?? $name;

            $slugBase = (string) ($name['en'] ?? $name['az'] ?? 'bina-listing');
            $slug = $this->allocateUniqueBinaListingSlugForCsvSeeder($slugBase);

            $sortBase += 10;
            $payload = [
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'unit_id' => $unitId,
                'unit_size' => null,
                'parser_type' => 'bina',
                'is_active' => true,
                'sort_order' => $sortBase,
            ];

            $position = PricePosition::query()->create($payload);
            $n++;

            $sourcePayload = [
                'position_id' => (int) $position->id,
                'source_type' => 'bina',
                'links_json' => array_values($urls),
                'is_active' => true,
                'priority' => 100,
            ];
            if (Schema::hasColumn('price_sources', 'options_json')) {
                $sourcePayload['options_json'] = ['mode' => $mode];
            }

            PriceSource::query()->create($sourcePayload);
        }

        return $n;
    }

    /**
     * @param  string|list<string>|null  $url
     * @return list<string>
     */
    private function normalizeBinaDefinitionUrls(mixed $url): array
    {
        if ($url === null) {
            return [];
        }
        if (is_string($url)) {
            $s = trim($url);

            return $s !== '' ? [$s] : [];
        }
        if (! is_array($url)) {
            return [];
        }
        $out = [];
        foreach ($url as $u) {
            if (! is_string($u)) {
                continue;
            }
            $s = trim($u);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $this->uniqueUrlsPreserveOrder($out);
    }

    /**
     * @param  list<string>  $urls
     */
    private function inferBinaModeFromUrls(array $urls): string
    {
        foreach ($urls as $u) {
            $lower = mb_strtolower($u);
            if (str_contains($lower, '/kiraye/') || str_contains($lower, 'kiraye')) {
                return 'rent';
            }
        }

        return 'sale';
    }

    private function binaLeafCategoryIdForMode(string $mode): int
    {
        $realEstateId = DB::table('price_categories')->where('slug', 'real-estate')->value('id');
        if ($realEstateId === null) {
            throw new \RuntimeException('Kateqoriya tapılmadı: real-estate. Əvvəl PriceCategoryHierarchySeeder işlədin.');
        }
        // Hierarchy removed: both rent and sale are tracked under the same category.
        return (int) $realEstateId;
    }

    /**
     * @param  array{az?: string, en?: string, ru?: string}  $category
     * @param  array{az?: string, en?: string, ru?: string}  $position
     * @return array{az: string, en: string, ru: string}
     */
    private function mergeBinaCategoryPositionNames(array $category, array $position): array
    {
        $locales = ['az', 'en', 'ru'];
        $out = ['az' => '', 'en' => '', 'ru' => ''];
        foreach ($locales as $loc) {
            $c = trim((string) ($category[$loc] ?? ''));
            $p = trim((string) ($position[$loc] ?? ''));
            if ($c === '') {
                $out[$loc] = $p;

                continue;
            }
            if ($p === '' || str_contains($c, $p)) {
                $out[$loc] = $c;

                continue;
            }
            $out[$loc] = $c.' — '.$p;
        }

        return $out;
    }

    private function allocateUniqueBinaListingSlugForCsvSeeder(string $titleEnOrAz): string
    {
        $base = Str::slug(trim($titleEnOrAz), '-', 'en');
        if ($base === '') {
            $base = 'bina-listing';
        }
        $base = preg_replace('/\p{Nd}+/u', '', $base) ?? $base;
        $base = preg_replace('/-+/u', '-', $base) ?? $base;
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'bina-listing';
        }
        $base = Str::limit($base, 170, '');

        $candidate = $base;
        while (isset($this->usedBinaListingSlugs[$candidate]) || DB::table('price_positions')->where('slug', $candidate)->exists()) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= chr(random_int(97, 122));
            }
            $candidate = Str::limit($base.'-'.$suffix, 180, '');
        }
        $this->usedBinaListingSlugs[$candidate] = true;

        return $candidate;
    }

    private function ensureBinaListingUnitRow(string $code, string $name, string $shortName): int
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

    private function ensureCsvCategory(string $csvCategory): ?int
    {
        $label = trim($csvCategory);
        if (mb_strtolower($label) === 'хозтовары') {
            // Intentionally not imported (scope: only 5 core categories).
            return null;
        }

        $id = DB::table('price_categories')->where('slug', 'products')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{az: string, en: string, ru: string}
     */
    private function translateProductName(string $raw): array
    {
        $k = mb_strtolower(trim($raw));

        // Fallback only (most rows should be covered by productDictionary()).
        $map = [
            'süd' => ['az' => 'Süd', 'en' => 'Milk', 'ru' => 'Молоко'],
        ];

        $out = $map[$k] ?? ['az' => $raw, 'en' => $raw, 'ru' => $raw];

        return LocalizedJson::normalizeFlatName($out) ?? $out;
    }

    /**
     * @return array<string, array{az: string, en: string, ru: string}>
     */
    private function productDictionary(): array
    {
        return [
            'milk-1l' => ['az' => 'Süd 1L', 'en' => 'Milk 1L', 'ru' => 'Молоко 1л'],
            'kefir-750ml' => ['az' => 'Kefir 750ml', 'en' => 'Kefir 750ml', 'ru' => 'Кефир 750мл'],
            'yogurt-500g' => ['az' => 'Qatıq 500g', 'en' => 'Yogurt 500g', 'ru' => 'Йогурт 500г'],
            'ayran-1l' => ['az' => 'Ayran 1L', 'en' => 'Ayran 1L', 'ru' => 'Айран 1л'],
            'sour-cream-175g' => ['az' => 'Xama 175g', 'en' => 'Sour cream 175g', 'ru' => 'Сметана 175г'],
            'cottage-cheese-180g' => ['az' => 'Kəsmik 180g', 'en' => 'Cottage cheese 180g', 'ru' => 'Творог 180г'],
            'white-cheese-500g' => ['az' => 'Ağ pendir 500g', 'en' => 'White cheese 500g', 'ru' => 'Белый сыр 500г'],
            'suluguni-250g' => ['az' => 'Suluguni 250g', 'en' => 'Suluguni 250g', 'ru' => 'Сулугуни 250г'],
            'hard-cheese-1kg' => ['az' => 'Bərk pendir 1kg', 'en' => 'Hard cheese 1kg', 'ru' => 'Твердый сыр 1кг'],
            'butter-1kg' => ['az' => 'Kərə yağı 1kg', 'en' => 'Butter 1kg', 'ru' => 'Сливочное масло 1кг'],
            'condensed-milk-370g' => ['az' => 'Qatılaşdırılmış süd 370g', 'en' => 'Condensed milk 370g', 'ru' => 'Сгущенное молоко 370г'],
            'cream-200g' => ['az' => 'Qaymaq 200g', 'en' => 'Cream 200g', 'ru' => 'Сливки 200г'],
            'processed-cheese-400g' => ['az' => 'Əriyən pendir 400g', 'en' => 'Processed cheese 400g', 'ru' => 'Плавленый сыр 400г'],
            'drinkable-yogurt-270ml' => ['az' => 'İçməli yoqurt 270ml', 'en' => 'Drinkable yogurt 270ml', 'ru' => 'Питьевой йогурт 270мл'],

            'white-bread-400g' => ['az' => 'Ağ çörək 400g', 'en' => 'White bread 400g', 'ru' => 'Белый хлеб 400г'],
            'lavash-1pcs' => ['az' => 'Lavaş', 'en' => 'Lavash', 'ru' => 'Лаваш'],
            'tandir-bread' => ['az' => 'Təndir çörəyi', 'en' => 'Tandoor bread', 'ru' => 'Тандырный хлеб'],
            'black-bread-400g' => ['az' => 'Qara çörək 400g', 'en' => 'Black bread 400g', 'ru' => 'Черный хлеб 400г'],
            'yufka' => ['az' => 'Yuxa', 'en' => 'Yufka', 'ru' => 'Юфка'],
            'baguette' => ['az' => 'Baget', 'en' => 'Baguette', 'ru' => 'Багет'],
            'crackers-100g' => ['az' => 'Qaleta 100g', 'en' => 'Crackers 100g', 'ru' => 'Галеты 100г'],

            'rice-1kg' => ['az' => 'Düyü 1kg', 'en' => 'Rice 1kg', 'ru' => 'Рис 1кг'],
            'buckwheat-800g' => ['az' => 'Qarabaşaq 800g', 'en' => 'Buckwheat 800g', 'ru' => 'Гречка 800г'],
            'bulgur-800g' => ['az' => 'Bulqur 800g', 'en' => 'Bulgur 800g', 'ru' => 'Булгур 800г'],
            'oats-420g' => ['az' => 'Yulaf 420g', 'en' => 'Oats 420g', 'ru' => 'Овсянка 420г'],
            'semolina-800g' => ['az' => 'Manka 800g', 'en' => 'Semolina 800g', 'ru' => 'Манка 800г'],
            'millet-500g' => ['az' => 'Darı 500g', 'en' => 'Millet 500g', 'ru' => 'Пшено 500г'],
            'barley-500g' => ['az' => 'Arpa yarması 500g', 'en' => 'Barley 500g', 'ru' => 'Перловка 500г'],
            'flour-1kg' => ['az' => 'Un 1kg', 'en' => 'Flour 1kg', 'ru' => 'Мука 1кг'],
            'lentils-800g' => ['az' => 'Mərci 800g', 'en' => 'Lentils 800g', 'ru' => 'Чечевица 800г'],
            'beans-800g' => ['az' => 'Lobya 800g', 'en' => 'Beans 800g', 'ru' => 'Фасоль 800г'],
            'chickpeas-800g' => ['az' => 'Noxud 800g', 'en' => 'Chickpeas 800g', 'ru' => 'Нут 800г'],

            'spaghetti-500g' => ['az' => 'Spagetti 500g', 'en' => 'Spaghetti 500g', 'ru' => 'Спагетти 500г'],
            'penne-500g' => ['az' => 'Penne 500g', 'en' => 'Penne 500g', 'ru' => 'Пенне 500г'],
            'vermicelli-500g' => ['az' => 'Şehriyyə 500g', 'en' => 'Vermicelli 500g', 'ru' => 'Вермишель 500г'],

            'chicken-fillet-1kg' => ['az' => 'Toyuq filesi 1kg', 'en' => 'Chicken fillet 1kg', 'ru' => 'Куриное филе 1кг'],
            'whole-chicken' => ['az' => 'Toyuq bütöv', 'en' => 'Whole chicken', 'ru' => 'Целая курица'],
            'chicken-leg' => ['az' => 'Toyuq budu', 'en' => 'Chicken leg', 'ru' => 'Куриная ножка'],
            'beef-1kg' => ['az' => 'Mal əti 1kg', 'en' => 'Beef 1kg', 'ru' => 'Говядина 1кг'],
            'minced-beef-1kg' => ['az' => 'Qiymə mal 1kg', 'en' => 'Minced beef 1kg', 'ru' => 'Фарш говяжий 1кг'],
            'lamb-1kg' => ['az' => 'Qoyun əti 1kg', 'en' => 'Lamb 1kg', 'ru' => 'Баранина 1кг'],

            'trout-1kg' => ['az' => 'Forel 1kg', 'en' => 'Trout 1kg', 'ru' => 'Форель 1кг'],
            'salmon-1kg' => ['az' => 'Qızıl balıq 1kg', 'en' => 'Salmon 1kg', 'ru' => 'Лосось 1кг'],
            'shrimp-1kg' => ['az' => 'Krevet 1kg', 'en' => 'Shrimp 1kg', 'ru' => 'Креветки 1кг'],

            'eggs-10pcs' => ['az' => 'Yumurta 10 ədəd', 'en' => 'Eggs 10 pcs', 'ru' => 'Яйца 10 шт'],
            'quail-eggs-12pcs' => ['az' => 'Bildirçin yumurtası 12 ədəd', 'en' => 'Quail eggs 12 pcs', 'ru' => 'Перепелиные яйца 12 шт'],

            'sunflower-oil-1l' => ['az' => 'Günəbaxan yağı 1L', 'en' => 'Sunflower oil 1L', 'ru' => 'Подсолнечное масло 1л'],
            'olive-oil-500ml' => ['az' => 'Zeytun yağı 500ml', 'en' => 'Olive oil 500ml', 'ru' => 'Оливковое масло 500мл'],

            'tomato-1kg' => ['az' => 'Pomidor 1kg', 'en' => 'Tomato 1kg', 'ru' => 'Помидор 1кг'],
            'cucumber-1kg' => ['az' => 'Xiyar 1kg', 'en' => 'Cucumber 1kg', 'ru' => 'Огурец 1кг'],
            'potato-1kg' => ['az' => 'Kartof 1kg', 'en' => 'Potato 1kg', 'ru' => 'Картофель 1кг'],
            'onion-1kg' => ['az' => 'Soğan 1kg', 'en' => 'Onion 1kg', 'ru' => 'Лук 1кг'],
            'sweet-pepper-500g' => ['az' => 'Bibər şirin 500g', 'en' => 'Sweet pepper 500g', 'ru' => 'Болгарский перец 500г'],

            'apple-1kg' => ['az' => 'Alma 1kg', 'en' => 'Apple 1kg', 'ru' => 'Яблоко 1кг'],
            'banana-1kg' => ['az' => 'Banan 1kg', 'en' => 'Banana 1kg', 'ru' => 'Банан 1кг'],
            'orange' => ['az' => 'Portağal', 'en' => 'Orange', 'ru' => 'Апельсин'],
            'lemon' => ['az' => 'Limon', 'en' => 'Lemon', 'ru' => 'Лимон'],

            'walnuts-200g' => ['az' => 'Qoz 200g', 'en' => 'Walnuts 200g', 'ru' => 'Грецкие орехи 200г'],
            'hazelnuts-300g' => ['az' => 'Fındıq 300g', 'en' => 'Hazelnuts 300g', 'ru' => 'Фундук 300г'],
            'almonds-300g' => ['az' => 'Badam 300g', 'en' => 'Almonds 300g', 'ru' => 'Миндаль 300г'],

            'sugar-800g' => ['az' => 'Şəkər 800g', 'en' => 'Sugar 800g', 'ru' => 'Сахар 800г'],
            'salt-1kg' => ['az' => 'Duz 1kg', 'en' => 'Salt 1kg', 'ru' => 'Соль 1кг'],
            'tea-100g' => ['az' => 'Çay 100g', 'en' => 'Tea 100g', 'ru' => 'Чай 100г'],
            'coffee-95g' => ['az' => 'Qəhvə 95g', 'en' => 'Coffee 95g', 'ru' => 'Кофе 95г'],

            'ketchup-540g' => ['az' => 'Ketçup 540g', 'en' => 'Ketchup 540g', 'ru' => 'Кетчуп 540г'],
            'mayonnaise-500ml' => ['az' => 'Mayonez 500ml', 'en' => 'Mayonnaise 500ml', 'ru' => 'Майонез 500мл'],

            'dumplings-400g' => ['az' => 'Düşbərə 400g', 'en' => 'Dumplings 400g', 'ru' => 'Пельмени 400г'],
            'ice-cream-500ml' => ['az' => 'Dondurma 500ml', 'en' => 'Ice cream 500ml', 'ru' => 'Мороженое 500мл'],

            'sausages-450g' => ['az' => 'Sosiska 450g', 'en' => 'Sausages 450g', 'ru' => 'Сосиски 450г'],
            'salami-420g' => ['az' => 'Kolbasa 420g', 'en' => 'Salami 420g', 'ru' => 'Колбаса 420г'],

            'chocolate-80g' => ['az' => 'Şokolad 80g', 'en' => 'Chocolate 80g', 'ru' => 'Шоколад 80г'],
            'jam-400g' => ['az' => 'Mürəbbə 400g', 'en' => 'Jam 400g', 'ru' => 'Варенье 400г'],
            'cookies-224g' => ['az' => 'Peçenye 224g', 'en' => 'Cookies 224g', 'ru' => 'Печенье 224г'],

            'water-1l' => ['az' => 'Su 1L', 'en' => 'Water 1L', 'ru' => 'Вода 1л'],
            'juice-1l' => ['az' => 'Şirə 1L', 'en' => 'Juice 1L', 'ru' => 'Сок 1л'],
            'cola-1l' => ['az' => 'Coca-Cola 1L', 'en' => 'Coca-Cola 1L', 'ru' => 'Coca-Cola 1л'],

            'baby-formula-400g' => ['az' => 'Uşaq qatışığı 400g', 'en' => 'Baby formula 400g', 'ru' => 'Детская смесь 400г'],
            'baby-puree-90g' => ['az' => 'Uşaq püresi 90g', 'en' => 'Baby puree 90g', 'ru' => 'Детское пюре 90г'],

            'dishwashing-liquid-1l' => ['az' => 'Qab yuyucu 1L', 'en' => 'Dishwashing liquid 1L', 'ru' => 'Средство для мытья посуды 1л'],
            'laundry-detergent-3kg' => ['az' => 'Yuyucu toz 3kg', 'en' => 'Laundry detergent 3kg', 'ru' => 'Стиральный порошок 3кг'],
            'toilet-paper-4pcs' => ['az' => 'Tualet kağızı 4 ədəd', 'en' => 'Toilet paper 4 pcs', 'ru' => 'Туалетная бумага 4 шт'],
        ];
    }

    private function resolveProductDictionaryKey(string $productRaw, ?float $size, string $unitToken): ?string
    {
        $p = mb_strtolower(trim($productRaw));
        $p = preg_replace('/\s+/u', ' ', $p) ?? $p;

        $suffix = $this->formatUnitSuffixForSlug($size, $unitToken);

        $map = [
            'süd' => 'milk',
            'kefir' => 'kefir',
            'qatıq' => 'yogurt',
            'ayran' => 'ayran',
            'xama' => 'sour-cream',
            'kəsmik' => 'cottage-cheese',
            'kəsmk' => 'cottage-cheese',
            'pendir ağ' => 'white-cheese',
            'suluguni' => 'suluguni',
            'bərk pendir' => 'hard-cheese',
            'kərə yağı' => 'butter',
            'qatılaşdırılmış süd' => 'condensed-milk',
            'qaymaq' => 'cream',
            'əriyən pendir' => 'processed-cheese',
            'içməli yoqurt' => 'drinkable-yogurt',

            'ağ çörək' => 'white-bread',
            'lavaş' => 'lavash',
            'təndir çörəyi' => 'tandir-bread',
            'qara çörək' => 'black-bread',
            'yuxa' => 'yufka',
            'baget' => 'baguette',
            'qaleta' => 'crackers',

            'düyü' => 'rice',
            'qarabaşaq' => 'buckwheat',
            'bulqur' => 'bulgur',
            'yulaf' => 'oats',
            'manka' => 'semolina',
            'darı' => 'millet',
            'arpa yarması' => 'barley',
            'un' => 'flour',
            'mərci' => 'lentils',
            'lobya' => 'beans',
            'noxud' => 'chickpeas',

            'spagetti' => 'spaghetti',
            'penne' => 'penne',
            'şehriyyə' => 'vermicelli',

            'toyuq filesi' => 'chicken-fillet',
            'toyuq bütöv' => 'whole-chicken',
            'toyuq budu' => 'chicken-leg',
            'mal əti' => 'beef',
            'qiymə mal' => 'minced-beef',
            'qoyun əti' => 'lamb',

            'forel' => 'trout',
            'qızıl balıq' => 'salmon',
            'krevet' => 'shrimp',

            'yumurta' => 'eggs',
            'bildirçin yumurtası' => 'quail-eggs',

            'günəbaxan yağı' => 'sunflower-oil',
            'zeytun yağı' => 'olive-oil',

            'pomidor' => 'tomato',
            'xiyar' => 'cucumber',
            'kartof' => 'potato',
            'soğan' => 'onion',
            'bibər şirin' => 'sweet-pepper',

            'alma' => 'apple',
            'banan' => 'banana',
            'portağal' => 'orange',
            'limon' => 'lemon',

            'qoz' => 'walnuts',
            'fındıq' => 'hazelnuts',
            'badam' => 'almonds',

            'şəkər' => 'sugar',
            'duz' => 'salt',
            'çay' => 'tea',
            'qəhvə' => 'coffee',

            'ketçup' => 'ketchup',
            'mayonez' => 'mayonnaise',

            'düşbərə' => 'dumplings',
            'dondurma' => 'ice-cream',

            'sosiska' => 'sausages',
            'kolbasa' => 'salami',

            'şokolad' => 'chocolate',
            'mürəbbə' => 'jam',
            'peçenye' => 'cookies',

            'su' => 'water',
            'şirə' => 'juice',
            'coca-cola' => 'cola',

            'uşaq qatışığı' => 'baby-formula',
            'uşaq püresi' => 'baby-puree',

            'qab yuyucu' => 'dishwashing-liquid',
            'yuyucu toz' => 'laundry-detergent',
            'tualet kağızı' => 'toilet-paper',
        ];

        $base = $map[$p] ?? null;
        if ($base === null) {
            return null;
        }

        // Some items are "unitless" in slug keys (per provided dictionary).
        if (in_array($base, ['tandir-bread', 'yufka', 'baguette', 'whole-chicken', 'chicken-leg', 'orange', 'lemon'], true)) {
            return $base;
        }

        // Lavash uses pcs sizing in slug key even if numeric size is 1.
        if ($base === 'lavash') {
            return 'lavash-1pcs';
        }

        if ($suffix === '') {
            return null;
        }

        return $base.'-'.$suffix;
    }

    private function formatUnitSuffixForSlug(?float $size, string $unitToken): string
    {
        if ($size === null || $size <= 0 || $unitToken === '') {
            return '';
        }

        // Dictionary slugs use "l" for liters, while DB unit tokens may normalize Cyrillic "л" to "lt".
        if ($unitToken === 'lt') {
            $unitToken = 'lt';
        }

        $n = (string) (0 + (float) $size);
        if (str_contains($n, '.')) {
            $n = rtrim(rtrim($n, '0'), '.');
        }

        if ($unitToken === 'pcs') {
            return $n.'pcs';
        }

        return $n.$unitToken;
    }

    /**
     * @return array{az: string, en: string, ru: string}
     */
    private function translateCategoryName(string $raw): array
    {
        $k = mb_strtolower(trim($raw));

        $map = $this->categoryDictionary();
        $out = $map[$k] ?? ['az' => $raw, 'en' => $raw, 'ru' => $raw];

        return LocalizedJson::normalizeFlatName($out) ?? $out;
    }

    /**
     * Category dictionary (csv value → 3-locale JSON).
     *
     * The user-provided JSONs are authoritative here.
     *
     * @return array<string, array{az: string, en: string, ru: string}>
     */
    private function categoryDictionary(): array
    {
        return [
            'молочные' => ['az' => 'Süd məhsulları', 'en' => 'Dairy', 'ru' => 'Молочные продукты'],
            'хлеб' => ['az' => 'Çörək', 'en' => 'Bread', 'ru' => 'Хлеб'],
            'крупы' => ['az' => 'Yarmalar', 'en' => 'Cereals', 'ru' => 'Крупы'],
            'макароны' => ['az' => 'Makaron', 'en' => 'Pasta', 'ru' => 'Макароны'],
            'мясо' => ['az' => 'Ət', 'en' => 'Meat', 'ru' => 'Мясо'],
            'рыба' => ['az' => 'Balıq', 'en' => 'Fish', 'ru' => 'Рыба'],
            'яйца' => ['az' => 'Yumurta', 'en' => 'Eggs', 'ru' => 'Яйца'],
            'масла' => ['az' => 'Yağlar', 'en' => 'Oils', 'ru' => 'Масла'],
            'овощи' => ['az' => 'Tərəvəzlər', 'en' => 'Vegetables', 'ru' => 'Овощи'],
            'зелень' => ['az' => 'Göyərti', 'en' => 'Greens', 'ru' => 'Зелень'],
            'фрукты' => ['az' => 'Meyvələr', 'en' => 'Fruits', 'ru' => 'Фрукты'],
            'орехи' => ['az' => 'Qoz-fındıq', 'en' => 'Nuts', 'ru' => 'Орехи'],
            'бакалея' => ['az' => 'Bakaleya', 'en' => 'Grocery', 'ru' => 'Бакалея'],
            'консервы' => ['az' => 'Konservlər', 'en' => 'Canned goods', 'ru' => 'Консервы'],
            'заморозка' => ['az' => 'Dondurulmuş məhsullar', 'en' => 'Frozen foods', 'ru' => 'Заморозка'],
            'колбасы' => ['az' => 'Kolbasa məhsulları', 'en' => 'Sausages', 'ru' => 'Колбасы'],
            'сладости' => ['az' => 'Şirniyyat', 'en' => 'Sweets', 'ru' => 'Сладости'],
            'напитки' => ['az' => 'İçkilər', 'en' => 'Beverages', 'ru' => 'Напитки'],
            'детское' => ['az' => 'Uşaq', 'en' => 'Baby & kids', 'ru' => 'Детское'],
            'хозтовары' => ['az' => 'Məişət malları', 'en' => 'Household goods', 'ru' => 'Хозтовары'],
        ];
    }

    /**
     * @param  list<string>|null  $links
     */
    private function syncLinksToPriceSources(int $positionId, string $product, ?array $links): void
    {
        // keep only URLs; ignore titles like "Wolt ... | Wolt"
        $urls = [];
        if ($links !== null) {
            foreach ($links as $l) {
                $s = trim($l);
                if ($s === '') {
                    continue;
                }
                if (preg_match('#^https?://#i', $s)) {
                    $urls[] = $s;
                }
            }
        }

        PriceSource::query()
            ->where('position_id', $positionId)
            ->whereIn('source_type', ['wolt', 'bina', 'csv_links'])
            ->delete();

        if ($urls === []) {
            return;
        }

        $urls = $this->uniqueUrlsPreserveOrder($urls);
        $woltUrls = PriceSourceLinks::woltUrls($urls);
        $binaUrls = PriceSourceLinks::binaUrls($urls);

        if ($woltUrls !== []) {
            PriceSource::query()->create([
                'position_id' => $positionId,
                'source_type' => 'wolt',
                'links_json' => array_values($woltUrls),
                'is_active' => true,
                'priority' => 50,
            ]);
        }

        if ($binaUrls !== []) {
            PriceSource::query()->create([
                'position_id' => $positionId,
                'source_type' => 'bina',
                'links_json' => array_values($binaUrls),
                'is_active' => true,
                'priority' => 100,
            ]);
        }
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function uniqueUrlsPreserveOrder(array $urls): array
    {
        $seen = [];
        $out = [];
        foreach ($urls as $u) {
            $k = mb_strtolower(trim($u));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = trim($u);
        }

        return $out;
    }

    /**
     * @return array{0: ?float, 1: string} size, unitToken
     */
    private function parseUnit(string $csvUnit): array
    {
        $t = trim($csvUnit);
        if ($t === '') {
            return [null, ''];
        }

        // Examples: "1 л", "0.75 л", "500 г", "1 шт", also "500г"
        $numRaw = '';
        $unitRaw = '';
        if (preg_match('/^\s*([0-9]+(?:[.,][0-9]+)?)\s*([[:alpha:]\p{Cyrillic}]+)\s*$/u', $t, $m)) {
            $numRaw = trim((string) ($m[1] ?? ''));
            $unitRaw = trim((string) ($m[2] ?? ''));
        } else {
            $parts = preg_split('/\s+/u', $t) ?: [];
            $numRaw = trim((string) ($parts[0] ?? ''));
            $unitRaw = trim((string) ($parts[1] ?? ''));
        }

        $numRaw = str_replace(',', '.', $numRaw);
        $size = null;
        if ($numRaw !== '' && is_numeric($numRaw)) {
            $size = (float) $numRaw;
        }

        $unitToken = $this->normalizeUnitToken($unitRaw);

        return [$size, $unitToken];
    }

    /**
     * @param  array<string, array{id: int, short_name: string, code: string}>  $cache
     * @return array{id: int, short_name: string, code: string}|null
     */
    private function resolveUnitMeta(string $unitToken, array &$cache): ?array
    {
        if ($unitToken === '') {
            return null;
        }

        if (isset($cache[$unitToken])) {
            return $cache[$unitToken];
        }

        $row = DB::table('units')
            ->where(function ($q) use ($unitToken): void {
                $q->where('code', $unitToken)->orWhere('short_name', $unitToken);
            })
            ->first(['id', 'short_name', 'code']);

        if ($row === null) {
            return null;
        }

        $cache[$unitToken] = [
            'id' => (int) $row->id,
            'short_name' => (string) ($row->short_name ?? ''),
            'code' => (string) ($row->code ?? ''),
        ];

        return $cache[$unitToken];
    }

    /**
     * @return list<string>|null
     */
    private function decodeLinksJson(string $raw): ?array
    {
        $t = trim($raw);
        if ($t === '' || $t === '[]') {
            return null;
        }

        // CSV contains doubled quotes inside JSON: [""url"", ""title""]
        $t = str_replace('""', '"', $t);

        $decoded = json_decode($t, true);
        if (! is_array($decoded)) {
            return null;
        }

        $out = [];
        foreach ($decoded as $v) {
            if (! is_string($v)) {
                continue;
            }
            $s = trim($v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out !== [] ? $out : null;
    }

    /**
     * @param  list<string>|null  $links
     */
    private function inferParserType(?array $links): string
    {
        if ($links === null) {
            return 'manual';
        }
        foreach ($links as $l) {
            if (str_contains(mb_strtolower($l), 'wolt.com')) {
                return 'wolt';
            }
        }
        foreach ($links as $l) {
            if (str_contains(mb_strtolower($l), 'bina.az')) {
                return 'bina';
            }
        }

        return 'manual';
    }

    private function normalizeUnitToken(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = preg_replace('/\s+/u', '', $s);
        $aliases = [
            'г' => 'g',
            'q' => 'g',
            'qr' => 'g',
            'кг' => 'kg',
            'л' => 'lt',
            'мл' => 'ml',
            'əd' => 'pcs',
            'ed' => 'pcs',
            'шт' => 'pcs',
            'пуч' => 'pcs',
            'pc' => 'pcs',
            'piece' => 'pcs',
            'pieces' => 'pcs',
            'paket' => 'pack',
            'şüşə' => 'bottle',
            'suse' => 'bottle',
        ];
        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s;
    }
}

