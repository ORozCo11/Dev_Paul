<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy root: apply to a model that carries its own barangay_id
 * column (Vehicle, VehicleHub, ActivityLog). Every query — including
 * route-model-binding lookups by id — is automatically filtered to the
 * authenticated user's own barangay, and every new record is automatically
 * stamped with it too.
 *
 * Both the scope and the stamp are no-ops for an unauthenticated request
 * (registration, login) — Auth::check() is false at those moments, so
 * nothing here narrows those queries. Registration needs to see across
 * every barangay to decide whether a given barangay already has anyone
 * registered.
 *
 * NEVER apply this to the User model itself. Auth::check()/Auth::user()
 * resolve by having Sanctum query User::find($id) on the token's owner —
 * a global scope on User that itself calls Auth::check() re-enters that
 * same unresolved guard call, recursing until the process crashes (this
 * was tried and it took the whole PHP dev server down). User scoping
 * — the Users list, and the activate/deactivate/update actions — is
 * done with explicit `->where('barangay_id', ...)` filters in
 * UserController instead.
 */
trait BelongsToBarangay
{
    protected static function bootBelongsToBarangay(): void
    {
        static::addGlobalScope(function (Builder $builder) {
            if (Auth::check() && Auth::user()->barangay_id) {
                $builder->where($builder->getModel()->getTable() . '.barangay_id', Auth::user()->barangay_id);
            }
        });

        static::creating(function ($model) {
            if (!$model->barangay_id && Auth::check()) {
                $model->barangay_id = Auth::user()->barangay_id;
            }
        });
    }
}
