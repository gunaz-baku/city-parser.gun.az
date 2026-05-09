<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parser_run_errors', function (Blueprint $table) {
            if (! Schema::hasColumn('parser_run_errors', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('error_context');
                $table->index('occurred_at');
            }
        });

        if (Schema::hasColumn('parser_run_errors', 'occurred_at') && Schema::hasColumn('parser_run_errors', 'created_at')) {
            DB::table('parser_run_errors')
                ->whereNull('occurred_at')
                ->update(['occurred_at' => DB::raw('created_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parser_run_errors', function (Blueprint $table) {
            if (Schema::hasColumn('parser_run_errors', 'occurred_at')) {
                $table->dropIndex(['occurred_at']);
                $table->dropColumn('occurred_at');
            }
        });
    }
};
