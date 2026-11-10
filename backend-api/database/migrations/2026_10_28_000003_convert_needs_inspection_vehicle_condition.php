<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A vehicle's condition is either Good or Needs Repair (condition checks offer
     * nothing in between). Vehicles the system had flagged "Needs Inspection"
     * automatically are now Needs Repair. Older condition-check rows are history
     * and stay as they were.
     */
    public function up(): void
    {
        DB::table('vehicles')->where('condition', 'Needs Inspection')->update(['condition' => 'Needs Repair']);
    }

    public function down(): void
    {
        // Not reversible: the earlier value cannot be told apart afterwards.
    }
};
