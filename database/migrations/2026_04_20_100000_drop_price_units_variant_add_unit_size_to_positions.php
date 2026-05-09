<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_units') && Schema::hasColumn('price_units', 'variant')) {
            $rows = DB::table('price_units')->select('id', 'label', 'variant')->get();
            foreach ($rows as $row) {
                $variant = trim((string) ($row->variant ?? ''));
                if ($variant === '') {
                    continue;
                }
                $label = trim((string) ($row->label ?? ''));
                $merged = $label === '' ? $variant : $label.' — '.$variant;
                DB::table('price_units')->where('id', $row->id)->update(['label' => $merged]);
            }

            Schema::table('price_units', function (Blueprint $table): void {
                $table->dropColumn('variant');
            });
        }

        if (Schema::hasTable('price_positions') && ! Schema::hasColumn('price_positions', 'unit_size')) {
            Schema::table('price_positions', function (Blueprint $table): void {
                $table->decimal('unit_size', 12, 4)->nullable()->after('price_unit_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('price_positions') && Schema::hasColumn('price_positions', 'unit_size')) {
            Schema::table('price_positions', function (Blueprint $table): void {
                $table->dropColumn('unit_size');
            });
        }

        if (Schema::hasTable('price_units') && ! Schema::hasColumn('price_units', 'variant')) {
            Schema::table('price_units', function (Blueprint $table): void {
                $table->string('variant', 255)->nullable()->after('label');
            });
        }
    }
};
