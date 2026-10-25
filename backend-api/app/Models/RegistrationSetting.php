<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationSetting extends Model
{
    protected $fillable = ['barangay_id', 'staff_code'];

    /**
     * One row per barangay. Fetch it (lazily creating one with a fresh code
     * the first time it's needed — e.g. the moment that barangay's brand
     * new Admin first opens the Users module) instead of every caller
     * having to know that.
     */
    public static function for(int $barangayId): self
    {
        return static::firstOrCreate(
            ['barangay_id' => $barangayId],
            ['staff_code' => static::generateCode()]
        );
    }

    /**
     * Replaces the code under a row lock so two simultaneous regenerations
     * can't both report success with different codes. When the caller says
     * which version it last saw, a code changed since then is left alone
     * and null comes back instead.
     */
    public static function regenerateFor(int $barangayId, ?DateTimeInterface $expectedUpdatedAt = null): ?self
    {
        static::for($barangayId);

        return DB::transaction(function () use ($barangayId, $expectedUpdatedAt) {
            $setting = static::where('barangay_id', $barangayId)->lockForUpdate()->firstOrFail();
            if ($expectedUpdatedAt !== null && $setting->updated_at?->getTimestamp() !== $expectedUpdatedAt->getTimestamp()) {
                return null;
            }
            $setting->update(['staff_code' => static::generateCode()]);

            return $setting;
        });
    }

    // Str::random draws from random_bytes(), a cryptographically secure source.
    private static function generateCode(): string
    {
        return Str::upper(Str::random(8));
    }
}
