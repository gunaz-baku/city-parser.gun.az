<?php

return [

    'snapshot_timezone' => env('SNAPSHOT_TIMEZONE', 'Asia/Baku'),

    /*
    | Test (GunAz UI): averages `rows`-da ilk N mövqedən sonra qiymət sahələrini null göndər.
    | Boş — söndürülüb. Nümunə: GUN_AZ_TEST_NULL_AVG_AFTER=10 → indeks 0–9 tam, 10+ null.
    */
    'gun_az_test_null_avg_after' => (($raw = env('GUN_AZ_TEST_NULL_AVG_AFTER')) !== null && $raw !== '' && is_numeric(trim((string) $raw)))
        ? max(1, (int) trim((string) $raw))
        : null,

    /*
    | GunAzParsingStrategySeeder üçün CSV yolu (layihə kökünə nisbətən və ya tam yol).
    */
    'gun_az_csv_path' => env(
        'GUN_AZ_CSV_PATH',
        base_path('gun_az_FINAL_parsing_strategy D (2).csv')
    ),

    /*
    | CSV kateqoriya sütunu → hierarchy definition açarları (food_* …), sonra DB `price_categories.slug`.
    */
    'gun_az_category_leaf_map' => require __DIR__.'/parsers/gun_az_category_leaf_map.php',

    /*
    | Bina.az Bakı listing URL-ləri — seeder `price_sources.links_json` + `source_type=bina` yazır;
    | ParseBinaSourceJob URL-dən sale/rent təxmin edir, `options_json.mode` varsa onu üstün tutur.
    | DB-yə yazmaq: php artisan db:seed --class=BinaBakuListingSourcesSeeder
    */
    'bina' => [
        'listings' => [
            ['code' => 'bina_baku_sale_new_1', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/1-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Yeni tikili · 1 otaq'],
            ['code' => 'bina_baku_sale_new_2', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/2-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Yeni tikili · 2 otaq'],
            ['code' => 'bina_baku_sale_new_3', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/3-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Yeni tikili · 3 otaq'],
            ['code' => 'bina_baku_sale_new_4', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/4-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Yeni tikili · 4 otaq'],
            ['code' => 'bina_baku_sale_new_5p', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/yeni-tikili/5-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Yeni tikili · 5+ otaq'],
            ['code' => 'bina_baku_sale_old_1', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/1-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Köhnə tikili · 1 otaq'],
            ['code' => 'bina_baku_sale_old_2', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/2-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Köhnə tikili · 2 otaq'],
            ['code' => 'bina_baku_sale_old_3', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/3-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Köhnə tikili · 3 otaq'],
            ['code' => 'bina_baku_sale_old_4', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/4-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Köhnə tikili · 4 otaq'],
            ['code' => 'bina_baku_sale_old_5p', 'url' => 'https://bina.az/baki/alqi-satqi/menziller/kohne-tikili/5-otaqli', 'mode' => 'sale', 'title' => 'Alqı-satqı · Köhnə tikili · 5+ otaq'],
            ['code' => 'bina_baku_rent_1', 'url' => 'https://bina.az/baki/kiraye/menziller/1-otaqli', 'mode' => 'rent', 'title' => 'Kirayə · 1 otaq'],
            ['code' => 'bina_baku_rent_2', 'url' => 'https://bina.az/baki/kiraye/menziller/2-otaqli', 'mode' => 'rent', 'title' => 'Kirayə · 2 otaq'],
            ['code' => 'bina_baku_rent_3', 'url' => 'https://bina.az/baki/kiraye/menziller/3-otaqli', 'mode' => 'rent', 'title' => 'Kirayə · 3 otaq'],
            ['code' => 'bina_baku_rent_4', 'url' => 'https://bina.az/baki/kiraye/menziller/4-otaqli', 'mode' => 'rent', 'title' => 'Kirayə · 4 otaq'],
            ['code' => 'bina_baku_rent_5', 'url' => 'https://bina.az/baki/kiraye/menziller/5-otaqli', 'mode' => 'rent', 'title' => 'Kirayə · 5 otaq'],
        ],
    ],

];
