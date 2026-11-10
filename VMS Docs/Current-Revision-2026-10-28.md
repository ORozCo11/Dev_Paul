# Current implementation - 28 October 2026

Reviewed against the working tree on `dev-paul` (after `20449a7`). Several of the changes below are not committed yet. This note **supersedes** [Current-Revision-2026-10-25.md](Current-Revision-2026-10-25.md) wherever the two differ; everything in the 25 October note that is not listed here still applies. The 24 October audit remains a point-in-time record.

## External-shop rule change (28 October, later the same day)

- The **Admin only approves** an external-shop proposal; the Admin no longer sends the vehicle out, records its return or handles rework. `external.handle` is now held by the **Custodian** in `backend-api/config/permissions.php`, and the API accepts it only from the ticket's own assigned Custodian.
- The ticket's Custodian logs the vehicle **sent to the external shop**, records its **return/receipt** and any repeat **rework** visit, then **tests** the vehicle and, if it works, **closes the ticket by verifying** (or returns it for rework). Verification still closes the ticket.
- The Custodian who handled the shop is **no longer barred from verifying**; only mechanics who did repair work (current and previous) cannot verify their own work. Rework notifications go to the ticket's Custodian.
- This **supersedes** earlier statements in these documents (and in the 24/25 October review notes) that the Admin dispatches/receives external-shop work and that the shop handler cannot verify.

## What changed since 25 October

### Platform and data

- **Database is MySQL 8** (`barangay_vms_db`), not SQLite. 47 tables, 74 foreign keys, 121 migrations. Files (vehicle photos, repair evidence, documents, registration ID/photo) stay in private **Supabase Storage**. Automated tests still run on an in-memory SQLite database.
- **One role per account.** The `users.roles` JSON column and `users.barangay_name` are gone. A new `user_roles` table holds the account's role (the API refuses more than one row: `roles` accepts at most one value on create and update). Accounts that previously held two roles keep both rows until an Admin edits them; the edit form opens on the first role. `users.role` remains the primary role used for routing.
- **Temporary password.** `users.must_change_password` (boolean). See "Account security" below.
- **Multiple donor vehicles.** `ticket_sub_issue_donor_parts.donor_vehicle_id` (nullable FK to `vehicles`) records which donor each part came from. Existing parts were backfilled to their repair's original donor (`ticket_sub_issues.source_vehicle_id`, which is kept as the first donor).
- Migrations added since 25 October: `2026_10_26_000001_create_recurrence_intervals_table`, `2026_10_26_000001_add_reply_emails_to_concern_reports`, `2026_10_26_000002_make_concern_description_optional`, `2026_10_27_000001_create_external_reasons_table`, `2026_10_27_000001_create_user_roles_table`, `2026_10_27_000002_add_registration_documents_to_users`, `2026_10_27_000002_drop_barangay_name_from_users`, `2026_10_28_000001_add_must_change_password_to_users`, `2026_10_28_000002_add_donor_vehicle_to_donor_parts`.

### Roles and access (source: `backend-api/config/permissions.php`)

| Change | Detail |
|---|---|
| **Only a Custodian reports vehicle issues** | `issue.create` is Custodian-only. Maintenance Personnel can no longer file an Issue Report (the API returns 403). Admin never could. Maintenance Personnel work from tickets, work orders and schedules; they have no Issue Reports page. |
| Custodian: Vehicle Types | New sidebar page. View, and **add** a type (`vehicle_type.create`). Edit and delete stay Admin-only. |
| Custodian: Vehicle Location | New sidebar page. View, **add and edit** hubs (`hub.create`, `hub.edit`, `vehicle.update_location`). Delete, hide and "Restore Hubs" stay Admin-only. The map has an **Add Hub** button; the "By Coordinates" button, the hint line and the on-map boundary label were removed. |
| Maintenance Personnel: View Vehicles | See every vehicle in their barangay (previously only vehicles tied to their own assignments). Barangay scope still applies. |
| Admin: users | Admin creates accounts with a temporary password (below) and chooses **one** role. |

### Account security

