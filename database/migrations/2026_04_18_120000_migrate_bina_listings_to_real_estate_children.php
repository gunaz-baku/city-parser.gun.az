<?php

use App\Support\PriceCategoryHierarchy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Köhnə bina_az_listings kateqoriyasındakı mövqeləri real_estate_sale / real_estate_rent altına köçürür.
 */
return new class extends Migration
{
    public function up(): void
    {
        PriceCategoryHierarchy::sync();

        $oldId = DB::table('price_categories')->where('code', 'bina_az_listings')->value('id');
        if ($oldId === null) {
            return;
        }

        $saleId = DB::table('price_categories')->where('code', 'real_estate_sale')->value('id');
        $rentId = DB::table('price_categories')->where('code', 'real_estate_rent')->value('id');
        if ($saleId === null || $rentId === null) {
            return;
        }

        $positions = DB::table('price_positions')
            ->where('category_id', $oldId)
            ->where('parser_type', 'bina')
            ->get(['id']);

        foreach ($positions as $p) {
            $posId = (int) $p->id;
            $src = DB::table('price_sources')
                ->where('position_id', $posId)
                ->where('source_type', 'bina')
                ->orderBy('id')
                ->first();

            $mode = 'sale';
            if ($src !== null && $src->source_config) {
                $cfg = json_decode((string) $src->source_config, true);
                if (is_array($cfg) && (($cfg['mode'] ?? 'sale') === 'rent')) {
                    $mode = 'rent';
                }
            }

            DB::table('price_positions')->where('id', $posId)->update([
                'category_id' => $mode === 'rent' ? (int) $rentId : (int) $saleId,
                'updated_at' => now(),
            ]);
        }

        DB::table('price_categories')->where('id', $oldId)->delete();
    }

    public function down(): void
    {
        // Geri qaytarılmır (köhnə bina_az_listings bərpa olunmur).
    }
};
