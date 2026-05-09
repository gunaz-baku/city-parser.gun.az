<?php

namespace App\Http\Controllers\Api\Parser;

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserBasketSnapshotController extends Controller
{
    public function __construct(
        private readonly GunAzParserSnapshotController $snapshotController,
    ) {
    }

    public function pull(Request $request): JsonResponse
    {
        return $this->snapshotController->pullBasketSnapshots($request);
    }

    public function acknowledge(Request $request): JsonResponse
    {
        return $this->snapshotController->acknowledgeBasketSnapshots($request);
    }
}
