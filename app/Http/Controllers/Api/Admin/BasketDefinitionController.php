<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\BasketDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class BasketDefinitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);

        $all = AdminApiReferenceCache::basketDefinitionsCollection();
        $page = max(1, (int) $request->query('page', 1));

        $slice = $all->forPage($page, $perPage)->values();
        $mapped = $slice->map(static function (BasketDefinition $basket) use ($locale): array {
            return array_merge($basket->toArray(), AdminApiPresenter::basketDefinitionExtras($basket, $locale));
        });

        $paginator = new LengthAwarePaginator(
            $mapped,
            $all->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
        $paginator->appends($request->query());

        return response()->json($paginator);
    }

    public function show(Request $request, BasketDefinition $basketDefinition): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $basketDefinition->load([
            'basketItems.position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order',
            'basketItems.position.measurementUnit:id,code,name,short_name',
            'basketItems.unit:id,code,name,short_name',
            'basketItems.basket:id,name',
        ]);

        $payload = $basketDefinition->toArray();
        $payload['basket_items'] = $basketDefinition->basketItems
            ->map(static function ($item) use ($locale): array {
                return array_merge($item->toArray(), AdminApiPresenter::basketItemExtras($item, $locale));
            })
            ->values()
            ->all();

        $payload = array_merge($payload, AdminApiPresenter::basketDefinitionExtras($basketDefinition, $locale));

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $basket = BasketDefinition::query()->create($data);
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($basket->toArray(), AdminApiPresenter::basketDefinitionExtras($basket, $locale)), 201);
    }

    public function update(Request $request, BasketDefinition $basketDefinition): JsonResponse
    {
        $data = $this->validated($request, $basketDefinition->id);

        $basketDefinition->update($data);
        if ($basketDefinition->type !== BasketDefinition::TYPE_BADGE) {
            $basketDefinition->clearMediaCollection('badge_icon');
        }
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $basketDefinition->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'Basket not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::basketDefinitionExtras($fresh, $locale)));
    }

    public function uploadIcon(Request $request, BasketDefinition $basketDefinition): JsonResponse
    {
        if ($basketDefinition->type !== BasketDefinition::TYPE_BADGE) {
            return response()->json(['message' => 'Icon upload is allowed only for badge type.'], 422);
        }

        $request->validate([
            'icon' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
        ]);

        $basketDefinition->clearMediaCollection('badge_icon');
        $basketDefinition->addMediaFromRequest('icon')->toMediaCollection('badge_icon');
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $basketDefinition->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'Basket not found after icon upload.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::basketDefinitionExtras($fresh, $locale)));
    }

    public function deleteIcon(Request $request, BasketDefinition $basketDefinition): JsonResponse
    {
        $basketDefinition->clearMediaCollection('badge_icon');
        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $basketDefinition->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'Basket not found after icon delete.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::basketDefinitionExtras($fresh, $locale)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            // 'code' => ['required', 'string', 'max:80', Rule::unique('basket_definitions', 'code')->ignore($ignoreId)],
            'name' => ['required', 'array'],
            'name.az' => ['required', 'string', 'max:500'],
            'name.en' => ['nullable', 'string', 'max:500'],
            'name.ru' => ['nullable', 'string', 'max:500'],
            'type' => ['sometimes', 'string', Rule::in(BasketDefinition::supportedTypes())],
            'is_active' => ['boolean'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);
        $validated['type'] = (string) ($validated['type'] ?? BasketDefinition::TYPE_BASKET);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }
}
