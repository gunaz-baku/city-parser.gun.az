<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait TruncatesPriceDomainTables
{
    /**
     * Qiymət mövqeləri, mənbələr, snapshotlar — cities / basket_definitions / users toxunulmur.
     */
    protected function truncatePriceDomainTables(): void
    {
        $tables = [
            'api_sync_logs',
            'source_price_results',
            'price_snapshots',
            'basket_snapshots',
            'basket_items',
            'parser_run_errors',
            'price_sources',
            'price_positions',
            'parser_runs',
            'price_categories',
        ];

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('Qiymət domeni cədvəlləri truncate edildi.');
    }
}
