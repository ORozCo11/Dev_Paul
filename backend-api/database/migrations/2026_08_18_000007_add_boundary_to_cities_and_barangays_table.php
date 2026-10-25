<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // Raw GeoJSON geometry (Polygon or MultiPolygon), sourced from the
            // PSA's PSGC shapefiles — lets the Vehicle Location map draw a
            // boundary outline for whichever city an admin selects, not just
            // the hardcoded Paknaan polygon.
            $table->json('boundary')->nullable()->after('code');
        });

        Schema::table('barangays', function (Blueprint $table) {
            $table->json('boundary')->nullable()->after('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('boundary');
        });

        Schema::table('barangays', function (Blueprint $table) {
            $table->dropColumn('boundary');
        });
    }
};
