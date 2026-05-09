<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('position_failures')) {
            return;
        }

        Schema::create('position_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_run_id')->constrained('parser_runs')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('price_positions')->cascadeOnDelete();
            $table->date('failure_date');
            $table->text('reason');
            $table->timestamp('created_at')->nullable();

            $table->unique(['parser_run_id', 'position_id']);
            $table->index(['position_id', 'failure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_failures');
    }
};
