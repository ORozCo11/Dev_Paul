<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\ConcernReport;
use App\Models\RegistrationSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Platform-level account administration — the one tier above every
 * barangay's own Admin. Deliberately scoped to ACCOUNTS only (users,
 * barangays, registration codes, impersonation, the audit log): a Super
 * Admin can never see a barangay's vehicles, tickets, or other fleet data.
 * That boundary is what keeps "separate everything per barangay" true even
 * with this role in the system — see BelongsToBarangay's docblock for why
 * User itself is never scoped, which is exactly what lets a barangay_id-less
 * Super Admin see across every barangay here without any special-casing.
 */
class SuperAdminController extends Controller
{
    private const ROLES = ['Admin', 'Custodian', 'Maintenance Personnel'];

    /**
     * `activity_logs.module` values that represent account/administrative-
     * level activity — the only entries activityLog() may return, per this
     * class's docblock. Every other module string in that table is fleet-
     * operational detail logged by FleetController/TicketController for a
     * barangay's own Admin audit trail (e.g. 'Vehicle Management', 'Vehicle
     * Maintenance Records', 'Vehicle Maintenance Schedule', 'Vehicle
     * Condition Monitoring', 'Vehicle Issue Reports', 'Vehicle Documents',
     * 'Vehicle Categories', 'Reports', 'Maintenance Tickets') and must never
     * surface here. Using an allowlist (rather than excluding fleet modules
     * by name) means a newly added fleet module is excluded by default.
     */
    private const ACCOUNT_MODULES = ['Super Admin', 'Dev Tools', 'Profile'];

