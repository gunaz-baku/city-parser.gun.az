<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\City;
use App\Models\CityPriceSectionItem;
use App\Models\CityPriceSectionTab;
use App\Models\PricePosition;
use App\Support\SyntheticCity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityPriceSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $cityId = $this->resolveCityIdFromRequest($request);

        $tabs = CityPriceSectionTab::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = CityPriceSectionItem::query()
            ->with([
                'tab:id,route_slug,name,sort_order,is_active',
                'position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order',
                'position.category:id,slug,name',
                'position.measurementUnit:id,code,name,short_name',
            ])
            ->orderBy('tab_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $itemsByTabId = [];
        foreach ($items as $item) {
            $pos = $item->position;
            if (! ($pos instanceof PricePosition)) {
                continue;
            }
            $tabId = (int) $item->tab_id;
            if ($tabId < 1) {
                continue;
            }

            $itemsByTabId[$tabId][] = [
                'id' => (int) $item->id,
                'tab_id' => $tabId,
                'sort_order' => (int) $item->sort_order,
                'position_id' => (int) $pos->id,
                'position' => array_merge($pos->toArray(), AdminApiPresenter::pricePositionExtras($pos, $locale)),
            ];
        }

        return response()->json([
            'city_id' => $cityId,
            'tabs' => $tabs->map(function (CityPriceSectionTab $t) use ($locale, $itemsByTabId): array {
                $name = is_array($t->name) ? $t->name : [];
                $label = (string) ($name[$locale] ?? $name['az'] ?? $t->route_slug ?? '');

                return [
                    'id' => (int) $t->id,
                    'route_slug' => $t->route_slug,
                    'name' => $name,
                    'name_label' => $label,
                    'sort_order' => (int) $t->sort_order,
                    'is_active' => (bool) $t->is_active,
                    'items' => $itemsByTabId[(int) $t->id] ?? [],
                ];
            })->values()->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tabs' => ['required', 'array', 'min:1'],
            'tabs.*' => ['array'],
            'tabs.*.id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tabs.*.route_slug' => ['required', 'string', 'max:160'],
            'tabs.*.name' => ['required', 'array'],
            'tabs.*.name.az' => ['required', 'string', 'max:120'],
            'tabs.*.name.en' => ['nullable', 'string', 'max:120'],
            'tabs.*.name.ru' => ['nullable', 'string', 'max:120'],
            'tabs.*.is_active' => ['sometimes', 'boolean'],
            'tabs.*.positions' => ['sometimes', 'array'],
            'tabs.*.positions.*' => ['integer', 'min:1'],
        ]);

        /** @var list<array<string, mixed>> $tabsPayload */
        $tabsPayload = $validated['tabs'];
        $cityId = $this->resolveCityIdFromRequest($request);

        foreach ($tabsPayload as $idx => $t) {
            $slug = strtolower(trim((string) ($t['route_slug'] ?? '')));
            $tabsPayload[$idx]['route_slug'] = $slug;
        
            if ($slug === '') {
                return response()->json([
                    'message' => 'Tab route_slug boş ola bilməz.'
                ], 422);
            }
        }

        $allPosIds = [];
        foreach ($tabsPayload as $t) {
            $pos = $t['positions'] ?? [];
            if (is_array($pos)) {
                foreach ($pos as $pid) {
                    $pid = (int) $pid;
                    if ($pid >= 1) {
                        $allPosIds[] = $pid;
                    }
                }
            }
        }
        $allPosIds = array_values(array_unique($allPosIds));
        $existsMap = [];
        if ($allPosIds !== []) {
            $exists = PricePosition::query()
                ->whereIn('id', $allPosIds)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $existsMap = array_fill_keys($exists, true);
        }

        DB::transaction(function () use ($tabsPayload, $existsMap): void {
            CityPriceSectionItem::query()->delete();
            CityPriceSectionTab::query()->delete();

            foreach ($tabsPayload as $i => $t) {
                $name = is_array($t['name'] ?? null) ? $t['name'] : [];
                $tab = CityPriceSectionTab::query()->create([
                    'route_slug' => strtolower(trim((string) ($t['route_slug'] ?? ''))),
                    'name' => [
                        'az' => (string) ($name['az'] ?? ''),
                        'en' => (string) ($name['en'] ?? ''),
                        'ru' => (string) ($name['ru'] ?? ''),
                    ],
                    'sort_order' => $i,
                    'is_active' => (bool) ($t['is_active'] ?? true),
                ]);

                $positions = $t['positions'] ?? [];
                $ids = [];
                if (is_array($positions)) {
                    foreach ($positions as $pid) {
                        $pid = (int) $pid;
                        if ($pid >= 1 && isset($existsMap[$pid])) {
                            $ids[] = $pid;
                        }
                    }
                }
                $ids = array_values(array_unique($ids));
                foreach ($ids as $idx => $posId) {
                    CityPriceSectionItem::query()->create([
                        'tab_id' => (int) $tab->id,
                        'position_id' => (int) $posId,
                        'sort_order' => $idx,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true, 'city_id' => $cityId]);
    }

    private function resolveCityIdFromRequest(Request $request): int
    {
        $rawId = (int) $request->input('city_id', $request->query('city_id', 0));
        if ($rawId >= 1) {
            $city = City::query()->whereKey($rawId)->where('is_active', true)->first();
            if ($city instanceof City) {
                return (int) $city->id;
            }
        }

        $fallback = City::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($fallback instanceof City) {
            return (int) $fallback->id;
        }

        return SyntheticCity::ID;
    }
}
