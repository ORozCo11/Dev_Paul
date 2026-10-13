<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cannibalization as a direct record of what happened: parts taken off a
 * source vehicle and where they went. There is no approval lifecycle; a saved
 * record is complete and is never edited or deleted. New tables only —
 * the per-ticket donor fields on ticket_sub_issues are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cannibalization_records', function (Blueprint $table) {
            $table->id('cannibalization_id');
            $table->string('record_number')->nullable()->unique();
            $table->unsignedBigInteger('source_vehicle_id');
            $table->string('destination_type', 20);            // vehicle | storage
            $table->unsignedBigInteger('destination_vehicle_id')->nullable();
            $table->string('storage_location')->nullable();
            $table->text('reason');
            $table->text('source_reason');                       // why parts leave the source vehicle
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by');           // always the signed-in user
            $table->string('performed_by_name')->nullable();     // who physically removed the parts
            $table->dateTime('removed_at');
            $table->string('received_by_name')->nullable();      // who received / installed them
            $table->dateTime('transferred_at')->nullable();
            $table->json('evidence_urls')->nullable();
            $table->timestamps();

            $table->index('source_vehicle_id');
            $table->index('destination_vehicle_id');
        });

        Schema::create('cannibalization_parts', function (Blueprint $table) {
            $table->id('part_id');
            $table->unsignedBigInteger('cannibalization_id');
            $table->string('part_name');
            $table->string('part_number')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('unit', 30)->default('pc');
            $table->string('part_condition', 100)->nullable();
            $table->text('installation_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('cannibalization_id')->references('cannibalization_id')->on('cannibalization_records')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cannibalization_parts');
        Schema::dropIfExists('cannibalization_records');
    }
};
