<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parser_run_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_run_id')->constrained('parser_runs')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('price_positions')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('price_sources')->nullOnDelete();
            $table->string('error_stage', 80);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message');
            $table->json('error_context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['parser_run_id', 'error_stage']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_run_errors');
    }
};
