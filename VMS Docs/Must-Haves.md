# Barangay VMS — Must-Haves and Requirement Alignment

Reviewed **25 October 2026** against integrated revision `130fe38` on `dev-paul`. See [current revision notes](Current-Revision-2026-10-25.md) for current behavior, audit disposition and verification limits. The supplied academic baseline remains unchanged.

Status key: **Implemented** means an identifiable code path exists; it does not claim deployment acceptance. **Verify in deployment** means credentials, storage, or an end-to-end acceptance check is still needed. **Open finding** means a confirmed defect prevents acceptance of the affected control. **Role alignment** means the capability exists but the responsible role differs from the supplied baseline and should be reviewed with the adviser.

## Current scope and audit status - 25 October 2026

This is a fleet **readiness and maintenance** system. Operational dispatch, a Dispatched availability state, trip tracking, Usage Log views and temporary-release workflows are outside the baseline and have been removed. Admin handling of an external-shop repair remains a maintenance workflow. Availability (Available / Under Maintenance / Inactive / Decommissioned), physical condition and verified readiness remain separate indicators.

The 24 October audit recorded six findings at `0027023`. Later integrated code rejects future completion dates. Other findings retain their specific acceptance retests rather than being automatically closed by integration. See [current revision notes](Current-Revision-2026-10-25.md) and section 8.

## Working references for ERD and use-case revision

Use [Requirements-Traceability.md](Requirements-Traceability.md) for stable UC-01 through UC-18 identifiers, actor assignments, entity links and acceptance rules. Edit [ERD.md](ERD.md) and [Use-Case-Diagram.md](Use-Case-Diagram.md) alongside this requirements source. The SVGs are current overview diagrams; PDFs and slides remain older exports. Requirements marked role alignment still need adviser agreement.

## 1. Platform requirements from the supplied document

| Baseline requirement | Status | Current implementation / evidence |
|---|---|---|
| Web-based desktop platform | Implemented | React/Vite frontend and Laravel API; responsive layouts also support smaller screens. |
| Admin, Custodian, and Maintenance Personnel access | Implemented; open findings | Central abilities in backend-api/config/permissions.php, backend role/ownership guards, frontend module gates. Multi-role accounts are supported. Issue-detail visibility and global catalog writes have open authorization findings (AUD-24-02/03). |
| Organized, updated vehicle records | Implemented | FleetController vehicle endpoints, vehicle catalog and individual profiles, land/water type fields, photos and locations. |
| Maintenance records and schedules | Implemented | FleetController record/schedule endpoints; ticket repairs, recurrence, approval and scheduled-to-ticket processing. |
| Dashboard summaries of status and activities | Implemented; open finding | Distinct Admin decisions, Custodian inspection/verification, and personal Maintenance work dashboards. No invented trends or weather data. Readiness can remain fresh after parts removal; AUD-24-04 must be resolved. |
| Secure cloud storage, backup and retrieval of images/files | Open finding; deployment checks | Private managed storage and five-minute signed links exist, but an unrelated profile response can sign an inaccessible same-barangay file (AUD-24-01). Successful uploads, retrieval, retention and recovery depend on deployment credentials and storage configuration; backup/recovery has not been demonstrated by this UI change. |

## 2. Administrator requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| Manage all vehicle records | Implemented | Register, edit and archive barangay-scoped vehicles. Vehicle registration is a standard Custodian duty under the current permission map; the obsolete delegation checkbox has been removed. |
| Manage vehicle categories/types | Open finding | Shared types and domains exist, but a barangay Admin can change types used by other barangays (AUD-24-03). |
| Update vehicle locations | Implemented | Vehicle locations and hub management with map views; occupied hubs cannot be deleted. |
| Monitor conditions needing inspection/repair | Implemented | Vehicle condition/readiness views and fleet-condition analytics; recording physical condition checks is Custodian-only. |
| Manage vehicle issue reports | Implemented | Review reports and linked tickets; dismiss obsolete reports with a reason. Custodians propose tickets. |
| Manage maintenance records | Implemented | Maintenance record oversight and repair-ticket history; ownership and verification guards still apply. |
| Schedule maintenance | Role alignment | Custodian books a schedule; Admin approves/declines and reassigns personnel. Admin does not originate a booking under the current permission map. |
| View vehicle history | Implemented | Automatic per-vehicle event timeline. |
| Generate vehicle, issue, maintenance and history reports | Implemented; open finding | Reports module plus relevant CSV/print exports. Validate report formats and neutralize formula-leading cells in all CSV exports (AUD-24-05). |
| Monitor logs and user activities | Implemented | Barangay-scoped activity log and platform-only Super Admin log. |
| Dashboard summaries and recent updates | Implemented | Four primary KPIs, decisions queue, ticket workload, fleet condition and concise coverage warnings. Details are linked from the queue. |
| Create user accounts | Implemented | Admin user.create permission and user-management flow; public signup additionally requires registration code and approval. |

