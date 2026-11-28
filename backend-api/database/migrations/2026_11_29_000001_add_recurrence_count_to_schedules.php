<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Repeat every 3 months, 4 times": the total number of services in the series (null = until stopped)
        // and which one in the series this schedule is.
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('recurrence_total')->nullable()->after('recurrence_months');
            $table->unsignedSmallInteger('recurrence_number')->default(1)->after('recurrence_total');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_schedules', fn (Blueprint $table) => $table->dropColumn(['recurrence_total', 'recurrence_number']));
    }
};
