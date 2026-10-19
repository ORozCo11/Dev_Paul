# Regression continuation — 17 October 2026

## Starting point

Pulled `paul/dev-paul` through commit `053d177` before this pass. This note is an incremental verification record; it does not replace the 15 October audit or claim deployment acceptance.

## Verified fixes in this pass

- **REG-01:** logout captures and sends the current bearer token before browser storage is cleared, so the server can revoke that exact token.
- **REG-02 / REG-08:** operational fault-category and maintenance-type resolution now accepts only existing catalog rows. Creating shared catalog entries remains on the `catalog.create`-guarded endpoints, and the frontend hides catalog-add controls from users without that ability.
- **REG-03:** `SmartForm` no longer re-seeds its values on every parent render. This preserves an in-progress completion form's entered values and selected file while its conditional fields change.
- **REG-04:** the workspace preserves a valid URL-selected module instead of resetting a direct schedule link to Dashboard while the module list settles.
- **REG-06 / REG-07:** the completion-date default uses `Asia/Manila`; the receipt chooser now advertises only the file types accepted by the server.
- **REG-09:** vehicle-document replacement/deletion attempts to remove the corresponding application-owned storage object. Cleanup failures are logged and do not block deletion of the database row.
- **REG-13:** API guests are no longer redirected to a nonexistent web login route. A plain `GET /api/vehicles` without credentials now returns `401 {"message":"Unauthenticated."}`.
- **REG-17 / REG-18:** policy section bodies use a block container rather than invalid paragraph/list nesting, and use the shared VMS logo component.

## Verification

- PHP syntax checks passed for the changed backend files.
- `VehicleDocumentUploadTest` and `PhaseB4RoleModelTest` passed: **34 tests, 65 assertions**. The latter includes a regression case proving a Custodian cannot create an unknown maintenance type indirectly through a schedule.
- Frontend production build passed. It still reports the existing large-chunk warning.

## Still open / not claimed complete

- **REG-10:** existing file URLs remain public. A complete fix requires private Supabase bucket policy plus an authenticated or signed-download delivery design for every current image/document consumer. This cannot be verified without deployment storage credentials and bucket-policy access.
- The remaining audit items, including the full frontend lint backlog and route-level performance splitting, need additional code work and browser coverage. Lint was run after restoring the lockfile dependencies and still reports **55 errors and 19 warnings**; no lint checks were disabled.
- Cloud upload/retrieval/retention/backup, production PostgreSQL behavior, mail/queue/scheduler behavior, report-format acceptance, and current authoritative barangay geometry remain deployment, adviser, or data-owner work. In particular, Mantalongon's boundary remains an unverified reference until an LGU-approved geometry is supplied.
