# Implementation Report — Retire legacy imports

**Plan**: .claude/plans/retire-legacy-imports.md
**Branch**: codex/sso-directory-integration
**Status**: COMPLETE

## Summary

Retired the ETTS import feature following the owner's decision. Removed SQL upload, mapping, dry run, execution, reporting, rollback, clear-all, broadcast channels/events, backfill command, jobs, models, configuration, pages, types, and navigation. The historical implementation plan is marked retired. Laravel schema history and existing procurement records remain intact.

Updated current PR, PO, and voucher schema tests rather than retaining obsolete column expectations. Latest transaction activity now selects the newest timestamp with ID as a tie breaker, including eager loading used by the dashboard.

## Tests and verification

- Full PHPUnit suite: **687 tests, 2775 assertions passed**, zero failures/errors. Existing PHP/PHPUnit deprecation notices remain (4 PHP, 37 PHPUnit deprecations).
- Production asset build and TypeScript: passed.
- Scoped Pint: 82 changed/new surviving PHP files passed; subsequent migration/test changes also passed focused Pint.
- Diff whitespace check: passed.
- Current schema tests enforce unique document transaction links and restrict hard deletion of referenced transactions.
- Transaction activity regression covers out-of-order timestamps, tied timestamps, eager loading across multiple transactions, and empty history.
- Removed import URLs return 404, including the upload/execute/rollback/clear-all endpoints in feature tests. Route inventory contains zero legacy import routes.
- The exact PR uniqueness upgrade passed up/down/up and idempotent up in a disposable MySQL database; scratch data was removed afterward. Duplicate preflight refusal preserves existing rows in the regression test.
- Local Docker image rebuilt and container healthy. The unique PR transaction index is present in MySQL.
- Browser: administrator dashboard loads, Admin menu contains no Data Migration item, former `/admin/migration` shows 404, procurement #3 still has its three completed documents and 15 actions.
- Screenshots: /Users/jsonse/Downloads/opts-import-retirement-2026-10-14/.

Logs: /tmp/opts-retirement-full-final.log, /tmp/opts-retirement-build.log, /tmp/opts-retirement-pint-final.log, /tmp/opts-retirement-docker.log.

## Deviations and findings resolved

Updating the stale schema tests exposed an actual missing unique index on `purchase_requests.transaction_id` in both SQLite and local MySQL. The old migration called `unique()` on a foreign key definition. Added a new upgrade migration to enforce the existing one-document-per-transaction requirement. Existing duplicate links cause a clear preflight failure without deleting data. None existed locally. Rollback preserves an index needed by MySQL's foreign key, and drops only the index introduced by this upgrade.

The work continues on the existing feature branch because it contains the authorized uncommitted SSO integration. No commit, push, or production deployment was requested or performed. A private local database backup was taken before the upgrade. Historical import tables remain inert; retirement did not drop user data or rewrite applied schema migrations.

## Review

.claude/code-reviews/retire-legacy-imports.md records the scoped review. The MySQL rollback-index finding is resolved. No open findings remain.
