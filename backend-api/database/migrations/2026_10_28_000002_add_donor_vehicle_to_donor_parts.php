<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cannibalized repair can draw parts from more than one donor vehicle,
     * so each donor part records which vehicle it was taken from. Existing
     * parts belong to the sub-issue's original (single) donor.
     */
    public function up(): void
    {
        // Guarded so a re-run after a partial failure (MySQL can't roll back DDL) just continues.
        if (! Schema::hasColumn('ticket_sub_issue_donor_parts', 'donor_vehicle_id')) {
            Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
                $table->unsignedBigInteger('donor_vehicle_id')->nullable()->after('sub_issue_id');
                $table->foreign('donor_vehicle_id')->references('vehicle_id')->on('vehicles')->nullOnDelete();
            });
        }

        DB::table('ticket_sub_issue_donor_parts')
            ->whereNull('donor_vehicle_id')
            ->get()
            ->each(function ($part) {
                $donorId = DB::table('ticket_sub_issues')->where('sub_issue_id', $part->sub_issue_id)->value('source_vehicle_id');
                if ($donorId) {
                    DB::table('ticket_sub_issue_donor_parts')->where('part_id', $part->part_id)->update(['donor_vehicle_id' => $donorId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            $table->dropForeign(['donor_vehicle_id']);
            $table->dropColumn('donor_vehicle_id');
        });
    }
};
