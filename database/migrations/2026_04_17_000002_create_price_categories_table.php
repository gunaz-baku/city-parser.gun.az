<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('price_categories')
                ->nullOnDelete();
            $table->string('code', 100)->unique();
            $table->string('slug', 160)->unique();
            $table->json('name');
            $table->string('icon', 255)->nullable();
            $table->string('source_type', 50)->default('mixed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'is_active']);
            $table->index('source_type');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_categories');
    }
};
