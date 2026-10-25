<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Seeds the one platform-level Super Admin account, with a known
     * demo password — for local development/testing/grading convenience
     * ONLY. Deliberately does NOT run outside those environments: a
     * well-known email+password for an account that can control every
     * barangay must never be auto-created on a real deployment. To
     * provision a real Super Admin in production, create the account
     * manually (e.g. `php artisan tinker`) with a strong, unique password.
     *
     * Not reachable through /register (no self-service path creates this
     * role) and not scoped to any barangay (barangay_id stays null) — see
     * SuperAdminController and BelongsToBarangay's docblock for why that
     * null barangay_id is exactly what lets it see across every barangay.
     * Idempotent: safe to run again if the account is ever deleted.
     */
    public function up(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        if (DB::table('users')->where('email', 'superadmin@barangay.gov')->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Super Admin',
            'email' => 'superadmin@barangay.gov',
            'password' => Hash::make('superadmin123'),
            'role' => 'Super Admin',
            'roles' => json_encode(['Super Admin']),
            'barangay_id' => null,
            'is_active' => true,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'superadmin@barangay.gov')->delete();
    }
};
