<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A barangay's first Administrator registers without a staff code, then
// activates by entering the code a Super Admin emails them.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('awaiting_staff_code')->default(false);
            $table->timestamp('staff_code_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['awaiting_staff_code', 'staff_code_sent_at']);
        });
    }
};
