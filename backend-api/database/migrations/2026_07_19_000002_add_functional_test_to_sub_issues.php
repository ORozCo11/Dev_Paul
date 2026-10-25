<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Problem 2 — real verification (the "UAT" / functional test).
     *
     * "Verify" used to be satisfiable by looking at the paperwork. That
     * proves the work was *done*, not that the vehicle actually *works*.
     * The Custodian's verification step now carries a real functional test:
     * the independent user operates the vehicle against a type-specific
     * checklist and attests to it. A failed check bounces the sub-issue
     * back to the mechanic (the existing "Rejected -> Under Repair" loop).
     *
     * functional_test — the completed checklist [{item, passed}, ...]
     * test_attested   — the tester's explicit "I operated and tested it"
     */
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->json('functional_test')->nullable();
            $table->boolean('test_attested')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn(['functional_test', 'test_attested']);
        });
    }
};
