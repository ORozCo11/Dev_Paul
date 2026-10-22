<?php

namespace App\Models\Concerns;

use App\Services\BarangayNumbers;
use Illuminate\Support\Facades\DB;

trait HasBarangayNumber
{
    public function initializeHasBarangayNumber(): void
    {
        $this->mergeCasts(['barangay_number' => 'integer']);
    }

    public function getDisplayNumberAttribute(): int
    {
        return (int) ($this->barangay_number ?? $this->getKey());
    }

    protected static function bootHasBarangayNumber(): void
    {
        static::creating(function ($model) {
            // BelongsToBarangay's creating hook runs before this hook on Vehicle.
            // Use raw queries for child rows so allocation also works in jobs and
            // seeders without an authenticated viewer or loaded relationships.
            $barangayId = in_array($model->getTable(), ['users', 'vehicles'], true)
                ? $model->barangay_id
                : DB::table('vehicles')->where('vehicle_id', $model->vehicle_id)->value('barangay_id');
            if ($barangayId) {
                $model->barangay_number = BarangayNumbers::next((int) $barangayId, $model->getTable());
                if ($model->getTable() === 'maintenance_tickets') {
                    $model->ticket_title = preg_replace('/^MT-\d+(?=\s)/u', sprintf('MT-%04d', $model->barangay_number), $model->ticket_title ?? '');
                }
            }
        });
    }
}
