<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature #9 — a bare recurrence_count told you "this happened before"
     * but not WHICH ticket. This links a new ticket to the most recent prior
     * closed ticket for the same fault, so "it broke again" is a clickable
     * trail, not just a number.
     */
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->foreignId('recurrence_of_ticket_id')->nullable()
                ->after('recurrence_count')
                ->constrained('maintenance_tickets', 'ticket_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_of_ticket_id');
        });
    }
};
