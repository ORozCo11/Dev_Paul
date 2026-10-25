<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * External-shop repair, receipt-backed fast close: the receipt is the
     * evidence a repair happened and was paid for, standing in for the
     * usual Custodian verification when the vehicle was fixed off-site by
     * a shop instead of in-house.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->string('receipt_url')->nullable()->after('warranty_until');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropColumn('receipt_url');
        });
    }
};
