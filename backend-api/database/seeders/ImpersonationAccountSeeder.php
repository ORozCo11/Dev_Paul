<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ImpersonationAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo impersonation credentials only seed in local/testing.');
            return;
        }

        User::updateOrCreate(['email' => 'impersonation@test.com'], [
            'name' => 'Account Switcher', 'password' => Hash::make('test123'),
            'role' => 'Impersonator', 'roles' => ['Impersonator'],
            'barangay_id' => null, 'city_id' => null, 'barangay_name' => null,
            'is_active' => true, 'approved_at' => now(),
        ]);
    }
}
