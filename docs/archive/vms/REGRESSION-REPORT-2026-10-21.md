# VMS regression audit — 21 October 2026

**Fix follow-up:** all 22 findings below now have local fixes and successful regression checks. See [the fix report](REGRESSION-FIXES-2026-10-21.md) for current status, evidence, and storage deployment requirements. The remainder of this document preserves the original audit baseline.

**Super Admin follow-up:** the later focused audit's nine findings and related edge cases were fixed and retested. See [Super Admin fixes and final verification](SUPERADMIN-FIXES-2026-10-21.md).

**Latest Super Admin recheck:** 111 API/browser scenarios passed with no new findings; all 433 backend tests also passed. See [the second recheck](SUPERADMIN-SECOND-RECHECK-2026-10-21.md).

**Original result, before fixes: regression sign-off fails.** This audit recorded **22 open findings: 4 High, 13 Medium, 5 Low**. These include functional bugs, interface discrepancies, documentation discrepancies, and tooling/performance findings; they are not all runtime exceptions.

The backend passed **415 tests / 1,730 assertions**, the production frontend build passed, and **6 geometry tests passed**. Frontend lint failed with **59 errors / 18 warnings**. Browser/API exploration recorded **321 observations**, including expected denials, diagnostic probes, and repeated layout samples. This number is not a count of independent passing acceptance tests.

Revision: `515bc69`, with the two unrelated untracked files present before testing left alone. No application fixes were made. The findings below describe the current checkout, not just the previous audit.

## Environment and isolation

- Windows; PHP 8.5.7; Node 26.7.0; Laravel API; React/Vite frontend; headless Chromium through Playwright.
- Backend feature tests used the configured SQLite `:memory:` database.
- Browser/API mutations used a separate SQLite snapshot in `%TEMP%/vms-regression-20261021`, created with SQLite's read-only backup API. New QA barangays, accounts, vehicles, reports, tickets, schedules, and files were the mutation targets. The working application's database was not a mutation target.
- Isolated API: `http://127.0.0.1:8021`; isolated frontend: `http://127.0.0.1:5191`. Mail used the array driver; queues ran synchronously; uploads used a separate local disk. No real email or cloud upload was attempted.
- Browser widths: 390, 1024, 1440, and 1920 px, depending on the check. The calendar rollover check used `Asia/Manila` and a fixed browser clock. Mobile failures were rechecked after 1.2 seconds to exclude transient animation overflow.
- Current workflow reference: [Must-Haves.md](../../../VMS%20Docs/Must-Haves.md), current permissions, and the supported whole-ticket workflow. The README contains older workflow descriptions; see REG-27.

## Finding index

Existing IDs are retained to make comparison with the earlier audit straightforward. REG-20 is a historical boundary-data concern, discussed separately below.

| ID | Severity | Finding | Classification |
|---|---|---|---|
| REG-01 | High | Logout clears the browser session but leaves its server token valid | Functional/security |
| REG-02 | High | A Custodian creates Admin-only catalog entries indirectly through scheduling | Authorization consistency |
| REG-03 | High | Switching completion to external shop clears cost, notes, and attachment | Data loss |
| REG-04 | Medium | Direct schedule-detail links render the dashboard | Navigation |
| REG-06 | Medium | Completion defaults to yesterday during Philippine early-morning hours | Calendar/date |
| REG-07 | Medium | Receipt picker accepts GIF files that submission rejects | Validation mismatch |
| REG-08 | Medium | Custodian is offered catalog creation that returns 403 | Permission/UI mismatch |
| REG-09 | Medium | Deleting a document leaves its stored file retrievable | Storage lifecycle |
| REG-10 | High | Public file URLs conflict with the authorized-retrieval requirement | Architecture/requirement discrepancy |
| REG-13 | Medium | Anonymous API request without JSON Accept produces 500 | Error handling |
| REG-14 | Medium | Frontend lint fails with 59 errors and 18 warnings | Tooling/code quality |
| REG-15 | Low | Public entry points eagerly load a large JavaScript bundle | Performance risk, unbenchmarked |
| REG-17 | Low | Privacy and Terms nest lists inside paragraphs | Invalid markup |
| REG-18 | Low | Policy-card logos use the older gear component | Visual consistency |
| REG-19 | Low | Privacy text describes local-only storage despite supported cloud storage | Documentation |
| REG-21 | Medium | Failed Province loading leaves registration stuck without error or retry | Registration/recovery |
| REG-22 | Medium | Admin Vehicle Location overflows at 390 px | Responsive layout |
| REG-23 | Medium | Admin Users registration-code controls overflow at 390 px | Responsive layout |
| REG-24 | Medium | Custodian Maintenance Schedule overflows at 390 px | Responsive layout |
| REG-25 | Medium | Custodian Vehicle History toolbar overflows at 390 px | Responsive layout |
| REG-26 | Medium | “Allow vehicle registration” checkbox does not control registration | Ineffective Admin control |
| REG-27 | Low | Support FAQ and README describe obsolete ticket responsibilities | Workflow guidance |

