<?php

use App\Services\BarangayNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barangay_number_sequences')) Schema::create('barangay_number_sequences', function (Blueprint $table) {
            $table->foreignId('barangay_id')->constrained('barangays')->cascadeOnDelete();
            $table->string('resource', 64);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['barangay_id', 'resource']);
        });

        foreach (BarangayNumbers::RESOURCES as $table => $primaryKey) {
            if (!Schema::hasColumn($table, 'barangay_number')) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('barangay_number')->nullable());
            // Ascending creation IDs give existing rows stable numbers starting
            // at one for each tenant. Internal PKs and every FK stay unchanged.
            $rows = DB::table($table.' as records');
            $direct = in_array($table, ['users', 'vehicles'], true);
            if (!$direct) $rows->join('vehicles', 'vehicles.vehicle_id', '=', 'records.vehicle_id');
            $rows->select('records.'.$primaryKey.' as record_id', 'records.barangay_number', ($direct ? 'records' : 'vehicles').'.barangay_id as tenant_id')
                ->orderBy('records.'.$primaryKey)
                ->chunk(500, function ($records) use ($table, $primaryKey) {
                    foreach ($records as $record) {
                        if (!$record->tenant_id || $record->barangay_number !== null) continue;
                        $number = BarangayNumbers::next((int) $record->tenant_id, $table);
                        DB::table($table)->where($primaryKey, $record->record_id)->update(['barangay_number' => $number]);
                    }
                });
        }

        DB::table('maintenance_tickets')->whereNotNull('barangay_number')->orderBy('ticket_id')->chunkById(500, function ($tickets) {
            foreach ($tickets as $ticket) {
                $title = preg_replace('/^MT-\d+(?=\s)/u', sprintf('MT-%04d', $ticket->barangay_number), $ticket->ticket_title);
                if ($title !== $ticket->ticket_title) DB::table('maintenance_tickets')->where('ticket_id', $ticket->ticket_id)->update(['ticket_title' => $title]);
            }
        }, 'ticket_id');

        if (!Schema::hasColumn('ticket_archive_logs', 'ticket_number')) Schema::table('ticket_archive_logs', fn (Blueprint $table) => $table->unsignedBigInteger('ticket_number')->nullable());
        $archivedNumbers = [];
        DB::table('ticket_archive_logs')->orderBy('archive_id')->chunkById(500, function ($archives) use (&$archivedNumbers) {
            foreach ($archives as $archive) {
                $tenant = DB::table('vehicles')->where('vehicle_id', $archive->vehicle_id)->value('barangay_id');
                if (!$tenant) continue;
                $key = $tenant.':'.$archive->ticket_id;
                $number = $archivedNumbers[$key] ??= ($archive->ticket_number ?? DB::table('maintenance_tickets')->where('ticket_id', $archive->ticket_id)->value('barangay_number')
                    ?? BarangayNumbers::next((int) $tenant, 'maintenance_tickets'));
                $snapshot = $this->decodeSnapshot($archive->full_ticket_snapshot);
                $title = preg_replace('/^MT-\d+(?=\s)/u', sprintf('MT-%04d', $number), $archive->ticket_title);
                if ($snapshot !== null) {
                    $snapshot['barangay_number'] = $number;
                    $snapshot['ticket_title'] = $title;
                }
                DB::table('ticket_archive_logs')->where('archive_id', $archive->archive_id)->update([
                    'ticket_number' => $number, 'ticket_title' => $title, 'full_ticket_snapshot' => $snapshot === null ? $archive->full_ticket_snapshot : json_encode($snapshot),
                ]);
            }
        }, 'archive_id');
    }

    public function down(): void
    {
        DB::table('maintenance_tickets')->orderBy('ticket_id')->chunkById(500, function ($tickets) {
            foreach ($tickets as $ticket) {
                DB::table('maintenance_tickets')->where('ticket_id', $ticket->ticket_id)->update([
                    'ticket_title' => preg_replace('/^MT-\d+(?=\s)/u', sprintf('MT-%04d', $ticket->ticket_id), $ticket->ticket_title),
                ]);
            }
        }, 'ticket_id');
        DB::table('ticket_archive_logs')->orderBy('archive_id')->chunkById(500, function ($archives) {
            foreach ($archives as $archive) {
                $snapshot = $this->decodeSnapshot($archive->full_ticket_snapshot);
                $title = preg_replace('/^MT-\d+(?=\s)/u', sprintf('MT-%04d', $archive->ticket_id), $archive->ticket_title);
                if ($snapshot !== null) {
                    unset($snapshot['barangay_number']);
                    $snapshot['ticket_title'] = $title;
                }
                DB::table('ticket_archive_logs')->where('archive_id', $archive->archive_id)->update(['ticket_title' => $title, 'full_ticket_snapshot' => $snapshot === null ? $archive->full_ticket_snapshot : json_encode($snapshot)]);
            }
        }, 'archive_id');
        Schema::table('ticket_archive_logs', fn (Blueprint $table) => $table->dropColumn('ticket_number'));
        foreach (BarangayNumbers::RESOURCES as $table => $primaryKey) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('barangay_number'));
        }
        Schema::dropIfExists('barangay_number_sequences');
    }

    private function decodeSnapshot(string $value): ?array
    {
        // Older seeders serialized an array before passing it to an array cast.
        // Decode that legacy nesting without discarding an unreadable payload.
        for ($depth = 0; $depth < 3 && is_string($value); $depth++) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : null;
    }
};
