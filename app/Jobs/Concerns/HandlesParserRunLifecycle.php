<?php

namespace App\Jobs\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait HandlesParserRunLifecycle
{
    /**
     * @param  array<string, mixed>|null  $base
     * @return array<string, mixed>
     */
    protected function buildThrowableContext(Throwable $e, ?array $base = null): array
    {
        $trace = $e->getTraceAsString();
        $trace = mb_substr($trace, 0, 8000);

        $out = $base ?? [];
        $out['exception_class'] = $e::class;
        $out['exception_message'] = mb_substr($e->getMessage(), 0, 5000);
        $out['exception_code'] = is_scalar($e->getCode()) ? $e->getCode() : null;
        $out['exception_file'] = $e->getFile();
        $out['exception_line'] = $e->getLine();
        $out['exception_trace'] = $trace;

        if ($e->getPrevious() !== null) {
            $prev = $e->getPrevious();
            $out['previous_exception'] = [
                'class' => $prev::class,
                'message' => mb_substr($prev->getMessage(), 0, 2000),
                'code' => is_scalar($prev->getCode()) ? $prev->getCode() : null,
            ];
        }

        return $out;
    }

    protected function createParserRun(string $parserType, string $triggerType = 'cron'): ?int
    {
        if (! Schema::hasTable('parser_runs')) {
            return null;
        }

        return (int) DB::table('parser_runs')->insertGetId([
            'parser_type' => $parserType,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  int  $skippedPositions  Parser tərəfindən qəsdən ötürülən mövqelər
     */
    protected function finishParserRun(
        ?int $runId,
        int $totalPositions,
        int $successPositions,
        int $failedPositions,
        int $skippedPositions = 0,
        ?string $message = null,
        ?int $totalSources = null,
        ?int $successSources = null,
        ?int $failedSources = null,
    ): void {
        if (! $runId || ! Schema::hasTable('parser_runs')) {
            return;
        }

        $row = DB::table('parser_runs')->where('id', $runId)->first();
        if ($row === null) {
            return;
        }

        $started = Carbon::parse($row->started_at);
        $finished = now();
        $duration = (int) $started->diffInSeconds($finished);

        $totalSrc = $totalSources ?? $totalPositions;
        $okSrc = $successSources ?? $successPositions;
        $failSrc = $failedSources ?? $failedPositions;

        if ($failedPositions === 0 && $successPositions > 0) {
            $status = 'success';
        } elseif ($successPositions > 0 && $failedPositions > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        DB::table('parser_runs')->where('id', $runId)->update([
            'status' => $status,
            'finished_at' => $finished,
            'duration_seconds' => $duration,
            'total_positions' => $totalPositions,
            'success_positions' => $successPositions,
            'failed_positions' => $failedPositions,
            'skipped_positions' => $skippedPositions,
            'total_sources' => $totalSrc,
            'success_sources' => $okSrc,
            'failed_sources' => $failSrc,
            'message' => $message,
            'updated_at' => $finished,
        ]);
    }

    protected function recordParserRunError(
        int $parserRunId,
        string $errorStage,
        string $errorMessage,
        ?int $positionId = null,
        ?int $sourceId = null,
        ?string $errorCode = null,
        ?array $errorContext = null,
    ): void {
        if (! Schema::hasTable('parser_run_errors')) {
            return;
        }

        $tz = (string) config('parsers.snapshot_timezone', 'Asia/Baku');
        $nowTz = now($tz);
        $msg = mb_substr($errorMessage, 0, 65535);

        if ($errorContext === null) {
            $errorContext = [];
        }
        if (! array_key_exists('occurred_at', $errorContext)) {
            $errorContext['occurred_at'] = $nowTz->toIso8601String();
        }

        DB::table('parser_run_errors')->insert([
            'parser_run_id' => $parserRunId,
            'position_id' => $positionId,
            'source_id' => $sourceId,
            'error_stage' => $errorStage,
            'error_code' => $errorCode,
            'error_message' => $msg,
            'error_context' => $errorContext !== [] ? json_encode($errorContext, JSON_UNESCAPED_UNICODE) : null,
            'occurred_at' => $nowTz,
            'created_at' => $nowTz,
        ]);
    }

    /**
     * `source_price_results` üzrə mövqe + tarix üçün aggregate snapshot yazır/yeniləyir.
     */
    protected function syncPriceSnapshotFromSourceResults(
        int $positionId,
        int $parserRunId,
        string $snapshotDate,
        string $parserType,
    ): void {
        if (! Schema::hasTable('price_snapshots') || ! Schema::hasTable('source_price_results')) {
            return;
        }

        $position = DB::table('price_positions')->where('id', $positionId)->first();
        if ($position === null) {
            return;
        }

        $values = DB::table('source_price_results')
            ->where('position_id', $positionId)
            ->whereDate('result_date', $snapshotDate)
            ->where('is_valid', true)
            ->whereNotNull('normalized_price')
            ->pluck('normalized_price');

        if ($values->isEmpty()) {
            return;
        }

        $min = (float) $values->min();
        $max = (float) $values->max();
        $avg = (float) $values->avg();
        $sampleSize = $values->count();

        $sourceCount = DB::table('source_price_results')
            ->where('position_id', $positionId)
            ->whereDate('result_date', $snapshotDate)
            ->where('is_valid', true)
            ->whereNotNull('normalized_price')
            ->distinct()
            ->count('source_id');

        $sourceCount = max(1, (int) $sourceCount);

        $now = now();
        $existing = DB::table('price_snapshots')
            ->where('position_id', $positionId)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        $payload = [
            'snapshot_date' => $snapshotDate,
            'currency' => 'AZN',
            'price_min' => round($min, 4),
            'price_max' => round($max, 4),
            'price_avg' => round($avg, 4),
            'sample_size' => $sampleSize,
            'source_count' => $sourceCount,
            'parser_type' => $parserType,
            'parser_run_id' => $parserRunId,
            'sync_status' => 'pending',
            'synced_at' => null,
            'last_sync_error' => null,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            DB::table('price_snapshots')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('price_snapshots')->insert($payload);
        }
    }

    protected function resolveBinaSourceId(int $positionId): ?int
    {
        if (! Schema::hasTable('price_sources')) {
            return null;
        }

        return DB::table('price_sources')
            ->where('position_id', $positionId)
            ->where('source_type', 'bina')
            ->where('is_active', true)
            ->orderBy('priority')
            ->value('id');
    }

    /**
     * Eyni gün üçün mövqeyə aid bina mənbə nəticələrini təmizləyir (yalnız `source_type = bina` source-lar və ya source_id null).
     */
    protected function deleteBinaSourcePriceResultsForPositionDate(int $positionId, string $date): void
    {
        if (! Schema::hasTable('source_price_results')) {
            return;
        }

        $ids = DB::table('price_sources')
            ->where('position_id', $positionId)
            ->where('source_type', 'bina')
            ->pluck('id');

        $q = DB::table('source_price_results')
            ->where('position_id', $positionId)
            ->whereDate('result_date', $date);

        if ($ids->isNotEmpty()) {
            $q->whereIn('source_id', $ids);
        } else {
            $q->whereNull('source_id');
        }

        $q->delete();
    }

    protected function deleteWoltSourcePriceResultsForSourceAndDate(int $positionId, ?int $sourceId, string $date): void
    {
        if (! Schema::hasTable('source_price_results')) {
            return;
        }

        $q = DB::table('source_price_results')
            ->where('position_id', $positionId)
            ->whereDate('result_date', $date);

        if ($sourceId !== null) {
            $q->where('source_id', $sourceId);
        } else {
            $q->whereNull('source_id');
        }

        $q->delete();
    }
}