## Confirmed issues and reproduction steps

### REG-01 — Logout does not revoke the token

1. Sign in normally and retain the issued token in the test harness.
2. Account menu → Logout.
3. Replay `GET /api/user` with the retained bearer token.

Expected: logout revokes the token and replay returns 401. Actual: logout itself returns **401**, has **no Authorization header**, and replay returns **200**, although browser storage clears. The four role logout samples also returned 401/200.

`AuthContext.jsx` removes both stored tokens before calling Axios; the interceptor reads those stores when constructing the logout request. The rejected request is swallowed. Send the captured token or revoke before clearing it, retaining reliable local cleanup on failure.

Evidence: [logout replay](../../../logs/regression-2026-10-21-logout.json), [four-role samples](../../../logs/regression-2026-10-21-corners.json). Both browser storage modes should be covered when fixing this; this audit did not separately repeat the sessionStorage-only path.

### REG-02 — Operational submission bypasses catalog creation restrictions

1. As Custodian, POST a new name to `/maintenance-types`: **403**.
2. Submit a schedule using that nonexistent `maintenance_type`: **201**.
3. Read the maintenance-type catalog as Admin: the new entry exists.

Expected: the same creation authorization applies to direct and indirect catalog writes. Actual: schedule submission inserts the catalog entry despite the direct denial. `MaintenanceType::resolve()` creates unknown names. Resolve existing values separately from authorized creation; test every other resolver call before assuming this is confined to schedules.

Evidence: [API checks](../../../logs/regression-2026-10-21-api.json), including `catalogCreated: true`.

### REG-03 — External completion toggle destroys entered work

1. Assigned mechanic → Maintenance Schedule → Mark as Done.
2. Enter cost `1500`, notes, and a receipt.
3. Change “Sent to External Shop?” to Yes.

Expected: values remain while extra fields appear. Actual: cost and notes become empty, the receipt disappears, and the external selection itself becomes blank. The parent supplies a new `initialValues` object during rerender and SmartForm resets its values. Stabilize the form identity and preserve edits/files when conditional fields change.

Evidence: [state values](../../../logs/regression-2026-10-21-cabinet.json), [screenshot](../../../logs/regression-2026-10-21-completion-reset.png).

### REG-04 — Schedule bookmarks and refresh show the dashboard

Sign in as Maintenance Personnel and directly open `/maintenance/schedules/{schedule_id}`. Expected: schedule details. Actual: the page title says **Maintenance Schedule Details**, while the body says **Maintenance Dashboard** and no `.maintenance-schedule-detail` exists. A direct vehicle profile control check works.

Preserve the URL-selected module during workspace initialization and role/module reconciliation. Evidence: [deep links](../../../logs/regression-2026-10-21-race-and-links.json).

### REG-06 — Wrong completion date in the Philippine morning

At fixed browser time **22 October 2026, 01:30 Asia/Manila**, open Mark as Done. Expected: `2026-10-22`. Actual: `2026-10-21`. The default uses `new Date().toISOString().slice(0, 10)`, so it takes the UTC date. Format local calendar dates explicitly and check other date defaults/maxima.

Evidence: [calendar and receipt checks](../../../logs/regression-2026-10-21-ui-details.json).

### REG-07 — File selection accepts a receipt submission will reject

