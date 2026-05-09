<?php

namespace App\Actions;

use App\Models\BasketDefinition;
use App\Services\Baskets\BasketPriceCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Yazır: aktiv səbətlər üçün {@see BasketSnapshot} cəmləri (bugünkü snapshot tarixi, timezone ilə).
 *
 * `parser_run_id` veriləndə parser run günü ilə qiymətlər; verilməyəndə son snapshot/source ortaları.
 */
final class RecalculateBasketSnapshotsAction
{
    public function __construct(
        private readonly BasketPriceCalculationService $basketPrices,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     snapshot_date: string,
     *     parser_run_id: ?int,
     *     updated_pairs: int,
     *     skipped_pairs: int,
     *     message?: string,
     *     hint?: string,
     *     skipped_breakdown?: list<array{basket_id: int, basket_label: string, reason: string}>
     * }
     */
    public function run(?int $parserRunId = null, ?string $snapshotDate = null): array
    {
        if (! Schema::hasTable('basket_definitions') || ! Schema::hasTable('basket_snapshots')) {
            return [
                'ok' => false,
                'snapshot_date' => '',
                'parser_run_id' => $parserRunId,
                'updated_pairs' => 0,
                'skipped_pairs' => 0,
                'message' => 'basket_definitions / basket_snapshots cədvəlləri yoxdur.',
            ];
        }

        $tz = (string) config('parsers.snapshot_timezone', 'Asia/Baku');
        if ($snapshotDate === null || trim($snapshotDate) === '') {
            $snapshotDate = now($tz)->toDateString();
        } else {
            $snapshotDate = trim($snapshotDate);
        }

        if ($parserRunId !== null) {
            $run = DB::table('parser_runs')->where('id', $parserRunId)->first();
            if ($run === null) {
                return [
                    'ok' => false,
                    'snapshot_date' => $snapshotDate,
                    'parser_run_id' => $parserRunId,
                    'updated_pairs' => 0,
                    'skipped_pairs' => 0,
                    'message' => 'parser_run_id tapılmadı.',
                ];
            }
        }

        $baskets = BasketDefinition::query()
            ->where('is_active', true)
            ->with([
                'basketItems.position.measurementUnit',
                'basketItems.unit',
            ])
            ->get();

        $hasDolmaColumn = Schema::hasColumn('basket_snapshots', 'dolma_index_total');
        $dolmaBasketId = BasketDefinition::resolveDolmaIndexSourceBasketId();

        /** @var array<int, float|null> */
        $totals = [];
        foreach ($baskets as $basket) {
            if ($basket->basketItems->isEmpty()) {
                continue;
            }
            $totals[(int) $basket->id] = $this->basketPrices->sumBasket(
                $basket,
                $parserRunId,
                $snapshotDate,
            );
        }

        $dolmaTotal = $dolmaBasketId !== null ? ($totals[(int) $dolmaBasketId] ?? null) : null;

        $updatedPairs = 0;
        $skippedPairs = 0;
        /** @var list<array{basket_id: int, basket_label: string, reason: string}> */
        $skippedBreakdown = [];

        foreach ($baskets as $basket) {
            if ($basket->basketItems->isEmpty()) {
                continue;
            }

            $total = $totals[(int) $basket->id] ?? null;
            if ($total === null) {
                $basketLabel = trim((string) data_get($basket->name, 'az', ''));
                Log::info('Basket snapshot skipped (incomplete pricing)', [
                    'parser_run_id' => $parserRunId,
                    'basket_id' => $basket->id,
                    'basket_label' => $basketLabel,
                    'snapshot_date' => $snapshotDate,
                ]);
                $skippedPairs++;
                if (count($skippedBreakdown) < 40) {
                    $skippedBreakdown[] = [
                        'basket_id' => (int) $basket->id,
                        'basket_label' => $basketLabel !== '' ? $basketLabel : ('#'.(int) $basket->id),
                        'reason' => 'incomplete_line_pricing',
                    ];
                }

                continue;
            }

            $dolmaSnapshot = null;
            if ($hasDolmaColumn && $dolmaTotal !== null) {
                $dolmaSnapshot = round((float) $dolmaTotal, 4);
            }

            $now = now();
            $existing = DB::table('basket_snapshots')
                ->where('basket_id', $basket->id)
                ->whereDate('snapshot_date', $snapshotDate)
                ->first();

            $payload = [
                'total_price' => $total,
                'currency' => 'AZN',
                'sync_status' => 'pending',
                'synced_at' => null,
                'last_sync_error' => null,
                'updated_at' => $now,
            ];

            if ($hasDolmaColumn) {
                $payload['dolma_index_total'] = $dolmaSnapshot;
            }

            if ($existing !== null) {
                DB::table('basket_snapshots')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('basket_snapshots')->insert(array_merge($payload, [
                    'basket_id' => $basket->id,
                    'snapshot_date' => $snapshotDate,
                    'created_at' => $now,
                ]));
            }
            $updatedPairs++;
        }

        $out = [
            'ok' => true,
            'snapshot_date' => $snapshotDate,
            'parser_run_id' => $parserRunId,
            'updated_pairs' => $updatedPairs,
            'skipped_pairs' => $skippedPairs,
        ];

        if ($skippedPairs > 0) {
            $out['hint'] = 'Skipped pairs had no priced lines at all (every ingredient lacked a computable line total). When only some lines are missing, the basket total uses the sum of priced lines.';
            $out['skipped_breakdown'] = $skippedBreakdown;
        }

        return $out;
    }
}
