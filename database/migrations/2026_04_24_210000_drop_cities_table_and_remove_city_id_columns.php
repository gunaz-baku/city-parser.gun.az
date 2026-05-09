<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cities')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->mergeDuplicateCityPriceSectionTabs();
            $this->dedupeBasketSnapshotsByBasketAndDate();

            $this->safeDropForeign('city_price_section_items', 'city_id');
            $this->safeDropForeign('city_price_section_tabs', 'city_id');
            $this->safeDropForeign('price_positions', 'city_id');
            $this->safeDropForeign('parser_runs', 'city_id');
            $this->safeDropForeign('price_snapshots', 'city_id');
            $this->safeDropForeign('basket_snapshots', 'city_id');

            $this->reshapeCityPriceSectionItems();
            $this->reshapeCityPriceSectionTabs();

            $this->reshapePricePositions();
            $this->reshapeParserRuns();
            $this->reshapePriceSnapshots();
            $this->reshapeBasketSnapshots();

            Schema::dropIfExists('cities');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // İntentionally not reversible (data model simplified).
    }

    private function mergeDuplicateCityPriceSectionTabs(): void
    {
        if (! Schema::hasTable('city_price_section_tabs') || ! Schema::hasTable('city_price_section_items')) {
            return;
        }

        if (Schema::hasColumn('city_price_section_tabs', 'code')) {
            $groups = DB::table('city_price_section_tabs')
                ->selectRaw('code as g')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('g');

            foreach ($groups as $code) {
                $code = (string) $code;
                if ($code === '') {
                    continue;
                }
                $ids = DB::table('city_price_section_tabs')->where('code', $code)->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
                if (count($ids) < 2) {
                    continue;
                }
                $keep = $ids[0];
                foreach (array_slice($ids, 1) as $dropId) {
                    DB::table('city_price_section_items')->where('tab_id', $dropId)->update(['tab_id' => $keep]);
                    DB::table('city_price_section_tabs')->where('id', $dropId)->delete();
                }
            }

            return;
        }

        if (! Schema::hasColumn('city_price_section_tabs', 'route_slug')) {
            return;
        }

        $groups = DB::table('city_price_section_tabs')
            ->selectRaw('LOWER(route_slug) as g')
            ->whereNotNull('route_slug')
            ->groupBy(DB::raw('LOWER(route_slug)'))
            ->havingRaw('COUNT(*) > 1')
            ->pluck('g');

        foreach ($groups as $slugKey) {
            $slugKey = (string) $slugKey;
            if ($slugKey === '') {
                continue;
            }
            $ids = DB::table('city_price_section_tabs')
                ->whereRaw('LOWER(route_slug) = ?', [$slugKey])
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if (count($ids) < 2) {
                continue;
            }
            $keep = $ids[0];
            foreach (array_slice($ids, 1) as $dropId) {
                DB::table('city_price_section_items')->where('tab_id', $dropId)->update(['tab_id' => $keep]);
                DB::table('city_price_section_tabs')->where('id', $dropId)->delete();
            }
        }
    }

    private function dedupeBasketSnapshotsByBasketAndDate(): void
    {
        if (! Schema::hasTable('basket_snapshots') || ! Schema::hasColumn('basket_snapshots', 'city_id')) {
            return;
        }

        $dupGroups = DB::table('basket_snapshots')
            ->selectRaw('basket_id, snapshot_date, MIN(id) as keep_id, COUNT(*) as c')
            ->groupBy('basket_id', 'snapshot_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupGroups as $g) {
            DB::table('basket_snapshots')
                ->where('basket_id', $g->basket_id)
                ->whereDate('snapshot_date', $g->snapshot_date)
                ->where('id', '!=', (int) $g->keep_id)
                ->delete();
        }
    }

    private function reshapeCityPriceSectionItems(): void
    {
        if (! Schema::hasTable('city_price_section_items')) {
            return;
        }

        $this->trySchemaTable('city_price_section_items', function (Blueprint $table): void {
            $table->dropUnique('city_price_section_items_unique');
        });
        $this->trySchemaTable('city_price_section_items', function (Blueprint $table): void {
            $table->dropUnique('cps_items_city_tabid_pos_unique');
        });
        $this->trySchemaTable('city_price_section_items', function (Blueprint $table): void {
            $table->dropIndex('city_price_section_items_city_tab_sort_idx');
        });
        $this->trySchemaTable('city_price_section_items', function (Blueprint $table): void {
            $table->dropIndex('cps_items_city_tabid_sort_idx');
        });

        if (Schema::hasColumn('city_price_section_items', 'city_id')) {
            Schema::table('city_price_section_items', function (Blueprint $table): void {
                $table->dropColumn('city_id');
            });
        }

        Schema::table('city_price_section_items', function (Blueprint $table): void {
            if (! $this->hasMysqlUnique('city_price_section_items', 'cps_items_tab_pos_unique')) {
                $table->unique(['tab_id', 'position_id'], 'cps_items_tab_pos_unique');
            }
        });
    }

    private function reshapeCityPriceSectionTabs(): void
    {
        if (! Schema::hasTable('city_price_section_tabs')) {
            return;
        }

        $this->trySchemaTable('city_price_section_tabs', function (Blueprint $table): void {
            $table->dropUnique('cps_tabs_city_code_unique');
        });
        $this->trySchemaTable('city_price_section_tabs', function (Blueprint $table): void {
            $table->dropIndex('cps_tabs_city_sort_idx');
        });

        if (Schema::hasColumn('city_price_section_tabs', 'city_id')) {
            Schema::table('city_price_section_tabs', function (Blueprint $table): void {
                $table->dropColumn('city_id');
            });
        }

        Schema::table('city_price_section_tabs', function (Blueprint $table): void {
            if (Schema::hasColumn('city_price_section_tabs', 'route_slug') && ! $this->hasMysqlUnique('city_price_section_tabs', 'cps_tabs_route_slug_unique')) {
                $table->unique('route_slug', 'cps_tabs_route_slug_unique');
            }
        });
    }

    private function reshapePricePositions(): void
    {
        if (! Schema::hasTable('price_positions') || ! Schema::hasColumn('price_positions', 'city_id')) {
            return;
        }

        $dupSlugs = DB::table('price_positions')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');
        if ($dupSlugs->isNotEmpty()) {
            throw new \RuntimeException(
                'price_positions: eyni slug ilə bir neçə sətir var; city_id silinməzdən əvvəl slug unikallığını düzəldin: '
                .$dupSlugs->take(10)->implode(', ')
            );
        }

        $this->trySchemaTable('price_positions', function (Blueprint $table): void {
            $table->dropUnique('price_positions_city_id_slug_unique');
        });

        Schema::table('price_positions', function (Blueprint $table): void {
            $table->dropColumn('city_id');
        });

        Schema::table('price_positions', function (Blueprint $table): void {
            if (! $this->hasMysqlUnique('price_positions', 'price_positions_slug_unique')) {
                $table->unique('slug', 'price_positions_slug_unique');
            }
        });
    }

    private function reshapeParserRuns(): void
    {
        if (! Schema::hasTable('parser_runs') || ! Schema::hasColumn('parser_runs', 'city_id')) {
            return;
        }

        $this->trySchemaTable('parser_runs', function (Blueprint $table): void {
            $table->dropIndex('parser_runs_city_id_started_at_index');
        });

        Schema::table('parser_runs', function (Blueprint $table): void {
            $table->dropColumn('city_id');
        });
    }

    private function reshapePriceSnapshots(): void
    {
        if (! Schema::hasTable('price_snapshots') || ! Schema::hasColumn('price_snapshots', 'city_id')) {
            return;
        }

        $this->trySchemaTable('price_snapshots', function (Blueprint $table): void {
            $table->dropIndex('price_snapshots_city_id_snapshot_date_index');
        });

        Schema::table('price_snapshots', function (Blueprint $table): void {
            $table->dropColumn('city_id');
        });
    }

    private function reshapeBasketSnapshots(): void
    {
        if (! Schema::hasTable('basket_snapshots') || ! Schema::hasColumn('basket_snapshots', 'city_id')) {
            return;
        }

        $this->trySchemaTable('basket_snapshots', function (Blueprint $table): void {
            $table->dropUnique('basket_snapshots_basket_id_city_id_snapshot_date_unique');
        });
        $this->trySchemaTable('basket_snapshots', function (Blueprint $table): void {
            $table->dropIndex('basket_snapshots_city_id_snapshot_date_index');
        });

        Schema::table('basket_snapshots', function (Blueprint $table): void {
            $table->dropColumn('city_id');
        });

        Schema::table('basket_snapshots', function (Blueprint $table): void {
            if (! $this->hasMysqlUnique('basket_snapshots', 'basket_snapshots_basket_id_snapshot_date_unique')) {
                $table->unique(['basket_id', 'snapshot_date'], 'basket_snapshots_basket_id_snapshot_date_unique');
            }
        });
    }

    /**
     * @param  callable(Blueprint): void  $callback
     */
    private function trySchemaTable(string $table, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            //
        }
    }

    private function safeDropForeign(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($column): void {
                $t->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // FK adı fərqli ola bilər — əl ilə silinmişdirsə davam et.
        }
    }

    private function hasMysqlUnique(string $table, string $indexName): bool
    {
        $db = Schema::getConnection()->getDatabaseName();

        $n = (int) DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? AND non_unique = 0',
            [$db, $table, $indexName]
        )?->c ?? 0;

        return $n > 0;
    }
};
