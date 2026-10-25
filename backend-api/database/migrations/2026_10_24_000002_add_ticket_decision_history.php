<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->text('correction_reason')->nullable();
            $table->timestamp('correction_requested_at')->nullable();
            $table->json('decision_history')->nullable();
            $table->unsignedInteger('decision_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn(['correction_reason', 'correction_requested_at', 'decision_history', 'decision_revision']);
        });
    }
};
