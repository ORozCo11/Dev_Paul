<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the maintenance_tickets table — the central Ticket Ledger
     * described in the 5-phase DFD workflow. Every vehicle maintenance
     * cycle is tracked here from creation to archive.
     *
     * Ticket Status lifecycle:
     *   Open → For Maintenance → For Inspection → For Confirmation → Done
     */
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id('ticket_id');

            // Phase 1: Created by Admin
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_title');
            $table->text('ticket_description');
            $table->string('priority')->default('Medium'); // Low, Medium, High, Critical
            $table->enum('status', [
                'Open',             // Phase 1: Ticket created, assigned to Custodian for inspection
                'For Maintenance',  // Phase 2: Custodian found issues, needs mechanic
                'Under Repair',     // Phase 3: Mechanic assigned and working
                'For Inspection',   // Phase 3→4: Mechanic done, Custodian must verify
                'For Confirmation', // Phase 4 Tier 2: Custodian approved, Admin must confirm
                'Done',             // Phase 4 Tier 2: Admin confirmed — ticket closed
                'Cancelled',        // Admin cancelled at any point
            ])->default('Open');

            // Phase 1: Custodian assignment (Inspection Assignment payload)
            $table->foreignId('assigned_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Phase 2: Custodian inspection result
            $table->text('inspection_notes')->nullable();
            $table->string('inspection_result')->nullable(); // 'Needs Maintenance', 'No Issues'
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();

            // Phase 3: Mechanic assignment (Work Order payload)
            $table->foreignId('assigned_mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('maintenance_type')->nullable();
            $table->text('work_order_notes')->nullable();
            $table->timestamp('mechanic_assigned_at')->nullable();
            $table->foreignId('mechanic_assigned_by')->nullable()->constrained('users')->nullOnDelete();

            // Phase 3: Mechanic repair logs
            $table->text('repair_logs')->nullable();
            $table->text('parts_used')->nullable();
            $table->date('repair_started_at')->nullable();
            $table->date('repair_completed_at')->nullable();

            // Phase 4 Tier 1: Custodian verification
            $table->string('verification_verdict')->nullable(); // 'Approved', 'Rejected'
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Phase 4 Tier 2: Admin confirmation
            $table->string('confirmation_verdict')->nullable(); // 'Confirmed', 'Reopened'
            $table->text('confirmation_notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            // Phase 5: Archiving
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
        });

        // Phase 5: Archived tickets log (immutable, tamper-proof audit trail)
        Schema::create('ticket_archive_logs', function (Blueprint $table) {
            $table->id('archive_id');
            $table->foreignId('ticket_id')->constrained('maintenance_tickets', 'ticket_id')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->string('ticket_title');
            $table->string('vehicle_name');
            $table->string('plate_number');
            $table->string('final_status');
            $table->json('full_ticket_snapshot'); // Immutable JSON snapshot of the completed ticket
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->useCurrent();
            // No timestamps() — this is intentionally immutable (no updated_at)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_archive_logs');
        Schema::dropIfExists('maintenance_tickets');
    }
};
