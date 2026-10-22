<?php

namespace App\Console\Commands;

use App\Models\ManagedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeMedia extends Command
{
    protected $signature = 'media:privatize {--apply : Copy files, verify their bytes, and revoke old public objects} {--include-cloud : Also migrate objects in the configured legacy Supabase bucket}';
    protected $description = 'Inventory or privatize legacy public uploads without changing historical record references';

    public function handle(): int
    {
        $models = [
            \App\Models\Vehicle::class => ['photo_url'],
            \App\Models\User::class => ['photo_url'],
            \App\Models\VehicleDocument::class => ['file_url'],
            \App\Models\VehicleIssueReport::class => ['photo_url'],
            \App\Models\IssueReportAttachment::class => ['file_url'],
            \App\Models\VehicleMaintenanceRecord::class => ['receipt_url'],
            \App\Models\TicketSubIssue::class => ['attachment_url', 'attachment_files'],
        ];
        $references = [];
        foreach ($models as $model => $fields) {
            foreach ($model::withoutGlobalScopes()->cursor() as $record) {
                $barangayId = match (true) {
                    $record instanceof \App\Models\TicketSubIssue => $record->ticket?->vehicle?->barangay_id,
                    $record instanceof \App\Models\IssueReportAttachment => $record->issueReport?->vehicle?->barangay_id,
                    $record instanceof \App\Models\Vehicle, $record instanceof \App\Models\User => $record->barangay_id,
                    default => $record->vehicle?->barangay_id,
                };
                $collect = function ($value) use (&$collect, &$references, $barangayId) {
                    if (is_array($value)) { foreach ($value as $item) $collect($item); }
                    elseif (is_string($value) && $value !== '' && !str_contains($value, '/api/files/')) $references[$value] = $barangayId;
                };
                foreach ($fields as $field) $collect($record->$field);
            }
        }
        $errors = 0; $found = 0;
        foreach ($references as $url => $barangayId) {
            $localPrefix = rtrim(config('app.url'), '/').'/storage/';
            $cloudPrefix = rtrim((string) config('filesystems.disks.supabase.url'), '/').'/';
            if (str_starts_with($url, $localPrefix)) { $disk = 'public'; $path = substr($url, strlen($localPrefix)); }
            elseif (str_starts_with($url, '/storage/')) { $disk = 'public'; $path = substr($url, 9); }
            elseif ($cloudPrefix !== '/' && str_starts_with($url, $cloudPrefix)) {
                if (!$this->option('include-cloud')) { $this->warn('Cloud reference requires --include-cloud.'); $errors++; continue; }
                config(['filesystems.disks.legacy_supabase' => array_merge(config('filesystems.disks.supabase'), ['bucket' => env('SUPABASE_BUCKET')])]);
                $disk = 'legacy_supabase'; $path = substr($url, strlen($cloudPrefix));
            } else continue; // Never fetch arbitrary supplied URLs.
            if (str_contains($path, '..') || str_contains($path, '?')) { $errors++; continue; }
            try {
                $source = Storage::disk($disk);
                if (!$source->exists($path)) continue;
                $found++;
                $this->line(($this->option('apply') ? 'Privatizing ' : 'Would privatize ').$disk.':'.$path);
                if (!$this->option('apply')) continue;
                $existing = ManagedFile::where('original_url', $url)->first();
                if (!$existing) {
                    $extension = match ($source->mimeType($path)) {
                        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
                        'application/pdf' => 'pdf', 'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', default => 'bin',
                    };
                    $id = \Illuminate\Support\Str::uuid().'.'.$extension;
                    $privatePath = 'legacy/'.$id;
                    $stream = $source->readStream($path);
                    try { $saved = Storage::disk('local')->put($privatePath, $stream, 'private'); }
                    finally { if (is_resource($stream)) fclose($stream); }
                    if (!$saved || hash('sha256', $source->get($path)) !== hash('sha256', Storage::disk('local')->get($privatePath))) throw new \RuntimeException('Private copy verification failed.');
                    $existing = ManagedFile::create(['id' => $id, 'disk' => 'local', 'path' => $privatePath, 'directory' => explode('/', $path)[0], 'barangay_id' => $barangayId, 'original_url' => $url]);
                }
                // Recheck even on a retry: a previously copied file may have
                // disappeared since an earlier public-delete failure.
                $private = Storage::disk($existing->disk);
                if (!$private->exists($existing->path) || hash('sha256', $source->get($path)) !== hash('sha256', $private->get($existing->path))) throw new \RuntimeException('Private copy verification failed; public original retained.');
                if (!$source->delete($path)) throw new \RuntimeException('Old public object could not be revoked.');
            } catch (\Throwable $error) { $this->error($error->getMessage()); $errors++; }
        }
        $this->info("Referenced public files found: {$found}. Errors or deferred cloud references: {$errors}.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
