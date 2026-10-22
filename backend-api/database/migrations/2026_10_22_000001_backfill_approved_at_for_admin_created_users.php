<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * UserController::store used to create active accounts without stamping
     * approved_at, so every Admin-created staff member read as a pending
     * registration in the Super Admin portal. Self-registrations always start
     * inactive and every activation path stamps approved_at, so an active
     * account with no stamp can only have come from that path.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('approved_at')
            ->where('is_active', true)
            ->update(['approved_at' => DB::raw('created_at')]);
    }

    /**
     * Not reversible — the backfilled rows can't be told apart afterwards.
     */
    public function down(): void
    {
    }
};
