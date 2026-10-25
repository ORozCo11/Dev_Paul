<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive columns for several planned enhancements:
     *  - #8 fault_category: a standardized fault category on a ticket (from the
     *    same catalog as issue_type) so recurrence (#9) and cost (#10) can group
     *    reliably instead of matching free-text titles.
     *  - #3 down_since: when the vehicle actually became unavailable, so the
     *    downtime metric reflects reality rather than ticket-creation time.
     *  - #5 external repair: vendor + warranty for third-party shop repairs.
     */
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('fault_category')->nullable()->after('ticket_title');
            $table->timestamp('down_since')->nullable()->after('status');
        });

        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('maintenance_personnel_id');
            $table->string('external_vendor')->nullable()->after('is_external');
            $table->date('warranty_until')->nullable()->after('external_vendor');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn(['fault_category', 'down_since']);
        });
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['is_external', 'external_vendor', 'warranty_until']);
        });
    }
};
