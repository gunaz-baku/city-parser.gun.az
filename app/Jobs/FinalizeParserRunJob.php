<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesParserRunLifecycle;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class FinalizeParserRunJob implements ShouldQueue
{
    use Dispatchable, HandlesParserRunLifecycle, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public int $parserRunId,
        public ?string $batchId = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(): void
    {
        $run = DB::table('parser_runs')->where('id', $this->parserRunId)->first();
        if ($run === null) {
            return;
        }

        $totalJobs = 0;

        if ($this->batchId !== null) {
            $batch = Bus::findBatch($this->batchId);
            if ($batch !== null) {
                $totalJobs = (int) $batch->totalJobs;
            }
        }

        if ($totalJobs === 0) {
            $row = DB::table('parser_runs')->where('id', $this->parserRunId)->first();
            if ($row !== null) {
                $started = Carbon::parse($row->started_at);
                DB::table('parser_runs')->where('id', $this->parserRunId)->update([
                    'status' => 'success',
                    'finished_at' => now(),
                    'duration_seconds' => (int) $started->diffInSeconds(now()),
                    'total_positions' => 0,
                    'success_positions' => 0,
                    'failed_positions' => 0,
                    'skipped_positions' => 0,
                    'total_sources' => 0,
                    'success_sources' => 0,
                    'failed_sources' => 0,
                    'message' => 'No parse jobs were queued (no active sources).',
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        // Batch-level "failedJobs" only counts jobs that threw an uncaught exception.
        // ParseWoltSourceJob/ParseBinaSourceJob handle "no price found" as a normal
        // (non-throwing) outcome — they log it via recordParserRunError() and return.
        // So a position only really succeeded if this run actually produced a
        // price_snapshot for it; everything else with a logged error is a real failure.
        $snapshotPositionIds = DB::table('price_snapshots')
            ->where('parser_run_id', $this->parserRunId)
            ->distinct()
            ->pluck('position_id');

        $errorPositionIds = DB::table('parser_run_errors')
            ->where('parser_run_id', $this->parserRunId)
            ->whereNotNull('position_id')
            ->distinct()
            ->pluck('position_id');

        $failedPositionIds = $errorPositionIds->diff($snapshotPositionIds);
        $allPositionIds = $snapshotPositionIds->merge($errorPositionIds)->unique();

        $totalPositions = $allPositionIds->count();
        $failedPositions = $failedPositionIds->count();
        $successPositions = max(0, $totalPositions - $failedPositions);

        $this->finishParserRun(
            $this->parserRunId,
            $totalPositions,
            $successPositions,
            $failedPositions,
        );
    }
}
