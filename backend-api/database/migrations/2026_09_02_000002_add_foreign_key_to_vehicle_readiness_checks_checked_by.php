<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * vehicle_readiness_checks.checked_by was a plain unsignedBigInteger with
     * no foreign key at all — unlike every other "who did this" column in
     * this app (e.g. vehicle_histories.updated_by, vehicle_maintenance_schedules
     * .assigned_to), which are real FKs with nullOnDelete(). The column is
     * already nullable, so this just adds the missing constraint, consistent
     * with that convention: the readiness-check row survives the user who
     * performed it being deleted.
     */
    public function up(): void
    {
        Schema::table('vehicle_readiness_checks', function (Blueprint $table) {
            $table->foreign('checked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_readiness_checks', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
        });
    }
};
