<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-tenancy: a vehicle now belongs to exactly one barangay. This is
     * the root of the tenant boundary for fleet data — tickets, maintenance
     * records/schedules, and condition/readiness checks all derive their
     * tenant from the vehicle they're attached to, rather than each
     * carrying their own copy of this column.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('vehicle_id')->constrained('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
