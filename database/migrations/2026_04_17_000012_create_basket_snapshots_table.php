<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basket_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_id')->constrained('basket_definitions')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('total_price', 14, 4);
            $table->string('currency', 3)->default('AZN');
            $table->timestamps();

            $table->unique(['basket_id', 'city_id', 'snapshot_date']);
            $table->index(['city_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_snapshots');
    }
};
