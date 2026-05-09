<?php

namespace App\Services\Baskets;

use App\Models\PricePosition;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qiymət: əvvəlcə `price_snapshots.price_avg` (günlük aggregate orta) → kataloq vahidi üzrə bazis qiymət;
 * snapshot yoxdursa `source_price_results` üzrə orta mənbə qiymətlərindən hesablanır.
 *
 * Semantics: {@see PricePosition::$unit_size} together with {@see PricePosition::$unit_id} describe the quantity
 * that each source {@code raw_price} refers to (operator-maintained catalog alignment).
 */
final class RawPayloadPricingResolver
{
    public function __construct(private readonly UnitConversionService $units) {}

    private array $priceCache = [];

    /**
     * Orta kataloq qiyməti (price_snapshots.price_avg) — mövqe + tarix; eyni günlük unikal snapshot.
     */
    public function averageCatalogPriceFromSnapshot(
        PricePosition $position,
        int $parserRunId,
        string $resultDate,
    ): ?float {
        if (! Schema::hasTable('price_snapshots')) {
            return null;
        }

        $q = DB::table('price_snapshots')
            ->where('position_id', $position->id)
            ->whereDate('snapshot_date', $resultDate);

        $avg = $q->clone()->where('parser_run_id', $parserRunId)->value('price_avg');
        if ($avg !== null && $avg !== '' && (float) $avg > 0) {
            return (float) $avg;
        }

        $avg = $q->value('price_avg');

        return $avg !== null && $avg !== '' && (float) $avg > 0 ? (float) $avg : null;
    }

    /**
     * Son mövcud gün üçün orta kataloq qiyməti (normalized mənbələr üzrə aggregate snapshot).
     */
    public function latestAverageCatalogPriceFromSnapshot(PricePosition $position): ?float
    {
        if (! Schema::hasTable('price_snapshots')) {
            return null;
        }

        $avg = DB::table('price_snapshots')
            ->where('position_id', $position->id)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->value('price_avg');

        return $avg !== null && $avg !== '' && (float) $avg > 0 ? (float) $avg : null;
    }

    /**
     * @return ?float Average price per canonical base unit (g, ml, or count) for the scoped parser run + calendar day
     */
    public function averagePricePerBaseForPositionRunDay(
        PricePosition $position,
        int $parserRunId,
        string $resultDate,
    ): ?float {
        $positionUnit = $position->relationLoaded('measurementUnit')
            ? $position->getRelation('measurementUnit')
            : $position->measurementUnit()->first();
        if (! $positionUnit instanceof Unit) {
            return null;
        }

        $unitSize = $position->unit_size;
        if ($unitSize === null || (float) $unitSize <= 0) {
            return null;
        }

        $snapshotAvg = $this->averageCatalogPriceFromSnapshot($position, $parserRunId, $resultDate);
        if ($snapshotAvg !== null && $snapshotAvg > 0) {
            $pb = $this->units->pricePerBaseFromReferencePrice($snapshotAvg, (float) $unitSize, $positionUnit);
            if ($pb !== null && $pb > 0) {
                return $pb;
            }
        }

        if (mb_strtolower(trim((string) $position->parser_type)) === 'bina') {
            return null;
        }

        $rows = DB::table('source_price_results')
            ->where('parser_run_id', $parserRunId)
            ->where('position_id', $position->id)
            ->whereDate('result_date', $resultDate)
            ->where('is_valid', true)
            ->where(function ($q): void {
                $q->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            })
            ->get(['raw_price', 'normalized_price']);

        if ($rows->isEmpty()) {
            return null;
        }

        $perBaseValues = [];
        foreach ($rows as $row) {
            $p = self::effectiveSourcePrice($row->raw_price ?? null, $row->normalized_price ?? null);
            if ($p === null || $p < 0) {
                continue;
            }

            $pb = $this->units->pricePerBaseFromReferencePrice($p, (float) $unitSize, $positionUnit);
            if ($pb !== null && $pb >= 0) {
                $perBaseValues[] = $pb;
            }
        }

        if ($perBaseValues === []) {
            return null;
        }

        return array_sum($perBaseValues) / count($perBaseValues);
    }

