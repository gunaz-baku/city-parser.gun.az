<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\BasketDefinition;
use App\Models\BasketSnapshot;
use App\Models\CityPriceSectionItem;
use App\Models\CityPriceSectionTab;
use App\Support\LocalizedJson;
use App\Support\SyntheticCity;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GunAzParserSnapshotController extends Controller
{
    /**
     * Son uğurlu parse günlüyü: DB-də ən son snapshot tarixi (eyni mühitdə son run-un yazdığı gün).
     */
    private function latestSnapshotDateStringForGunAzReference(?string $parserType): ?string
    {
        $q = DB::table('price_snapshots');
        if ($parserType !== null && $parserType !== '') {
            $q->where('parser_type', $parserType);
        }
        $max = $q->max('snapshot_date');
        if ($max === null) {
            return null;
        }

        try {
            return Carbon::parse((string) $max)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function snapshotDateMatchesReferenceDay(mixed $snapshotDate, ?string $referenceDateStr): bool
    {
        if ($referenceDateStr === null || $referenceDateStr === '') {
            return false;
        }
        if ($snapshotDate === null || $snapshotDate === '') {
            return false;
        }
        try {
            return Carbon::parse((string) $snapshotDate)->toDateString() === $referenceDateStr;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Mövqənin son snapshot-u istinad tarixi ilə üst-üstə düşmədikdə qiymət göndərilmir (GunAz «—»).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stripCityAverageRowPricesWhenNotReferenceDay(array $row): array
    {
        $row['avg_display_price'] = null;
        $row['price_min'] = null;
        $row['price_max'] = null;
        $row['price_avg'] = null;
        $row['price_avg_7_days_ago'] = null;
        $row['price_avg_30_days_ago'] = null;
        $row['sample_size'] = 0;
        $row['source_count'] = 0;

        return $row;
    }

    public function pullPriceSnapshots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parser_run_id' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = $validated['limit'] ?? 50;

        $query = DB::table('price_snapshots as ps')
            ->join('price_positions as pp', 'pp.id', '=', 'ps.position_id')
            ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
            ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
            ->where('ps.sync_status', 'pending')
            ->orderBy('ps.id')
            ->select(array_values(array_filter(array_merge([
                'ps.id',
                'ps.position_id',
                'ps.snapshot_date',
                'ps.currency',
                'ps.price_min',
                'ps.price_max',
                'ps.price_avg',
                'ps.sample_size',
                'ps.source_count',
                'ps.parser_type',
                'ps.parser_run_id',
                'ps.sync_status',
                'ps.synced_at',
                'ps.last_sync_error',
                'ps.created_at',
                'ps.updated_at',
                'pp.slug as position_code',
                'pp.slug as position_slug',
                'pp.name as position_name',
                self::positionUnitLabelSelect(),
                'pp.unit_size as position_unit_size',
                'pc.slug as category_code',
                'pc.slug as category_slug',
                'pc.name as category_name',
                Schema::hasColumn('price_categories', 'show_in_page') ? 'pc.show_in_page as category_show_in_page' : null,
            ], SyntheticCity::selectAliases()))));

        if (isset($validated['parser_run_id'])) {
            $query->where('ps.parser_run_id', $validated['parser_run_id']);
        }

        $rows = $query->limit($limit)->get();

        return response()->json([
            'snapshots' => $rows->map(fn ($r) => $this->formatPriceSnapshotRow($r))->values()->all(),
        ]);
    }

    public function acknowledgePriceSnapshots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $ids = $validated['ids'];
        $now = now();

        $updated = DB::table('price_snapshots')
            ->whereIn('id', $ids)
            ->where('sync_status', 'pending')
            ->update([
                'sync_status' => 'synced',
                'synced_at' => $now,
                'last_sync_error' => null,
                'updated_at' => $now,
            ]);

        return response()->json([
            'updated' => $updated,
            'requested' => count($ids),
        ]);
    }

    public function pullBasketSnapshots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'list_mode' => ['sometimes', 'string', 'in:full'],
        ]);

        if (($validated['list_mode'] ?? null) === 'full') {
            if (! Schema::hasTable('basket_snapshots')) {
                return response()->json(['data' => []]);
            }

            $limit = min(max((int) ($validated['limit'] ?? 100), 1), 200);
            $locale = AdminApiLocale::fromRequest($request);
            $snapshots = BasketSnapshot::query()
                ->with(['basket:id,name'])
                ->orderByDesc('snapshot_date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            $data = $snapshots->map(static function (BasketSnapshot $snapshot) use ($locale): array {
                return array_merge($snapshot->toArray(), AdminApiPresenter::basketSnapshotExtras($snapshot, $locale));
            });

            return response()->json(['data' => $data->values()->all()]);
        }

        $limit = $validated['limit'] ?? 50;

        $rows = DB::table('basket_snapshots as bs')
            ->join('basket_definitions as bd', 'bd.id', '=', 'bs.basket_id')
            ->where('bs.sync_status', 'pending')
            ->orderBy('bs.id')
            ->select(array_values(array_filter(array_merge([
                'bs.id',
                'bs.basket_id',
                'bs.snapshot_date',
                'bs.total_price',
                Schema::hasColumn('basket_snapshots', 'dolma_index_total') ? 'bs.dolma_index_total' : null,
                'bs.currency',
                'bs.sync_status',
                'bs.synced_at',
                'bs.last_sync_error',
                'bs.created_at',
                'bs.updated_at',
                'bd.name as basket_name',
            ], SyntheticCity::selectAliases()))))
            ->limit($limit)
            ->get();

        return response()->json([
            'basket_snapshots' => $rows->map(fn ($r) => $this->formatBasketSnapshotRow($r))->values()->all(),
        ]);
    }

    public function pullSourcePriceResults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parser_run_id' => ['sometimes', 'integer', 'min:1'],
            'position_id' => ['sometimes', 'integer', 'min:1'],
            'source_id' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = $validated['limit'] ?? 100;

        $sourceSelectExtras = [];
        if (Schema::hasTable('price_sources')) {
            foreach ([
                'links_json' => 'psrc.links_json',
                'options_json' => 'psrc.options_json',
                'is_active' => 'psrc.is_active',
                'priority' => 'psrc.priority',
                'source_name' => 'psrc.source_name',
                'source_url' => 'psrc.source_url',
                'external_source_id' => 'psrc.external_source_id',
                'source_config' => 'psrc.source_config',
            ] as $col => $expr) {
                if (Schema::hasColumn('price_sources', $col)) {
                    $sourceSelectExtras[] = $expr;
                }
            }
        }

        $query = DB::table('source_price_results as spr')
            ->join('price_positions as pp', 'pp.id', '=', 'spr.position_id')
            ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
            ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
            ->leftJoin('price_sources as psrc', 'psrc.id', '=', 'spr.source_id')
            ->orderByDesc('spr.id')
            ->select(array_values(array_filter(array_merge([
                'spr.id',
                'spr.parser_run_id',
                'spr.position_id',
                'spr.source_id',
                'spr.result_date',
                'spr.external_item_id',
                'spr.title',
                'spr.raw_price',
                'spr.raw_area',
                'spr.normalized_price',
                'spr.currency',
                'spr.is_outlier',
                'spr.is_valid',
                'spr.raw_payload',
                'spr.created_at',
                'pp.slug as position_code',
                'pp.slug as position_slug',
                'pp.name as position_name',
                self::positionUnitLabelSelect(),
                'pp.unit_size as position_unit_size',
                'pc.slug as category_code',
                'pc.slug as category_slug',
                'pc.name as category_name',
                Schema::hasColumn('price_categories', 'show_in_page') ? 'pc.show_in_page as category_show_in_page' : null,
                'psrc.id as src_table_id',
                'psrc.source_type',
            ], SyntheticCity::selectAliases(), $sourceSelectExtras))));

        if (isset($validated['parser_run_id'])) {
            $query->where('spr.parser_run_id', $validated['parser_run_id']);
        }
        if (isset($validated['position_id'])) {
            $query->where('spr.position_id', $validated['position_id']);
        }
        if (isset($validated['source_id'])) {
            $query->where('spr.source_id', $validated['source_id']);
        }

        $rows = $query->limit($limit)->get();

        return response()->json([
            'source_price_results' => $rows->map(fn ($r) => $this->formatSourcePriceResultRow($r))->values()->all(),
        ]);
    }

    /**
     * Legacy GunAz parsing tables export (JSON-only; separate arrays by table name).
     *
     * GET …/legacy-tables-export?city_id=1&max_runs=20
     */
    public function legacyTablesExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_runs' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $cityId = isset($validated['city_id']) ? (int) $validated['city_id'] : 1;
        if ($cityId !== 1) {
            throw ValidationException::withMessages([
                'city_id' => sprintf('Unsupported city_id "%d". This endpoint currently supports only "1".', $cityId),
            ]);
        }
        $maxRuns = max(1, (int) ($validated['max_runs'] ?? 20));

        $parserRuns = DB::table('parser_runs')
            ->orderByDesc('id')
            ->limit($maxRuns)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $units = DB::table('units')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $categories = DB::table('price_categories')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $products = DB::table('price_positions')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $snapshots = DB::table('price_snapshots')
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $sourceResults = DB::table('source_price_results')
            ->orderByDesc('id')
            ->limit(10000)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $sources = DB::table('price_sources')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $companiesMap = [];
        foreach ($sources as $s) {
            $name = trim((string) ($s['source_name'] ?? $s['source_type'] ?? ''));
            $code = trim((string) ($s['source_type'] ?? ''));
            if ($name === '' && $code === '') {
                continue;
            }
            $key = mb_strtolower($code !== '' ? $code : $name);
            $companiesMap[$key] = [
                'code' => $code !== '' ? $code : $key,
                'names' => [
                    'az' => $name !== '' ? $name : $key,
                    'en' => $name !== '' ? $name : $key,
                    'ru' => $name !== '' ? $name : $key,
                ],
                'is_active' => (bool) ($s['is_active'] ?? true),
                'sort_order' => 0,
            ];
        }

        $productAvg = DB::table('price_snapshots as ps')
            ->join('price_positions as pp', 'pp.id', '=', 'ps.position_id')
            ->selectRaw('pp.category_id as parsing_product_category_id, AVG(ps.price_avg) as avg_price, COUNT(DISTINCT pp.id) as products_count, MAX(ps.snapshot_date) as snapshot_date')
            ->groupBy('pp.category_id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values()
            ->all();

        $productPrices = array_map(static function (array $r): array {
            $price = isset($r['normalized_price']) && is_numeric($r['normalized_price']) ? (float) $r['normalized_price'] : null;

            return [
                'parsing_product_id' => (int) ($r['position_id'] ?? 0),
                'discounted_price' => $price,
                'original_price' => $price,
                'unit_price' => $price,
                'currency' => (string) ($r['currency'] ?? 'AZN'),
                'source' => (string) ($r['source_name'] ?? $r['source_type'] ?? ''),
                'recorded_at' => (string) ($r['result_date'] ?? ''),
                'meta' => [
                    'source_result_id' => $r['id'] ?? null,
                    'source_id' => $r['source_id'] ?? null,
                    'title' => $r['title'] ?? null,
                ],
            ];
        }, $sourceResults);

        return response()->json([
            'meta' => [
                'city_id' => $cityId,
                'generated_at' => now()->toIso8601String(),
            ],
            'parser_runs' => $parserRuns,
            'parsing_companies' => array_values($companiesMap),
            'parsing_product_avg' => $productAvg,
            'parsing_product_categories' => $categories,
            'parsing_product_prices' => $productPrices,
            'parsing_products' => $products,
            'parsing_units' => $units,
            'price_snapshots' => $snapshots,
            'price_sources' => $sources,
        ]);
    }

    public function cityPriceAverages(Request $request): JsonResponse
    {
        $payload = $this->buildCityPriceAveragesPayload($request);
        if ($payload === null) {
            return response()->json(['message' => 'Unable to build price averages payload.'], 500);
        }

        return response()->json($payload);
    }

    /**
     * Gun.Az qiymət səhifəsi üçün birləşmiş cavab: orta qiymətlər, nav kateqoriyaları, dolma indeksi.
     */
    public function cityPricesPage(Request $request): JsonResponse
    {
        $averages = $this->buildCityPriceAveragesPayload($request);
        if ($averages === null) {
            return response()->json(['message' => 'Unable to build price averages payload.'], 500);
        }

        return response()->json([
            'averages' => $averages,
            'nav_categories' => $this->buildNavCategories(),
            'dolma_index' => $this->buildDolmaIndex(),
            'highlights' => $this->buildPriceHighlights(),
        ]);
    }

    /**
     * GunAz city səhifəsində “price section” — admin seçimi ilə (4 tab).
     *
     * GET …/city-price-section?city_code=baku
     */
    public function cityPriceSection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $this->resolveRequestedCityCode($validated['city_code'] ?? null);

        $cityRow = SyntheticCity::asDbRow();
        $locale = AdminApiLocale::fromRequest($request);

        $tabs = CityPriceSectionTab::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = CityPriceSectionItem::query()
            ->select('city_price_section_items.*')
            ->join('price_positions as pp', 'pp.id', '=', 'city_price_section_items.position_id')
            ->with([
                'tab:id,route_slug,name,sort_order,is_active',
                'position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order',
                'position.measurementUnit:id,code,name,short_name',
            ])
            ->orderBy('city_price_section_items.tab_id')
            ->orderBy('pp.sort_order')
            ->orderBy('pp.id')
            ->get();

        $positionIds = [];
        foreach ($items as $item) {
            $tabId = (int) $item->tab_id;
            if ($tabId < 1 || $item->position === null) {
                continue;
            }
            $positionIds[] = (int) $item->position_id;
        }
        $positionIds = array_values(array_unique(array_filter($positionIds, fn (int $v) => $v >= 1)));

        $snapshots = collect();
        if ($positionIds !== []) {
            $snapshots = DB::table('price_snapshots')
                ->whereIn('position_id', $positionIds)
                ->orderByDesc('snapshot_date')
                ->orderByDesc('id')
                ->get(['position_id', 'snapshot_date', 'price_avg', 'currency', 'id']);
        }

        /** @var array<int, array{latest: ?object, week_base: ?object}> $snapByPos */
        $snapByPos = [];
        $snapshotsByPos = $snapshots->groupBy(fn ($row) => (int) $row->position_id);
        foreach ($snapshotsByPos as $pid => $posRows) {
            $latest = $posRows->first();
            if ($latest === null) {
                $snapByPos[(int) $pid] = ['latest' => null, 'week_base' => null];
                continue;
            }

            $latestDate = Carbon::parse((string) $latest->snapshot_date)->startOfDay();
            $weekThreshold = $latestDate->copy()->subDays(7);
            $weekBase = $posRows->first(function ($row) use ($weekThreshold) {
                if (! isset($row->snapshot_date)) {
                    return false;
                }

                return Carbon::parse((string) $row->snapshot_date)->startOfDay()->lte($weekThreshold);
            });

            $snapByPos[(int) $pid] = ['latest' => $latest, 'week_base' => $weekBase];
        }

        /** @var array<int, list<array{position_id: int, title: string, price: string, trend: string}>> $rowsByTabId */
        $rowsByTabId = [];

        foreach ($items as $item) {
            $pos = $item->position;
            if ($pos === null) {
                continue;
            }
            $tabId = (int) $item->tab_id;
            if ($tabId < 1) {
                continue;
            }

            $extras = AdminApiPresenter::pricePositionExtras($pos, $locale);
            $title = trim((string) ($extras['name_label'] ?? ''));
            $unitLabel = trim((string) ($extras['unit_label'] ?? ''));
            $unitSize = $extras['unit_size'] ?? null;
            if ($unitLabel !== '') {
                $suffix = $unitSize !== null && is_numeric($unitSize)
                    ? (string) (0 + (float) $unitSize).' '.$unitLabel
                    : $unitLabel;
                $title = $title !== '' ? ($title.', '.$suffix) : $suffix;
            }
            if ($title === '') {
                $title = (string) ($pos->slug ?? ('pos-'.$pos->id));
            }

            $snapPair = $snapByPos[(int) $pos->id] ?? ['latest' => null, 'week_base' => null];
            $latest = $snapPair['latest'];
            $weekBase = $snapPair['week_base'];

            $price = '—';
            $trend = 'neutral';
            if ($latest !== null && $latest->price_avg !== null) {
                $price = number_format((float) $latest->price_avg, 2, ',', '');
            }
            if ($latest !== null && $weekBase !== null && $latest->price_avg !== null && $weekBase->price_avg !== null) {
                $diff = (float) $latest->price_avg - (float) $weekBase->price_avg;
                if (abs($diff) < 1e-8) {
                    $trend = 'neutral';
                } elseif ($diff > 0) {
                    $trend = 'up';
                } else {
                    $trend = 'down';
                }
            }

            $rowsByTabId[$tabId][] = [
                'position_id' => (int) $pos->id,
                'title' => $title,
                'price' => $price,
                'trend' => $trend,
            ];
        }

        $dolmaBlock = $this->buildDolmaIndex();
        $dolma = $dolmaBlock !== null
            ? [
                'value' => (string) ($dolmaBlock['value'] ?? '—'),
                'trend' => (string) ($dolmaBlock['trend'] ?? 'neutral'),
                'delta_value' => $dolmaBlock['delta_value'] ?? null,
                'delta_percent' => $dolmaBlock['delta_percent'] ?? null,
            ]
            : ['value' => '—', 'trend' => 'neutral'];

        $tabsPayload = $tabs->map(function (CityPriceSectionTab $t) use ($locale): array {
            $name = is_array($t->name) ? $t->name : [];
            $label = (string) ($name[$locale] ?? $name['az'] ?? $t->route_slug ?? '');

            return [
                'id' => (int) $t->id,
                'route_slug' => $t->route_slug,
                'name' => $name,
                'name_label' => $label,
            ];
        })->values()->all();

        return response()->json([
            'tabs' => $tabsPayload,
            'rows' => $rowsByTabId,
            'dolma' => $dolma,
            'meta' => [
                'city' => $this->formatCityBlock($cityRow),
            ],
        ]);
    }

    /**
     * Yalnız qiymət səhifəsi yan paneli (kök kateqoriyalar + uşaq sayı).
     */
    public function cityNavCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $this->resolveRequestedCityCode($validated['city_code'] ?? null);

        return response()->json([
            'nav_categories' => $this->buildNavCategories(),
        ]);
    }

    /**
     * Tək mövqe üçün təqvim ili üzrə snapshot tarixçəsi (diaqram + aylıq siyahı statistikası).
     *
     * GET …/position-price-history?city_code=baku&position_id=… | &position_slug=…&year=2026
     */
    public function positionPriceHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['required', 'string', 'max:100'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'position_slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:2010', 'max:2100'],
        ]);

        $this->resolveRequestedCityCode($validated['city_code']);
        $cityRow = SyntheticCity::asDbRow();
        $positionId = isset($validated['position_id']) ? (int) $validated['position_id'] : 0;
        $positionSlug = isset($validated['position_slug']) ? trim((string) $validated['position_slug']) : '';

        if ($positionId < 1 && $positionSlug === '') {
            return response()->json([
                'message' => 'Provide position_id or position_slug.',
            ], 422);
        }

        $resolvedId = $this->resolvePricePositionIdForHistory($positionId >= 1 ? $positionId : null, $positionSlug);
        if ($resolvedId === null) {
            return response()->json([
                'message' => 'Price position not found for this city.',
            ], 404);
        }

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) Carbon::now()->year;
        $from = sprintf('%04d-01-01', $year);
        $until = sprintf('%04d-12-31', $year);

        $snapshots = DB::table('price_snapshots as ps')
            ->where('ps.position_id', $resolvedId)
            ->whereBetween('ps.snapshot_date', [$from, $until])
            ->orderBy('ps.snapshot_date')
            ->orderBy('ps.id')
            ->get(['ps.snapshot_date', 'ps.price_avg']);

        $values = $snapshots->map(fn ($r) => (float) $r->price_avg)->filter(fn ($v) => is_finite($v))->values();
        $summary = [
            'snapshot_count' => $snapshots->count(),
            'min_price' => $values->isNotEmpty() ? round((float) $values->min(), 4) : null,
            'max_price' => $values->isNotEmpty() ? round((float) $values->max(), 4) : null,
            'avg_price' => $values->isNotEmpty() ? round((float) $values->avg(), 4) : null,
        ];

        $chartPoints = $snapshots->map(function ($r) {
            $d = Carbon::parse((string) $r->snapshot_date)->startOfDay();

            return [
                'label' => $d->format('d.m'),
                'value' => round((float) $r->price_avg, 2),
                'date' => $d->format('Y-m-d'),
            ];
        })->values()->all();

        $monthsGrouped = $snapshots->groupBy(function ($r) {
            return Carbon::parse((string) $r->snapshot_date)->format('Y-m');
        })->sortKeysDesc()->take(8);

        $monthsForStat = [];
        foreach ($monthsGrouped as $ym => $group) {
            $monthsForStat[$ym] = $group->sortByDesc('snapshot_date')->map(function ($r) {
                $d = Carbon::parse((string) $r->snapshot_date)->startOfDay();

                return [
                    'snapshot_date' => $d->toDateString(),
                    'avg_price' => round((float) $r->price_avg, 4),
                ];
            })->values()->all();
        // })->take(4)->values()->all();
        }

        $positionPayload = null;
        $categoryPayload = null;
        $posMeta = DB::table('price_positions as pp')
            ->leftJoin('price_categories as pc', 'pc.id', '=', 'pp.category_id')
            ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
            ->where('pp.id', $resolvedId)
            ->first([
                'pp.id',
                'pp.slug',
                'pp.name',
                'pp.unit_size',
                'pc.id as category_id',
                'pc.slug as category_slug',
                'pc.name as category_name',
                DB::raw('COALESCE(NULLIF(TRIM(u.short_name), ""), NULLIF(TRIM(u.name), ""), "") as unit_label'),
            ]);
        if ($posMeta !== null) {
            $unitLabel = trim((string) ($posMeta->unit_label ?? ''));
            $unitPayload = $unitLabel !== ''
                ? ['az' => $unitLabel, 'en' => $unitLabel, 'ru' => $unitLabel]
                : null;
            $positionPayload = [
                'id' => (int) $posMeta->id,
                'slug' => (string) ($posMeta->slug ?? ''),
                'name' => $this->decodeJsonColumn($posMeta->name),
                'unit' => $unitPayload,
                'unit_label' => $unitLabel !== '' ? $unitLabel : null,
                'unit_name' => $unitLabel !== '' ? $unitLabel : null,
                'unit_size' => $this->positionSnapshotUnitSize($posMeta->unit_size ?? null),
                'unit_meta' => LocalizedJson::unitTriple($unitLabel, ''),
            ];
            $categoryPayload = [
                'id' => isset($posMeta->category_id) ? (int) $posMeta->category_id : null,
                'slug' => (string) ($posMeta->category_slug ?? ''),
                'name' => $this->decodeJsonColumn($posMeta->category_name ?? null),
            ];
        }

        return response()->json([
            'city' => $this->formatCityBlock($cityRow),
            'year' => $year,
            'position_id' => $resolvedId,
            'position' => $positionPayload,
            'category' => $categoryPayload,
            'chart_points' => $chartPoints,
            'months_for_stat' => $monthsForStat,
            'summary' => $summary,
            'meta' => [
                'source' => 'parser.gun.az',
                'range' => ['from' => $from, 'until' => $until],
            ],
        ]);
    }

    private function resolvePricePositionIdForHistory(?int $positionId, string $itemSlug): ?int
    {
        if ($positionId !== null && $positionId >= 1) {
            $row = DB::table('price_positions')->where('id', $positionId)->first();
            if ($row !== null) {
                return (int) $row->id;
            }
        }

        $slug = trim($itemSlug);
        if ($slug === '') {
            return null;
        }

        $direct = DB::table('price_positions')->where('slug', $slug)->first();
        if ($direct !== null) {
            return (int) $direct->id;
        }

        if (preg_match('/-(\d+)$/', $slug, $m)) {
            $byNumericId = DB::table('price_positions')->where('id', (int) $m[1])->first();
            if ($byNumericId !== null) {
                return (int) $byNumericId->id;
            }
            $base = (string) preg_replace('/-\d+$/', '', $slug);
            if ($base !== '' && $base !== $slug) {
                $byBase = DB::table('price_positions')->where('slug', $base)->first();
                if ($byBase !== null) {
                    return (int) $byBase->id;
                }
            }
        }

        return null;
    }

    /**
     * @return ?array<string, mixed> null — şəhər tapılmadı
     */
    private function buildCityPriceAveragesPayload(Request $request): ?array
    {
        $validated = $request->validate([
            'city_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'snapshot_date' => ['sometimes', 'date'],
            'parser_type' => ['sometimes', 'string', 'max:50'],
            'category_slug' => ['sometimes', 'string', 'max:160'],
        ]);

        $this->resolveRequestedCityCode($validated['city_code'] ?? null);
        $cityRow = SyntheticCity::asDbRow();

        $hasAnySnapshot = DB::table('price_snapshots')->exists();
        if (! $hasAnySnapshot) {
            return [
                'city' => $this->formatCityBlock($cityRow),
                'snapshot_date' => null,
                'meta' => [
                    'updated_display' => null,
                    'source' => 'parser.gun.az',
                    'message' => 'No price_snapshots for this city yet.',
                ],
                'rows' => [],
            ];
        }

        $selectCols = array_values(array_filter(array_merge([
            'ps.id as snapshot_row_id',
            'ps.position_id',
            'ps.snapshot_date',
            'ps.currency',
            'ps.price_min',
            'ps.price_max',
            'ps.price_avg',
            'ps.sample_size',
            'ps.source_count',
            'ps.parser_type',
            'ps.parser_run_id',
            'pp.slug as position_code',
            'pp.slug as position_slug',
            'pp.name as position_name',
            self::positionUnitLabelSelect(),
            'pp.unit_size as position_unit_size',
            'pc.slug as category_code',
            'pc.slug as category_slug',
            'pc.name as category_name',
            Schema::hasColumn('price_categories', 'show_in_page') ? 'pc.show_in_page as category_show_in_page' : null,
        ], SyntheticCity::selectAliases())));

        if (isset($validated['snapshot_date'])) {
            $snapshotDate = $validated['snapshot_date'];
            $query = DB::table('price_snapshots as ps')
                ->join('price_positions as pp', 'pp.id', '=', 'ps.position_id')
                ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
                ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
                ->whereDate('ps.snapshot_date', $snapshotDate)
                ->orderBy('pp.sort_order')
                ->orderBy('pp.id')
                ->select($selectCols);
        } else {
            $latestPerPosition = DB::table('price_snapshots')
                ->select('position_id', DB::raw('MAX(snapshot_date) as max_snapshot_date'))
                ->when(isset($validated['parser_type']), function ($q) use ($validated): void {
                    $q->where('parser_type', $validated['parser_type']);
                })
                ->groupBy('position_id');

            $query = DB::table('price_snapshots as ps')
                ->joinSub($latestPerPosition, 'lp', function ($join): void {
                    $join->on('lp.position_id', '=', 'ps.position_id')
                        ->on('lp.max_snapshot_date', '=', 'ps.snapshot_date');
                })
                ->join('price_positions as pp', 'pp.id', '=', 'ps.position_id')
                ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
                ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
                ->orderBy('pp.sort_order')
                ->orderBy('pp.id')
                ->select($selectCols);
        }

        if (isset($validated['parser_type'])) {
            $query->where('ps.parser_type', $validated['parser_type']);
        }

        if (isset($validated['category_slug'])) {
            $slug = trim((string) $validated['category_slug']);

            if ($slug !== '') {
                $cat = $this->resolvePriceCategoryRowForFilterSlug($slug);

                if ($cat !== null) {
                    $query->where('pp.category_id', (int) $cat->id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        $rows = $query->get();

        if (isset($validated['snapshot_date'])) {
            $snapshotDateOut = (string) $validated['snapshot_date'];
        } else {
            $maxSnapshotDate = $rows->max('snapshot_date');
            $snapshotDateOut = $maxSnapshotDate !== null ? (string) $maxSnapshotDate : null;
        }

        $positionIds = $rows->pluck('position_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $deltasByPosition = $this->buildPositionDeltasForAveragesRows($positionIds);

        $hideStalePrices = ! isset($validated['snapshot_date']);
        $parserTypeForRef = isset($validated['parser_type']) ? (string) $validated['parser_type'] : null;
        $referenceSnapshotDateStr = $hideStalePrices
            ? $this->latestSnapshotDateStringForGunAzReference($parserTypeForRef)
            : null;

        return [
            'city' => $this->formatCityBlock($cityRow),
            'snapshot_date' => $snapshotDateOut,
            'meta' => array_merge([
                'updated_display' => $snapshotDateOut !== null
                    ? Carbon::parse($snapshotDateOut)->format('d.m.Y')
                    : null,
                'source' => 'parser.gun.az',
            ], ($hideStalePrices && $referenceSnapshotDateStr !== null) ? [
                'reference_snapshot_date' => $referenceSnapshotDateStr,
            ] : []),
            'rows' => $rows->map(function ($r) use ($deltasByPosition, $hideStalePrices, $referenceSnapshotDateStr): array {
                $formatted = $this->formatCityAverageRow($r, $deltasByPosition[(int) ($r->position_id ?? 0)] ?? null);
                if ($hideStalePrices
                    && $referenceSnapshotDateStr !== null
                    && ! $this->snapshotDateMatchesReferenceDay($r->snapshot_date ?? null, $referenceSnapshotDateStr)) {
                    return $this->stripCityAverageRowPricesWhenNotReferenceDay($formatted);
                }

                return $formatted;
            })->values()->all(),
        ];
    }

    /**
     * Parser API hazırda yalnız SyntheticCity (Bakı) datası saxlayır.
     *
     * @throws ValidationException
     */
    private function resolveRequestedCityCode(null|string $requested): string
    {
        $code = Str::of((string) ($requested ?? ''))
            ->trim()
            ->lower()
            ->toString();

        if ($code === '' || $code === SyntheticCity::CODE || $code === 'baki') {
            return SyntheticCity::CODE;
        }

        throw ValidationException::withMessages([
            'city_code' => sprintf(
                'Unsupported city_code "%s". This endpoint currently supports only "%s".',
                $code,
                SyntheticCity::CODE
            ),
        ]);
    }

    /**
     * @param  list<int>  $positionIds
     * @return array<int, array{
     *   week_trend_pct: ?float,
     *   price_avg_7_days_ago: ?float,
     *   price_avg_30_days_ago: ?float
     * }>
     */
    private function buildPositionDeltasForAveragesRows(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        $rows = DB::table('price_snapshots')
            ->whereIn('position_id', $positionIds)
            ->orderBy('position_id')
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->get(['position_id', 'snapshot_date', 'price_avg']);

        $out = [];
        foreach ($rows->groupBy('position_id') as $positionId => $group) {
            $series = collect($group)
                ->map(function ($r) {
                    if ($r->snapshot_date === null || !is_numeric($r->price_avg)) {
                        return null;
                    }
                    return (object) [
                        'snapshot_date' => Carbon::parse((string) $r->snapshot_date),
                        'avg_price' => (float) $r->price_avg,
                    ];
                })
                ->filter()
                ->sortByDesc('snapshot_date')
                ->values();

            if ($series->count() < 2) {
                $out[(int) $positionId] = [
                    'week_trend_pct' => null,
                    'price_avg_7_days_ago' => null,
                    'price_avg_30_days_ago' => null,
                ];
                continue;
            }

            $latest = $series->first();
            $weekBase = $this->nearestSnapshotInRange($series, $latest, 7, 5, 9);
            $monthBase = $this->nearestSnapshotInRange($series, $latest, 30, 26, 32);
            [, $weekPct] = $this->computeDeltaPair(
                (float) $latest->avg_price,
                $weekBase ? (float) $weekBase->avg_price : null
            );

            $out[(int) $positionId] = [
                'week_trend_pct' => $weekPct !== null ? round($weekPct, 1) : null,
                'price_avg_7_days_ago' => $weekBase ? (float) $weekBase->avg_price : null,
                'price_avg_30_days_ago' => $monthBase ? (float) $monthBase->avg_price : null,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, object{snapshot_date: Carbon, avg_price: float}>  $rows
     * @param  object{snapshot_date: Carbon, avg_price: float}  $latest
     * @return object{snapshot_date: Carbon, avg_price: float}|null
     */
    private function nearestSnapshotInRange(Collection $rows, object $latest, int $targetDays, int $minDays, int $maxDays): ?object
    {
        $rangeStart = $latest->snapshot_date->copy()->subDays($maxDays)->startOfDay();
        $rangeEnd = $latest->snapshot_date->copy()->subDays($minDays)->endOfDay();
        $target = $latest->snapshot_date->copy()->subDays($targetDays);

        $candidates = $rows
            ->skip(1) // latest-i çıxarırıq (əgər ilkdirsə)
            ->filter(fn ($r) => $r->snapshot_date->gte($rangeStart) && $r->snapshot_date->lte($rangeEnd));

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortBy(fn ($r) => abs($r->snapshot_date->getTimestamp() - $target->getTimestamp()))->first();
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function computeDeltaPair(float $current, ?float $previous): array
    {
        if ($previous === null || abs($previous) <= 1e-8) {
            return [null, null];
        }

        $value = $current - $previous;
        $percent = ($value / $previous) * 100;

        return [$value, $percent];
    }

    /**
     * Kateqoriya slug/code (kök və ya yarpaq) → mövqelərin category_id-si bu ağacdadır.
     *
     * @return list<int>
     */
    private function categoryIdsForAverageFilter(string $slug): array
    {
        $row = $this->resolvePriceCategoryRowForFilterSlug($slug);

        if ($row === null) {
            return [];
        }

        return [(int) $row->id];
    }

    /**
     * URL / category_slug (nav route_slug və ya köhnə az-slug) → price_categories sətri.
     * Uyğunluq tapılmayanda [] → filter tətbiq olunmur; buna görə köhnə nav alias-ları əlavə edilir.
     */
    private function resolvePriceCategoryRowForFilterSlug(string $slug): ?object
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $row = DB::table('price_categories')
            ->where(function ($q) use ($slug): void {
                $q->where('slug', $slug);
            })
            ->first();

        if ($row !== null) {
            return $row;
        }

        /** @var array<string, string> Str::slug(az) və köhnə URL → price_categories.slug */
        $legacyRouteToCanonicalSlug = [
            'qida-mehsullari' => 'products',
            'et' => 'products',
            'sud-mehsullari' => 'products',
            'terevezler' => 'products',
            'terevuzler' => 'products',
            'meyveler' => 'products',
            'dasinmaz-emlak' => 'real-estate',
            'restoranlar' => 'restaurants',
            'tibb' => 'medicine',
            'usaq-baximi' => 'childcare',
            'taxil-ve-un-mehsullari' => 'products',
            'elave-erzaqlar' => 'products',
            'sirniyyat-ve-ickiler' => 'products',
            'satis' => 'sale',
            'kiraye' => 'rent',
        ];

        if (isset($legacyRouteToCanonicalSlug[$slug])) {
            $canon = $legacyRouteToCanonicalSlug[$slug];
            $row = DB::table('price_categories')
                ->where(function ($q) use ($canon): void {
                    $q->where('slug', $canon);
                })
                ->first();
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function collectDescendantCategoryIds(int $rootId): array
    {
        return [$rootId];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildNavCategories(): array
    {
        $cats = DB::table('price_categories')
            ->where('is_active', true)
            ->when(Schema::hasColumn('price_categories', 'show_in_page'), function ($q) {
                $q->where('show_in_page', true);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($cats->isEmpty()) {
            return [];
        }

        $positionsByCategory = DB::table('price_positions')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'category_id',
                'slug',
                'name',
            ])
            ->groupBy('category_id');

        return $cats->map(function ($root) use ($positionsByCategory): array {
            $name = $this->decodeJsonColumn($root->name);
            $az = is_array($name) ? (string) ($name['az'] ?? '') : '';

            $dbRootSlug = trim((string) ($root->slug ?? ''));

            $routeSlug = $dbRootSlug !== ''
                ? $dbRootSlug
                : ($az !== '' ? Str::slug($az) : '');

            $children = collect($positionsByCategory[(int) $root->id] ?? [])
                ->map(function ($pos): array {
                    $posName = $this->decodeJsonColumn($pos->name);
                    $posAz = is_array($posName) ? (string) ($posName['az'] ?? '') : '';

                    $posSlug = trim((string) ($pos->slug ?? ''));

                    $posRouteSlug = $posSlug !== ''
                        ? $posSlug
                        : ($posAz !== '' ? Str::slug($posAz) : '');

                    return [
                        'id' => (int) $pos->id,
                        'code' => $posSlug,
                        'slug' => $posSlug,
                        'route_slug' => $posRouteSlug,
                        'name' => $posName,
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => (int) $root->id,
                'code' => (string) ($root->slug ?? ''),
                'slug' => $root->slug,
                'route_slug' => $routeSlug,
                'name' => $name,
                'show_in_page' => isset($root->show_in_page)
                    ? (bool) $root->show_in_page
                    : true,
                'sort_order' => (int) $root->sort_order,
                'children_count' => count($children),
                'children' => [],
            ];
        })->values()->all();
    }

    private function countPositionsUnderCategory(int $categoryId, Collection $posCounts): int
    {
        return (int) ($posCounts[$categoryId] ?? 0);
    }

    /**
     * @return ?array{value: string, trend: string, currency: string, delta_value: ?string, delta_percent: ?float}
     */
    private function buildDolmaIndex(): ?array
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasTable('basket_snapshots')) {
            return null;
        }

        $dolmaBasketId = BasketDefinition::resolveDolmaIndexSourceBasketId();

        if (Schema::hasColumn('basket_snapshots', 'dolma_index_total') && $dolmaBasketId !== null) {
            // `dolma_index_total` bəzən null qalır (köhnə job); həmin gün üçün dolma səbətinin `total_price`-ı eyni mənadadır.
            $byDate = DB::table('basket_snapshots')
                ->where('basket_id', $dolmaBasketId)
                ->select('snapshot_date')
                ->selectRaw('MAX(COALESCE(dolma_index_total, total_price)) as dolma_index_total')
                ->groupBy('snapshot_date')
                ->havingRaw('MAX(COALESCE(dolma_index_total, total_price)) IS NOT NULL')
                ->orderByDesc('snapshot_date')
                ->limit(2)
                ->get();

            if ($byDate->isNotEmpty()) {
                $latest = $byDate->first();
                $prev = $byDate->get(1);
                $value = number_format((float) $latest->dolma_index_total, 2, ',', '');
                $trend = 'neutral';
                $deltaValue = null;
                $deltaPercent = null;
                if ($prev !== null) {
                    $diff = (float) $latest->dolma_index_total - (float) $prev->dolma_index_total;
                    if ($diff > 1e-6) {
                        $trend = 'up';
                    } elseif ($diff < -1e-6) {
                        $trend = 'down';
                    }
                    $deltaValue = number_format($diff, 2, ',', '');
                    $base = (float) $prev->dolma_index_total;
                    $deltaPercent = abs($base) > 1e-8 ? round(($diff / $base) * 100, 2) : null;
                }

                return [
                    'value' => $value,
                    'trend' => $trend,
                    'currency' => 'AZN',
                    'delta_value' => $deltaValue,
                    'delta_percent' => $deltaPercent,
                ];
            }
        }

        $basket = $dolmaBasketId !== null
            ? DB::table('basket_definitions')->where('id', $dolmaBasketId)->first()
            : null;

        if ($basket === null) {
            $basket = DB::table('basket_definitions')
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->where('name->az', 'like', '%dolma%')
                        ->orWhere('name->en', 'like', '%dolma%')
                        ->orWhere('name->ru', 'like', '%долм%');
                })
                ->orderBy('id')
                ->first();
        }

        if ($basket === null) {
            $basket = DB::table('basket_definitions')->where('is_active', true)->orderBy('id')->first();
        }

        if ($basket === null) {
            return null;
        }

        $snaps = DB::table('basket_snapshots')
            ->where('basket_id', $basket->id)
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->get();

        if ($snaps->isEmpty()) {
            return null;
        }

        $latest = $snaps->first();
        $prev = $snaps->get(1);
        $value = number_format((float) $latest->total_price, 2, ',', '');
        $trend = 'neutral';
        $deltaValue = null;
        $deltaPercent = null;
        if ($prev !== null) {
            $diff = (float) $latest->total_price - (float) $prev->total_price;
            if ($diff > 1e-6) {
                $trend = 'up';
            } elseif ($diff < -1e-6) {
                $trend = 'down';
            }
            $deltaValue = number_format($diff, 2, ',', '');
            $base = (float) $prev->total_price;
            $deltaPercent = abs($base) > 1e-8 ? round(($diff / $base) * 100, 2) : null;
        }

        return [
            'value' => $value,
            'trend' => $trend,
            'currency' => (string) ($latest->currency ?? 'AZN'),
            'delta_value' => $deltaValue,
            'delta_percent' => $deltaPercent,
        ];
    }

    /**
     * Qiymət səhifəsi üst sətri: hər bir alt kateqoriya üçün ümumi orta qiymətin dəyişimi (son iki snapshot günü — günlük müqayisə).
     *
     * @return list<array{key: string, trend: string, label: ?string, name?: array<string, string>|null, route_slug?: ?string, parent_route_slug?: ?string}>
     */
    private function buildPriceHighlights(): array
    {
        $badgeHighlights = $this->buildBadgeHighlights();
        $categoryHighlights = $this->buildCategoryHighlightsFallback();
        if ($badgeHighlights === []) {
            return $categoryHighlights;
        }
        if ($categoryHighlights === []) {
            return $badgeHighlights;
        }

        $out = [];
        $seenKeys = [];
        foreach (array_merge($badgeHighlights, $categoryHighlights) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $k = (string) ($row['key'] ?? '');
            if ($k !== '' && isset($seenKeys[$k])) {
                continue;
            }
            if ($k !== '') {
                $seenKeys[$k] = true;
            }
            $out[] = $row;
            if (count($out) >= 32) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{key: string, trend: string, label: ?string, name?: array<string, string>|null, route_slug?: ?string, parent_route_slug?: ?string}>
     */
    private function buildBadgeHighlights(): array
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasTable('basket_items') || ! Schema::hasTable('price_snapshots') || ! Schema::hasTable('price_positions')) {
            return [];
        }

        $badges = BasketDefinition::query()
            ->where('is_active', true)
            ->where('type', BasketDefinition::TYPE_BADGE)
            ->with('media')
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($badges->isEmpty()) {
            return [];
        }

        $itemsByBasket = DB::table('basket_items as bi')
            ->join('price_positions as pp', 'pp.id', '=', 'bi.position_id')
            ->whereIn('bi.basket_id', $badges->pluck('id')->all())
            ->where('pp.is_active', true)
            ->get(['bi.basket_id', 'bi.position_id'])
            ->groupBy('basket_id');

        $allPositionIds = collect($itemsByBasket)
            ->flatten(1)
            ->pluck('position_id')
            ->map(static fn ($v): int => (int) $v)
            ->filter(static fn (int $v): bool => $v > 0)
            ->unique()
            ->values()
            ->all();
        $deltasByPosition = $this->buildPositionDeltasForAveragesRows($allPositionIds);

        $out = [];
        foreach ($badges as $badge) {
            $positionIds = collect($itemsByBasket[(int) $badge->id] ?? [])
                ->pluck('position_id')
                ->map(static fn ($v): int => (int) $v)
                ->filter(static fn (int $v): bool => $v > 0)
                ->unique()
                ->values()
                ->all();

            $weekPcts = [];
            foreach ($positionIds as $positionId) {
                $delta = $deltasByPosition[$positionId] ?? null;
                if (! is_array($delta)) {
                    continue;
                }
                $pct = $delta['week_trend_pct'] ?? null;
                if ($pct !== null && is_numeric($pct)) {
                    $weekPcts[] = (float) $pct;
                }
            }

            $avgChangeWeekPct = $weekPcts === []
                ? null
                : (array_sum($weekPcts) / count($weekPcts));

            $trend = 'neutral';
            $statusByLocale = [
                'az' => 'sabitdir',
                'en' => 'stable',
                'ru' => 'стабилен',
            ];
            if ($avgChangeWeekPct !== null && $avgChangeWeekPct < -1.0) {
                $trend = 'down';
                $statusByLocale = [
                    'az' => 'ucuzlaşıb',
                    'en' => 'cheaper',
                    'ru' => 'подешевел',
                ];
            } elseif ($avgChangeWeekPct !== null && $avgChangeWeekPct > 1.0) {
                $trend = 'up';
                $statusByLocale = [
                    'az' => 'bahalaşıb',
                    'en' => 'more expensive',
                    'ru' => 'подорожал',
                ];
            }

            $badgeName = is_array($badge->name) ? $badge->name : $this->decodeJsonColumn($badge->name);
            if (! is_array($badgeName)) {
                $badgeName = [];
            }
            $resolvedBaseName = [
                'az' => trim((string) ($badgeName['az'] ?? '')),
                'en' => trim((string) ($badgeName['en'] ?? ($badgeName['az'] ?? ''))),
                'ru' => trim((string) ($badgeName['ru'] ?? ($badgeName['az'] ?? ''))),
            ];
            $composedName = [];
            foreach (['az', 'en', 'ru'] as $loc) {
                $base = $resolvedBaseName[$loc] !== '' ? $resolvedBaseName[$loc] : ($resolvedBaseName['az'] !== '' ? $resolvedBaseName['az'] : 'Badge');
                $composedName[$loc] = trim($base . ' ' . ($statusByLocale[$loc] ?? ''));
            }

            $out[] = [
                'key' => 'badge_'.(int) $badge->id,
                'type' => BasketDefinition::TYPE_BADGE,
                'trend' => $trend,
                'label' => null,
                'name' => $composedName,
                'slug' => (string) (trim((string) data_get($badge->name, 'az', '')) !== ''
                    ? \Illuminate\Support\Str::slug((string) data_get($badge->name, 'az'))
                    : ('badge-'.$badge->id)),
                'icon_url' => $badge->getFirstMediaUrl('badge_icon') ?: null,
            ];

            if (count($out) >= 32) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{key: string, trend: string, label: ?string, name?: array<string, string>|null, route_slug?: ?string, parent_route_slug?: ?string}>
     */
    private function buildCategoryHighlightsFallback(): array
    {
        $dates = DB::table('price_snapshots')
            ->select('snapshot_date')
            ->groupBy('snapshot_date')
            ->orderByDesc('snapshot_date')
            ->limit(2)
            ->pluck('snapshot_date');

        if ($dates->count() < 2) {
            return [];
        }

        $d0 = (string) $dates[0];
        $d1 = (string) $dates[1];

        $nav = $this->buildNavCategories();
        $out = [];
        foreach ($nav as $root) {
            foreach ($root['children'] ?? [] as $child) {
                $cid = (int) ($child['id'] ?? 0);
                if ($cid < 1) {
                    continue;
                }
                $catIds = $this->collectDescendantCategoryIds($cid);
                if ($catIds === []) {
                    continue;
                }
                $cur = $this->avgNonBinaPriceForCategorySubtreeOnDate($catIds, $d0);
                $pr = $this->avgNonBinaPriceForCategorySubtreeOnDate($catIds, $d1);
                $trend = 'neutral';
                if ($cur !== null && $pr !== null && abs($pr) > 1e-12) {
                    $delta = ($cur - $pr) / abs($pr);
                    if ($delta > 0.001) {
                        $trend = 'up';
                    } elseif ($delta < -0.001) {
                        $trend = 'down';
                    }
                }
                $out[] = [
                    'key' => 'subcat_'.$cid,
                    'trend' => $trend,
                    'label' => null,
                    'name' => $child['name'] ?? null,
                    'route_slug' => $child['route_slug'] ?? null,
                    'parent_route_slug' => $root['route_slug'] ?? null,
                ];
                if (count($out) >= 32) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $catIds
     */
    private function avgNonBinaPriceForCategorySubtreeOnDate(array $catIds, string $dateStr): ?float
    {
        if ($catIds === []) {
            return null;
        }
        $avg = DB::table('price_snapshots as ps')
            ->join('price_positions as pp', 'pp.id', '=', 'ps.position_id')
            ->join('price_categories as pc', 'pc.id', '=', 'pp.category_id')
            ->whereDate('ps.snapshot_date', $dateStr)
            ->whereIn('pc.id', $catIds)
            ->whereRaw('LOWER(ps.parser_type) NOT LIKE ?', ['%bina%'])
            ->selectRaw('AVG(ps.price_avg) as a')
            ->value('a');

        return $avg !== null ? (float) $avg : null;
    }

    public function acknowledgeBasketSnapshots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $ids = $validated['ids'];
        $now = now();

        $updated = DB::table('basket_snapshots')
            ->whereIn('id', $ids)
            ->where('sync_status', 'pending')
            ->update([
                'sync_status' => 'synced',
                'synced_at' => $now,
                'last_sync_error' => null,
                'updated_at' => $now,
            ]);

        return response()->json([
            'updated' => $updated,
            'requested' => count($ids),
        ]);
    }

    private function formatCityBlock(object $cityRow): array
    {
        return [
            'id' => (int) $cityRow->id,
            'code' => $cityRow->code,
            'name' => $this->decodeJsonColumn($cityRow->name),
        ];
    }

    /**
     * @param  array{
     *   price_avg_7_days_ago: ?float,
     *   price_avg_30_days_ago: ?float
     * }|null  $delta
     */
    private function formatCityAverageRow(object $r, ?array $delta = null): array
    {
        $a = (array) $r;
        $avg = $a['price_avg'];

        return [
            'snapshot_row_id' => (int) $a['snapshot_row_id'],
            'position_id' => (int) $a['position_id'],
            'avg_display_price' => $avg !== null ? round((float) $avg, 4) : null,
            'price_min' => $a['price_min'],
            'price_max' => $a['price_max'],
            'price_avg' => $a['price_avg'],
            'currency' => $a['currency'],
            'sample_size' => (int) $a['sample_size'],
            'source_count' => (int) $a['source_count'],
            'parser_type' => $a['parser_type'],
            'price_avg_7_days_ago' => $delta['price_avg_7_days_ago'] ?? null,
            'price_avg_30_days_ago' => $delta['price_avg_30_days_ago'] ?? null,
            'parser_run_id' => $a['parser_run_id'] !== null ? (int) $a['parser_run_id'] : null,
            'snapshot_date' => $a['snapshot_date'],
            'position' => [
                'code' => $a['position_code'],
                'slug' => $a['position_slug'],
                'name' => $this->decodeJsonColumn($a['position_name']),
                'unit' => $this->legacyPositionUnitPayload($a),
                'unit_size' => $this->positionSnapshotUnitSize($a['position_unit_size'] ?? null),
            ],
            'category' => [
                'code' => $a['category_code'],
                'slug' => $a['category_slug'],
                'name' => $this->decodeJsonColumn($a['category_name']),
                'show_in_page' => array_key_exists('category_show_in_page', $a) ? (bool) $a['category_show_in_page'] : null,
            ],
            'city' => [
                'code' => $a['city_code'],
                'name' => $this->decodeJsonColumn($a['city_name']),
            ],
        ];
    }

    private function formatSourcePriceResultRow(object $r): array
    {
        $a = (array) $r;

        $source = null;
        if ($a['src_table_id'] !== null) {
            $source = [
                'id' => (int) $a['src_table_id'],
                'source_type' => $a['source_type'],
                'source_name' => $a['source_name'] ?? null,
                'source_url' => $a['source_url'] ?? null,
                'external_source_id' => $a['external_source_id'] ?? null,
                'source_config' => array_key_exists('source_config', $a)
                    ? $this->decodeJsonColumn($a['source_config'])
                    : null,
                'links_json' => array_key_exists('links_json', $a)
                    ? $this->decodeJsonColumn($a['links_json'])
                    : null,
                'options_json' => array_key_exists('options_json', $a)
                    ? $this->decodeJsonColumn($a['options_json'])
                    : null,
            ];
        }

        return [
            'id' => (int) $a['id'],
            'parser_run_id' => (int) $a['parser_run_id'],
            'position_id' => (int) $a['position_id'],
            'source_id' => $a['source_id'] !== null ? (int) $a['source_id'] : null,
            'result_date' => $a['result_date'],
            'external_item_id' => $a['external_item_id'],
            'title' => $a['title'],
            'raw_price' => $a['raw_price'],
            'raw_area' => $a['raw_area'],
            'normalized_price' => $a['normalized_price'],
            'currency' => $a['currency'],
            'is_outlier' => (bool) $a['is_outlier'],
            'is_valid' => (bool) $a['is_valid'],
            'raw_payload' => $this->decodeJsonColumn($a['raw_payload']),
            'created_at' => $a['created_at'],
            'position' => [
                'code' => $a['position_code'],
                'slug' => $a['position_slug'],
                'name' => $this->decodeJsonColumn($a['position_name']),
                'unit' => $this->legacyPositionUnitPayload($a),
                'unit_size' => $this->positionSnapshotUnitSize($a['position_unit_size'] ?? null),
            ],
            'category' => [
                'code' => $a['category_code'],
                'slug' => $a['category_slug'],
                'name' => $this->decodeJsonColumn($a['category_name']),
                'show_in_page' => array_key_exists('category_show_in_page', $a) ? (bool) $a['category_show_in_page'] : null,
            ],
            'city' => [
                'code' => $a['city_code'],
                'name' => $this->decodeJsonColumn($a['city_name']),
            ],
            'source' => $source,
        ];
    }

    private function formatPriceSnapshotRow(object $r): array
    {
        $a = (array) $r;

        return [
            'id' => (int) $a['id'],
            'position_id' => (int) $a['position_id'],
            'snapshot_date' => $a['snapshot_date'],
            'currency' => $a['currency'],
            'price_min' => $a['price_min'],
            'price_max' => $a['price_max'],
            'price_avg' => $a['price_avg'],
            'sample_size' => (int) $a['sample_size'],
            'source_count' => (int) $a['source_count'],
            'parser_type' => $a['parser_type'],
            'parser_run_id' => $a['parser_run_id'] !== null ? (int) $a['parser_run_id'] : null,
            'sync_status' => $a['sync_status'],
            'synced_at' => $a['synced_at'],
            'last_sync_error' => $a['last_sync_error'],
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'],
            'position' => [
                'code' => $a['position_code'],
                'slug' => $a['position_slug'],
                'name' => $this->decodeJsonColumn($a['position_name']),
                'unit' => $this->legacyPositionUnitPayload($a),
                'unit_size' => $this->positionSnapshotUnitSize($a['position_unit_size'] ?? null),
            ],
            'category' => [
                'code' => $a['category_code'],
                'slug' => $a['category_slug'],
                'name' => $this->decodeJsonColumn($a['category_name']),
                'show_in_page' => array_key_exists('category_show_in_page', $a) ? (bool) $a['category_show_in_page'] : null,
            ],
            'city' => [
                'code' => $a['city_code'],
                'name' => $this->decodeJsonColumn($a['city_name']),
            ],
        ];
    }

    private function formatBasketSnapshotRow(object $r): array
    {
        $a = (array) $r;

        $row = [
            'id' => (int) $a['id'],
            'basket_id' => (int) $a['basket_id'],
            'snapshot_date' => $a['snapshot_date'],
            'total_price' => $a['total_price'],
            'currency' => $a['currency'],
            'sync_status' => $a['sync_status'],
            'synced_at' => $a['synced_at'],
            'last_sync_error' => $a['last_sync_error'],
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'],
            'basket' => [
                'code' => null,
                'name' => $this->decodeJsonColumn($a['basket_name']),
            ],
            'city' => [
                'code' => $a['city_code'],
                'name' => $this->decodeJsonColumn($a['city_name']),
            ],
        ];

        if (array_key_exists('dolma_index_total', $a)) {
            $row['dolma_index_total'] = $a['dolma_index_total'];
        }

        return $row;
    }

    private static function positionUnitLabelSelect(): Expression
    {
        return DB::raw('COALESCE(NULLIF(TRIM(u.short_name), ""), NULLIF(TRIM(u.name), ""), NULLIF(TRIM(u.code), ""), "") as unit_label');
    }

    /**
     * Köhnə API formasında `unit` (eyni label/variant üç dil üçün).
     *
     * @param  array<string, mixed>  $row
     * @return array{az: array{label: string, variant: string}, en: array{label: string, variant: string}, ru: array{label: string, variant: string}}
     */
    private function legacyPositionUnitPayload(array $row): array
    {
        $label = trim((string) ($row['unit_label'] ?? ''));

        return LocalizedJson::unitTriple($label, '');
    }

    private function positionSnapshotUnitSize(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    private function decodeJsonColumn(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
