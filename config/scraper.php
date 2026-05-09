<?php

return [

    'chrome_driver_url' => env('CHROME_DRIVER_URL', 'http://127.0.0.1:9515'),

    'wolt' => [
        // ChromeDriver olmadan: birbaşa HTTP (DomCrawler). true üçün CHROME_DRIVER_URL-də server işləməlidir.
        'use_webdriver' => filter_var(env('WOLT_USE_WEBDRIVER', false), FILTER_VALIDATE_BOOLEAN),
        'http_fallback' => filter_var(env('WOLT_HTTP_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),
        'page_wait_seconds' => (int) env('WOLT_WEBDRIVER_PAGE_WAIT', 2),
    ],

    'bina' => [
        'max_pages' => (int) env('BINA_PARSER_MAX_PAGES', 3),
        'max_item_cards' => (int) env('BINA_MAX_ITEM_CARDS', 90),
        'graphql_url' => env('BINA_GRAPHQL_URL', 'https://bina.az/graphql'),
        'use_graphql' => filter_var(env('BINA_USE_GRAPHQL', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
