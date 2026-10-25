<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature #10 — lifetime repair spend is only a decommission signal when
     * there's something to compare it against. Optional; existing vehicles
     * with no value on file simply don't get a ratio computed.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('acquisition_cost');
        });
    }
};
