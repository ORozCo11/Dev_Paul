<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Real boundary polygon for Mantalongon (Dalaguete, Cebu), sourced from
     * the GADM-derived barangay dataset in faeldon/philippines-json-maps
     * (2011 vintage — barangay boundaries rarely change, and this is a
     * decorative map outline, not an exact administrative shape, same as
     * every other barangay boundary in this table). Idempotent: only fills
     * it in if Mantalongon exists and doesn't already have one.
     *
     * Scoped to Dalaguete: a bare name match could attach this outline to a
     * same-named barangay elsewhere. The status backfill that follows marks
     * it 'legacy', which is treated as an unverified reference, not a
     * reviewed boundary.
     */
    public function up(): void
    {
        $boundary = json_encode([
            'type' => 'Polygon',
            'coordinates' => [[
                [123.45967864990234, 9.828519821167106],
                [123.47341918945312, 9.827300071716309],
                [123.4790496826173, 9.813890457153377],
                [123.47139739990234, 9.79500961303711],
                [123.45223236083984, 9.817850112915039],
                [123.45967864990234, 9.828519821167106],
            ]],
        ]);

        DB::table('barangays')
            ->where('name', 'Mantalongon')
            ->whereIn('city_id', DB::table('cities')->where('name', 'like', 'Dalaguete%')->select('id'))
            ->whereNull('boundary')
            ->update(['boundary' => $boundary]);
    }

    public function down(): void
    {
        DB::table('barangays')
            ->where('name', 'Mantalongon')
            ->whereIn('city_id', DB::table('cities')->where('name', 'like', 'Dalaguete%')->select('id'))
            ->update(['boundary' => null]);
    }
};
