<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const REMOVED_TABLES = ['vehicle_usage_logs' => 'usage_id', 'ticket_dispatch_assessments' => 'id'];
    public const RETIRED_STATUSES = ['Dispatched', 'In Use'];
    private const BACKUP = 'retired_feature_backups';
    private const VALID_STATUSES = ['Available', 'Under Maintenance', 'Inactive', 'Decommissioned'];

    public function up(): void
    {
        if (!Schema::hasTable(self::BACKUP)) {
            Schema::create(self::BACKUP, function (Blueprint $table) {
                $table->id();
                $table->string('source_table');
                $table->string('source_id');
                $table->json('payload');
                $table->unique(['source_table', 'source_id']);
            });
        }

        foreach (self::REMOVED_TABLES as $table => $key) {
            if (Schema::hasTable($table)) $this->archive($table, $key, DB::table($table));
        }
        $vehicles = DB::table('vehicles')->whereIn('status', self::RETIRED_STATUSES);
        $this->archive('vehicles', 'vehicle_id', clone $vehicles);
        // A historical operational flag is not proof of readiness. Keep condition
        // and readiness evidence intact and require an explicit availability review.
        $vehicles->update(['status' => 'Under Maintenance']);

        foreach ($this->derivedRows() as $table => [$key, $query]) {
            $this->archive($table, $key, clone $query);
            $query->delete();
        }

        Schema::dropIfExists('ticket_dispatch_assessments');
        Schema::dropIfExists('vehicle_usage_logs');
        Schema::table('vehicles', fn (Blueprint $table) => $table->enum('status', self::VALID_STATUSES)->default('Available')->change());
    }

    public function down(): void
    {
        // Widen before restoring any historical operational values.
        Schema::table('vehicles', fn (Blueprint $table) => $table->enum('status', array_merge(self::VALID_STATUSES, self::RETIRED_STATUSES))->default('Available')->change());
        Schema::create('vehicle_usage_logs', function (Blueprint $table) {
            $table->id('usage_id');
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->cascadeOnDelete();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->string('destination')->nullable();
            $table->string('driver_name')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        // Reuse the unmodified historical definition for its dependent table/column.
        (require __DIR__.'/2026_10_23_120000_add_temporary_dispatch_releases.php')->up();

        if (!Schema::hasTable(self::BACKUP)) return;
        foreach (self::REMOVED_TABLES + ['vehicle_histories' => 'history_id', 'notifications' => 'notification_id', 'activity_logs' => 'log_id'] as $table => $key) {
            DB::table(self::BACKUP)->where('source_table', $table)->orderBy('id')->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) DB::table($table)->insert(json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR));
            });
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', '{$key}'), COALESCE((SELECT MAX({$key}) FROM {$table}), 1), EXISTS(SELECT 1 FROM {$table}))");
            }
        }
        DB::table(self::BACKUP)->where('source_table', 'vehicles')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $original = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                // Do not undo an operator's later availability/retirement decision.
                DB::table('vehicles')->where('vehicle_id', $original['vehicle_id'])->where('status', 'Under Maintenance')->update(['status' => $original['status']]);
            }
        });
        Schema::drop(self::BACKUP);
    }

    private function archive(string $table, string $key, $query): void
    {
        $query->orderBy($key)->chunkById(100, function ($rows) use ($table, $key) {
            foreach ($rows as $row) {
                DB::table(self::BACKUP)->updateOrInsert(['source_table' => $table, 'source_id' => (string) $row->{$key}], ['payload' => json_encode($row, JSON_THROW_ON_ERROR)]);
            }
        }, $key);
    }

    private function derivedRows(): array
    {
        return [
            'vehicle_histories' => ['history_id', DB::table('vehicle_histories')->where(function ($query) {
                $query->where('related_table', 'vehicle_usage_logs')->orWhereIn('activity_type', ['Vehicle Taken Out', 'Vehicle Returned', 'Temporary Dispatch Authorized', 'Temporary Dispatch Returned']);
            })],
            'notifications' => ['notification_id', DB::table('notifications')->whereIn('type', ['dispatch_assessment', 'temporary_dispatch', 'temporary_dispatch_returned'])],
            'activity_logs' => ['log_id', DB::table('activity_logs')->where(function ($query) {
                $query->where('module', 'Vehicle Usage')->orWhereIn('action', ['Dispatch Assessment', 'Temporary Dispatch Authorized', 'Temporary Dispatch Returned']);
            })],
        ];
    }
};
