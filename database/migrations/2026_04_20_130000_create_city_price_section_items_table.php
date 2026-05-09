<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_price_section_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->unsignedTinyInteger('tab'); // 1..4
            $table->unsignedBigInteger('position_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['city_id', 'tab', 'position_id'], 'city_price_section_items_unique');
            $table->index(['city_id', 'tab', 'sort_order'], 'city_price_section_items_city_tab_sort_idx');

            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
            $table->foreign('position_id')->references('id')->on('price_positions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_price_section_items');
    }
};

