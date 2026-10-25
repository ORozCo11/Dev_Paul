<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a completed schedule back to the Maintenance Record it produced,
     * so a completed row can show real completion detail (date, cost,
     * verification, who did it) and a "View Record" link — instead of only
     * ever showing the original scheduled_date with nothing to trace.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('resulting_maintenance_id')->nullable()->after('recurrence_months');
            $table->foreign('resulting_maintenance_id')->references('maintenance_id')->on('vehicle_maintenance_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->dropForeign(['resulting_maintenance_id']);
            $table->dropColumn('resulting_maintenance_id');
        });
    }
};
