<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('city_price_section_tabs')) {
            return;
        }

        Schema::table('city_price_section_tabs', function (Blueprint $table): void {
            if (Schema::hasColumn('city_price_section_tabs', 'code')) {
                $table->dropUnique('cps_tabs_city_code_unique');
                $table->dropColumn('code');
            }
        });
    }

    public function down(): void
    {
        // `code` sütunu köhnə məntiqlə bərpa olunmur.
    }
};
