<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parser_runs', function (Blueprint $table) {
            $table->id();
            $table->string('parser_type', 50);
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('trigger_type', 30)->default('cron');
            $table->string('status', 30)->default('running');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('total_positions')->default(0);
            $table->unsignedInteger('success_positions')->default(0);
            $table->unsignedInteger('failed_positions')->default(0);
            $table->unsignedInteger('skipped_positions')->default(0);
            $table->unsignedInteger('total_sources')->default(0);
            $table->unsignedInteger('success_sources')->default(0);
            $table->unsignedInteger('failed_sources')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['parser_type', 'status']);
            $table->index(['city_id', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_runs');
    }
};
