<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('price_positions')->cascadeOnDelete();
            $table->string('source_type', 50);
            $table->string('source_name', 120);
            $table->string('source_url', 1024)->nullable();
            $table->json('source_config')->nullable();
            $table->string('external_source_id', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['source_type', 'is_active']);
            $table->index('position_id');
            $table->index(['position_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_sources');
    }
};
