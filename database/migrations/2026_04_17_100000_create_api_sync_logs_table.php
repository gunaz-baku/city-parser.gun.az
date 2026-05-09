<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parser_run_id')->nullable()->constrained('parser_runs')->nullOnDelete();
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id');
            $table->string('endpoint', 512);
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['parser_run_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
    }
};
