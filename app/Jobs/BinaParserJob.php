<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BinaParserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $parserRunId)
    {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(): void
    {
        if (! Schema::hasTable('price_sources')) {
            throw new \RuntimeException('price_sources mövcud deyil.');
        }

        $hasLinksJson = Schema::hasColumn('price_sources', 'links_json');
        $hasSourceUrl = Schema::hasColumn('price_sources', 'source_url');
        if (! $hasLinksJson && ! $hasSourceUrl) {
            throw new \RuntimeException('price_sources.links_json və ya source_url sütunu lazımdır.');
        }

        $query = DB::table('price_positions')
            ->join('price_sources', 'price_sources.position_id', '=', 'price_positions.id')
            ->where('price_sources.is_active', true)
            ->where('price_sources.source_type', 'bina')
            ->where(function ($q) use ($hasLinksJson, $hasSourceUrl): void {
                if ($hasLinksJson) {
                    $q->where(function ($q2): void {
                        $q2->whereNotNull('price_sources.links_json')
                            ->whereRaw('JSON_LENGTH(price_sources.links_json) > 0')
                            ->whereRaw('CAST(price_sources.links_json AS CHAR) LIKE ?', ['%bina.az%']);
                    });
                }
                if ($hasSourceUrl) {
                    $outer = $hasLinksJson ? 'orWhere' : 'where';
                    $q->{$outer}(function ($q2): void {
                        $q2->whereNotNull('price_sources.source_url')
                            ->where('price_sources.source_url', '!=', '')
                            ->whereRaw('LOWER(price_sources.source_url) LIKE ?', ['%bina.az%']);
                    });
                }
            })
            ->orderBy('price_sources.id')
            ->select('price_sources.id as source_id');

        $max = (int) env('BINA_PARSER_MAX_SOURCES', 0);
        if ($max > 0) {
            $query->limit($max);
        }

        $sourceIds = array_map('intval', $query->pluck('source_id')->all());

        $jobs = array_map(
            fn (int $sid) => new ParseBinaSourceJob($this->parserRunId, $sid),
            $sourceIds
        );

        $runId = $this->parserRunId;

        if ($jobs === []) {
            Bus::chain([
                new AggregatePricesJob($runId),
                new AggregateBasketSnapshotsJob($runId),
                new PushSnapshotsToGunAzJob($runId),
                new FinalizeParserRunJob($runId, null),
            ])->dispatch();

            return;
        }

        Bus::batch($jobs)
            ->name('bina-parse-'.$runId)
            ->allowFailures()
            ->then(function (Batch $batch) use ($runId): void {
                Bus::chain([
                    new AggregatePricesJob($runId),
                    new AggregateBasketSnapshotsJob($runId),
                    new PushSnapshotsToGunAzJob($runId),
                    new FinalizeParserRunJob($runId, $batch->id),
                ])->dispatch();
            })
            ->dispatch();
    }
}
