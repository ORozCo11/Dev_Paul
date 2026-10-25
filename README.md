# Barangay Vehicle Management System (VMS)

A web-based system for managing a barangay's fleet of **emergency response vehicles** (Ambulance, Fire Truck, Rescue Boat, and other units). The VMS keeps track of whether each vehicle is **ready to respond right now**, and manages the **maintenance lifecycle** that keeps it that way — from an issue being reported, through a structured repair workflow, to verified return-to-service.

It is built as a decoupled application: a **Laravel REST API** (`backend-api/`) and a **React single-page app** (`frontend-spa/`).

---

## Table of Contents

1. [Purpose & Scope](#purpose--scope)
2. [Technology Stack](#technology-stack)
3. [Repository Structure](#repository-structure)
4. [Roles & Access](#roles--access)
5. [Modules by Role](#modules-by-role)
6. [The Maintenance Ticket Workflow](#the-maintenance-ticket-workflow)
7. [Feature Reference](#feature-reference)
8. [Fleet Intelligence & Dashboard](#fleet-intelligence--dashboard)
9. [Data Model](#data-model)
10. [API Endpoints](#api-endpoints)
11. [Security](#security)
12. [Setup & Installation](#setup--installation)
13. [Running the App](#running-the-app)
14. [Testing](#testing)
15. [Deployment](#deployment)
16. [Documentation](#documentation)

---

## Purpose & Scope

The system answers two operational questions for a barangay disaster/emergency unit:

- **"Can this vehicle be dispatched right now?"** — availability & readiness.
- **"What is being done to keep it serviceable, and by whom?"** — the maintenance lifecycle with accountability.

### In scope
- Fleet inventory and vehicle profiles
- Vehicle **availability** (dispatchable) and **condition** (physical state), tracked independently
- Issue reporting → maintenance tickets → repairs → verification → closure
- Preventive maintenance scheduling (including recurring)
- Emergency **readiness checks** and fleet **intelligence** (reliability, single-point-of-failure, failure patterns, decommission lifecycle)
- Location tracking of where vehicles are stationed (hubs)

### Out of scope (deliberate boundaries)
- **Operations / dispatch** (who drove where on a call)
- **Document compliance** (registration, insurance, permit expiry)
- **Mileage / odometer-based** servicing

These boundaries keep the system focused on **availability + maintenance**, which is what it is designed and defended to do.

---

## Technology Stack

### Backend — `backend-api/`
| Component | Version / Detail |
|---|---|
| PHP | ^8.3 |
| Laravel Framework | ^13.8 |
| Auth | Laravel Sanctum ^4.3 (bearer tokens) |
| Database | SQLite (local); Postgres-ready for deployment |
| REPL | Laravel Tinker |
| Cloud storage | Flysystem AWS S3 driver → Supabase Storage (S3-compatible), for private uploads and short-lived download links |
| Testing | PHPUnit ^12.5 |

### Frontend — `frontend-spa/`
| Component | Version / Detail |
|---|---|
| React | ^19.2 |
| Build tool | Vite ^8 |
| Routing | react-router-dom ^7 |
| HTTP | axios ^1.17 (Sanctum bearer-token interceptor) |
| Maps | Leaflet ^1.9 + react-leaflet ^5 (Esri satellite / CARTO street basemaps) |
| Animation | GSAP, OGL (visual effects) |
| Image export | html-to-image (map snapshot / "Download Map") |

---

## Repository Structure

```
CAPSTONE PROJECT/
├── backend-api/                 # Laravel REST API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/     # Auth, Fleet, Ticket, User, Hub, Notification
│   │   │   └── Middleware/      # SanitizeInput, SecurityHeaders
│   │   └── Models/              # Eloquent models (see Data Model)
│   ├── database/
│   │   ├── migrations/          # Schema history
│   │   └── seeders/             # UserSeeder, FleetReferenceSeeder, MockDataSeeder
│   ├── routes/api.php           # All API routes
│   └── tests/Feature/           # Feature test suites
│
├── frontend-spa/                # React SPA
│   └── src/
│       ├── views/Workspace.jsx  # Main app shell + all module views
│       ├── components/          # LocationDensityMap, VehicleLocationMap, etc.
│       ├── context/AuthContext.jsx
│       └── api/axios.js         # API client + token interceptor
│
└── docs/                        # Generated PDFs & workflow notes
```

---

## Roles & Access

The system has **three roles**. Each account has a **primary role** plus an optional set of **additional roles** (`users.roles`), so one person can wear several hats (e.g. a small barangay where one staffer is both Custodian and Maintenance Personnel). The sidebar a user sees is the **union** of every module across all their roles.

| Role | Responsibility |
|---|---|
| **Admin** | Reviews and approves/declines Custodian proposals, assigns/reassigns personnel, manages fleet/accounts, and handles justified decision-close. Admin does **not** create or verify ordinary repair tickets. Separate maintenance records retain Admin confirmation after Custodian verification. |
| **Custodian** | Reports issues, is the **only** role that creates a Maintenance Ticket (by proposing one for Admin's approval), registers vehicles as a standard Custodian duty, and verifies a mechanic's completed repair. |
| **Maintenance Personnel** (Mechanic) | Executes the actual repairs, logs work performed & parts used, and submits work orders for verification. |

Permission checks use role membership (not just the primary role), so multi-role users are correctly allowed to act under any hat they hold.

---

## Modules by Role

**Admin**
- Dashboard · Issue Reports · Maintenance Tickets (Archives live inside this module now, not a separate row)
- Vehicle Management · Vehicle Types · Vehicle Location · Vehicle Documents
- Condition Monitoring · Maintenance Schedule · Maintenance Records
- Users · Reports · Activity Log

**Custodian**
- Dashboard
- Vehicles: View Vehicles · Vehicle Documents · Vehicle History
- Vehicle Operations: Report Vehicle Issue · My Tasks (Assigned Inspections / Repair Verification / Work Tracker, tabbed) · Condition Monitoring
- Maintenance: Maintenance Schedule · Maintenance Records

**Maintenance Personnel**
- Dashboard
- Maintenance: My Work Orders · Work Tracker · Maintenance Records · Maintenance Schedule
- Issues: Vehicle Issues (their own reports, with a Report Technical Issue action inline)
- Vehicles: View Vehicles (no Vehicle Documents access — repair evidence lives on the work order itself)

---

## The Maintenance Ticket Workflow

**Only a Custodian creates a Maintenance Ticket.** There is no Admin "create ticket" path — `ticket.create` is a permanently empty permission grant (nobody holds it, by design); the only reachable way a ticket comes into existence is a Custodian's proposal, which an Admin reviews and approves or declines. The ticket (the "Main Issue") represents a problem on a vehicle; the Custodian's proposal already states the sub-issues (root causes) and how each will be repaired.

### Ticket states
`Pending Approval ? Active ? For Verification ? Closed` (also Declined and Cancelled).

### The workflow

1. **Report (Custodian or Maintenance Personnel).** Submit an issue with its vehicle, description, severity, and evidence. A mechanic can recommend a ticket to a Custodian.
2. **Propose (Custodian).** Propose the repair, optionally linked to the report, as Pending Approval. Select in-house, cannibalized, or external-shop work and enter its work lines.
3. **Approve and assign (Admin).** Review or decline the proposal. In-house repairs require a mechanic; external-shop work is approved without an in-house mechanic. Reassignment requires a reason.
4. **Repair.** The assigned mechanic logs work and parts. For shop work, Admin records dispatch, return, and repair evidence. Completing all repair lines moves the whole ticket to For Verification and notifies its assigned Custodian.
5. **Verify (assigned independent Custodian).** Approve the completed work to close the whole ticket automatically, or disapprove with a reason and return it for rework. Server-side guards prevent anyone who performed or handled the repair from verifying it, including multi-role accounts and previous mechanics. Reassign an independent Custodian when needed; there is no Admin verification fallback.

Maintenance records created outside the ticket workflow have their own lifecycle: Custodian verification followed by Admin confirmation. Recurring schedule completion creates such a proof record and the next occurrence.

### Handling unfinished work
- **Rework and cancellation:** independent Custodian verification returns selected failed repairs for rework; cancellation requires a reason and preserves evidence. Admin decision-close and per-item deferral are legacy paths without current role grants.
- **Aging:** open tickets are flagged once they pass 30 days.

### Archives
Closed tickets are locked in a permanent **Ticket Archive** audit log. An accidentally-*deleted* ticket that had real progress can be reopened from the archive.

---

## Feature Reference

### Fleet Inventory
- Register / edit / archive / restore vehicles, with photo upload.
- **Vehicle Types** (categories) with a **domain** (land / water) — water vehicles capture hull material (dropdown) and engine type.
- **Availability** (Available / Under Maintenance / Inactive / Decommissioned) vs **Condition** (physical state) tracked separately.
- Rich **vehicle profile page**: information, status & key dates, operational status, location map and documents/photos; repair history is available through Maintenance Records.

### Issue Reporting
- Custodian and Maintenance Personnel can both report vehicle issues (severity-rated, with photo evidence); filing one lands on that report's own page with a **Create Maintenance Ticket** action right there for the Custodian — not back on a list to search for what was just submitted.

### Maintenance Records & Schedules
- **Maintenance Records:** log external/historical/third-party maintenance and costs without running the full ticket workflow. Verify/confirm flow supported.
- **Maintenance Schedules:** plan preventive maintenance.
  - **Recurring PM:** a schedule can repeat (Monthly / Quarterly / Every 6 months / Yearly).
  - **Complete & close the loop:** marking a schedule "Done" in one action records the proof-of-work maintenance record **and** auto-creates the next occurrence at *completed date + interval* if it recurs.

### Condition Monitoring
- Records and filters vehicle condition checks over time (by category, condition, and date range).

### Vehicle Location
- **Leaflet map** of the barangay (Paknaan) showing **hubs** and how many vehicles are stationed at each.
- **Map / Satellite** basemap toggle (Esri World Imagery, no API key) — defaults to **Satellite**.
- Highlighted **Paknaan boundary** (high-visibility line), hub management (Admin can add/pin/remove hubs), focus-on-vehicle, fullscreen, and **Download Map** (PNG snapshot).
- A tabular **Location Record** with history.

### Vehicle History
- Automatic, paginated activity timeline across location, issue, condition, and maintenance events (10 / 20 / 40 / 50 per page, searchable).

### Notifications
- In-app notifications for workflow handoffs (assignments, reassignments, verifications), with read/read-all/delete.

### Reports
- Report catalog with a generator and printable preview (browser print), with the supported export actions in each module.

### Users & Logs
- User management (create, edit, activate/deactivate) with multi-role support.
- Read-only **accountability logs** of user actions (paginated).

### UI/UX
- Light/dark theme support throughout.
- Calm, consistent card design; paginated data tables; dismissible per-module hints; labeled primary actions.

---

## Fleet Intelligence & Dashboard

The dashboard surfaces the operational and analytical signals that make this more than a CRUD app:

- **Emergency readiness & coverage** — how many units of each type are ready to respond.
- **Response-readiness checks** — a Custodian personal-operation attestation per vehicle, with a freshness window (a readiness check goes stale after 24h; "never checked" is flagged).
- **Availability forecast** — expected return-to-service dates.
- **Preventive-maintenance watch** — services coming due.
- **Reliability** — failure counts (6-/12-month), average days out of service, and lifetime repair spend; chronic/repeat-failure units flagged.
- **Single point of failure** — vehicle types with only one working unit.
- **Fleet-wide failure patterns** — recurring failure categories across the fleet.
- **Decommission lifecycle** — retire a vehicle that is no longer worth keeping, with a reason.

---

## Data Model

Key Eloquent models (`backend-api/app/Models/`):

| Model | Purpose |
|---|---|
| `User` | Accounts; `role` (primary) + `roles` (JSON, multi-role). |
| `Vehicle` | Fleet unit; availability, condition, domain/water fields, decommission fields, photo. |
| `VehicleCategory` | Vehicle type + domain (land/water). |
| `MaintenanceTicket` | The "Main Issue"; status, priority, recurrence, close/return-to-service. |
| `TicketSubIssue` | Individual repair item; status, repair logs, parts, functional test, deferral. |
| `TicketArchiveLog` | Permanent record of closed/deleted tickets. |
| `VehicleIssueReport` | Reported problems (feed tickets; breadcrumb for deferrals). |
| `VehicleMaintenanceRecord` | Logged/verified maintenance work + cost. |
| `VehicleMaintenanceSchedule` | Planned PM; `recurrence_months` for recurring. |
| `VehicleConditionCheck` | Point-in-time condition assessment. |
| `VehicleReadinessCheck` | "Ready to respond" checklist result. |
| `VehicleLocation` | Where a vehicle is stationed. |
| `VehicleHub` | Named location/hub on the map. |
| `VehicleHistory` | Auto activity timeline. |
| `Notification` | In-app notifications. |
| `ActivityLog` | Accountability log. |

---

## API Endpoints

All routes are under `/api` (see `backend-api/routes/api.php`). All protected routes require a Sanctum bearer token.

**Auth (public + protected)**
- `POST /login` (rate-limited), `POST /logout`, `GET /user`, `GET /greeting`, `PUT /profile/password`

**Fleet & reference**
- `GET /lookups`, `GET /dashboard`
- `GET|POST /categories`, `PUT|DELETE /categories/{id}`
- `GET|POST /vehicles`, `PUT|DELETE /vehicles/{id}`, `POST /vehicles/{id}/restore`, `PUT /vehicles/{id}/decommission`
- `GET /vehicles/{id}/reliability`, `GET /vehicles/{id}/readiness`, `POST /vehicles/{id}/readiness-check`
- `GET|POST /locations`, `GET|POST|PUT|DELETE /hubs`
- `GET|POST /conditions`, `PUT|DELETE /conditions/{id}`
- `GET|POST /issues`, `PUT|DELETE /issues/{id}`
- `GET|POST /maintenance-records`, `PUT /maintenance-records/{id}`, `.../verify`, `.../confirm`
- `GET|POST /maintenance-schedules`, `PUT|DELETE /maintenance-schedules/{id}`, `PUT /maintenance-schedules/{id}/complete`
- `GET /histories`, `GET /reports`, `GET /logs`

**Users**
- `GET|POST /users`, `PUT /users/{id}`, `PUT /users/{id}/activate|deactivate`

**Ticket workflow**
- `GET /tickets`, `GET /tickets/{id}`, `POST /tickets`, `GET /tickets/lookups`
- `POST /tickets/propose`, `PUT /tickets/{id}/approve|decline`, `PUT /tickets/{id}/verify`
- `PUT /tickets/{id}/assign-mechanic`, `PUT /tickets/{id}/submit-for-verification`
- `PUT /tickets/{id}/sub-issues/{sub}/reassign-mechanic | log-repairs | defer | external-sent | external-returned`
- `PUT /tickets/{id}/close | cancel | uncancel`, `DELETE /tickets/{id}`
- `GET /ticket-archives`, `PUT /ticket-archives/{id}/reopen`

**Notifications**
- `GET /notifications`, `PUT /notifications/read-all`, `PUT /notifications/{id}/read`, `DELETE /notifications/{id}`

---

## Security

- **Token auth:** Laravel Sanctum bearer tokens; the SPA attaches the token via an axios interceptor.
- **Rate limiting:** login and password-change endpoints are throttled (`throttle:10,1`) to blunt brute-forcing.
- **Input sanitization** (`SanitizeInput` middleware): strips NULL/control characters and guards against invalid UTF-8 — **non-destructive** (does not strip HTML, because the correct XSS defense is output-encoding, not input mangling).
- **Security headers** (`SecurityHeaders` middleware): `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, a strict `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`, and removal of `X-Powered-By`/`Server`.
- **XSS:** React auto-escapes output; no `dangerouslySetInnerHTML`; user-supplied URLs are protocol-checked.
- **SQL injection:** all queries are parameterized (Eloquent / bound placeholders).
- **Authorization:** every controller action enforces role membership; ownership checks (e.g. only the assigned Custodian/Mechanic can add a sub-issue) are by ID, not just role label.

> **Dedicated account switcher:** `impersonation@test.com` automatically enters the active `admin@barangay.gov` workspace (Paknaan in the demo data). If unavailable, the system uses the first active barangay Admin by barangay ID and user ID. This utility account can open active staff and Super Admin workspaces across every barangay. It has no direct fleet or Super Admin administration access. Ordinary users and Super Admins cannot start impersonation. Only sessions opened through the utility account show the Province, City/Municipality, Barangay, and User dropdowns. Direct staff logins have no impersonation controls; their fleet maps use their own barangay boundary. Select an account from the User dropdown, then click the adjacent Impersonate button to open its workspace and record the switch in the audit log. The dropdown contains only real accounts, with no empty User option. Production sessions retain the existing 30-minute expiry and read-only behavior.

---

## Setup & Installation

For teammates on Windows, pull the shared branch and run `powershell -ExecutionPolicy Bypass -File .\setup-demo.ps1` from the repository root. This installs dependencies from the committed lockfiles, creates missing `.env` files/keys, backs up local SQLite, applies migrations and seeds the shared demo. Existing configuration and keys are preserved. With dependencies already installed, add `-SkipInstall`. A Git pull alone does not update your database. See [team demo setup](VMS%20Docs/Sample-Data.md#teammates-after-pulling).

### Prerequisites
- PHP 8.3+ and Composer
- Node.js 20.19+ or 22.12+ (Vite 8 requirement) and npm

### 1. Backend (`backend-api/`)
```bash
cd backend-api
composer install
cp .env.example .env          # then edit as needed
php artisan key:generate
php artisan migrate --seed     # creates SQLite DB + seeds accounts & mock data
```

For an existing database, run `php artisan migrate` to apply updates. The barangay numbering migration assigns separate record and ticket numbers to each barangay while preserving database IDs and relationships. Each module starts at 1; deleted numbers are not reused.

Local/testing sample data now contains **five Paknaan vehicles: two fire trucks and three ambulances**, six staff profiles using the supplied cat photos, three ongoing tickets and four maintenance schedules. To replace the earlier 15-vehicle demo without rebuilding the database, back up first, then run `php artisan db:seed --class=SampleFleetSeeder`. Each run resets work attached to the named demo vehicles and refreshes dates/readiness; it preserves unrelated users and vehicles. See [the sample-data guide](VMS%20Docs/Sample-Data.md) for accounts, workflow examples and reset boundaries.

By default the app uses **SQLite** (`DB_CONNECTION=sqlite`). Create the file if it doesn't exist:
```bash
touch database/database.sqlite
```

Uploads use a **private Supabase bucket** configured by `SUPABASE_PRIVATE_BUCKET` and the `SUPABASE_*` credentials. The bucket must have public access disabled. Without a working private bucket, uploads fall back to `storage/app/private`; use persistent storage for this directory in deployment. Authorized API responses issue five-minute signed links, and the frontend renews links through an authenticated endpoint when opening saved files.

For existing installations, run the new migration, then inventory old uploads with `php artisan media:privatize`. Add `--apply` to copy and verify referenced public files before revoking their public copies. `--include-cloud` explicitly includes the legacy `SUPABASE_BUCKET`/`SUPABASE_URL` location. Original database references remain valid through the managed-file mapping. Test the private bucket, migration, downloads, deletion, and backup/restoration on the target deployment before acceptance.

### 2. Frontend (`frontend-spa/`)
```bash
cd frontend-spa
npm install
```
The API base URL defaults to `http://127.0.0.1:8001/api`. To point elsewhere, create `frontend-spa/.env`:
```
VITE_API_BASE_URL=http://127.0.0.1:8001/api
```

### Seeded accounts
| Role | Email | Password |
|---|---|---|
| Admin | `admin@barangay.gov` | `admin123` |
| Custodian | `custodian@barangay.gov` | `custodian123` |
| Custodian | `custodian2@barangay.gov` | `custodian123` |
| Maintenance Personnel | `maintenance@barangay.gov` | `maintenance123` |
| Maintenance Personnel | `maintenance2@barangay.gov` | `maintenance123` |
| Maintenance Personnel | `maintenance3@barangay.gov` | `maintenance123` |

> ⚠️ These are **development** credentials — change them before any real deployment.

---

## Running the App

**Backend** (serve on port 8001 to match the frontend's default API URL):
```bash
cd backend-api
php artisan serve --port=8001
```

**Frontend:**
```bash
cd frontend-spa
npm run dev        # Vite dev server (default http://localhost:5173)
```

Then open the SPA and sign in with a seeded account.

For a production build of the SPA:
```bash
npm run build      # outputs to frontend-spa/dist/
```

---

## Testing

Feature tests live in `backend-api/tests/Feature/`:

| Suite | Covers |
|---|---|
| `TicketWorkflowTest` | The full 5-phase ticket lifecycle, deferral, reassign, functional test, decision-close. |
| `FleetIntelligenceTest` | Readiness, reliability, single-point-of-failure, failure patterns, decommission. |
| `ScheduleCompletionTest` | Recurring PM + schedule/record close-the-loop. |
| `MultiRoleTest` | Multi-role account permissions. |
| `VehicleValidationTest` | Vehicle field validation. |
| `ImpersonationTest` | Dedicated-login permissions, cross-barangay account coverage, scope isolation, session restrictions and credentials. |
| `SmokeTest` / `ExampleTest` | Baseline. |

Run the whole suite:
```bash
cd backend-api
php artisan test
```

Verify the frontend builds:
```bash
cd frontend-spa
npm run build
```

---

## Deployment

Planned deployment topology (deferred):
- **Frontend:** Vercel (Vite static build).
- **Backend:** Render (Docker; PHP 8.4 image).
- **Database:** managed **Postgres** (Supabase), seeded fresh via the seeders.
- **Storage:** Supabase Storage for photos/attachments.

Set production env values (`APP_ENV=production`, `APP_DEBUG=false`, database credentials, `SUPABASE_*`, and the SPA's `VITE_API_BASE_URL`) accordingly.

---

## Documentation

Additional generated documentation lives in `docs/`:
- **VMS System Capabilities** (PDF/HTML) — feature overview.
- **VMS Strategic Design** (PDF/HTML) — panel-defense rationale with examples of the workflow safeguards (deferral, fit-for-service gate, readiness, single-point-of-failure, etc.).
- Workflow/manual update notes.

---

*Barangay Vehicle Management System — a smarter way to manage your barangay's emergency fleet.*


## October 22, 2026 review


Maintenance Tickets default to Pending Approval and Active for Admin. Latest Reports is paginated. Maintenance Records always uses a list. Condition Monitoring summarizes the latest inspection for each active vehicle. Schedule actions use labeled buttons.

The location catalog is internally consistent but outdated against the September 2026 PSA summary. See [the geography audit](docs/geography-audit-2026-10-22.md) for confirmed gaps and verification limits. Run `python backend-api/scripts/audit-registration-geography.py` while the API is running on port 8001; a nonzero exit currently indicates the documented official-data discrepancies.

After pulling this update, run `php artisan migrate` from `backend-api` to apply the included barangay record-number migration.


### Account switcher setup

For local/testing use, sign in with `impersonation@test.com` and password `test123`. Run `php artisan db:seed --class=ImpersonationAccountSeeder` from `backend-api` to create or reset only this utility account. The regular user seeder also includes it. Its role is `Impersonator`, separate from Super Admin, and no schema migration is needed on an up-to-date database. Fixed demo credentials are not seeded in production.

### Scope limitation

Vehicle dispatch and Usage Log tracking are outside this readiness and maintenance system. The removal migration keeps a database-only rollback archive of retired records; it has no application routes or UI. Run `php artisan migrate` from `backend-api` after pulling. Migration rollback restores archived records while their referenced vehicles and accounts still exist.


## October 23, 2026 UI documentation update

See [UI updates and branch status](docs/archive/vms/UI-UPDATES-2026-10-23.md) for ticket list/card behavior, square badges, the removal of the Custom Fields editor, vehicle type analytics, automatic satellite labels, location summary cards, report readability fixes, and the province account selector update. Changes were delivered on separate `v2` branches; the note distinguishes pushed work from the selector/documentation branch and does not claim deployment.

Use [the current VMS document index](VMS%20Docs/README.md) for maintained references, the requirements PDF and the latest presentation with examples. Older reports and superseded exports are in [the historical archive](docs/archive/vms/README.md). Earlier generated manuals elsewhere in the repository remain historical material.

Registration address suggestions use [Photon](https://github.com/komoot/photon) / OpenStreetMap after a typing pause only when the registrant explicitly opts in; suggestions are off by default. Requests include the selected barangay, city and province, are rate limited, and fall back to manual entry if unavailable. Suggestions are not proof of residence. Set backend `PHOTON_URL` to a compatible hosted or private Photon `/api/` endpoint for larger deployments; the public demo has no availability guarantee.

## Current documentation ? 25 October 2026

See [current revision notes](VMS%20Docs/Current-Revision-2026-10-25.md) for integrated behavior at `130fe38`, audit disposition, setup after pulling and verification limits. The maintained overview, capabilities and requirements Markdown have been reconciled; existing exported PDFs, slides and diagrams have not been regenerated.
