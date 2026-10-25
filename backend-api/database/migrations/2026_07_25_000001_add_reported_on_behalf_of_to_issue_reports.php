<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature #6 — capture the real eyewitness on a relayed report. Drivers
     * are not system users, so when a Custodian/Admin files an issue a driver
     * phoned in, this holds the driver's name. Optional free text; no new role.
     */
    public function up(): void
    {
        Schema::table('vehicle_issue_reports', function (Blueprint $table) {
            $table->string('reported_on_behalf_of')->nullable()->after('reported_by');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_issue_reports', function (Blueprint $table) {
            $table->dropColumn('reported_on_behalf_of');
        });
    }
};
