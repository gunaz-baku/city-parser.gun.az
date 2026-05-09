<?php

namespace Database\Seeders;

use App\Support\LocalizedJson;
use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * gun_az_FINAL_parsing_strategy*.csv faylından price_categories (köhnə gun_az_cat_* təmizlənir),
 * price_positions, price_sources doldurur.
 *
 * Mövqe slug-ları: gun_az_{№} (Dolma səbəti və digər istinadlar üçün sabit).
 * Wolt mənbələri: hər brend üçün bir sətir — links_json-da bütün kanal URL-ləri (sıra ilə),
 * variant / fallback_variants / parsing_rule isə options_json-da (mövcuddursa).
 */
class GunAzParsingStrategySeeder extends Seeder
{
    private const CSV_SKIP_ROWS = 3;

    /** @var array<string, int> price_categories.slug => id */
    private array $categoryIdBySlug = [];

    /** @var array<string, array{0: ?int, 1: ?float}> CSV «vahid» sütunu → [unit_id, unit_size] */
    private array $unitColumnCache = [];

    /** @var array<string, int|null> units.code → id (yoxdursa null) */
    private array $unitPkByCodeCache = [];

    public function run(): void
    {
        $path = (string) config('parsers.gun_az_csv_path', base_path('gun_az_FINAL_parsing_strategy D (2).csv'));
        if (! is_readable($path)) {
            $this->command?->error('CSV tapılmadı: '.$path);

            return;
        }

        PriceCategoryHierarchy::sync();

        DB::transaction(function () use ($path): void {
            $this->purgePreviousGunAzData();
            $this->categoryIdBySlug = [];
            $this->unitColumnCache = [];
            $this->unitPkByCodeCache = [];

            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new \RuntimeException('CSV oxuna bilmədi.');
            }

            $rowNum = 0;
            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowNum++;
                if ($rowNum <= self::CSV_SKIP_ROWS) {
                    continue;
                }
                if (count($data) < 27) {
                    continue;
                }
                $this->importRow($data);
            }
            fclose($handle);

            $this->ensureGunAzPositionsMatchCanonicalLeaves();
        });

        $this->command?->info('Gun.AZ CSV seed tamamlandı.');
    }

    /** gun_az_151–153 → child-products (CSV map və ya miqrasiya əvvəli uyğunsuzluğu üçün). */
    private function ensureGunAzPositionsMatchCanonicalLeaves(): void
    {
        $childProductsId = DB::table('price_categories')->where('slug', 'child-products')->value('id');
        if ($childProductsId === null) {
            return;
        }

        DB::table('price_positions')
            ->whereIn('slug', ['gun_az_151', 'gun_az_152', 'gun_az_153'])
            ->update([
                'category_id' => (int) $childProductsId,
                'updated_at' => now(),
            ]);
    }

    private function purgePreviousGunAzData(): void
    {
        $positionIds = DB::table('price_positions')
            ->where('slug', 'like', 'gun_az_%')
            ->pluck('id');

        if ($positionIds->isEmpty()) {
            return;
        }

        $ids = $positionIds->all();

        DB::table('source_price_results')->whereIn('position_id', $ids)->delete();
        DB::table('price_snapshots')->whereIn('position_id', $ids)->delete();
        DB::table('basket_items')->whereIn('position_id', $ids)->delete();
        DB::table('parser_run_errors')->whereIn('position_id', $ids)->delete();
        DB::table('price_sources')->whereIn('position_id', $ids)->delete();
        DB::table('price_positions')->whereIn('id', $ids)->delete();

        DB::table('price_categories')->where('slug', 'like', 'gun_az_cat_%')->delete();
    }

    /**
     * @param  list<string>  $data
     */
    private function importRow(array $data): void
    {
        $no = trim((string) ($data[0] ?? ''));
        if ($no === '' || ! ctype_digit($no)) {
            return;
        }

        $catTitle = trim((string) ($data[1] ?? ''));
        $nameRu = trim((string) ($data[2] ?? ''));
        $nameAz = trim((string) ($data[3] ?? ''));
        $vid = trim((string) ($data[4] ?? ''));
        $unitLabel = trim((string) ($data[5] ?? ''));
        $rule = trim((string) ($data[26] ?? ''));

        if ($nameAz === '' && $nameRu === '') {
            return;
        }

        $categoryId = $this->resolveCategoryId($catTitle);
        if ($categoryId === null) {
            // Intentionally not imported (e.g. household / Хозтовары).
            return;
        }

        $slug = 'gun_az_'.$no;
        $nameJson = json_encode(
            LocalizedJson::productName($nameAz, $nameRu),
            JSON_UNESCAPED_UNICODE
        );

        [$unitId, $unitSize] = $this->resolveUnitColumn($unitLabel);

        $positionPayload = [
            'category_id' => $categoryId,
            'slug' => $slug,
            'name' => $nameJson,
            'unit_id' => $unitId,
            'unit_size' => $unitSize,
            'parser_type' => 'wolt',
            'is_active' => true,
            'sort_order' => (int) $no * 10,
            'updated_at' => now(),
        ];

        $existing = DB::table('price_positions')->where('slug', $slug)->first();
        if ($existing === null) {
            $positionPayload['created_at'] = now();
            $positionId = (int) DB::table('price_positions')->insertGetId($positionPayload);
        } else {
            $positionId = (int) $existing->id;
            DB::table('price_positions')->where('id', $positionId)->update($positionPayload);
            DB::table('price_sources')->where('position_id', $positionId)->delete();
        }

        $brands = $this->collectBrands($data);
        $priorityBase = 10;

        foreach ($brands as $b) {
            $urls = $b['urls'];
            if ($urls === []) {
                continue;
            }

            $fallbackVariants = $this->fallbackVariants($vid, $rule);
            $options = [
                'brand' => $b['label'],
                'parsing_rule' => $rule,
                'csv_channel_order' => ['wolt_market', 'bravo_wolt', 'neptun_wolt'],
            ];
            if ($fallbackVariants !== []) {
                $options['fallback_variants'] = $fallbackVariants;
            }
            if ($vid !== '') {
                $options['variant'] = Str::limit($vid, 191, '');
            }

            $sourcePayload = [
                'position_id' => $positionId,
                'links_json' => json_encode(array_values($urls), JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'priority' => $priorityBase,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('price_sources', 'source_type')) {
                $sourcePayload['source_type'] = 'wolt';
            }
            if (Schema::hasColumn('price_sources', 'options_json')) {
                $sourcePayload['options_json'] = json_encode($options, JSON_UNESCAPED_UNICODE);
            }

            $sourcePayload['created_at'] = now();

            DB::table('price_sources')->insert($sourcePayload);

            $priorityBase += 10;
        }
    }

    private function resolveCategoryId(string $catTitle): ?int
    {
        $definitionKey = $this->mapCsvCategoryTitleToLeafCode($catTitle);
        $slug = PriceCategoryHierarchy::slugForDefinitionKey($definitionKey);
        if ($slug === null) {
            return null;
        }

        if (isset($this->categoryIdBySlug[$slug])) {
            return $this->categoryIdBySlug[$slug];
        }

        $id = DB::table('price_categories')->where('slug', $slug)->value('id');
        if ($id === null) {
            throw new \RuntimeException(
                'price_categories boşdur və ya iyerarxiya yoxdur. Əvvəl: php artisan db:seed --class=PriceCategoryHierarchySeeder'
            );
        }

        $this->categoryIdBySlug[$slug] = (int) $id;

        return (int) $id;
    }

    /**
     * @return array{0: ?int, 1: ?float}
     */
    private function resolveUnitColumn(string $unitLabel): array
    {
        $key = trim($unitLabel);
        if ($key === '') {
            return [null, null];
        }

        if (isset($this->unitColumnCache[$key])) {
            return $this->unitColumnCache[$key];
        }

        [$code, $size] = $this->parseGunAzCsvUnit($key);
        if ($code !== null) {
            $id = $this->unitPrimaryKeyForCode($code);
            $out = [$id, $size === null ? null : (float) $size];

            return $this->unitColumnCache[$key] = $out;
        }

        $row = DB::table('units')
            ->where(function ($q) use ($key): void {
                $q->where('name', $key)
                    ->orWhere('short_name', $key)
                    ->orWhere('code', $key);
            })
            ->first();
        if ($row !== null) {
            return $this->unitColumnCache[$key] = [(int) $row->id, null];
        }

        return $this->unitColumnCache[$key] = [null, null];
    }

    private function unitPrimaryKeyForCode(string $code): ?int
    {
        if (! array_key_exists($code, $this->unitPkByCodeCache)) {
            $this->unitPkByCodeCache[$code] = DB::table('units')->where('code', $code)->value('id');
        }

        $id = $this->unitPkByCodeCache[$code];

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{0: ?string, 1: float|int|null} [units.code (g, kg, ml, l, pcs, pack, bottle), ölçü]
     */
    private function parseGunAzCsvUnit(string $raw): array
    {
        $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($s === '') {
            return [null, null];
        }

        $s = preg_replace('/^(\d+(?:[.,]\d+)?)\s*rг\s*$/iu', '$1 кг', $s) ?? $s;

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(мл|ml)\s*$/iu', $s, $m)) {
            return ['ml', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(л|l|lt|litr|litrd)\s*$/iu', $s, $m)) {
            return ['l', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(кг|kg|kq)\s*$/iu', $s, $m)) {
            return ['kg', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(г|g|q|qr)\s*\.?$/iu', $s, $m)) {
            return ['g', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(шт|pcs?|pc|əd\.?|ed)\s*(\([^)]*\))?\s*$/iu', $s, $m)) {
            return ['pcs', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(пуч|pack|paket)\s*$/iu', $s, $m)) {
            return ['pack', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(бут|bottle|şüşə)\.?\s*$/iu', $s, $m)) {
            return ['bottle', $this->parseCsvQuantity($m[1])];
        }

        if (preg_match('/^(мл|ml)$/iu', $s)) {
            return ['ml', null];
        }

        if (preg_match('/^(л|l|lt)$/iu', $s)) {
            return ['l', null];
        }

        if (preg_match('/^(кг|kg|kq)$/iu', $s)) {
            return ['kg', null];
        }

        if (preg_match('/^(г|g|q)$/iu', $s)) {
            return ['g', null];
        }

        if (preg_match('/^(шт|pcs?|pc|əd|ed)$/iu', $s)) {
            return ['pcs', null];
        }

        return [null, null];
    }

    private function parseCsvQuantity(string $n): float|int
    {
        $n = str_replace(',', '.', $n);
        $f = (float) $n;

        return abs($f - round($f)) < 1e-9 ? (int) round($f) : $f;
    }

    /**
     * CSV kateqoriya başlığı → hierarchy definition key (config map), sonra DB `slug`-a çevrilir.
     */
    private function mapCsvCategoryTitleToLeafCode(string $catTitle): string
    {
        $stripped = $this->normalizeCsvCategoryTitleForLeafMap($catTitle);
        $map = config('parsers.gun_az_category_leaf_map', []);
        if (! is_array($map)) {
            return 'food_grocery';
        }
        if ($stripped !== '' && isset($map[$stripped])) {
            return (string) $map[$stripped];
        }

        return 'food_grocery';
    }

    private function normalizeCsvCategoryTitleForLeafMap(string $catTitle): string
    {
        $s = $catTitle;
        for ($i = 0; $i < 8; $i++) {
            $next = preg_replace('/^[\p{So}\s]+/u', '', $s) ?? $s;
            if ($next === $s) {
                break;
            }
            $s = $next;
        }

        return trim($s);
    }

    /**
     * @param  list<string>  $data
     * @return list<array{label: string, urls: list<string>}>
     */
    private function collectBrands(array $data): array
    {
        $slots = [
            [7, 8, 9, 10, 11, 12],
            [13, 14, 15, 16, 17, 18],
            [19, 20, 21, 22, 23, 24],
        ];

        $out = [];
        $n = 0;
        foreach ($slots as $cols) {
            $n++;
            [$lW, $uW, $lB, $uB, $lN, $uN] = [
                $data[$cols[0]] ?? '',
                $data[$cols[1]] ?? '',
                $data[$cols[2]] ?? '',
                $data[$cols[3]] ?? '',
                $data[$cols[4]] ?? '',
                $data[$cols[5]] ?? '',
            ];

            $label = $this->normalizeLabel($lW);
            if ($label === null) {
                $label = $this->normalizeLabel($lB) ?? $this->normalizeLabel($lN);
            }
            if ($label === null) {
                $label = 'Brand '.$n;
            }

            $urls = [];
            foreach ([$uW, $uB, $uN] as $u) {
                $url = $this->normalizeWoltUrl($u);
                if ($url !== null) {
                    $urls[] = $url;
                }
            }
            $urls = array_values(array_unique($urls));

            if ($urls === []) {
                continue;
            }

            $out[] = ['label' => $label, 'urls' => $urls];
        }

        return $out;
    }

    private function normalizeLabel(string $raw): ?string
    {
        $s = trim(str_replace(["\r", "\n"], ' ', $raw));
        if ($s === '' || $s === '—' || $s === '-') {
            return null;
        }
        if (preg_match('/^\(по весу\)$/u', $s) || strcasecmp($s, 'Wolt Market (по весу)') === 0) {
            return null;
        }

        return $s;
    }

    private function normalizeWoltUrl(string $raw): ?string
    {
        $s = trim(str_replace(["\r", "\n"], ' ', $raw));
        if ($s === '' || mb_strtolower($s) === 'не представлен') {
            return null;
        }
        if (str_starts_with($s, 'https://wolt.com')) {
            return $s;
        }
        if (str_starts_with($s, 'http://wolt.com')) {
            return 'https://'.substr($s, 7);
        }
        if (preg_match('#https://wolt\.com[^\s|"]+#u', $s, $m)) {
            return rtrim($m[0], ',.;');
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function fallbackVariants(string $vid, string $rule): array
    {
        $primary = $this->firstPercentToken($vid);
        $tokens = [];
        foreach ([$vid, $rule] as $chunk) {
            if (preg_match_all('/(\d+[.,]\d+)\s*%/u', (string) $chunk, $m)) {
                foreach ($m[1] as $p) {
                    $tokens[] = str_replace(',', '.', $p).'%';
                }
            }
        }
        $tokens = array_values(array_unique($tokens));
        if ($primary !== null) {
            $tokens = array_values(array_filter($tokens, fn (string $t) => $t !== $primary));
        }

        return $tokens;
    }

    private function firstPercentToken(string $s): ?string
    {
        if (preg_match('/(\d+[.,]\d+)\s*%/u', $s, $m)) {
            return str_replace(',', '.', $m[1]).'%';
        }

        return null;
    }
}
