<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Condition Check to the ticket it was escalated into (if any),
 * same pattern as vehicle_maintenance_schedules.resulting_maintenance_id.
 * Set once, at ticket-creation time, and never changed again — the
 * Condition Monitoring table reads the linked ticket's live status through
 * this to show what became of a finding, without ever mutating the
 * historical check row itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('resulting_ticket_id')->nullable()->after('condition_result');
            $table->foreign('resulting_ticket_id')->references('ticket_id')->on('maintenance_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->dropForeign(['resulting_ticket_id']);
            $table->dropColumn('resulting_ticket_id');
        });
    }
};
