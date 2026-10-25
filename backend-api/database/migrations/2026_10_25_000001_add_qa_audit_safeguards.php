<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('readiness_invalidated_at')->nullable();
        });
        // Existing readiness evidence predates this enforcement boundary.
        \Illuminate\Support\Facades\DB::table('vehicles')->update(['readiness_invalidated_at' => now()]);
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonator_user_id')->nullable();
            $table->text('impersonation_reason')->nullable();
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonator_user_id')->nullable();
            $table->unsignedBigInteger('impersonation_session_id')->nullable();
            $table->text('impersonation_reason')->nullable();
        });
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->boolean('test_attested')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', fn (Blueprint $table) => $table->dropColumn('test_attested'));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropColumn(['impersonator_user_id', 'impersonation_session_id', 'impersonation_reason']));
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['impersonator_user_id', 'impersonation_reason']));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('readiness_invalidated_at'));
    }
};
