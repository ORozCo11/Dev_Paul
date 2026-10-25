<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-role accounts. `role` stays as the PRIMARY role (routing,
     * dashboards, display); `roles` holds every hat the account may wear
     * (permission checks). This lets one person in a small barangay hold,
     * e.g., ['Custodian', 'Maintenance Personnel'] and act under each — no
     * new merged role, just multiple existing roles on one account.
     *
     * Existing users are backfilled to roles = [their current role], so a
     * single-role account is simply a list of one and nothing breaks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role');
        });

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'roles' => json_encode(array_values(array_filter([$user->role]))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
