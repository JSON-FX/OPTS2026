# EDTS Connect backend implementation

Plan: `.claude/plans/edts-connect-widget.md`. Branch: `feature/edts-connect-widget`.

## Completed

- Reused the LC-029 host adapter, signed wire protocol, revocation ledger, and launch/introspection controller.
- Reused current SSO authorization semantics. Confirmed denial revokes; temporary upstream failure remains unavailable.
- Introspection checks encrypted database session contents against the exact binding, UUID, guard user ID, expiry, and current SSO token. Cache contains no bearer token. Session connection and table configuration are respected.
- Added revocation before logout, successful SSO identity replacement, and eligibility middleware session invalidation.
- Added separate route file. Launch keeps authentication and CSRF; signed introspection excludes browser eligibility and CSRF middleware.
- All environments default disabled. Production permits explicit safe configuration without an unconditional code prohibition.

## Owned files

- app/Services/ConnectPilotHost.php
- app/Http/Controllers/ConnectPilotController.php
- config/connect_pilot.php
- database/migrations/2026_10_09_000000_create_connect_pilot_revocations_table.php
- routes/connect.php and its include in routes/web.php
- lifecycle additions in app/Http/Controllers/Auth/SSOController.php and app/Http/Middleware/EnsureSsoEligibility.php
- tests/Feature/ConnectPilotTest.php

Frontend, shared Inertia props, environment rollout and live verification are handled by the parent task.

## Validation

PHP 8.4.26, composer.lock dependencies, SQLite `:memory:` from phpunit.xml. Synthetic APP_KEY only. No operational database was used.

```sh
APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE= /opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit
```

Full suite: 702 tests, 2962 assertions, no failures, exit 0. Existing 37 PHPUnit metadata deprecations originate in untouched procurement/purchase-order/voucher test classes. Focused ConnectPilot + SSOAuthentication: 39 tests, 329 assertions. Eight owned PHP files passed Pint. `git diff --check` passed.

The locked nette/schema version excludes PHP 8.5; installed dependencies with `/opt/homebrew/opt/php@8.4/bin/php /opt/homebrew/bin/composer install --no-interaction --prefer-dist --no-progress`. No dependency changes.

## Limits

Enabling encrypted database sessions invalidates previous plaintext sessions, requiring fresh sign-in. Existing browser introspection requests may create a normal Laravel session cookie, but it grants no widget access and is not required. Current EDTS callback guest middleware prevents an interactive signed-in account switch; callback revocation is defense in depth and tested separately with that guest redirect guard disabled.

GitNexus tools and referenced local skill were unavailable in this checkout. Direct lifecycle callers and route middleware were traced manually before edits. No primary-checkout changes or commits were performed.
