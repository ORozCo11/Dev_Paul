<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictImpersonationScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('Impersonator')) {
            $path = preg_replace('#^api/#', '', $request->path());
            abort_unless(in_array($path, ['user', 'logout'], true) || str_starts_with($path, 'impersonate/'),
                403, 'Choose an account from the account switcher to open its workspace.');
        }

        return $next($request);
    }
}
