# Screenshot UI refinements — 19 October 2026

Implemented the annotated screenshot requests:

- Barangay View / Manage opens a full page at `/superadmin/barangays/:id`, with reload support, back navigation, registration code management, boundary review, and administrator recovery.
- Ticket details omit the Next Best Action summary, inspection result, and sub-issue edit controls. Vehicle status, condition, and repair progress in the overview use colored text.
- Parts and materials start empty, can be removed entirely, and are optional. Repair logs accept no parts and no maintenance cost.
- Archived tickets have recoverable deletion. The Deleted Tickets checkbox shows deleted archive records and existing recoverable deleted-ticket snapshots. Restoring a closed archive restores its record without reopening the closed ticket.
- Custodian issue rows omit Propose Ticket; the page-level proposal action remains available.
- Vehicle table defaults begin with ID, Type, and Brand / Model. A new preference key applies this default for existing browser profiles.
- The maintenance-personnel label omits the optional/Admin hint.
- Cannibalization management omits the repeated repair-type badge, donor condition, and duplicate empty-state Add Part button.
- Repair evidence appears in a sidebar file cabinet beneath the submission panel. It supports up to ten pending files per submission, preview, rename, removal, browsing, and drag-and-drop. Uploaded evidence retains filename, size, uploader, and date and appears in ticket details. Previous single-file attachments remain available. Pending files upload on repair-log submission; previously submitted evidence is read-only in this cabinet.
- The loading gear rotates once every six seconds.
- Location maps show prominent barangay and city labels. Boundary details contain the review status, recorded source, and PSGC code; the unverified-reference banner is removed. The selected barangay's actual review status is preserved.

Database migration: `backend-api/database/migrations/2026_10_19_000001_add_archive_recovery_and_repair_files.php`. Applied to the local development database. Run `php artisan migrate --force` when deploying this revision.

Validation:

- Frontend production build passed.
- Full backend suite after retaining only the boundary changes from the imported branch: **415 tests passed, 1,730 assertions**. Added coverage for archive deletion/restoration, closed-ticket locking, role and barangay isolation, multiple evidence files, omitted repair costs, and rejected evidence types/sizes. File uploads use fake storage in these tests.
- Browser checks passed for barangay routing/reload/back, ticket removals and text styling, archive controls, file selection/rename/removal, external and cannibalized repair layouts, vehicle-column ordering, and Custodian issue actions. Repair layouts checked at 390px and 1440px. No JavaScript page errors. Evidence: `logs/screenshot-ui-check.json` and `logs/screenshot-*.png`.
- Six boundary geometry tests passed. Map browser checks confirmed the barangay/city labels, collapsed boundary details, removed banner, and correct labels after changing the selected barangay. Evidence: `logs/map-barangay-label-check.json` and `logs/map-barangay-label-*.png`.
- The new file cabinet and map detail components lint cleanly. Existing lint errors remain in Workspace and the imported boundary-preview form.

Browser checks used temporary development sessions, removed after verification. Existing operational tickets and archives were not deleted during browser checks; archive mutations and upload validation were exercised in isolated backend tests. Cloud-storage deployment acceptance remains separate from these checks.
