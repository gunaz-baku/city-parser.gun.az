<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricePosition;
use App\Models\PriceSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PricePositionSourcesController extends Controller
{
    public function index(Request $request, PricePosition $pricePosition): JsonResponse
    {
        $sources = PriceSource::query()
            ->where('position_id', $pricePosition->id)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return response()->json([
            'position_id' => (int) $pricePosition->id,
            'sources' => $sources->map(fn (PriceSource $s) => $s->toArray())->values()->all(),
        ]);
    }

    public function update(Request $request, PricePosition $pricePosition): JsonResponse
    {
        $validated = $request->validate([
            'sources' => ['required', 'array'],
            'sources.*' => ['array'],
            'sources.*.source_type' => ['required', 'string', 'max:50'],
            'sources.*.links_json' => ['nullable'],
            'sources.*.options_json' => ['nullable', 'array'],
            'sources.*.is_active' => ['sometimes', 'boolean'],
            'sources.*.priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        /** @var list<array<string, mixed>> $sources */
        $sources = $validated['sources'];

        DB::transaction(function () use ($pricePosition, $sources): void {
            PriceSource::query()->where('position_id', $pricePosition->id)->delete();

            $now = now();
            foreach ($sources as $idx => $src) {
                $priority = array_key_exists('priority', $src)
                    ? (int) $src['priority']
                    : (100 + ($idx * 10));

                $links = $this->normalizeLinksJson($src['links_json'] ?? null);

                PriceSource::query()->create([
                    'position_id' => $pricePosition->id,
                    'source_type' => trim((string) $src['source_type']),
                    'links_json' => $links,
                    'options_json' => isset($src['options_json']) && is_array($src['options_json']) && $src['options_json'] !== []
                        ? $src['options_json']
                        : null,
                    'is_active' => (bool) ($src['is_active'] ?? true),
                    'priority' => $priority,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @return list<string>|null
     */
    private function normalizeLinksJson(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $u) {
                if (is_string($u) && trim($u) !== '') {
                    $out[] = trim($u);
                }
            }

            return $out === [] ? null : array_values($out);
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeLinksJson($decoded);
            }
            $trim = trim($raw);
            if ($trim === '') {
                return null;
            }
            $lines = preg_split('/\r\n|\r|\n/', $trim) ?: [];
            $urls = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $urls[] = $line;
                }
            }

            return $urls === [] ? null : $urls;
        }

        return null;
    }
}
