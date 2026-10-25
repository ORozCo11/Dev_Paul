# System audit ? 23 October 2026

Scope: the Laravel API, React workspace, role permissions, tenant scoping, and operational workflows at baseline `3b22cca`, followed by fixes and regression checks. This is a code and local test audit, not a claim that every possible defect has been eliminated.

## Workflow reviewed

- Registration ? pending approval ? activation; role editing, last-Admin/open-work guards, password changes and session revocation.
- Direct login versus dedicated impersonation; default Admin selection, cascading location/user selection, switching and refresh.
- Vehicle registration/import, custom fields, status and condition, readiness, retirement/restoration.
- Issue report ? Custodian proposal ? Admin review and per-sub-issue mechanic assignment ? repair logging ? independent Custodian verification ? ledger/archive/notifications.
- External-shop dispatch/return and cannibalized repairs; assignment changes and self-verification guards.
- Maintenance schedule proposal/approval, edits, cancellation/restoration, completion, recurrence and due-date conversion.
- Maintenance ledger verification/confirmation and decision closure, especially overlapping repairs and retired vehicles.
- Reports, pagination, list views, dashboard summaries, schedule action icons, file delivery, map/boundary handling, notifications and Activity Log.

## Confirmed findings and fixes

All items below are addressed in this change. Related symptoms are grouped so they are not counted repeatedly.

| ID | Impact | Finding | Correction and evidence |
|---|---|---|---|
| A01 | High | A schedule edit accepted `Scheduled`, bypassing Admin approval. | Edits cannot approve or complete a schedule. Legacy cancellation requests use the guarded cancellation action. Regression test rejects self-approval. |
| A02 | High | Schedule assignment accepted wrong-role, inactive or other-barangay users. | Creation, approval, editing and reassignment validate active Maintenance Personnel in the vehicle's barangay. Tests cover each invalid assignment class. |
| A03 | High | Changing an approved plan, or restoring a cancelled proposal, could proceed without fresh review. | Changed/restored plans become Pending Approval; Admins are notified. Tests cover both transitions. |
| A04 | High | Completed schedules and schedules already converted to proposals could still be rewritten or cancelled. | Completed plans and plans with a resulting record/ticket are locked. Matching list/card actions are hidden. Regression tests cover completed and proposal-linked plans. |
| A05 | High | A Custodian could replace the mechanic on an approved schedule through ordinary editing. | Approved assignment changes use the Admin reassignment action. The approved-plan edit form omits reassignment/status fields while pending plans retain mechanic selection; browser QA checks its request. |
| A06 | High | The activation endpoint could change an already-approved user's role without normal role-edit safeguards. | Role confirmation is limited to genuinely pending accounts; existing accounts use Edit User. A test verifies an active Admin cannot be demoted this way. |
| A07 | High | Admin-created active accounts lacked approval timestamps; active legacy accounts could appear pending and be permanently rejected. | Creation stamps approval; pending lists exclude active accounts; rejection refuses active or previously approved accounts. Regression coverage checks all three. |
| A08 | High | Admin password resets left existing tokens valid. | Reset revokes every target token. A test begins with two live tokens and verifies both are removed. |
| A09 | High | Self-service password changes left other sessions valid. | Other tokens are revoked while the current token remains valid. A real bearer-token test checks both results. |
| A10 | Medium | Bulk reassignment notified only a ticket-level previous mechanic, missing mechanics on split assignments. | Notify every distinct outgoing mechanic and the new mechanic. A two-mechanic regression checks both outgoing notifications. |
| A11 | High | Reassignment after repair submission retained submitted line statuses, allowing old work to be resubmitted under a new mechanic. | Return lines to Under Repair, clear pending verification data and retain prior notes/mechanic history. The new mechanic must log work before submission. |
| A12 | High | Adding a line to a ticket with split assignments could create an unassigned, stranded work order. | Require an explicit active local mechanic when there is no shared ticket mechanic. Tests cover rejection and successful assignment. |
| A13 | Medium | Bulk ticket reassignment accepted inactive mechanics. | Require active Maintenance Personnel. A regression checks rejection. |
| A14 | Medium | Unfinished sub-issues on cancelled tickets could block staff deactivation forever. | Open-work guards exclude closed/cancelled parent tickets. Regression verifies deactivation is permitted after cancellation. |
| A15 | High | Confirming or decision-closing one record could release a vehicle with another active repair. Dashboard synchronization also treated proposals as active repairs. | Shared vehicle-state coordination considers active tickets and unfinished records. Proposals do not themselves take vehicles out of service. Tests cover overlapping repairs and a dashboard refresh after cancellation. |
| A16 | High | Record verification/closure and old scheduled jobs could restore retired vehicles implicitly. | Preserve Inactive/Decommissioned states in record handling; refuse completion of retired vehicles' schedules. Regression cases cover verification, decision closure and schedule completion. |
| A17 | High | Sending a different repair method while logging an external job could bypass the recorded shop-return requirement. | A dispatched, unreturned job cannot be submitted regardless of the requested repair method. Regression attempts the bypass. |
| A18 | High | Platform role editing could convert the tenantless dedicated impersonation account into barangay staff. | Refuse conversion of the dedicated account through staff-role editing. A regression verifies the role stays Impersonator. |
| A20 | High | Due-schedule conversion checked eligibility and duplicates outside its transaction; the creator could belong to a different barangay. | Lock the schedule and vehicle before current-state/duplicate checks; select an active local Custodian and validate mechanic suggestions. Repeat-run and other-barangay creator tests pass. Schedule transitions also lock/re-read current rows, preventing stale completion after ticket conversion. |
| A21 | High | Vehicle inserts could commit before the import status and audit log; a later failure left an incomplete import summary despite saved vehicles. | Import validation, vehicle/history creation, completion and audit logging commit together under an import-row lock. The upload is deleted only after commit. A simulated completion failure verifies rollback and upload retention. |
| A22 | High | A late session check or profile refresh could log out or overwrite a newly switched account. | Bind requests to their captured token and ignore stale results; transient session-check failures do not delete credentials. Browser regression deliberately delays old responses across account changes. |
| A23 | Documentation | Capability descriptions disagreed with code on impersonation, mechanic assignments, schedule ownership, registration permission, verification UI, file delivery and removed cards. | Update the affected sections in System-Capabilities.md. Shared same-barangay Custodian planning is preserved, rather than imposing an owner-only rule from stale documentation. |
| A24 | High | Repair submission and ticket verification could authorize against route-bound assignments that had since changed. | Lock and reload the ticket and repair line before checking the actor. Deterministic stale-Custodian and stale-mechanic regressions confirm the former assignees are denied without creating ledger entries or repair logs. |

