<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('basket_snapshots')) {
            return;
        }

        if (! Schema::hasColumn('basket_snapshots', 'dolma_index_total')) {
            Schema::table('basket_snapshots', function (Blueprint $table): void {
                $table->decimal('dolma_index_total', 14, 4)->nullable()->after('total_price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('basket_snapshots')) {
            return;
        }

        if (Schema::hasColumn('basket_snapshots', 'dolma_index_total')) {
            Schema::table('basket_snapshots', function (Blueprint $table): void {
                $table->dropColumn('dolma_index_total');
            });
        }
    }
};
