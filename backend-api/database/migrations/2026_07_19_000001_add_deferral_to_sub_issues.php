<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Problem 1 — "Deferred" outcome + decision-close.
     *
     * A sub-issue used to have exactly one exit ("Done"). That trapped a
     * whole ticket whenever a single line item could not be finished (no
     * budget, part on back-order, etc.) — the ticket could never reach N/N,
     * so the vehicle stayed benched indefinitely.
     *
     * "Deferred" is the second exit: a recorded decision NOT to fix this
     * item now, with a mandatory reason and who decided. A ticket can then
     * be closed once every sub-issue is *resolved* (Done OR Deferred).
     *
     * deferred_issue_report_id is the "breadcrumb": when an item is
     * deferred we auto-open a fresh Issue Report so the known-but-unfixed
     * defect is never forgotten, and we keep its id here for tracing.
     *
     * returned_to_service records the fit-for-service call made at a
     * decision-close: closing a ticket no longer *automatically* returns
     * the vehicle to Available when something was left unfixed.
     */
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->text('deferred_reason')->nullable();
            // Plain nullable columns (no DB-level FK) — SQLite cannot add a
            // foreign-key constraint to an existing table, and the Eloquent
            // relationships work without one.
            $table->unsignedBigInteger('deferred_by')->nullable();
            $table->timestamp('deferred_at')->nullable();
            $table->unsignedBigInteger('deferred_issue_report_id')->nullable();
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            // null = never closed with a fit-for-service decision;
            // true/false = the call the Admin made at a decision-close.
            $table->boolean('returned_to_service')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn(['deferred_reason', 'deferred_by', 'deferred_at', 'deferred_issue_report_id']);
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn('returned_to_service');
        });
    }
};
