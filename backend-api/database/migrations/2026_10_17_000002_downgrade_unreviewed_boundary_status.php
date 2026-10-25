<?php

use App\Services\BarangayGeography;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Boundary accuracy QA (17 October 2026) — only an uploaded outline that
     * names its source document and was confirmed by a Super Admin counts as
     * reviewed. The 2026_10_11 'legacy' backfill and confirmed geocoder
     * (OSM) candidates were stored as 'verified' without that evidence, which
     * made the map enforce containment against unreviewed geometry.
     *
     * Geometry and the recorded applying administrator are left untouched,
     * so administrator-applied outlines are still preserved by automatic
     * refreshes; only the stored status is corrected. Not reversible: the
     * previous status was not evidence-backed.
     */
    public function up(): void
    {
        DB::table('barangays')
            ->whereNotNull('boundary')
            ->where('boundary_status', 'verified')
            ->where(function ($query) {
                $query->whereNull('boundary_verified_by')
                    ->orWhereNull('boundary_source')
                    ->orWhere('boundary_source', 'not like', BarangayGeography::UPLOADED_SOURCE_PREFIX.'%');
            })
            ->update(['boundary_status' => 'needs_review']);
    }

    public function down(): void
    {
        //
    }
};
