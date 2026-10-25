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
        Schema::create('vehicles', function (Blueprint $table) {
    $table->id('vehicle_id'); // Auto-generated [cite: 72]
    $table->string('vehicle_name'); // Identifies the vehicle [cite: 72]
    $table->string('plate_number')->unique(); // Official unique identifier [cite: 72]
    $table->foreignId('category_id')->constrained('vehicle_categories', 'category_id'); // Classifies the vehicle [cite: 72]
    $table->string('brand'); // Manufacturer [cite: 72]
    $table->string('model'); // Specific model [cite: 72]
    $table->integer('year_model'); // Age of vehicle [cite: 72]
    $table->string('capacity'); // Load capacity [cite: 72]
    $table->string('vehicle_color'); // Visual identification [cite: 72]
    $table->string('current_location'); // Initial stationing [cite: 72]
    $table->text('remarks')->nullable(); // Optional extra notes [cite: 72]
    
    // System-managed state trackers [cite: 74]
    $table->enum('status', ['Available', 'In Use', 'Under Maintenance', 'Inactive'])->default('Available'); // [cite: 83]
    $table->enum('condition', ['Good', 'Needs Inspection', 'Needs Repair', 'Damaged'])->default('Good'); // [cite: 86]
    $table->timestamps(); // Handles Date Added and Last Updated automatically [cite: 74]
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
