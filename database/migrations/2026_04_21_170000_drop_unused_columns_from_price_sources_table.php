<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_sources')) {
            return;
        }

        Schema::table('price_sources', function (Blueprint $table): void {
            $cols = [];
            foreach (['source_name', 'source_url', 'source_config', 'external_source_id'] as $col) {
                if (Schema::hasColumn('price_sources', $col)) {
                    $cols[] = $col;
                }
            }

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }

    public function down(): void
    {
        // no-op
    }
};

