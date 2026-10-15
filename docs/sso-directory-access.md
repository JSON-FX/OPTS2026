# SSO directory and OPTS access

SSO is the source of operational offices, employee identity, office assignment, designation, and OPTS application grants. OPTS keeps local records to preserve procurement foreign keys and historical actor attribution. Local user/office editing and seeded operational accounts are no longer supported.

## Roles

| SSO role for OPTS | OPTS access | Capabilities |
| --- | --- | --- |
| guest | Viewer | Dashboard, procurement/transaction details, timelines, and activity. No procurement mutations, endorsement, receipt, completion, or administration. Use this grant for a Mayor who needs overview access. |
| standard | Endorser | Viewer access plus procurement/document management. An active SSO office is required. Endorse, receive, and complete only when the transaction is at that office. |
| administrator | Administrator | Configuration and reference administration. Business processing still requires an active assigned office; office handoffs require the matching office. |
| super_administrator | Administrator | Same OPTS application capabilities as Administrator. Central SSO privileges are not exposed through OPTS. |

Job title never determines permission. Grants and office assignments are changed in SSO. Unknown roles, inactive/deleted employees, unfinished password setup, missing grants, and revoked sessions cannot establish access. Current authorization and directory eligibility are checked on each authenticated request before local role checks, including broadcast authorization. Logout remains available during an SSO outage.

## Information displayed

Administrators can search the live application-scoped directory. It contains UUID, full name, initials, SSO role, mapped OPTS role, office abbreviation/name, and designation. SSO does not provide other users' email, username, private profile, or other applications' grants. Ordinary users see their own profile and the actor names needed for business history. The directory is never used as a cached permission list.

Office dropdowns display abbreviations. Transaction timelines retain full office names. Historical records retain their local office/user keys; name labels reflect the synchronized reference record. Missing or inactive offices remain available in history and the admin mapping view but cannot be selected for new assignments.

## Office synchronization

`php artisan opts:sync-sso-offices` reads all active office pages and resolves already-mapped inactive references. A successful complete read is applied transactionally. Incomplete/failed reads leave the prior snapshot untouched. `sso_id` is the stable external identifier; local numeric IDs are never replaced with SSO numeric IDs.

On the first sync, a unique exact abbreviation or name match can attach an existing local row to an SSO identity. Conflicting matches fail without merging records. Later updates use the external ID. Unmatched legacy offices are marked inactive and retain their history. Numeric SSO IDs must never be copied between independent environments.

Office metadata is refreshed on authenticated reads after at most one minute, and immediately before business mutations and workflow assignments. Preference updates, notification actions, and socket subscriptions use the same one-minute metadata interval as reads. Laravel's scheduler also runs the sync every minute; the local Docker image runs `schedule:work`. Existing deployments must run their normal Laravel scheduler. An unavailable SSO service blocks protected operations rather than granting access from stale user eligibility.

MMO-PO and MMO-PSMD were absent from the current SSO list during implementation. They are not aliased to similarly named offices. Add the intended records in SSO; synchronization then makes them available. Existing workflows with missing/inactive offices are unavailable for new assignments. The repeat MVP uses separately labeled demo workflows containing active SSO offices.

## Endpoint contract

- `POST /api/v1/sso/exchange`: exchange the login code.
- `POST /api/v1/sso/authorize`: current session/application access and identity.
- `GET /api/v1/sso/directory` and `/{uuid}`: current eligible people and role/office/designation.
- `GET /api/v1/sso/offices` and `/{id}`: active choices and historical references.
- `POST /api/v1/auth/logout`: terminate the SSO session.

Directory requests use client credentials only on the OPTS server. The server fixes `roles[]` to the four supported roles; browser parameters cannot widen application scope. Position details already arrive with each user, so separate position selectors are not required.

## Deployment configuration

Set the OPTS consumer application in SSO to **100 requests per minute** in every environment (Portal → Applications → Configure → Requests per minute). This is the application's `rate_limit_per_minute`, not an OPTS environment variable or a global limit for other SSO consumers. Confirm the setting when registering or updating the consumer during deployment. The local application was updated to 100 on 2026-10-14; deployment environments must receive the same setting when this integration is deployed.

The limit is shared by all users of this consumer. Fresh session and directory eligibility checks remain mandatory; throttling fails closed and does not enable a cached permission fallback. Capacity should be checked during the pilot.

## Request efficiency and failure handling

Browser eligibility and widget launch reuse a successful authorization response only within the same inbound request and exact token/client context. A new request, replacement token or changed client credentials always requires a new check. Current directory identity, role and office enforcement remain separate and fresh. No successful grants are placed in a cross-request cache.

Temporary authorization/directory failures return sanitized 503 responses with `Cache-Control: no-store` and bounded numeric `Retry-After`. Numeric and standard HTTP-date hints are normalized to 1–300 seconds; absent or malformed hints use 2 seconds. A shared upstream/client cooldown stores only a failure deadline. Requests during that deadline remain unavailable without contacting SSO. Confirmed 401/403 denials do not start a client-wide cooldown, and cooldown expiry performs fresh verification. One-use codes, launch requests and signed introspection are never automatically replayed.

Signed widget introspection excludes the complete web middleware group. It verifies the persisted encrypted host session directly and must neither create browser sessions nor return browser cookies. Launch retains normal host authentication and CSRF enforcement.

This correction introduces no limit, registration, grant, office-mapping or activation changes. Deploying it does not enable the widget or its host/key, and does not establish 300–500-user capacity. Full-office reads retain their one-minute freshness rule, and business/workflow writes still require a fresh snapshot. Coordination of forced/CLI snapshot writers and optional directory protocol optimizations are separate follow-ups.

## Testing

Domain test fixtures use synthetic SSO IDs and disable only the external-session middleware. `SSOAuthenticationTest` enables it and tests real enforcement with fake HTTP responses. Directory/client/sync tests cover privacy, pagination, conflicting mappings, failed reads, inactive offices, and role changes. Browser MVP tests use local SSO demo accounts, with no real purchase or payment.
