<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('basket_definitions') || Schema::hasColumn('basket_definitions', 'type')) {
            return;
        }

        Schema::table('basket_definitions', function (Blueprint $table): void {
            $table->string('type', 20)->default('basket')->after('name');
            $table->index('type');
        });

        DB::table('basket_definitions')
            ->whereNull('type')
            ->update(['type' => 'basket']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasColumn('basket_definitions', 'type')) {
            return;
        }

        Schema::table('basket_definitions', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};

