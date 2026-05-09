<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->string('sync_status', 30)->default('pending')->after('parser_run_id');
            $table->dateTime('synced_at')->nullable()->after('sync_status');
            $table->text('last_sync_error')->nullable()->after('synced_at');

            $table->index('sync_status');
        });

        Schema::table('basket_snapshots', function (Blueprint $table) {
            $table->string('sync_status', 30)->default('pending')->after('currency');
            $table->dateTime('synced_at')->nullable()->after('sync_status');
            $table->text('last_sync_error')->nullable()->after('synced_at');

            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['sync_status', 'synced_at', 'last_sync_error']);
        });

        Schema::table('basket_snapshots', function (Blueprint $table) {
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['sync_status', 'synced_at', 'last_sync_error']);
        });
    }
};
