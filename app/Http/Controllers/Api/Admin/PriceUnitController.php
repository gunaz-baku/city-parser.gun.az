<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\PriceUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PriceUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 100), 1), 200);
        $all = AdminApiReferenceCache::priceUnitsCollection();
        $page = max(1, (int) $request->query('page', 1));

        $slice = $all->forPage($page, $perPage)->values();
        $mapped = $slice->map(static fn (PriceUnit $u): array => $u->toArray());

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

    public function show(PriceUnit $priceUnit): JsonResponse
    {
        return response()->json($priceUnit->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $unit = PriceUnit::query()->create($data);

        return response()->json($unit->toArray(), 201);
    }

    public function update(Request $request, PriceUnit $priceUnit): JsonResponse
    {
        $data = $this->validated($request, $priceUnit->id);
        $priceUnit->update($data);

        return response()->json($priceUnit->fresh()?->toArray() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:80', Rule::unique('price_units', 'code')->ignore($ignoreId)],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }
}
