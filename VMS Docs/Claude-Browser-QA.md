# Claude browser QA skill

Use this package to have Claude audit VMS as a system analyst, QA analyst and IT consultant. It covers complete multi-role workflows and explains findings with evidence, suggested solutions and retest criteria. Installing it does not execute tests.

## Install and start

1. Upload [vms-browser-qa.zip](vms-browser-qa.zip) through **Customize > Skills > + > Create skill > Upload a skill**, then enable it. Enable Code execution and file creation if requested. See [official installation instructions](https://support.claude.com/en/articles/12512180-use-skills-in-claude).
2. Use a browser-enabled session. [Claude in Chrome](https://support.claude.com/en/articles/12012173-get-started-with-claude-in-chrome) supports browser interaction; account/organization availability varies. The ZIP cannot grant browser access or make localhost reachable remotely.
3. Supply URL, environment and approved account access. Use dedicated local/staging data for write tests. Enter secrets through browser login; do not embed passwords in the skill/report.
4. Attach current Must-Haves.md, Documentation.md, System-Capabilities.md, ticket review and system audit when available. Browser-only Claude cannot automatically read your repository. Historical audit findings must be retested.
5. Paste the prompt below. If your browser surface cannot load custom skills, attach/paste [SKILL.md](skills/vms-browser-qa/SKILL.md), [test catalogue](skills/vms-browser-qa/references/test-catalogue.md) and [report format](skills/vms-browser-qa/references/report-format.md) into a browser-enabled session.

## Starter prompt

```text
Use the vms-browser-qa skill. Act as our system analyst, QA analyst and IT consultant.

VMS URL: [enter URL]. Environment: [local/staging/live].
Test-data authorization: [whether you may create/change dedicated QA records;
identify the dataset and forbidden operations].
Available identities: [Admin, Custodians, Maintenance Personnel, Super Admin,
dual-role and second-barangay test accounts; list any missing].
Use browser login without putting passwords in reports.

Inventory every module/action, build a coverage ledger, execute the catalogue
and add discovered features. Cover valid/invalid fields, complete workflows,
decline/undecline/corrections/approval, assignment, repair, independent verification,
shop rework, cancel/restore, schedules, reports, uploads, role/tenant boundaries,
recovery, desktop/mobile and light/dark mode where available.

Read attached requirements. Investigate discrepancies instead of assuming old
documents or old bugs still apply. Do not change code. For every bug, suspected
issue, role conflict, unintended feature or UI suggestion, give evidence,
expected/actual behavior, impact, proposed solution, acceptance and retest cases.
Report what works, what is blocked and what remains untested.

Continue independent cases when blocked; keep resumable checkpoints.
Do not claim unexecuted tests passed or certify zero bugs. Give a concise
summary here and a detailed report/case ledger if your tools can create files.
Ask only for missing access or scope necessary for the next operation.
```

Replace bracketed fields before use. Read-only discovery can begin without write permission; dependent mutation cases remain blocked until authorized.

## Deliverables

- Coverage with Pass/Fail/Blocked/Not tested/Not applicable.
- Reproduction steps, expected/actual, severity, confidence and redacted evidence.
- Separate defects, requirements conflicts, scope issues, staffing limits and improvements.
- Suggested fixes, acceptance criteria, priority and regression/retest plan.
- Fixture/cleanup ledger, browser limits and continuation checkpoint.

The [source skill](skills/vms-browser-qa/SKILL.md) and two references are bundled without credentials/account data. Rebuild the ZIP after editing its source folder. This package changes no application code or seeded records. Skill structure and archive packaging follow [Anthropic's custom skill guide](https://support.claude.com/en/articles/12512198-how-to-create-custom-skills).
