<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\PriceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PriceCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);

        $all = AdminApiReferenceCache::priceCategoriesCollection();
        $page = max(1, (int) $request->query('page', 1));

        $slice = $all->forPage($page, $perPage)->values();
        $mapped = $slice->map(static function (PriceCategory $category) use ($locale): array {
            return array_merge($category->toArray(), AdminApiPresenter::priceCategoryExtras($category, $locale));
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

    public function show(Request $request, PriceCategory $priceCategory): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($priceCategory->toArray(), AdminApiPresenter::priceCategoryExtras($priceCategory, $locale)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $category = PriceCategory::query()->create($data);
        AdminApiReferenceCache::forgetPriceCategories();

        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($category->toArray(), AdminApiPresenter::priceCategoryExtras($category, $locale)), 201);
    }

    public function update(Request $request, PriceCategory $priceCategory): JsonResponse
    {
        $data = $this->validated($request, $priceCategory->id);

        $priceCategory->update($data);
        AdminApiReferenceCache::forgetPriceCategories();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $priceCategory->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'Category not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::priceCategoryExtras($fresh, $locale)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'slug' => ['required', 'string', 'max:160', Rule::unique('price_categories', 'slug')->ignore($ignoreId)],
            'name' => ['required', 'array'],
            'name.az' => ['required', 'string', 'max:500'],
            'name.en' => ['nullable', 'string', 'max:500'],
            'name.ru' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'show_in_page' => ['boolean'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['show_in_page'] = (bool) ($validated['show_in_page'] ?? true);

        return $validated;
    }
}
