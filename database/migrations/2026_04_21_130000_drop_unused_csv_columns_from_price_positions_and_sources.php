<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // price_positions: remove intermediate CSV columns
        if (Schema::hasTable('price_positions')) {
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

        // price_sources: keep only links_json (Links), drop other CSV-ish columns
        if (Schema::hasTable('price_sources')) {
            Schema::table('price_sources', function (Blueprint $table): void {
                $cols = [];
                foreach (['category', 'product', 'unit', 'unit_size'] as $col) {
                    if (Schema::hasColumn('price_sources', $col)) {
                        $cols[] = $col;
                    }
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }

    public function down(): void
    {
        // We intentionally keep this as a no-op. These columns were transient and not part of the desired schema.
    }
};

