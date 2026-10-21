<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

trait UploadsImages
{
    private function storeUploadedImage($file, string $directory): string
    {
        $filename = uniqid(rtrim($directory, '-') . '_', true) . '.' . $this->safeStoredExtension($file);

        try {
            $path = Storage::disk('supabase')->putFileAs($directory, $file, $filename, 'public');

            return $this->publicStorageUrl($path);
        } catch (\Throwable $throwable) {
            // Don't fail the request, but make the fallback visible — a silent
            // fallback previously hid broken Supabase credentials for a long time.
            \Illuminate\Support\Facades\Log::warning(
                "Supabase upload failed for {$directory}/{$filename}; stored locally instead. ({$throwable->getMessage()})"
            );
            $path = Storage::disk('public')->putFileAs($directory, $file, $filename, 'public');

            return $this->publicLocalStorageUrl($path);
        }
    }

    /**
     * The extension used to name the stored file — NEVER the client-supplied
     * original filename's extension. A "polyglot" upload (genuine image/PDF
     * bytes with a malicious payload appended, sent with an original filename
     * like "shell.php") passes content-based validation (Laravel's `image`
     * and `mimes:` rules already inspect real file content via finfo — see
     * ValidatesAttributes::validateMimes(), which checks $value->guessExtension(),
     * not the client's claimed name), but this trait used to still store the
     * file under the client's claimed extension, e.g. "shell.php" — served
     * directly from the web root when Supabase upload falls back to the
     * local `public` disk.
     *
     * `UploadedFile::extension()` (Illuminate\Http\FileHelpers) instead
     * guesses the extension from the file's actual content type, detected
     * server-side via PHP's fileinfo/finfo — never from the client's
     * original filename. Falls back to a harmless, non-executable extension
     * if the content type can't be recognized at all.
     */
    private function safeStoredExtension($file): string
    {
        return $file->extension() ?: 'bin';
    }

    private function publicStorageUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('filesystems.disks.supabase.url'), '/');

        return $baseUrl === '' ? $path : $baseUrl . '/' . ltrim($path, '/');
    }

    private function publicLocalStorageUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl . '/storage/' . ltrim($path, '/');
    }
}
