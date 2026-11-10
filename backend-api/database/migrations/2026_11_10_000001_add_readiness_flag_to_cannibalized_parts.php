<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cannibalization rules from the November 2026 redefense:
 *  - each missing part says whether the donor vehicle can still respond without it
 *    (blocks_readiness). Rows that already exist default to "blocks", so nothing
 *    becomes Ready by accident;
 *  - a record says whether the donor is an active or a retired vehicle, and which
 *    repair ticket the parts went into.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cannibalization_parts', 'blocks_readiness')) {
            Schema::table('cannibalization_parts', function (Blueprint $table) {
                $table->boolean('blocks_readiness')->default(true)->after('quantity');
            });
        }
        if (! Schema::hasColumn('ticket_sub_issue_donor_parts', 'blocks_readiness')) {
            Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
                $table->boolean('blocks_readiness')->default(true)->after('quantity');
            });
        }
        if (! Schema::hasColumn('cannibalization_records', 'donor_type')) {
            Schema::table('cannibalization_records', function (Blueprint $table) {
                $table->string('donor_type', 10)->default('active')->after('source_vehicle_id'); // active | retired
                $table->unsignedBigInteger('ticket_id')->nullable()->after('donor_type');
                $table->index('ticket_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cannibalization_parts', function (Blueprint $table) {
            $table->dropColumn('blocks_readiness');
        });
        Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            $table->dropColumn('blocks_readiness');
        });
        Schema::table('cannibalization_records', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
            $table->dropColumn(['donor_type', 'ticket_id']);
        });
    }
};
