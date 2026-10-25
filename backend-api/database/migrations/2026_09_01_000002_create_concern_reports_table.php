<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public "Report a Concern" submissions from the Support Center page —
     * no account required to submit, since the reporter may be a resident
     * flagging a barangay whose Admin has gone unresponsive, or a fraud
     * concern about a registered account. A Super Admin reviews these and
     * acts using the tools already in their workspace (promote/recover an
     * orphaned barangay, deactivate a suspicious account).
     */
    public function up(): void
    {
        Schema::create('concern_reports', function (Blueprint $table) {
            $table->id();
            $table->string('concern_type'); // 'Barangay Inactive', 'Suspected Fake Staff', 'Other'
            $table->string('barangay_name')->nullable();
            $table->text('description');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->string('status')->default('Open'); // 'Open', 'Resolved'
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concern_reports');
    }
};
