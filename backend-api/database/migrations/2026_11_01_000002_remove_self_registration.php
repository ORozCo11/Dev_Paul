<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Barangay self-registration is gone: the LGU's Super Admin creates each
// barangay Admin and each Admin creates the staff. The staff registration
// codes and the applicant ID/photo columns have no use any more.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('registration_settings');
        $drop = array_values(array_filter(
            ['awaiting_staff_code', 'staff_code_sent_at', 'registration_id_path', 'registration_photo_path'],
            fn ($column) => Schema::hasColumn('users', $column)
        ));
        if ($drop) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('awaiting_staff_code')->default(false);
            $table->timestamp('staff_code_sent_at')->nullable();
            $table->string('registration_id_path')->nullable();
            $table->string('registration_photo_path')->nullable();
        });
        Schema::create('registration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('staff_code');
            $table->timestamps();
        });
    }
};