Select a valid GIF in Mark as Done. The cabinet accepts and lists it, but submission returns **422**: receipt must be jpg, jpeg, png, or pdf. The picker advertises `image/*,.pdf`. Align picker types, explanatory text, and backend validation.

Evidence: [receipt response](../../../logs/regression-2026-10-21-ui-details.json). PNG selection and pending-file removal passed.

### REG-08 — Forbidden catalog action is visible to Custodian

As Custodian, open a new external-shop proposal and its Maintenance Type selector. **Add New Maintenance Type** is visible, although the creation endpoint returns **403** for this role. Gate the Add action by `catalog.create` and provide the supported alternative.

Evidence: [rendered control](../../../logs/regression-2026-10-21-ui-details.json), [endpoint denial](../../../logs/regression-2026-10-21-api.json).

### REG-09 — Document deletion leaves the uploaded object

Upload a QA PNG vehicle document as Custodian; delete it as Admin; request its previous URL. Expected: deletion applies the intended file-retention/access policy. Actual: document deletion returns **200**, but its original URL still returns **200** and the bytes. The controller removes the database row without deleting the stored object. Define retention and implement object cleanup/revocation accordingly.

Evidence: [document lifecycle](../../../logs/regression-2026-10-21-extended-api.json). This was isolated local storage, not a production-bucket retention test.

### REG-10 — Known file URLs bypass API authorization

Upload a document, then fetch its URL without a bearer token. Actual: **200**, while another barangay correctly receives **404** from that vehicle's document-list endpoint. `UploadsImages.php` explicitly uploads with public visibility and returns public URLs; the storage configuration describes public delivery. This conflicts with Must-Haves' authorized-retrieval claim.

Determine which asset classes must be private and enforce authorized downloads or expiring links. This is an implementation/requirement discrepancy demonstrated with the application's local storage equivalent; the deployed Supabase bucket policy was not inspected.

Evidence: [anonymous retrieval](../../../logs/regression-2026-10-21-extended-api.json), `backend-api/app/Http/Controllers/Concerns/UploadsImages.php`.

### REG-13 — Plain anonymous API requests return an internal error

Call `GET /api/vehicles` anonymously with `Accept: text/html` or without JSON Accept. Actual: **500**, `Route [login] not defined`. With JSON Accept the response is correctly **401**. Configure API authentication failure handling before Laravel tries to generate the nonexistent login route.

Evidence: [non-JSON request](../../../logs/regression-2026-10-21-lifecycle.json), [JSON control](../../../logs/regression-2026-10-21-extended-api.json).

### REG-14 — Frontend lint fails

Run `npm.cmd run lint -- --format json --output-file ../logs/regression-2026-10-21-eslint.json` from `frontend-spa`. Result: **59 errors / 18 warnings across seven files**. Workspace.jsx accounts for 50 errors and all 18 warnings; remaining diagnostics affect Icon, LocationDensityMap, TextType, geocode, Register, and SuperAdminWorkspace. These diagnostics require individual triage; not every diagnostic is a reproduced runtime bug.

Evidence: [every diagnostic](../../../logs/regression-2026-10-21-eslint.json), [readable ledger](../../../logs/regression-2026-10-21-lint-ledger.md).

### REG-15 — Large eager bundle

The production build emits a main JS asset of **1,504.11 kB minified / 416.09 kB gzip** and a chunk-size warning. App.jsx eagerly imports fleet and Super Admin workspaces alongside public pages. Consider route-level lazy loading, then measure representative devices/networks. No measured page-speed threshold violation is claimed.

Evidence: [build output](../../../logs/regression-2026-10-21-build.txt).

### REG-17 — Invalid list nesting on policy pages

Visit Privacy and Terms and inspect DOM/console. Privacy has **2** `p ul` descendants; Terms has **4**. React reports that a list cannot descend from a paragraph. Their section helpers wrap mixed content in `<p>`. Use a block container and explicit paragraphs. Pages remained readable; no hydration crash was observed.

Evidence: [markup and console](../../../logs/regression-2026-10-21-corners.json).

### REG-18 — Policy-card branding is inconsistent

