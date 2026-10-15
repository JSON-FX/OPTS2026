# QR endorsement and receipt acceptance

15 October 2026. The approved Casebook QR proposal is integrated into the local OPTS application for purchase requests (PR), purchase orders (PO) and vouchers (VCH). Mandatory reference checking protects endorsement and receipt. Delivery is local only; no commit, push or production deployment is recorded.

## Authority and source mapping

The existing Hub `projects/opts/DESIGN.md` and `.impeccable/design.json` remain the visual authority. This is an ordinary extension of that system. The implementation plan is [qr-document-handoffs.md](../../../.claude/plans/qr-document-handoffs.md).

| Approved Hub source | Application source | Mapping |
| --- | --- | --- |
| `projects/opts/src/qr-handoff-prototype.tsx` | `resources/js/Components/Documents/HandoffWorkspace.tsx` | Shared receive/endorse workspace: expected reference, scan check, unlocked details, explicit confirmation Dialog and document context. |
| `projects/opts/src/qr-handoff.css` | `resources/css/qr-handoff.css` | Numbered tasks, flat ruled panels, scan states, office transfer, context route and task-first phone layout. Catalog CSS variables and slot selectors adapt to the app's existing Tailwind 3 controls and HSL variables. |
| Catalog Casebook shell and shared primitives | `resources/js/Layouts/DashboardLayout.tsx`, existing `Components/ui/` and `Documents/DocumentControls.tsx` | The authenticated shell supplies real navigation, notifications, account and office context. Existing official controls supply labels, fields, office choices and Dialog behavior. |
| Synthetic category and route fixtures | `app/Services/TransactionHandoffPresenter.php`, `Pages/Transactions/Endorse.tsx`, `Receive.tsx` | Real transaction, procurement, category links, workflow offices, active SSO offices and active action choices replace fixtures. |
| Catalog receive entry | `Documents/DocumentDetail.tsx`, `Pages/Transactions/Pending.tsx`, `routes/web.php` | Eligible document actions and Pending receipts link to `transactions.receive.create`; endorsement links to `transactions.endorse.create`. Their final POST routes retain the existing endpoint names. |
| Shared document route context | `Documents/DocumentFrame.tsx` | `awaiting_office` names the actual endorsement destination while the current workflow step remains unchanged until receipt. |

## Preserved system and justified adaptations

Source comparison confirms the incumbent blue/neutral Casebook palette, Source Sans 3, tabular references, white ruled surfaces, written status labels and Lucide icons. The app keeps its existing HSL token representation; QR success and warning colors alias existing OPTS tokens. Desktop context remains beside the task; at 700px and below it follows the task. The scan input remains 46px high with 16px text. Phone actions retain at least 44px targets. Reduced motion disables meaningful transitions.

The prototype simulator, downloadable demo QR labels and preview controls remain catalog references. They do not appear in the application. Live QR generation, printing and camera scanning are outside this delivery. The prototype's simulated completion is replaced by the real mutation and existing application redirect.

Real single sign-on (SSO) office and role eligibility remains authoritative. Endorsement preserves action defaults, action selection, notes, active destination choices and permitted out-of-workflow routing. An unexpected destination shows a written warning in the workspace and confirmation. Receipt retains the existing notification and audit behavior. Only receipt advances the workflow. Endorsement may name the destination as awaiting receipt without advancing the step.

## Scan and authorization contract

The scanner contract is keyboard input followed by Enter. The focused field accepts the decoded document reference; Enter runs the same check as **Check scan**. Readiness text describes field focus, not hardware detection. A physical scanner gun was unavailable, so hardware compatibility is not certified. Typed or pasted references can exercise this input contract.

Matching is case-sensitive and preserves spaces. Only trailing carriage return and line feed characters (CR/LF) are removed. Missing, wrong-case or different references cannot unlock confirmation or produce a successful handoff. The matching state merely unlocks details; it is not authorization or proof that physical scanning occurred.

The final server action requires the scanned reference independently. It re-reads and locks the transaction row, refreshes related state and office eligibility, checks the existing policy, then compares the fresh reference before writing atomically. A stale or duplicate confirmation cannot reuse earlier eligibility. The bulk application programming interface (API) still requires a reference map for every distinct selected transaction; each reference receives the same server checks. The interface uses one scan-and-receive action per document instead of the unchecked bulk shortcut.

**Scan again** clears the match and returns focus to the scan field. Dialog cancellation returns to review without saving. Server errors close confirmation and retain actionable error feedback; a scan error requires another match. Processing guards prevent duplicate submission.

## Verification and limits

This document records the parent delivery evidence and the independent reviews. The documentation pass inspected sources and existing evidence; it did not rerun Browser or application checks.

| Evidence | Result and boundary |
| --- | --- |
| Local Browser flow | Receipt followed by endorsement passed using the clearly labeled local QA fixture. Different-reference and wrong-case scans stayed blocked. Scan again, confirmation cancellation and the out-of-workflow office warning passed. |
| Responsive Browser checks | Widths 390px, 1440px and 1683px had no horizontal page overflow. The explicit confirmation Dialog was inspected on phone. |
| Application suite | 801 tests and 4,359 assertions passed. TypeScript, Vite build and changed-file Pint checks passed. |
| MySQL concurrency | 48 calls across six races covered endorsement and receipt for PR, PO and VCH. Each race accepted one call and rejected seven stale duplicates; one audit action was recorded and receipt advanced the step once. |
| Independent technical review | [Technical review](../../../.claude/code-reviews/2026-10-15-qr-document-handoffs.md) passed; its focused scan suite passed 36 tests and 236 assertions. |
| Independent finish review | [Finish review](finish-review.md) inspected the seven desktop/phone captures under `.impeccable/review/qr/` and returned `ship`. It certifies its stated visual/source scope; saved mutations belong to the parent Browser evidence. |
| Hardware | No physical scanner gun was available. Keyboard input plus Enter is the verified contract. |
| Deployment | Only local OPTS was rebuilt. Source/image readback and final fixture cleanup are tracked by the parent delivery report. No production verification or release is implied. |

The Browser fixture is `QA-QR-PR-20261015-HANDOFF`, procurement 9, transaction 9 and PR 5. It was clearly labeled and backed up privately. Browser document writes were confined to that fixture; existing documents were not changed. The fixture was soft archived after final readback. Its audit history remains, and its three test notifications were marked read. See [local readback](local-qa-readback.json) and [source sync](source-sync.md).

## Documentation boundary

The Hub retains all 15 QR stories and synthetic sample labels as proposal references. They demonstrate states rather than authenticating users or writing live records. [Hub integration notes](../../../../../storybook-hub/projects/opts/docs/qr-handoffs-integration.md) record the same application boundary.

The existing DESIGN and sidecar were preserved. Their older metadata and inherited overlay timing drift were not repaired or promoted into new rules. This pass changes documentation only; it adds no global design tokens, Hub runtime changes or stories.
