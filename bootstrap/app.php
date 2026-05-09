<?php

use App\Http\Middleware\AuthenticateGunAzApi;
use App\Http\Middleware\AuthenticateWithBasicAuthForApi;
use App\Http\Middleware\ForceJsonAccept;
use App\Jobs\StartParserJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Cümə axşamı = Thursday, 06:00 UTC+4 (Asia/Baku)
        $tz = 'Asia/Baku';
        $schedule->call(fn () => StartParserJob::dispatch('wolt'))
            ->thursdays()->at('10:00')->timezone($tz)->name('parser:wolt');
        $schedule->call(fn () => StartParserJob::dispatch('bina'))
            ->thursdays()->at('10:00')->timezone($tz)->name('parser:bina');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonAccept::class,
        ]);
        $middleware->alias([
            'force.json' => ForceJsonAccept::class,
            'gunaz.api' => AuthenticateGunAzApi::class,
            'auth.basic.api' => AuthenticateWithBasicAuthForApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
