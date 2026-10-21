<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CannibalizationPart extends Model
{
    protected $primaryKey = 'part_id';

    protected $fillable = [
        'cannibalization_id', 'part_name', 'part_number', 'quantity', 'unit', 'part_condition', 'installation_details', 'notes',
    ];

    protected $casts = ['quantity' => 'integer'];
}
