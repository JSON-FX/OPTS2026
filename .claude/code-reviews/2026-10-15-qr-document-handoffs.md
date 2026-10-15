# QR document handoffs: independent technical review

Date: 2026-10-15 (Asia/Manila)

## Result

**Code review passed. No technical issues detected.**

This is a review of the QR handoff scope in `.claude/plans/qr-document-handoffs.md`. Unrelated existing Dashboard, document-page, numbering, SSO reconciliation and Storybook changes in the dirty working tree are excluded.

## Scope and statistics

- 21 implementation, integration, validation and probe files reviewed in full (hash inventory below).
- QR changes modify 12 existing files and add 9 files. No files are deleted by this feature.
- The repository-wide diff is not a valid measure of this feature's line count because it includes earlier uncommitted delivery work. The hash inventory binds this review to the actual source examined.
- Existing TransactionPolicy, EnsureSsoEligibility, workflow model, action model, shared controls and relevant request/controller conventions were inspected as dependencies.

## Checks

- Both endpoint requests require a bounded, case-sensitive matching scan. The global trimming replacement preserves only scan-field bytes; ordinary note trimming remains unchanged. Only trailing CR/LF are normalized, consistently with the React scan handler.
- The service serializes each handoff with a transaction row lock, refreshes the supplied model, clears its stale relations, reloads the user's office and applies the existing policy before validating the reference and mutating. A stale request cannot reuse a prior office, state or reference.
- Endorsement retains the first status transition, audit metadata, active SSO destination validation and existing out-of-workflow event. Receipt retains the latest endorsement metadata, receipt timestamp, current user, notification and receipt-only workflow advancement.
- The bulk endpoint requires a scan for every distinct requested document. Every service iteration independently enforces policy and the fresh reference. The unchecked bulk UI is removed; all app receive submission paths converge on the shared scanner workspace.
- GET pages remain behind authentication, processing roles, existing SSO middleware and individual policy checks. Real document, category, procurement, office and workflow presentation is shared; no fixtures or scanner simulator enter live pages.
- Client matching only unlocks the next step. It never performs a write. Review confirmation, processing guards, cancellation, scan again, mismatch messages, server validation retention, labels/focus and mobile task order were inspected. Parent owns the separate actual browser and visual acceptance checks.

## Validation performed independently

```text
php -d memory_limit=512M vendor/bin/phpunit --colors=never tests/Feature/DocumentQrHandoffTest.php
PASS: 36 tests, 236 assertions; four existing runtime deprecations.
```

```sh
docker run --rm --network dev-net --env-file .env \
  -e APP_ENV=testing -e DB_CONNECTION=mysql -e DB_HOST=mysql \
  --mount type=bind,src=/Users/jsonse/Documents/development/lgu-dev/opts2026,dst=/work,readonly \
  --entrypoint php opts2026:local /work/scripts/check-handoff-concurrency.php
```

PASS on real local MySQL: six races of eight workers, covering endorsement and receipt for PR, PO and VCH. Each race accepts one caller and rejects seven duplicate stale callers. Each action creates exactly one audit row; only receipt advances the workflow, once. Missing, wrong-case and mismatched receipt scans write nothing. Stale/current-reference mismatches and empty endorsement scans write nothing. A previously loaded office becoming inactive is rejected by the direct service policy check. Trailing scanner CR/LF remains accepted.

The probe clones only 17 empty table structures with a random prefix. It seeds synthetic values and no copied user rows. It logs exact physical names and drops only its explicitly created names in `finally`. Its query guard rejects unprefixed tables on each isolated connection. All 17 tables were removed after each successful run. No live rows, SSO network requests, real notification events or secrets were copied or changed. The runtime image has no Faker, so deterministic synthetic fixture inserts replace framework factories.

Physical QR scanner compatibility is not certified by these tests. The intended keyboard-wedge plus Enter input contract is tested in application code and requires parent browser verification. No new QR generation, camera scanning or physical hardware attestation is claimed.

