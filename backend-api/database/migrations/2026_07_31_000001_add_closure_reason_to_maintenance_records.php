<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision-close: an Admin ending a maintenance record WITHOUT waiting for
     * Custodian verification, mirroring the ticket workflow's decision-close
     * (see TicketController::closeTicket). The reason is mandatory there and
     * mandatory here — the point is that the record honestly says "closed
     * unverified, and here's why" instead of silently claiming it passed.
     *
     * No separate boolean is needed to spot one: a record with
     * progress_status = 'Completed' and verification_result = null was closed
     * without verification, by definition.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->text('closure_reason')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropColumn('closure_reason');
        });
    }
};
