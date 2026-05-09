<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGunAzApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('gun_az.token');
        if ($expected === null || $expected === '') {
            abort(503, 'GUN_AZ_API_TOKEN is not set (inbound parser API disabled).');
        }

        $token = $request->bearerToken()
            ?? (is_string($request->query('token')) ? $request->query('token') : null);
        if ($token === null || ! hash_equals((string) $expected, (string) $token)) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
