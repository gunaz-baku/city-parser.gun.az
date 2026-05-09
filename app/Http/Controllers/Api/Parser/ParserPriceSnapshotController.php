<?php

namespace App\Http\Controllers\Api\Parser;

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserPriceSnapshotController extends Controller
{
    public function __construct(
        private readonly GunAzParserSnapshotController $snapshotController,
    ) {
    }

    public function pull(Request $request): JsonResponse
    {
        return $this->snapshotController->pullPriceSnapshots($request);
    }

    public function acknowledge(Request $request): JsonResponse
    {
        return $this->snapshotController->acknowledgePriceSnapshots($request);
    }
}
