<?php

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Köhnə qısa URL-lər (brauzer); əsas API: /api/v1/parser/…
*/
Route::middleware(['throttle:parser-api', 'force.json', 'gunaz.api'])->group(function (): void {
    Route::get('/price-snapshots', [GunAzParserSnapshotController::class, 'pullPriceSnapshots'])
        ->name('web.parser.price-snapshots');
    Route::get('/basket-snapshots', [GunAzParserSnapshotController::class, 'pullBasketSnapshots'])
        ->name('web.parser.basket-snapshots');
    Route::get('/source-price-results', [GunAzParserSnapshotController::class, 'pullSourcePriceResults'])
        ->name('web.parser.source-price-results');
    Route::get('/city-price-averages', [GunAzParserSnapshotController::class, 'cityPriceAverages'])
        ->name('web.parser.city-price-averages');
    Route::get('/city-prices-page', [GunAzParserSnapshotController::class, 'cityPricesPage'])
        ->name('web.parser.city-prices-page');
});