Privacy and Terms card headers still use `Icon name="gear"`, while their shared site headers use the current VMSLogo. Replace the older policy-card glyph with the shared brand component. This concerns the card mark, not the favicon.

Evidence: [rendered policy-card markers](../../../logs/regression-2026-10-21-corners.json), `Privacy.jsx` and `Terms.jsx`.

### REG-19 — Privacy storage statement is inaccurate

Privacy section 4 states that all data is in a secured local database managed by barangay IT. The implementation supports cloud database deployment and Supabase file uploads with a local fallback. Align the statement with actual deployed hosting, file access, and retention. This is a content discrepancy, not an assessment of legal compliance or the uninspected production configuration.

Evidence: `frontend-spa/src/views/Privacy.jsx:52`, `backend-api/config/filesystems.php`, `UploadsImages.php`.

### REG-21 — Province request failure has no recovery UI

1. Simulate failure of `GET /api/provinces` and load registration.
2. Complete step 1 and continue to the barangay step.

Expected: a visible loading failure and retry/recovery action. Actual: Province contains only **Select your province**, City/Barangay cannot proceed, there is no error, and no retry button exists. Register.jsx catches the failure by setting an empty array. Show the failure and support retry, as for downstream geography requests.

Evidence: [injected network failure](../../../logs/regression-2026-10-21-corners.json), observation `registration province API failure`.

### REG-22 — Mobile Vehicle Location controls overflow

Admin → Vehicle Location at **390 px**, wait for settling. Document width is **476 px**. The location-tab button and map action row extend beyond the viewport; action-row descendants extend as far as 660 px. Wrap/stack the tabs and actions and keep map controls within their container.

Evidence: [settled dimensions](../../../logs/regression-2026-10-21-mobile.json), [screenshot](../../../logs/regression-2026-10-21-mobile-admin-Vehicle-Location.png).

### REG-23 — Mobile Users registration-code panel overflows

Admin → Users at **390 px**. Document width is **448 px**; the registration-code/Regenerate row extends outside the card. This is separate from the intentionally scrollable user table. Stack the code and regeneration action at narrow widths.

Evidence: [settled dimensions](../../../logs/regression-2026-10-21-mobile.json), [screenshot](../../../logs/regression-2026-10-21-mobile-admin-Users.png).

### REG-24 — Mobile Custodian schedules overflow

Custodian → Maintenance Schedule at **390 px**, with QA schedules present. Document width is **501 px**. Contain the table's horizontal scrolling and reflow surrounding controls so the page itself remains viewport-width. The mechanic's schedule page did not retain overflow in its settled control check.

Evidence: [settled dimensions](../../../logs/regression-2026-10-21-mobile.json), [screenshot](../../../logs/regression-2026-10-21-mobile-custodian-Maintenance-Schedule.png).

### REG-25 — Mobile Vehicle History toolbar overflows

Custodian → Vehicle History at **390 px**. Document width is **497 px**; the search/export and adjacent toolbar controls exceed the viewport. Wrap or stack the toolbar and preserve access to its actions.

Evidence: [settled dimensions](../../../logs/regression-2026-10-21-mobile.json), [screenshot](../../../logs/regression-2026-10-21-mobile-custodian-Vehicle-History.png).

### REG-26 — Admin registration checkbox has no effect

1. Admin → Users → edit a Custodian; clear **Allow vehicle registration** and save.
2. Confirm the account's API field is `can_register_vehicles: false`.
3. As that Custodian, register a valid vehicle.

Expected from the control's label/help: disabling it prevents registration. Actual: registration returns **201**. The browser still shows the unchecked checkbox and help saying it lets the Custodian add vehicles. `User::canRegisterVehicles()` intentionally allows every Custodian and ignores the retained flag.

This is an ineffective/misleading administrative control. The current code calls registration a standard Custodian duty, so this audit does not assume that policy itself is unauthorized. Remove/reword the obsolete control and update the requirement, or restore enforcement if delegation remains the requirement. Must-Haves and controller/permission comments still describe delegation.

Evidence: [false flag plus successful creation](../../../logs/regression-2026-10-21-permission.json), [rendered unchecked control](../../../logs/regression-2026-10-21-registration-permission.png), [save and UI observations](../../../logs/regression-2026-10-21-followthrough.json).

