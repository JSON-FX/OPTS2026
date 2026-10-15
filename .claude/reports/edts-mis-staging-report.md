# EDTS MIS staging package implementation

## Scope

Prepared the accepted SSO baseline and Connect widget for a new isolated MIS staging deployment at `https://staging.opts.lguquezon.local`. No production target, remote mutation, source publication, or dirty primary-checkout UI changes were performed by this implementation subtask.

## Changes

- Added a dedicated CI workflow that validates source, builds a full-SHA Linux AMD64 image, tests fresh MySQL startup, and publishes the image archive/checksum only after successful main pushes.
- Moved public browser Reverb configuration to runtime Blade data. The same built frontend follows each environment's APP_URL and public Reverb key. Private keys remain server-side.
- Preserved local automatic migration/seeding defaults. MIS disables both and uses fixed operator-owned migration and reference-only bootstrap hooks.
- Added isolated staging Compose, private environment examples, public CA trust, proxy example, helper registration, readiness checks, recovery notes, and explicit staging acceptance requirements.
- Restricted staging Reverb origins to its exact hostname. Sessions use a distinct host-only cookie and encrypted database storage.
- Excluded private environment files and generated config caches from the Docker context. Request logs omit query strings and referrers. The SSO callback also suppresses Nginx error logging through its PHP front-controller handling.
- Added staging bootstrap and runtime configuration tests. Fixed the existing OfficeFactory abbreviation generator to produce unique values after a reproducible full-suite collision.

## Validation

- PHP 8.4, synthetic APP_KEY, SQLite in memory: **710 tests, 3396 assertions passed**. There are 37 existing PHPUnit deprecations.
- Existing frontend lifecycle tests: **6 passed**.
- `npm run build`: passed, including TypeScript compilation.
- Focused Pint checks: passed for six changed/new PHP files.
- Shell syntax and Compose schema checks with synthetic configuration: passed.
- A disposable fresh MySQL 8.4.11 database accepted all migrations and repeated reference bootstrap. Verification confirmed three roles, three fund types, 19 action labels, and zero users/suppliers/particulars.
- Final Node 22 candidate image acceptance: passed. Checked Nginx syntax, normal entrypoint startup, /up and /login, Reverb socket, fresh MySQL schema and repeated bootstrap. A separate Nginx-only container returned the expected callback HTTP 502 without logging code/state markers to actual Alpine log files or container output. An unprotected /login request logged its distinct control marker, proving the negative assertion inspected an active error sink. All disposable validation containers, networks, and database volumes were removed.

The default 128 MB PHP CLI limit exhausted memory while writing PHPUnit's result cache. The workflow uses 512 MB. A subsequent run found the OfficeFactory random abbreviation collision; after the narrow unique generator fix the complete suite passed.

## Remaining release gates

The parent task owns independent review completion, source commit/push, main CI artifact provenance, MIS registration/provisioning/deployment, and live browser acceptance. Remote acceptance must cover SSO login/logout, two-user widget chat, unread/seen/group behavior, revocation, host logout isolation, immutable image identity, and production isolation. Concurrent-user capacity and recovery remain explicit acceptance gates. No production deployment is included.

## Staging egress correction

The first staging attempt at main `f4fe0bc` completed migrations and local application health, but failed the SSO readiness smoke. Parent runtime inspection confirmed both attached networks were internal, so the app had no default route. Office DNS requests timed out and SSO reported a connection error.

Added a project-owned egress bridge for the app only. The existing internal data network and shared ingress remain unchanged. MySQL still joins only the internal data network and exposes no host ports. Validate the resolved Compose network topology, then require the running staging app's default route, office DNS resolution, and trusted SSO/Connect HTTPS readiness before acceptance. Local image health alone does not prove office connectivity.

Validation: Docker Compose schema resolution passed with disposable synthetic environment files. Assertions passed for app membership in data/ingress/egress, MySQL membership in data only, internal data isolation, non-internal bridge egress, unchanged external lgu-staging-app ingress, and no published app or MySQL ports. Whitespace validation passed. Live MIS route/DNS/HTTPS recovery remains for the parent deployment retry. No MIS mutation was performed by this correction subtask.
