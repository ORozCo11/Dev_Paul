<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salvageable parts logged from a decommissioned vehicle. quantity_available goes down when a repair draws from it.
        Schema::create('salvage_parts', function (Blueprint $table) {
            $table->id('salvage_part_id');
            $table->unsignedBigInteger('barangay_id')->nullable()->index();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->string('part_name');
            $table->string('category', 80)->nullable();
            $table->string('part_condition', 30)->default('Good');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('quantity_available')->default(1);
            $table->string('storage_location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('logged_by')->nullable();
            $table->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            // The mechanic has finished listing what can be salvaged from this decommissioned vehicle.
            $table->timestamp('salvage_completed_at')->nullable();
            $table->unsignedBigInteger('salvage_completed_by')->nullable();
        });

        Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            // Set when the part was drawn from the salvage inventory (its stock was reduced by this quantity).
            $table->unsignedBigInteger('salvage_part_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issue_donor_parts', fn (Blueprint $table) => $table->dropColumn('salvage_part_id'));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['salvage_completed_at', 'salvage_completed_by']));
        Schema::dropIfExists('salvage_parts');
    }
};
