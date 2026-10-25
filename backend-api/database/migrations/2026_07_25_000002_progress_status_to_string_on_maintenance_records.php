<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature #4 — allow an "On Hold - Awaiting Parts" progress state.
     *
     * progress_status was an enum, whose SQLite CHECK constraint would reject
     * any new value. Rather than fight the constraint, widen the column to a
     * plain string; the allowed set is already enforced at the application
     * layer (Rule::in in FleetController), which is the single source of truth.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->string('progress_status', 50)->default('Assigned')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->enum('progress_status', ['Assigned', 'Under Repair', 'For Verification', 'Completed'])
                ->default('Assigned')->change();
        });
    }
};
