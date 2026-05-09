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
            if (! Schema::hasColumn('price_positions', 'csv_category')) {
                $table->string('csv_category', 191)->nullable()->after('category_id');
            }
            if (! Schema::hasColumn('price_positions', 'csv_product')) {
                $table->string('csv_product', 191)->nullable()->after('csv_category');
            }
            if (! Schema::hasColumn('price_positions', 'csv_unit')) {
                $table->string('csv_unit', 50)->nullable()->after('csv_product');
            }
            if (! Schema::hasColumn('price_positions', 'csv_unit_size')) {
                $table->string('csv_unit_size', 50)->nullable()->after('csv_unit');
            }
            if (! Schema::hasColumn('price_positions', 'csv_links_json')) {
                $table->json('csv_links_json')->nullable()->after('csv_unit_size');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_positions')) {
            return;
        }

        Schema::table('price_positions', function (Blueprint $table): void {
            $cols = [];
            foreach (['csv_category', 'csv_product', 'csv_unit', 'csv_unit_size', 'csv_links_json'] as $col) {
                if (Schema::hasColumn('price_positions', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};

