<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\PositionFailure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionFailureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);
        $query = PositionFailure::query()
            ->with(['position:id,code,slug,name', 'parserRun:id,parser_type,status'])
            ->orderByDesc('id');

        if ($request->filled('parser_run_id')) {
            $query->where('parser_run_id', (int) $request->query('parser_run_id'));
        }

        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(static function (PositionFailure $row) use ($locale): array {
            return array_merge($row->toArray(), AdminApiPresenter::positionFailureExtras($row, $locale));
        });

        return response()->json($paginator);
    }
}
