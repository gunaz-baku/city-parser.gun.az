<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_sources')) {
            return;
        }

        Schema::table('price_sources', function (Blueprint $table): void {
            if (Schema::hasColumn('price_sources', 'brand_name')) {
                // created by 2026_04_17_120000_add_brand_and_variant_to_price_sources_table.php
                $table->dropIndex(['position_id', 'source_type', 'brand_name']);
                $table->dropColumn('brand_name');
            }
            if (Schema::hasColumn('price_sources', 'variant')) {
                $table->dropColumn('variant');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_sources')) {
            return;
        }

        Schema::table('price_sources', function (Blueprint $table): void {
            if (! Schema::hasColumn('price_sources', 'brand_name')) {
                $table->string('brand_name', 160)->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('price_sources', 'variant')) {
                $table->string('variant', 191)->nullable()->after('brand_name');
            }
            $table->index(['position_id', 'source_type', 'brand_name']);
        });
    }
};

