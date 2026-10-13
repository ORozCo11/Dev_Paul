<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-shop repairs record who actually handled the vehicle (not an
 * invented mechanic), the shop's address, and when it is expected back.
 * Additive and nullable: existing tickets and sub-issues are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->string('external_shop_address')->nullable();
            $table->date('external_expected_return')->nullable();
            // Authenticated users who recorded the dispatch / the return. They
            // can never go on to verify the same ticket (see verifyTicket()).
            $table->unsignedBigInteger('external_handled_by')->nullable();
            $table->unsignedBigInteger('external_received_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn(['external_shop_address', 'external_expected_return', 'external_handled_by', 'external_received_by']);
        });
    }
};
