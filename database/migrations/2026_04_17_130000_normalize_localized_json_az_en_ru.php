<?php

use App\Support\LocalizedJson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->patchTable('cities', ['name']);
        $this->patchTable('price_categories', ['name']);
        $this->patchTable('price_positions', ['name', 'unit', 'meta_title', 'meta_description', 'seo_text']);
        if (Schema::hasTable('basket_definitions')) {
            $this->patchTable('basket_definitions', ['name']);
        }
    }

    public function down(): void
    {
        // Məlumat itkisi riski: geri qaytarma tətbiq olunmur.
    }

    /**
     * @param  list<string>  $columns
     */
    private function patchTable(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->select(array_merge(['id'], $columns))->get();
        foreach ($rows as $row) {
            $updates = [];
            foreach ($columns as $col) {
                if (! property_exists($row, $col)) {
                    continue;
                }
                $raw = $row->{$col};
                if ($raw === null || $raw === '') {
                    continue;
                }
                $decoded = json_decode((string) $raw, true);
                if (! is_array($decoded)) {
                    continue;
                }
                if ($col === 'unit') {
                    $normalized = LocalizedJson::normalizeUnit($decoded);
                } elseif (in_array($col, ['name', 'meta_title', 'meta_description', 'seo_text'], true)) {
                    $normalized = LocalizedJson::normalizeFlatName($decoded);
                } else {
                    continue;
                }
                if ($normalized === null) {
                    continue;
                }
                $updates[$col] = json_encode($normalized, JSON_UNESCAPED_UNICODE);
            }
            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }
        }
    }
};
