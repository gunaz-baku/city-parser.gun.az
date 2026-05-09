<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\PriceCategory;
use App\Models\PricePosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class PricePositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);

        $query = PricePosition::query()
            ->with(['category:id,slug,name', 'measurementUnit:id,code,name,short_name']);

        $categoryId = null;
        if ($request->filled('category_id')) {
            $categoryId = (int) $request->query('category_id');
        } elseif ($request->filled('parent_category_id')) {
            // Backward-compat: hierarchy removed; treat as plain category filter.
            $categoryId = (int) $request->query('parent_category_id');
        }
        if ($categoryId !== null && $categoryId >= 1 && PriceCategory::query()->whereKey($categoryId)->exists()) {
            $query->where('category_id', $categoryId);
        }

        $paginator = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(static function (PricePosition $position) use ($locale): array {
            return array_merge($position->toArray(), AdminApiPresenter::pricePositionExtras($position, $locale));
        });

        return response()->json($paginator);
    }

    public function show(Request $request, PricePosition $pricePosition): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $pricePosition->load(['category:id,slug,name', 'measurementUnit:id,code,name,short_name']);

        return response()->json(array_merge($pricePosition->toArray(), AdminApiPresenter::pricePositionExtras($pricePosition, $locale)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $position = PricePosition::query()->create($data);
        $locale = AdminApiLocale::fromRequest($request);
        $position->load(['category:id,slug,name', 'measurementUnit:id,code,name,short_name']);

        return response()->json(array_merge($position->toArray(), AdminApiPresenter::pricePositionExtras($position, $locale)), 201);
    }

    public function update(Request $request, PricePosition $pricePosition): JsonResponse
    {
        $data = $this->validated($request, $pricePosition);

        $pricePosition->update($data);
        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $pricePosition->fresh()?->load(['category:id,slug,name', 'measurementUnit:id,code,name,short_name']);
        if ($fresh === null) {
            return response()->json(['message' => 'Position not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::pricePositionExtras($fresh, $locale)));
    }

    public function destroy(PricePosition $pricePosition): JsonResponse
    {
        try {
            $pricePosition->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Position cannot be deleted because related records exist.',
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PricePosition $existing = null): array
    {
        $ignoreId = $existing?->id;

        $rules = [
            'category_id' => ['required', 'integer', 'exists:price_categories,id'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('price_positions', 'slug')->ignore($ignoreId),
            ],
            'name' => ['required', 'array'],
            'name.az' => ['required', 'string', 'max:500'],
            'name.en' => ['nullable', 'string', 'max:500'],
            'name.ru' => ['nullable', 'string', 'max:500'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit_size' => ['nullable', 'numeric'],
            'parser_type' => ['required', 'string', Rule::in(['manual', 'wolt', 'bina', 'gun_az'])],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        if (array_key_exists('unit_id', $validated)) {
            $validated['unit_id'] = $validated['unit_id'] === null || $validated['unit_id'] === ''
                ? null
                : (int) $validated['unit_id'];
        }
        if (array_key_exists('unit_size', $validated)) {
            $validated['unit_size'] = $validated['unit_size'] === null || $validated['unit_size'] === ''
                ? null
                : (float) $validated['unit_size'];
        }

        /** @var list<string> $fillable */
        $fillable = (new PricePosition)->getFillable();

        return array_intersect_key($validated, array_flip($fillable));
    }
}
