# VMS regression report — 15 October 2026

## Result

**Additional finding REG-20 — Medium, partially addressed:** imported barangay reference polygons were automatically marked verified and used to reject location pins, despite unresolved geographic accuracy. Registration, status handling, replacement review/upload, and pin handling are fixed locally; the exact current Mantalongon polygon remains unresolved. This finding is additional to the original 19-item audit baseline below. See [boundary accuracy follow-up](BOUNDARY-ACCURACY-2026-10-15.md) for evidence, tests, and the remaining data requirement.

**Registration follow-up, 15 October 2026:** REG-05 is now fixed locally and browser-verified. Both city and barangay requests ignore stale responses, registration uses preloaded coded barangays, and the API validates parent relationships. The expanded suite passed **403 tests / 1,622 assertions**. See [registration geography](REGISTRATION-GEOGRAPHY.md) and its evidence for coverage, reference dates, and remaining boundary gaps. Together with the layout fixes below, **15 findings remain open**.

**Layout follow-up, 15 October 2026:** REG-11, REG-12, and REG-16 are fixed locally for the supported ticket proposal UI. Vehicle History renders last; issue details and repair fields share a right-hand column with an 18px gap; external-shop guidance describes the correct handoff. Both repair methods passed browser layout checks at 1920, 1440, 1024, and 390px, dark-theme rendering, and successful proposal submissions (201). See [verification results](../../../logs/ticket-layout-fix-verification.json). The original audit results below remain a record of the tested baseline.

**397 backend tests passed with 1,585 assertions. The frontend production build passed. This does not constitute a clean regression sign-off:** browser/API checks reproduced the findings below. Frontend lint failed with **56 errors and 19 warnings**.

Tested revision: `a1456fe`, branch `dev-paul`, including the uncommitted public-header alignment, Custodian navigation, and ticket-form CSS changes present at the start of this audit. No application fixes were made during the audit.

The finding register contains **19 items: 4 High, 8 Medium, 7 Low**. It includes confirmed functional bugs, UI discrepancies, storage/documentation discrepancies, and tooling/performance findings. An item's evidence and classification distinguish those categories.

## Environment and evidence

- Windows; PHP 8.5.7; Node 26.7.0; Chromium through Playwright.
- PHPUnit used its configured SQLite `:memory:` database.
- Browser/API tests used a separate SQLite snapshot under the Windows temporary directory, with new QA barangays, accounts, a vehicle, and schedules. The snapshot was created using SQLite's read-only backup API. Working application records were not used as mutation targets.
- An isolated API ran on port 8015 and an isolated Vite frontend on port 5185. Test CORS configuration allowed that origin. Test uploads used an isolated local disk in place of Supabase; email and queue configuration prevented real outgoing mail.
- Browser checks included desktop 1440px and mobile 390px widths. A frozen clock with `Asia/Manila` tested the date rollover.
- **147 recorded browser/API observations**, including negative cases and exploratory requests. These are not 147 independent passing acceptance tests. Some exploratory requests used old contracts or missing fields; the triage section below identifies them.

Evidence files are in [../logs](../../../logs). Principal outputs:

| Evidence | File |
|---|---|
| Backend test result | [Backend summary](../../../logs/regression-2026-10-15-backend.txt), [JUnit XML](../../../logs/regression-2026-10-15-junit.xml) |
| Build output | [Build log](../../../logs/regression-2026-10-15-build.txt) |
| Every lint diagnostic | [ESLint JSON](../../../logs/regression-2026-10-15-eslint.json), [readable ledger](../../../logs/regression-2026-10-15-lint-ledger.md) |
| Public pages and four role workspaces | [Browser smoke results](../../../logs/regression-2026-10-15-browser.json) |
| Session revocation | [Logout reproduction](../../../logs/regression-2026-10-15-logout.json) |
| Registration and geography race | [Registration](../../../logs/regression-2026-10-15-focused-browser.json), [race and deep links](../../../logs/regression-2026-10-15-race-and-links.json) |
| Upload form state | [Cabinet reproduction](../../../logs/regression-2026-10-15-cabinet.json), [date/file/CSS checks](../../../logs/regression-2026-10-15-ui-details.json) |
| Permissions and workflows | [API probes](../../../logs/regression-2026-10-15-api.json), [follow-up](../../../logs/regression-2026-10-15-api-followup.json), [extended API](../../../logs/regression-2026-10-15-extended-api.json), [contract checks](../../../logs/regression-2026-10-15-contract-checks.json), [external final steps](../../../logs/regression-2026-10-15-external-final.json) |
| Ticket submission and responsive checks | [External proposal UI](../../../logs/regression-2026-10-15-external-ui.json) |
| Policy-page console and markup | [Policy results and React warnings](../../../logs/regression-2026-10-15-policy-console.json) |

