<?php

/**
 * gun_az CSV-dəki kateqoriya sütunu (adətən RU, emoji ola bilər) → PriceCategoryHierarchy definition açarı
 * (5 əsas kateqoriyadan biri), sonra DB-də uyğun `slug` ilə həll olunur.
 *
 * Qeyd: `Хозтовары` import olunmur (skip).
 */
return [
    /* Qida məhsulları */
    'Молочные' => 'food_products',
    'Мясо' => 'food_products',
    'Рыба' => 'food_products',
    'Яйца' => 'food_products',
    'Колбасы' => 'food_products',
    'Овощи' => 'food_products',
    'Зелень' => 'food_products',
    'Фрукты' => 'food_products',
    'Орехи' => 'food_products',
    'Хлеб' => 'food_products',
    'Крупы' => 'food_products',
    'Макароны' => 'food_products',
    'Масла' => 'food_products',
    'Бакалея' => 'food_products',
    'Консервы' => 'food_products',
    'Заморозка' => 'food_products',
    'Напитки' => 'food_products',
    'Сладости' => 'food_products',

    /* Uşaq baxımı (CSV: emoji + RU başlıq; normalizeCsvCategoryTitleForLeafMap → «Детское») */
    'Детское' => 'childcare',
    'Детские товары' => 'childcare',
    '🍼 Детское' => 'childcare',

    /* Restoranlar */
    'Рестораны' => 'restaurants',
    'Ресторан' => 'restaurants',

    /* Tibb (dərmanlar) */
    'Аптека' => 'medicine',
    'Лекарства' => 'medicine',
    'Медицина' => 'medicine',

    /* Skip */
    'Хозтовары' => null,
];
