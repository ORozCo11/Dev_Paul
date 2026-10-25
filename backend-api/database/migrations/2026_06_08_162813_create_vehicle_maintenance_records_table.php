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
       Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
    $table->id('maintenance_id'); // Auto-generated [cite: 149]
    $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->onDelete('cascade'); // [cite: 149]
    $table->foreignId('issue_report_id')->nullable()->constrained('vehicle_issue_reports', 'issue_report_id')->onDelete('set null'); // Optional link to a field complaint [cite: 149]
    $table->string('maintenance_type'); // Preventive, Repair, Oil Change [cite: 149, 151]
    $table->text('problem_reason'); // Why it needs maintenance [cite: 149]
    $table->date('date_started')->nullable(); // [cite: 149]
    $table->date('date_completed')->nullable(); // Required only if finished [cite: 149]
    $table->foreignId('maintenance_personnel_id')->constrained('users'); // Assigned mechanic [cite: 149]
    $table->text('action_taken')->nullable(); // Work done on the vehicle [cite: 149]
    $table->text('parts_used')->nullable(); // Materials used [cite: 149, 233]
    $table->enum('progress_status', ['Assigned', 'Under Repair', 'For Verification', 'Completed'])->default('Assigned');
    $table->text('remarks')->nullable(); // Final notes [cite: 248]
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
    }
};
