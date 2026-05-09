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
        if (! Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('short_name', 40);
                $table->string('unit_type', 20);
                $table->string('base_unit', 40)->nullable();
                $table->decimal('multiplier', 14, 6)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        $this->seedCanonicalUnits($now);

        if (Schema::hasTable('price_units')) {
            $this->ensureLegacyUnitsFromPriceUnitsTable($now);
        }

        if (Schema::hasTable('price_positions') && ! Schema::hasColumn('price_positions', 'unit')) {
            Schema::table('price_positions', function (Blueprint $table): void {
                $table->json('unit')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('price_positions') && Schema::hasColumn('price_positions', 'price_unit_id') && Schema::hasTable('price_units')) {
            $positions = DB::table('price_positions as pp')
                ->leftJoin('price_units as pu', 'pu.id', '=', 'pp.price_unit_id')
                ->select('pp.id', 'pu.label as pu_label')
                ->get();

            foreach ($positions as $p) {
                $label = trim((string) ($p->pu_label ?? ''));
                DB::table('price_positions')->where('id', $p->id)->update([
                    'unit' => json_encode(LocalizedJson::unitTriple($label, ''), JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if (Schema::hasTable('price_positions') && ! Schema::hasColumn('price_positions', 'unit_id')) {
            $after = Schema::hasColumn('price_positions', 'unit_size') ? 'unit_size' : 'name';
            Schema::table('price_positions', function (Blueprint $table) use ($after): void {
                $table->foreignId('unit_id')->nullable()->after($after)->constrained('units')->nullOnDelete();
            });
        }

        if (Schema::hasTable('price_positions') && Schema::hasColumn('price_positions', 'price_unit_id') && Schema::hasTable('price_units')) {
            $positions = DB::table('price_positions as pp')
                ->leftJoin('price_units as pu', 'pu.id', '=', 'pp.price_unit_id')
                ->select('pp.id', 'pp.price_unit_id', 'pu.code as pu_code', 'pu.label as pu_label')
                ->get();

            foreach ($positions as $p) {
                $unitId = $this->resolveUnitIdForOldPriceUnit(
                    $p->price_unit_id !== null ? (int) $p->price_unit_id : null,
                    (string) ($p->pu_code ?? ''),
                    (string) ($p->pu_label ?? ''),
                );
                DB::table('price_positions')->where('id', $p->id)->update(['unit_id' => $unitId]);
            }

            Schema::table('price_positions', function (Blueprint $table): void {
                $table->dropForeign(['price_unit_id']);
            });
            Schema::table('price_positions', function (Blueprint $table): void {
                $table->dropColumn('price_unit_id');
            });
            Schema::dropIfExists('price_units');
        }

        if (Schema::hasTable('basket_items') && ! Schema::hasColumn('basket_items', 'unit_id')) {
            Schema::table('basket_items', function (Blueprint $table): void {
                $table->foreignId('unit_id')->nullable()->after('qty')->constrained('units')->nullOnDelete();
            });
        }

        if (Schema::hasTable('basket_items') && Schema::hasColumn('basket_items', 'unit_id')) {
            foreach (DB::table('basket_items')->select('id', 'qty_unit')->cursor() as $item) {
                $id = $this->resolveUnitIdFromQtyUnitString((string) ($item->qty_unit ?? ''));
                DB::table('basket_items')->where('id', $item->id)->update(['unit_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('basket_items') && Schema::hasColumn('basket_items', 'unit_id')) {
            Schema::table('basket_items', function (Blueprint $table): void {
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
            });
        }
    }

    private function seedCanonicalUnits(\Illuminate\Support\Carbon $now): void
    {
        $rows = [
            ['code' => 'g', 'name' => 'Gram', 'short_name' => 'g', 'unit_type' => 'weight', 'base_unit' => null, 'multiplier' => null, 'sort_order' => 10],
            ['code' => 'kg', 'name' => 'Kilogram', 'short_name' => 'kg', 'unit_type' => 'weight', 'base_unit' => 'g', 'multiplier' => '1000', 'sort_order' => 20],
            ['code' => 'ml', 'name' => 'Millilitre', 'short_name' => 'ml', 'unit_type' => 'volume', 'base_unit' => null, 'multiplier' => null, 'sort_order' => 30],
            ['code' => 'l', 'name' => 'Liter', 'short_name' => 'l', 'unit_type' => 'volume', 'base_unit' => 'ml', 'multiplier' => '1000', 'sort_order' => 40],
            ['code' => 'pcs', 'name' => 'Piece', 'short_name' => 'pcs', 'unit_type' => 'count', 'base_unit' => null, 'multiplier' => null, 'sort_order' => 50],
            ['code' => 'pack', 'name' => 'Pack', 'short_name' => 'pack', 'unit_type' => 'count', 'base_unit' => null, 'multiplier' => null, 'sort_order' => 60],
            ['code' => 'bottle', 'name' => 'Bottle', 'short_name' => 'bottle', 'unit_type' => 'count', 'base_unit' => null, 'multiplier' => null, 'sort_order' => 70],
        ];

        foreach ($rows as $row) {
            if (DB::table('units')->where('code', $row['code'])->exists()) {
                continue;
            }
            DB::table('units')->insert(array_merge($row, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function ensureLegacyUnitsFromPriceUnitsTable(\Illuminate\Support\Carbon $now): void
    {
        $rows = DB::table('price_units')->get();
        foreach ($rows as $pu) {
            $code = trim((string) ($pu->code ?? ''));
            if ($code === '' || $code === 'unspecified') {
                continue;
            }
            if (DB::table('units')->where('code', $code)->exists()) {
                continue;
            }
            $label = trim((string) ($pu->label ?? ''));
            $name = $label !== '' ? $label : $code;
            $short = mb_substr($label !== '' ? $label : $code, 0, 40);
            DB::table('units')->insert([
                'code' => mb_substr($code, 0, 40),
                'name' => mb_substr($name, 0, 120),
                'short_name' => mb_substr($short, 0, 40),
                'unit_type' => 'count',
                'base_unit' => null,
                'multiplier' => null,
                'is_active' => true,
                'sort_order' => (int) ($pu->sort_order ?? 500),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function resolveUnitIdForOldPriceUnit(?int $priceUnitId, string $puCode, string $puLabel): ?int
    {
        if ($priceUnitId === null) {
            return null;
        }
        if ($puCode === 'unspecified') {
            return null;
        }

        $byCode = DB::table('units')->where('code', $puCode)->value('id');
        if ($byCode !== null) {
            return (int) $byCode;
        }

        $normalized = $this->normalizeUnitToken($puLabel !== '' ? $puLabel : $puCode);
        if ($normalized !== '') {
            $byToken = DB::table('units')
                ->where(function ($q) use ($normalized): void {
                    $q->where('code', $normalized)->orWhere('short_name', $normalized);
                })
                ->value('id');
            if ($byToken !== null) {
                return (int) $byToken;
            }
        }

        return null;
    }

    private function resolveUnitIdFromQtyUnitString(string $qtyUnit): ?int
    {
        $t = $this->normalizeUnitToken($qtyUnit);
        if ($t === '') {
            return null;
        }

        $id = DB::table('units')
            ->where(function ($q) use ($t): void {
                $q->where('code', $t)->orWhere('short_name', $t);
            })
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function normalizeUnitToken(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = preg_replace('/\s+/u', '', $s);
        $aliases = [
            'г' => 'g',
            'кг' => 'kg',
            'к' => 'kg',
            'л' => 'l',
            'мл' => 'ml',
            'əd' => 'pcs',
            'ed' => 'pcs',
            'шт' => 'pcs',
            'pc' => 'pcs',
            'piece' => 'pcs',
            'pieces' => 'pcs',
            'paket' => 'pack',
            'şüşə' => 'bottle',
            'suse' => 'bottle',
        ];
        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s;
    }
};
