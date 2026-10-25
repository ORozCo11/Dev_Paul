<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * vehicle_condition_checks.checked_by used cascadeOnDelete() on users,
     * which meant deleting a user would silently delete their entire
     * condition-check history — the same class of bug already fixed for
     * vehicle_histories.updated_by (nullOnDelete()). A condition check is a
     * historical record of the vehicle's condition at a point in time; it
     * should survive the user who filed it being removed, same as every
     * other "who did this" column in this app.
     */
    public function up(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
        });

        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('checked_by')->nullable()->change();
        });

        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->foreign('checked_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
        });

        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('checked_by')->nullable(false)->change();
        });

        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->foreign('checked_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