## Finding register

### REG-01 — High — Logout leaves the server token usable

**Confirmed in Chromium and by replaying the token.**

1. Sign in normally and retain the issued token in the test harness.
2. Open Account menu → Logout.
3. Inspect `POST /api/logout`, then call `GET /api/user` with the retained token.

Expected: logout revokes the token; replay returns 401. Actual: logout returns **401**, its request has **no Authorization header**, browser storage clears, and replay returns **200**.

Cause: `frontend-spa/src/context/AuthContext.jsx:13–20` clears storage before calling Axios. `src/api/axios.js` reads storage when preparing the request. The logout failure is swallowed.

Fix direction: send the captured token explicitly or revoke it before clearing storage, with reliable local cleanup on failure. Add a real-browser logout/replay test for both token-storage modes.

### REG-02 — High — Operational submissions bypass Admin-only catalog creation

**Confirmed through real bearer-token API requests.**

1. As a Custodian, POST a new name to `/api/maintenance-types`: **403**.
2. POST `/api/maintenance-schedules` with the same previously nonexistent name as `maintenance_type`: **201**.
3. Read `/api/maintenance-types` as Admin: the new global catalog item exists.

Expected: the Admin-only `catalog.create` permission also governs indirect catalog insertion. Actual: a Custodian can mint global maintenance types by creating a schedule.

Source: `backend-api/config/permissions.php` grants `catalog.create` only to Admin; `FleetController.php:2592` calls `MaintenanceType::resolve()`, whose `findOrCreateByName()` inserts unknown names. Similar resolution paths exist in issue reporting and ticket proposals; those additional paths need dedicated regression tests.

Fix direction: separate resolving existing values from creating catalog rows; implement an approved “Other” value/note path for staff. Perform authorization/scoping before any catalog write.

### REG-03 — High — Switching schedule completion to external shop loses entered data

**Confirmed in Chromium.**

1. As the assigned Maintenance Personnel, open a Scheduled row's “Mark as Done” dialog.
2. Enter cost `1500`, add repair notes, and select an attachment.
3. Change “Sent to External Shop?” to Yes.

Expected: all values remain and external-shop fields appear. Actual: **cost and notes become empty, the attachment disappears, and the external selection becomes blank**, although external-shop fields appear.

Cause: `Workspace.jsx:3384` constructs a new `initialValues` object on parent renders; changing external mode rerenders the parent. `SmartForm` resets values whenever that object's identity changes (`Workspace.jsx:5905`).

Evidence: [state result](../../../logs/regression-2026-10-15-cabinet.json), [screenshot](../../../logs/regression-completion-reset.png).

Fix direction: stabilize initial values or reset only when the record/form identity changes. Test switching modes after entering values and selecting a file.

### REG-04 — Medium — Direct schedule links display the dashboard

**Confirmed in Chromium after allowing the page to finish loading.**

1. Sign in as Maintenance Personnel with an assigned schedule.
2. Open or refresh `/maintenance/schedules/{schedule_id}` directly.

Expected: the schedule detail page. Actual: the breadcrumb says **Maintenance Schedule Details**, but the body is **Maintenance Dashboard** and `.maintenance-schedule-detail` is absent. A direct vehicle profile control check rendered its profile correctly.

Cause: URL-based `activeModule` initialization includes schedules (`Workspace.jsx:597`), but the `[modules]` effect resets the module to Dashboard (`Workspace.jsx:1082`). Schedule details are rendered only inside the schedules branch.

Fix direction: preserve URL-selected modules on mount and role changes; test refresh, bookmarks, and dashboard/notification links.

### REG-05 — Medium — Registration geography requests can overwrite newer selections

**Confirmed using delayed real API responses.**

1. On registration step 2, select Abra; delay that cities response.
2. Immediately select Agusan Del Norte, allowing its response to arrive first.
3. Let the delayed Abra response finish.

Expected: only Agusan Del Norte cities remain. Actual: the Province field shows **Agusan Del Norte**, while the City list contains **Bangued, Boliney, Bucay**, and the other Abra municipalities.

Source: `Register.jsx:121–131` applies city/barangay responses without cancellation or checking whether they belong to the current selection. The analogous barangay race is a source-level risk; only the province/city race was dynamically reproduced.

Evidence: [race data](../../../logs/regression-2026-10-15-race-and-links.json), [screenshot](../../../logs/regression-registration-race.png).

Fix direction: cancel stale requests or use request identities; preserve consistency between the selected province, city, and barangay. Add delayed-response browser tests.

