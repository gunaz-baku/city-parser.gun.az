<?php

namespace Database\Seeders;

use App\Models\City;
use App\Support\SyntheticCity;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        City::query()->updateOrCreate(
            ['id' => SyntheticCity::ID],
            [
                'name' => SyntheticCity::NAME,
                'is_active' => true,
            ],
        );
    }
}
