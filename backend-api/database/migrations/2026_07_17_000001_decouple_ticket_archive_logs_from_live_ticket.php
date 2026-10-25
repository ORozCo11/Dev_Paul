<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An archive log is meant to survive independently of the live ticket
     * it was snapshotted from — that's the whole point of storing a
     * self-contained JSON snapshot. The original `ticket_id` foreign key
     * used cascadeOnDelete(), which meant deleting a ticket that already
     * had an archive row (e.g. an accidentally-deleted in-progress ticket,
     * archived as "Deleted" so it can be Reopened) would immediately wipe
     * out that same archive row the moment the live ticket was removed.
     * `ticket_id` here becomes a plain historical reference number instead
     * of an enforced foreign key.
     */
    public function up(): void
    {
        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('ticket_id')->on('maintenance_tickets')->cascadeOnDelete();
        });
    }
};
