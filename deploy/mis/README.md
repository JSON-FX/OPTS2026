# OPTS delivery through MIS

## Release contract

Repository: `JSON-FX/OPTS2026`. `staging` is the release branch requested by the owner. `mis-image.yml` tests PHP 8.2/8.3/8.4, checks frontend types/build and deployment contracts, then exports `mis-opts:<full SHA>` for linux/amd64. Only successful pushes to staging upload `mis-image-<full SHA>` with image archive/checksum. Existing workflows are retained. CI receives no office SSH or Docker credentials. The MIS service validates current branch/run/artifact/image provenance before loading. Main has no automatic production deployment.

The registered staging host is `staging.opts.lguquezon.local`. The reserved production host is `opts.lguquezon.local`. Owner supplies Windows DNS A records pointing to `192.168.32.15`. Verify each record through Windows DNS and ordinary client resolution. The nested staging name needs an exact SAN; a wildcard for `*.lguquezon.local` is insufficient.

## Current boundary

These files are prepared source templates. At preparation time no OPTS environment existed on MIS. SSH was available, but sudo required interactive authentication. No protected registration, runtime credential, CA key, proxy configuration or service was changed. DNS and real SSO login acceptance remain pending. Do not call a successful GitHub artifact a running staging deployment.

## Operator installation for staging

Review files at the committed release before installation. As the existing trusted MIS administrator:

1. Create root-owned directories `/srv/mis-deployment/opts/staging`, `/srv/mis-deployment/opts/production`, and `/var/lib/mis-deployment/opts`, mode700. Application containers must not write these directories. Keep the existing production/SSO services and disabled runners untouched.
2. Install `staging.compose.yml` as `/srv/mis-deployment/opts/staging/compose.yml`, `trusted-ca.ini` beside it, and `smoke.sh` as `/srv/mis-deployment/opts/smoke.sh` (755). Compose/config sources may be644 inside the private directories. Create `/srv/mis-deployment/opts/staging/image.env` (600); it contains only `APP_IMAGE=<verified runtime image ID>`, selected by the helper. Runtime secrets must never enter that file or Git.
3. Copy `staging.env.example` to private `/srv/mis-deployment/opts/staging/runtime.env` (600). Replace every placeholder. Also copy `database.env.example` to this environment’s private `database.env` (600). Generate independent APP_KEY, database passwords and Reverb credentials. `MYSQL_PASSWORD` must equal `DB_PASSWORD`. Use a distinct root password. Only MySQL receives database.env; only the app receives runtime.env. Do not copy production data, users, sessions or secrets. Local build/test environment files are not deployment credentials.
4. Provision a confidential staging OPTS client in staging SSO, callback exactly `https://staging.opts.lguquezon.local/auth/sso/callback`. Save its client ID/secret only in runtime.env. Grant only selected staging identities. SSO API is `https://staging.api.sso.lguquezon.local` (OPTS appends `/api/v1`); browser portal is `https://staging.sso.lguquezon.local`. Do not use the production client or broaden all-employee access. SSO owns offices/roles. Configure workflows after office synchronization; no legacy offices are inserted.
5. Check the server pull credential has Actions-read/Contents-read access to this repository. Use the existing restricted credential; do not replace it with an administrator token. The app repository currently has no image history before this delivery.
6. Issue `opts-staging.fullchain.crt` and `opts-staging.key` on the server using the office CA, exact staging SAN. Private CA/key material stays on the server. Install inside existing proxy cert directory. Mount the existing public office CA as the template specifies. The PHP trust file configures both curl and OpenSSL.
7. Confirm the existing `mis-staging-sso-ingress` network is attached to the main proxy. The web service joins it with alias `mis-staging-opts-web`; MySQL only joins the project-private internal data network. No app/database ports are published. Back up the proxy config, merge only `staging.nginx.conf` into its http context, run `docker exec lguquezon-proxy nginx -t`, and reload gracefully. Restore the backup on failure. Do not install the production snippet yet.
8. Start only staging MySQL using its isolated project/config and a known placeholder image variable before enabling delivery:

   ```sh
   APP_IMAGE=mis-opts:bootstrap docker compose --project-name mis-staging-opts -f /srv/mis-deployment/opts/staging/compose.yml up -d mysql
   ```

   Verify healthy MySQL and private volume/network membership. Never reuse SSO or production database volumes.
