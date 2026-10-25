<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ManagedFile extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function reference(): string { return '/api/files/'.$this->id; }

    public function canRenewFor(User $user): bool
    {
        $references = array_filter([$this->reference(), $this->original_url]);
        if ($this->directory === 'vehicle-documents') return $user->canDo('document.view');
        if ($this->directory === 'repair-attachments' && !$user->hasRole('Admin')) {
            if (!$user->hasAnyRole(['Custodian', 'Maintenance Personnel'])) return false;
            $lines = TicketSubIssue::with('ticket')->where(function ($query) use ($user) {
                if ($user->hasRole('Custodian')) $query->orWhereHas('ticket', fn ($tickets) => $tickets->where('assigned_custodian_id', $user->id));
                if ($user->hasRole('Maintenance Personnel')) $query->orWhere('assigned_mechanic_id', $user->id)->orWhereHas('ticket', fn ($tickets) => $tickets->where('assigned_mechanic_id', $user->id));
            })->get();
            return $lines->contains(function ($line) use ($references) {
                $urls = array_merge([$line->attachment_url], array_column($line->attachment_files ?? [], 'url'));
                return (bool) array_intersect($references, array_filter($urls));
            });
        }
        if ($this->directory === 'maintenance-receipts') {
            $records = VehicleMaintenanceRecord::whereIn('receipt_url', $references);
            if (!$user->hasAnyRole(['Admin', 'Custodian'])) $records->where('maintenance_personnel_id', $user->id);
            return $records->exists();
        }
        if ($this->directory === 'issue-attachments') {
            $attachments = IssueReportAttachment::whereIn('file_url', $references)->whereHas('issueReport');
            if (!$user->hasAnyRole(['Admin', 'Custodian'])) $attachments->whereHas('issueReport', fn ($query) => $query->where('reported_by', $user->id));
            return $attachments->exists();
        }
        return true; // Fleet photos and staff avatars keep their existing viewing scope.
    }

    public static function fromReference(?string $reference): ?self
    {
        if (!$reference) return null;
        $path = parse_url($reference, PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/api/files/([^/]+)$~', $path, $match)) {
            return static::find($match[1]);
        }
        return static::where('original_url', $reference)->first();
    }

    public static function removeReference(?string $reference): void
    {
        $file = static::fromReference($reference);
        if (!$file) return;
        // Keep the DB reference and fail visibly if storage refuses deletion.
        // A successful delete must revoke both the link and the stored bytes.
        if (!Storage::disk($file->disk)->delete($file->path)) {
            throw new \RuntimeException('Could not remove the stored file. Please retry.');
        }
        $file->delete();
    }
}
