<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Performed By" doesn't always match a registered user — a Custodian or
     * Admin logging a repair might name someone outside the system entirely
     * (a volunteer, an outside helper). This is a free-text fallback,
     * mutually exclusive with maintenance_personnel_id: exactly one of the
     * two is ever set for a given record, never both.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->string('performed_by_other')->nullable()->after('maintenance_personnel_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropColumn('performed_by_other');
        });
    }
};
