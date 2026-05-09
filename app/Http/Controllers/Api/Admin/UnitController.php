<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiReferenceCache;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 100), 1), 200);
        $all = AdminApiReferenceCache::unitsCollection();
        $page = max(1, (int) $request->query('page', 1));

        $slice = $all->forPage($page, $perPage)->values();
        $mapped = $slice->map(static fn (Unit $u): array => $u->toArray());

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

    public function show(Unit $unit): JsonResponse
    {
        return response()->json($unit->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $unit = Unit::query()->create($data);

        return response()->json($unit->toArray(), 201);
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        $data = $this->validated($request, $unit->id);
        $unit->update($data);

        return response()->json($unit->fresh()?->toArray() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:40', Rule::unique('units', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['required', 'string', 'max:40'],
            'unit_type' => ['required', 'string', Rule::in(['weight', 'volume', 'count'])],
            'base_unit' => ['nullable', 'string', 'max:40'],
            'multiplier' => ['nullable', 'numeric'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = $request->validate($rules);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        if (array_key_exists('multiplier', $validated)) {
            $validated['multiplier'] = $validated['multiplier'] === null || $validated['multiplier'] === ''
                ? null
                : $validated['multiplier'];
        }

        return $validated;
    }
}