## 3. Custodian requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| View vehicle details and availability | Implemented; open finding | Vehicle catalog and profiles; availability, physical condition and verified readiness remain separate. A parts-removal event does not yet invalidate an earlier readiness pass (AUD-24-04). |
| Search/filter vehicles | Implemented | Search, filters, table/card views and column controls. |
| Report vehicle issues | Implemented | Issue reporting with category, severity, description and linked proposal flow. |
| Upload issue photos | Verify in deployment | Issue-photo inputs/storage integration exist. Validate upload and authorized retrieval against configured cloud storage. |
| Monitor submitted reports/status updates | Open finding | Lists apply ownership restrictions, but direct issue-detail requests expose another staff member's hidden report and linked ticket details (AUD-24-02). |
| View ongoing/completed maintenance | Implemented | Maintenance ledger, ticket details, repair logs and verification history. |
| Dashboard of available vehicles, issues and maintenance | Implemented with role focus | Verification, readiness and unresolved issue KPIs; one tasks queue, one condition chart and readiness by vehicle type. Availability is accessible from Vehicles. |

Additional current responsibilities: propose maintenance tickets, record condition/readiness checks, book schedules, and independently verify completed repairs. Successful Custodian approval closes the ticket; Admin does not verify a repair.

## 4. Maintenance Personnel requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| View reported vehicle issues | Open finding | Own-report lists and assigned-ticket access exist, but direct issue-detail access bypasses the list restriction (AUD-24-02). |
| Add maintenance records | Implemented with scope | Record repair work on assigned sub-issues and use permitted maintenance-record actions. |
| Update repair progress/completion | Implemented with scope | Assigned work logs and submission; a mechanic cannot independently verify or close their own repair. |
| Manage maintenance schedules | Role alignment | View and perform assigned scheduled work. Booking/editing is Custodian-owned; approval/reassignment is Admin-owned. Current completion validation rejects future Philippine dates; AUD-24-06 retains target-environment acceptance checks. |
| Add repair actions and remarks | Implemented | Repair log, parts, evidence, donor-part records and supporting notes. The current repair-log UI does not ask for Start/Completion dates. |
| Monitor upcoming maintenance | Implemented | Personal upcoming/overdue schedule KPIs and assigned-work queue. |
| View maintenance history | Implemented | Work tracker, completed repair history and permitted maintenance records. |
| Dashboard of issues, records and vehicles needing attention | Implemented with personal scope | Three personal KPIs, assigned work, workload by actual ticket status and recent completed work. Fleet-wide analytics remain in authorized fleet modules. |

## 5. Current workflow and safeguards

- [x] Ticket proposals enter Pending Approval. Custodian proposes; Admin reviews/assigns; Maintenance Personnel repairs; assigned Custodian verifies and closes, or returns the work with a reason.
- [x] Self-verification is blocked server-side, including multi-role accounts. Reassign an independent Custodian when needed; there is no Admin verification fallback.
- [x] Cannibalized repairs record donor vehicles and parts and generate donor follow-up issues. The current flow does not require the retired Admin cannibalization approval step.
- [x] External-shop handling and verification duties are separated.
- [x] Schedule bookings require approval; recurrence and schedule-to-ticket processing remain intact.
- [x] Vehicle-rooted tenant scopes and the Super Admin fleet allowlist exist.
- [ ] Close the issue-detail ownership, private-file signing and shared-catalog scope findings before treating access control as accepted (AUD-24-01/02/03).
- [ ] Invalidate stale readiness evidence after a parts-removal event (AUD-24-04).
- [x] Last-Admin and open-work deactivation guards remain enforced.
- [x] The dedicated switcher uses authorized account candidates and an automatically recorded access description; production tokens expire after 30 minutes and writes are restricted server-side. Local development has explicit expiry/write exceptions.
- [x] The account menu identifies the current user and role. Ordinary support sessions offer End Impersonation; the dedicated switcher retains its account picker. Ending calls the existing logout endpoint for token revocation and audit logging before restoring the original session.
- [x] Registration retains first-Administrator versus ordinary staff approval routing, registration-code checks, and inactive-until-approved behavior.

## 6. UI requirements delivered in this revision

- [x] Three focused fleet dashboards with real data, scoped assignments, task links and useful empty states.
- [x] Restored Super Admin overview with three summary cards, administrative actions and a coverage chart.
- [x] Flat Super Admin navigation and a dedicated Barangay Management screen; existing recovery, boundary review, registration codes and account controls remain accessible.
- [x] Province → City/Municipality → Barangay → Staff impersonation cascade, using barangay IDs to distinguish duplicate names.
- [x] Search/filter improvements for users, barangays, concerns and platform activity.
- [x] Three-step registration form, wider desktop layout and readable introduction.
- [x] Cleaner ticket-creation hierarchy and compact schedule summaries with full details under View.
- [x] Shared eight-tooth blue/white SVG brand gear, transparent background, contrasting center ring, stationary wordmark and existing rotation timing. Reduced-motion preference is respected.

