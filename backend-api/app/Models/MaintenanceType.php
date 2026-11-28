<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceType extends Model
{
    protected $fillable = ['name'];

    /** Preventive Maintenance first, then the most common repair types, then everything else A to Z. */
    private const USUAL_ORDER = [
        'Preventive Maintenance', 'Oil Change', 'Tire Service', 'Brake System', 'Engine Repair', 'Air-Conditioning',
        'Electrical System', 'Steering System', 'Hydraulic System', 'Pump Service', 'Siren and Lights', 'Outboard Service', 'Hull Repair',
    ];

    public static function inUsualOrder(\Illuminate\Support\Collection $rows, string $key = 'name'): \Illuminate\Support\Collection
    {
        $rank = array_flip(array_map('mb_strtolower', self::USUAL_ORDER));

        return $rows->sortBy(fn ($row) => sprintf('%03d|%s', $rank[mb_strtolower(is_array($row) ? $row[$key] : (is_object($row) ? $row->{$key} : $row))] ?? 999, mb_strtolower(is_array($row) ? $row[$key] : (is_object($row) ? $row->{$key} : $row))))->values();
    }

    /**
     * Look up a name case-insensitively and create it if it doesn't exist
     * yet — this is what makes the catalog grow from real use instead of
     * staying a fixed list. Returns the stored record so a near-duplicate
     * typed with different casing doesn't fork the catalog, and callers
     * that need the id (e.g. to delete it later) have it.
     */
    public static function findOrCreateByName(?string $name): ?self
    {
        $name = $name !== null ? trim($name) : null;
        if (!$name) {
            return null;
        }

        $existing = static::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing) {
            return $existing;
        }

        try {
            return static::create(['name' => $name]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Another request created the same type between our lookup and
            // our insert — use theirs instead of throwing a 500.
            return static::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->firstOr(fn () => throw $e);
        }
    }

    /** Same lookup, but just the canonical name — for callers that only
     * need the resolved string (e.g. saving it onto a ticket/record). */
    public static function resolve(?string $name): ?string
    {
        $name = trim($name ?? '');
        if ($name === '') return null;

        // Recording work may retain a free-text description, but must not
        // silently grant catalog.create. Only the catalog endpoint inserts.
        return static::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('name') ?? $name;
    }
}
