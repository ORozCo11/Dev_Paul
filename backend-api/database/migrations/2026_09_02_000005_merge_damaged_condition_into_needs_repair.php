<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Damaged" and "Needs Repair" were treated identically everywhere in the
 * app already (every check that cared about one cared about both) — folded
 * into a single value. This is a data-only backfill: the enum CHECK
 * constraints on these columns were already dropped in an earlier migration
 * (2026_07_25_000002), so no schema change is needed, just updating any
 * existing rows that still carry the now-retired value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vehicles')->where('condition', 'Damaged')->update(['condition' => 'Needs Repair']);
        DB::table('vehicle_condition_checks')->where('condition_result', 'Damaged')->update(['condition_result' => 'Needs Repair']);
    }

    public function down(): void
    {
        // Not reversible — once merged, there's no way to tell which rows
        // were originally "Damaged" vs "Needs Repair".
    }
};
