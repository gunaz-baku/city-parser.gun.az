<?php

namespace App\Http\Controllers\Api\Parser;

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserCityDataController extends Controller
{
    public function __construct(
        private readonly GunAzParserSnapshotController $snapshotController,
    ) {
    }

    public function cityPriceAverages(Request $request): JsonResponse
    {
        return $this->snapshotController->cityPriceAverages($request);
    }

    public function cityPricesPage(Request $request): JsonResponse
    {
        return $this->snapshotController->cityPricesPage($request);
    }

    public function cityPriceSection(Request $request): JsonResponse
    {
        return $this->snapshotController->cityPriceSection($request);
    }

    public function cityNavCategories(Request $request): JsonResponse
    {
        return $this->snapshotController->cityNavCategories($request);
    }

    public function positionPriceHistory(Request $request): JsonResponse
    {
        return $this->snapshotController->positionPriceHistory($request);
    }
}
