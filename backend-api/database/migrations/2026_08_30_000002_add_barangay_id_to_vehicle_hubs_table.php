<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hub is a standalone location (not created in the context of any
     * vehicle — see VehicleHub's schema), so it needs its own tenant anchor
     * rather than deriving one through a relation.
     */
    public function up(): void
    {
        Schema::table('vehicle_hubs', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('hub_id')->constrained('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_hubs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
