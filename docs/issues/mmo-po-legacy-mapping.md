# MMO-PO legacy mapping conflict

## Request and root cause

On 2026-10-15 the owner requested removal of the legacy MMO-PO mapping. SSO is the authority for office identity. This supersedes the earlier instruction to leave the unmapped office until the directory was updated.

Live read-only checks against local OPTS and the SSO API found:

| Identity | Local ID | SSO ID | Abbreviation | Name |
| --- | --- | --- | --- | --- |
| Legacy | 6 | null | MMO-PO | MMO - Procurement Office |
| Canonical, stale snapshot | 80 | 54 | MMO-PS-PURCH | Municipal Mayor's Office - Purchasing Section |
| Fresh SSO record | — | 54 | MMO-PO | Municipal Mayor's Office - Purchasing Section |

`SSOOfficeService::upsert` resolves SSO identity and checks all matching local names/abbreviations, including soft-deleted rows. Its refusal is correct: local 6 and local 80 both match fresh SSO 54. Soft deletion alone would retain the conflicting abbreviation and fail again.

## Exact impact before repair

The actual MySQL foreign-key schema contains six references to `offices`. Counts include deleted rows.

| Table and column | References to local 6 |
| --- | ---: |
| procurements.end_user_id | 1 |
| transaction_actions.from_office_id | 4 |
| transaction_actions.to_office_id | 4 |
| transactions.current_office_id | 0 |
| users.office_id | 2 |
| workflow_steps.office_id | 2 |

The workflow uniqueness constraint is `(workflow_id, step_order)`, not office ID. Updating the office leaves workflow IDs, step IDs, order, transaction current step, action history, statuses, creator identities, and user-role links intact.

`migration_imports` and `migration_records` are empty. No office 6 keys were found in the current session payload. Queued/failed jobs are empty. Notifications contain no office 6 identity fields; four historical messages mention MMO-PO as text. Preserve those messages because the label remains valid for SSO 54 and their audit text is historical. No office-specific database cache keys were found. Repository configuration contains no runtime hardcoded MMO-PO alias.

## Prepared operation; execution belongs to parent task

`scripts/repair-mmo-po-office.php` defaults to a read-only dry run. It is restricted to local MySQL and asserts:

- The reviewed six foreign-key columns still match the actual schema.
- Legacy ID 6 remains unmapped and has the expected identity.
- Canonical ID 80 remains linked to SSO 54.
- A fresh authenticated SSO API response still identifies active MMO-PO as SSO 54.
- All six impacted counts still match this snapshot.
- No third office conflicts with either canonical or archive labels.

Before applying, place local OPTS in maintenance mode and make a private full database backup. Keep SSO and its portal running. Ensure no local queue worker is writing office-related data. The parent owns this backup and execution step.

Run the script from the application root in the OPTS container, without `--apply`, and inspect the count report. For application, pass `--apply --backup=/absolute/private/existing-directory/mmo-po-reference-backup.json`. Use a path in the ignored `storage/app/private` tree and copy the backup outside the container immediately. The script refuses to overwrite an existing backup and creates it with mode 0600. It stores only original office rows and affected row IDs per reference column, with no user passwords, tokens, names, or emails.

Inside one transaction the script locks offices 6/80 and all affected records. It writes and flushes the private backup before changing anything. It moves every reviewed reference from 6 to 80 using query-builder updates, without rewriting timestamps or audit fields. It renames legacy 6 to `ARCHIVED-LOCAL-6` / `Archived local office #6 (replaced by SSO #54)`, marks it inactive, and soft deletes it. It updates canonical 80 with the fresh SSO identity. Before commit it verifies zero references to 6 in every actual foreign key. Any assertion fails the transaction.

After commit, invoke the existing `SSOOfficeService::sync()` to refresh the complete directory. Do not add generic auto-merge behavior to that service. Rerunning the script after a completed repair reports `already_applied` without changes.

## Reversal

Use the private JSON snapshot under maintenance mode. Lock offices 6/80 and every recorded reference row. First restore canonical 80's original name/abbreviation to free MMO-PO. Restore office 6's original row, including its active/deleted state. For each recorded `(table, column, IDs)` entry, verify those IDs still point to 80 and update only that column to 6. Restore the remaining original office 80 fields from the snapshot. Verify original counts and commit. Do not broadly change every reference to 80: some references may have belonged to the canonical office before this operation.

If any recorded row or identity has changed after repair, stop and assess those newer writes before reversal. The full database backup is for disaster recovery; a broad restore would also revert unrelated work.

## Status

Investigation and guarded script prepared. No live repair executed by this subtask. Parent will record the backup, apply result, full SSO sync, and readback.


## Execution and readback — 15 October 2026

Owner explicitly authorized retiring the legacy office after initially deferring the repair. Parent placed only local OPTS into maintenance mode, confirmed no forced queue worker, and saved a full MySQL backup under the private host backup directory. The guarded apply created its selective JSON snapshot with mode0600 before changing records.

Apply moved exactly1 procurement office link,8 action office links,2 user office links and2 workflow step office links to local80/SSO54. No transaction current-office links required changes. It retained all document references, statuses, workflow/step IDs, user-role assignments and audit text. Legacy6 is inactive and soft deleted under a nonconflicting archive identity. The SSO identity is canonical.

Full directory synchronization then succeeded:85 active SSO offices. An immediate dry run returned `already_applied`, with zero references to6 across all six actual foreign keys. OPTS returned to service; Browser opened Procurements and real document pages successfully. No SSO server or portal changes were made.
