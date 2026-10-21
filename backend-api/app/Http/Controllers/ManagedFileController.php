<?php

namespace App\Http\Controllers;

use App\Models\ManagedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ManagedFileController extends Controller
{
    public function refresh(Request $request)
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:4096']]);
        $original = Request::create($data['url']);
        abort_unless(URL::hasCorrectSignature($original), 403);
        $asset = ManagedFile::fromReference($data['url']);
        abort_unless($asset, 404);
        $user = $request->user();
        abort_unless(
            (int) $asset->barangay_id === (int) $user->barangay_id
            || ($asset->directory === 'profile-photos' && $user->hasRole('Super Admin')),
            404
        );
        abort_if($user->hasRole('Super Admin') && $asset->directory !== 'profile-photos', 403);
        abort_unless($asset->canRenewFor($user), 403);
        return response()->json(['url' => URL::temporarySignedRoute('files.show', now()->addMinutes(5), ['file' => $asset->id])]);
    }

    // The signed URL is issued only inside an authorized API response. It
    // expires in five minutes and deletion revokes it immediately via the row.
    public function show(string $file)
    {
        $asset = ManagedFile::findOrFail($file);
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);
        $stream = $disk->readStream($asset->path);
        abort_unless(is_resource($stream), 404);
        $mime = $disk->mimeType($asset->path) ?: 'application/octet-stream';
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
        return response()->stream(function () use ($stream) {
            try { fpassthru($stream); } finally { fclose($stream); }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$asset->id.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
