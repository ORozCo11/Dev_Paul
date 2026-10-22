<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Test Super Admin only seeds in local/testing.');
            return;
        }

        User::updateOrCreate(['email' => 'superadmin@test.com'], [
            'name' => 'Test Super Admin',
            'password' => Hash::make('test123'),
            'role' => 'Super Admin',
            'roles' => ['Super Admin'],
            'barangay_id' => null,
            'city_id' => null,
            'barangay_name' => null,
            'is_active' => true,
            'approved_at' => now(),
        ]);
    }
}
