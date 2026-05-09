<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CSV 🍼 Детское (151–153) əvvəl food_vegetables altına düşürdü; düzgün: childcare_products yarpağı.
     */
    public function up(): void
    {
        if (! Schema::hasTable('price_positions') || ! Schema::hasTable('price_categories')) {
            return;
        }

        $childProductsId = DB::table('price_categories')->where('code', 'childcare_products')->value('id');
        if ($childProductsId === null) {
            return;
        }

        DB::table('price_positions')
            ->whereIn('code', ['gun_az_151', 'gun_az_152', 'gun_az_153'])
            ->update([
                'category_id' => (int) $childProductsId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_positions') || ! Schema::hasTable('price_categories')) {
            return;
        }

        $vegGroupId = DB::table('price_categories')->where('code', 'food_vegetables')->value('id');
        if ($vegGroupId === null) {
            return;
        }

        DB::table('price_positions')
            ->whereIn('code', ['gun_az_151', 'gun_az_152', 'gun_az_153'])
            ->update([
                'category_id' => (int) $vegGroupId,
                'updated_at' => now(),
            ]);
    }
};
