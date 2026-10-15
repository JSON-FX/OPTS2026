# OPTS Connect widget: production preparation

## Current state

The production application at https://opts.lguquezon.local runs approved release e83ece8902c26ee903d0bc890d42c8096125f54a. Its SSO sign-in, Administrator/MMO-MISS mapping and procurement office searches passed browser acceptance on 15 October 2026.

This branch prepares the existing Connect host adapter against that release. It is not committed, published, deployed or activated by this task. The widget defaults to disabled. No Connect production registration or secret has been provisioned by this work. Connect production readiness and 300–500 concurrent-employee capacity remain unverified. Optional staging releases are deferred at the owner's request.

## Ownership and exact contract

OPTS owns its authenticated session, SSO office/role enforcement, connector configuration, launcher and introspection endpoint. Connect owns its separate employee grant/session, host/key registration, frame ancestors, messaging, files, sockets and capacity acceptance. A host launch never grants Connect access by itself.

The implementation reuses the local adapter and Connect LC-024 contract. Keep the `CONNECT_PILOT_*` namespace and `/connect/pilot/*` routes for compatibility in all environments.

| Setting | Production | Staging reference |
| --- | --- | --- |
| OPTS origin | https://opts.lguquezon.local | https://staging.opts.lguquezon.local |
| Connect origin | https://connect.lguquezon.local | https://staging.connect.lguquezon.local |
| Introspection | https://opts.lguquezon.local/connect/pilot/introspect | https://staging.opts.lguquezon.local/connect/pilot/introspect |
| Host ID, proposed | opts-production | opts-staging |
| Signing key ID, proposed | opts-production-v1 | opts-staging-v1 |
| Enabled | false | false |

Host/key IDs require confirmation during provisioning. Environment examples contain no secrets. The production example is `deploy/connect-widget/production.env.example`; merge its connector settings into the existing private runtime environment after review, rather than replacing the complete runtime file. The staging example is reference material and does not authorize a staging deployment.

### Request and response boundaries

1. The authenticated browser POSTs to its same-origin `/connect/pilot/launch` with CSRF protection. It provides no UUID, host session ID, signing key or SSO bearer.
2. OPTS checks the active local user and current host SSO grant. It persists a random 32-byte binding in its encrypted database session and a shared database/Redis cache record. The Laravel session ID and bearer remain server-side.
3. OPTS POSTs exactly `employee_uuid`, `host_session_ref` and `parent_origin` to Connect `/api/widget/host-launches`. HMAC-SHA256 signs the method, exact path, timestamp, fresh nonce and raw-body SHA256. The five `X-Connect-*` headers use the dedicated host key. HTTPS verification is required; redirects are disabled and requests have bounded timeouts.
4. Only a canonical opaque 30-second `launch_ref` and expiry return to the browser. Connect requires its own SSO identity and matches the employee UUID before consumption.
5. Connect signs server POSTs to the exact registered introspection path. OPTS checks signature, 30-second timestamp, nonce replay, durable revocation, persisted encrypted session, UUID, active user and current SSO grant. A successful response contains only `active`, `host_id`, `employee_uuid` and the binding digest.
6. Logout, confirmed eligibility denial and identity replacement revoke the host binding before local session invalidation/replacement. Temporary outages fail closed without permanently revoking a valid grant. Connect's independent web session remains governed by Connect.

The introspection route alone skips browser CSRF and host eligibility middleware because its server signature/session proof supplies authorization. Browser launch retains both normal host authentication and CSRF. Disabled endpoints refuse access; disabled pages receive a null widget prop and make no Connect asset/request calls.

### Pinned host assets

`public/js/lgu-connect-widget.js` is the exact canonical Connect loader, not a new implementation. Manifest: `deploy/connect-widget/loader-manifest.json`.

- Source repository: JSON-FX/lgu-communication, base 5c7ef4a.
- Source path: frontend/public/lgu-connect-widget.js.
- SHA256: `0a9ddcdf1dcda9a46c66712e91b5443678ddb1c5911d78038bc9b51366f53c81`.
- Event protocol: `lgu-connect.widget.*.v1`, strict event origin and iframe source.
- Embed path: `/embed`.

The Inertia adapter preserves the widget/draft across ordinary navigation and destroys it on logout, guest pages, identity or configuration changes. It publishes only a safe unread badge. A stale script callback cannot mount the previous identity. The loader and its signing/postMessage contract must be reviewed together when updated. Compatible iframe UI updates remain owned by Connect.

## Production preparation and release sequence

### 1. Disabled registration and private handoff

The Connect owner provisions a distinct production host and encrypted signing-key row with **both enabled flags false**. Register the exact parent origin and introspection URL above. Preserve existing frame ancestors and add only the exact approved OPTS production origin when the Connect deployment is ready. Wildcard origins are not valid.

Generate a dedicated random connector secret of at least 32 bytes, distinct from either application's SSO client secret. `ConnectPilotHost::configured()` rejects a connector secret equal to OPTS's SSO client secret. Retain each environment's existing confidential SSO client and grants.

Future private handoff: `/etc/mis-deployment/connect/production/opts-widget.env`, proposed by the Connect owner. Root owns the file (600) inside private directories (700). This path has not been created by this preparation. Share only its location and registration identifiers in release coordination; keep its contents out of chat, Git, process arguments, browser props and logs.

Review and merge these values into `/srv/mis-deployment/opts/production/runtime.env` through the approved private operator process:

