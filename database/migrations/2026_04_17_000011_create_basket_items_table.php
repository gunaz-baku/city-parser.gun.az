<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_id')->constrained('basket_definitions')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('price_positions')->cascadeOnDelete();
            $table->decimal('qty', 12, 4);
            $table->string('qty_unit', 50);
            $table->timestamps();

            $table->unique(['basket_id', 'position_id']);
            $table->index('position_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_items');
    }
};
