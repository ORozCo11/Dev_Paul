<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Non-destructive input hardening.
 *
 * This is intentionally NOT an HTML sanitizer — output encoding is the correct
 * XSS defense, and React already escapes everything on render, so stripping
 * "<" or "<script>" on input would only corrupt legitimate content (e.g. a note
 * that says "pressure < 50 psi") without adding real protection.
 *
 * What it DOES remove is genuinely never-legitimate and a real attack vector:
 *   - NULL bytes (\0)          — used for null-byte injection / path tricks.
 *   - Other C0/C1 control chars — used to smuggle payloads past filters or
 *                                 break log/header parsing.
 * Tab, newline and carriage return are preserved so multi-line text fields
 * (notes, descriptions, reasons) are untouched.
 *
 * Uploaded files are left alone (this walks only the request's input values).
 */
class SanitizeInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $clean = $this->clean($request->input());

        // Replace only the scalar/array input; uploaded files are untouched
        // because they live in $request->files, not $request->input().
        $request->merge($clean);

        return $next($request);
    }

    private function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->clean($item), $value);
        }

        if (is_string($value)) {
            // Strip NULL and control chars EXCEPT tab (09), LF (0A), CR (0D).
            // preg_replace returns null on invalid UTF-8 — fall back to the
            // original in that case rather than silently wiping the field.
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

            return $cleaned ?? $value;
        }

        return $value;
    }
}
