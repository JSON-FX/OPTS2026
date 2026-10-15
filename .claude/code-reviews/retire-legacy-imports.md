# Legacy import retirement review

Date: 2026-10-14
Scope: import runtime/UI removal, current document schema coverage, purchase-request transaction uniqueness upgrade, chronological latest-action selection.

## Findings

No open findings remain after the correction below.

## Corrected finding

### Resolved P2: Preserve a foreign-key supporting index when rolling back the PR unique constraint

Location: `database/migrations/2026_10_14_140000_enforce_unique_purchase_request_transaction.php:29`

`down()` unconditionally drops its unique index without checking that another usable index supports `purchase_requests.transaction_id`. MySQL/InnoDB can discard the original automatically created foreign-key index after a new covering index is added. In that state, rollback fails with a required-foreign-key-index error. Ensure a replacement nonunique index exists before dropping the unique index, while preserving pre-existing indexes. Exercise the down/up cycle against MySQL with a disposable schema.

Correction reviewed: `down()` now checks for another index beginning with `transaction_id` and creates a replacement nonunique index when required. It then drops only its unique index. Added reversal/reapply/idempotency coverage preserves the original document. The parent is separately validating the migration cycle in a disposable MySQL database.

This engine-specific risk was verified against the [MySQL foreign-key constraints manual](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html). SQLite cannot establish the original InnoDB behavior; the additional MySQL validation addresses that engine-specific behavior.

## Checked behavior

- No runtime backend, frontend, config, or active-test references remain to the deleted import classes, routes, channels, or import types. Navigation tests intentionally retain retired URLs to check rejection.
- Historical database migration files remain intact. Legacy flag columns are preserved but are no longer accepted through model mass assignment or advertised in frontend types.
- The remaining `MigrationTestHelper` is a schema-migration fixture helper used by current table tests. Its three obsolete document builders had no remaining callers and were removed.
- Latest-action selection uses greatest `created_at`, then greatest ID for timestamp ties. Both lazy access and dashboard eager loading are covered.
- The PR upgrade skips an existing single-column unique constraint. Duplicate detection precedes DDL and preserves duplicate rows while refusing the upgrade. It does not silently delete or relink records.
- Current document tests verify each factory can persist, duplicate transaction links are rejected, and referenced transactions cannot be hard deleted.

## Validation

`php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Migrations/TypeSpecificTablesTest.php tests/Feature/Models/TransactionActionsIntegrationTest.php`

Initial result: 27 tests, 55 assertions passed. After the rollback correction, the schema suite passed again with 11 tests and 20 assertions. Four existing PHP 8.5 PDO constant deprecations were reported.

Review performed without application-code edits or live-database mutations.

Parent validation completed: the exact upgrade migration passed up/down/up and idempotent up on a disposable MySQL database. The scratch database was removed. Final full suite: 687 tests, 2775 assertions passed. Local upgrade applied with the unique PR transaction link verified.
