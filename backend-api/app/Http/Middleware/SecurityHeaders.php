<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defense-in-depth security headers to every response.
 *
 * The React SPA already escapes all rendered text (no dangerouslySetInnerHTML),
 * so these headers are a second layer — they matter most for the JSON and
 * uploaded-file responses this API serves:
 *
 *  - nosniff        stops a browser from re-interpreting a JSON or uploaded
 *                   file as HTML/JS (the main way a stored file could "execute").
 *  - frame-ancestors/X-Frame-Options  blocks clickjacking (embedding in an iframe).
 *  - CSP default-src 'none'  API responses are data, not documents — nothing in
 *                   them should ever load or run a resource.
 *  - Referrer/Permissions-Policy  minimise information leakage and disable APIs
 *                   this backend never needs.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'   => 'nosniff',
            'X-Frame-Options'          => 'DENY',
            'Referrer-Policy'          => 'no-referrer',
            'Permissions-Policy'       => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
            // API responses are data consumed by fetch/XHR, never rendered as a
            // document — so lock the whole thing down. frame-ancestors 'none'
            // is the modern, header-independent anti-clickjacking directive.
            'Content-Security-Policy'  => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
            'X-XSS-Protection'         => '0', // legacy filter, explicitly off (CSP supersedes it)
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Don't advertise the framework/PHP version.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
