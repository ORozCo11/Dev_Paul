<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap 2 — recurring preventive maintenance.
     *
     * A schedule used to be a one-off calendar note: completing it left no
     * next service, so someone had to remember to hand-create every future
     * one. `recurrence_months` (null = one-time) lets completing a schedule
     * auto-create the next due date, so prevention keeps itself alive.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->unsignedInteger('recurrence_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->dropColumn('recurrence_months');
        });
    }
};
