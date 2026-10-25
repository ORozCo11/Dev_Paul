<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redesigns the ticket workflow: a ticket is now a Main Issue container
     * (e.g. "Overheating") holding one or more independently-tracked
     * Sub-Issues (e.g. "low coolant level"), each running its own
     * assign -> repair -> verify -> confirm pipeline with its own mechanic.
     *
     * A ticket's progress is X/N sub-issues Done. Sub-issues can be
     * appended to a ticket while it's Active; once an Admin explicitly
     * Closes the ticket, it is permanently locked — no further sub-issues,
     * no reopening. Data here is dev/test data, so the old ticket tables
     * are rebuilt rather than patched in place.
     *
     * Ticket status lifecycle: Open -> Active -> Closed / Cancelled
     * Sub-issue status lifecycle: Open -> Under Repair -> For Inspection
     *                              -> For Confirmation -> Done
     */
    public function up(): void
    {
        Schema::dropIfExists('ticket_archive_logs');
        Schema::dropIfExists('maintenance_tickets');

        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id('ticket_id');

            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issue_report_id')->nullable()->constrained('vehicle_issue_reports', 'issue_report_id')->nullOnDelete();

            $table->string('ticket_title'); // the Main Issue, e.g. "Overheating"
            $table->text('ticket_description');
            $table->string('priority')->default('Medium');
            $table->string('status')->default('Open'); // Open, Active, Closed, Cancelled

            // Phase 1: Custodian assignment for inspection
            $table->foreignId('assigned_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Phase 2: Initial inspection — determines the sub-issue list
            $table->text('inspection_notes')->nullable();
            $table->string('inspection_result')->nullable(); // 'Needs Maintenance', 'No Issues'
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();

            // Explicit closing — only allowed when every sub-issue is Done
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_notes')->nullable();

            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
        });

        Schema::create('ticket_sub_issues', function (Blueprint $table) {
            $table->id('sub_issue_id');

            $table->foreignId('ticket_id')->constrained('maintenance_tickets', 'ticket_id')->cascadeOnDelete();
            $table->foreignId('issue_report_id')->nullable()->constrained('vehicle_issue_reports', 'issue_report_id')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('title'); // e.g. "Low coolant level"
            $table->string('status')->default('Open'); // Open, Under Repair, For Inspection, For Confirmation, Done

            // Assignment (Work Order) — independent per sub-issue
            $table->foreignId('assigned_mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('maintenance_type')->nullable();
            $table->text('work_order_notes')->nullable();
            $table->timestamp('mechanic_assigned_at')->nullable();
            $table->foreignId('mechanic_assigned_by')->nullable()->constrained('users')->nullOnDelete();

            // Repair logging
            $table->text('repair_logs')->nullable();
            $table->text('parts_used')->nullable();
            $table->date('repair_started_at')->nullable();
            $table->date('repair_completed_at')->nullable();
            $table->decimal('maintenance_cost', 10, 2)->nullable();

            // Tier 1 — Custodian verification
            $table->string('verification_verdict')->nullable(); // Approved, Rejected
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Tier 2 — Admin confirmation of this line item
            $table->string('confirmation_verdict')->nullable(); // Confirmed, Reopened
            $table->text('confirmation_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });

        // Phase 5: Archived tickets log (immutable, tamper-proof audit trail).
        // full_ticket_snapshot now embeds the full sub_issues checklist.
        Schema::create('ticket_archive_logs', function (Blueprint $table) {
            $table->id('archive_id');
            $table->foreignId('ticket_id')->constrained('maintenance_tickets', 'ticket_id')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->string('ticket_title');
            $table->string('vehicle_name');
            $table->string('plate_number');
            $table->string('final_status');
            $table->decimal('maintenance_cost', 10, 2)->nullable(); // sum across sub-issues
            $table->json('full_ticket_snapshot');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->useCurrent();
            // No timestamps() — intentionally immutable (no updated_at)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_archive_logs');
        Schema::dropIfExists('ticket_sub_issues');
        Schema::dropIfExists('maintenance_tickets');

        // Restore the previous flat-ticket schema shape so `down` is coherent.
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id('ticket_id');
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_title');
            $table->text('ticket_description');
            $table->string('priority')->default('Medium');
            $table->string('status')->default('Open');
            $table->foreignId('assigned_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->string('inspection_result')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('assigned_mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('maintenance_type')->nullable();
            $table->text('work_order_notes')->nullable();
            $table->timestamp('mechanic_assigned_at')->nullable();
            $table->foreignId('mechanic_assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('repair_logs')->nullable();
            $table->text('parts_used')->nullable();
            $table->date('repair_started_at')->nullable();
            $table->date('repair_completed_at')->nullable();
            $table->decimal('maintenance_cost', 10, 2)->nullable();
            $table->string('verification_verdict')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('confirmation_verdict')->nullable();
            $table->text('confirmation_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('issue_report_id')->nullable()->constrained('vehicle_issue_reports', 'issue_report_id')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_archive_logs', function (Blueprint $table) {
            $table->id('archive_id');
            $table->foreignId('ticket_id')->constrained('maintenance_tickets', 'ticket_id')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->string('ticket_title');
            $table->string('vehicle_name');
            $table->string('plate_number');
            $table->string('final_status');
            $table->decimal('maintenance_cost', 10, 2)->nullable();
            $table->json('full_ticket_snapshot');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->useCurrent();
        });
    }
};
