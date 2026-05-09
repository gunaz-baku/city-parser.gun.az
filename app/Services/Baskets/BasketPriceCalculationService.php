<?php

namespace App\Services\Baskets;

use App\Models\BasketDefinition;
use App\Models\BasketItem;
use App\Models\PricePosition;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\BasketPriceCalculationError;

final class BasketPriceCalculationService
{
    private ?bool $unitsHasCodeColumn = null;

    public function __construct(
        private readonly UnitConversionService $units,
        private readonly RawPayloadPricingResolver $resolver,
    ) {}

    /**
     * @return array{line_total: ?float, unit_price_per_base: ?float, currency: string}
     */
    public function calculateLine(BasketItem $item): ?float
    {
        $position = $item->position;

        if (! $position) {
            return null;
        }

        $pricePerBase = $this->resolver->getLatestPricePerBase($position);

        if ($pricePerBase === null) {
            $this->logError(
                'PRICE_NOT_FOUND',
                'No price per base found for position',
                $item,
                [
                    'position_id' => $position->id,
                    'qty' => $item->qty,
                ],
                null
            );

            return null;
        }

        return $pricePerBase * (float) $item->qty;
    }

    /**
     * @return list<float>
     */
    public function lineTotalsForItems(iterable $items, ?int $parserRunId, ?string $resultDate): array
    {
        unset($parserRunId, $resultDate);
        $out = [];
        foreach ($items as $item) {
            if (! $item instanceof BasketItem) {
                continue;
            }
            $line = $this->calculateLine($item);
            if ($line !== null) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * Səbət üzrə şəhər diliminin cəmi. Əvvəl bütün sətirlər tam olmalı idi; indi **ən azı bir** sətir
     * qiymətlənəndə cəm qaytarılır (əksik ingredientlər sıfırlanır, qalanlar toplanır).
     */
    public function sumBasket(BasketDefinition $basket): float
    {
        $total = 0;

        foreach ($basket->basketItems as $item) {
            $total += $this->calculateLine($item) ?? 0;
        }

        return round($total, 4);
    }

    private function calculateCountLine(
        BasketItem $item,
        PricePosition $position,
        Unit $positionUnit,
        Unit $basketUnit,
        ?int $parserRunId,
        ?string $resultDate,
    ): ?float {
        if ($this->unitIdentity($positionUnit) !== $this->unitIdentity($basketUnit)) {
            return null;
        }

        $unitSize = $position->unit_size;
        if ($unitSize === null || (float) $unitSize <= 0) {
            return null;
        }

        $avgPkg = $this->averageCatalogPackagePrice($position, $parserRunId, $resultDate);
        if ($avgPkg === null) {
            return null;
        }

        return $avgPkg * ((float) $item->qty / (float) $unitSize);
    }

    /**
     * Əvvəlcə {@see RawPayloadPricingResolver::averageCatalogPriceFromSnapshot} (orta snapshot),
     * yoxdursa mənbə sətirlərindən orta xam qiymət.
     */
    private function averageCatalogPackagePrice(PricePosition $position, ?int $parserRunId, ?string $resultDate): ?float
    {
        if ($parserRunId !== null && $resultDate !== null) {
            $fromSnap = $this->resolver->averageCatalogPriceFromSnapshot($position, $parserRunId, $resultDate);
            if ($fromSnap !== null) {
                return $fromSnap;
            }
        }

        return $this->averageRawPrice($position, $parserRunId, $resultDate);
    }

    private function averageRawPrice(PricePosition $position, ?int $parserRunId, ?string $resultDate): ?float
    {
        $q = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->where('is_valid', true)
            ->where(function ($query): void {
                $query->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            });

        if ($parserRunId !== null && $resultDate !== null) {
            $q->where('parser_run_id', $parserRunId)->whereDate('result_date', $resultDate);
        } else {
            $maxDate = DB::table('source_price_results')
                ->where('position_id', $position->id)
                ->where('is_valid', true)
                ->where(function ($query): void {
                    $query->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
                })
                ->max('result_date');
            if ($maxDate === null) {
                return null;
            }
            $q->whereDate('result_date', (string) $maxDate);
        }

        $rows = $q->get(['raw_price', 'normalized_price']);
        $values = [];
        foreach ($rows as $row) {
            $p = $row->raw_price ?? $row->normalized_price;
            if ($p !== null && $p !== '' && (float) $p >= 0) {
                $values[] = (float) $p;
            }
        }

        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }
    private function logError(
        string $type,
        string $message,
        ?BasketItem $item = null,
        array $context = [],
        ?int $runId = null
    ): void {
        BasketPriceCalculationError::create([
            'basket_id' => $item?->basket_id,
            'basket_item_id' => $item?->id,
            'position_id' => $item?->position_id,
            'error_type' => $type,
            'message' => $message,
            'context' => $context,
            'calculation_run_id' => $runId,
        ]);
    }

    /**
     * Köhnə sətirlərdə {@see BasketItem::$unit_id} boş olanda: qty_unit → Unit, yoxdursa çəki/həcm üçün bazis (g/ml).
     */
    private function inferBasketUnitForItem(BasketItem $item, PricePosition $position): ?Unit
    {
        $positionUnit = $position->relationLoaded('measurementUnit')
            ? $position->getRelation('measurementUnit')
            : $position->measurementUnit()->first();
        if (! $positionUnit instanceof Unit) {
            return null;
        }

        $token = strtolower(trim((string) ($item->qty_unit ?? '')));
        if ($token !== '') {
            $unitQuery = Unit::query()
                ->where('is_active', true)
                ->where(function ($q) use ($token): void {
                    $q->whereRaw('LOWER(short_name) = ?', [$token])
                        ->orWhereRaw('LOWER(name) = ?', [$token]);
                    if ($this->unitsHasCodeColumn()) {
                        $q->orWhereRaw('LOWER(code) = ?', [$token]);
                    }
                });
            $byToken = $unitQuery->first();
            if ($byToken instanceof Unit && $this->units->sameFamily($byToken, $positionUnit)) {
                return $byToken;
            }
        }

        $family = trim((string) $positionUnit->unit_type);

        return match ($family) {
            'weight' => $this->resolveBaseUnitByFamilyAndToken('weight', 'g'),
            'volume' => $this->resolveBaseUnitByFamilyAndToken('volume', 'ml'),
            default => null,
        };
    }

    private function resolveBaseUnitByFamilyAndToken(string $family, string $token): ?Unit
    {
        return Unit::query()
            ->where('is_active', true)
            ->where('unit_type', $family)
            ->where(function ($q) use ($token): void {
                $q->whereRaw('LOWER(short_name) = ?', [$token])
                    ->orWhereRaw('LOWER(name) = ?', [$token]);
                if ($this->unitsHasCodeColumn()) {
                    $q->orWhereRaw('LOWER(code) = ?', [$token]);
                }
            })
            ->first();
    }

    private function unitIdentity(Unit $unit): string
    {
        if ($this->unitsHasCodeColumn()) {
            $code = trim((string) ($unit->code ?? ''));
            if ($code !== '') {
                return mb_strtolower($code);
            }
        }

        $shortName = trim((string) ($unit->short_name ?? ''));
        if ($shortName !== '') {
            return mb_strtolower($shortName);
        }

        $name = trim((string) ($unit->name ?? ''));
        if ($name !== '') {
            return mb_strtolower($name);
        }

        return '#'.(string) $unit->id;
    }

    private function unitsHasCodeColumn(): bool
    {
        return $this->unitsHasCodeColumn ??= Schema::hasColumn('units', 'code');
    }
}
