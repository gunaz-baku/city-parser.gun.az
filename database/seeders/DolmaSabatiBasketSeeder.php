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
 * Mövqelər {@see NewFormattedCsvPricePositionsSeeder} importundan sonra mövcud `slug`-larla uyğunlaşır;
 * köhnə Gun.AZ CSV üçün `gun_az_*` slug-ları ehtiyat kimi yoxlanır.
 *
 *     php artisan db:seed --class=DolmaSabatiBasketSeeder
 */
class DolmaSabatiBasketSeeder extends Seeder
{
    /**
     * Üzüm yarpağı: CSV slug (Str::slug «Üzüm yarpağı» → uzum-yarpagi) və digər ehtiyatlar.
     *
     * @var list<string>
     */
    private const GRAPE_LEAF_SLUG_CANDIDATES = [
        'uzum-yarpagi',
        'uzum-yarpagi-500-q',
        'grape-leaves',
        'grape-leaf',
        'vine-leaves',
        'vine-leaf',
    ];

    /**
     * @var list<array{position_slugs: list<string>, qty: string, unit: 'g'|'ml', note: string}>
     */
    private const LINES = [
        [
            'position_slugs' => ['minced-beef-1kg', 'gun_az_43'],
            'qty' => '500',
            'unit' => 'g',
            'note' => 'Çəkilmiş mal əti (farş)',
        ],
        [
            'position_slugs' => ['rice-1kg', 'gun_az_22'],
            'qty' => '200',
            'unit' => 'g',
            'note' => 'Düyü',
        ],
        [
            'position_slugs' => ['onion-1kg', 'gun_az_67'],
            'qty' => '200',
            'unit' => 'g',
            'note' => 'Quru soğan',
        ],
        [
            'position_slugs' => ['tomato-1kg', 'gun_az_64'],
            'qty' => '300',
            'unit' => 'g',
            'note' => 'Pomidor',
        ],
        [
            'position_slugs' => ['sweet-pepper-500g', 'gun_az_69'],
            'qty' => '300',
            'unit' => 'g',
            'note' => 'Bolqar bibəri',
        ],
        [
            'position_slugs' => ['sunflower-oil-1l', 'sunflower-oil-1lt', 'gunebaxan-yagi', 'gun_az_59'],
            'qty' => '100',
            'unit' => 'ml',
            'note' => 'Günəbaxan yağı',
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasTable('basket_items') || ! Schema::hasTable('price_positions')) {
            $this->command?->warn('DolmaSabatiBasketSeeder: basket/position cədvəlləri yoxdur — atlanıldı.');

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
            ['name->az' => 'Dolma səbəti'],
            [
                'name' => [
                    'az' => 'Dolma səbəti',
                    'en' => 'Dolma basket',
                    'ru' => 'Корзина для долмы',
                ],
                'type' => BasketDefinition::TYPE_BASKET,
                'is_active' => true,
            ],
        );

        $basket->basketItems()->delete();

        foreach (self::LINES as $spec) {
            $positionId = $this->resolvePositionIdBySlugCandidates($spec['position_slugs']);
            if ($positionId === null) {
                $tried = implode(', ', $spec['position_slugs']);
                $this->command?->error("DolmaSabatiBasketSeeder: mövqe tapılmadı ({$tried}) — {$spec['note']}.");

                continue;
            }
            $this->createLine($basket->id, $positionId, $spec['qty'], $unitIds[$spec['unit']]);
        }

        $grapeLeafId = $this->resolveGrapeLeafPositionId();
        if ($grapeLeafId === null) {
            $this->command?->warn(
                'DolmaSabatiBasketSeeder: «Üzüm yarpağı» üçün mövqe tapılmadı (CSV + ad axtarışı). '.
                'Kataloqa əlavə edib seederi təkrar işə sala bilərsiniz.'
            );
        } else {
            $this->createLine($basket->id, $grapeLeafId, '200', $unitIds['g']);
        }

        AdminApiReferenceCache::forgetBasketDefinitionsIndex();

        $this->command?->info('DolmaSabatiBasketSeeder: Dolma səbəti hazırdır.');
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

    /**
     * @param  list<string>  $slugs
     */
    private function resolvePositionIdBySlugCandidates(array $slugs): ?int
    {
        foreach ($slugs as $slug) {
            $slug = trim($slug);
            if ($slug === '') {
                continue;
            }
            $id = DB::table('price_positions')->where('slug', $slug)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    private function resolveGrapeLeafPositionId(): ?int
    {
        $byExplicitSlug = $this->resolvePositionIdBySlugCandidates(self::GRAPE_LEAF_SLUG_CANDIDATES);
        if ($byExplicitSlug !== null) {
            return $byExplicitSlug;
        }

        // AZ: «yarpağı» (ğ) CSV-də tez-tez olur; köhnə «yarpaq» yazılışı da saxlanılır.
        $q = PricePosition::query()
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    $q2->where('name->az', 'like', '%yarpağ%')
                        ->orWhere('name->az', 'like', '%Yarpağ%')
                        ->orWhere('name->az', 'like', '%yarpaq%')
                        ->orWhere('name->az', 'like', '%Yarpaq%');
                })->where(function ($q3): void {
                    $q3->where('name->az', 'like', '%üzüm%')
                        ->orWhere('name->az', 'like', '%Üzüm%')
                        ->orWhere('name->az', 'like', '%uzum%')
                        ->orWhere('name->az', 'like', '%Uzum%');
                });
            })
            ->orWhere(function ($q): void {
                $q->where('name->ru', 'like', '%лист%виноград%')
                    ->orWhere('name->ru', 'like', '%виноград%лист%')
                    ->orWhere('name->ru', 'like', '%виноградн%лист%');
            })
            ->orWhere(function ($q): void {
                $q->where('name->en', 'like', '%grape%leaf%')
                    ->orWhere('name->en', 'like', '%vine%leaf%');
            });
        $byName = $q->orderBy('id')->value('id');

        if ($byName !== null) {
            return (int) $byName;
        }

        $q2 = DB::table('price_positions')
            ->where(function ($q): void {
                $q->where('slug', 'like', '%yarpag%')
                    ->orWhere('slug', 'like', '%yarpaq%')
                    ->orWhere('slug', 'like', '%grape-leaf%')
                    ->orWhere('slug', 'like', '%vine-leaf%')
                    ->orWhere('slug', 'like', '%uzum%yar%');
            });

        $bySlug = $q2->orderBy('id')->value('id');

        return $bySlug !== null ? (int) $bySlug : null;
    }
}
