<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshotted at write time from the acting user's own barangay_id
     * (see BelongsToBarangay's creating-event stamp), not derived live from
     * a relation — an audit trail should keep reading the tenant it was
     * actually written under, not whatever the actor's barangay is today.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('log_id')->constrained('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
