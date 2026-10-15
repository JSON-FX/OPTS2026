# SSO directory integration technical review

Scope: SSO client/reference synchronization, UUID-bound authentication and ongoing eligibility, active office assignment validation, additive migration, profile projection, notification/migration payload privacy, and related tests. Unrelated dirty configuration/documents were excluded. Stats describe the scoped checkout diff against HEAD, which includes earlier local changes in shared files.

## Stats

- Files Modified: 22
- Files Added: 9
- Files Deleted: 0
- New lines: 1158
- Deleted lines: 412

## Fixed findings

### Queued broadcasts could disclose information after revocation

Severity: high (security). A recipient passed eligibility before the Laravel `BroadcastNotificationCreated` event was queued. If the recipient's grant changed before delivery, an existing subscribed socket could still receive the precomputed transaction payload. Migration events also sent logs/counts/errors to long-lived shared subscriptions.

Fixed: notification wire and queued data now contain only `{refresh: true}`; database notification data remains intact. Migration events use one generic event name with the same payload. Both clients fetch details through authenticated HTTP requests, which run fresh authorization and eligibility checks. Tests enqueue actual `BroadcastEvent` jobs and serialize/unserialize them before checking exact public payloads.

### Idle SSO assignment changes missed notification recipients

Severity: medium. Local office/role queries excluded newly transferred or promoted recipients until they next visited OPTS.

Fixed: `SSOIdentityService::eligibleRecipients` obtains all live directory pages, filters current SSO role/external office ID, and resolves only already provisioned local users by UUID. It does not persist eligibility. A later-page failure returns no partial recipient list. Tests cover office transfer, promotion, pagination, an SSO-only unprovisioned account, and later-page outage.

## Resolved historical-office finding

Severity: medium (history / update regression).
File: `app/Http/Requests/UpdateProcurementRequest.php`, line 22.
Issue: an unchanged historical end-user office must pass the new active-Sso-office rule.
Reproduction: create a procurement and linked PR, then retire its end-user office through SSO synchronization. Submit a purpose/amount/date edit with its unchanged end_user_id. Validation rejects the office even though transactions lock that field and the controller ignores reassignment. The edit UI also does not offer inactive offices as new choices.
Suggested fix: preserve the unchanged historical association; require an active mapped SSO office only for actual reassignment on an unlocked procurement. Add a regression test for editing a record whose historical office is inactive/unmapped.
Status: resolved. Unchanged historical associations bypass reassignment validation. Regression passes with an inactive unmapped office and existing transaction.

## Review conclusions

- Office synchronization finishes network reads before its database transaction. Failed pagination does not deactivate a partial snapshot; ambiguous mappings roll back. IDs/foreign keys are preserved, and unmapped references become inactive rather than being deleted.
- The migration is additive, preserves numeric local identities, permits missing SSO email/type metadata, and adds unique external office IDs.
- Current identity synchronization validates the UUID against the session and replaces local roles. Matching email cannot adopt another identity. Outages fail closed; logout remains independent.
- The identity service clears cached office/role relations before middleware completes office synchronization. Subsequent `canProcessTransactions()` loads the current office state, so the full synchronization does not leave a stale active relation authorizing mutations.
- Public directory fields are explicitly projected. No consumer credentials reach the browser. PR creator fields were narrowed to business display identity.
- Selected workflows must match category and contain only active mapped offices. Existing reference uniqueness remains database backed. Parent owns final controller validation and broader business test review.

## Verification performed

- Auth and original notification suites: 68 tests, 256 assertions passed before broadcast/candidate changes.
- Broadcast/auth suite after payload changes: 36 tests, 176 assertions passed.
- Recipient + notification suites after live candidate discovery: 53 tests, 173 assertions passed.
- TypeScript check passed after both client refetch changes.
- Focused Pint and `git diff --check` passed.
- PHP 8.5 emitted existing deprecation notices; these were not failures.

Browser/Computer MVP acceptance and the final integrated build remain parent-owned. This report does not claim those checks passed.

## Runtime capacity observation

Read-only inspection of the deployed local SSO application found OPTS 2026 (application 13) configured for 60 requests per minute, shared by exchange, authorize, directory, and office routes. A safe authenticated offices probe returned HTTP 200 with `X-RateLimit-Limit: 60` and 15 remaining. Local SSO logs contained 56 consumer-route requests during one calendar minute and 44 during the next. The limiter uses a fixed 60-second window starting at first hit, so calendar-minute counts do not directly show the denial window.

This evidence establishes a capacity risk; it does not prove the earlier browser 503 was an upstream 429, because the prior OPTS client did not log upstream statuses. Added failure logging records only sanitized endpoint, status, and Retry-After. User identifiers, bodies, tokens, and client credentials are excluded. Focused SSO client suite passed after this logging addition (45 tests, 55 assertions).

SSO supports an explicit per-application `rate_limit_per_minute` setting through its authenticated application administration endpoint and Portal Configure form. No SSO limit, cache, or security setting was changed. Keep live authorization and directory checks per request. Redundant office metadata lookups can be reduced independently; even two live checks per OPTS request cap a 60-request shared allowance at fewer than 30 application reads per minute before background and mutation traffic. Multiuser rollout must size this configured allowance and preserve fail-closed behavior.

## Request reduction follow-up

Implemented metadata-only request reduction after the runtime capacity observation. Every authenticated request still calls live `/sso/authorize` and `/sso/directory/{uuid}`. Ordinary mapped-user reads now issue exactly two upstream identity calls instead of three, verified by HTTP fake call counts. Office metadata uses the existing maximum-one-minute refresh; unknown assignment IDs still trigger immediate office detail lookup. Business mutations and workflow assignment updates synchronize the complete office snapshot before identity mapping and permission checks. Notification reads, preference changes, and broadcast subscription authorization do not force a complete reference refresh.

New regressions verify that a freshly retired office denies a mutation even when the user previously loaded the office relation, and that current role/assignment checks still execute on reads. The focused auth/recipient/office/client suites passed: 81 tests, 238 assertions. Focused Pint and diff checks passed.

The parent reports the user explicitly selected a 100-request-per-minute allowance for local OPTS and future deployment configuration. The parent changed only local application 13 and documented the deployment requirement. This reviewer did not modify SSO settings or access production.
