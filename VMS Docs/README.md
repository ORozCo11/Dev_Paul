# Current VMS documents

Updated 1 November 2026 after the panel redefense. Start with [the redefense changes](Redefense-Changes-2026-11-01.md) (no public registration, emailed temporary passwords, local-only storage, View Vehicles sub-pages, merged Condition Monitoring, Maintenance Personnel tests repairs), then with [the 28 October revision notes](Current-Revision-2026-10-28.md) (MySQL, one role per account, Custodian-only issue reporting, temporary passwords, several donor vehicles, new sidebar groups, Gmail email), then [the 25 October notes](Current-Revision-2026-10-25.md), then the overview or detailed reference. The two Must-Haves PDFs were regenerated on 28 October. The Demo Workflow PDF and the two slide decks were not; the "Older exports" rows below say exactly what in them is outdated. Diagrams, ERD, schema reference and the SRS in the defense folder are maintained separately.

| File | Use it for |
|---|---|
| [Redefense-Changes-2026-11-01.md](Redefense-Changes-2026-11-01.md) | Panel feedback item by item, the new account and repair flows, open gaps and likely panel questions. Read first; it supersedes older statements. |
| [Current-Revision-2026-10-28.md](Current-Revision-2026-10-28.md) | What changed since 25 October, statements it supersedes, verification and known gaps. Read first. |
| [Requirements-Traceability.md](Requirements-Traceability.md) | Start here for actor/use-case IDs, requirements, entities and acceptance rules. |
| [ERD.md](ERD.md) | Editable logical ERD, key notes and schema constraints to verify. |
| [Use-Case-Diagram.md](Use-Case-Diagram.md) | Editable actor/action overview and diagram rules. |
| [Documentation.md](Documentation.md) | System overview, architecture, workflow and current audit status. |
| [System-Capabilities.md](System-Capabilities.md) | Detailed module and feature reference; check the audit for known limitations. |
| [Must-Haves.md](Must-Haves.md) | Final-defense must-have list (what to demonstrate, where it lives), then the requirements mapping and outstanding acceptance criteria. |
| [VMS-Must-Haves-Updated.pdf](VMS-Must-Haves-Updated.pdf) | Print of [Must-Haves.md](Must-Haves.md), regenerated 28 October 2026 (8 pages). |
| [VMS-Must-Haves-Short.pdf](VMS-Must-Haves-Short.pdf) | Two-page must-haves and functionalities by role, regenerated 28 October 2026. |
| [Sample-Data.md](Sample-Data.md) | Team setup, demo accounts, five vehicles, sample work and verification results. |
| [System-Audit-2026-10-24.md](System-Audit-2026-10-24.md) | Historical six-finding audit baseline; see current revision notes for disposition. |
| [Ticket-Process-Review-2026-10-24.md](Ticket-Process-Review-2026-10-24.md) | Ticket fields, repair/decision fixes, migration notes and verification evidence. |
| [Claude-Browser-QA.md](Claude-Browser-QA.md) | Claude browser QA skill, full-system test catalogue, findings format and starter prompt. |
| [Branch-Integration-2026-10-25.md](Branch-Integration-2026-10-25.md) | Unmerged V2 branches integrated into dev-paul, conflict decisions and combined verification. |
| [Workflow-Test-Run-2026-10-25.md](Workflow-Test-Run-2026-10-25.md) | Latest ticket/maintenance regression results, real API/browser journey and remaining verification limits. |
| [QA-Run1-Fixes-2026-10-25.md](QA-Run1-Fixes-2026-10-25.md) | Disposition of all 37 browser-audit findings, safeguards, migration and retest evidence. |
| [VMS-Evidence-and-Defense-Review-With-Examples.pptx](VMS-Evidence-and-Defense-Review-With-Examples.pptx) | **Older export (25 October).** Defense presentation with examples and speaker notes. Outdated: slide 14 (architecture table lists SQLite and PostgreSQL; the database is MySQL 8), slide 15 ("SQLite" row in the repair-submission example), slide 22 ("SQLite backup creation was demonstrated" refers to the earlier SQLite setup; MySQL backup/restore has not been demonstrated). Also outdated: any slide or note saying the Admin sends/receives external-shop work. |
| [VMS-Final-Defense-Presentation-v4.pptx](VMS-Final-Defense-Presentation-v4.pptx) | **Older export.** Outdated: slide 9 "Who uses it" lists "Reports technical issues" under Maintenance Personnel (only the Custodian reports issues) and omits the Custodian's Vehicle Types and Vehicle Location pages. Any slide or speaker note that says the Admin sends, receives or handles external-shop work is also outdated: the Admin only approves; the ticket's Custodian logs send/return, tests the vehicle and closes the ticket. Slides 10 and 14 (ticket flow, demo) otherwise match the workflow. |
| [VMS-Demo-Workflow.pdf](VMS-Demo-Workflow.pdf) | **Older export (25 October).** Outdated: step 2 (Admin-created accounts now start with a temporary password and the user must change it at first sign-in), step 3 (Condition Monitoring has no Add button; a new vehicle is Good/Available and condition changes by itself), step 4 (the propose form first asks about the vehicle's open issue reports). Steps 5-7 still match, except any step that has the Admin send or receive an external-shop repair (the ticket's Custodian does; Admin only approves). |
| [System-Architecture.svg](../VMS%20FINAL%20DEFENSE%20PREPARATION/System-Architecture.svg) | Component diagram. |
| [ERD.svg](../VMS%20FINAL%20DEFENSE%20PREPARATION/ERD.svg) | Database relationship diagram. |
| [Use-Case-Diagram.svg](../VMS%20FINAL%20DEFENSE%20PREPARATION/Use-Case-Diagram.svg) | Role and use-case diagram. |

Older audits, UI reports, geography-review evidence, planning documents and superseded exports live in [the historical archive](../docs/archive/vms/README.md). Their dates and claims describe earlier revisions. They do not supersede the current audit or establish production acceptance.

The latest combined regression result is **612 backend tests / 5,765 assertions**, with **28 frontend tests**, lint, production build and targeted browser/API workflow journeys passing; see [the integration record](Branch-Integration-2026-10-25.md) and [workflow test run](Workflow-Test-Run-2026-10-25.md). Broader audit findings still require their documented acceptance review. Diagrams and older generated artifacts are not a substitute for the current code and verification evidence.
