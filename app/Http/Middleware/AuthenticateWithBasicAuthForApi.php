<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * API üçün HTTP Basic auth (sessiya olmadan) — Laravel-in onceBasic() yoxlaması.
 */
class AuthenticateWithBasicAuthForApi
{
    public function handle(Request $request, Closure $next, ?string $guard = null, ?string $field = null): Response
    {
        Auth::guard($guard ?? 'web')->onceBasic($field ?: 'email');

        return $next($request);
    }
}
