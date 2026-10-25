<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\RegistrationSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReferenceBarangayRegistrationSeeder extends Seeder
{
    /**
     * Seed local-only registration fixtures against authoritative PSGC and
     * boundary records. These make the Super Admin registration workflow
     * demonstrable across more than one barangay without inventing places.
     */
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        $barangays = Barangay::query()
            ->whereHas('city', fn ($query) => $query->where('code', '072230000'))
            ->whereNotNull('psgc_code')
            ->whereNotNull('boundary')
            ->orderBy('name')
            ->take(10)
            ->get();

        if ($barangays->count() !== 10) {
            throw new \RuntimeException('Ten PSGC-coded Mandaue barangays with reference boundaries are required before demo registrations can be seeded.');
        }

        foreach ($barangays as $index => $barangay) {
            RegistrationSetting::for($barangay->id);

            User::updateOrCreate(
                ['email' => sprintf('boundary-qa-%02d@example.test', $index + 1)],
                [
                    'name' => "Boundary QA Admin — {$barangay->name}",
                    'password' => Hash::make('boundaryqa123'),
                    'role' => 'Admin',
                    'barangay_id' => $barangay->id,
                    'city_id' => $barangay->city_id,
                    'barangay_name' => $barangay->name,
                    'is_active' => true,
                    'approved_at' => now(),
                ],
            );
        }
    }
}
