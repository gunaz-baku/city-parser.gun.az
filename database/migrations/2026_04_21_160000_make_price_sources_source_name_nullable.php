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

        if (Schema::hasColumn('price_sources', 'source_name')) {
            Schema::table('price_sources', function (Blueprint $table): void {
                // allow minimal PriceSource rows (csv_links) without source_name
                $table->string('source_name', 120)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // no-op
    }
};

