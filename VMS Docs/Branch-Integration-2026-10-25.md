# V2 branch integration - 25 October 2026

Target: `origin/dev-paul`. The user requested pushing pending changes and merging every branch that had not yet been merged.

## Integrated work

All unmerged remote branch tips discovered by `git fetch origin --prune` were merged locally. Shared ancestry was identified before merging, so the task-branch stack was integrated through its latest tip rather than replaying duplicate changes. The pending workflow report and Condition Monitoring change were first committed and pushed on separate branches:

- `test/ticket-maintenance-workflow-audit` (`6ed40b5`)
- `ui/condition-monitoring-results` (`dc1b47e`)

The integration includes the unmerged `Jake/...` account, issue visibility, ticket safety, registration-code, geography, Super Admin, phone input, icon and typography branches, plus the full sequence of QA and UI branches. Source branches remain available for traceability. Merge commits record the individual source names.

## Conflict decisions

- Preserved locked ticket decisions, revision checks, required cancellation reasons, evidence retention and source-conflict checks. Older implementations of the same fixes did not replace those safeguards.
- Retained the complete audit-event timeline and the barangay ticket-number helpers.
- Combined condensed schedule cards with the existing multiple-status/urgency filters; kept action alignment and responsive tools.
- Combined Solar icons and dynamic header measurements with the existing skip link, sidebar identifiers and mobile navigation. Legacy glyph fallback remains available for controls such as photo/camera and theme switching.
- Used registered/provisioned/staffed barangay scoping for tenant management while keeping registration-code issuing independent of boundary review. Review timestamps remain present.
- Kept the current default sample-fleet seeding policy. The additional reference QA seeder is available for explicit use and is not automatically run against existing installations.
- Kept the filtered verification queue and excluded users who repaired the work.

## Verification and integration corrections

The first combined backend run exposed eight older test expectations: deleting approved work, Custodian deletion of a work item, cancellation without a reason, and verification without an explicit verdict. Tests now exercise the current safeguards; deletion with logged repairs explicitly checks that the original repair remains intact. The sub-issue removal test checks that Custodian deletion is forbidden and authorized Admin removal advances the remaining submitted work.

Lint and runtime checks identified a missing `useMemo` import in the merged Super Admin overview and unused management-section state. The import was restored, and management links now identify their destination section through the URL fragment.

The merged frontend passes 28 tests, ESLint and a production build. Browser checks pass for Condition Monitoring, History list-only display, repair method selection, personal hide/restore, schedule alignment, ticket-age position, and Super Admin overview/management. The real ticket browser journey and additional maintenance API journeys also pass; see [the workflow test report](Workflow-Test-Run-2026-10-25.md).

Final complete backend regression result: **612 tests passed, 5,765 assertions** (224.448 seconds), including the 50- and 100-ticket lifecycle cases. The code tested is revision `9e3592d`; the following documentation-only commit records these results. A fresh fetch confirmed that every origin branch tip is an ancestor of the merged development branch.

The production build reports a large bundle warning. No hosted deployment, PostgreSQL race test or external storage/email acceptance test was performed.

## Deployment note

This merge includes `2026_10_22_000001_backfill_approved_at_for_admin_created_users.php` in addition to the QA safeguard, concern-location and personal hidden-list migrations already delivered by the task branches. Apply pending migrations through the normal deployment process. This integration did not run migrations against the existing development or production fleet database; only the disposable QA database was migrated for the API journeys.


## Super Admin presentation correction

After integration, the user requested restoring the Super Admin portal that was present before the older redesign branches were merged. The presentation baseline is revision `37b25ba` (25 October): the branded overview heading, three summary cards, administrative action list, coverage donut, flat sidebar, visible table filters, barangay management pages and nationwide Province / City / Barangay registration-code selector.

This correction only changes the Super Admin frontend and removes the stylesheet for the superseded registration-code table. Backend changes from the integrated branches remain: registered-barangay scoping, nationwide search endpoints, literal user search, Admin-created account approval fixes, review dates, code audit logging, regeneration throttling and version checks. The restored selector does not require a boundary to view or issue a code. It now sends the displayed code's version when regenerating, reloads on a stale-version conflict, and refreshes platform data after code provisioning. The duplicate-barangay guard is retained.

Validation: 84 relevant backend tests passed (455 assertions); targeted frontend ESLint and production build passed. Browser checks use isolated fixtures and cover both themes, restored overview/navigation, management filters, code copy/regeneration and concurrent-version recovery, nationwide cascade, application deep links/approval, user search and role drawer, activity/support pages, duplicate prevention and mobile layout. Original logo assets, fleet workflow changes and the removed impersonation banner are preserved.
