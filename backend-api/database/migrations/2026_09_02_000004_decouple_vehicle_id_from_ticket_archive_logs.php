<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors 2026_07_17_000001_decouple_ticket_archive_logs_from_live_ticket.php,
     * which dropped the cascadeOnDelete() foreign key on `ticket_id` for
     * exactly this reason: an archive row is meant to survive independently
     * of the live records it was snapshotted from — that's the whole point
     * of storing a self-contained JSON snapshot. `vehicle_id` on the same
     * table was never given the same treatment and still cascades, so
     * hard-deleting a vehicle would silently wipe out its archived ticket
     * history along with it.
     *
     * There is currently no vehicle hard-delete endpoint (only the
     * soft-archive path via decommissionVehicle), so this is unreachable
     * today — but it's the same latent gap `ticket_id` had, fixed here for
     * consistency before any hard-delete path is ever added. `vehicle_id`
     * becomes a plain historical reference number instead of an enforced
     * foreign key, same as `ticket_id` already is.
     */
    public function up(): void
    {
        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->foreign('vehicle_id')->references('vehicle_id')->on('vehicles')->cascadeOnDelete();
        });
    }
};
