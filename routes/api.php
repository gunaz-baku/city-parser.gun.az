<?php

use App\Http\Controllers\Api\Admin\BasketDefinitionController;
use App\Http\Controllers\Api\Admin\BasketItemController;
use App\Http\Controllers\Api\Admin\BasketSnapshotAdminController;
use App\Http\Controllers\Api\Admin\CityPriceSectionController;
use App\Http\Controllers\Api\Admin\CityController;
use App\Http\Controllers\Api\Admin\ParserRunController as AdminParserRunController;
use App\Http\Controllers\Api\Admin\ParserRunErrorController;
use App\Http\Controllers\Api\Admin\ParserUiDictionaryController;
use App\Http\Controllers\Api\Admin\PriceCategoryController;
use App\Http\Controllers\Api\Admin\PricePositionController;
use App\Http\Controllers\Api\Admin\PricePositionSourcesController;
use App\Http\Controllers\Api\Admin\PriceSnapshotAdminController;
use App\Http\Controllers\Api\Admin\UnitController;
use App\Http\Controllers\Api\Parser\ParserBasketSnapshotController;
use App\Http\Controllers\Api\Parser\ParserCityDataController;
use App\Http\Controllers\Api\Parser\ParserLegacyTablesController;
use App\Http\Controllers\Api\Parser\ParserPriceSnapshotController;
use App\Http\Controllers\Api\Parser\ParserSourcePriceResultController;
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
| GET …/city-nav-categories — yalnız nav (GunAz fallback).
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
        'admin' => [
            'base' => url('/api/v1/admin'),
            'note' => 'GunAz Filament operator CRUD/lists; same Bearer as gun_az.',
            'parser_ui_dictionary' => url('/api/v1/admin/parser-ui-dictionary'),
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
        Route::get('price-snapshots', [ParserPriceSnapshotController::class, 'pull'])
            ->name('price-snapshots');
        Route::post('price-snapshots/acknowledge', [ParserPriceSnapshotController::class, 'acknowledge'])
            ->name('price-snapshots.acknowledge');
        Route::get('basket-snapshots', [ParserBasketSnapshotController::class, 'pull'])
            ->name('basket-snapshots');
        Route::post('basket-snapshots/acknowledge', [ParserBasketSnapshotController::class, 'acknowledge'])
            ->name('basket-snapshots.acknowledge');
        Route::get('source-price-results', [ParserSourcePriceResultController::class, 'index'])
            ->name('source-price-results');
        Route::get('city-price-averages', [ParserCityDataController::class, 'cityPriceAverages'])
            ->name('city-price-averages');
        Route::get('city-prices-page', [ParserCityDataController::class, 'cityPricesPage'])
            ->name('city-prices-page');
        Route::get('city-price-section', [ParserCityDataController::class, 'cityPriceSection'])
            ->name('city-price-section');
        Route::get('city-nav-categories', [ParserCityDataController::class, 'cityNavCategories'])
            ->name('city-nav-categories');
        Route::get('position-price-history', [ParserCityDataController::class, 'positionPriceHistory'])
            ->name('position-price-history');
        Route::get('legacy-tables-export', [ParserLegacyTablesController::class, 'index'])
            ->name('legacy-tables-export');
    });

/*
| GunAz admin operator API — CRUD / lists backed by this app’s database (Bearer GUN_AZ_API_TOKEN).
| GunAz Filament “Save to API” calls these routes; prefix differs from public snapshot routes.
*/
Route::prefix('v1/admin')
    ->middleware(['throttle:parser-api', 'gunaz.api'])
    ->name('admin.')
    ->group(function (): void {
        Route::get('parser-ui-dictionary', ParserUiDictionaryController::class)->name('parser-ui-dictionary');
        Route::get('city-price-section', [CityPriceSectionController::class, 'index'])->name('city-price-section.index');
        Route::put('city-price-section', [CityPriceSectionController::class, 'update'])->name('city-price-section.update');
        Route::apiResource('cities', CityController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('price-categories', PriceCategoryController::class)->only(['index', 'show', 'store', 'update']);
        Route::apiResource('units', UnitController::class)->only(['index', 'show', 'store', 'update']);
        // Legacy path — same controller; `{unit}` keeps implicit `Unit` binding (not `{price_unit}`).
        Route::get('price-units', [UnitController::class, 'index']);
        Route::post('price-units', [UnitController::class, 'store']);
        Route::get('price-units/{unit}', [UnitController::class, 'show'])->whereNumber('unit');
        Route::put('price-units/{unit}', [UnitController::class, 'update'])->whereNumber('unit');
        Route::apiResource('price-positions', PricePositionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::get('price-positions/{pricePosition}/sources', [PricePositionSourcesController::class, 'index'])->name('price-positions.sources.index');
        Route::put('price-positions/{pricePosition}/sources', [PricePositionSourcesController::class, 'update'])->name('price-positions.sources.update');
        Route::apiResource('basket-definitions', BasketDefinitionController::class)->only(['index', 'show', 'store', 'update']);
        Route::post('basket-definitions/{basketDefinition}/icon', [BasketDefinitionController::class, 'uploadIcon'])->name('basket-definitions.icon.upload');
        Route::delete('basket-definitions/{basketDefinition}/icon', [BasketDefinitionController::class, 'deleteIcon'])->name('basket-definitions.icon.delete');
        Route::post('basket-items', [BasketItemController::class, 'store'])->name('basket-items.store');
        Route::put('basket-items/{basket_item}', [BasketItemController::class, 'update'])->name('basket-items.update');
        Route::delete('basket-items/{basket_item}', [BasketItemController::class, 'destroy'])->name('basket-items.destroy');
        Route::apiResource('parser-runs', AdminParserRunController::class)->only(['index', 'show']);
        Route::get('parser-run-errors', [ParserRunErrorController::class, 'index'])->name('parser-run-errors.index');
        Route::post('parser-run-errors/grouped/delete', [ParserRunErrorController::class, 'destroyGrouped'])->name('parser-run-errors.grouped.delete');
        Route::post('parser-run-errors/purge-old', [ParserRunErrorController::class, 'purgeOld'])->name('parser-run-errors.purge-old');
        Route::apiResource('price-snapshots', PriceSnapshotAdminController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        // Not `basket-snapshots/recalculate` — avoids clashing with `basket-snapshots/{basket_snapshot}` on some stacks / cached routes.
        Route::post('basket-snapshots-recalculate', [BasketSnapshotAdminController::class, 'recalculate'])->name('basket-snapshots.recalculate');
        Route::get('basket-snapshots', [BasketSnapshotAdminController::class, 'index'])->name('basket-snapshots.index');
        Route::get('basket-snapshots/{basket_snapshot}', [BasketSnapshotAdminController::class, 'show'])
            ->whereNumber('basket_snapshot')
            ->name('basket-snapshots.show');
    });
