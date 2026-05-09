<?php

use App\Http\Controllers\Api\GunAzParserSnapshotController;
use App\Http\Controllers\Api\ParserControlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API (prefiks: /api)
|--------------------------------------------------------------------------
| Token: Authorization: Bearer … və ya ?token= (GUN_AZ_API_TOKEN)
|
| Gun.Az qiymət səhifəsi (resources/views/web/city/prices.blade.php):
| - Brauzer GunAz-dakı proxy-yə sorğu göndərir: GET …/prices/{citySlug}/parser-json
| - GunAz server city-parser-ə keçirir: GET /api/v1/parser/city-price-averages
|   ?city_code=baku (və s.)
| Cavab: city, snapshot_date, meta.updated_display, rows[] (mövqə ortaları).
| GET …/city-prices-page — birləşmiş: averages + nav_categories + dolma_index + highlights.
| Köhnə kök URL (web.php): /city-price-averages — eyni handler.
*/

Route::get('/v1', function () {
    return response()->json([
        'service' => config('app.name'),
        'version' => 1,
        'base' => url('/api/v1/parser'),
        'auth' => [
            'gun_az' => [
                'header' => 'Authorization: Bearer {GUN_AZ_API_TOKEN}',
                'query' => '?token=…',
            ],
            'parser_control' => [
                'type' => 'HTTP Basic (users cədvəli, email + parol)',
                'example' => 'Authorization: Basic base64(email:password)',
                'setup' => 'php artisan parser:ensure-basic-user',
            ],
        ],
        'control' => [
            'run_parser' => url('/api/v1/control/parser/run/{wolt|bina}'),
        ],
    ]);
})->name('api.v1');

Route::prefix('v1/control')
    ->middleware(['throttle:parser-api', 'auth.basic.api'])
    ->name('control.')
    ->group(function (): void {
        Route::get('parser/run/{type}', [ParserControlController::class, 'runParser'])
            ->where('type', 'wolt|bina')
            ->name('parser.run');
    });

Route::prefix('v1/parser')
    ->middleware(['throttle:parser-api', 'gunaz.api'])
    ->name('parser.')
    ->group(function (): void {
        Route::get('price-snapshots', [GunAzParserSnapshotController::class, 'pullPriceSnapshots'])
            ->name('price-snapshots');
        Route::post('price-snapshots/acknowledge', [GunAzParserSnapshotController::class, 'acknowledgePriceSnapshots'])
            ->name('price-snapshots.acknowledge');
        Route::get('basket-snapshots', [GunAzParserSnapshotController::class, 'pullBasketSnapshots'])
            ->name('basket-snapshots');
        Route::post('basket-snapshots/acknowledge', [GunAzParserSnapshotController::class, 'acknowledgeBasketSnapshots'])
            ->name('basket-snapshots.acknowledge');
        Route::get('source-price-results', [GunAzParserSnapshotController::class, 'pullSourcePriceResults'])
            ->name('source-price-results');
        Route::get('city-price-averages', [GunAzParserSnapshotController::class, 'cityPriceAverages'])
            ->name('city-price-averages');
        Route::get('city-prices-page', [GunAzParserSnapshotController::class, 'cityPricesPage'])
            ->name('city-prices-page');
    });
