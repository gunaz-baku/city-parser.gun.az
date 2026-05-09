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
            if (Schema::hasColumn('price_categories', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('price_categories', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('price_categories')
                    ->nullOnDelete();
                $table->index(['parent_id', 'is_active']);
            }
        });
    }
};
