# Diagnosis: "Login works on your machine but not mine"

## Symptom

A teammate cloned the repo, got the backend running, but:

- Their login credentials are **not the same** as the ones on the original machine.
- The login page sometimes shows: **"Unable to sign in. Please check your connection and try again."**

They concluded: *"The SQLite database only works on your computer because it's already set up there — the data is separated."*

## Root cause

That conclusion is **half right**. The problem is the SQLite **data**, not the backend code. But the database being separate per machine is **by design and correct** — it is not something to "fix" by sharing a file.

Two independent things are happening:

### 1. The SQLite file is never in the repo (correct behavior)

- [`backend-api/database/.gitignore`](backend-api/database/.gitignore) ignores `*.sqlite*`.
- The root [`.gitignore`](.gitignore) also lists `/database/database.sqlite`.

So `database.sqlite` is **never committed**. Cloning gives you an empty database with no accounts. This is the standard Laravel workflow — you never share the raw DB file.

### 2. Accounts come from the seeder, not the DB file

The three standard accounts are defined in code in [`backend-api/database/seeders/UserSeeder.php`](backend-api/database/seeders/UserSeeder.php). Running the seeder recreates **identical** accounts on every machine — no shared DB, no manual setup.

The teammate's credentials differed because they **created users by hand** instead of running the seeder.

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@barangay.gov` | `admin123` |
| Custodian | `custodian@barangay.gov` | `custodian123` |
| Maintenance | `maintenance@barangay.gov` | `maintenance123` |

### 3. The "check your connection" error is separate — it's the port

That message is a **network error**, not a credentials error. The frontend calls the API at `http://127.0.0.1:8001/api` (see [`frontend-spa/src/api/axios.js`](frontend-spa/src/api/axios.js)). If nothing is listening on **port 8001**, login fails with that message.

Plain `php artisan serve` defaults to port **8000**, so the backend must be started on **8001** explicitly (matching `APP_URL` and [`watchdog-backend.ps1`](watchdog-backend.ps1)).

## Fix

From `backend-api/`:

```bash
# Rebuild the database with the standard accounts
php artisan migrate:fresh --seed

# Start the backend on the port the frontend expects
php artisan serve --host=127.0.0.1 --port=8001
```

Then log in with `admin@barangay.gov` / `admin123`.

## Takeaways

- **Never** commit or share `database.sqlite`, and never create accounts by hand.
- Every machine gets identical schema **and** accounts from `php artisan migrate:fresh --seed`.
- Always run the backend on **port 8001**, or the login page shows the connection error.