### REG-27 — Public workflow help and README are obsolete

Open Support → **How do I report a vehicle issue?** The answer says an Admin can turn the report into a repair ticket. Actual supported flow: Custodian proposes; Admin approves/assigns. The README also describes Admin final confirmation and per-sub-issue verification as the normal ticket endpoint, while current whole-ticket Custodian approval closes the ticket.

Update the FAQ and README against the current workflow to prevent staff following unavailable actions. This does not imply that the separate maintenance-record confirmation endpoint is obsolete; it was exercised independently.

Evidence: [rendered FAQ](../../../logs/regression-2026-10-21-mobile.json), `frontend-spa/src/views/Support.jsx`, README sections “Roles & Access” and “The Maintenance Ticket Workflow,” [successful current lifecycle](../../../logs/regression-2026-10-21-lifecycle.json).

## Workflow and page coverage

| Area | Executed coverage and outcome |
|---|---|
| Registration | Empty-step validation; wrong staff code; browser staff registration; first-Administrator registration; duplicate email denial; pending-Admin login denial; Super Admin approval; staff approval and login. Province response-race check passed. Failed initial Province loading is REG-21. |
| Public pages | Login, Register, About, Developers, Support, Privacy, Terms, anonymous protected-route redirect. No uncaught JS exception in the recorded smoke/corner runs. Policy console errors remain REG-17. |
| Four workspaces | Actual browser login for Admin, Custodian, Maintenance Personnel, Super Admin. All **31 visible sidebar modules** visited; desktop/mobile rendering checks. This is module-level coverage, not every control's acceptance. |
| Fleet lifecycle | Create hub/type, register/edit vehicle, duplicate-plate denial, occupied-hub deletion guard, readiness confirmation and validation, archive/restore, retirement/reason validation. |
| Internal repair | Issue → linked Custodian proposal → Admin mechanic assignment → repair log → failed-check approval denial → Custodian rejection → mechanic rework → independent approval → **Closed**. Premature and duplicate verification denied. Admin verification denied. |
| External repair | Browser proposal → Admin approval without mechanic → shop dispatch → return → premature verification denial → Admin logs shop work → Custodian approval → **Closed**. |
| Scheduled maintenance | Booking/approval; assigned-mechanic completion creates proof record and next occurrence; monthly next date **2026-11-21** from completion **2026-10-21**; Custodian record verification; duplicate verification denial; final Admin record confirmation using `confirmed: true`. |
| Overlapping work safety | Explicitly created an Active brake-repair ticket alongside independent scheduled work. Completing/verifying/confirming the scheduled record kept the vehicle **Under Maintenance / Needs Repair**, and usage returned **422**. The suspected premature-availability bug was disproved. |
| Roles/tenancy | Mechanic user-management and vehicle-edit denials; Super Admin fleet denial; foreign-barangay readiness/document-list 404; empty barangay fleet contains zero foreign vehicles; last-Admin deactivation guard. Wider ownership/multi-role cases covered by backend suites. |
| Documents | PNG upload, tenant/role guards, anonymous bytes, deletion and old-URL replay. Public/orphaned files remain REG-09/10. PNG/PDF cabinet selection and pending removal exercised; GIF rejection mismatch remains REG-07. |
| Reports | All **seven current catalog reports** generated in Chromium; report heading awaited for each response. Inventory CSV downloaded and inspected. Print button invoked the browser print hook; actual physical printing and multi-page output acceptance were not established. |
| Notifications/support | Own notification read/delete, foreign-user read denial, read-all. Empty concern denial; public concern submission → Super Admin list → resolve → reopen → delete QA concern. |
| Ticket form | Internal/external layouts at 1920, 1440, 1024, 390; dark-theme sample; both proposal submissions 201. No retained overflow in these form samples. |
| Backend regression suite | 415 tests include authorization/scoping, role model, impersonation, accounts, ticket handoffs, donor parts/cannibalization, external shop, schedules/recurrence, imports/custom fields, geography/boundary review, hubs, usage/readiness, dashboards, and notifications. See JUnit for every test. |

## Previous findings now passing / not established

