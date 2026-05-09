<?php

namespace Database\Seeders;

use App\Http\Support\AdminApiReferenceCache;
use App\Models\BasketDefinition;
use App\Models\BasketItem;
use App\Models\PricePosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dolma səbəti: qiymə, düyü, soğan, pomidor, bolqar bibəri, günəbaxan yağı, üzüm yarpağı.
 *
 * Mövqelər {@see GunAzParsingStrategySeeder} CSV № ilə uyğunlaşır (kodlar gun_az_{№}).
 * «Üzüm yarpağı» hazırda əsas CSV-də olmaya bilər — o zaman mövqe adında «yarpaq» axtarılır;
 * tapılmasa xəbərdarlıq verilir və bu sətir atlanır.
 *
 *     php artisan db:seed --class=DolmaSabatiBasketSeeder
 */
class DolmaSabatiBasketSeeder extends Seeder
{
    public const BASKET_CODE = 'dolma_sabati';

    /**
     * Sətirlər: mövqe kodu (CSV №), səbətdə miqdar, vahid (g və ya ml).
     *
     * @var list<array{code: string, qty: string, unit: 'g'|'ml', note: string}>
     */
    private const LINES = [
        ['code' => 'gun_az_43', 'qty' => '500', 'unit' => 'g', 'note' => 'Çəkilmiş mal əti (farş)'],
        ['code' => 'gun_az_22', 'qty' => '200', 'unit' => 'g', 'note' => 'Düyü'],
        ['code' => 'gun_az_67', 'qty' => '200', 'unit' => 'g', 'note' => 'Quru soğan'],
        ['code' => 'gun_az_64', 'qty' => '300', 'unit' => 'g', 'note' => 'Pomidor'],
        ['code' => 'gun_az_69', 'qty' => '300', 'unit' => 'g', 'note' => 'Bolqar bibəri'],
        ['code' => 'gun_az_59', 'qty' => '100', 'unit' => 'ml', 'note' => 'Günəbaxan yağı'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasTable('basket_items') || ! Schema::hasTable('price_positions')) {
            $this->command?->warn('DolmaSabatiBasketSeeder: basket/position cədvəlləri yoxdur — atlanıldı.');

            return;
        }

        $cityId = (int) (DB::table('cities')->where('code', 'baku')->value('id') ?? 0);
        if ($cityId < 1) {
            $this->command?->error('DolmaSabatiBasketSeeder: Bakı şəhəri tapılmadı.');

            return;
        }

        $unitIds = [
            'g' => DB::table('units')->where('code', 'g')->value('id'),
            'ml' => DB::table('units')->where('code', 'ml')->value('id'),
        ];
        foreach (['g', 'ml'] as $u) {
            if ($unitIds[$u] === null) {
                $this->command?->error("DolmaSabatiBasketSeeder: units.cədvəlində '{$u}' yoxdur — əvvəl vahidlər seed olunmalıdır.");

                return;
            }
            $unitIds[$u] = (int) $unitIds[$u];
        }

        $basket = BasketDefinition::query()->updateOrCreate(
            ['code' => self::BASKET_CODE],
            [
                'name' => [
                    'az' => 'Dolma səbəti',
                    'en' => 'Dolma basket',
                    'ru' => 'Корзина для долмы',
                ],
                'is_active' => true,
            ],
        );

        $basket->basketItems()->delete();

        foreach (self::LINES as $spec) {
            $positionId = $this->resolvePositionIdByCode($cityId, $spec['code']);
            if ($positionId === null) {
                $this->command?->error("DolmaSabatiBasketSeeder: mövqe tapılmadı: {$spec['code']} ({$spec['note']}).");

                continue;
            }
            $this->createLine($basket->id, $positionId, $spec['qty'], $unitIds[$spec['unit']]);
        }

        $grapeLeafId = $this->resolveGrapeLeafPositionId($cityId);
        if ($grapeLeafId === null) {
            $this->command?->warn(
                'DolmaSabatiBasketSeeder: «Üzüm yarpağı» üçün mövqe tapılmadı (CSV + ad axtarışı). '.
                'Kataloqa əlavə edib seederi təkrar işə sala bilərsiniz.'
            );
        } else {
            $this->createLine($basket->id, $grapeLeafId, '200', $unitIds['g']);
        }

        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $this->command?->info('DolmaSabatiBasketSeeder: səbət «'.self::BASKET_CODE.'» hazırdır.');
    }

    private function createLine(int $basketId, int $positionId, string $qty, int $unitId): void
    {
        BasketItem::query()->create([
            'basket_id' => $basketId,
            'position_id' => $positionId,
            'qty' => $qty,
            'unit_id' => $unitId,
            'qty_unit' => '',
        ]);
    }

    private function resolvePositionIdByCode(int $cityId, string $code): ?int
    {
        $id = DB::table('price_positions')
            ->where('city_id', $cityId)
            ->where('code', $code)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function resolveGrapeLeafPositionId(int $cityId): ?int
    {
        $byName = PricePosition::query()
            ->where('city_id', $cityId)
            ->where(function ($q): void {
                $q->where('name->az', 'like', '%yarpaq%')
                    ->orWhere('name->az', 'like', '%Yarpaq%')
                    ->orWhere('name->ru', 'like', '%лист%виноград%')
                    ->orWhere('name->ru', 'like', '%виноград%лист%')
                    ->orWhere('name->en', 'like', '%grape%leaf%');
            })
            ->orderBy('id')
            ->value('id');

        if ($byName !== null) {
            return (int) $byName;
        }

        $byCode = DB::table('price_positions')
            ->where('city_id', $cityId)
            ->where('code', 'like', '%yarpaq%')
            ->orderBy('id')
            ->value('id');

        return $byCode !== null ? (int) $byCode : null;
    }
}
