<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_categories') && Schema::hasColumn('price_categories', 'code')) {
            Schema::table('price_categories', function (Blueprint $table): void {
                // created in 2026_04_17_000002_create_price_categories_table.php
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }

        if (Schema::hasTable('price_positions') && Schema::hasColumn('price_positions', 'code')) {
            Schema::table('price_positions', function (Blueprint $table): void {
                // created in 2026_04_17_000003_create_price_positions_table.php
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }

    public function down(): void
    {
        // no-op (we intentionally don't restore the legacy code columns)
    }
};

