<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Support\AdminApiLocale;
use App\Http\Support\AdminApiPresenter;
use App\Models\ParserRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserRunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);
        $locale = AdminApiLocale::fromRequest($request);

        $paginator = ParserRun::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(static function (ParserRun $run) use ($locale): array {
            return array_merge($run->toArray(), AdminApiPresenter::parserRunExtras($run, $locale));
        });

        return response()->json($paginator);
    }

    public function show(Request $request, ParserRun $parserRun): JsonResponse
    {
        $locale = AdminApiLocale::fromRequest($request);

        return response()->json(array_merge($parserRun->toArray(), AdminApiPresenter::parserRunExtras($parserRun, $locale)));
    }
}
