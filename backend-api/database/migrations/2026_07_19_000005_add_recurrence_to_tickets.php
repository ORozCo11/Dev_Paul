<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap 3 — reliability intelligence (repeat-failure detection).
     *
     * Every ticket used to be an island, so nobody noticed the same vehicle
     * failing for the same reason over and over. At creation we now compute
     * how many times this same Main Issue was already fixed-and-closed on
     * this vehicle recently, and stamp it here — so a chronic unit surfaces
     * as "3rd time" instead of hiding as three unrelated tickets.
     */
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->unsignedInteger('recurrence_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn('recurrence_count');
        });
    }
};
