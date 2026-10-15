# Feature: OPTS production Connect preparation

## Description and user story

As an OPTS employee, I want Connect available through my existing SSO identity once its production and capacity gates pass. Prepare the adapter now, with no launcher or Connect traffic while disabled.

## Scope and inherited decisions

Base: deployed e83ece8902c26ee903d0bc890d42c8096125f54a. Branch: codex/opts-connect-production in the existing attached release worktree. Reuse the independent local adapter without modifying its original checkout. Laravel 12, PHP 8.2+, Inertia/React and MySQL remain unchanged; no new dependencies.

The owner explicitly requires the widget remain disabled until Connect and server capacity checks pass. This task prepares source, tests, production configuration and an activation runbook. It does not publish a release, provision a signing key, enable a Connect registration, deploy staging, change SSO budgets/grants, run production migrations or activate the launcher.

The current Connect owner confirmed the existing signed protocol and loader hash. No product questions remain open. Registration IDs are proposed placeholders until that owner provisions them; missing credentials are an activation gate, not a reason to delay disabled preparation.

## Context references

Read the complete source before editing:

- Original `app/Services/ConnectPilotHost.php`: existing signed launch, current authorization, encrypted session introspection, durable revocation and source/binding limits.
- Original `app/Http/Controllers/ConnectPilotController.php`, `routes/connect.php`, `config/connect_pilot.php`.
- Original `tests/Feature/ConnectPilotTest.php` and `tests/frontend/connect-widget.test.cjs`.
- Original `resources/js/connect-widget.ts`, `public/js/lgu-connect-widget.js`, `docs/connect-widget-rollout.md`.
- Release `app/Http/Controllers/Auth/SSOController.php`, `app/Http/Middleware/{EnsureSsoEligibility,HandleInertiaRequests}.php`, `resources/js/app.tsx`, `routes/web.php`.
- Release `deploy/mis/{production,staging}.compose.yml`, `.github/workflows/mis-image.yml`, `scripts/check-mis-deployment.py`.
- Connect contract: `/Users/jsonse/Documents/development/lgu-dev/.worktrees/lgu-connect-capacity/docs/decisions/lc-024-host-adapter-contract.md` and `backend/app/Services/Widget/{SignedHostRequests,HostIntrospection,HostRevocations}.php`.
- [Laravel 12 HTTP client](https://laravel.com/docs/12.x/http-client#timeout): reuse bounded timeouts, disabled redirects and HTTP fakes.
- [Laravel 12 CSRF](https://laravel.com/docs/12.x/csrf#csrf-x-csrf-token): browser launch remains protected; only signed server introspection is exempt.

## Contract

- Browser launch: authenticated, CSRF-protected POST /connect/pilot/launch, no browser identity claims. Only an opaque 30-second launch reference returns.
- Connect launch/revoke: exact raw JSON and HMAC-SHA256 five-header protocol; dedicated key, fresh nonce, 30-second timestamp, HTTPS with no redirects.
- Introspection: /connect/pilot/introspect; signed server request, current encrypted persisted host session, matching UUID and fresh host SSO grant. No bearer or session ID reaches browser/Connect proof.
- Logout, denied host eligibility and account replacement revoke bindings before clearing/replacing identity. Temporary failures deny operations without treating outages as revoked grants.
- Pinned loader SHA256: 0a9ddcdf1dcda9a46c66712e91b5443678ddb1c5911d78038bc9b51366f53c81.
- Production origins: https://opts.lguquezon.local and https://connect.lguquezon.local. Proposed host/key: opts-production / opts-production-v1. Private future handoff: /etc/mis-deployment/connect/production/opts-widget.env, not yet provisioned.

## Step-by-step tasks

### 1. Reuse adapter foundation

Copy only the adapter service/controller/config/routes, append-only revocation migration, pinned loader, frontend adapter and their tests. Preserve existing namespace and protocol. Add only required hooks to the release's existing auth/middleware/app bootstrap. Do not replace those files wholesale from the original dirty checkout.

VALIDATE: `php -l app/Services/ConnectPilotHost.php`; focused PHP tests and `node --test tests/frontend/connect-widget.test.cjs` once hooks exist.

### 2. Prepare disabled production configuration

Add exact production/staging environment examples using distinct proposed registrations and blank secret values. Keep default false in config and examples. Preserve runtime config precedence so later explicit activation can be reviewed separately. No live files are edited. Pin the loader in a non-secret JSON manifest with source and digest. Add a deployment contract check for disabled templates, correct origins and pinned asset. Add frontend adapter checks to existing CI.

VALIDATE: `python3 scripts/check-mis-deployment.py`; `npm run build`.

### 3. Add focused disabled and production tests

Assert disabled production with otherwise complete config returns no widget prop, denies launch/introspection, and sends no Connect traffic. Add frontend test proving null config never loads script, creates iframe/launcher, or sends requests. Exercise supported environment handshake with exact production origins/IDs using HTTP fakes, CSRF, persisted encrypted sessions and current SSO authorization. Retain replay, revocation, outage, privacy and navigation tests.

VALIDATE: `php -d memory_limit=512M vendor/bin/phpunit tests/Feature/ConnectPilotTest.php --colors=never`; `node --test tests/frontend/connect-widget.test.cjs`.

### 4. Document activation and recovery

Update the rollout guide for actual OPTS hosts and current release/preparation boundary. Document separate disabled host/key provisioning, private secret handoff, exact frame ancestor/introspection, budgets and functional acceptance. Require measured 300–500 concurrent employees including ordinary OPTS navigation, group fanout, reconnects and revocation. Capture latency, 429/503, worker/queue pressure and SSO starvation. Require backup/off-server restore and exact-image approval before a future release. Widget enablement remains a separate explicit action after readiness evidence. Recovery disables adapter and host/key, preserves revocation data, and restores compatible prior image without reversing schema.

VALIDATE: cross-check URLs/paths/hash against current sources and owner handoff.

### 5. Validate and review

Run full PHP suite, focused changed PHP Pint, frontend tests, amount regression, TypeScript/Vite build, deployment contract and whitespace checks. Use Browser for a local disabled candidate and verify no loader/launcher/iframe; inspect live production read-only only if useful. Do not create production fixtures. Review the entire scoped diff and new files, then write implementation and code-review reports. No commit/push/deployment without a request.

## Acceptance criteria

- [x] Candidate reuses current Connect contract and hash; existing release behavior remains intact.
- [x] Default false hides widget and prevents Connect launch/introspection network traffic.
- [x] Production URLs and private signing-key process are concrete and isolated.
- [x] Existing SSO roles/offices/workflows and independent Connect session remain authoritative.
- [x] Migration is additive and retains revocation records on rollback.
- [x] Required tests/build/format/deployment checks and review pass.
- [x] Documentation distinguishes prepared, deployed and activated; 300–500 capacity is explicitly unverified.

## Assumptions and pending activation gates

No runtime capacity values or signing secrets are guessed. The Connect owner controls production registration, host/key enabling, frame ancestors and workload acceptance. The reviewed source candidate can be prepared while those remain pending. Production stays on e83ece8 during this task.

## Amendments

- 2026-10-15: Added a configuration guard and regression test rejecting reuse of the OPTS SSO client secret as the connector secret. This enforces the inherited dedicated-key requirement.
