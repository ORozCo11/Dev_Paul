<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * maintenance_personnel_id was never actually nullable at the schema
     * level, even though the controller's validation always treated it as
     * optional (it just never sent an empty value before). Now that
     * performed_by_other can be the "who did it" answer instead, this
     * column has to genuinely allow null — exactly one of the two is set,
     * never both.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->foreignId('maintenance_personnel_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->foreignId('maintenance_personnel_id')->nullable(false)->change();
        });
    }
};