### REG-06 — Medium — Completion date defaults to the previous Philippine calendar day

**Confirmed with a frozen browser clock and Philippine timezone.**

At the test instant `2026-10-16 01:30 Asia/Manila`, the completion dialog defaulted to **2026-10-15**, while the browser's Philippine calendar date was **2026-10-16**.

Cause: `Workspace.jsx:3384` uses `new Date().toISOString().slice(0, 10)`, which reads the UTC date. The default is wrong from midnight through 07:59 Philippine time. Other `toISOString()` date-default/max-date call sites also warrant review; they were not all exercised.

Fix direction: format calendar dates in the intended timezone and test midnight boundaries.

### REG-07 — Medium — Receipt picker accepts GIF files that completion rejects

**Confirmed through browser selection and the resulting API response.**

1. Select a valid GIF as the receipt in “Mark as Done”.
2. The file cabinet lists it as ready to upload.
3. Submit the dialog.

Actual: **422**, “The receipt field must be a file of type: jpg, jpeg, png, pdf.” The input advertises `image/*,.pdf` (`Workspace.jsx:3372`), admitting GIF and other image formats that the backend excludes.

Fix direction: align accepted extensions/MIME types and the displayed limits with backend validation. Keep server validation authoritative.

### REG-08 — Medium — Custodian is offered catalog creation that always fails

**Confirmed visible in Chromium; endpoint denial confirmed through API.**

1. As Custodian, open a new external-shop proposal.
2. Open Maintenance Type.
3. “Add New Maintenance Type” is available, although this role's creation request returns **403**.

Source: `CreatableSelect` in `Workspace.jsx` gates catalog edit/delete by role, but its Add controls are not gated by `catalog.create`. The schedule form also uses this component for maintenance types.

Fix direction: use ability-based Add controls and provide the supported “Other” flow. Include non-Admin dropdown behavior in browser tests.

### REG-09 — Medium — Deleting a document does not remove its stored bytes

**Confirmed with an isolated uploaded PNG.**

1. Upload a vehicle document as Custodian.
2. Delete it as Admin: the API returns **200**, “Document deleted.”
3. Request the original file URL: it still returns **200** and the original bytes.

Source: `FleetController.php:1909–1914` deletes the database row without deleting the object. This leaves orphaned storage and preserves access through old links. Actual production-bucket retention was not tested.

Fix direction: define retention semantics explicitly and delete/revoke stored objects when deletion is intended, using retryable cleanup for storage failures.

### REG-10 — High — File retrieval is public, conflicting with documented authorized retrieval

**Confirmed architecture/requirement discrepancy; isolated local retrieval reproduced.**

An uploaded vehicle document's URL returned **200 without a bearer token**, even though another barangay's document-list endpoint correctly returned **404**.

`UploadsImages.php:14–25` writes uploads with public visibility and returns plain public URLs. `config/filesystems.php` explicitly documents that the application relies on unauthenticated file URLs. `Must-Haves.md:16` describes authorized upload/download flows, and its acceptance checklist calls for authorized retrieval.

API list scoping therefore does not protect bytes at a known file URL. This is especially relevant to documents and repair evidence. Supabase's deployed bucket policy was not inspected; the observed runtime used the isolated local disk.

Fix direction: decide which asset categories must be private, then implement authorized downloads or short-lived signed links and align the documentation with the chosen behavior. Do not mark deployment acceptance complete based on this audit.

### REG-11 — Low — Vehicle History is still second in the Custodian sidebar

**Confirmed in Chromium; regression in the recent navigation change.**

Actual order: Dashboard → **Vehicle History** → View Vehicles → Condition Monitoring → Report Vehicle Issue → My Tasks → Maintenance Schedule → Maintenance Records.

Expected from the user's request: Vehicle History last. Condition Monitoring is correctly after View Vehicles.

Cause: the new History group has `section: null` (`Workspace.jsx:168`), as does Dashboard. `resolveModuleGroups()` merges groups sharing that key (`Workspace.jsx:253`), moving History into Dashboard's first group.

Fix direction: preserve distinct unsectioned positions or give the final group a stable unique identity. Test rendered order, rather than the source array alone.

### REG-12 — Low — External-shop details still span both columns

**Confirmed using browser computed CSS.**

The form has columns `340px 810px`, but the external-shop section computes to **`grid-column: 1 / -1`**, rather than remaining beside the vehicle context as intended by the latest redesign.

Cause: the early `.veh-card:not(.ticket-vehicle-section):not(.ticket-issue-section)` full-width selector in `TicketCreation.css:9` has greater class specificity than the later `:has(> .ticket-external-fields)` override.