    /**
     * Latest calendar day (≤ today in DB) with any valid raw_price for this position; not limited to a parser run.
     *
     * @return array{0: ?string, 1: ?float} [date Y-m-d, average price per base]
     */
    public function latestDayAveragePricePerBase(PricePosition $position): array
    {
        $positionUnit = $position->relationLoaded('measurementUnit')
            ? $position->getRelation('measurementUnit')
            : $position->measurementUnit()->first();
        if (! $positionUnit instanceof Unit) {
            return [null, null];
        }

        $unitSize = $position->unit_size;
        if ($unitSize === null || (float) $unitSize <= 0) {
            return [null, null];
        }

        if (Schema::hasTable('price_snapshots')) {
            $snap = DB::table('price_snapshots')
                ->where('position_id', $position->id)
                ->orderByDesc('snapshot_date')
                ->orderByDesc('id')
                ->first(['snapshot_date', 'price_avg']);
            if ($snap !== null) {
                $pkgAvg = (float) $snap->price_avg;
                if ($pkgAvg > 0) {
                    $pb = $this->units->pricePerBaseFromReferencePrice($pkgAvg, (float) $unitSize, $positionUnit);
                    if ($pb !== null && $pb >= 0) {
                        return [(string) $snap->snapshot_date, $pb];
                    }
                }
            }
        }

        if (mb_strtolower(trim((string) $position->parser_type)) === 'bina') {
            return [null, null];
        }

        $maxDate = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->where('is_valid', true)
            ->where(function ($q): void {
                $q->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            })
            ->max('result_date');

        if ($maxDate === null) {
            return [null, null];
        }

        $dateStr = (string) $maxDate;

        $rows = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->whereDate('result_date', $dateStr)
            ->where('is_valid', true)
            ->where(function ($q): void {
                $q->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            })
            ->get(['raw_price', 'normalized_price']);

        $perBaseValues = [];
        foreach ($rows as $row) {
            $p = self::effectiveSourcePrice($row->raw_price ?? null, $row->normalized_price ?? null);
            if ($p === null || $p < 0) {
                continue;
            }
            $pb = $this->units->pricePerBaseFromReferencePrice($p, (float) $unitSize, $positionUnit);
            if ($pb !== null && $pb >= 0) {
                $perBaseValues[] = $pb;
            }
        }

        if ($perBaseValues === []) {
            return [$dateStr, null];
        }

        return [$dateStr, array_sum($perBaseValues) / count($perBaseValues)];
    }

    /**
     * Mean {@code raw_price} across all valid sources on the latest day that has data (any parser run).
     */
    public function latestAverageRawPrice(PricePosition $position): ?float
    {
        if (mb_strtolower(trim((string) $position->parser_type)) === 'bina') {
            return null;
        }

        $maxDate = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->where('is_valid', true)
            ->where(function ($q): void {
                $q->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            })
            ->max('result_date');

        if ($maxDate === null) {
            return null;
        }

        $prices = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->whereDate('result_date', (string) $maxDate)
            ->where('is_valid', true)
            ->where(function ($q): void {
                $q->whereNotNull('raw_price')->orWhereNotNull('normalized_price');
            })
            ->get(['raw_price', 'normalized_price']);

        $values = [];
        foreach ($prices as $row) {
            $p = self::effectiveSourcePrice($row->raw_price ?? null, $row->normalized_price ?? null);
            if ($p !== null && $p >= 0) {
                $values[] = $p;
            }
        }

        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  float|string|null  $raw
     * @param  float|string|null  $normalized
     */
    private static function effectiveSourcePrice(mixed $raw, mixed $normalized): ?float
    {
        if ($raw !== null && $raw !== '') {
            $f = (float) $raw;

            return $f >= 0 ? $f : null;
        }
        if ($normalized !== null && $normalized !== '') {
            $f = (float) $normalized;

            return $f >= 0 ? $f : null;
        }

        return null;
    }

    public function getLatestPrice(PricePosition $position): ?float
    {
        $key = $position->id;

        if (isset($this->priceCache[$key])) {
            return $this->priceCache[$key];
        }

        $row = DB::table('source_price_results')
            ->where('position_id', $position->id)
            ->where('is_valid', true)
            ->orderByDesc('result_date')
            ->first();

        if (! $row) {
            return null;
        }

        $price = $row->normalized_price ?? $row->raw_price;

        return $this->priceCache[$key] = $price ? (float) $price : null;
    }

    public function getLatestPricePerBase(PricePosition $position): ?float
    {
        if (mb_strtolower(trim((string) $position->parser_type)) === 'bina') {
            return null;
        }

        $positionUnit = $position->relationLoaded('measurementUnit')
            ? $position->getRelation('measurementUnit')
            : $position->measurementUnit()->first();

        if (! $positionUnit instanceof Unit) {
            return null;
        }

        $unitSize = $position->unit_size;
        if ($unitSize === null || (float) $unitSize <= 0) {
            return null;
        }

        $snap = DB::table('price_snapshots')
            ->where('position_id', $position->id)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first(['price_avg']);

        if (! $snap || (float) $snap->price_avg <= 0) {
            return null;
        }

        return $this->units->pricePerBaseFromReferencePrice(
            (float) $snap->price_avg,
            (float) $unitSize,
            $positionUnit
        );
    }
}
