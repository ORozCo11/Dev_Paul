<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use BelongsToBarangay;

    protected $primaryKey = 'log_id';

    protected $fillable = [
        'user_id',
        'role',
        'action',
        'module',
        'affected_record_id',
        'details',
        'barangay_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $token = auth()->user()?->currentAccessToken();
            if ($token?->name === 'impersonation_token') {
                $log->impersonator_user_id = $token->impersonator_user_id;
                $log->impersonation_session_id = $token->id;
                $log->impersonation_reason = $token->impersonation_reason;
                $log->details = '[Impersonated by account #' . ($token->impersonator_user_id ?? 'unknown legacy initiator') . '; reason: ' . ($token->impersonation_reason ?? 'legacy session') . '] ' . $log->details;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
