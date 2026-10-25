<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRAGMA index_list showed only explicit unique() constraints had any
     * index at all — every foreign key and every column this app filters
     * on constantly (the per-tenant `barangay_id` scope from
     * BelongsToBarangay, plus the hot-path lookups below) was doing a full
     * table scan. Adds the missing secondary indexes; no column types or
     * constraints change, so this is additive and safe to run against
     * existing data.
     */
    public function up(): void
    {
        // barangay_id — filtered on every request via BelongsToBarangay's
        // global scope (vehicles, vehicle_hubs, activity_logs) and via
        // explicit ->where('barangay_id', ...) calls in UserController /
        // TicketController (users).
        Schema::table('vehicles', function (Blueprint $table) {
            $table->index('barangay_id');
        });

        Schema::table('vehicle_hubs', function (Blueprint $table) {
            $table->index('barangay_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('barangay_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('barangay_id');
        });

        // maintenance_tickets — TicketController repeatedly filters a
        // vehicle's tickets by status together (open-tickets-by-vehicle,
        // eligible-to-close checks, etc).
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->index(['vehicle_id', 'status']);
        });

        // vehicle_maintenance_schedules — FleetController's dashboard /
        // schedule-completion logic filters a vehicle's schedules by status
        // and scheduled_date together (overdue/upcoming/due-soon queries).
        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->index(['vehicle_id', 'status', 'scheduled_date']);
        });

        // notifications — NotificationController lists/marks-read a user's
        // notifications filtered by read_at together.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['barangay_id']);
        });

        Schema::table('vehicle_hubs', function (Blueprint $table) {
            $table->dropIndex(['barangay_id']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['barangay_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['barangay_id']);
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'status']);
        });

        Schema::table('vehicle_maintenance_schedules', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'status', 'scheduled_date']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
        });
    }
};
