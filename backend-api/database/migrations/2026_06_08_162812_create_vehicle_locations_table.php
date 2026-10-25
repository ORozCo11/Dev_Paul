<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('vehicle_locations', function (Blueprint $table) {
    $table->id('location_record_id'); // Auto-generated [cite: 112]
    $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->onDelete('cascade'); // [cite: 112]
    $table->string('current_location'); // Station, garage, or area [cite: 114]
    $table->string('address_area')->nullable(); // Specific location details [cite: 112]
    $table->foreignId('updated_by')->constrained('users'); // User who updated location [cite: 112]
    $table->text('remarks')->nullable(); // Optional extra notes [cite: 112]
    $table->timestamps(); // Handles Date Updated automatically [cite: 112]
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_locations');
    }
};
