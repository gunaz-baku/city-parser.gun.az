<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('price_positions')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('currency', 3)->default('AZN');
            $table->decimal('price_min', 14, 4);
            $table->decimal('price_max', 14, 4);
            $table->decimal('price_avg', 14, 4);
            $table->unsignedInteger('sample_size')->default(0);
            $table->unsignedInteger('source_count')->default(0);
            $table->string('parser_type', 50);
            $table->foreignId('parser_run_id')->nullable()->constrained('parser_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['position_id', 'snapshot_date']);
            $table->index(['city_id', 'snapshot_date']);
            $table->index(['position_id', 'snapshot_date']);
            $table->index(['snapshot_date', 'parser_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
