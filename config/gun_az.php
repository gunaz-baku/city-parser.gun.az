<?php

return [

    'base_url' => rtrim((string) env('GUN_AZ_API_URL', 'https://gun.az'), '/'),

    /**
     * false = gun.az snapshot-ları bu hostdan pull edir (GET /api/v1/parser/...); push job HTTP göndərmir.
     * true = köhnə rejim: parser gun.az-a POST edir (GUN_AZ_API_URL üzərində endpoint olmalıdır).
     */
    'push_enabled' => filter_var(env('GUN_AZ_PUSH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'token' => env('GUN_AZ_API_TOKEN'),

    'timeout' => (int) env('GUN_AZ_API_TIMEOUT', 60),

    'endpoints' => [
        'price_snapshots' => env('GUN_AZ_ENDPOINT_PRICE_SNAPSHOTS', '/api/v1/parser/price-snapshots'),
        'basket_snapshots' => env('GUN_AZ_ENDPOINT_BASKET_SNAPSHOTS', '/api/v1/parser/basket-snapshots'),
    ],

];
