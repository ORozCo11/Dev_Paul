<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;
use App\Models\ManagedFile;
use Illuminate\Support\Str;

trait UploadsImages
{
    /**
     * Every upload is kept on the server's private local disk. Nothing is
     * sent to a cloud service, and files are only served through the
     * authenticated /api/files/{id} route.
     */
    private function storeUploadedImage($file, string $directory): string
    {
        $filename = Str::uuid() . '.' . $this->safeStoredExtension($file);
        $path = Storage::disk('local')->putFileAs($directory, $file, $filename, 'private');
        if (!$path) throw new \RuntimeException('Could not save the uploaded file.');
        $asset = ManagedFile::create([
            'id' => $filename, 'disk' => 'local', 'path' => $path, 'directory' => $directory,
            'barangay_id' => auth()->user()?->barangay_id, 'uploaded_by' => auth()->id(),
        ]);
        return $asset->reference();
    }

    /**
     * The extension used to name the stored file — NEVER the client-supplied
     * original filename's extension. `UploadedFile::extension()` guesses it
     * from the file's real content type (PHP fileinfo), so a "shell.php"
     * upload carrying image bytes is stored with an image extension. Falls
     * back to a harmless, non-executable extension if the type is unknown.
     */
    private function safeStoredExtension($file): string
    {
        return $file->extension() ?: 'bin';
    }

}
