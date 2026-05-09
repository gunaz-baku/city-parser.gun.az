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
            if (! Schema::hasColumn('price_sources', 'category')) {
                $table->string('category', 191)->nullable()->after('position_id');
            }
            if (! Schema::hasColumn('price_sources', 'product')) {
                $table->string('product', 191)->nullable()->after('category');
            }
            if (! Schema::hasColumn('price_sources', 'unit')) {
                $table->string('unit', 50)->nullable()->after('product');
            }
            if (! Schema::hasColumn('price_sources', 'unit_size')) {
                $table->string('unit_size', 50)->nullable()->after('unit');
            }
            if (! Schema::hasColumn('price_sources', 'links_json')) {
                $table->json('links_json')->nullable()->after('unit_size');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_sources')) {
            return;
        }

        Schema::table('price_sources', function (Blueprint $table): void {
            $cols = [];
            foreach (['category', 'product', 'unit', 'unit_size', 'links_json'] as $col) {
                if (Schema::hasColumn('price_sources', $col)) {
                    $cols[] = $col;
                }
            }

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};