9. Install reviewed `opts.json` as `/etc/mis-deployment/apps/opts.json` (root-owned600). It defines staging plus an inactive future production target. Poll selects staging only. The production paths have no runtime file or running service until a separately approved promotion. Validate and stage using the existing helper:

   ```sh
   python3 /opt/mis-deployment/deploy.py check --config /etc/mis-deployment/apps/opts.json
   python3 /opt/mis-deployment/deploy.py stage --config /etc/mis-deployment/apps/opts.json --token-file /etc/mis-deployment/github-token
   ```

10. Verify the exact source SHA/run/attempt/artifact, retained archive checksum and loaded/running image ID. The web entrypoint migrates, initializes only application roles on an empty database and starts web/queue/Reverb/scheduler. Its migration hook is intentionally empty; do not run a concurrent second migration. No demo supplier/user/document seed runs. Configure fund types, suppliers and particulars through normal administration.

## Readiness and acceptance

`smoke.sh staging` checks the real trusted hostname, public `/up`, unauthenticated protected-route401, database connectivity, exact SSO/callback hosts and secure host-only encrypted database sessions. `opts:deployment-check` probes the authenticated SSO office directory read-only. It prints no credentials or employee rows. It does not certify employee login, grants/revocation, logout isolation, workflow actions or scanner hardware.

Finish acceptance in Browser: normal HTTPS login/callback; expected SSO UUID/role/office; denied/revoked grant; sign-out; wrong-office/read-only restrictions; PR/PO/VCH references and branded QR; scanner verification when hardware arrives. Stage uses log mail and its own database/storage/queues. No production test data is allowed. Record the verified release and acceptance before promotion.

## One image, future production

Public Reverb host/port/key are escaped runtime HTML settings. The bundle contains no environment-specific Reverb endpoint or client secret, so the tested image can be promoted unchanged. The templates have identical session, queue, mail and persistence behavior; only hosts, environment and private credentials differ. Production keeps log mail until a separately reviewed outbound integration is configured.

For a later approved production release, install `production.compose.yml`, private production runtime.env and image.env, exact production certificate/proxy snippet and independent production SSO client callback. Start its own MySQL. Do not rebuild the image. Use MIS `promote` with the exact staging-verified release and a fresh explicit approval/backup record under the supplied operations guide. The timer never promotes.

## Migration and rollback

The approved UI/QR/reference delivery introduces no new schema beyond the baseline history already in this repository. The unrelated Connect pilot migration is excluded. Existing tables and soft-deleted references stay intact. First deployment has no old OPTS image to restore: preserve staging volumes, diagnose any failure and use the helper held-candidate procedure. Once a release is recorded, normal image rollback restores the prior image selection; it never reverses migrations. Future schema changes need compatibility review. Production promotion requires fresh database/files backup, off-server checksum and disposable restore proof. No production installation or backup is claimed here.

## Local checks

```sh
python3 scripts/check-mis-deployment.py
python3 /path/to/mis-deployment/skills/mis-deploy-app/scripts/deploy.py check --config deploy/mis/opts.json
sh -n deploy/mis/smoke.sh
php -d memory_limit=512M vendor/bin/phpunit --colors=never
npm run build
```

The Compose contract check uses `config --no-env-resolution` and placeholder APP_IMAGE, never runtime secrets. Source tests for runtime public configuration and deployment readiness cover mixed-host rejection, insecure sessions, upstream failure redaction and same-image host changes.
