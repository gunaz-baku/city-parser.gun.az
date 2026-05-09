<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:default' => 60,
        'redis:parser-wolt' => 120,
        'redis:parser-bina' => 120,
        'redis:parser-orchestration' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration (FIXED)
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-parsers' => [
            'connection' => 'redis',

            // Queue-lar stabil və ayrı işləyir
            'queue' => [
                'parser-orchestration',
                'parser-bina',
                'parser-wolt',
                'default',
            ],

            // ❌ AUTO BALANCE SİLİNDİ (problem yaradırdı)
            'balance' => 'simple',

            // ❌ AUTO SCALING SİLİNDİ
            'autoScalingStrategy' => null,

            // Stabil worker sayı
            'maxProcesses' => 6,

            'memory' => 256,

            'tries' => 3,

            'timeout' => 3600,

            'nice' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environments (FIXED)
    |--------------------------------------------------------------------------
    */

    'environments' => [
        'production' => [
            'supervisor-parsers' => [
                'maxProcesses' => 6,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-parsers' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];