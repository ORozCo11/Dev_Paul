<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap 4 — vehicle end-of-life (decommission).
     *
     * A vehicle used to only be Available / Under Maintenance / Inactive.
     * "Inactive" is a vague, reversible archive — it can't tell a temporary
     * hide apart from a permanent write-off. "Decommissioned" is a distinct,
     * reason-stamped end-of-life state (beyond economical repair, too old,
     * totalled) that removes the unit from readiness/coverage for good while
     * keeping its full history. Same "a big decision must be documented"
     * discipline as a Deferred sub-issue, applied to the whole vehicle.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->text('decommission_reason')->nullable();
            $table->unsignedBigInteger('decommissioned_by')->nullable();
            $table->timestamp('decommissioned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['decommission_reason', 'decommissioned_by', 'decommissioned_at']);
        });
    }
};
