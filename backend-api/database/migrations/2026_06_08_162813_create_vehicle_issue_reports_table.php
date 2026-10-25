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
       Schema::create('vehicle_issue_reports', function (Blueprint $table) {
    $table->id('issue_report_id'); // Auto-generated [cite: 138]
    $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->onDelete('cascade'); // [cite: 138]
    $table->string('issue_type'); // Category of problem [cite: 138]
    $table->text('issue_description'); // Full explanation [cite: 138]
    $table->enum('severity_level', ['Low', 'Medium', 'High', 'Critical'])->default('Medium'); // [cite: 142]
    $table->string('photo_url')->nullable(); // Public secure string URL from Supabase storage [cite: 9]
    $table->foreignId('reported_by')->constrained('users'); // Logged-in custodian [cite: 138, 206]
    $table->enum('status', ['Pending', 'Under Review', 'In Maintenance', 'Resolved'])->default('Pending'); // [cite: 136]
    $table->text('remarks')->nullable(); // Extra administrative notes [cite: 138]
    $table->timestamps(); // Date Reported [cite: 138]
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_issue_reports');
    }
};
