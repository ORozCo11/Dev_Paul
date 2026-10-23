# Barangay Vehicle Management System (VMS) — Documentation

UI documentation reviewed: **23 October 2026**. See [UI updates and branch status](UI-UPDATES-2026-10-23.md) for current presentation changes, separate branch delivery, and verification limits.

## 1. What this system is

The Barangay VMS is a fleet management platform for a barangay's emergency response vehicles — ambulances, fire trucks, and rescue boats. It tracks whether a vehicle is ready to respond right now, and manages the full lifecycle of keeping it that way: reporting problems, inspecting them, repairing them, verifying the repair, and scheduling preventive maintenance before problems happen.

It is explicitly **not** a dispatch/operations system, a document-compliance tracker, or a mileage-based service planner — those are out of scope by design.

See `System-Architecture.svg` in this folder for the full component diagram.

## 2. Tech stack

| Layer | Technology |
|---|---|
| Frontend | React 19 (Vite 8), react-router-dom 7, axios, Leaflet/react-leaflet for maps |
| Backend | Laravel 13 (PHP 8.3), Laravel Sanctum for auth |
| Database | SQLite for local development; PostgreSQL (Supabase-managed) in deployment |
| File storage | Supabase Storage (S3-compatible) via `league/flysystem-aws-s3-v3` |
| Auth | Sanctum bearer tokens, tagged with the user's role(s) as token abilities |

The frontend is almost entirely one large component tree (`frontend-spa/src/views/Workspace.jsx`) that renders different modules based on the logged-in user's role and the current route, rather than a page-per-file structure. The Super Admin portal (`SuperAdminWorkspace.jsx`) is a separate, smaller tree.

## 3. Roles

