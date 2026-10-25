<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicle cannibalization: a part removed from one vehicle to repair
     * another. source_vehicle_id names the DONOR vehicle so the loss is
     * traceable from its own history, instead of only existing as a free-text
     * mention buried in the recipient's Parts Used field.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('source_vehicle_id')->nullable()->after('vehicle_id');
            $table->foreign('source_vehicle_id')->references('vehicle_id')->on('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['source_vehicle_id']);
            $table->dropColumn('source_vehicle_id');
        });
    }
};
