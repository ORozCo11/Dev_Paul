# Barangay Vehicle Management System (VMS) — Documentation

Reviewed **25 October 2026** against integrated revision `130fe38` on `dev-paul`. See [current revision notes](Current-Revision-2026-10-25.md) for current behavior, audit disposition and verification limits.

## 1. What this system is

The Barangay VMS is a fleet management platform for a barangay's emergency response vehicles — ambulances, fire trucks, and rescue boats. It tracks whether a vehicle is ready to respond right now, and manages the full lifecycle of keeping it that way: reporting problems, inspecting them, repairing them, verifying the repair, and scheduling preventive maintenance before problems happen.

It is explicitly **not** a dispatch/operations system, a document-compliance tracker, or a mileage-based service planner. Vehicle dispatch, the Dispatched badge/action, Usage Log and temporary-release feature have been removed. Availability, physical condition and verified readiness remain separate. Sending a vehicle to an external shop remains part of the repair workflow.

See `System-Architecture.svg` for the component overview. For diagram revision, use [ERD.md](ERD.md), [Use-Case-Diagram.md](Use-Case-Diagram.md) and [requirements traceability](Requirements-Traceability.md). The ERD distinguishes logical associations from physical constraints.

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
| **Custodian** | One Barangay | Files Issue Reports, is the **only** role that creates a Maintenance Ticket (by proposing one for Admin's approval), registers/imports vehicles as a standard role duty, verifies a mechanic's completed repair, records preventive-maintenance checks and schedules. |
| **Maintenance Personnel** | One Barangay | Can file a technical Issue Report (routed to the Custodian, who still owns ticket creation), repairs assigned sub-issues, logs work and parts used. |
| **Super Admin** | Platform-wide | Manages Barangays and their user accounts, approves each Barangay's first-ever Admin, regenerates registration codes, handles concern reports, handles platform support; workspace switching uses the dedicated switcher account. Sees **no** fleet data — vehicles, tickets, and issues are intentionally out of its scope. |

The intended operational boundary for Admin, Custodian and Maintenance Personnel is their own Barangay. Vehicle-rooted query scopes enforce that boundary for many records, but shared-catalog writes and issue/file access have confirmed open exceptions (AUD-24-01/02/03); full isolation must not be claimed as accepted yet. A user can hold more than one of these three roles at once (e.g. a small Barangay's one staff member covering both Custodian and Maintenance Personnel duties).

## 4. Registration & onboarding

- Anyone can reach the public `/register` page, but every registration requires a **Staff Registration Code** — a per-Barangay code the barangay office hands out, checked server-side (`hash_equals`).
- The **first person to register for a given Barangay becomes its Admin** — but, like every other registrant, starts inactive and cannot log in until approved. Because that Barangay has no Admin yet to approve them, a **Super Admin** reviews and approves this specific case instead, from a dedicated Pending Approvals queue; they're notified the moment the registration happens.
- Everyone who registers after that must choose Custodian or Maintenance Personnel, and their account stays inactive until an existing Admin approves them from the Users page.
- See `Must-Haves.md`'s workflow safeguards and acceptance notes for why this two-track approval flow replaced the old "first registrant goes live instantly" behavior.

## 5. Core workflow: the maintenance ticket lifecycle

This is the heart of the system. **Every ticket enters as a Custodian's proposal that an Admin must review and approve before it's live** — Admin has no direct "create ticket" path: `ticket.create` is a permanently empty permission grant (nobody holds it, by design). A proposal has two possible sources: a human Custodian (`POST /tickets/propose`), or the system itself, auto-proposing one when a Maintenance Schedule's date arrives (§6) — either way, it lands at `Pending Approval` and goes through the exact same Admin review, never a bypass to `Active`. A Custodian who already knows what's wrong (they filed, or were pointed to, an Issue Report) submits a full proposal, picking one repair path: **in-house** (repaired by the barangay's own mechanics; can optionally be flagged as **cannibalized**, swapping in a part taken from a donor vehicle instead of a new one), or **external** (sent to an outside shop). Each sub-issue in the proposal carries its own Repair Type and optional Suggested Mechanic — a single ticket can mix different kinds of repair (e.g. a brake job and an electrical fix) without forcing one category on every sub-issue. The ticket's title is generated automatically when the proposal is created, using the barangay ticket number, vehicle and issue summary; the Custodian does not type a separate title. Admin assigns a mechanic to each in-house sub-issue at approval, and different sub-issues may have different mechanics. A proposal can optionally link to an existing open Issue Report on that vehicle, carrying forward its vehicle, issue type, description, severity, and reporter so none of it is re-typed.

The workflow end to end:

1. **Report** — a Custodian or Maintenance Personnel files an Issue Report (a Maintenance Personnel account is notified-forward to the Custodian, since they still can't create the ticket themselves — see §3).
2. **Propose** — the Custodian proposes a ticket (optionally linked to that report), entering **Pending Approval**.
3. **Approve** — the Admin reviews, can edit ticket/sub-issue fields, and approves (the ticket becomes **Active**, assigning the mechanics for its in-house sub-issues) or final-declines with a reason (the proposal remains traceable as **Declined**). Admin can instead request corrections; the assigned Custodian edits and resubmits the same Pending Approval proposal for fresh review. Undecline only returns a declined proposal to review, without starting work.
4. **Repair** — the assigned Maintenance Personnel logs work on the ticket's sub-issues. Admin handles external-shop sending, return and repeat rework cycles; previous handlers cannot verify that work. External costs and provider details are included in the unified ledger at closure.
5. **Verify** — the assigned Custodian runs a functional test. A user must not verify their own repair — enforced server-side even for a dual-role (Custodian + Maintenance Personnel) account on the same sub-issue. Verification is Custodian-only, with no Admin fallback; if the assigned Custodian is unavailable, the ticket is reassigned to a different Custodian instead.
6. **Decide** — the assigned Custodian uses the verification popup to Approve or Disapprove. Approval requires explicit personal-test attestation. Disapproval requires a reason and selects the failed items for rework; other submitted repairs stay submitted. The former Admin confirmation and decision-close flow is not the current closure path.
7. **Close** — successful Custodian verification closes the ticket and notifies the Admin. The server checks assignment, repair readiness, and self-verification restrictions before accepting the decision.

Extra signals layered on top: **recurrence detection** (the same fault reported again soon after a "fix" flags as a likely rework) and **aging** (a ticket open more than 30 days is flagged).

Selected unfinished work can be reassigned without resetting submitted repairs. Cancellation requires a reason and notifies responsible staff; restoration preserves submitted work and resumes verification when ready. Tickets with repair evidence cannot be permanently deleted. Decision history and fresh locked checks protect the process against stale actions and conflicting source ownership. The schedule-conversion job checks each minute against Philippine date and service time; the deployment must run Laravel's scheduler. See [the ticket process review](Ticket-Process-Review-2026-10-24.md) for the fixes, migration and verification evidence.

## 6. Other modules

- **Issue Reports** — a reporting inbox where Custodians and Maintenance Personnel file reports and Admin reviews them. It also tracks reported donor-vehicle findings and other defects. Filing one lands straight on that report's own page with a **Create Maintenance Ticket** action right there (Custodian only) — not back on a list to search for what was just submitted. The Maintenance Personnel list is limited to reports they personally filed; direct detail access currently bypasses that restriction (AUD-24-02), and filing one notifies that barangay's Custodians directly. The only Admin action on one is **Dismiss** (with a required reason) once it's no longer relevant; if a ticket already exists for it, the report's View action opens that ticket directly instead.
- **Vehicle Management / Vehicle Types** — the vehicle catalog. Admin registers vehicles directly; Custodians may register/import as a standard role duty. The legacy per-account flag does not revoke that capability; protected master-data edits remain Admin-only. Vehicle Types carry a Domain (Land/Water) that changes which fields a vehicle of that type asks for (Hull Material/Engine Type for Water vehicles). These types are shared; cross-barangay write effects remain open under AUD-24-03.
- **Vehicle Location** — a map of hubs (named parking/stationing points) with vehicles grouped under them; hubs can't be deleted while a vehicle is still assigned to them.
- **Vehicle History** — an automatic timeline of every location/issue/condition/maintenance event per vehicle.
- **Condition Monitoring** — a Custodian's routine physical check-in on a vehicle, independent of any reported issue.
- **Maintenance Schedule** — preventive maintenance planning, with optional recurrence (fixed intervals or a custom number of months) and an urgency view (Overdue / 1–3 / 4–7 / 8+ days).
- **Maintenance Records** — finished repair history from tickets, external shops and maintenance schedules. Each completed repair appears separately with its work performed, source and completion date; ongoing work remains in the work and verification views.
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
- Role/ownership guards exist in the backend, including `RestrictSuperAdminScope`, `EnsureUserIsActive`, and per-Barangay query scopes. These do not close the confirmed issue-detail, file-signing and shared-catalog findings. UI visibility is not sufficient authorization.
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

Use [the current document index](README.md) to choose the right reference.

- [System-Capabilities.md](System-Capabilities.md): detailed module and feature reference.
- [Must-Haves.md](Must-Haves.md) and [the requirements PDF](VMS-Must-Haves-Updated.pdf): requirements mapping and acceptance criteria.
- [Sample-Data.md](Sample-Data.md): team setup, demo accounts and recorded verification.
- [System-Audit-2026-10-24.md](System-Audit-2026-10-24.md): current open findings and retest criteria.
- [Ticket process review](Ticket-Process-Review-2026-10-24.md): field review, reproduced ticket workflow gaps and proposed improvements.
- [The presentation with examples](VMS-Evidence-and-Defense-Review-With-Examples.pptx): defense preparation and follow-along cases.
- [System-Architecture.svg](System-Architecture.svg), [ERD.svg](ERD.svg) and [Use-Case-Diagram.svg](Use-Case-Diagram.svg): diagrams.
- [Historical archive](../docs/archive/vms/README.md): earlier role decisions, audit passes, UI changes, geographic evidence and superseded exports. These describe earlier revisions.
- Root [README.md](../README.md): project setup and technical overview. The root `docs/` folder also contains earlier manuals and QA material.

## Recent changes (see `System-Capabilities.md` §5.3, §5.4, §14b for detail)

- Focused role dashboards; separate Super Admin platform overview and Barangay Management; flat Super Admin navigation and Province → City/Municipality → Barangay → Staff impersonation selection.
- Three-step registration, clearer ticket-creation layout, compact schedule summaries, and shared blue/white mechanical gear branding. See `Must-Haves.md` for the supplied academic requirement baseline, implementation mapping, and role-alignment items requiring adviser review.
- Cannibalized repairs have **no Admin approval**: the mechanic records one donor vehicle and a CRUD list of donor parts on the Record Repair Work page; submitting goes straight to the Custodian and files one donor Issue Report. Standalone Cannibalization records live on the vehicle profile (no sidebar item).
- External-shop repairs are handled by the Admin (dispatch and return details); the handler can't verify.
- The Custodian closes tickets: verification is a popup (Approve / Disapprove with reason); "Tickets Ready to Close" is on the Custodian page; closing notifies the Admin. Verification is never done by Admin or by the repairer.
- Assigned-personnel avatars on tickets, schedules and records; completion counts/stages on ticket cards and a progress bar/percentage in the ticket list; inline per-field validation messages in the shared form.

## 11. Audit baseline and acceptance

The 24 October probes recorded six findings at `0027023`. The table preserves their original descriptions. Later integrated code rejects future completion dates; the remaining controls require their specific acceptance retests. See [current audit disposition](Current-Revision-2026-10-25.md#audit-disposition).

| ID | Severity | Current problem |
|---|---|---|
| AUD-24-01 | High | A profile/free-text response can issue a signed URL for an inaccessible same-barangay managed file, bypassing the renewal endpoint's parent-record check. |
| AUD-24-02 | High | Direct issue details expose reports hidden by the list policy, including linked ticket details denied by the ticket endpoint. |
| AUD-24-03 | High | Barangay Admin edits to shared catalogs affect other barangays' types and completed records; catalog-backed historical rewrites have no corresponding edit audit entry. |
| AUD-24-04 | High | Parts removal flags condition for inspection but does not invalidate an earlier readiness pass. |
| AUD-24-05 | Medium | Shared CSV exports retain formula-leading untrusted values. Spreadsheet execution depends on the receiving application/settings. |
| AUD-24-06 | Medium | Schedule completion accepts future dates and uses them to seed future recurrences. |

Documentation discrepancies AUD-24-07/08 are now aligned with code: registration is a standard Custodian duty and the ticket list currently has percentage progress. This does not approve any change to the supplied academic baseline. See [the audit evidence and retest criteria](System-Audit-2026-10-24.md) and [Must-Haves acceptance criteria](Must-Haves.md#8-required-acceptance-criteria-from-the-24-october-audit).

Verification record: the merged revision passed 480 backend tests (2,245 assertions), frontend lint/build and targeted desktop/mobile checks. A later six-test isolated investigation (36 assertions), plus a CSV-generation probe, reproduced the findings above. Passing those probes confirmed the defective behavior, not its resolution. The investigation files were removed and no application fixes were made in this documentation update.

The editable requirements source is `Must-Haves.md`; its PDF is an earlier export and was not regenerated in this update. Older audit documents and the earlier generated DOCX are historical artifacts in `docs/archive/vms/`. Deployment storage policy, persistence, recovery and representative-user acceptance still need target-host evidence; this is not an exhaustive penetration-test certification.
