<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_categories')) {
            return;
        }

        if (! Schema::hasColumn('price_categories', 'show_in_page')) {
            Schema::table('price_categories', function (Blueprint $table): void {
                $table->boolean('show_in_page')->default(true)->after('is_active');
                $table->index('show_in_page');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_categories') || ! Schema::hasColumn('price_categories', 'show_in_page')) {
            return;
        }

        Schema::table('price_categories', function (Blueprint $table): void {
            $table->dropIndex(['show_in_page']);
            $table->dropColumn('show_in_page');
        });
    }
};
