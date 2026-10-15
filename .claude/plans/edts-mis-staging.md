# EDTS MIS staging image and deployment package

## Inherited scope

User approved `staging.opts.lguquezon.local` and including the existing SSO baseline. Build from the clean widget worktree; do not deploy, publish, or alter primary UI work in this subtask. The MIS operator owns deployment credentials and fixed hooks. No open product choices remain.

## Design

Reuse the existing one-image Laravel/PHP-FPM/Nginx/queue/Reverb/scheduler runtime. Move public Reverb endpoint settings from Vite build arguments to server-rendered runtime configuration. Keep local automatic migration/seed defaults; MIS explicitly disables those and runs fixed migration plus reference-only bootstrap hooks. Do not seed demo suppliers/users. Build/test one linux/amd64 full-SHA artifact through the existing MIS convention. Staging has its own MySQL, storage, app key, sessions, SSO client, widget key, and public office CA.

## Steps

1. Update runtime public Reverb bootstrap, types and Blade; remove Docker build arguments. Source image labels supplied by CI. Validate `npm run build` and focused PHP public-config test.
2. Gate local entrypoint auto migration/seeding with explicit runtime flags; add an idempotent staging-only bootstrap command for roles/fund types/action labels. Redact Nginx request queries. Validate shell syntax, bootstrap tests, Nginx configuration in candidate image.
3. Add dedicated MIS CI workflow retaining all existing workflows, PHP8.4 tests, frontend lifecycle tests/build, syntax/format checks and main-only artifact upload. Add staging Compose, private env examples, fixed migrate/smoke hooks, registration and proxy examples. Validate Compose with synthetic configuration and shell syntax.
4. Run full PHPUnit SQLite suite, frontend tests/build, focused Pint and whitespace check. Build candidate image and test fresh isolated MySQL startup/bootstrap as a required CI image acceptance check; parent owns remote acceptance and publication.

## Acceptance

Image frontend uses no environment-specific hostname/key baked into assets. Bootstrap is staging-only/idempotent and adds no employees/demo operational records. MIS hook performs migrations explicitly after app recreation and before health/smoke acceptance. Staging routes use trusted exact HTTPS and host-only cookies. CI artifact is sourced from passing current main; existing checks remain. No application can write deployment code/config or access the Docker socket. Health and SSO readiness smoke are read-only. Production promotion remains outside this package.

## References

`Dockerfile`, `docker/entrypoint.sh`, `docker/nginx.conf`, `resources/js/bootstrap.ts`, `resources/views/app.blade.php`, `database/seeders/DatabaseSeeder.php`; MIS skill infrastructure/onboarding/operations/SSO references and CI/registration assets; `.claude/plans/edts-connect-widget.md`.

## Validation commands

Use PHP8.4 and synthetic APP_KEY with phpunit.xml SQLite :memory:. `vendor/bin/phpunit`, `vendor/bin/pint --test` scoped to added PHP, `node --test tests/frontend/*.test.cjs`, `npm run build`, `sh -n` for scripts, `docker compose ... config --quiet` with synthetic env, `git diff --check`. Docker candidate build must use this worktree, not the unrelated dirty primary checkout.
