<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * For a model that doesn't carry its own barangay_id, but always belongs to
 * a Vehicle (directly, or via a parent — see TicketSubIssue's override
 * below) — its tenant is derived by following that relation to a Vehicle,
 * which IS scoped (see BelongsToBarangay). No creating-time stamp needed:
 * these rows are always created against an already-tenant-correct vehicle,
 * so there's nothing of their own to stamp.
 */
trait ScopedThroughVehicle
{
    /**
     * Dot-notation relation path to the owning Vehicle. Override in a model
     * that reaches Vehicle through an intermediate relation instead of
     * directly (e.g. TicketSubIssue -> 'ticket.vehicle').
     */
    protected static function vehicleRelationPath(): string
    {
        return 'vehicle';
    }

    protected static function bootScopedThroughVehicle(): void
    {
        static::addGlobalScope(function (Builder $builder) {
            if (Auth::check() && Auth::user()->barangay_id) {
                $builder->whereRelation(static::vehicleRelationPath(), 'barangay_id', Auth::user()->barangay_id);
            }
        });
    }
}
