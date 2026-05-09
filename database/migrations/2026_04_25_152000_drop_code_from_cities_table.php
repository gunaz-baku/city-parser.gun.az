<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cities') || ! Schema::hasColumn('cities', 'code')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropUnique('cities_code_unique');
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cities') || Schema::hasColumn('cities', 'code')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            $table->string('code', 100)->nullable()->unique();
        });
    }
};
