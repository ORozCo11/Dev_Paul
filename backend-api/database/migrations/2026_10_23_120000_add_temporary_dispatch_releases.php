<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_usage_logs', function (Blueprint $table) {
            $table->json('temporary_release')->nullable();
        });
        Schema::create('ticket_dispatch_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('maintenance_tickets', 'ticket_id')->cascadeOnDelete();
            $table->foreignId('assessed_by')->constrained('users');
            $table->boolean('safe_to_operate');
            $table->boolean('assembled');
            $table->boolean('no_safety_critical_fault');
            $table->text('notes');
            $table->string('context_hash', 64);
            $table->timestamp('assessed_at');
            $table->index(['ticket_id', 'assessed_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_dispatch_assessments');
        Schema::table('vehicle_usage_logs', fn (Blueprint $table) => $table->dropColumn('temporary_release'));
    }
};
