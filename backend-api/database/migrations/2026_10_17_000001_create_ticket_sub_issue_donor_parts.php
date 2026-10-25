<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cannibalized repair can use several parts from its (single) donor vehicle.
 * Each is its own row here. The single-part columns on ticket_sub_issues
 * (part_needed, part_quantity, ...) are left untouched, and any value already
 * stored there is copied into this table so nothing recorded so far is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            $table->id('part_id');
            $table->unsignedBigInteger('sub_issue_id');
            $table->string('part_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('part_condition', 100)->nullable();
            $table->date('installed_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sub_issue_id')->references('sub_issue_id')->on('ticket_sub_issues')->cascadeOnDelete();
            $table->index('sub_issue_id');
        });

        DB::table('ticket_sub_issues')
            ->where('repair_type', 'cannibalized')
            ->whereNotNull('part_needed')
            ->orderBy('sub_issue_id')
            ->each(function ($row) {
                DB::table('ticket_sub_issue_donor_parts')->insert([
                    'sub_issue_id'   => $row->sub_issue_id,
                    'part_name'      => $row->part_needed,
                    'quantity'       => max(1, (int) ($row->part_quantity ?? 1)),
                    'part_condition' => $row->part_condition,
                    'installed_at'   => $row->part_installed_at ? substr((string) $row->part_installed_at, 0, 10) : null,
                    'reason'         => $row->cannibal_reason,
                    'notes'          => $row->part_missing ? 'Replaces: ' . $row->part_missing : null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sub_issue_donor_parts');
    }
};