    private function requireSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403, 'Only a Super Admin can perform this action.');
    }

    private function log(Request $request, string $action, string $details): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'role'    => $request->user()->role,
            'action'  => $action,
            'module'  => 'Super Admin',
            'details' => $details,
        ]);
    }

    /**
     * Every barangay, with its staff count and whether it currently has an
     * active Admin — an "orphaned" barangay (none) is the case this whole
     * role exists to let someone recover from.
     */
    public function barangays(Request $request)
    {
        $this->requireSuperAdmin($request);

        return Barangay::with(['city.province', 'registrationSetting'])
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'city_name' => $b->city?->name,
                'province_name' => $b->city?->province?->name,
                'staff_count' => $b->users_count,
                'has_active_admin' => $b->users()->havingRole('Admin')->where('is_active', true)->exists(),
                // Both one-line, already-loaded checks — no extra queries per
                // row. A barangay can exist with neither yet: freshly added
                // via storeBarangay() below, nobody's registered there, and
                // no boundary polygon has been matched/drawn for it.
                'has_registration_code' => $b->registrationSetting !== null,
                'has_boundary' => $b->boundary !== null,
            ]);
    }

    /**
     * Lets a Super Admin add a barangay for ANY province/city up front —
     * not just Mandaue's seeded 27 — so a registration code can be
     * generated and handed to that barangay's office before its first
     * resident ever registers (registrationCode()/RegistrationSetting::for()
     * auto-creates the code the first time it's looked up, so nothing
     * further is needed here to make one available).
     */
    public function storeBarangay(Request $request)
    {
        $this->requireSuperAdmin($request);

        $data = $request->validate([
            'city_id' => ['required', 'exists:cities,id'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('barangays')->where('city_id', $request->city_id),
            ],
        ]);

        $barangay = Barangay::create($data);
        $barangay->load('city.province');

        // Best-effort — the 27 Mandaue barangays came with a real PSA
        // boundary file, but nothing does for a barangay added here for
        // some other city. Rather than always leaving it unset, ask a
        // public geocoder for one; if it can't find a match (or the
        // lookup fails/times out) the barangay is still created, just
        // without a boundary, exactly as before this existed.
        $boundary = $this->lookupBoundary($barangay->name, $barangay->city?->name);
        if ($boundary) {
            $barangay->update(['boundary' => $boundary]);
        }

        $this->log($request, 'Add', "Added barangay {$barangay->name} ({$barangay->city?->name}).");

        return response()->json([
            'id' => $barangay->id,
            'name' => $barangay->name,
            'city_name' => $barangay->city?->name,
            'province_name' => $barangay->city?->province?->name,
            'staff_count' => 0,
            'has_active_admin' => false,
            'has_registration_code' => false,
            'has_boundary' => $boundary !== null,
        ], 201);
    }

    /**
     * Asks OpenStreetMap's public Nominatim geocoder for a boundary polygon
     * matching "<barangay>, <city>, Philippines" — free, no API key, but
     * rate-limited to ~1 req/sec and requires an identifying User-Agent per
     * its usage policy (https://operations.osmfoundation.org/policies/nominatim/).
     * Fine for this: one lookup per barangay actually added, never a bulk
     * operation. Coverage isn't guaranteed for every barangay in the
     * country — returns null (silently — this must never block a barangay
     * from being created) whenever nothing usable comes back.
     */
    private function lookupBoundary(string $barangayName, ?string $cityName): ?array
    {
        if (!$cityName) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'BarangayVMS/1.0 (https://github.com/ORozCo11/Dev_Paul)'])
                ->timeout(6)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'polygon_geojson' => 1,
                    'limit' => 5,
                    'q' => "{$barangayName}, {$cityName}, Philippines",
                ]);

            if (!$response->successful()) {
                return null;
            }

            foreach ($response->json() ?? [] as $result) {
                // Only trust an actual administrative boundary relation — anything
                // else (a barangay hall, a health center, a POI) can still carry a
                // Polygon/MultiPolygon geojson but it's the shape of a building, not
                // the barangay's territory, and would draw the wrong line on the map.
                if (($result['class'] ?? null) !== 'boundary' || ($result['type'] ?? null) !== 'administrative') {
                    continue;
                }

                $geojson = $result['geojson'] ?? null;
                if (in_array($geojson['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                    return $geojson;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("Boundary lookup failed for {$barangayName}, {$cityName}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Every user, across every barangay — the one screen in the whole
     * system where that's true. Search matches name or email.
     */
    public function users(Request $request)
    {
        $this->requireSuperAdmin($request);

        $query = User::with('barangay.city.province');

        if ($request->filled('q')) {
            $search = $request->string('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'roles' => $u->roles,
            'is_active' => $u->is_active,
            'approved_at' => $u->approved_at,
            'barangay_id' => $u->barangay_id,
            'barangay_name' => $u->barangay?->name,
            'city_name' => $u->barangay?->city?->name,
            'province_name' => $u->barangay?->city?->province?->name,
        ]);
    }

    public function activateUser(Request $request, User $user)
    {
        $this->requireSuperAdmin($request);

        $user->update(['is_active' => true, 'approved_at' => $user->approved_at ?? now()]);
        $this->log($request, 'Activate', "Activated {$user->name} ({$user->email}).");

        return response()->json(['message' => 'User activated.']);
    }

    public function deactivateUser(Request $request, User $user)
    {
        $this->requireSuperAdmin($request);
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');

        $user->update(['is_active' => false]);
        $user->tokens()->delete();
        $this->log($request, 'Deactivate', "Deactivated {$user->name} ({$user->email}).");

        return response()->json(['message' => 'User deactivated.']);
    }

    /**
     * Rejects a registration that was never approved — deletes the account
     * outright rather than just deactivating it, since a rejected signup
     * (e.g. someone who wasn't actually the barangay's real first Admin)
     * has no legitimate record worth keeping around in an inactive state.
     * Deliberately refuses to touch anyone who was EVER approved (even if
     * later deactivated) — that path only goes through deactivateUser().
     */
    public function rejectUser(Request $request, User $user)
    {
        $this->requireSuperAdmin($request);
        abort_if($user->approved_at !== null, 422, 'This account was already approved at some point — deactivate it instead of rejecting.');

        $name = $user->name;
        $email = $user->email;
        $user->delete();
        $this->log($request, 'Delete', "Rejected pending registration for {$name} ({$email}).");

        return response()->json(['message' => 'Registration rejected.']);
    }

    /**
     * The specific queue this role exists to guard: every account still
     * waiting on approval, newest first. Not just first-for-barangay Admins
     * (an Admin can, in principle, sit unapproved if a barangay's own Admin
     * never got around to reviewing them) — but Super Admin approving a
     * pending Admin is the case that closes the registration hole.
     */
    public function pendingApprovals(Request $request)
    {
        $this->requireSuperAdmin($request);

        return User::with('barangay.city.province')
            ->whereNull('approved_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'phone' => $u->phone,
                'created_at' => $u->created_at,
                'barangay_id' => $u->barangay_id,
                'barangay_name' => $u->barangay?->name ?? $u->barangay_name,
                'city_name' => $u->barangay?->city?->name,
                'province_name' => $u->barangay?->city?->province?->name,
            ]);
    }

    /**
     * Change a user's role — also how an orphaned barangay gets recovered:
     * pick an existing user there and set their role to Admin.
     */
    public function updateUserRole(Request $request, User $user)
    {
        $this->requireSuperAdmin($request);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');
        abort_if($user->hasRole('Super Admin'), 422, 'Super Admin roles cannot be changed through this endpoint.');

        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $user->update(['role' => $data['role'], 'roles' => [$data['role']]]);
        $this->log($request, 'Edit', "Changed {$user->name}'s role to {$data['role']}.");

        return $user->fresh();
    }

    public function registrationCode(Request $request, Barangay $barangay)
    {
        $this->requireSuperAdmin($request);

        return response()->json(['staff_code' => RegistrationSetting::for($barangay->id)->staff_code]);
    }

    public function regenerateRegistrationCode(Request $request, Barangay $barangay)
    {
        $this->requireSuperAdmin($request);

        $setting = RegistrationSetting::regenerateFor($barangay->id);
        $this->log($request, 'Edit', "Regenerated the staff registration code for {$barangay->name}.");

        return response()->json(['staff_code' => $setting->staff_code]);
    }

    /**
     * Unscoped by design — BelongsToBarangay's global scope only narrows a
     * query when the VIEWER has a barangay_id, which a Super Admin never
     * does. Every barangay's own Admin still only ever sees their own
     * barangay's entries through the same model, unchanged.
     *
     * Restricted to ACCOUNT_MODULES so fleet-detail entries (vehicles,
     * tickets, maintenance, etc.) logged by FleetController/TicketController
     * never leak through this endpoint — see this class's docblock.
     */
    public function activityLog(Request $request)
    {
        $this->requireSuperAdmin($request);

        return ActivityLog::with('user')
            ->whereIn('module', self::ACCOUNT_MODULES)
            ->latest('log_id')
            ->limit(200)
            ->get();
    }

    /**
     * Public "Report a Concern" submissions from the Support Center page —
     * a barangay that looks unmanaged, or a suspected fake-staff account.
     * This is the inbox that closes the loop: the Super Admin reads a
     * report here, then acts using the tools above (promote/recover a
     * barangay, deactivate a suspicious account) and marks it resolved.
     */
    public function concernReports(Request $request)
    {
        $this->requireSuperAdmin($request);

        return ConcernReport::with('resolvedBy')->latest('id')->get();
    }

    public function resolveConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireSuperAdmin($request);

        $concernReport->update([
            'status' => 'Resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);
        $this->log($request, 'Edit', "Marked concern report #{$concernReport->id} resolved.");

        return $concernReport->fresh('resolvedBy');
    }

    public function reopenConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireSuperAdmin($request);

        $concernReport->update(['status' => 'Open', 'resolved_by' => null, 'resolved_at' => null]);
        $this->log($request, 'Edit', "Reopened concern report #{$concernReport->id}.");

        return $concernReport->fresh();
    }

    /**
     * Permanently removes a concern report — the only way to clear out
     * obvious spam, since resolve/reopen only ever toggle status and never
     * delete. Submission is already rate-limited (see /concern-reports in
     * routes/api.php); this is the cleanup side of that.
     */
    public function destroyConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireSuperAdmin($request);

        $id = $concernReport->id;
        $concernReport->delete();
        $this->log($request, 'Delete', "Deleted concern report #{$id}.");

        return response()->json(['message' => 'Concern report deleted.']);
    }
}