Fix direction: assign explicit classes/grid areas instead of overlapping structural overrides. Desktop and mobile checks found no persistent horizontal overflow in this form.

### REG-13 — Medium — Unauthenticated API requests without JSON Accept return 500

**Confirmed through HTTP requests.**

- `GET /api/vehicles` without a token and without `Accept: application/json`: **500**, “Route [login] not defined.”
- The same request with JSON Accept: **401**, “Unauthenticated.”

Expected: a protected API endpoint consistently responds with an authentication failure, not an internal server error. JSON-only tests conceal this path. The browser's configured Axios client sends JSON Accept and is not affected by this particular reproduction.

Fix direction: configure unauthenticated API handling before Laravel attempts to generate a nonexistent named login route. Add a plain-HTTP negative test.

### REG-14 — Medium — Frontend lint fails with 56 errors and 19 warnings

**Confirmed by running ESLint across the frontend.** These include existing issues outside the recent UI changes.

| File | Errors | Warnings |
|---|---:|---:|
| `src/views/Workspace.jsx` | 50 | 19 |
| `src/components/Icon.jsx` | 2 | 0 |
| `src/components/LocationDensityMap.jsx` | 1 | 0 |
| `src/components/TextType.jsx` | 1 | 0 |
| `src/utils/geocode.js` | 2 | 0 |

Diagnostics include duplicate `search` keys in the icon map, 26 state-update-in-effect findings, 24 unused-variable findings, 17 hook dependency warnings, and render purity/ref findings. These are lint findings, not 56 independently reproduced runtime failures. The new FileCabinet component has no lint diagnostics.

Every diagnostic is logged with file, line, rule, and severity in the [lint ledger](../../../logs/regression-2026-10-15-lint-ledger.md); full messages are retained in the JSON output.

Fix direction: resolve diagnostics by affected workflow, starting with state resets and hook dependencies; do not blanket-disable the checks.

### REG-15 — Low — Build ships a large eagerly loaded JavaScript bundle

**Confirmed production-build warning and source inspection.** Main JavaScript is approximately **1,493.49 kB minified / 411.46 kB gzip**. `App.jsx` eagerly imports both workspace implementations and the public pages. Vite emits its chunk-size warning.

Impact: increased initial download/parse cost, including for registration and login. This audit did not measure low-end-device timings or a performance budget violation.

Fix direction: lazy-load workspace/public routes and measure representative device/network performance.

### REG-16 — Low — External repair hint describes an in-house mechanic handoff

**Confirmed in the rendered external proposal form.** Selecting “Sent to External Shop” still shows “Once an Admin approves and assigns a mechanic, it goes straight to repair.” External tickets are intentionally approved without an in-house mechanic, as the passing backend tests and API workflow confirm.

Source: the proposal form's `ticket-method-hint` uses the same nonempty-mode text for both methods.

Fix direction: use mode-specific copy explaining shop dispatch, return, repair evidence, and independent Custodian verification.

### REG-17 — Low — Policy pages nest lists inside paragraphs

**Confirmed in Chromium DOM and React console output.** `/privacy` contains two `p ul` descendants; `/terms` contains four. React emits invalid HTML nesting errors: lists cannot be descendants of paragraphs.

Source: `Privacy.jsx:101` and `Terms.jsx:125` wrap all section children in `<p>`, including the lists passed by their callers. The client-rendered pages remain readable in the tested browser; the console describes hydration risks, but this audit does not claim an observed hydration crash.

Fix direction: use a block container for mixed paragraph/list content, with explicit paragraphs around prose. Capture console errors as well as uncaught JavaScript exceptions in browser tests.

### REG-18 — Low — Privacy and Terms still use the older inline logo

**Confirmed in Chromium and source.** The shared page headers use the current `VMSLogo`, but the small logo inside each policy card uses `Icon name="gear"` (`Privacy.jsx:16`, `Terms.jsx:16`). That glyph is generated from circles and rounded rectangles, whereas the current logo uses the shared tapered-tooth path.

Fix direction: use `VMSLogo` consistently wherever the brand mark is displayed. This finding concerns the inline policy-card mark, not the already updated favicon.

### REG-19 — Low — Privacy text inaccurately describes local-only storage

**Confirmed source/documentation discrepancy.** Privacy section 4 says “All data is stored within a secured local database managed by the barangay's IT infrastructure.” The implementation supports Supabase/PostgreSQL deployment and sends image/file uploads through the Supabase disk, with a local fallback.

The policy therefore omits the configured cloud file-storage path and does not accurately describe all supported deployment storage. This audit did not inspect the actual production deployment or determine its selected database.

