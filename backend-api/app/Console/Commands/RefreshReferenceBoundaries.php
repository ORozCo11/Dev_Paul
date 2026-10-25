<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\BarangayGeography;
use Illuminate\Console\Command;

class RefreshReferenceBoundaries extends Command
{
    protected $signature = 'geography:refresh-boundaries';
    protected $description = 'Upgrade automatic reference boundaries and require review while preserving confirmed boundaries';

    public function handle(BarangayGeography $geography): int
    {
        $count = 0;
        Barangay::whereNotNull('psgc_code')
            ->whereNull('boundary_verified_by')
            ->where(fn ($query) => $query->whereNotNull('boundary')->orWhereIn('boundary_source', [BarangayGeography::LEGACY_SOURCE, BarangayGeography::SOURCE, 'legacy']))
            ->with('city')
            ->chunkById(20, function ($barangays) use ($geography, &$count) {
                foreach ($barangays as $barangay) {
                    $geography->attachBoundary($barangay);
                    if ($barangay->wasChanged()) $count++;
                }
            });
        $this->info("Updated {$count} automatic reference boundaries. Administrator-confirmed boundaries were preserved.");
        return self::SUCCESS;
    }
}
