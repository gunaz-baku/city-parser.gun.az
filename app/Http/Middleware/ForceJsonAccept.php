<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->wantsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        if (
            $response instanceof JsonResponse
            && app()->environment('local')
        ) {
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

        return $response;
    }
}