## Audit rounds

1. **Baseline:** all 446 existing backend tests passed (2,039 assertions), as did frontend lint and production build. This established that the later findings were gaps in coverage rather than an already-failing baseline.
2. **Reproduction and correction:** the first audit cases exposed 13 failing assertions and two incomplete record fixtures. After correcting those fixtures, the record tests confirmed premature release and unintended retirement changes. Fixes brought the initial 15 audit cases to green.
3. **Broader regression review:** added stale-request, multi-mechanic, cross-barangay due-job, password-session and import-rollback checks. Existing tests caught compatibility differences: shared Custodian schedule planning and guarded cancellation remain supported. Updated two outdated fixtures to represent a genuinely inactive pending registration and an explicitly assigned additional repair.
4. **Reinvestigation:** reviewed the resulting diffs and ran full backend regression, frontend lint/build and browser checks. The browser review also caught an audit-harness selector mismatch; the schedule edit test now uses the actual textbox label and verifies its submitted payload. The final review found one further stale-assignment authorization gap (A24); added the two reproductions, corrected it, and repeated the full suite.

## Regression artifacts

- `backend-api/tests/Feature/SystemWorkflowAuditTest.php`: 27 focused workflow/session/state regressions.
- `backend-api/tests/Feature/VehicleImportTest.php`: adds transaction rollback verification.
- `backend-api/tests/Feature/TicketWorkflowTest.php`: adds dashboard refresh to the proposal/availability regression.
- `logs/auth-session-audit-qa.mjs`: delayed-response account-switching checks against the actual AuthProvider.
- `logs/ticket-revisions-qa.mjs`: proposal layouts, per-line mechanic approval, locked schedules and schedule edit payload.
- `logs/request-ui-qa.mjs`: report pagination, filters, maintenance list, condition graph and schedule actions.
- `logs/impersonation-hub-qa.mjs`: real local login/switching, default workspace, location cascade, platform switching, refresh and mobile layout. Its created session tokens are revoked by the script.

## Final verification

- Full backend suite: **474 passed, 2,118 assertions** (446 baseline tests plus 28 added regressions).
- New audit class: **27 passed**; import rollback adds the remaining new case.
- Frontend ESLint and production build: passed.
- Browser checks: request/report/schedule checks passed; real local impersonation checks passed; delayed-response authentication checks passed. The expanded ticket/schedule form checks passed, including approved-versus-pending assignment behavior.
- `git diff --check`: passed. Generated QA screenshots and build artifacts are excluded from the source commit.

## Verification limits

The final clean pass means no unresolved defect was found in the reviewed paths and executed checks. It is not a guarantee about untested inputs, every browser/device, production data or external services. Stale-request tests are deterministic; a parallel multi-process load test against the deployed database was not performed. The audit did not alter production data or rerun destructive user seeders. External geocoder coverage and national geographic-reference completeness were not independently certified in this software audit. Historical public file URLs, if present in old data, still need a separate migration; new managed uploads use private storage and expiring signed delivery.

## Follow-up review and fixes

A later targeted review reproduced seven additional defects: verified record edits retained old sign-off; record edits accepted a different vehicle's issue; restoration ignored unfinished records; inactive Custodians could receive tickets; cancelled tickets accepted external-shop actions; record edits permitted self-donor vehicles; and recurrence duplicated a booking after 60 occupied dates. These are now corrected, with seven permanent regression tests (including the legacy self-verification guard and both external-shop actions).

The reported schedule completion problem was traced to the detail-page early return omitting the dialogs: clicking Mark as Done set state without displaying anything. Completion, reassignment and decline dialogs now render on the detail page. Browser QA exercises completion submission, all three dialogs, light/dark theme persistence, and the mobile toggle. Restored the shared light/dark header control for every workspace role.

Follow-up backend validation: 481 tests passed, 2,131 assertions. See `logs/schedule-theme-qa.mjs` for the new browser workflow checks. The earlier audit's clean pass applied to its tested paths; these additional findings demonstrate why it was not a guarantee of a defect-free system.

Follow-up frontend validation: ESLint, production build, schedule/theme browser checks, and the existing ticket-form/workflow browser checks passed.
