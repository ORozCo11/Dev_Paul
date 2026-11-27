<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // The Custodian proposes; the Admin approves (the vehicle becomes Decommissioned) or declines.
            // decommission_status: null (nothing pending), 'Proposed', or 'Declined' (kept so the Custodian sees why).
            $table->string('decommission_status', 20)->nullable();
            $table->text('decommission_proposal_reason')->nullable();
            $table->unsignedBigInteger('decommission_proposed_by')->nullable();
            $table->string('decommission_proposed_by_name')->nullable();
            $table->timestamp('decommission_proposed_at')->nullable();
            $table->text('decommission_decline_reason')->nullable();
            $table->timestamp('decommission_declined_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['decommission_status', 'decommission_proposal_reason', 'decommission_proposed_by', 'decommission_proposed_by_name', 'decommission_proposed_at', 'decommission_decline_reason', 'decommission_declined_at']);
        });
    }
};
