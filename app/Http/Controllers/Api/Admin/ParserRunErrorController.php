<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Http\Support\ParserRunErrorGroupedRowPresenter;
use App\Models\ParserRunError;
use App\Support\Admin\ParserRunErrorsGroupedTableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParserRunErrorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);

        if ($request->boolean('grouped')) {
            $query = ParserRunErrorsGroupedTableQuery::newGroupedBuilder();

            if ($request->filled('parser_run_id')) {
                $query->where('parser_run_errors.parser_run_id', (int) $request->query('parser_run_id'));
            }

            ParserRunErrorsGroupedTableQuery::applyOccurredDateFilter(
                $query,
                $request->query('occurred_from'),
                $request->query('occurred_until'),
            );

            $query->orderByDesc('occurred_at');

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->transform(static function (ParserRunError $row) use ($locale): array {
                return ParserRunErrorGroupedRowPresenter::toApiArray($row, $locale);
            });

            return response()->json($paginator);
        }

        $query = ParserRunError::query()
            ->with(['position:id,category_id,slug,name,unit_id,unit_size,parser_type,is_active,sort_order', 'parserRun:id,parser_type,status'])
            ->orderByDesc('id');

        if ($request->filled('parser_run_id')) {
            $query->where('parser_run_id', (int) $request->query('parser_run_id'));
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(static function (ParserRunError $row) use ($locale): array {
            return array_merge($row->toArray(), AdminApiPresenter::parserRunErrorExtras($row, $locale));
        });

        return response()->json($paginator);
    }

    /**
     * Deletes all parser_run_errors rows for one aggregated group (same rules as Filament “Delete” on grouped rows).
     */
    public function destroyGrouped(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parser_run_id' => 'required|integer|min:1',
            'position_id' => 'nullable|integer',
            'source_id' => 'nullable|integer',
        ]);

        $runId = (int) $validated['parser_run_id'];
        $posId = $validated['position_id'] ?? null;
        $srcId = $validated['source_id'] ?? null;

        $q = DB::table('parser_run_errors')->where('parser_run_id', $runId);
        if ($posId !== null && $posId > 0) {
            $q->where('position_id', $posId);
        } else {
            $q->whereNull('position_id');
        }
        if ($srcId !== null && $srcId > 0) {
            $q->where('source_id', $srcId);
        } else {
            $q->whereNull('source_id');
        }

        $deleted = $q->delete();

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * occurred_at bu gündən ən azı N gün əvvəl olan bütün parser_run_errors sətirlərini silir (köhnə loglar).
     */
    public function purgeOld(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'older_than_days' => 'required|integer|min:1|max:3650',
        ]);

        $days = (int) $validated['older_than_days'];
        $cutoff = now()->subDays($days)->startOfDay();

        $deleted = DB::table('parser_run_errors')
            ->whereNotNull('occurred_at')
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        return response()->json([
            'deleted' => $deleted,
            'older_than_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
        ]);
    }
}