```dotenv
CONNECT_PILOT_ENABLED=false
CONNECT_PILOT_ORIGIN=https://connect.lguquezon.local
CONNECT_PILOT_HOST_ID=opts-production
CONNECT_PILOT_KEY_ID=opts-production-v1
CONNECT_PILOT_SECRET=<private registered secret>
```

The existing MIS production configuration already supplies trusted office CA, encrypted Secure/HttpOnly host-only database sessions, database cache, correct production SSO URLs and persistent storage. Preserve those settings. Do not print the complete runtime configuration or use `php artisan config:show` to collect evidence.

### 2. Exact image and additive migration

After a separately requested commit/release, required CI must pass and retain the exact tested image. The existing MIS workflow tests PHP 8.2/8.3/8.4, frontend build/tests and deployment isolation. A later deployment must follow mis-deploy-app, including a fresh database/files/runtime backup, private off-server checksum and disposable restore, schema compatibility review and concrete release approval.

The new schema is only `connect_pilot_revocations`: a SHA256 binding digest primary key and revocation timestamp. It inserts no users, offices, grants or business records. The normal image entrypoint applies migrations once. Review pending migrations before startup; do not run a second migration process. The old OPTS image tolerates this extra table, and image rollback must retain its revocation records. Current production sessions are already encrypted; this task does not change encryption mode or copy sessions.

Deploy the candidate **disabled** only through that approved release process. Recheck the real production URL, trusted TLS, running image, sign-in, office/role mapping, procurement controls, null widget prop, and absence of loader/iframe/network traffic. A disabled preparation is not an activated widget.

### 3. Functional integration gate

The Connect owner must provide its production release identity, healthy trusted host, disabled registration/key evidence and private handoff. Perform coordinated bounded acceptance in the approved isolated environment with two authorized employees. Do not create synthetic production users/messages/documents.

Cover same UUID and mismatched identity, expired/replayed/forged launch or introspection, revoked/inactive host and Connect grants, account switching, blocked third-party cookies and popup handoff, messaging/group fanout, badge/read status, reconnect, ordinary OPTS navigation and unsaved work, minimize/reopen, host logout and SSO/Connect outages. Record exact images and observed behavior. Existing local/fake tests do not establish live production integration.

### 4. Mandatory server and capacity gate

Keep the production widget and host/key disabled until Connect and server readiness evidence is accepted. The target is **300–500 concurrent employees**, not simply 500 idle sockets.

The inherited coarse source limit is 6,000 introspections/minute (bounded 300–60,000), and the verified-binding limit is 120/minute (bounded 30–600). These are provisional guardrails. The historical baseline of 16 introspections/minute per foreground employee implies 4,800–8,000/minute for 300–500 employees, before messages, read receipts, typing, reconnects or recipient fanout. A 6,000 limit is therefore not a certified 500-user budget. Each permitted introspection also performs current OPTS SSO authorization; ordinary OPTS requests share its client budget. Never cache grants or skip authorization to improve a load result.

The Connect/SSO owners must agree measured budgets, headroom and ownership. Measure on the upgraded target hardware in a coordinated safe window/environment. Include foreground activity, realistic groups, fanout, reconnect bursts, revoked-access checks and ordinary OPTS login/navigation. Record:

- Actual concurrent employees, per-minute average/peak calls and burst shape.
- OPTS/Connect/SSO latency and unexpected 429/503 responses.
- PHP workers, memory/swap, CPU/storage pressure, queue delay and WebSocket recovery.
- Continued OPTS login/navigation and prompt denial after access revocation.

Acceptance requires the agreed workload without unexpected throttling or OPTS starvation, correct outage recovery and no stale authorization. Capacity owners must record explicit acceptable latency/error thresholds before the run. Do not raise SSO budgets or launch a production load test as part of this source preparation. A failed or missing capacity record blocks activation.

### 5. Activation and recovery

After functional, infrastructure and measured workload evidence passes, present the exact release and request the explicit activation decision. Enable the matching Connect host/key and set `CONNECT_PILOT_ENABLED=true` only through that coordinated change. Refresh route/config caches by recreating the approved OPTS service; then verify a real authorized launch and current-session enforcement. Configuration completeness alone is not capacity approval.

To disable/recover: set `CONNECT_PILOT_ENABLED=false`, refresh configuration through the approved service recreation, and disable the matching Connect host/key. Verify the launcher disappears, future signed operations fail, and normal OPTS navigation continues. Keep databases, encrypted sessions and revocation rows. Use the previous compatible immutable image if needed; do not reverse the additive migration or roll back procurement records.

## Validation commands

```sh
php -d memory_limit=512M vendor/bin/phpunit --colors=never
node --test tests/frontend/connect-widget.test.cjs
node scripts/check-php-amount.cjs
npm run build
python3 scripts/check-mis-deployment.py
vendor/bin/pint --test app/Services/ConnectPilotHost.php app/Http/Controllers/ConnectPilotController.php app/Http/Controllers/Auth/SSOController.php app/Http/Middleware/EnsureSsoEligibility.php app/Http/Middleware/HandleInertiaRequests.php config/connect_pilot.php routes/connect.php tests/Feature/ConnectPilotTest.php database/migrations/2026_10_09_000000_create_connect_pilot_revocations_table.php
git diff --check
```

Required source review and reports live under `.claude/code-reviews/` and `.claude/reports/`. Record separately: source preparation, CI, release deployment, disabled behavior, live functional integration, capacity acceptance and activation. No passing source test replaces a missing operational gate.
