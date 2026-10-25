<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fault categories (tickets' fault_category / issue reports' issue_type) and
 * maintenance types (sub-issues', maintenance records', schedules' shared
 * catalog) used to be hardcoded PHP arrays duplicated across TicketController
 * and FleetController. Moving them into tables lets a user add a new one
 * from the dropdown (like adding a new Company in a CRM) and have it show up
 * for everyone from then on, instead of being stuck with a fixed list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fault_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('fault_categories')->insert(array_map(fn ($name) => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'Engine Problem',
            'Brake Problem',
            'Tire Problem',
            'Battery Problem',
            'Electrical Problem',
            'Fuel Problem',
            'Body Damage',
            'Overheating',
            'Lights / Siren Problem',
            'Other',
        ]));

        DB::table('maintenance_types')->insert(array_map(fn ($name) => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            'General Inspection',
            'Preventive Maintenance',
            'Engine Repair',
            'Brake Repair',
            'Tire Replacement',
            'Tire Rotation',
            'Battery Replacement',
            'Oil Change',
            'Electrical Repair',
            'Body Repair',
            'Other',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_types');
        Schema::dropIfExists('fault_categories');
    }
};
