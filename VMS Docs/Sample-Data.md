# Five-vehicle sample data

Updated: 24 October 2026. Entry point: `backend-api/database/seeders/SampleFleetSeeder.php`.

This is local/testing demonstration data for Paknaan, Mandaue City, Cebu. It contains exactly five sample vehicles; unrelated vehicles are preserved if they already exist. Demo accounts are active and approved. Photos are the six supplied cat images, copied unchanged into `backend-api/public/mockups/profiles/`; vehicle photos use the existing bundled mockups. No network download or cloud storage is needed to seed.

## Run or reset

### Teammates after pulling

Pull the same shared branch, then run from the repository root in Windows PowerShell:

```powershell
git pull --ff-only
powershell -ExecutionPolicy Bypass -File .\setup-demo.ps1
```

Prerequisites: PHP 8.3+ with the project's Composer extensions (including SQLite), Composer, and Node.js compatible with Vite 8 (20.19+ or 22.12+), with npm. The script uses `composer install` and `npm ci` with committed lockfiles; it creates missing configuration files and keys but preserves existing ones. It is safe to run again to reset the demo. If dependencies are already installed, use `-SkipInstall`.

On an already configured installation, including macOS/Linux, the backend-only equivalent is:

```bash
cd backend-api
php artisan config:clear
php artisan demo:sync
```

`demo:sync` accepts local/testing SQLite only. Before migrating or seeding, it saves a consistent SQLite backup under `backend-api/storage/app/backups/` and stops if backup creation fails. It then runs the complete reference seed chain and the five-vehicle sample reset. The backup stays on that teammate's machine and is not committed. Custom renamed demo plates or donor links to non-demo work stop the sample reset with an explanation.

Start the API on port 8001 and the SPA with `npm run dev` in a second terminal. The default frontend API URL is `http://127.0.0.1:8001/api`; check existing environment overrides if requests go elsewhere. Log out and log back in so an old browser session does not retain the previous photo/profile. Browser theme and viewed-item badges are per-browser preferences and are not synchronized by Git.

Git shares the seed code, all 11 used vehicle/profile image assets and dependency lockfiles. It does not share `.env`, application keys, access tokens, the SQLite database, browser storage or private uploaded files. Everyone who runs the setup gets the same sample names, roles, credentials, photos, repair assignments, ticket stages and schedule examples. Database IDs/visible record numbers may differ on existing databases; dates and readiness timestamps are relative to each run. Unrelated existing accounts/vehicles remain, so this does not promise identical database copies or erase teammates' own work.

### Manual sample-only reset

For a fresh local installation, follow the README setup and run `php artisan migrate --seed` in `backend-api`. This seeds geographic references and the sample fleet.

For an existing local installation, back up the database, then run:

```bash
cd backend-api
php artisan db:seed --class=SampleFleetSeeder
```

`MockDataSeeder` and `DemoActivitySeeder` remain compatibility entry points to this same five-vehicle reset; they no longer restore the old 15-vehicle fleet.

Every reset replaces tickets, issues, schedules, records, condition/readiness checks, history, location records and document rows attached to the 15 named legacy demo vehicles. Ten retired sample vehicles are removed; the five retained vehicles keep their IDs. Related notifications, parts-transfer rows and matching activity entries are cleared. Existing uploaded file bytes and managed-file mappings are left intact; the reset does not delete cloud/local storage files. Migration rollback backups remain untouched. Demo account credentials/profile fields are reset; unrelated accounts and vehicles remain. Shared type/hub seed values are only inserted when absent, so existing reference edits are preserved. Re-running is a demo reset, not a migration that preserves progress on demo tickets.

The reset runs inside one database transaction. It aborts on a demo plate belonging to a renamed vehicle or another barangay, and on donor/parts-transfer links to non-demo work. It is skipped outside `local` and `testing`. It does not disable foreign keys, truncate tables or rebuild the database. Use a demo database for these public credentials.

## Staff profiles