## Final browser-discovered copy correction

Reviewed the final `OfficeWorkflow` label branch in `DocumentFrame.tsx`. After endorsement, an unreceived current workflow step now names its server-provided `awaiting_office`, so a source-office step no longer implies the paper awaits receipt there. The existing received, completed, fallback and upcoming labels retain their branches. No step selection, progress, authorization or mutation behavior changes. The correction passes technical review; no additional behavior test is needed for this text-only branch. Parent owns the final build and served-source verification.

## Reviewed source hashes

```text
3d3b0f66222dbd0a3dce603adeb6dd5060d515e91ee80a37257b8e759dce0ee3  app/Http/Middleware/TrimStrings.php
3a0a06ef7a5fad3f1838a7570ea22910fd39a17f225ed5a6fdaf775c5e3516df  app/Rules/ScannedDocumentReference.php
3fe50acdd1a9348df2c8ef767ac2d80a2620918dbc441638de212f0a166ccd49  app/Http/Requests/EndorseTransactionRequest.php
bb996eae0b85243678a90e6bee936e02959c238ceb0f6e9623b8d99595cbf1e9  app/Http/Requests/ReceiveTransactionRequest.php
dcb1eee9f8db90854e548bc2125a639a2ffa57409fbc45bb3256db4924dde342  app/Http/Requests/ReceiveBulkRequest.php
b3e6c5df53ab5da80e421270704e19e850cb5a2127984b987669e922566e55b2  app/Http/Controllers/TransactionEndorseController.php
77b0344b58a953f54f7fc7b1b04a8ef89e5ccb55b234db458e819f0426a2f7ed  app/Http/Controllers/TransactionReceiveController.php
1cbd398dcc7f279661328e0f861f7cb9ddd41edfdbe9a182c9c6deef700f11ba  app/Services/EndorsementService.php
c78153a07448f509741f71d3e84e0798fa864701705325fa887b093786078976  app/Services/TransactionHandoffPresenter.php
a57ef8e0bd4ac72b5698c2e75f6f825f8e5b13bc3e082ada28672f2478ffe7e4  bootstrap/app.php
fb18a51bf2c8f3382429c5f369cfe66c79902fde7b489c89bcd4e3747a15c299  routes/web.php
e6806536b1671b6fe4edbde2132b6f22567345bf5cb4d4f63ba919befc71cb57  resources/js/Components/Documents/HandoffWorkspace.tsx
487720768808472e50d24e96845d08f507b496d382c594bbfae4d92c2c3b9b7b  resources/js/Components/Documents/handoff-types.ts
04f62bae4b377ae4a390660c57cf7c20afbcd187726c122aba0afcfc009f251a  resources/js/Components/Documents/DocumentDetail.tsx
3507521a260fea7ac53d7e7a6440177e679666b6065dabe621d6298ad3d03f9c  resources/js/Components/Documents/DocumentFrame.tsx
52cf8d5787c15300fd273943c8ec21827eff798cb1e170bf7f61746c4229933b  resources/js/Pages/Transactions/Endorse.tsx
6fbd0b99b77c5ec042b9539d94751572ff91e42ec7fb15d6fedb00c5756172e6  resources/js/Pages/Transactions/Receive.tsx
8aab4c1049a2e4a1ac1f514f25cd937004dd57ae21bf02f2d28eafbc698e561f  resources/js/Pages/Transactions/Pending.tsx
7a74089ba6304f1c97de9378aad873c5c32f75b10add06a113520f3c740da542  resources/css/qr-handoff.css
e815157d019f361a7794b0d7cd4e63839cf8f9a33177562a4fc9a23f0a67bef8  tests/Feature/DocumentQrHandoffTest.php
f61080b024c8205e826ffb02a6ee963e0b007bb2801216eab4f1d563a5d835a0  scripts/check-handoff-concurrency.php
```
