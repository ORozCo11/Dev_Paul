# Barangay VMS — Must-Haves

What the system must do to be considered complete, grouped by area. "Done" reflects the current `dev-paul` branch; "Gap" items are must-haves that are not fully met yet.

## 1. Fleet Records

- [x] Register a vehicle with type, capacity, brand/model, plate/registration, photo, and current location.
- [x] Support both Land and Water vehicle domains, with domain-specific fields (Hull Material/Engine Type for Water).
- [x] Track vehicle status (Available / Under Maintenance / Inactive) and physical condition independently.
- [x] Vehicle location on a map, grouped by named hubs; hubs cannot be deleted while vehicles are still assigned to them.
- [x] Full activity history per vehicle (location changes, issues, condition checks, maintenance).

## 2. Issue → Repair Workflow

- [x] Anyone can report a vehicle issue with severity, category, and description.
- [x] Admin creates a Maintenance Ticket from an issue (or from scratch), assigning a Custodian.
- [x] 5-phase ticket lifecycle: Open → Custodian inspection → Active (repair/verify/confirm per sub-issue) → Closed.
- [x] A ticket can be closed with unresolved sub-issues only via an explicit "decision close" that records why and whether the vehicle is fit for service.
- [x] Recurring-failure detection: flags when the same fault on the same vehicle has come back.
- [x] Aging flag on tickets open past 30 days.

## 3. Preventive Maintenance

- [x] Schedule a service with a date, type, assigned mechanic, and optional recurrence (Month / Quarter / 6 Months / Year / custom interval).
- [x] Completing a recurring schedule auto-creates the next occurrence.
- [x] Urgency view of upcoming/overdue schedules (Overdue, 1–3 days, 4–7 days, 8+ days).

## 4. Roles & Access

- [x] Three fleet-facing roles — Admin, Custodian, Maintenance Personnel — each sees only the modules relevant to their work.
- [x] A Super Admin role, separate from the fleet app, for platform-level account and Barangay administration.
- [x] Every fleet record (vehicles, tickets, issues, schedules, hubs, activity log) is isolated to its own Barangay.
- [x] New accounts require approval before they can log in — an existing Admin approves Custodian/Maintenance Personnel signups; a Super Admin approves a Barangay's first-ever Admin.

## 5. Auditability

- [x] Every create/update/status-change is written to an Activity Log, attributable to a user and timestamped.
- [x] Ticket detail view reflects other users' actions without a manual page reload.

## 6. Reporting

- [x] Exportable reports by category (fleet, maintenance, issues, users).
- [x] CSV export and print view on the tables that need it (Vehicle History, Activity Log, Reports).

---

## Known Gaps (must-have, not yet fully met)

1. **Vehicle photo upload.** Currently broken in at least one environment due to invalid/missing Supabase storage credentials — this is a configuration issue, not a code defect, and needs valid credentials supplied by whoever owns the Supabase project.
2. **User profile completeness from partial lookups.** A user's profile page can show blank Role/Phone/Address when opened from a form that only carried a name+email lookup (e.g. a ticket's assigned-custodian dropdown) instead of fetching the full record by ID.
3. **Automated tests.** No automated frontend tests exist for the UI flows described above; verification has been manual/visual. A must-have for long-term maintainability, not yet started.

## Resolved Gaps

- **Registration security (resolved).** The first person to register for a new Barangay used to become its Admin automatically and instantly active, gated only by a shared "Staff Registration Code" that also gated ordinary staff signup — anyone holding that code could race to register first and claim Admin uncontested. **Fix:** every registrant, first-for-Barangay or not, now starts `is_active: false` with no `approved_at`; login is hard-blocked until approved. An existing Admin still approves ordinary Custodian/Maintenance Personnel signups, but a Barangay's first-ever Admin is instead approved by a Super Admin, who is notified the moment that registration happens and reviews it from a dedicated Pending Approvals queue (reject deletes the account outright; approve activates it). See `AuthController::register()`/`notifySuperAdmins()`, `SuperAdminController::pendingApprovals()`/`rejectUser()`, and the Super Admin portal's Pending Approvals tab.

---

*This list reflects the state of the codebase as of the `Paul/UI-Cleanup-Filters-Tables-Vehicle-Types-Ticket-Detail` and `Paul/Forms-Persist-Draft-On-Back-Navigation` branches merged into `dev-paul`. Update it as gaps are closed or new must-haves are identified.*
