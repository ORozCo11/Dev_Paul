# Current implementation ? 25 October 2026

Reviewed against revision `130fe38` on `dev-paul`. At review time, `origin/WelcomePage-remove-media-icons` points to the same commit. Branch names do not describe separate versions when their commit IDs match.

## Subsequent Jake branch integration

Local development revision now includes Jake/Registration/SMTP, Jake/SupportCenterUI and Jake/ReduceDashboardRequests (merge tip 352fd0e). Earlier descriptions below and in diagram sources use the pre-merge baseline and need the following onboarding correction: a first barangay Admin now registers without a staff code, stays inactive, receives a code emailed by Super Admin, and activates by entering it with login credentials. Unclaimed emailed codes expire after seven days. Later staff registration still requires the barangay code and Admin approval.

Support Center adds Concern History and a centered detail view; concern deletion uses soft deletion. Dashboard changes reduce repeated dashboard/preflight requests. New migrations add staff-code activation fields and concern soft deletion. Apply them through the normal backed-up migration process; configure working mail transport and Laravel scheduling for email activation/expiration.

Integration validation: 24 focused registration/Super Admin tests passed with 143 assertions. No migration was applied to the existing fleet database during the merge. Local documentation edits and the pre-existing package-lock edits were preserved.

## Current behavior

- Tickets follow Custodian proposal ? Admin review/assignment ? repair ? independent Custodian verification ? closure. Approval requires personal-test attestation; rejection records a reason and sends selected failed work back for rework. Admin confirmation and decision-close are not the current ticket closure path. Legacy endpoints do not establish role access.
- Active/For Verification tickets cannot be deleted. Cancellation requires a reason and preserves evidence. Personal Hide/Hidden/Restore changes only the current user's list; it does not cancel work, remove assignments or alter operational totals.
- Maintenance Records displays completed repairs individually, with In-House Repair, Sent to External Shop and Maintenance Schedule sources. Scheduled origin takes precedence so source totals do not overlap. Work awaiting verification stays in active-work views.
- My Work Orders defaults to List View; compact Card View remains selectable. History uses a list. The ticket table retains progress percentage and completion counts.
- Readiness uses a Custodian's explicit personal operation attestation, fresh for 24 hours. Active repairs block confirmation on both the dialog and server. Repair closure invalidates older readiness evidence; it does not create a fresh check.
- Condition Monitoring accepts Good or Needs Repair for new inspections. Legacy inconclusive results remain stored; the current list uses Not Checked when no concrete outcome exists.
- Public registration uses a staff code and inactive approval flow. First-Admin approval belongs to Super Admin. Personal address suggestions require opt-in and permit manual fallback. A service-area reference is not proof of residence or legal boundary accuracy.
- The Super Admin frontend restores its pre-integration overview, management pages and nationwide registration-code cascade while retaining backend safeguards. The dedicated account switcher is separate from ordinary staff and Super Admin logins.
- Vehicle import remains an authorized API capability; the Vehicles import toolbar action is removed. Dispatch and Usage Log remain outside scope.

## Audit disposition

The [24 October audit](System-Audit-2026-10-24.md) remains a point-in-time reproduction at `0027023`, not a fresh audit of this revision.

| Finding | Current documentation disposition |
|---|---|
| AUD-24-01: managed-file signing | Keep parent-record authorization acceptance retests outstanding; this documentation review does not certify remediation. |
| AUD-24-02: issue-detail visibility | Keep list/detail/nested-record/attachment authorization retests outstanding. |
| AUD-24-03: shared catalogs | Cross-barangay ownership and historical-rename acceptance remain outstanding. Staff suggestion permissions do not resolve global catalog isolation. |
| AUD-24-04: parts-removal readiness | Repair closure invalidates readiness in current code; the specific donor/parts-removal paths still require their documented retests before closing this finding. |
| AUD-24-05: CSV formulas | Spreadsheet-safe export acceptance remains outstanding across each export path. |
| AUD-24-06: future completion dates | Later implementation rejects future manual/schedule completion dates using the Philippine day. QA-002 records isolated test evidence; target-environment acceptance remains required. |
| AUD-24-07/08: documentation | Registration is an Admin/Custodian duty; ticket-list progress is present. These descriptions do not amend the supplied academic baseline. |

## Setup after pulling

A Git pull updates source files, not the local database or installed dependencies. Follow [Sample Data](Sample-Data.md#teammates-after-pulling) and the repository setup instructions. Apply pending migrations, including approval backfill, workflow safeguards, concern locations and personal hidden-ticket preferences. The Laravel scheduler must run for timed schedule-to-proposal conversion. Back up existing data before migration or demo reseeding; demo seeders can reset their named fleet work.

## Verification and artifact status

This update reviews documentation and selected source paths. It does not rerun the application test suites. The [integration record](Branch-Integration-2026-10-25.md) reports 612 backend tests / 5,765 assertions at `9e3592d`, 28 frontend tests, lint/build and targeted browser/API checks. Later Super Admin restoration has its own targeted evidence. These recorded results do not establish that the complete suite was rerun at `130fe38`.

Hosted deployment, PostgreSQL concurrency, external storage/email, recovery and representative-user acceptance remain outside those results. The integration build reported a large-bundle warning.

Markdown references are updated here. The ERD and use-case SVG overviews now have editable Markdown sources and a requirements traceability table. Existing PDF, presentation and architecture exports were not regenerated; check their source date before sharing them as current material. Historical audit and QA reports retain their original dates and results.
