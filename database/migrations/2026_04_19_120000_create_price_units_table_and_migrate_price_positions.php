<?php

use App\Support\LocalizedJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('label', 255);
            $table->string('variant', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('price_units')->insert([
            'code' => 'unspecified',
            'label' => '—',
            'variant' => null,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $defaultId = (int) DB::table('price_units')->where('code', 'unspecified')->value('id');

        Schema::table('price_positions', function (Blueprint $table) {
            $table->foreignId('price_unit_id')
                ->nullable()
                ->after('name')
                ->constrained('price_units')
                ->restrictOnDelete();
        });

        /** @var array<string, int> */
        $fingerprintToId = [];
        $seq = 0;

        $positionRows = DB::table('price_positions')->select('id', 'unit')->get();
        foreach ($positionRows as $row) {
            $decoded = json_decode((string) $row->unit, true);
            $normalized = LocalizedJson::normalizeUnit(is_array($decoded) ? $decoded : null)
                ?? LocalizedJson::unitTriple('', '');

            $enBlock = $normalized['en'] ?? null;
            $label = '';
            $variant = '';
            if (is_array($enBlock)) {
                $label = trim((string) ($enBlock['label'] ?? ''));
                $variant = trim((string) ($enBlock['variant'] ?? ''));
            }
            if ($label === '' && $variant === '') {
                foreach (['az', 'ru'] as $loc) {
                    $b = $normalized[$loc] ?? null;
                    if (is_array($b)) {
                        $label = trim((string) ($b['label'] ?? ''));
                        $variant = trim((string) ($b['variant'] ?? ''));
                        if ($label !== '' || $variant !== '') {
                            break;
                        }
                    }
                }
            }

            if ($label === '' && $variant === '') {
                DB::table('price_positions')->where('id', $row->id)->update(['price_unit_id' => $defaultId]);

                continue;
            }

            $fp = $label."\x1e".$variant;
            if (! isset($fingerprintToId[$fp])) {
                $seq++;
                $code = 'u_'.substr(md5($fp), 0, 14);
                $baseCode = $code;
                $suffix = 0;
                while (DB::table('price_units')->where('code', $code)->exists()) {
                    $suffix++;
                    $code = substr($baseCode, 0, 10).'_'.$suffix;
                }

                $fingerprintToId[$fp] = (int) DB::table('price_units')->insertGetId([
                    'code' => $code,
                    'label' => $label !== '' ? $label : '—',
                    'variant' => $variant !== '' ? $variant : null,
                    'sort_order' => 100 + $seq,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('price_positions')->where('id', $row->id)->update([
                'price_unit_id' => $fingerprintToId[$fp],
            ]);
        }

        Schema::table('price_positions', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('price_positions') || ! Schema::hasTable('price_units')) {
            return;
        }

        Schema::table('price_positions', function (Blueprint $table) {
            $table->json('unit')->nullable()->after('name');
        });

        $positions = DB::table('price_positions')
            ->leftJoin('price_units as pu', 'pu.id', '=', 'price_positions.price_unit_id')
            ->select('price_positions.id', 'pu.label', 'pu.variant')
            ->get();

        foreach ($positions as $p) {
            $label = trim((string) ($p->label ?? ''));
            $variant = trim((string) ($p->variant ?? ''));
            $unit = LocalizedJson::unitTriple($label, $variant);
            DB::table('price_positions')->where('id', $p->id)->update([
                'unit' => json_encode($unit, JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('price_positions', function (Blueprint $table) {
            $table->dropForeign(['price_unit_id']);
            $table->dropColumn('price_unit_id');
        });

        Schema::dropIfExists('price_units');
    }
};
