# Automatic document references

## Feature and accepted decisions

As processing staff, I want document references assigned incrementally so simultaneous users cannot create duplicate references. Owner confirmed yearly resets. PR is fixed automatic numbering with no manual override. PO and vouchers default to automatic, with an explicit manual full-reference override for office-issued numbers. Procurement already uses its database auto-increment ID; retain that identity.

Existing Casebook page designs remain proposals. Implement numbering in current app forms and mirror the behavior in Storybook proposals. No SSO roles, workflows, prerequisites, historical references, legacy imports or page redesign integration changes. No new dependencies/schema needed: reuse reference_sequences unique(category,year), transactions unique(reference_number), and current DB transactions.

## Context and approach

Laravel12/MySQL8, Inertia React18, PHPUnit SQLite fixtures and host PHP available. Branch codex/sso-directory-integration contains authorized uncommitted UI work; preserve it. ReferenceNumberService has an unused annual category generator and manual PR/PO builders. Store controllers currently build references before a procurement row lock; requests require manual components. Existing transactions uniqueness is the final authority. Fund and continuation retain existing formatting. Current formats PR-FUND-YYYY-MM-NNN and PO-YYYY-MM-NNN; new auto voucher VCH-YYYY-MM-NNN. One yearly sequence per category, across funds/months/continuations. Padding at least3; growth allowed. Read-only next-number estimate must not reserve a number; final assigned inside save transaction. Sequence allocation must consider existing references including soft-deleted/manual numeric references, counter max, and annual resets. Nonstandard external references stay valid and don't corrupt counters.

Required reads: app/Services/ReferenceNumberService.php; Store/Update requests and Controllers for PurchaseRequest/PurchaseOrder/Voucher; app/Rules/UniqueReferenceNumber.php; database/migrations/*reference_sequences* and *transactions*; respective React Create/Edit pages; tests/Feature/{PurchaseRequest,PurchaseOrder,Voucher}ControllerTest.php; tests/Unit/Services/ReferenceNumberServiceTest.php; docs and routes/permissions. Reference: official Laravel12 Query Builder lockForUpdate and database transactions documentation. Existing field validation, SSO roles and workflow assignment remain authoritative.

## Step-by-step tasks

1. UPDATE ReferenceNumberService with atomic annual document allocation and read-only next-number estimate. Initialize/upsert existing category/year row safely, lock before advancing, respect committed historical max including deleted, no frontend allocation authority. Preserve legacy methods compatibility. VALIDATE focused service PHPUnit tests, PHP syntax and pint. SATISFIES automatic uniqueness/year reset/historical safety.
2. UPDATE store requests/controllers: PR ignores client-provided sequence; fixed reference not rewritten on edit. PO/VCH explicit manual_reference boolean defaults false; full reference_number unique when manual; auto otherwise. Validate max lengths/format, handle constraint-race errors as actionable field validation. Generate inside existing procurement transaction/row guard with bounded deadlock retries. Send non-mutating numbering defaults (year,month,next_number) in create props. PO edit supports full manual reference while preserving existing values; no unnecessary regeneration on edit. VALIDATE focused controller/feature tests, including tampering, duplicates, roles, prerequisites, rollback. SATISFIES server integrity/manual override.
3. UPDATE current React create forms with automatic reference explanation/estimate and read-only sequence. PO/VCH add explicit manual override and full-reference field. PR Edit displays original immutable reference. Repair only fields necessary in existing PO Edit to submit supplier/contract price/current reference correctly; no layout overhaul. Keep existing components. VALIDATE npm run build, focused DOM/browser checks. SATISFIES usable creation/override/edit behavior.
4. UPDATE Storybook document proposals and meaningful stories for auto references, PR fixed numbering, external manual refs, duplicate override and annual simulation. Reuse shared controls. Procurements already auto ID; clarify only where useful. VALIDATE hub pnpm typecheck/build:all and Browser desktop/phone reference interactions.
5. ADD regression tests and run validation/review. Focus: yearly category independence, monthly/fund continuity, bootstrap high existing suffix, deleted refs, simultaneous allocation including first-row race, manual duplicates, PR tampering/immutability, stale suggestions, validation rollback, roles/prerequisites. Validate actual MySQL contention in isolated test tables/schema; never reset local app database or create unlabeled real records. Full existing suite may contain baseline legacy failures: distinguish newly changed expectations from unrelated failures, fix only task regressions. Browser creates preview records only or uses unsaved forms for live QA. Save evidence/report/source sync. No commit/push requested.

## Validation commands

- php artisan test --filter='ReferenceNumber|AutomaticDocumentReference' (SQLite testing env)
- php artisan test tests/Feature/PurchaseRequestControllerTest.php tests/Feature/PurchaseOrderControllerTest.php tests/Feature/VoucherControllerTest.php
- vendor/bin/pint --test <changed PHP paths>; npm run build; git diff --check
- composer test (classify pre-existing failures separately)
- Hub pnpm typecheck; pnpm build:all; publish only OPTS static artifacts
- Browser primary @Browser/CUA; preserve user-owned tabs/drafts; verify auto vs manual, immutable PR, duplicate error, successful local fixture creation if needed, responsive labels and focus.

## Acceptance

Yearly automatic numbers persist on backend and cannot duplicate under concurrent auto saves. Existing IDs/references unchanged. PR manual sequence cannot be forced or rewritten through update. PO/VCH support external references with uniqueness including deleted records. Suggestions reserve nothing and are explicitly provisional. Failed save does not leak a sequence increment. Default mode automatic. Roles/workflow/prerequisites preserved. App build, meaningful tests, MySQL probe and Storybook evidence recorded.

## Assumptions and risks

Year/month components retain established date input behavior for historical/continuation records; fixed PR means its generated sequence/reference is not user-overridable. Each category has its own yearly counter. Historical data may include nonnumeric external patterns; only recognized numeric suffixes count toward automatic high-water mark. Gaps may exist from deleted/cancelled records or failed independent reservations; uniqueness takes precedence over gaplessness. No mutation of historical references. No remaining product clarification required after yearly reset answer.