- **Add User** pre-fills a random 12-character temporary password (upper, lower and digits; look-alike characters excluded) with a **Regenerate** button and a show/hide eye. The Admin may type their own (8+ characters). There is no confirm field. The Admin never sets the password that finally protects the account.
- A user created this way has `must_change_password = true`. At first sign-in the SPA shows a **Set your own password** modal instead of the workspace. The API enforces it too: every request except `GET /api/user`, `POST /api/logout` and `PUT /api/profile/password` returns **403** with `code: password_change_required`. Impersonation sessions are exempt. The new password must differ from the current one. Changing it clears the flag.
- The Update User page offers Admins no password field. The update API still accepts a `password`, so this is a UI rule, not a server rule; `SystemWorkflowAuditTest::test_password_resets_revoke_old_sessions` exercises that path and is one of the failing tests listed below.
- Registration additionally requires a government **valid ID** and a **photo**, stored privately (`users.registration_id_path`, `registration_photo_path`) for the reviewer. A barangay's first Admin registers without a staff code; a Super Admin emails the code after confirming the appointment and the Admin enters it at sign-in (unclaimed codes expire after seven days). Later staff register with the barangay code and need their Admin's approval.

### Tickets and repairs

- **Propose-ticket form** (Custodian): after the repair method and vehicle are chosen, if the vehicle has open Issue Reports (Pending / Under Review) the form shows **only** a centered card asking "Is this repair for one of them?" Choosing a report links it; "None of these - this is something new" continues without a link. The rest of the form (sub-issues, details, Submit) appears after the answer, with a small "Change" link to revisit it. A report already held by another open ticket (directly or through a sub-issue) is greyed out with "Already linked to Ticket #N" (`GET /vehicles/{id}/open-issues` returns `reserved_by_ticket`).
- **Ticket titles** no longer show sub-issues. The stored title still carries the first problem (duplicate and recurrence checks match on it); screens use the API's `display_title`, e.g. `MT-0002 - BARKO`.
- **Repair Type column** (Maintenance Schedule / In-House Repair / External Shop / In-House + External) beside Title in the main ticket tables, My Work Orders, verification and Tickets Ready to Close. The API now eager-loads the originating schedule.
- **Admin ticket list** opens with **Proposals + Active** ticked; Declined, Closed, Cancelled and For Verification are one tick away. The "Manage tickets removed from my list" link was removed.
- Closed/Cancelled tickets show "Closed same day / after N days" in the age badge, never "Opened today". The green "Approved" chip on sub-issue cards was removed.
- **Record Repair Work** (mechanic): no Labor/Service Cost field (cost is parts only); Submit and Cancel sit at the bottom left; the two methods read **Standard Repair** ("your own mechanic, using bought or on-hand parts") and **Uses Parts From Another Vehicle** ("cannibalization") and behave as radio buttons. The file-rename prompt is an in-app dialog. Submitted attachments show on the ticket: a **View attachments (N)** chip on each sub-issue and an "Attached Files" section in its details. The action icon in My Work Orders is a repair icon.
- **Several donor vehicles per cannibalized repair.** Cannibalization Management shows one block per donor with its own parts table and Add Part. **Add Donor Vehicle** opens a picker in which vehicles already used are greyed out ("already selected"); the repaired vehicle and retired vehicles are excluded. A donor is saved with its first part. On submit, **one Issue Report per donor** (listing only that donor's parts) is filed and each donor is flagged Needs Repair; Admins get one notification naming all donors. The ticket shows "Donors: A, B". The legacy Admin approval path for cannibalized repairs still handles only the first donor (it is no longer used by the normal flow).
- Validation messages in the ticket forms appear as a centered overlay and no longer move the form sections.

### Vehicles and condition

- A new vehicle starts **Available / Good**, and Condition Monitoring shows it as **Good** (not "Not Checked") until a check says otherwise.
- As soon as a ticket exists for a vehicle (a proposal or an open issue report sets Needs Repair; approval also sets Under Maintenance) Condition Monitoring shows it as **Needs Repair**. There is no manual **Add** button (header or per row) in Condition Monitoring any more; existing checks can still be edited/deleted per their permissions.
- The Dashboard "vehicles by condition" chart quotes `condition` (a reserved word in MySQL) so it loads on MySQL.
- Add Vehicle, step 3: vehicle photo and file cabinet side by side, then the Current Location dropdown, then the map.
- The Add Location page no longer shows the "Unverified reference outline" box or the map's barangay label.

### Navigation

The sidebar shows small group labels:

| Role | Sidebar |
|---|---|
| Admin | Dashboard; **Operations** (Maintenance Tickets, Maintenance Schedule, Maintenance Records, Condition Monitoring); **Fleet & Assets** (Vehicle Management, Vehicle Types, Vehicle Location); **Administration** (Users, Reports, Activity Log) |
| Custodian | Dashboard; **Fleet** (View Vehicles, Vehicle Types, Vehicle Location); **Monitoring** (Condition Monitoring, Vehicle History); **Repairs** (Report Vehicle Issue, My Tasks); **Maintenance** (Maintenance Schedule, Maintenance Records) |
| Maintenance Personnel | Dashboard; **Fleet** (View Vehicles); **Repairs** (My Work Orders); **Maintenance** (Maintenance Schedule, Maintenance Records) |
| Super Admin | Separate platform portal (unchanged) |

Reports uses a bar-chart icon and Activity Log a pulse-line icon. Every text field, dropdown and textarea shares one focus style (solid blue border and soft ring).

### Email

Account emails (welcome, staff registration code, account active, registration update/rejection, registration expired, Super Admin messages and concern replies) go through **Gmail SMTP** (`MAIL_MAILER=smtp`, `smtp.gmail.com:587`, sender `superadming4@gmail.com`; credentials only in the git-ignored `.env`). Every message uses one layout: blue header band with the gear logo (embedded inline, so it shows without "load images") and a footer reading "(c) YEAR Barangay Vehicle Management System. All rights reserved." Sign-in/Register buttons stay hidden while `FRONTEND_URL` is localhost.

## Statements in older documents that this note replaces

- "Maintenance Personnel can file an Issue Report / recommend a ticket from their own report" - no longer true; only Custodians report. (`issue.recommend_ticket` still exists in `permissions.php` and the API, but Maintenance Personnel have no Issue Reports page, so the feature has no entry point.)
- "A user can hold more than one role" / multi-role "dual-hat" accounts - one role per account now; the self-verification guards remain in place for older accounts that still carry two roles.
- "Database: SQLite for local development; PostgreSQL in deployment" - MySQL.
- "Nothing forces the user to change a password on next login" - Admin-created accounts are forced to.
- "Maintenance Personnel only see vehicles tied to their own assigned work."
- "One donor vehicle per cannibalized repair" and "one issue report on the donor."
- "Admin ticket list opens with every status ticked."
- Sidebar notes saying Maintenance Personnel's "Vehicle Issues" and "Report Technical Issue" are one combined module (removed) and flat sidebars without group labels.

## Verification and known gaps

- Targeted backend tests added or updated for this revision: `TemporaryPasswordTest`, `OpenIssuesReservedTest`, the multi-donor case in `DonorPartsCrudTest`, the Custodian-only issue-reporting tests in `PhaseB3DataScopingTest` and `SpecPhase1RolesTest`, and the single-role rule in `SuperAdminRegressionFixesTest`. These passed when run individually. Frontend production build passes; ESLint reports one older `set-state-in-effect` error in `Workspace.jsx`.
- **The full backend suite has not been rerun.** Known failing or stale tests at this revision: registration tests that do not send the now-required valid ID and photo (`StaffCodeActivationTest`, `AuthControllerTest` first-Admin case, `PhaseB3DataScopingTest` ordinary-staff registration), two shared-catalog permission tests (`CatalogControllerTest`, `RecurrenceIntervalCatalogTest`) and `SystemWorkflowAuditTest::test_password_resets_revoke_old_sessions`. They need updating; they do not describe the intended behaviour.
- `setup-demo.ps1` and `php artisan demo:sync` still assume SQLite and `demo:sync` refuses to run on MySQL. On MySQL use `php artisan migrate:fresh --seed` (see [Sample-Data.md](Sample-Data.md)).
- The AUD-24-01 to AUD-24-06 acceptance retests from the 24 October audit are unchanged by this revision.
- Exported PDFs, slide decks and architecture/ERD/use-case images outside this folder are snapshots; see the per-file notes in [README.md](README.md).


## Vehicle condition is only Good or Needs Repair (28 October, later)

The automatic **Needs Inspection** condition is removed. Condition checks only ever offered Good and Needs Repair, yet filing an issue report, proposing a ticket or taking a part from a donor vehicle set a vehicle to Needs Inspection, so the Vehicle Management list showed a state nobody could record. Those events now set **Needs Repair**, and existing vehicles in that state were converted by migration `2026_10_28_000003`. Older condition-check rows are history and are unchanged.
