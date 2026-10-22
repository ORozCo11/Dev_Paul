<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\City;
use App\Services\BarangayGeography;
use Illuminate\Database\Seeder;

class BarangayBoundarySeeder extends Seeder
{
    public function run(): void
    {
        // Run after PhilippineBarangaySeeder assigns the official codes. The
        // old name-matched Mandaue file contains very coarse polygons and must
        // never overwrite detailed references or administrator-reviewed maps.
        $cityId = City::where('code', '072230000')->value('id');
        if (!$cityId) return;

        $geography = app(BarangayGeography::class);
        $count = 0;
        foreach (Barangay::where('city_id', $cityId)->whereNotNull('psgc_code')->with('city')->get() as $barangay) {
            $geography->attachBoundary($barangay);
            if ($barangay->wasChanged()) $count++;
        }
        $this->command?->info("Code-matched Mandaue reference boundaries updated: {$count}.");
    }
}
