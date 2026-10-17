<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Demo accounts from UserSeeder were created active but without an
     * approved_at stamp, so the Super Admin "Pending Approvals" list (which
     * keys off approved_at IS NULL) showed working staff as unapproved
     * applicants. Registration always creates accounts inactive, so an active
     * account with no stamp was never a real pending application — stamp it
     * with its creation time, matching the 2026_08_18 approved_at backfill.
     * Not reversible: the null was never meaningful for these rows.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('is_active', true)
            ->whereNull('approved_at')
            ->update(['approved_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        //
    }
};
