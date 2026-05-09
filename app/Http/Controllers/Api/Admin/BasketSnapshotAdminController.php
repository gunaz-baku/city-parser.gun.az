<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\RecalculateBasketSnapshotsAction;
use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\BasketSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasketSnapshotAdminController extends Controller
{
    /**
     * Aktiv səbətlər üçün bugünkü snapshot cəmlərini yenidən hesablayır və DB-yə yazır.
     *
     * Body (JSON, hamısı ixtiyari): `parser_run_id` — konkret parser run günü; yoxdursa son qiymət məlumatı.
     * `snapshot_date` — YYYY-MM-DD; yoxdursa snapshot timezone ilə bu gün.
     * Marşrut: `POST /api/v1/admin/basket-snapshots-recalculate` (`basket-snapshots/recalculate` yox — `{basket_snapshot}` ilə qarışmır).
     */
    public function recalculate(Request $request, RecalculateBasketSnapshotsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'parser_run_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'snapshot_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $runId = isset($validated['parser_run_id']) ? (int) $validated['parser_run_id'] : null;
        $date = isset($validated['snapshot_date']) ? (string) $validated['snapshot_date'] : null;

        $result = $action->run(parserRunId: $runId, snapshotDate: $date);

        if (($result['ok'] ?? false) !== true) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);
        $query = BasketSnapshot::query()
            ->with(['basket:id,name'])
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id');

        if ($request->filled('basket_id')) {
            $query->where('basket_id', (int) $request->query('basket_id'));
        }
        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(static function (BasketSnapshot $snapshot) use ($locale): array {
            return array_merge($snapshot->toArray(), AdminApiPresenter::basketSnapshotExtras($snapshot, $locale));
        });

        return response()->json($paginator);
    }

    public function show(Request $request, BasketSnapshot $basketSnapshot): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);
        $basketSnapshot->load(['basket:id,name']);

        return response()->json(array_merge($basketSnapshot->toArray(), AdminApiPresenter::basketSnapshotExtras($basketSnapshot, $locale)));
    }
}
