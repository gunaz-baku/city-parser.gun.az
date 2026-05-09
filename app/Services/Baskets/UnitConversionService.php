<?php

namespace App\Services\Baskets;

use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Converts physical quantities within the same {@see Unit::$unit_type} family using the `units` table chain.
 */
final class UnitConversionService
{
    /** @var Collection<string, Unit>|null */
    private ?Collection $unitsByCode = null;

    private array $unitCache = [];

    /**
     * Amount expressed in the canonical base of the unit family (g for weight, ml for volume, or raw count).
     */
    public function toBaseAmount(float $quantity, Unit $unit): ?float
    {
        if ($quantity < 0) {
            return null;
        }

        $byCode = $this->unitsByCode();
        $current = $unit;
        $amount = $quantity;

        for ($guard = 0; $guard < 8; $guard++) {
            $baseCode = $current->base_unit !== null ? trim((string) $current->base_unit) : '';
            if ($baseCode === '' || $current->multiplier === null) {
                return $amount;
            }

            $amount *= (float) $current->multiplier;
            $next = $byCode->get($baseCode);
            if (! $next instanceof Unit) {
                return null;
            }
            $current = $next;
        }

        return null;
    }

    public function sameFamily(?Unit $a, ?Unit $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return $a->unit_type === $b->unit_type;
    }

    /**
     * @return ?float Price for one base increment (1 g, 1 ml, or 1 count unit) in currency of raw_price
     */
    public function pricePerBaseFromReferencePrice(
        float $rawPriceForReference,
        float $referenceQuantity,
        Unit $referenceUnit,
    ): ?float {
        if ($rawPriceForReference < 0 || $referenceQuantity <= 0) {
            return null;
        }

        $refBase = $this->toBaseAmount($referenceQuantity, $referenceUnit);
        if ($refBase === null || $refBase <= 0) {
            return null;
        }

        return $rawPriceForReference / $refBase;
    }

    /**
     * @return ?float Line total in currency of raw_price inputs
     */
    public function lineTotalFromPricePerBase(
        float $pricePerBase,
        float $basketQuantity,
        Unit $basketUnit,
        Unit $positionUnit,
    ): ?float {
        if ($pricePerBase < 0) {
            return null;
        }

        if (! $this->sameFamily($basketUnit, $positionUnit)) {
            return null;
        }

        $basketBase = $this->toBaseAmount($basketQuantity, $basketUnit);
        if ($basketBase === null) {
            return null;
        }

        return $basketBase * $pricePerBase;
    }

    /**
     * For count units without a multiplier chain, pricing is only defined relative to the same unit code as the catalog.
     */
    public function lineTotalForCountSameUnitOnly(
        float $rawPriceForReference,
        float $referenceQuantity,
        Unit $referenceUnit,
        float $basketQuantity,
        Unit $basketUnit,
    ): ?float {
        if ($rawPriceForReference < 0 || $referenceQuantity <= 0 || $basketQuantity < 0) {
            return null;
        }

        if (trim((string) $referenceUnit->code) !== trim((string) $basketUnit->code)) {
            return null;
        }

        return $rawPriceForReference * ($basketQuantity / $referenceQuantity);
    }

    public function cacheUnit(Unit $unit): Unit
    {
        return $this->unitCache[$unit->id] ??= $unit;
    }

    /**
     * @return Collection<string, Unit>
     */
    private function unitsByCode(): Collection
    {
        return $this->unitsByCode ??= Unit::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (Unit $u): string => trim((string) $u->code));
    }
}