Fix direction: align the published policy with the real hosting/storage arrangement, including files and their access/retention rules, after resolving REG-10.

## Coverage and passing behavior

| Area | Evidence and scope |
|---|---|
| Public pages | Login, Register, About, Team, Support, Privacy, Terms rendered without browser JavaScript exceptions. An anonymous workspace link redirected to login. |
| Registration | Empty first-step validation, wrong staff code, successful browser staff submission, first-Admin API submission, pending-account login denial, Super Admin activation, duplicate email rejection, staff activation/login. |
| Role workspaces | Admin, Custodian, Maintenance Personnel, and Super Admin signed in through the browser. Available sidebar modules were visited; the smoke pass logged 45 observations with no `pageerror` events. This is rendering coverage, not full CRUD acceptance for every screen. |
| Tenant boundaries | A separate barangay had an empty fleet and received 404 for another barangay's vehicle readiness/document list. Broader cross-tenant cases passed in the backend scoping suite. |
| Issues and condition checks | Custodian issue creation, Admin dismissal with the current field contract, Custodian condition creation/editing, and blocked Admin condition editing. |
| Internal repair | Custodian proposal → Admin approval/assignment → mechanic repair log → whole-ticket Custodian verification → automatic Closed status. Mechanic verification denied. |
| External repair | Browser proposal submission → Admin approval without mechanic → dispatch → return → repair log → Custodian verification. Missing repair logging correctly prevented premature verification. |
| Scheduling | Custodian submission, Admin approval, assigned-mechanic completion, and recurring-schedule response containing `schedule`, `record`, and `next`. Admin completion denied. Browser data loss/deep-link/date findings remain open. |
| Uploads | PNG/PDF selection and pending-file removal in the cabinet; PNG document upload; role/tenant deletion/list guards. GIF mismatch and public/orphan-file findings remain open. |
| Reports | All six supported report types returned 200: vehicle, issue, maintenance, history, schedule, location. Custodian generation denied. Printed/exported layouts were not comprehensively visually verified. |
| Accounts and safeguards | Last-Admin deactivation guard, Super Admin fleet denial, mechanic user-management denial. Existing tests cover open-work deactivation, multi-role ownership, token expiry, and impersonation restrictions. |
| Other backend coverage | The passing suite includes vehicle validation/import/custom fields, hubs, boundaries, cannibalization/donor parts, notification links/pruning, dashboard badges, reliability/readiness/usage, and schedule conflicts/conversion. See JUnit XML for every executed test. |

## Triage of exploratory failures

The raw logs retain unsuccessful probes for traceability. These are **not additional application bugs**:

- Initial per-sub-issue verification requests hit a deliberately retired permission path. The supported `/tickets/{id}/verify` flow subsequently passed and closed the ticket.
- `/reports` without `report_type` correctly returned 422; all six typed report requests passed.
- Requests using `description`, `condition_status`, or `reason` instead of current `issue_description`, `condition_result`, and `dismiss_reason` correctly returned 422. Corrected requests passed.
- Attempting external verification immediately after shop return, before logging the repair, correctly returned 422. Logging the repair and then verifying passed.
- Custodian/mechanic calls to the development impersonation candidate endpoint returned expected 403s. The development frontend nevertheless makes those requests; production behavior was not tested here.
- The initial browser environment had an isolated-port CORS mismatch. The test router was corrected before the recorded successful browser run; this was a test setup issue.
- An early registration smoke sample reported overflow during animation. Settled follow-up checks at 1440px and 390px found no document-width overflow, so no persistent layout defect is asserted from that sample.

## Remaining acceptance work

This is a broad **local regression audit**, not proof that every deployed workflow works. Remaining checks:

- Real Supabase credentials, bucket access policy, upload/download URLs, retention, backup, and restoration.
- PostgreSQL execution and production migration/locking behavior; this run used SQLite.
- Full browser CRUD/negative-case coverage for every vehicle, import, hub map, boundary review, profile, notification, concern, multi-role, and impersonation flow. The corresponding backend suites passed where tests exist.
- Email delivery, deployment queue/scheduler execution, and real notification handoffs outside synchronous tests.
- Print/PDF/CSV content and pagination against submission requirements, map/geocoding services, and keyboard/screen-reader accessibility acceptance.
- Production build served through the deployment host, multiple browser engines, slow networks, concurrent edits, and representative mobile devices.

Recommended repair order: **REG-01, REG-02, REG-03**, resolve the private-file requirement/retention decisions in **REG-10/REG-09**, then address schedule links, registration races, and date handling. Re-run the backend suite and add browser reproductions for each fixed finding.
