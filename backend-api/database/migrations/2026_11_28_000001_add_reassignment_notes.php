<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Why, when and by whom a ticket (Custodian) or a work item (mechanic) was handed to its current owner,
        // so the new owner sees a "Reassigned" mark and the reason without hunting for it.
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->timestamp('custodian_reassigned_at')->nullable();
            $table->text('custodian_reassign_reason')->nullable();
            $table->string('custodian_reassigned_by_name')->nullable();
        });
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->timestamp('reassigned_at')->nullable();
            $table->text('reassign_reason')->nullable();
            $table->string('reassigned_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', fn (Blueprint $table) => $table->dropColumn(['custodian_reassigned_at', 'custodian_reassign_reason', 'custodian_reassigned_by_name']));
        Schema::table('ticket_sub_issues', fn (Blueprint $table) => $table->dropColumn(['reassigned_at', 'reassign_reason', 'reassigned_by_name']));
    }
};
