<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class ParserControlController extends Controller
{
    /**
     * Link və ya HTTP Basic ilə parser:run Artisan əmrini işə salır (StartParserJob növbəyə).
     */
    public function runParser(string $type): JsonResponse
    {
        $type = strtolower($type);
        if (! in_array($type, ['wolt', 'bina'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'type yalnız wolt və ya bina ola bilər.',
            ], 422);
        }

        $exitCode = Artisan::call('parser:run', ['type' => $type]);

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'type' => $type,
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 422);
    }
}
