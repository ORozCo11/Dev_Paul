# Barangay VMS — Must-Haves and Requirement Alignment

Reviewed: 14 October 2026. Baseline: the supplied **VMS_MUST-HAVES.docx** (Vehicle Management System, Group 4). The baseline is retained as requirements evidence; the implementation notes below distinguish its wording from the current role and permission model.

Status key: **Implemented** means an identifiable code path exists; it does not claim deployment acceptance. **Verify in deployment** means credentials, storage, or an end-to-end acceptance check is still needed. **Role alignment** means the capability exists but the responsible role differs from the supplied baseline and should be reviewed with the adviser.

## 1. Platform requirements from the supplied document

| Baseline requirement | Status | Current implementation / evidence |
|---|---|---|
| Web-based desktop platform | Implemented | React/Vite frontend and Laravel API; responsive layouts also support smaller screens. |
| Admin, Custodian, and Maintenance Personnel access | Implemented | Central abilities in backend-api/config/permissions.php, backend role/ownership guards, frontend module gates. Multi-role accounts are supported. |
| Organized, updated vehicle records | Implemented | FleetController vehicle endpoints, vehicle catalog and individual profiles, land/water type fields, photos and locations. |
| Maintenance records and schedules | Implemented | FleetController record/schedule endpoints; ticket repairs, recurrence, approval and scheduled-to-ticket processing. |
| Dashboard summaries of status and activities | Implemented | Distinct Admin decisions, Custodian inspection/verification, and personal Maintenance work dashboards. No invented trends or weather data. |
| Secure cloud storage, backup and retrieval of images/files | Verify in deployment | Supabase/S3-compatible storage integration and authorized upload/download flows exist. Successful uploads, retrieval, retention and recovery depend on deployment credentials and storage configuration; backup/recovery has not been demonstrated by this UI change. |

## 2. Administrator requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| Manage all vehicle records | Implemented | Register, edit and archive barangay-scoped vehicles. Custodians can register vehicles under the current permission map. |
| Manage vehicle categories/types | Implemented | Vehicle-type catalog and land/water domains. |
| Update vehicle locations | Implemented | Vehicle locations and hub management with map views; occupied hubs cannot be deleted. |
| Monitor conditions needing inspection/repair | Implemented | Vehicle condition/readiness views and fleet-condition analytics; recording physical condition checks is Custodian-only. |
| Manage vehicle issue reports | Implemented | Review reports and linked tickets; dismiss obsolete reports with a reason. Custodians propose tickets. |
| Manage maintenance records | Implemented | Maintenance record oversight and repair-ticket history; ownership and verification guards still apply. |
| Schedule maintenance | Role alignment | Custodian books a schedule; Admin approves/declines and reassigns personnel. Admin does not originate a booking under the current permission map. |
| View vehicle history | Implemented | Automatic per-vehicle event timeline. |
| Generate vehicle, issue, maintenance and history reports | Implemented | Reports module plus relevant CSV/print exports. Validate required report formats with representative data for acceptance. |
| Monitor logs and user activities | Implemented | Barangay-scoped activity log and platform-only Super Admin log. |
| Dashboard summaries and recent updates | Implemented | Four primary KPIs, decisions queue, ticket workload, fleet condition and concise coverage warnings. Details are linked from the queue. |
| Create user accounts | Implemented | Admin user.create permission and user-management flow; public signup additionally requires registration code and approval. |

## 3. Custodian requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| View vehicle details and availability | Implemented | Vehicle catalog and profiles; availability, physical condition and verified readiness remain separate. |
| Search/filter vehicles | Implemented | Search, filters, table/card views and column controls. |
| Report vehicle issues | Implemented | Issue reporting with category, severity, description and linked proposal flow. |
| Upload issue photos | Verify in deployment | Issue-photo inputs/storage integration exist. Validate upload and authorized retrieval against configured cloud storage. |
| Monitor submitted reports/status updates | Implemented | Report details, associated tickets and notifications. |
| View ongoing/completed maintenance | Implemented | Maintenance ledger, ticket details, repair logs and verification history. |
| Dashboard of available vehicles, issues and maintenance | Implemented with role focus | Verification, readiness and unresolved issue KPIs; one tasks queue, one condition chart and readiness by vehicle type. Availability is accessible from Vehicles. |

Additional current responsibilities: propose maintenance tickets, record condition/readiness checks, book schedules, and independently verify completed repairs. Successful Custodian approval closes the ticket; Admin does not verify a repair.

## 4. Maintenance Personnel requirements

| Supplied requirement | Status | Current behavior |
|---|---|---|
| View reported vehicle issues | Implemented with scope | Own technical issue reports and authorized assigned tickets; unrelated staff workload is excluded. |
| Add maintenance records | Implemented with scope | Record repair work on assigned sub-issues and use permitted maintenance-record actions. |
| Update repair progress/completion | Implemented with scope | Assigned work logs and submission; a mechanic cannot independently verify or close their own repair. |
| Manage maintenance schedules | Role alignment | View and perform assigned scheduled work. Booking/editing is Custodian-owned; approval/reassignment is Admin-owned. |
| Add repair actions and remarks | Implemented | Repair log, parts, dates, evidence, donor-part records and supporting notes. |
| Monitor upcoming maintenance | Implemented | Personal upcoming/overdue schedule KPIs and assigned-work queue. |
| View maintenance history | Implemented | Work tracker, completed repair history and permitted maintenance records. |
| Dashboard of issues, records and vehicles needing attention | Implemented with personal scope | Three personal KPIs, assigned work, workload by actual ticket status and recent completed work. Fleet-wide analytics remain in authorized fleet modules. |

