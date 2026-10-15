# Feature: QR endorsement and receipt

## User story and scope
As an authorized office user, I want to scan a paper document's reference before endorsing or receiving it so each handoff is tied to the correct document. The owner requests direct application integration of the approved QR handoff prototype while retaining SSO office and workflow rules.

The prototype and existing business rules settle the implementation. No blocking product question remains. A scanner gun acts as a keyboard with an Enter suffix. The browser can accept its exact decoded reference; it does not attest physical hardware. Existing paper QR labels encode the reference. Printing/QR generation and camera scanning are outside this request.

## Authority and context
- Hub projects/opts/src/qr-handoff-prototype.tsx and qr-handoff.css; approved Casebook DESIGN.md.
- app/Services/EndorsementService.php: current office/status/receipt/final-step eligibility, endorsement and receipt side effects, step advancement on receipt, out-of-workflow routing.
- app/Policies/TransactionPolicy.php and EnsureSsoEligibility middleware: existing SSO and permission boundaries.
- TransactionEndorseController, TransactionReceiveController and corresponding requests: three current mutation endpoints, including bulk.
- resources/js/Components/Documents/DocumentDetail.tsx, Transactions/Endorse.tsx and Pending.tsx: all direct mutation UI entrypoints. Dashboard and Procurements navigate to these routes.
- Prior document page plan/report: shared Casebook primitives, context components and source sync.

## Acceptance criteria
1. Endorse and receive confirmations require a nonempty scanned_reference that matches the current document exactly, case-sensitive, after removing only trailing CR/LF scanner terminators. Missing/mismatched scans never mutate a document or audit history.
2. Both final actions re-read and lock the transaction, recheck existing policy/SSO office eligibility and reference, then perform the original side effects atomically. Concurrent duplicate actions cannot create duplicate successful handoffs.
3. The existing bulk endpoint requires a matching reference for every distinct selected document. The app's unchecked bulk shortcut is removed; individual scan-and-receive links replace it.
4. Shared real-data full page UI follows scan, matching reference, unlocked details, explicit review dialog, submit/success. Live UI has no simulator, demo fixture or hardware-connected claims.
5. Preserve active SSO destination options, permitted out-of-workflow endorsement warnings, action-taken selection/default, notes, current and next offices, receipt-driven step advancement, existing notifications and category links.
6. Support Enter scanner input, clear mismatch/retry/server errors, scan again, accessible labels/focus, desktop/phone layout and reduced motion. Context follows the primary task on small screens.
7. Existing PR/PO/voucher details, Dashboard and Pending links enter the protected scan routes. No remaining app receive POST skips the shared page.
8. Framework tests, app build, formatting, focused concurrency verification, browser desktop/mobile and independent review pass. Publish only local OPTS; no commit, push or production release.

## Implementation decisions
No new scan proof token, session store or schema is required. Local matching uses the authorized server page's exact reference to unlock the confirmation UI. The final POST independently requires and validates the scanned reference against the fresh locked record; client matching alone cannot authorize an action. Existing SSO middleware remains authoritative for identity/office/role.

Preserve exact scan bytes through the trimming middleware for only scanned_reference and scanned_references.*. All other fields retain existing trimming. Normalize scanner terminators consistently on client/server. Existing input max length remains bounded.

Use a shared handoff presenter for real document, source/destination, procurement and route props consumed by both GET pages. Keep the current endpoint names for confirmation; add transactions.receive.create for the new receive page. Pending retains selected-year and current-office query scope.

## Ordered tasks and ownership
1. Backend worker owns scan normalization/validation, Endorse/Receive/Bulk requests, the minimal trim middleware override, protected endorse/receive service mutations, shared presenter, controllers/routes and relevant tests. Add mandatory scan service arguments; update every caller explicitly. Existing unrelated service actions remain untouched.
2. Frontend worker owns shared HandoffWorkspace/types/CSS, Endorse and new Receive adapters, Pending single-document links and removal of unchecked selection controls, and category detail receive links. Reuse official existing shadcn primitives and Casebook layout. No app fixtures or demo QR panel.
3. Parent records source authority, performs fresh technical review, validates tests/build/concurrency, publishes only local OPTS, then inspects representative desktop/phone and error interactions. Independent technical/finish review is required.
4. Parent updates the catalog implementation boundary and source sync documentation; preserve the existing proposal and demo helper states as reference material.

Tasks1 and2 are independent after the shared prop contract is agreed. Parent validation follows their final source freeze.

## Test strategy and commands
- Missing, empty, wrong-case, leading/trailing spaces, excessive length, non-string and mismatched references on each action; CR/LF accepted only as scanner terminators.
- All PR/PO/VCH categories; successful endorsement/receipt side effects and action metadata, wrong office, Viewer, inactive/unmapped SSO office, terminal/unreceived/final-step states.
- Duplicate/stale confirmations under a lock; direct service calls cannot omit scanning. Bulk missing/incomplete/mismatched mappings, duplicate IDs and forbidden documents cannot bypass scanning or policy.
- php -d memory_limit=512M vendor/bin/phpunit --colors=never
- npm run build
- vendor/bin/pint --test <changed PHP files>
- Browser: scanner input plus Enter, reference mismatch, confirmation cancellation, real route/action selection, error retention, scan again, receipt success and endorsement success using clearly labeled local QA data only when needed; desktop1440, reported1683 and phone390 widths.
- Source hashes compared with the rebuilt local image. If Hub source/stories change: pnpm typecheck and pnpm build:all, then publish only OPTS.

## Delivery constraints
Keep the extensive existing working tree intact. Do not alter SSO authority, roles, office mapping, numbering, existing document references or unrelated apps. No automatic bulk acceptance, camera permission prompt, simulated scanner control in live pages or new UI framework.
