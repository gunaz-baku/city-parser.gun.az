<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\RecalculateBasketSnapshotsAction;
use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\BasketDefinition;
use App\Models\BasketItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BasketItemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'basket_id' => ['required', 'integer', 'exists:basket_definitions,id'],
            'position_id' => ['required', 'integer', 'exists:price_positions,id'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'qty_unit' => ['nullable', 'string', 'max:50'],
        ]);
        $basket = BasketDefinition::query()->findOrFail((int) $data['basket_id']);
        $isBadge = (string) ($basket->type ?? BasketDefinition::TYPE_BASKET) === BasketDefinition::TYPE_BADGE;
        if ($isBadge) {
            if (! isset($data['qty']) || ! is_numeric($data['qty'])) {
                $positionMeta = DB::table('price_positions as pp')
                    ->leftJoin('units as u', 'u.id', '=', 'pp.unit_id')
                    ->where('pp.id', (int) $data['position_id'])
                    ->first([
                        'pp.unit_size',
                        DB::raw('COALESCE(NULLIF(TRIM(u.short_name), ""), NULLIF(TRIM(u.name), ""), "") as unit_label'),
                    ]);

                $fallbackQty = isset($positionMeta?->unit_size) && is_numeric($positionMeta->unit_size)
                    ? (float) $positionMeta->unit_size
                    : 1.0;
                $data['qty'] = $fallbackQty;
                if (! isset($data['qty_unit']) || trim((string) ($data['qty_unit'] ?? '')) === '') {
                    $unitLabel = trim((string) ($positionMeta?->unit_label ?? ''));
                    $data['qty_unit'] = $unitLabel !== '' ? $unitLabel : null;
                }
            }
        } elseif (! isset($data['qty']) || ! is_numeric($data['qty'])) {
            return response()->json(['message' => 'qty is required for basket type.'], 422);
        }
        if (array_key_exists('unit_id', $data)) {
            $data['unit_id'] = $data['unit_id'] === null || $data['unit_id'] === '' ? null : (int) $data['unit_id'];
        }

        $item = BasketItem::query()->create($data);
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();
        $this->recalculateBasketSnapshots();

        $locale = AdminApiLocale::fromRequest($request);
        $item->load(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'position.measurementUnit:id,code,name,short_name', 'unit:id,code,name,short_name', 'basket:id,name']);

        return response()->json(array_merge($item->toArray(), AdminApiPresenter::basketItemExtras($item, $locale)), 201);
    }

    public function update(Request $request, BasketItem $basketItem): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'qty_unit' => ['nullable', 'string', 'max:50'],
        ]);
        $basketType = (string) ($basketItem->basket?->type ?? BasketDefinition::TYPE_BASKET);
        if ($basketType !== BasketDefinition::TYPE_BADGE && (! isset($data['qty']) || ! is_numeric($data['qty']))) {
            return response()->json(['message' => 'qty is required for basket type.'], 422);
        }
        if (array_key_exists('unit_id', $data)) {
            $data['unit_id'] = $data['unit_id'] === null || $data['unit_id'] === '' ? null : (int) $data['unit_id'];
        }

        $basketItem->update($data);
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();
        $this->recalculateBasketSnapshots();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $basketItem->fresh()?->load(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'position.measurementUnit:id,code,name,short_name', 'unit:id,code,name,short_name', 'basket:id,name']);
        if ($fresh === null) {
            return response()->json(['message' => 'Basket item not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::basketItemExtras($fresh, $locale)));
    }

    public function destroy(BasketItem $basketItem): JsonResponse
    {
        $basketItem->delete();
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();
        $this->recalculateBasketSnapshots();

        return response()->json(['ok' => true]);
    }

    private function recalculateBasketSnapshots(): void
    {
        try {
            app(RecalculateBasketSnapshotsAction::class)->run();
        } catch (Throwable $e) {
            // Do not fail CRUD calls if recalculation fails transiently.
            Log::warning('Basket snapshots recalculation failed after basket item change', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
