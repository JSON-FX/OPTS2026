# OPTS local Docker setup review

Date: 10 October 2026 (Asia/Manila)

## Scope and result

Created a separate `opts2026` container from the current local application source. Added `compose.yaml` and a Docker startup section in `README.md`. Existing uncommitted application changes were preserved. No commit or push was requested.

- Files modified by this task: 1 (`README.md`, 12 lines added).
- Files added for operation: 1 (`compose.yaml`).
- Files deleted: 0.
- Review records and check logs are stored locally under `.claude/`, which is excluded from Docker builds.

Code review passed for the new Docker configuration. No technical issues were detected in these changes. Overall application validation remains **FAIL** because the existing backend suite and formatting checks fail.

## Verification

- `docker compose config --quiet`: PASS.
- `docker compose build`: PASS. This includes Composer dependency installation and `npm run build` (`tsc && vite build`).
- `docker compose up -d --no-build --wait --wait-timeout 120`: PASS. `opts2026` is healthy and restarts unless explicitly stopped.
- HTTPS `/up`, `/`, and `/login`: HTTP 200 with certificate verification enabled.
- Browser: homepage rendered; clicking Sign In rendered the OPTS login page. Homepage left open as the deliverable.
- WebSocket handshake through `https://opts.lgu.lan/app/…`: HTTP 101.
- Process inspection: Nginx, PHP-FPM, queue worker, and Reverb running. The existing supervisor configuration does not expose `supervisorctl`; startup logs and `docker top` confirmed the processes instead.
- Image hashes match the local `SSOController.php`, `SSOService.php`, and `bootstrap/app.php` files.
- Migrations: all 38 already applied; startup reported nothing to migrate.
- Database: 11 users and zero procurements before and after startup; no reseeding occurred. Zero queued jobs before startup. A private database backup was saved before startup under `.claude/local-backups/`.
- Uploaded files use the persistent `opts2026-local_opts_storage` Docker volume. Existing host `storage/app` contained no uploaded files.
- `git diff --check`: PASS.

## Existing check failures and limits

`composer test` ran on the host using an isolated in-memory SQLite database. It reported 42 failed tests, 593 deprecated tests, and one passed test, with 2,408 assertions. Failures include migration mapping, service resolution, purchase-request validation expectations, and a removed user-creation route expectation. See `opts-docker-2026-10-10/backend-tests.log` for the full results.

`vendor/bin/pint --test` reported 16 style issues across existing application and test files. See `opts-docker-2026-10-10/php-format.log`. No PHP source files were changed by this task.

Single sign-on credentials are absent from the current OPTS `.env`. Prior SSO registrations were retired according to the workspace documentation. This task does not restore credentials or register an application. Authenticated sign-in and procurement flows remain unverified.

## Operation

From `opts2026/`, run `docker compose up -d --build` after app changes. Open `https://opts.lgu.lan`. This setup requires the shared MySQL and Traefik services on `dev-net`; it does not alter the root SSO Compose stack.