## 5. Current workflow and safeguards

- [x] Ticket proposals enter Pending Approval. Custodian proposes; Admin reviews/assigns; Maintenance Personnel repairs; assigned Custodian verifies and closes, or returns the work with a reason.
- [x] Self-verification is blocked server-side, including multi-role accounts. Reassign an independent Custodian when needed; there is no Admin verification fallback.
- [x] Cannibalized repairs record donor vehicles and parts and generate donor follow-up issues. The current flow does not require the retired Admin cannibalization approval step.
- [x] External-shop handling and verification duties are separated.
- [x] Schedule bookings require approval; recurrence and schedule-to-ticket processing remain intact.
- [x] Fleet data is scoped by barangay. Super Admin sees platform/account data rather than fleet operations.
- [x] Last-Admin and open-work deactivation guards remain enforced.
- [x] Impersonation uses the existing authorized candidate list and reason requirement; production tokens expire after 30 minutes and writes are restricted server-side. Local development has explicit expiry/write exceptions.
- [x] The account menu identifies the current user and role and offers End Impersonation during support sessions. Ending calls the existing logout endpoint for token revocation and audit logging before restoring the original session.
- [x] Registration retains first-Administrator versus ordinary staff approval routing, registration-code checks, and inactive-until-approved behavior.

## 6. UI requirements delivered in this revision

- [x] Three focused fleet dashboards with real data, scoped assignments, task links and useful empty states.
- [x] Super Admin overview with four platform KPIs, an administrative queue and one account-status chart.
- [x] Flat Super Admin navigation and a dedicated Barangay Management screen; existing recovery, boundary review, registration codes and account controls remain accessible.
- [x] Province → City/Municipality → Barangay → Staff impersonation cascade, using barangay IDs to distinguish duplicate names.
- [x] Search/filter improvements for users, barangays, concerns and platform activity.
- [x] Three-step registration form, wider desktop layout and readable introduction.
- [x] Cleaner ticket-creation hierarchy and compact schedule summaries with full details under View.
- [x] Shared eight-tooth blue/white SVG brand gear, transparent background, contrasting center ring, stationary wordmark and existing rotation timing. Reduced-motion preference is respected.

## 7. Acceptance checks and remaining work

Registration geography, 15 October 2026: the cascading selectors now use **42,046 preloaded PSGC barangay references**, including Cebu City's **80 barangays**. Free-text barangay creation is rejected server-side; selected city/parent relationships are validated. Boundaries are resolved by PSGC code, with missing matches shown as unavailable instead of substituting another barangay. **403 backend tests passed (1,622 assertions)** after this change. See [registration geography](REGISTRATION-GEOGRAPHY.md) for source dates, boundary coverage, deployment steps, and evidence.

Boundary correction, 15 October 2026: Suba (Pob.), Bantayan now uses a detailed 1,281-vertex PSA/NAMRIA reference instead of the coarse triangle. Existing automatic references and new registrations share the correction; map rendering and location checks preserve holes and islands. **406 backend tests passed (1,649 assertions)** plus three geometry tests. See [registration geography](REGISTRATION-GEOGRAPHY.md#boundary-detail-correction--15-october-2026).

Suba verification follow-up: the government-hosted PSA/GeoRisk polygon agrees with the imported outline; the northern screenshot vicinity lies in Binaobao in that reference. Provider place labels are now optional, and the app labels the selected reference polygon internally. This verifies reference consistency, not current legal boundaries. See [comparison and evidence](REGISTRATION-GEOGRAPHY.md#suba-boundary-and-satellite-label-verification--15-october-2026). Five geometry tests and the build pass.

Boundary accuracy follow-up: imported polygons are now **unverified references**, including during registration. Super Admins can upload, preview, and confirm a replacement GeoJSON with its source recorded. Two automatic assignments were marked for review; live polygon coordinates and account/fleet/code data were unchanged. **Mantalongon's exact current geographic outline remains unresolved** pending a reliable replacement map. See [boundary accuracy findings and fixes](BOUNDARY-ACCURACY-2026-10-15.md).

Regression audit, 15 October 2026: **397 backend tests passed (1,585 assertions)** and the frontend build passed. Browser/API checks and source review identified **19 findings**; REG-05, REG-11, REG-12, and REG-16 were subsequently fixed locally and browser-verified, leaving **15 open findings**. Baseline frontend lint reports **56 errors and 19 warnings**. See [the regression report](REGRESSION-REPORT-2026-10-15.md) for reproduction steps, evidence, severity, and coverage limits. Passing backend tests do not close the remaining findings or establish deployment acceptance.

- [ ] Demonstrate cloud image/file upload, authorized retrieval, and backup/recovery in the target deployment.
- [ ] Obtain adviser review of the role-alignment differences in sections 2 and 4. These notes record current behavior; they do not claim approval to amend the original baseline.
- [ ] Complete user acceptance with representative accounts and real operational records, including an empty barangay and multi-role staff.
- [ ] Confirm required generated report layouts and exports against the academic submission requirements.
- [ ] Maintain automated browser coverage for critical registration, verification and support flows. Backend feature tests exist in backend-api/tests/Feature; a passing frontend build alone is insufficient evidence of every workflow.

The original supplied document is not overwritten. Historical audit documents remain point-in-time records; their older Admin-confirmation, cannibalization-approval, and direct-ticket-creation descriptions should not be used as the current workflow contract.
