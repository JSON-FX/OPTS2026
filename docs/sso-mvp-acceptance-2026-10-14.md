# SSO directory integration and MVP acceptance

## Result

The local Browser/Computer acceptance flow passed on 2026-10-14. Procurement #3 has exactly one PR, one PO, and one voucher, created sequentially after completing the preceding document's workflow. All records are explicitly labeled local demo only. No real purchase, order, disbursement, or payment was made.

| Record | Reference | Workflow | Result |
| --- | --- | --- | --- |
| Procurement #3 | Local SSO MVP | PR → PO → voucher | Completed |
| PR #3 / transaction #5 | PR-GF-2026-10-003 | MBO → MMO → MMO-BAC | Completed, 3/3 steps, 5 actions |
| PO #2 / transaction #6 | PO-2026-10-002 | MMO-BAC → MTO | Completed, 2/2 steps, 3 actions |
| Voucher #2 / transaction #7 | VCH-SSO-2026-10-002 | MBO → MACCO → MMO → MTO | Completed, 4/4 steps, 7 actions |

The three separate LOCAL MVP SSO workflows (#4–6) were created through the administrator UI before creating procurement #3. All steps use active SSO offices. Existing historical workflows and transactions were preserved.

## Browser and Computer checks

- Docker Desktop showed the running opts2026-local stack. The final container health check is healthy; web, queue, Reverb, and scheduler processes are running.
- The SSO user directory showed live application members, role mapping, office abbreviations, and position. There are no local user-edit controls.
- The Mayor demo has SSO guest → Viewer. It sees the dashboard and completed procurement, but no create/edit/delete/receive/endorse/complete controls. Direct `/procurements/create` returned 403.
- SSO standard accounts mvp.mbo, mvp.mmo, mvp.mmobac, mvp.macco, and mvp.mto signed in separately to receive and endorse each assigned step.
- Before each handoff, the transaction timeline showed full office names. Each endorsement appeared in Action History, followed by the receiving office's receipt. Final completion showed 100%.
- Duplicate creation attempts using PR-GF-2026-10-002, PO-2026-10-001, and VCH-DEMO-2026-10-001 were rejected visibly. The unique references above were then submitted on the same forms.
- Creation dropdowns showed abbreviations and excluded missing/inactive legacy offices. The invalid historical PR/PO workflows were excluded from new-document choices.
- A synthetic SSO office was added for synchronization testing. It was renamed from MVP-SYNC to MVP-SYNC2, which appeared automatically in OPTS. Its later deactivation removed it from creation choices. The reference remains inactive for evidence; no transaction used it.
- Server verification confirmed exactly three completed transactions and 15 actions attached to procurement #3.

Screenshots are outside the repository at `/Users/jsonse/Downloads/opts-sso-mvp-2026-10-14/`. The clearest final screenshot is `voucher-viewer.png`, showing the completed full-name timeline and the Mayor's restricted navigation.

## Automated validation

- Focused integration and affected business tests: **273 tests, 1080 assertions passed**.
- Full suite: **702 tests, 2819 assertions; 7 errors and 5 failures**. Remaining failures are outside the SSO integration:
  - EttsMapperTest: outdated office mapping expectations/configuration (1 error, 1 failure).
  - ReferenceChainResolverTest: missing Laravel configuration container in base PHPUnit tests (6 errors).
  - TypeSpecificTablesTest: expects removed legacy columns (3 failures).
  - TransactionActionsIntegrationTest: expects latest created_at while latestOfMany chooses highest ID (1 failure).
- PHP 8.5 and PHPUnit deprecation notices remain.
- Pint: 79 changed/new PHP files passed.
- TypeScript, production asset build, Docker image build, and `git diff --check` passed.
- Scoped technical review findings were fixed: queued payload disclosure, idle SSO notification recipient discovery, and historical-office update validation. Regression tests cover them.

Logs are `/tmp/opts-sso-scoped-final.log`, `/tmp/opts-sso-full-phpunit-final.log`, and `/tmp/opts-sso-pint-final.log` on the local machine. The full suite is not claimed green.

## Operations and deployment

SSO is authoritative for identities, roles, office assignments, and operational offices. Eligibility is checked live on each authenticated request. Office metadata refreshes within one minute on reads; business mutations/workflow assignments obtain a fresh snapshot. Historical keys remain stable. Local user/office seed data is no longer used for operational provisioning.

A transient 503 occurred during rapid testing under the original shared 60-request/minute SSO limit. The precise upstream status of that earlier failure was not logged. Safe endpoint/status logging was added. Redundant metadata requests were reduced while keeping live eligibility checks. The subsequent PO/voucher flow completed without recurrence.

The user selected **100 requests per minute** for this OPTS consumer in local and deployment environments. The local SSO application now has 100. The deployment requirement is documented in `sso-directory-access.md`. No deployment environment was accessed or changed during this local acceptance task.

MMO-PO and MMO-PSMD still need the intended active records in SSO. No aliases were guessed. A database backup was taken before local migration and is excluded from Git. No commit or push was performed.

## Follow-up: import retirement

After this acceptance run, the owner retired legacy imports. The ETTS import runtime and its tests were removed. Current PR/PO/voucher schema tests were updated, and latest transaction action ordering was corrected. The earlier full-suite failures above describe the pre-retirement run; see `.claude/reports/retire-legacy-imports-report.md` for the subsequent validation outcome.
