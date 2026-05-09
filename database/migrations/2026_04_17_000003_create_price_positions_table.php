<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('price_categories')->restrictOnDelete();
            $table->string('code', 120)->unique();
            $table->string('slug', 180);
            $table->json('name');
            $table->json('unit');
            $table->string('parser_type', 50)->default('manual');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('seo_text')->nullable();
            $table->boolean('include_in_dolma_index')->default(false);
            $table->decimal('dolma_qty', 12, 4)->nullable();
            $table->string('dolma_unit', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
            $table->index(['category_id', 'is_active']);
            $table->index('parser_type');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_positions');
    }
};
