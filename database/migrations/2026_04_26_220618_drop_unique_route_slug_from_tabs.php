<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('city_price_section_tabs', function (Blueprint $table) {
            $table->dropUnique('cps_tabs_route_slug_unique');
        });
    }
    
    public function down(): void
    {
        Schema::table('city_price_section_tabs', function (Blueprint $table) {
            $table->unique('route_slug', 'cps_tabs_route_slug_unique');
        });
    }
};