## 7. Acceptance checks and remaining work

**Historical verification record (24 October):** on revision `0027023`, the recorded full suite passed **480 backend tests (2,245 assertions)**; frontend lint, production build and targeted desktop/mobile merge checks passed. The 24 October investigation then reproduced the observed behavior with **six isolated API tests (36 assertions)** and a direct CSV-generation probe. Those investigation tests passed because they asserted the defective behavior; they are not evidence that the defects were fixed. No application fixes were made in this documentation update.

**Acceptance remains open:** resolve AUD-24-01 through AUD-24-06, retain regression tests that reject the bypasses, repeat affected workflows, and perform target-deployment storage/recovery acceptance. SQLite removal-migration up/down/reapplication was verified on fresh and populated databases; other database engines were not exercised for that migration.

### Historical verification notes - 15 to 21 October 2026

The results below describe their original revisions. Older statements that all findings were fixed do not close the findings recorded on 24 October.


Regression fix follow-up, 21 October 2026: all **22 audit findings have local fixes**. **422 backend tests pass (1,770 assertions)**, ESLint is clean, the production build passes without a chunk-size warning, seven geometry/calendar tests pass, and **27 targeted browser assertions pass**. Storage now uses private managed objects and temporary signed links; the new migration was applied locally after a backup. Target-host bucket policy, persistent storage, legacy migration, and backup/restoration remain deployment acceptance checks. See [the fix report](../docs/archive/vms/REGRESSION-FIXES-2026-10-21.md) for evidence and rollout instructions.

Regression audit, 21 October 2026: **415 backend tests passed (1,730 assertions)**, six geometry tests passed, and the frontend build passed. Browser/API regression testing recorded **22 open findings (4 High, 13 Medium, 5 Low)** across functional behavior, interface consistency, documentation, and tooling/performance. Frontend lint reports **59 errors and 18 warnings**. Registration, approval, internal/external repairs, rework, vehicle lifecycle, scheduled maintenance, reports, notifications, and support were exercised with isolated QA data. This is not deployment acceptance or exhaustive coverage of every control. See [the current regression report](../docs/archive/vms/REGRESSION-REPORT-2026-10-21.md) for reproduction steps, evidence, previously fixed findings, and remaining coverage.

Registration geography, 15 October 2026: the cascading selectors now use **42,046 preloaded PSGC barangay references**, including Cebu City's **80 barangays**. Free-text barangay creation is rejected server-side; selected city/parent relationships are validated. Boundaries are resolved by PSGC code, with missing matches shown as unavailable instead of substituting another barangay. **403 backend tests passed (1,622 assertions)** after this change. See [registration geography](../docs/archive/vms/REGISTRATION-GEOGRAPHY.md) for source dates, boundary coverage, deployment steps, and evidence.

