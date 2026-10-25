# Report and evidence format

Write for developers and barangay representatives. Urgent confirmed issues come first; keep hypotheses and optional improvements separate. Create a Markdown report/case ledger when file tools permit, otherwise provide tables in chat. Never fabricate files/evidence links.

## Run record

Record run ID, URL/environment/revision or unknown, date/time/timezone, browser/viewport, anonymized role/tenant aliases, approved writes, requirement sources, unavailable tools/accounts, fixtures/side effects/cleanup and actual scope. No credentials/private personal details.

## Findings index

| ID | Type | Title | Severity | Priority | Confidence | Role/module | Status |
|---|---|---|---|---|---|---|---|

Types: functional bug; security/authorization; data integrity; workflow/role conflict; requirements/documentation discrepancy; unintended/out-of-scope feature; UX/accessibility; environment limitation; optional improvement.

Severity follows demonstrated consequence:
- Critical: demonstrated broad compromise, widespread irreversible loss or immediately severe safety-critical bypass.
- High: unauthorized sensitive action/access, independent-verification bypass, lost repair evidence or blocked core workflow without reasonable workaround.
- Medium: wrong state/result or obstructed task with workaround, misleading operational information or substantial recurring UX failure.
- Low: minor presentation/consistency defect with limited task impact.
- Informational: improvement, policy question, unverified risk or environment limit without established defect impact.

Priority: P0 immediate containment, P1 before acceptance/release, P2 planned fix, P3 backlog. Explain context; do not mechanically equate priority and severity. Confidence: Confirmed, Likely, Unverified. Preserve serious one-time observations if repetition is unsafe. Status: Open, Needs policy decision, Needs investigation, Fixed and verified, Partially fixed, Still reproducible, Cannot retest.

## Each finding

### QA-001 - Concrete problem and consequence

- Classification: type/severity/priority with rationale, confidence/status.
- Location/actors: route, fixture alias, starting state, role/tenant/ownership; redact sensitive URLs.
- Expected behavior/authority: precise rule with source/date, or explicitly proposed policy.
- Preconditions/data: safe synthetic reproduction setup.
- Reproduction: numbered actions/inputs.
- Actual: visible response, persisted data, repeat rate and downstream effects. Separate UI/API/source evidence.
- Evidence: screenshot/tool observation/time, sanitized status, case/record IDs; state unavailable evidence.
- Impact: affected users/tasks/data/decisions, prerequisites and limits.
- Cause: confirmed mechanism/evidence or hypothesis plus diagnostic next step. No invented file/line numbers.
- Proposed solution: smallest practical change at correct layer, preserving working paths/evidence; alternatives/tradeoffs for policy questions.
- Acceptance: observable pass/fail criteria including persistence, denied roles and linked effects.
- Regression: original case, adjacent valid path, wrong role and downstream workflow.
- Workaround: safe route or none known; never shared passwords/impersonation/verification bypass.
- Retest: new build/date/outcome/evidence/remaining gaps; preserve original finding.

## Hypothetical examples (not actual VMS findings)

Functional/UI bug: assigned mechanic clicks Mark as Done on approved unconverted schedule; nothing opens after refresh, but keyboard works. Browser evidence identifies an overlay intercepting clicks. Propose removing interception while preserving the dialog. Accept when mouse/keyboard work, submission creates one For Verification record, wrong mechanic stays denied, and converted schedules link to tickets. This alone does not prove an API failure.

Role/process limitation: the only Custodian performed the repair, so independent verification is correctly denied. Under that rule this is a staffing limitation, not a broken guard. Recommend an eligible independent Custodian through an approved arrangement; surface a policy decision if unavailable, not an Admin impersonation shortcut.

UX improvement: an observed route needs eight clicks and re-entry of a vehicle known by the schedule. Propose prefill/next-action link with existing validation/approval intact. It is an improvement unless a requirement is violated. Do not invent time savings without measuring.

## Final report/handoff

Include:
1. Plain-language outcome, urgent findings/release blockers and unknowns. Pass counts are not security certification.
2. Findings index/details, deduplicating manifestations of one root issue.
3. Ledger: ID | module | scenario/transition | role/tenant | expected/source | actual | status | evidence/finding | tested build.
4. Module rollup with applicable/pass/fail/blocked/not-tested counts and justified exclusions. Distinguish sampling from full planned-case execution.
5. Role/transition coverage and controls demonstrated to work.
6. Requirement conflicts and exact decisions needed; optional improvements separately.
7. Prioritized actions with suggested owner/function, remedy, dependencies, estimated effort and acceptance/retest. Architectural proposals need evidence.
8. Browser limits and exact API/source/job/concurrency follow-ups: fixture/action/expected result.
9. Fixture/notification/cleanup ledger and next resume case. Preserve audit evidence instead of deleting it for a tidy environment.

On retest summarize verified fixes, remaining/partial issues, new findings and blockers. Stop at agreed scope/limits with a checkpoint; never promise an endless audit can establish zero possible defects.
