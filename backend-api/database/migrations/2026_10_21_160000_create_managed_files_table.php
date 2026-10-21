<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('managed_files', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('disk');
            $table->string('path');
            $table->string('directory');
            $table->unsignedBigInteger('barangay_id')->nullable()->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->text('original_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('managed_files'); }
};
