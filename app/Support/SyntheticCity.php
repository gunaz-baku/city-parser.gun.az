<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * `cities` cədvəli silindikdən sonra GunAz / admin API üçün sabit “Bakı” göstəricisi.
 */
final class SyntheticCity
{
    public const ID = 1;

    public const CODE = 'baku';

    /** @var array{az: string, en: string, ru: string} */
    public const NAME = [
        'az' => 'Bakı',
        'en' => 'Baku',
        'ru' => 'Баку',
    ];

    /**
     * @return object{id: int, code: string, name: string}
     */
    public static function asDbRow(): object
    {
        return (object) [
            'id' => self::ID,
            'code' => self::CODE,
            'name' => json_encode(self::NAME, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * SQL select hissələri (cities join əvəzi).
     *
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    public static function selectAliases(): array
    {
        $pdo = DB::connection()->getPdo();
        $code = $pdo->quote(self::CODE);
        $name = $pdo->quote(json_encode(self::NAME, JSON_UNESCAPED_UNICODE));

        return [
            DB::raw("{$code} as city_code"),
            DB::raw("{$name} as city_name"),
        ];
    }
}
