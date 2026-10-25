<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap A — routine readiness ("ready to respond") checks.
     *
     * "Available" only ever meant "no open repair". That is not the same as
     * "this vehicle can actually respond right now" — an ambulance can be
     * unbroken yet have an empty tank, no oxygen, a dead siren. A readiness
     * check is a short, type-specific pre-deployment checklist the custodian
     * runs on a schedule; a vehicle is only "verified ready" if it passed a
     * check recently (within a freshness window). Older than that = "stale".
     */
    public function up(): void
    {
        Schema::create('vehicle_readiness_checks', function (Blueprint $table) {
            $table->id('readiness_check_id');
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->json('checklist');          // [{item, passed}, ...]
            $table->boolean('all_passed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_readiness_checks');
    }
};