- **REG-05:** delayed Province/City response test passed; the selected Agusan Del Norte province retained its own cities.
- **REG-11:** Vehicle History is last in the rendered Custodian navigation.
- **REG-12:** external/in-house repair sections stayed beside vehicle context on desktop and stacked at mobile widths; all eight layout samples passed.
- **REG-16:** current external-shop handoff guidance is the corrected implementation; no return of the original form-layout/handoff defect was observed.
- **REG-20:** exact Mantalongon boundary accuracy remains an existing unresolved data concern. This audit did not obtain an authoritative replacement or independently re-survey it. It is **not** counted as a newly reproduced finding. See [boundary accuracy report](BOUNDARY-ACCURACY-2026-10-15.md).
- Early 180 ms mobile samples flagged additional pages. After settling, Admin tickets, Custodian Dashboard, and mechanic schedules did **not** retain document overflow and are excluded from the finding count. Offscreen cells within intentionally scrollable tables alone were not counted as page overflow.
- The raw lifecycle log contains an expected-403 probe for an undelegated Custodian which returned 201. Source review and the explicit false-flag reproduction classify it as **REG-26**, not an unexplained authorization failure.
- A maintenance-record probe initially sent obsolete `confirmation_result` and correctly received 422. The correct `confirmed: true` request passed in the safety follow-up. This is a harness-contract correction, not another bug.
- The safety probe initially expected usage to succeed to test a suspected vulnerability. Its actual 422 demonstrates the safety guard working; that raw `pass: false` is **not** an application failure.
- The report harness initially read the prior preview before the next asynchronous response. The retained final report evidence waits for the requested heading; all seven matched. No stale-report bug is claimed.
- Custodian/mechanic development-only impersonation-candidate requests returned expected 403s. No production impersonation defect is inferred from them.

## Evidence and repeatability

- [Backend result](../../../logs/regression-2026-10-21-backend.txt), [JUnit XML](../../../logs/regression-2026-10-21-junit.xml), [production build](../../../logs/regression-2026-10-21-build.txt).
- [Main API run](../../../logs/regression-2026-10-21-api.json), [follow-up](../../../logs/regression-2026-10-21-api-followup.json), [extended API](../../../logs/regression-2026-10-21-extended-api.json), [lifecycle](../../../logs/regression-2026-10-21-lifecycle.json), [record/concern follow-through](../../../logs/regression-2026-10-21-followthrough.json), [overlapping-work safety](../../../logs/regression-2026-10-21-safety.json).
- [Browser smoke](../../../logs/regression-2026-10-21-browser.json), [public/mobile corner checks](../../../logs/regression-2026-10-21-corners.json), [settled mobile results](../../../logs/regression-2026-10-21-mobile.json), [ticket layouts](../../../logs/regression-2026-10-21-layout-verification.json), [report UI/export](../../../logs/regression-2026-10-21-final-probes.json), [inventory CSV](../../../logs/regression-2026-10-21-inventory.csv).
- Setup/router and original focused scripts are retained in `logs/regression-2026-10-21-harness/`; additional `regression-2026-10-21-*.cjs` scripts are in `logs/`. These are environment-specific audit harnesses, require the isolated snapshot/fixtures, and include deliberate failure probes. Do not point them at production or blindly rerun preparation over an existing audit snapshot. Test passwords and staff codes belong only to QA fixtures.

## Remaining acceptance work

This was a broad local regression audit. **It does not establish that every corner of the deployed application works.** Remaining coverage includes real Supabase credentials/policies, authorized cloud retrieval and retention, backup/restoration, PostgreSQL concurrency and production migrations, real scheduler/queue/email delivery, every form/filter/sort/import/export variant, fully visual multi-page printing, map download/geocoding failures, keyboard/screen-reader acceptance, additional browser engines, and real mobile/slow-network behavior. Existing backend tests cover portions of these areas, but do not replace browser/deployment acceptance.

Repair first: **REG-01, REG-02, REG-03**, then settle file-access/retention behavior in **REG-10/09**. Next address the ineffective registration control, broken schedule deep links, registration recovery, dates, and mobile controls. Preserve the reproductions as assertions when fixing each issue.
