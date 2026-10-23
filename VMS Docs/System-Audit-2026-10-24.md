# Barangay VMS - Audit findings and acceptance criteria

Reviewed: **24 October 2026**. Audited application revision: **`0027023`**.

This records a targeted source/workflow investigation, not an exhaustive penetration test or deployment certification. Six implementation findings remain open. Documentation discrepancies AUD-24-07/08 have been aligned with current behavior in the living references; no application fix is claimed by that documentation change.

## Scope and verification evidence

- Reviewed authentication/roles, tenant scopes, issue and ticket visibility, private managed files, shared catalogs, readiness/cannibalization, schedule completion, CSV exports and the living documentation.
- The merged revision's recorded regression result was **480 backend tests / 2,245 assertions passed**, with frontend lint/build and targeted desktop/mobile checks passing.
- Six temporary API tests / 36 assertions, using Laravel's isolated in-memory SQLite database and fake file storage, reproduced the observed behavior. The tests asserted the defects, so their passing result does not mean the controls are safe. Temporary investigation files were removed.
- A separate direct invocation of the shared CSV generator preserved the benign value `=1+1` as an unprotected CSV cell. Spreadsheet execution was not exercised.
- No production records, live accounts or remote storage were used for these probes. No application fixes were made in this documentation revision.

## AUD-24-01 - Private-file signing bypass

**Severity: High. Status: Open.** Same-barangay access; an existing managed-file reference must be known.

`ManagedFileController::refresh` checks access to the file's parent record. `SignManagedFiles`, however, scans arbitrary strings in successful JSON responses and signs matching file references after only its tenant check. A profile address can therefore be used as an unrelated echo of a repair-file reference.

**Observed:** a mechanic's renewal request was refused with 403. Updating that mechanic's own profile address to the same managed-file reference returned a newly signed link; downloading it returned 200 with the protected QA bytes, despite the mechanic having no assignment to the repair.

**Source:** `backend-api/app/Http/Middleware/SignManagedFiles.php:36-42`; `backend-api/app/Http/Controllers/ManagedFileController.php:13-28`; `backend-api/app/Http/Controllers/AuthController.php:508`.

**Acceptance:** apply parent-record authorization to every URL-issuance path, not only renewal. A known but inaccessible reference echoed through profile or free-text responses must not yield a usable signature. Preserve valid authorized links and cross-barangay denial.

## AUD-24-02 - Issue-detail visibility bypass

**Severity: High. Status: Open.** Affects ownership/assignment restrictions within one barangay.

`FleetController::issues` filters a mechanic's list to their own reports and restricts another Custodian's reports. `showIssue` checks only the broad `issue.view` ability and returns the report, linked ticket and attachments without matching those list restrictions.

**Observed:** both a mechanic and another Custodian had an empty issue list and received 403 for the linked ticket's direct endpoint. They nevertheless received 200 for the report detail, including the hidden report description and the linked ticket description.

**Source:** `backend-api/app/Http/Controllers/FleetController.php:1482-1486`, `1513-1546`; `backend-api/app/Http/Controllers/TicketController.php:117-134`.

**Acceptance:** use one visibility rule for list, detail, nested ticket metadata and attachments. IDs must not bypass the same-role ownership restrictions; keep legitimate Admin and assigned-user access.

## AUD-24-03 - Global catalog writes exceed barangay authority

**Severity: High. Status: Open; authority policy must be decided.** The shared catalog design is deliberate, but barangay-scoped Admins currently receive its global write effects.

Maintenance-type/fault-category renames use `withoutGlobalScopes()` to rewrite all referencing records. Vehicle categories are also shared, and any barangay Admin may change their domain/default criticality. Those changes are not limited to the Admin's vehicles or unfinished work.

**Observed:** an Admin in barangay A renamed a maintenance type used by a Completed record in barangay B. The closed record changed without a corresponding activity entry. The same Admin changed a shared Land type to Water/Critical, changing the inherited criticality for barangay B's vehicle.

**Source:** `backend-api/app/Http/Controllers/CatalogController.php:103-123` and `69-70`; `backend-api/app/Http/Controllers/FleetController.php:880-899`; `backend-api/app/Models/VehicleCategory.php`.

**Acceptance:** isolate tenant-owned catalogs or require suitable platform-wide authority for global edits. Preserve historical repair evidence and audit any authorized cross-record correction. Do not silently rewrite another barangay's completed records or safety classifications.

