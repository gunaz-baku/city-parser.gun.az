<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_positions')) {
            return;
        }

        Schema::table('price_positions', function (Blueprint $table): void {
            // Drop columns not present in current PricePosition model fillable fields.
            // Keep id/timestamps and the model fields: category_id, slug, name, unit_id, unit_size, parser_type, is_active, sort_order.

            // if (Schema::hasColumn('price_positions', 'city_id')) {
            //     // created in 2026_04_17_000003_create_price_positions_table.php
            //     $table->dropForeign(['city_id']);
            //     $table->dropUnique(['city_id', 'slug']);
            //     $table->dropColumn('city_id');
            // }

            if (Schema::hasColumn('price_positions', 'unit')) {
                $table->dropColumn('unit');
            }
            foreach (['meta_title', 'meta_description', 'seo_text'] as $col) {
                if (Schema::hasColumn('price_positions', $col)) {
                    $table->dropColumn($col);
                }
            }
            foreach (['include_in_dolma_index', 'dolma_qty', 'dolma_unit'] as $col) {
                if (Schema::hasColumn('price_positions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // no-op (intentionally not re-adding legacy columns)
    }
};

