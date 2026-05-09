<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tərəvəzlər: Laravel Str::slug('Tərəvəzlər') = terevezler; köhnə dəyər terevuzler nav/URL ilə uyğun gəlmirdi.
     */
    public function up(): void
    {
        if (! Schema::hasTable('price_categories')) {
            return;
        }

        DB::table('price_categories')
            ->where('code', 'food_vegetables')
            ->update([
                'slug' => 'terevezler',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_categories')) {
            return;
        }

        DB::table('price_categories')
            ->where('code', 'food_vegetables')
            ->update([
                'slug' => 'terevuzler',
                'updated_at' => now(),
            ]);
    }
};