Boundary correction, 15 October 2026: Suba (Pob.), Bantayan now uses a detailed 1,281-vertex PSA/NAMRIA reference instead of the coarse triangle. Existing automatic references and new registrations share the correction; map rendering and location checks preserve holes and islands. **406 backend tests passed (1,649 assertions)** plus three geometry tests. See [registration geography](../docs/archive/vms/REGISTRATION-GEOGRAPHY.md#boundary-detail-correction--15-october-2026).

Suba verification follow-up: the government-hosted PSA/GeoRisk polygon agrees with the imported outline; the northern screenshot vicinity lies in Binaobao in that reference. Provider place labels are now optional, and the app labels the selected reference polygon internally. This verifies reference consistency, not current legal boundaries. See [comparison and evidence](../docs/archive/vms/REGISTRATION-GEOGRAPHY.md#suba-boundary-and-satellite-label-verification--15-october-2026). Five geometry tests and the build pass.

Boundary accuracy follow-up: imported polygons are now **unverified references**, including during registration. Super Admins can upload, preview, and confirm a replacement GeoJSON with its source recorded. Two automatic assignments were marked for review; live polygon coordinates and account/fleet/code data were unchanged. **Mantalongon's exact current geographic outline remains unresolved** pending a reliable replacement map. See [boundary accuracy findings and fixes](../docs/archive/vms/BOUNDARY-ACCURACY-2026-10-15.md).

Regression audit, 15 October 2026: **397 backend tests passed (1,585 assertions)** and the frontend build passed. Browser/API checks and source review identified **19 findings**; REG-05, REG-11, REG-12, and REG-16 were subsequently fixed locally and browser-verified, leaving **15 open findings**. Baseline frontend lint reports **56 errors and 19 warnings**. See [the regression report](../docs/archive/vms/REGRESSION-REPORT-2026-10-15.md) for reproduction steps, evidence, severity, and coverage limits. Passing backend tests do not close the remaining findings or establish deployment acceptance.

- [ ] Demonstrate cloud image/file upload, authorized retrieval, and backup/recovery in the target deployment.
- [ ] Obtain adviser review of the role-alignment differences in sections 2 and 4. These notes record current behavior; they do not claim approval to amend the original baseline.
- [ ] Complete user acceptance with representative accounts and real operational records, including an empty barangay and multi-role staff.
- [ ] Confirm required generated report layouts and exports against the academic submission requirements.
- [ ] Maintain automated browser coverage for critical registration, verification and support flows. Backend feature tests exist in backend-api/tests/Feature; a passing frontend build alone is insufficient evidence of every workflow.

The original supplied document is not overwritten. Historical audit documents remain point-in-time records; their older Admin-confirmation, cannibalization-approval, and direct-ticket-creation descriptions should not be used as the current workflow contract.

Super Admin follow-up, 21 October 2026: all nine Super Admin audit findings and related retest edge cases were fixed locally. **433 backend tests (1,825 assertions), 98 API/browser scenarios, and 7 frontend tests passed; lint reports zero errors/warnings and the production build passes.** New barangays remain managed before onboarding, operational role changes protect assigned work, complete platform activity is searchable, and failures/retries are isolated by resource. Orphan recovery retains operational roles. Reference-only boundary onboarding remains permitted when a live outline exists and no replacement is pending. See [Super Admin fixes and verification](../docs/archive/vms/SUPERADMIN-FIXES-2026-10-21.md) for evidence, migration requirements, and coverage limits.

Later Super Admin recheck, 21 October 2026: the original 98 checks passed again, but expanded probes found **one new Low-severity issue: searching a concern by its displayed `CR-` reference returns no match**. Total API/browser result: 103 of 104 checks passed. Backend tests, frontend tests, lint and build still pass. See [the latest recheck](../docs/archive/vms/SUPERADMIN-RECHECK-2026-10-21.md).

Concern-reference fix, 21 October 2026: SA-R01 is fixed. **All 104 Super Admin API/browser scenarios, 433 backend tests (1,825 assertions), and 7 frontend tests passed; lint and build passed.** Reference search also passed lowercase, whitespace, and numeric-ID checks. See [the fix and complete recheck](../docs/archive/vms/SUPERADMIN-REFERENCE-SEARCH-FIX-2026-10-21.md).

Second Super Admin recheck, 21 October 2026: **111 API/browser scenarios, 433 backend tests (1,825 assertions), and 7 frontend tests passed.** Lint and build passed; no new findings or browser runtime exceptions were recorded. The additional control checks cover combined filters, pagination state, sidebar state, code cancellation/copy, and invalid boundary files. See [the latest recheck evidence](../docs/archive/vms/SUPERADMIN-SECOND-RECHECK-2026-10-21.md).

## 8. Required acceptance criteria from the 24 October audit

| ID | Priority / status | Requirement for acceptance |
|---|---|---|
| AUD-24-01 | High - open | Validate parent-record access whenever a managed-file URL is issued or renewed. Echoing a file reference in profile/free-text data must not produce a usable signed URL for an inaccessible file. |
| AUD-24-02 | High - open | Apply the same issue visibility policy to lists, direct details, nested tickets and attachments. A report hidden from an account must not be exposed by changing an ID in the URL. |
| AUD-24-03 | High - open | Separate platform-wide catalog authority from barangay administration, or isolate tenant-owned catalogs. Renaming a shared value must not silently rewrite another barangay's completed records or inherited vehicle criticality. |
| AUD-24-04 | High - open | Parts removal must invalidate readiness evidence recorded before that physical change. Require a fresh check without conflating availability, condition and readiness. |
| AUD-24-05 | Medium - open | Export untrusted CSV cells as spreadsheet-safe text, including formula-leading characters and control characters. Test vehicle, user, report, schedule, history and work-tracker exports. |
| AUD-24-06 | Implemented; acceptance retest required | Reject schedule completion dates later than the current accepted date. Invalid completion requests must not complete a schedule, create a ledger record or move its recurrence. |
| AUD-24-07 | Low - documentation aligned | Current registration/import eligibility is Admin or Custodian; the legacy per-account registration flag is not an access gate. Baseline role changes still require adviser acceptance. |
| AUD-24-08 | Low - documentation aligned | Current ticket cards show completion counts and stages; the ticket list also includes a progress bar and percentage. The old card progress ring is removed. Recording current behavior does not approve a new baseline requirement. |

The requirements PDF is an earlier export of this Markdown reference and was not regenerated in this update. The original supplied baseline and earlier generated DOCX remain historical artifacts; neither is evidence that the new audit findings are resolved.
