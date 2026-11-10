# Use-case working source

Baseline: 28 October 2026, working tree on `dev-paul` after `20449a7`. The [SVG](../VMS%20FINAL%20DEFENSE%20PREPARATION/Use-Case-Diagram.svg) groups current use cases by actor. This Mermaid view is an editable actor/action overview, not formal UML include/extend notation. Shared actors do not inherit every action from each other.

```mermaid
flowchart LR
    A0["Applicant"] --> U0(["UC-01 Register with staff code"])
    A1["Admin (Custodian: add/edit only)"] --> U1(["UC-02 Manage fleet, vehicle types and locations"])
    A2["Admin / Custodian"] --> U2(["UC-03 Register vehicle"])
    A3["Custodian"] --> U3(["UC-04 Report vehicle issue"])
    A4["Custodian"] --> U4(["UC-05 Propose or resubmit ticket"])
    A5["Admin"] --> U5(["UC-06 Review proposal and assign work"])
    A6["Maintenance Personnel"] --> U6(["UC-07 Record assigned repairs"])
    A7["Custodian (Admin only approves the proposal)"] --> U7(["UC-08 Handle external-shop repair"])
    A8["Custodian"] --> U8(["UC-09 Verify repair and close ticket"])
    A9["Custodian"] --> U9(["UC-10 Record condition or confirm readiness"])
    A10["Custodian"] --> U10(["UC-11 Book and edit own schedule"])
    A11["Admin"] --> U11(["UC-12 Review and reassign schedule"])
    A12["Maintenance Personnel"] --> U12(["UC-13 Complete assigned schedule"])
    A13["Admin / Custodian"] --> U13(["UC-14 Manage standalone repair record"])
    A14["Admin"] --> U14(["UC-15 Manage staff, reports and audit"])
    A15["Super Admin"] --> U15(["UC-16 Manage platform and first-Admin approval"])
    A16["Operational staff"] --> U16(["UC-17 Hide or restore own ticket list entry"])
    A17["Scheduler"] --> U17(["UC-18 Convert due approved schedule to proposal"])
    A18["Admin"] --> U18(["UC-19 Create user with temporary password"])
    A19["Any account with a temporary password"] --> U19(["UC-20 Set own password at first sign-in"])
    A20["Admin / Custodian / Maintenance Personnel"] --> U20(["UC-21 View vehicles and fleet catalogs"])
```

Use [the traceability table](Requirements-Traceability.md) for preconditions, entity links and role restrictions. Actor groups such as Admin / Custodian apply only to the named action. UC-14 deliberately summarizes a three-step standalone record lifecycle; Admin entry/confirmation and Custodian verification remain separate responsibilities.

## Diagram rules

- Keep the platform boundary separate from barangay fleet access. Super Admin has no ordinary fleet access.
- External-shop handling (send, return, rework) belongs to the ticket's Custodian; the Admin only approves the proposal and has no shop use case.
- Model successful ticket closure as the outcome of assigned independent Custodian verification, not a second Admin approval use case.
- Model scheduled conversion as a scheduler action creating a proposal, not automatic approval.
- Do not infer include/extend from chronological order. For a formal UML export, split UC-14 into enter, independently verify and confirm, then assign each to its actor.
- A utility impersonation account is an optional support actor, not a Super Admin permission. Its local/testing write exceptions and production expiry/read-only guards belong in support documentation.
- Maintenance Personnel do not report issues; their actions are UC-07, UC-13 and UC-21. Each account holds exactly one role, so the actor boxes are roles, not people holding several roles.
- Public concern submission and optional support switching can be expanded in a separate platform/support diagram. They do not grant fleet permissions.

Authority: [permission grants](../backend-api/config/permissions.php), controller state/ownership checks, [Must-Haves](Must-Haves.md). Authorization findings and deployment acceptance remain tracked in [revision notes](Current-Revision-2026-10-25.md).