## AUD-24-04 - Parts removal leaves an earlier readiness pass current

**Severity: High. Status: Open.** This is a stale physical-evidence problem, not a request to merge availability and condition into one status.

Standalone cannibalization changes a Good source vehicle to Needs Inspection but leaves its earlier passing readiness check in effect. `responseReadinessState` checks availability and the age/pass result, without invalidating a pass recorded before parts removal.

**Observed:** a freshly checked Available vehicle had an engine assembly recorded as removed. Its condition became Needs Inspection, while the vehicles API still reported readiness `ready`.

**Source:** `backend-api/app/Http/Controllers/CannibalizationController.php:133-139`; `backend-api/app/Http/Controllers/FleetController.php:483-506`.

**Acceptance:** invalidate readiness evidence that predates a relevant physical change and require a fresh check. Recheck fleet coverage/readiness displays while retaining separate availability, physical condition and verified-readiness indicators.

## AUD-24-05 - Formula-leading CSV cells remain executable data

**Severity: Medium. Status: Open.** Impact depends on the receiving spreadsheet application and settings.

The shared CSV generator quotes separators/newlines but does not neutralize formula prefixes. Profile editing accepts a formula-leading name, which can then appear in an authorized user's export.

**Observed:** profile update accepted `=1+1`. Direct invocation of `exportRowsToCsv` produced `Name` followed by an unprotected `=1+1` cell. No malicious formula was executed.

**Source:** `frontend-spa/src/views/Workspace.jsx:10600-10613`; `backend-api/app/Http/Controllers/AuthController.php:508-521`. Background: [OWASP CSV Injection](https://community.owasp.org/attacks/CSV_Injection).

**Acceptance:** define a spreadsheet-safe text-export policy, cover formula-leading characters/control characters and test every shared export consumer. Do not claim server-side code execution from this probe or a universal mitigation for all spreadsheet/import consumers.

## AUD-24-06 - Schedule completion accepts future dates

**Severity: Medium. Status: Open.** Requires the schedule's assigned mechanic.

Completion validates `date_completed` only as a date. It uses that value for the created ledger record's start date and the recurring schedule's next date.

**Observed:** completing with `2040-01-01` returned 200, marked the schedule Completed, set the new record's start to that date and generated the next occurrence for `2040-02-01`.

**Source:** `backend-api/app/Http/Controllers/FleetController.php:3002`, `3016`, `3046`, `3084`.

**Acceptance:** reject future completion dates using an agreed calendar/timezone rule. A rejected request must leave the schedule, ledger and recurrence unchanged; valid current/historical work must still complete correctly.

## AUD-24-07 - Registration eligibility description

**Severity: Low. Status: Documentation aligned; baseline/adviser acceptance remains separate.**

The earlier capabilities reference said a Custodian needed an Admin-granted Vehicle Registration toggle. Current code intentionally treats registration/import as standard Custodian duties and retains the old flag only as legacy account data. A Custodian with `can_register_vehicles=false` still passed the eligibility checks.

**Source:** `backend-api/app/Models/User.php:160-168`; `backend-api/config/permissions.php:27-30`; `frontend-spa/src/views/Workspace.jsx:224`.

**Documentation update:** `Must-Haves.md`, `Documentation.md` and `System-Capabilities.md` now describe the current eligibility. This does not certify that the supplied baseline's role changes have adviser approval.

## AUD-24-08 - Ticket progress description

**Severity: Low. Status: Documentation aligned; no UI behavior changed.**

The older description said ticket percentage graphics were removed. The merged ticket list renders a progress bar and percentage; cards show counts/stages and the old card ring is removed.

**Source:** `frontend-spa/src/views/Workspace.jsx:15648-15652`; the previous `System-Capabilities.md` interface-conventions entry.

**Documentation update:** the living references distinguish card counts/stages from the list's percentage display. This records actual code behavior rather than approving a new requirement.

## Retest order and limits

1. Close file-signing and issue-detail authorization bypasses.
2. Decide shared-catalog authority and protect completed historical evidence.
3. Invalidate stale readiness after physical changes.
4. Validate CSV text safety and schedule completion dates.
5. Add permanent rejection-based regression tests, rerun the full suite and affected browser workflows, then complete target-host acceptance.

The current application has no vehicle dispatch, Usage Log or temporary-release feature. External-shop sending/return and mechanic assignment remain maintenance functions. Passing existing regressions is compatible with these newly reproduced defects; it is not a guarantee that no additional issues exist.
