<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\TruncatesPriceDomainTables;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use TruncatesPriceDomainTables;
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->truncatePriceDomainTables();

        $this->call([
            CitySeeder::class,
            PriceCategoryHierarchySeeder::class,
            NewFormattedCsvPricePositionsSeeder::class,
            CityPriceSectionSeeder::class,
            DolmaSabatiBasketSeeder::class,
        ]);

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $basicEmail = (string) config('parser_api.basic_username', '');
        $basicPassword = (string) env('PARSER_BASIC_PASSWORD', '');
        if ($basicEmail !== '' && $basicPassword !== '') {
            $exit = Artisan::call('parser:ensure-basic-user');
            $this->command?->line(trim(Artisan::output()));
            if ($exit !== 0) {
                $this->command?->warn('parser:ensure-basic-user çıxış kodu: '.$exit.' (GunAz /api/v1/control üçün HTTP Basic işləməyə bilər).');
            }
        } else {
            $this->command?->warn(
                'PARSER_BASIC_USERNAME və/və ya PARSER_BASIC_PASSWORD boşdur — parser:ensure-basic-user atlandı. '.
                'GunAz-dan parser işə salmaq üçün .env-də doldurun və: php artisan parser:ensure-basic-user'
            );
        }
    }
}
