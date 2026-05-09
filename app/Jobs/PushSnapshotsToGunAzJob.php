<?php

namespace App\Jobs;

use App\Services\GunAz\GunAzApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PushSnapshotsToGunAzJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(public int $parserRunId)
    {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(GunAzApiClient $client): void
    {
        if (! config('gun_az.push_enabled', false)) {
            Log::info('PushSnapshotsToGunAzJob: push disabled; snapshots stay pending for gun.az pull API', [
                'parser_run_id' => $this->parserRunId,
            ]);

            return;
        }

        $this->pushPriceSnapshots($client);
        $this->pushBasketSnapshots($client);
    }

    private function pushPriceSnapshots(GunAzApiClient $client): void
    {
        $snapshots = DB::table('price_snapshots')
            ->where('parser_run_id', $this->parserRunId)
            ->where('sync_status', 'pending')
            ->orderBy('id')
            ->get();

        if ($snapshots->isEmpty()) {
            return;
        }

        foreach ($snapshots->chunk(50) as $chunk) {
            $this->sendChunk(
                $client,
                'price_snapshot',
                $chunk,
                fn (array $payload) => $client->pushPriceSnapshotsPayload($payload),
                config('gun_az.endpoints.price_snapshots')
            );
        }
    }

    private function pushBasketSnapshots(GunAzApiClient $client): void
    {
        $rows = DB::table('basket_snapshots')
            ->where('sync_status', 'pending')
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows->chunk(50) as $chunk) {
            $this->sendChunk(
                $client,
                'basket_snapshot',
                $chunk,
                fn (array $payload) => $client->pushBasketSnapshotsPayload($payload),
                config('gun_az.endpoints.basket_snapshots')
            );
        }
    }

    /**
     * @param  Collection<int, object>  $chunk
     */
    private function sendChunk(
        GunAzApiClient $client,
        string $entityType,
        Collection $chunk,
        callable $push,
        string $endpointPath,
    ): void {
        $ids = $chunk->pluck('id')->all();
        $payloadKey = $entityType === 'basket_snapshot' ? 'basket_snapshots' : 'snapshots';
        $body = [$payloadKey => $chunk->map(fn ($r) => (array) $r)->values()->all()];

        try {
            $response = $push($body);
            $status = $response->status();

            if ($response->failed()) {
                $msg = mb_substr((string) $response->body(), 0, 2000);
                $this->markPriceSnapshotsFailed($entityType, $ids, 'HTTP '.$status.': '.$msg);
                $this->logApiSync(
                    (int) $chunk->first()->id,
                    $endpointPath,
                    $body,
                    $status,
                    $response->body(),
                    'failed',
                    $msg
                );
                throw new \RuntimeException('gun.az API uğursuz: '.$status);
            }

            $this->markSynced($entityType, $ids);
            $this->logApiSync(
                (int) $chunk->first()->id,
                $endpointPath,
                $body,
                $status,
                mb_substr((string) $response->body(), 0, 65000),
                'success',
                null
            );
        } catch (Throwable $e) {
            $this->markPriceSnapshotsFailed($entityType, $ids, $e->getMessage());
            Log::error('PushSnapshotsToGunAzJob', [
                'parser_run_id' => $this->parserRunId,
                'entity' => $entityType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function markSynced(string $entityType, array $ids): void
    {
        $now = now();
        if ($entityType === 'basket_snapshot') {
            DB::table('basket_snapshots')->whereIn('id', $ids)->update([
                'sync_status' => 'synced',
                'synced_at' => $now,
                'last_sync_error' => null,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('price_snapshots')->whereIn('id', $ids)->update([
                'sync_status' => 'synced',
                'synced_at' => $now,
                'last_sync_error' => null,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function markPriceSnapshotsFailed(string $entityType, array $ids, string $message): void
    {
        $now = now();
        if ($entityType === 'basket_snapshot') {
            DB::table('basket_snapshots')->whereIn('id', $ids)->update([
                'sync_status' => 'failed',
                'last_sync_error' => mb_substr($message, 0, 65000),
                'updated_at' => $now,
            ]);
        } else {
            DB::table('price_snapshots')->whereIn('id', $ids)->update([
                'sync_status' => 'failed',
                'last_sync_error' => mb_substr($message, 0, 65000),
                'updated_at' => $now,
            ]);
        }
    }

    private function logApiSync(
        int $entityId,
        string $endpoint,
        array $requestPayload,
        ?int $responseStatus,
        ?string $responseBody,
        string $status,
        ?string $errorMessage,
    ): void {
        if (! Schema::hasTable('api_sync_logs')) {
            return;
        }

        DB::table('api_sync_logs')->insert([
            'parser_run_id' => $this->parserRunId,
            'entity_type' => 'batch',
            'entity_id' => $entityId,
            'endpoint' => $endpoint,
            'request_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
            'response_status' => $responseStatus,
            'response_body' => $responseBody !== null ? mb_substr($responseBody, 0, 65000) : null,
            'status' => $status,
            'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 65000) : null,
            'attempt' => $this->attempts(),
            'synced_at' => $status === 'success' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
