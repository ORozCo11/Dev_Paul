<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every vehicle/hub/log/user/registration-setting created before
     * multi-tenancy existed has no barangay_id yet — without this, they'd
     * become invisible to everyone (global scopes filter by barangay_id,
     * and null never matches). Anchor all of it to Paknaan, the barangay
     * already baked into this install's seed data (its hub, its vehicles,
     * its existing Admin).
     */
    public function up(): void
    {
        $paknaanId = DB::table('barangays')->where('name', 'Paknaan')->value('id');

        if (!$paknaanId) {
            return;
        }

        DB::table('vehicles')->whereNull('barangay_id')->update(['barangay_id' => $paknaanId]);
        DB::table('vehicle_hubs')->whereNull('barangay_id')->update(['barangay_id' => $paknaanId]);
        DB::table('activity_logs')->whereNull('barangay_id')->update(['barangay_id' => $paknaanId]);
        DB::table('users')->whereNull('barangay_id')->update(['barangay_id' => $paknaanId]);
        DB::table('registration_settings')->whereNull('barangay_id')->update(['barangay_id' => $paknaanId]);
    }

    public function down(): void
    {
        // Not reversible — we can't tell which rows were null before this ran.
    }
};
