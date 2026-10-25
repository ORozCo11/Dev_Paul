<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConcernReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'concern_type',
        'province_name',
        'city_name',
        'barangay_name',
        'description',
        'reporter_name',
        'reporter_contact',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
