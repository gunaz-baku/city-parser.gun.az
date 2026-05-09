<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $perPage = min(max((int) $request->query('per_page', 100), 1), 200);
        $all = AdminApiReferenceCache::citiesCollection();
        $page = max(1, (int) $request->query('page', 1));

        $slice = $all->forPage($page, $perPage)->values();
        $mapped = $slice->map(
            static fn (City $city): array => array_merge($city->toArray(), AdminApiPresenter::cityExtras($city, $locale))
        );

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

    public function show(Request $request, City $city): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($city->toArray(), AdminApiPresenter::cityExtras($city, $locale)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $city = City::query()->create($data);
        AdminApiReferenceCache::forgetCities();

        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($city->toArray(), AdminApiPresenter::cityExtras($city, $locale)), 201);
    }

    public function update(Request $request, City $city): JsonResponse
    {
        $data = $this->validated($request);
        $city->update($data);
        AdminApiReferenceCache::forgetCities();

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $city->fresh();
        if ($fresh === null) {
            return response()->json(['message' => 'City not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::cityExtras($fresh, $locale)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $rules = [
            'name' => ['required', 'array'],
            'name.az' => ['required', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.ru' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }
}
