# Dashboard live integration review

Date: 14 October 2026. Scope: Dashboard implementation and its shared primitive/notification adaptations. Earlier UI prototype reports remain historical evidence.

## Stats

- Files Modified: 12
- Files Added: 13 source/test files
- Files Deleted: 0
- New lines: 7131
- Deleted lines: 841

Documentation, local font assets and proof images are excluded from source statistics.

## Result

Code review passed. No remaining confirmed technical issues detected in the reviewed implementation. Existing SSO role mapping and workflow/policy checks remain authoritative; no transaction mutation endpoints were added.

## Findings resolved before handoff

1. Selected/pending records outside Dashboard year or office lacked attention metadata. Shared serialization now includes authoritative per-record attention.
2. Notification refresh could update a count without pending records. Reload now includes pendingDocuments.
3. Missing selected records could show the first register record. Modal now shows unavailable state.
4. Handoff used an earlier workflow step as current after endorsement. Actual office ID and receipt state now label route correctly.
5. Queue changed background Dashboard filters. Separate validated queue filters/page/props now preserve them; regressions cover isolation and pagination.
6. Queue context changed before matching rows arrived. Use server filters for displayed rows, wait on office changes, disable stale actions, restore context on error.
7. Earlier background polls could overwrite queue responses. Poll excludes queue props and preserves URL.
8. Exit animation could change title/body to Help. Last rendered modal/notice content is retained until Radix unmounts it.
9. Queue purpose inherited nowrap. Fixed table layout and explicit wrapping remove horizontal overflow on desktop and phone.

Independent backend and frontend reviewers confirmed the final narrow queue and motion fixes. Root Browser verification and exact-source/container/served-asset readback are recorded in `.claude/reports/dashboard-live-integration-report.md`.
