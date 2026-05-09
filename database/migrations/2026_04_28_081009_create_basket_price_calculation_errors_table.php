<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_price_calculation_errors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('basket_id')
                ->nullable()
                ->constrained('basket_definitions')
                ->nullOnDelete();

            $table->foreignId('basket_item_id')
                ->nullable()
                ->constrained('basket_items')
                ->nullOnDelete();

            $table->foreignId('position_id')
                ->nullable()
                ->constrained('price_positions')
                ->nullOnDelete();

            // error type (logic-level classification)
            $table->string('error_type', 100);

            // human / debug message
            $table->text('message');

            // optional context (qty, unit, price, etc.)
            $table->json('context')->nullable();

            // calculation run id (if you batch calculate)
            $table->unsignedBigInteger('calculation_run_id')->nullable();

            $table->timestamps();

            $table->index('basket_id');
            $table->index('basket_item_id');
            $table->index('position_id');
            $table->index('error_type');
            $table->index('calculation_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_price_calculation_errors');
    }
};