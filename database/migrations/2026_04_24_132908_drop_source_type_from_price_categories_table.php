<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_categories', function (Blueprint $table) {
            if (Schema::hasColumn('price_categories', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('price_categories', 'source_type')) {
                $table->string('source_type', 50)->default('mixed');
                $table->index('source_type');
            }
        });
    }
};
