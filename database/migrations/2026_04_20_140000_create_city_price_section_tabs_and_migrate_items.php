<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_price_section_tabs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->string('code', 60); // e.g. general/products/children/medicine/custom-...
            $table->string('route_slug', 160)->nullable(); // GunAz route('city.price.category') segment
            $table->json('name'); // {az,en,ru}
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'code'], 'cps_tabs_city_code_unique');
            $table->index(['city_id', 'sort_order'], 'cps_tabs_city_sort_idx');
            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
        });

        // Add tab_id to items and migrate existing 1..4 numeric tabs.
        Schema::table('city_price_section_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('tab_id')->nullable()->after('city_id');
            $table->index(['city_id', 'tab_id', 'sort_order'], 'cps_items_city_tabid_sort_idx');
        });

        // Create default tabs for every city that has (or may have) items.
        $cityIds = DB::table('cities')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $now = now();
        foreach ($cityIds as $cityId) {
            $defaults = [
                ['code' => 'general', 'route_slug' => 'food', 'name' => ['az' => 'Ümumi', 'en' => 'General', 'ru' => 'Общее'], 'sort_order' => 0],
                ['code' => 'products', 'route_slug' => 'products', 'name' => ['az' => 'Məhsullar', 'en' => 'Products', 'ru' => 'Продукты'], 'sort_order' => 1],
                ['code' => 'children', 'route_slug' => 'childcare', 'name' => ['az' => 'Uşaqlar', 'en' => 'Children', 'ru' => 'Дети'], 'sort_order' => 2],
                ['code' => 'medicine', 'route_slug' => 'medicine', 'name' => ['az' => 'Tibb', 'en' => 'Medicine', 'ru' => 'Медицина'], 'sort_order' => 3],
            ];

            foreach ($defaults as $d) {
                $exists = DB::table('city_price_section_tabs')
                    ->where('city_id', $cityId)
                    ->where('code', $d['code'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('city_price_section_tabs')->insert([
                    'city_id' => $cityId,
                    'code' => $d['code'],
                    'route_slug' => $d['route_slug'],
                    'name' => json_encode($d['name'], JSON_UNESCAPED_UNICODE),
                    'sort_order' => (int) $d['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Map old numeric `tab` -> default codes.
        $tabCodeByNum = [1 => 'general', 2 => 'products', 3 => 'children', 4 => 'medicine'];
        $items = DB::table('city_price_section_items')->get(['id', 'city_id', 'tab']);
        foreach ($items as $item) {
            $num = (int) ($item->tab ?? 0);
            $code = $tabCodeByNum[$num] ?? null;
            if ($code === null) {
                continue;
            }
            $tabRow = DB::table('city_price_section_tabs')
                ->where('city_id', (int) $item->city_id)
                ->where('code', $code)
                ->first(['id']);
            if ($tabRow === null) {
                continue;
            }
            DB::table('city_price_section_items')
                ->where('id', (int) $item->id)
                ->update(['tab_id' => (int) $tabRow->id]);
        }

        // Make tab_id required, then drop old tab column + old indexes.
        Schema::table('city_price_section_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('tab_id')->nullable(false)->change();

            $table->dropUnique('city_price_section_items_unique');
            $table->dropIndex('city_price_section_items_city_tab_sort_idx');

            $table->dropColumn('tab');

            $table->unique(['city_id', 'tab_id', 'position_id'], 'cps_items_city_tabid_pos_unique');
            $table->foreign('tab_id')->references('id')->on('city_price_section_tabs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Best-effort rollback (data loss possible for custom tabs).
        if (Schema::hasTable('city_price_section_items')) {
            Schema::table('city_price_section_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('city_price_section_items', 'tab')) {
                    $table->unsignedTinyInteger('tab')->default(1);
                }
            });

            // Map back only the 4 defaults by code.
            $codeToNum = ['general' => 1, 'products' => 2, 'children' => 3, 'medicine' => 4];
            $tabs = DB::table('city_price_section_tabs')->get(['id', 'city_id', 'code']);
            $numByTabId = [];
            foreach ($tabs as $t) {
                $code = (string) ($t->code ?? '');
                if (isset($codeToNum[$code])) {
                    $numByTabId[(int) $t->id] = $codeToNum[$code];
                }
            }
            $items = DB::table('city_price_section_items')->get(['id', 'tab_id']);
            foreach ($items as $it) {
                $num = $numByTabId[(int) $it->tab_id] ?? 1;
                DB::table('city_price_section_items')->where('id', (int) $it->id)->update(['tab' => $num]);
            }

            Schema::table('city_price_section_items', function (Blueprint $table): void {
                if (Schema::hasColumn('city_price_section_items', 'tab_id')) {
                    $table->dropForeign(['tab_id']);
                    $table->dropUnique('cps_items_city_tabid_pos_unique');
                    $table->dropIndex('cps_items_city_tabid_sort_idx');
                    $table->dropColumn('tab_id');
                }
            });
        }

        Schema::dropIfExists('city_price_section_tabs');
    }
};

