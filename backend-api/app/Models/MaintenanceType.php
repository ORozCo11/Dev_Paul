<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceType extends Model
{
    protected $fillable = ['name'];

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
        return static::findOrCreateByName($name)?->name;
    }
}
