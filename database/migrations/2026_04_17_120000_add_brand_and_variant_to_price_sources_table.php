<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->string('brand_name', 160)->nullable()->after('source_type');
            $table->string('variant', 191)->nullable()->after('brand_name');
        });

        if (Schema::hasTable('price_sources')) {
            DB::table('price_sources')
                ->whereNull('brand_name')
                ->update(['brand_name' => DB::raw('source_name')]);
        }

        Schema::table('price_sources', function (Blueprint $table) {
            $table->index(['position_id', 'source_type', 'brand_name']);
        });
    }

    public function down(): void
    {
        Schema::table('price_sources', function (Blueprint $table) {
            $table->dropIndex(['position_id', 'source_type', 'brand_name']);
            $table->dropColumn(['brand_name', 'variant']);
        });
    }
};
