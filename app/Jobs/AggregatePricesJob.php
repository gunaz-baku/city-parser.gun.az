<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesParserRunLifecycle;
use App\Services\PriceAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AggregatePricesJob implements ShouldQueue
{
    use Dispatchable, HandlesParserRunLifecycle, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $parserRunId)
    {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(PriceAggregatorService $aggregator): void
    {
        $tz = (string) config('parsers.snapshot_timezone', 'Asia/Baku');
        $snapshotDate = now($tz)->toDateString();

        $run = DB::table('parser_runs')->where('id', $this->parserRunId)->first();
        if ($run === null) {
            return;
        }

        $positionIds = DB::table('source_price_results')
            ->where('parser_run_id', $this->parserRunId)
            ->whereDate('result_date', $snapshotDate)
            ->where('is_valid', true)
            ->whereNotNull('normalized_price')
            ->distinct()
            ->pluck('position_id');

        foreach ($positionIds as $positionId) {
            $values = DB::table('source_price_results')
                ->where('parser_run_id', $this->parserRunId)
                ->where('position_id', $positionId)
                ->whereDate('result_date', $snapshotDate)
                ->where('is_valid', true)
                ->whereNotNull('normalized_price')
                ->pluck('normalized_price')
                ->all();

            $parserType = (string) DB::table('price_positions')->where('id', $positionId)->value('parser_type');
            $stats = match ($parserType) {
                'bina' => $aggregator->calculateBina($values),
                'wolt' => $aggregator->calculateFromBrands($values),
                default => $aggregator->calculateFromBrands($values),
            };

            if ($stats['count'] === 0) {
                continue;
            }

            $position = DB::table('price_positions')->where('id', $positionId)->first();
            if ($position === null) {
                continue;
            }

            $sourceCount = (int) DB::table('source_price_results')
                ->where('position_id', $positionId)
                ->whereDate('result_date', $snapshotDate)
                ->where('is_valid', true)
                ->whereNotNull('normalized_price')
                ->distinct()
                ->count('source_id');
            $sourceCount = max(1, $sourceCount);

            $now = now();
            $payload = [
                'position_id' => (int) $positionId,
                'snapshot_date' => $snapshotDate,
                'currency' => 'AZN',
                'price_min' => $stats['min'],
                'price_max' => $stats['max'],
                'price_avg' => $stats['avg'],
                'sample_size' => $stats['count'],
                'source_count' => $sourceCount,
                'parser_type' => (string) $run->parser_type,
                'parser_run_id' => $this->parserRunId,
                'sync_status' => 'pending',
                'synced_at' => null,
                'last_sync_error' => null,
                'updated_at' => $now,
            ];

            $existing = DB::table('price_snapshots')
                ->where('position_id', $positionId)
                ->whereDate('snapshot_date', $snapshotDate)
                ->first();

            if ($existing !== null) {
                DB::table('price_snapshots')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = $now;
                DB::table('price_snapshots')->insert($payload);
            }
        }
    }
}
