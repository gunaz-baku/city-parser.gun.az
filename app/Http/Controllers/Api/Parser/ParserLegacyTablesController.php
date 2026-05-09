<?php

namespace App\Http\Controllers\Api\Parser;

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserLegacyTablesController extends Controller
{
    public function __construct(
        private readonly GunAzParserSnapshotController $snapshotController,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->snapshotController->legacyTablesExport($request);
    }
}
