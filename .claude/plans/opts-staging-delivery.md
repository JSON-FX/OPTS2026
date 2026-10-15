# OPTS staging delivery

## User story and inherited architecture
An operator can push the approved document work to staging and obtain a tested immutable image for MIS deployment. Inherit the supplied MIS skill and existing outbound pull service; no architecture fork remains open. User selected staging.opts.lguquezon.local and future opts.lguquezon.local, and will provision Windows DNS. Existing request explicitly selects the staging branch, so registration tracks staging rather than the skill default main.

## Verified state
Source is JSON-FX/OPTS2026. origin/main is 96063c7; approved local SSO baseline bf1b98b is required. Release worktree isolates Dashboard/Procurements/document/numbering/QR changes from independent Connect widget work. No OPTS server deployment or CI image workflow exists. Server SSH works but sudo requires interactive authentication. MIS protected registration paths cannot currently be installed. Production services and old runners remain untouched.

## Implementation sequence
1. Copy approved source and evidence into isolated release worktree. Exclude independent Connect pilot classes/routes/client/migration and local-only Compose changes. Preserve original dirty checkout. Validate the isolated PHP/TS application.
2. Add mis-image.yml for push staging and pull requests staging/main. Preserve existing test workflows. Required PHP 8.2/8.3/8.4 tests, amount check, TypeScript/Vite, scoped formatter, deployment contract check precede linux/amd64 image export. Upload full-SHA tagged image plus checksum only for push staging. No CI server credential.
3. Move browser Reverb public configuration from build-time host/key to escaped runtime HTML data. Preserve local VITE fallback. Keep broadcast secret server-only. Same image can move between staging/production.
4. Add operator-owned templates under deploy/mis: isolated per-environment app/MySQL/storage, TLS PHP CA mount, host-only encrypted sessions, exact SSO hosts/callbacks, staging log mail, no production target activated. Registration selects staging image only. Existing entrypoint migrates; helper migration argv stays empty to avoid duplicate migrations. Replace automatic broad reference-data seed with RoleSeeder only so no synthetic supplier rows are installed.
5. Add runtime deployment-check command: exact expected app/SSO hosts, secure session/config checks, configured separate client, database read, and authenticated read-only SSO office-directory probe. Never print secrets or employee records. Smoke also checks trusted HTTPS and protected-route redirect. Add focused tests and a static deployment contract check.
6. Add reviewable proxy snippets (HTTP redirect, HTTPS and Reverb proxy), install instructions, migration/rollback rules, DNS requirements, separate SSO client provisioning and operator handoff. Prepare files only while DNS/admin access are pending. No staging/prod data copy.
7. Run full isolated PHPUnit, build, PHP syntax/format, compose config, MIS registration validation and secret scan. Review changes. Commit approved UI/QR work and separate CI delivery changes. Push staging branch without force. Observe exact-SHA GitHub workflow and artifact. Report DNS/admin/SSO/live acceptance gates honestly.

## Acceptance
- All approved UI and QR dependencies are committed without unrelated Connect work.
- Successful staging push publishes provenance-labeled immutable image artifact only after required checks.
- Stage and future production differ only in hosts/secrets/environment flags; data/resources are isolated.
- No automatic production deploy, server SSH secret in CI, CA key in source, or production data mutation.
- Source commits and CI/artifact result are verified. DNS, privileged install, separate SSO client and live login remain explicit gates until actually completed.

## Context and checks
Read Dockerfile, docker/entrypoint.sh, config/reverb.php, resources/js/bootstrap.ts, existing auth/session config, SSOService and supplied MIS assets/ci.yml, app.example.json and scripts/deploy.py. Reuse entrypoint migration ordering. Use npm run build; php -d memory_limit=512M vendor/bin/phpunit; scoped vendor/bin/pint --test; python3 scripts/check-mis-deployment.py; docker compose config; MIS deploy.py check. Existing feature reviews and visual captures remain evidence; no new UI design is introduced.

## Scope boundary
CI/CD preparation and staging source publication are authorized. Production runtime creation, registration activation, SSO server edits, DNS management, broad Connect integration and legacy-host cleanup are separate. No additional product clarification is needed for preparation.