| Role | Scope | What they do |
|---|---|---|
| **Admin** | One Barangay | Runs the fleet: reviews and approves/declines Custodian-proposed tickets and schedules, assigns mechanics, handles external-shop work, approves accounts, manages vehicle types/locations/master data, and generates reports. Admin does **not** create tickets directly or verify repairs — see §5. |
| **Custodian** | One Barangay | Files Issue Reports, is the **only** role that creates a Maintenance Ticket (by proposing one for Admin's approval), registers vehicles when delegated to, verifies a mechanic's completed repair, records preventive-maintenance checks and schedules. |
| **Maintenance Personnel** | One Barangay | Can file a technical Issue Report (routed to the Custodian, who still owns ticket creation), repairs assigned sub-issues, logs work and parts used. |
| **Super Admin** | Platform-wide | Manages Barangays and their user accounts, approves each Barangay's first-ever Admin, regenerates registration codes, handles concern reports, can impersonate accounts for support. Sees **no** fleet data — vehicles, tickets, and issues are intentionally out of its scope. |

Admin, Custodian, and Maintenance Personnel are strictly scoped to the one Barangay they registered under. A user can hold more than one of these three roles at once (e.g. a small Barangay's one staff member covering both Custodian and Maintenance Personnel duties).

## 4. Registration & onboarding

- Anyone can reach the public `/register` page, but every registration requires a **Staff Registration Code** — a per-Barangay code the barangay office hands out, checked server-side (`hash_equals`).
- The **first person to register for a given Barangay becomes its Admin** — but, like every other registrant, starts inactive and cannot log in until approved. Because that Barangay has no Admin yet to approve them, a **Super Admin** reviews and approves this specific case instead, from a dedicated Pending Approvals queue; they're notified the moment the registration happens.
- Everyone who registers after that must choose Custodian or Maintenance Personnel, and their account stays inactive until an existing Admin approves them from the Users page.
- See `Must-Haves.md`'s "Resolved Gaps" for why this two-track approval flow replaced the old "first registrant goes live instantly" behavior.

## 5. Core workflow: the maintenance ticket lifecycle

This is the heart of the system. **Every ticket enters as a Custodian's proposal that an Admin must review and approve before it's live** — Admin has no direct "create ticket" path: `ticket.create` is a permanently empty permission grant (nobody holds it, by design). A proposal has two possible sources: a human Custodian (`POST /tickets/propose`), or the system itself, auto-proposing one when a Maintenance Schedule's date arrives (§6) — either way, it lands at `Pending Approval` and goes through the exact same Admin review, never a bypass to `Active`. A Custodian who already knows what's wrong (they filed, or were pointed to, an Issue Report) submits a full proposal, picking one repair path: **in-house** (repaired by the barangay's own mechanics; can optionally be flagged as **cannibalized**, swapping in a part taken from a donor vehicle instead of a new one), or **external** (sent to an outside shop). Each sub-issue in the proposal carries its own Maintenance Type and Suggested Mechanic — a single ticket can mix different kinds of repair (e.g. a brake job and an electrical fix) without forcing one category on every sub-issue. The ticket's title is generated automatically from the vehicle and repair type (the Custodian never types one); on approval, the ticket's own number is folded into it, e.g. `#42 - Ambulance 1 - Brake Repair`. A proposal can optionally link to an existing open Issue Report on that vehicle, carrying forward its vehicle, issue type, description, severity, and reporter so none of it is re-typed.

The workflow end to end:

1. **Report** — a Custodian or Maintenance Personnel files an Issue Report (a Maintenance Personnel account is notified-forward to the Custodian, since they still can't create the ticket themselves — see §3).
2. **Propose** — the Custodian proposes a ticket (optionally linked to that report), entering **Pending Approval**.
3. **Approve** — the Admin reviews, can edit ticket/sub-issue fields, and approves (the ticket becomes **Active**, assigning its mechanic) or declines (the proposal remains traceable as **Declined**).
4. **Repair** — the assigned Maintenance Personnel logs work on the ticket's sub-issues. Admin handles external-shop dispatch and return details; the handler cannot verify that work.
5. **Verify** — the assigned Custodian runs a functional test. A user can never verify their own repair — enforced server-side even for a dual-role (Custodian + Maintenance Personnel) account on the same sub-issue. Verification is Custodian-only, with no Admin fallback; if the assigned Custodian is unavailable, the ticket is reassigned to a different Custodian instead.
6. **Decide** — the assigned Custodian uses the verification popup to Approve or Disapprove. Disapproval requires a reason and returns the ticket for repair. The former Admin confirmation and decision-close flow is not the current closure path.
7. **Close** — successful Custodian verification closes the ticket and notifies the Admin. The server checks assignment, repair readiness, and self-verification restrictions before accepting the decision.

Extra signals layered on top: **recurrence detection** (the same fault reported again soon after a "fix" flags as a likely rework) and **aging** (a ticket open more than 30 days is flagged).

## 6. Other modules

- **Issue Reports** — both a reporting inbox (Admin, Custodian, and Maintenance Personnel can each file one — "Report Vehicle Issue" / "Report Technical Issue" in their sidebar) and a running to-do list of known, not-yet-repaired defects: deferred sub-issues, leftover notes from a cannibalized-repair donor vehicle, and other flagged concerns. Filing one lands straight on that report's own page with a **Create Maintenance Ticket** action right there (Custodian only) — not back on a list to search for what was just submitted. A Maintenance Personnel account sees only the reports they personally filed (everyone else's queue stays a Custodian/Admin concern), and filing one notifies that barangay's Custodians directly. The only Admin action on one is **Dismiss** (with a required reason) once it's no longer relevant; if a ticket already exists for it, the report's View action opens that ticket directly instead.
- **Vehicle Management / Vehicle Types** — the vehicle catalog. Admin registers vehicles directly; a Custodian may also register one when the barangay delegates that to them (registration is data entry, not a transfer of accountability — editing a vehicle's protected master data afterward stays Admin-only regardless of who registered it). Vehicle Types carry a Domain (Land/Water) that changes which fields a vehicle of that type asks for (Hull Material/Engine Type for Water vehicles).
- **Vehicle Location** — a map of hubs (named parking/stationing points) with vehicles grouped under them; hubs can't be deleted while a vehicle is still assigned to them.
- **Vehicle History** — an automatic timeline of every location/issue/condition/maintenance event per vehicle.
- **Condition Monitoring** — a Custodian's routine physical check-in on a vehicle, independent of any reported issue.
- **Maintenance Schedule** — preventive maintenance planning, with optional recurrence (fixed intervals or a custom number of months) and an urgency view (Overdue / 1–3 / 4–7 / 8+ days).
- **Maintenance Records** — a direct log of maintenance done outside the ticket workflow (external shop work, historical records).
- **Reports** — generated summaries across fleet, maintenance, issues, and users, exportable per report type.
- **Activity Log** — a full audit trail of actions taken across the fleet, scoped per Barangay.
- **Users** — account management: roles, activation, and (for Admin) approving pending signups.

## 7. Role responsibility summary & sidebar/navigation decisions

The one-line version of each role, for quick reference:

- **Custodian**: Report → Propose → Check → Verify → Close.
- **Admin**: Manage → Review → Assign → Monitor.
- **Maintenance Personnel**: Diagnose → Repair → Record → Complete.

Four navigation decisions are deliberate and final — not oversights to "fix" in a future pass:

1. **Custodian's "My Tasks" stays one tabbed sidebar entry** (My Tickets / History), rather than separate entries for verification and prior work. Independent repair verification is reached from the assigned ticket or the ready-to-close queue.
2. **Maintenance Personnel's "Vehicle Issues" and "Report Technical Issue" are one combined module** — the report action lives inline in the issue list's header, not as a second sidebar item. Reporting is an action performed from that list, so combining them avoids duplicate navigation.
3. **Readiness Check has no standalone sidebar item.** It stays a contextual action on the relevant vehicle (View Vehicles / vehicle profile), since a readiness check is always about one specific vehicle — a separate module would just make the user pick the vehicle a second time.
4. **Maintenance Personnel's repair evidence stays inside the repair/work-order workflow** (the repair log's own photo field), not the generic Vehicle Documents store. The repair already knows the vehicle, ticket, sub-issue, and mechanic — attaching evidence there avoids a duplicate upload with none of that context.

## 8. Security posture

- Sanctum bearer tokens, no server-side session state.
- Per-role scoping enforced both in the UI (which modules render) and in the backend (`RestrictSuperAdminScope`, `EnsureUserIsActive`, and per-Barangay query scoping via a `BelongsToBarangay` model concern).
- Inactive accounts cannot log in at all.
- Destructive actions that matter operationally (e.g. deleting a hub with vehicles still on it) are blocked server-side, not just hidden in the UI.

## 9. Where to look in the code

| Concern | File |
|---|---|
| Nearly all fleet-facing UI | `frontend-spa/src/views/Workspace.jsx` |
| Role-specific dashboard summaries | `frontend-spa/src/components/RoleDashboard.jsx` |
| Super Admin UI | `frontend-spa/src/views/SuperAdminWorkspace.jsx` |
| Shared mechanical brand gear | `frontend-spa/src/components/VMSLogo.jsx` |
| Shared styling | `frontend-spa/src/App.css` |
| Auth, registration, impersonation | `backend-api/app/Http/Controllers/AuthController.php` |
| Vehicles, issues, conditions, schedules, maintenance records | `backend-api/app/Http/Controllers/FleetController.php` |
| Ticket workflow | `backend-api/app/Http/Controllers/TicketController.php` |
| Map hubs | `backend-api/app/Http/Controllers/HubController.php` |
| Super Admin backend | `backend-api/app/Http/Controllers/SuperAdminController.php` |
| Route definitions | `backend-api/routes/api.php` |

## 10. Related documents

- `System-Architecture.svg` — component diagram (this folder).
- `ERD.svg` — entity-relationship diagram of the database (this folder).
- `Must-Haves.md` — required functionality checklist and known gaps (this folder).
- `Role-Realignment-Analysis.md` — the analysis and decisions behind the current role/permission model and sidebar structure (this folder).
- `System-Capabilities.md` — a full, module-by-module reference of everything the system can currently do, including explicitly-flagged partially-built/unused features (this folder).
- `Production-Readiness-Audit.md`, `Final-Stabilization-Review.md`, `Final-Senior-System-Review.md`, and `Final-Capability-Impact-And-Audit.md` — four audit passes behind the current verification/authorization model (schedule-ticket pipeline, document access, verification-bypass removal, schedule-completion self-attribution, the Fleet Capability & Readiness Impact feature, and the mid-repair-reassignment self-verification fix) — point-in-time records of what was found and fixed, not living documentation (this folder).
- Root `README.md` — a fuller project overview (setup, data model, API endpoints, deployment); kept in sync with this document's role/workflow model, but where the two ever disagree, treat this document (and the code) as current.
- Root `ROLE-AUDIT.md` and `VMS Docs/VMS-IMPROVEMENT-PLAN.md` — **point-in-time audit/planning snapshots**, not living documentation; several of the role/permission decisions they describe have since been superseded (see `Role-Realignment-Analysis.md`). Useful for history, not for "how the system works today."
- `docs/` (project root) — earlier QA, defense, and design documents from previous phases of the project; same caveat as above.

## Recent changes (see `System-Capabilities.md` §5.3, §5.4, §14b for detail)

- Focused role dashboards; separate Super Admin platform overview and Barangay Management; flat Super Admin navigation and Province → City/Municipality → Barangay → Staff impersonation selection.
- Three-step registration, clearer ticket-creation layout, compact schedule summaries, and shared blue/white mechanical gear branding. See `Must-Haves.md` for the supplied academic requirement baseline, implementation mapping, and role-alignment items requiring adviser review.
- Cannibalized repairs have **no Admin approval**: the mechanic records one donor vehicle and a CRUD list of donor parts on the Record Repair Work page; submitting goes straight to the Custodian and files one donor Issue Report. Standalone Cannibalization records live on the vehicle profile (no sidebar item).
- External-shop repairs are handled by the Admin (dispatch and return details); the handler can't verify.
- The Custodian closes tickets: verification is a popup (Approve / Disapprove with reason); "Tickets Ready to Close" is on the Custodian page; closing notifies the Admin. Verification is never done by Admin or by the repairer.
- Vehicle Management shows Dispatched (open Usage Log trip) with a Dispatch action and popup.
- Assigned-personnel avatars on tickets, schedules and records; progress ring on ticket cards; inline per-field validation messages in the shared form.