| Name | Role | Email | Password | Photo |
|---|---|---|---|---|
| Paul Orozco | Admin | admin@barangay.gov | admin123 | Flower cat |
| Precious Dignos | Custodian | custodian@barangay.gov | custodian123 | Pink-bow cat |
| Justine Mae Belia | Custodian | custodian2@barangay.gov | custodian123 | Surprised cat |
| Jake Engana | Maintenance Personnel | maintenance@barangay.gov | maintenance123 | Orange cat |
| Rohan Naveo | Maintenance Personnel | maintenance2@barangay.gov | maintenance123 | Cat with tongue out |
| Mia Santos | Maintenance Personnel | maintenance3@barangay.gov | maintenance123 | Tabby cat |

The existing Super Admin and account-switcher demo accounts remain available through `UserSeeder`; neither is a repair performer/verifier in these samples. Staff profiles include their barangay/city and address. Account switching uses `impersonation@test.com` / `test123`.

## Vehicles and ongoing tickets

| Vehicle | Plate | Type | Initial example |
|---|---|---|---|
| Barangay Fire Responder | BFP-5501 | Fire Truck | Active pump repair: seal replacement assigned to Jake; pressure regulator inspection assigned to Rohan. |
| Ladder Company 28 | FDT-2801 | Fire Truck | Available / Good with a fresh readiness pass. |
| Metro EMS Ambulance | AMB-1101 | Ambulance | For Verification: Jake logged the lightbar repair; Precious independently verifies. |
| INEM Portuguese Ambulance | INEM-112 | Ambulance | Available / Good with a fresh readiness pass and today's scheduled service. |
| Rescue Response Ambulance | FDR-303 | Ambulance | Pending Approval: Justine proposes external-shop A/C diagnosis; Admin reviews it before work proceeds. |

Vehicles with approved ongoing repairs are Under Maintenance / Needs Repair. The pending-proposal vehicle is Available / Needs Inspection with no passing readiness check; availability alone does not mean verified ready. These initial values match the vehicle list's normal state refresh. Availability, physical condition and verified readiness remain separate. Readiness passes expire normally after 24 hours. Re-seeding refreshes the two Good/Available vehicles' passes.

## Maintenance schedules

Dates are relative to the seeding day in Asia/Manila, all at 09:00.

| Vehicle | Date | Status / next action | Assignee | Recurrence |
|---|---|---|---|---|
| Barangay Fire Responder | Yesterday | Scheduled, already linked to the Active pump-repair ticket. Continue in that ticket; do not mark the schedule done separately. | Jake | None |
| INEM Portuguese Ambulance | Today | Scheduled; Jake can record completion through Mark as Done. | Jake | 1 month |
| Ladder Company 28 | In 2 days | Scheduled preventive service. | Rohan | 3 months |
| Metro EMS Ambulance | In 4 days | Pending Approval follow-up electrical inspection. | Mia, once approved | None |

Normal background schedule-to-ticket conversion still applies. Once a scheduled job becomes a ticket, continue through that ticket rather than completing the schedule directly. Completion creates a maintenance record awaiting independent verification; the seed does not bypass that control.

## Verification

`SampleFleetSeederTest` checks fresh seeding, replacement of an old sample, repeat runs without duplication, preservation of unrelated users/vehicles, profile assets and login, real API repair handoff, independent ticket verification, schedule completion/recurrence, production skipping, conflicting-plate refusal and SQLite foreign-key integrity. Tests use an isolated database; they do not complete the sample work in your running application.

Team-setup verification (24 October 2026): a separate checkout started without dependencies, environment files or a database. The setup installed the locked Composer/npm dependencies, migrated and seeded fresh SQLite, and its frontend production build passed. After aligning proposal availability with the normal vehicle-list refresh, the recreated demo matched the working app's vehicle/profile details, repair assignments, ticket stages and schedules; all 11 photo files matched byte-for-byte. Repeated setup preserved environment files/application keys, generated valid backups and introduced no duplicate samples. Record IDs, timestamps, URL hostnames and unrelated local data were excluded from the comparison. `SyncDemoTest` also verifies production/remote-database refusal and stopping before migration when backup creation fails.

Final regression result for this setup change: **489 backend tests passed (2,339 assertions)**, including nine focused sample/setup checks (94 assertions). The production frontend build passed using dependencies installed in the isolated checkout.
