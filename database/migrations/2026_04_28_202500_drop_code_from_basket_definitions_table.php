<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasColumn('basket_definitions', 'code')) {
            return;
        }

        Schema::table('basket_definitions', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('basket_definitions') || Schema::hasColumn('basket_definitions', 'code')) {
            return;
        }

        Schema::table('basket_definitions', function (Blueprint $table): void {
            $table->string('code', 100)->nullable()->after('id');
            $table->unique('code');
        });
    }
};

