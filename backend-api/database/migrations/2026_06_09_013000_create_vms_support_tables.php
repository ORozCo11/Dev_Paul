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
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('vehicle_categories') && ! Schema::hasColumn('vehicle_categories', 'description')) {
            Schema::table('vehicle_categories', function (Blueprint $table) {
                $table->text('description')->nullable();
            });
        }

        if (Schema::hasTable('vehicles') && ! Schema::hasColumn('vehicles', 'fuel_type')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->string('fuel_type')->nullable()->after('capacity');
            });
        }

        if (Schema::hasTable('vehicle_maintenance_records')) {
            Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('vehicle_maintenance_records', 'verification_result')) {
                    $table->string('verification_result')->nullable();
                }

                if (! Schema::hasColumn('vehicle_maintenance_records', 'verification_notes')) {
                    $table->text('verification_notes')->nullable();
                }

                if (! Schema::hasColumn('vehicle_maintenance_records', 'verified_by')) {
                    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('vehicle_maintenance_records', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }

                if (! Schema::hasColumn('vehicle_maintenance_records', 'confirmed_by')) {
                    $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('vehicle_maintenance_records', 'confirmed_at')) {
                    $table->timestamp('confirmed_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('vehicle_condition_checks')) {
            Schema::create('vehicle_condition_checks', function (Blueprint $table) {
                $table->id('condition_check_id');
                $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
                $table->enum('condition_result', ['Good', 'Needs Inspection', 'Needs Repair', 'Damaged'])->default('Good');
                $table->text('observations')->nullable();
                $table->foreignId('checked_by')->constrained('users')->cascadeOnDelete();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vehicle_maintenance_schedules')) {
            Schema::create('vehicle_maintenance_schedules', function (Blueprint $table) {
                $table->id('schedule_id');
                $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
                $table->string('maintenance_type');
                $table->date('scheduled_date');
                $table->time('scheduled_time')->nullable();
                $table->string('service_location')->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['Scheduled', 'Completed', 'Cancelled'])->default('Scheduled');
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vehicle_histories')) {
            Schema::create('vehicle_histories', function (Blueprint $table) {
                $table->id('history_id');
                $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
                $table->string('activity_type');
                $table->text('description');
                $table->string('related_table')->nullable();
                $table->string('related_record_id')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id('log_id');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role')->nullable();
                $table->string('action');
                $table->string('module');
                $table->string('affected_record_id')->nullable();
                $table->text('details')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('vehicle_histories');
        Schema::dropIfExists('vehicle_maintenance_schedules');
        Schema::dropIfExists('vehicle_condition_checks');

        if (Schema::hasTable('vehicle_maintenance_records')) {
            Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
                $columns = [
                    'verification_result',
                    'verification_notes',
                    'verified_by',
                    'verified_at',
                    'confirmed_by',
                    'confirmed_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('vehicle_maintenance_records', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
