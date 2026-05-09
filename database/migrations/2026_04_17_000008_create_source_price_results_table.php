<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_price_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_run_id')->constrained('parser_runs')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('price_positions')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('price_sources')->nullOnDelete();
            $table->date('result_date');
            $table->string('external_item_id', 191)->nullable();
            $table->string('title', 500)->nullable();
            $table->decimal('raw_price', 14, 4)->nullable();
            $table->decimal('raw_area', 14, 4)->nullable();
            $table->decimal('normalized_price', 14, 4)->nullable();
            $table->string('currency', 3)->default('AZN');
            $table->boolean('is_outlier')->default(false);
            $table->boolean('is_valid')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['position_id', 'result_date']);
            $table->index(['parser_run_id', 'position_id']);
            $table->index(['source_id', 'result_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_price_results');
    }
};
