# Requirements and diagram traceability

Working baseline: 25 October 2026, revision `130fe38`. These IDs organize the current implementation; they do not replace the supplied academic requirements or certify acceptance.

| ID | Actor | Use case / requirement | Main entities | Acceptance rule |
|---|---|---|---|---|
| UC-01 | Applicant | Register with staff code | User, Barangay, RegistrationSetting | Starts inactive; first Admin requires Super Admin approval |
| UC-02 | Admin | Manage fleet and locations | Vehicle, VehicleCategory, VehicleHub, VehicleLocation | Barangay scope; occupied hub cannot be deleted |
| UC-03 | Admin / Custodian | Register vehicle | Vehicle, VehicleCategory | Editing remains Admin-only; import API uses same role eligibility |
| UC-04 | Custodian / Maintenance Personnel | Report vehicle issue | VehicleIssueReport, IssueReportAttachment | Own-report and assigned-work visibility require acceptance retests |
| UC-05 | Custodian | Propose or resubmit ticket | MaintenanceTicket, TicketSubIssue | Proposal enters Pending Approval; corrections do not start repair |
| UC-06 | Admin | Review proposal and assign work | MaintenanceTicket, TicketSubIssue, User | Approve, decline or request corrections; no direct ticket creation |
| UC-07 | Maintenance Personnel | Record assigned repairs | TicketSubIssue, DonorPart | Assignment and state guards; no self-verification |
| UC-08 | Admin | Handle external-shop repair | TicketSubIssue, VehicleMaintenanceRecord | Handler cannot verify the work |
| UC-09 | Custodian | Verify repair and close ticket | MaintenanceTicket, TicketSubIssue, VehicleMaintenanceRecord | Independent assigned verifier; approval attests personal testing; rejection identifies rework |
| UC-10 | Custodian | Record condition or confirm readiness | VehicleConditionCheck, VehicleReadinessCheck | Readiness blocked during active repairs; fresh operation attestation required |
| UC-11 | Custodian | Book and edit own schedule | VehicleMaintenanceSchedule | Admin reviews; actual edits return to approval |
| UC-12 | Admin | Review and reassign schedule | VehicleMaintenanceSchedule | Approve/decline; no Admin performance of maintenance |
| UC-13 | Maintenance Personnel | Complete assigned schedule | VehicleMaintenanceSchedule, VehicleMaintenanceRecord | Philippine date validation; verification lifecycle and recurrence apply |
| UC-14 | Admin / Custodian | Manage standalone repair record | VehicleMaintenanceRecord | Admin enters/confirms; independent Custodian verifies |
| UC-15 | Admin | Manage staff, reports and audit | User, ActivityLog | Barangay scope; last-Admin and open-work guards |
| UC-16 | Super Admin | Manage platform and first-Admin approval | Barangay, User, RegistrationSetting, ConcernReport | No fleet access; includes codes, boundaries and platform support |
| UC-17 | Operational staff | Hide or restore own ticket list entry | hidden_ticket_lists, MaintenanceTicket, User | Personal preference only; work and totals remain intact |
| UC-18 | Scheduler | Convert due approved schedule to proposal | VehicleMaintenanceSchedule, MaintenanceTicket | Requires Laravel scheduler; still requires Admin ticket approval |

## How to revise the academic artifacts

1. Confirm scope and role alignment in [Must-Haves](Must-Haves.md); retain adviser decisions separately from implementation facts.
2. Edit [ERD source](ERD.md) and [use-case source](Use-Case-Diagram.md), keeping these IDs stable.
3. Check physical keys, nullability, uniqueness and deletion rules against migrations before claiming a physical schema constraint. Model relationships alone are insufficient.
4. Reconcile the overview, capabilities and diagrams together. Use [current revision notes](Current-Revision-2026-10-25.md) for audit retests and verification limits.
5. Regenerate exported PDF/slide material after the working sources are agreed. Existing exports remain older snapshots.

The main model is fleet readiness and maintenance. Do not add dispatch, trips, Usage Log, direct Admin ticket creation, Admin ticket verification or ticket decision-close as current operational requirements. Standalone record confirmation is a separate Admin action. Multi-role accounts remain subject to repairer/handler independence checks.
