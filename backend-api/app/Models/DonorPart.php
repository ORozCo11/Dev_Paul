<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One part taken from a cannibalized repair's donor vehicle. */
class DonorPart extends Model
{
    protected $table = 'ticket_sub_issue_donor_parts';
    protected $primaryKey = 'part_id';

    protected $fillable = ['sub_issue_id', 'part_name', 'quantity', 'part_condition', 'installed_at', 'reason', 'notes'];

    protected $casts = ['quantity' => 'integer', 'installed_at' => 'date:Y-m-d'];
}
