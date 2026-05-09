<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\PriceSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceSnapshotAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);
        $query = PriceSnapshot::query()
            ->with(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'parserRun:id,parser_type,status'])
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id');

        if ($request->filled('parser_run_id')) {
            $query->where('parser_run_id', (int) $request->query('parser_run_id'));
        }
        if ($request->filled('position_id')) {
            $query->where('position_id', (int) $request->query('position_id'));
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(static function (PriceSnapshot $snapshot) use ($locale): array {
            return array_merge($snapshot->toArray(), AdminApiPresenter::priceSnapshotExtras($snapshot, $locale));
        });

        return response()->json($paginator);
    }

    public function show(Request $request, PriceSnapshot $priceSnapshot): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $priceSnapshot->load(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'parserRun:id,parser_type,status']);

        return response()->json(array_merge($priceSnapshot->toArray(), AdminApiPresenter::priceSnapshotExtras($priceSnapshot, $locale)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:price_positions,id'],
            'snapshot_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'max:10'],
            'price_min' => ['required', 'numeric'],
            'price_max' => ['required', 'numeric'],
            'price_avg' => ['required', 'numeric'],
            'sample_size' => ['nullable', 'integer', 'min:0'],
            'source_count' => ['nullable', 'integer', 'min:0'],
            'parser_type' => ['required', 'string', 'max:50'],
            'parser_run_id' => ['nullable', 'integer', 'exists:parser_runs,id'],
            'sync_status' => ['required', 'string', 'max:20'],
            'last_sync_error' => ['nullable', 'string'],
        ]);

        $snapshot = PriceSnapshot::query()->create([
            'position_id' => (int) $data['position_id'],
            'snapshot_date' => $data['snapshot_date'],
            'currency' => strtoupper(trim((string) $data['currency'])),
            'price_min' => $data['price_min'],
            'price_max' => $data['price_max'],
            'price_avg' => $data['price_avg'],
            'sample_size' => (int) ($data['sample_size'] ?? 0),
            'source_count' => (int) ($data['source_count'] ?? 0),
            'parser_type' => trim((string) $data['parser_type']),
            'parser_run_id' => isset($data['parser_run_id']) && $data['parser_run_id'] !== ''
                ? (int) $data['parser_run_id']
                : null,
            'sync_status' => trim((string) $data['sync_status']),
            'last_sync_error' => isset($data['last_sync_error']) && trim((string) $data['last_sync_error']) !== ''
                ? trim((string) $data['last_sync_error'])
                : null,
        ]);

        $locale = AdminApiLocale::fromRequest($request);
        $snapshot->load(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'parserRun:id,parser_type,status']);

        return response()->json(array_merge($snapshot->toArray(), AdminApiPresenter::priceSnapshotExtras($snapshot, $locale)), 201);
    }

    public function update(Request $request, PriceSnapshot $priceSnapshot): JsonResponse
    {
        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'max:10'],
            'price_min' => ['nullable', 'numeric'],
            'price_max' => ['nullable', 'numeric'],
            'price_avg' => ['nullable', 'numeric'],
            'sample_size' => ['nullable', 'integer', 'min:0'],
            'source_count' => ['nullable', 'integer', 'min:0'],
            'sync_status' => ['required', 'string', 'max:20'],
            'last_sync_error' => ['nullable', 'string'],
        ]);

        // DB: price_min, price_max, price_avg NOT NULL — null göndəriləndə mövcud dəyəri saxla (Integrity constraint).
        $payload = [
            'snapshot_date' => $data['snapshot_date'],
            'currency' => strtoupper(trim((string) $data['currency'])),
            'sample_size' => (int) ($data['sample_size'] ?? 0),
            'source_count' => (int) ($data['source_count'] ?? 0),
            'sync_status' => trim((string) $data['sync_status']),
            'last_sync_error' => isset($data['last_sync_error']) && trim((string) $data['last_sync_error']) !== ''
                ? trim((string) $data['last_sync_error'])
                : null,
        ];
        foreach (['price_min', 'price_max', 'price_avg'] as $col) {
            if (! array_key_exists($col, $data)) {
                continue;
            }
            $v = $data[$col];
            if ($v !== null && $v !== '') {
                $payload[$col] = $v;
            }
        }

        $priceSnapshot->update($payload);

        $locale = AdminApiLocale::fromRequest($request);
        $fresh = $priceSnapshot->fresh()?->load(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'parserRun:id,parser_type,status']);
        if ($fresh === null) {
            return response()->json(['message' => 'Snapshot not found after update.'], 404);
        }

        return response()->json(array_merge($fresh->toArray(), AdminApiPresenter::priceSnapshotExtras($fresh, $locale)));
    }

    public function destroy(PriceSnapshot $priceSnapshot): JsonResponse
    {
        try {
            $priceSnapshot->delete();
        } catch (QueryException) {
            return response()->json([
                'message' => 'Snapshot cannot be deleted because related records exist.',
            ], 422);
        }

        return response()->json(['ok' => true]);
    }
}
