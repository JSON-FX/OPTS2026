# Implementation Report — automatic document references

Plan: `.claude/plans/automatic-document-references.md`
Branch: `codex/sso-directory-integration`
Status: COMPLETE (local app and catalog; no commit/push requested)

## Delivered

- Procurement keeps its existing database-generated primary ID.
- PR uses automatic yearly numbering with fixed references after creation. Client-supplied sequence/reference fields do not control allocation or edits.
- PO and vouchers default automatic; explicit office-issued full-reference override remains available. PO/VCH editing preserves references unless changed manually.
- Existing PR/PO formats retained; automatic voucher uses `VCH-YYYY-MM-NNN`. Each category restarts yearly; months, fund types and continuation flags share its annual sequence.
- Non-reserving suggestions are clearly provisional. Final allocation happens inside the save transaction, after the procurement guard. Existing references and deleted records remain reserved.
- Recognizable manual references, including lowercase or another category's prefix, lock and advance the counter identified by that prefix. Arbitrary external office patterns remain accepted and unique.
- Reused existing sequence table, unique constraints, roles, prerequisites, SSO mappings and workflow services. No migrations, renumbering, live record creation or SSO changes.
- Storybook mirrors automatic/fixed/manual behavior with two additional manual-override stories. New page redesigns remain prototypes.

## Validation

- Final full PHPUnit: **751 tests, 3,840 assertions, zero failures**. Existing PHP 8.5/PHPUnit deprecations remain (4 runtime and37 PHPUnit deprecations). Ran `php -d memory_limit=512M vendor/bin/phpunit --colors=never`; the artisan wrapper's child process otherwise retained128MiB and exhausted memory.
- Focused backend checks:103 tests,462 assertions; formatting passed.
- Real MySQL probe:8 workers saved200 unique automatic references through first-use/steady-state contention. Another51 mixed cross-prefix manual/automatic saves succeeded. Also verified annual reset, month continuity, category independence, manual high-water mark, deleted historical references, and rollback. Only randomly prefixed probe tables were created and removed; app tables untouched.
- App `npm run build`: TypeScript + Vite passed. Docker build/recreate healthy; only OPTS service recreated.
- Hub full workspace typecheck and all5 catalog builds passed. Targeted final OPTS rebuild after story-label clarification passed. Eleven runnable prototype numbering checks passed.
- Browser app: PO automatic suggestion003 read-only; full office-issued override unlocks; alternate-year hint assigned on save; existing PR008 edit reference read-only; no saves performed and no console errors.
- Browser prototype: PR022 across month changes, next-year001, readonly PR edit, automatic voucher001, manual duplicate rejection and external voucher save; phone390px no horizontal overflow. Browser preview saves were local fixtures only.
- Browser prototype verification and source-sync evidence: `docs/design/reference-numbering/` and Hub review record.

## Review findings resolved

1. Real MySQL INSERT IGNORE shared-lock upgrades deadlocked under burst. Replaced sequence initialization with no-op upsert, which takes the exclusive duplicate-key update lock before SELECT FOR UPDATE. Probe then passed.
2. Manual PO using PR prefix could permanently block auto PR. Historical scan now reconciles recognizable references globally; manual saves lock the actual prefix counter. Fresh review reports no remaining actionable findings.

Reference: [MySQL8 locking documentation](https://dev.mysql.com/doc/refman/8.0/en/innodb-locks-set.html).

## Deviations

Existing PO Edit lacked actual supplier/contract-price submission. Replaced that invalid legacy form with its current validated fields so external-reference editing works. This is a form repair, not the deferred page redesign.

## Limits

No production release, commit or push. Automatic numbering is unique, not gapless; cancelled/deleted records retain their references. Existing unsigned sequence limit is enforced without wrapping. SSO authorization/workflow eligibility remains enforced by existing app services.
