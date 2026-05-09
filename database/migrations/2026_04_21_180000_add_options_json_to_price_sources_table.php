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

        if (! Schema::hasColumn('price_sources', 'options_json')) {
            Schema::table('price_sources', function (Blueprint $table): void {
                $table->json('options_json')->nullable()->after('links_json');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_sources')) {
            return;
        }

        if (Schema::hasColumn('price_sources', 'options_json')) {
            Schema::table('price_sources', function (Blueprint $table): void {
                $table->dropColumn('options_json');
            });
        }
    }
};
