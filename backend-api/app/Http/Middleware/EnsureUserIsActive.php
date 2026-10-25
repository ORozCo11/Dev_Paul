<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation must take effect immediately, not just block future logins.
 * Without this, a deactivated user's existing Sanctum token keeps working on
 * every protected route indefinitely — `is_active` was previously only
 * checked at login. Runs after auth:sanctum, so $request->user() is set.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            abort(401, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
