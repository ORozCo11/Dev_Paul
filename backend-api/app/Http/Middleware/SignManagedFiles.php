<?php

namespace App\Http\Middleware;

use App\Models\ManagedFile;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class SignManagedFiles
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $user = $request->user();
        if (!$user || !$response instanceof JsonResponse || !$response->isSuccessful()) return $response;

        // Query only referenced URLs, once per response. Never persist an
        // expiring URL in records, archives, or the activity log.
        $data = $response->getData(true);
        $references = [];
        $collect = function ($value) use (&$collect, &$references) {
            if (is_array($value)) { foreach ($value as $item) $collect($item); }
            elseif (is_string($value) && (str_contains($value, '/api/files/') || str_contains($value, '/storage/') || str_starts_with($value, 'http'))) $references[] = $value;
        };
        $collect($data);
        if (!$references) return $response;
        $ids = array_values(array_filter(array_map(function ($reference) {
            $path = parse_url($reference, PHP_URL_PATH);
            return is_string($path) && preg_match('~^/api/files/([^/]+)$~', $path, $match) ? $match[1] : null;
        }, $references)));
        $files = ManagedFile::whereIn('id', $ids)->orWhereIn('original_url', array_unique($references))->get();
        $links = [];
        foreach ($files as $file) {
            // The endpoint already authorizes the parent record. This extra
            // tenant check also protects accidental cross-tenant references.
            if ((int) $file->barangay_id !== (int) $user->barangay_id && !($file->directory === 'profile-photos' && $user->hasRole('Super Admin'))) continue;
            $signed = URL::temporarySignedRoute('files.show', now()->addMinutes(5), ['file' => $file->id]);
            $links[$file->reference()] = $signed;
            if ($file->original_url) $links[$file->original_url] = $signed;
        }
        $replace = function ($value) use (&$replace, $links) {
            if (is_array($value)) return array_map($replace, $value);
            return is_string($value) ? ($links[$value] ?? $value) : $value;
        };
        $response->setData($replace($data));
        return $response;
    }
}
