# LGU Connect widget in EDTS 2027

EDTS 2027 is the application in `opts2026`. The widget uses the shared Connect interface inside an isolated iframe. The host loader is pinned locally at `public/js/lgu-connect-widget.js`. A script alone cannot authenticate the widget: EDTS must attest to its current SSO-backed session, while Connect independently checks the employee's Connect grant.

## Local setup

1. Use local SSO for both applications: portal `https://sso-portal.lgu.lan`, API `https://sso.lgu.lan`. EDTS is `https://opts.lgu.lan`; Connect is `https://connect.lgu.lan`. Register each application's exact `/auth/sso/callback` URL. Keep secrets on their respective servers.
2. Configure the widget keys in `.env`. Use a dedicated random connector secret of at least 32 bytes. This must differ from either application's SSO client secret. Set `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, secure HttpOnly cookies, and a shared database/Redis cache. Changing session encryption requires a new sign-in.
3. In Connect, register enabled `WidgetHost` and `WidgetHostKey` records for the exact EDTS origin and introspection URL `https://opts.lgu.lan/connect/pilot/introspect`. Store the secret through the encrypted model cast. Keep secrets out of SQL history, command arguments, Git, browser props and logs.
4. Set Connect ingress `CONNECT_FRAME_ANCESTORS=https://opts.lgu.lan`. Use exact origins, never `*`. Preserve any already approved origins. EDTS must permit the Connect frame and the pinned loader if its own content security policy restricts them.
5. Set `LGU_LOCAL_CA_FILE` to the public mkcert root certificate path. Run `docker compose up -d --build`. The local compose file provides Docker hostname resolution and verified TLS; no certificate bypass or private CA key is needed.
6. Apply the specific new `create_connect_pilot_revocations_table` migration after reviewing pending migrations. Do not reset or seed the EDTS database. Set `CONNECT_PILOT_ENABLED=true` only after both applications can reach their SSO and each other through HTTPS.
7. Give synthetic local test accounts a grant to both applications, then sign in normally. No local chat password is used.

## UI updates and environment compatibility

The web app and embedded chat UI are served by the selected Connect deployment. Deploying Connect updates the iframe UI for every registered host in that environment; an already-open widget uses the update after its containing page reloads. Do not force reloads while employees are composing messages. Local, staging and production each use their own Connect origin, SSO authority/client, host registration and signing key. The same adapter code supports all three environments with trusted HTTPS.

The EDTS launcher integration, visible unread badge and pinned `public/js/lgu-connect-widget.js` are host assets. Changes to these require an EDTS build/release. Compatible Connect UI changes do not. A change to the signed launch or postMessage contract requires coordinated compatibility review and acceptance before either application is promoted. Local Docker CA/hostname settings belong only in the local Compose setup; remote deployments use their environment configuration and trusted office certificates.

## Runtime behavior

The launcher survives ordinary Inertia navigation. It is destroyed when the active identity/configuration changes or a guest page replaces the authenticated page. Opening calls the CSRF-protected `/connect/pilot/launch` route. Only an opaque 30-second launch reference returns to the browser. Signed `/connect/pilot/introspect` requests validate the live encrypted database session, user, binding and current SSO grant. Host logout, confirmed denial and account replacement revoke the binding. Temporary failures fail closed without turning a temporary outage into permanent grant revocation. The host's secret and bearer never enter browser JavaScript.

## Staging preparation

Use `deploy/connect-widget/staging.env.example`. Exact EDTS staging hostname and SSO registration remain operator inputs. Do not connect local EDTS/SSO identities to staging Connect. Provision separate databases, sessions, cache, files and connector keys. Trust the office CA with a public CA mount, verify normal DNS/TLS and all server-to-server paths. Register the matching Connect host/key and exact frame ancestor, then enable only staging. Follow the existing MIS immutable-image deployment workflow. Do not copy local Docker host-gateway overrides into MIS.

Acceptance must cover two employees: launch and popup handoff, identical identity, mismatched identity rejection, blocked third-party cookies, direct/group live messaging, unread/Seen behavior, jump-to-latest, navigation/draft preservation, minimize/reopen, host logout, withdrawn grant and inactive user, stale/forged/replayed requests, SSO outage, and unaffected EDTS forms. Independent Connect web access must remain independent of host logout. Record source/image IDs, CI, TLS, screenshots and observed failures. The current remote Connect 503/capacity finding is a release blocker until investigated and accepted; passing this local integration does not resolve it.

## Capacity gate before staging and production

Introspection has two independent limits. `CONNECT_PILOT_INTROSPECTION_SOURCE_PER_MINUTE` is a coarse, pre-signature source-IP limit: default 6,000/minute, configurable from 300 to 60,000. All widget employees behind the Connect server share it. `CONNECT_PILOT_INTROSPECTION_BINDING_PER_MINUTE` applies after signature and body validation: default 120/minute for each host-session binding, configurable from 30 to 600. Rebuild configuration and route caches after changing either setting. Invalid signatures cannot consume a known binding's budget. Exceeded limits return HTTP 429; binding limits include Retry-After. Connect treats 429 as temporary unavailability, never a revoked grant. Revocation still applies on the next allowed operation; no authorization results are cached.

These values are guardrails, not a certified employee capacity. Foreground activity and heartbeat alone currently cause at least 16 introspections per minute per employee. Add messages, typing, read receipts, reconnects, and recipient fanout. Each permitted introspection also calls EDTS's SSO authorize endpoint. Ordinary EDTS navigation and identity/directory refresh use that same SSO consumer's budget. The documented 100/minute consumer budget cannot support even seven employees at this baseline, and two-person local testing does not certify an office rollout.

Before enabling staging or production, measure the expected concurrent employee count, average and peak widget introspections, recipient fanout, and ordinary EDTS SSO calls. Provision the SSO client's matching budget through its owner with explicit headroom for bursts and EDTS operations; do not raise it silently or reuse another environment's client. Size the coarse source limit for aggregate fanout, and the binding limit for one active employee/session. Record agreed budgets, responsible owner, and observed per-minute peak totals.

A concurrent-load gate is mandatory at the planned rollout population. Run a bounded test combining foreground chat, group fanout, normal EDTS navigation, and revoked-access checks. Record latency, 429/503 rates at EDTS/Connect/SSO, PHP worker saturation, queue delay, and retry behavior. Acceptance requires no unexpected throttling within the agreed workload, no starvation of EDTS sign-in/navigation, and no stale authorization after revocation. Failed capacity acceptance blocks staging expansion and production. A two-account local check remains valid functional evidence within the existing budget, not load evidence. No SSO limits or remote settings are changed by this implementation.

### Local capacity observation, 2026-10-15

The bounded two-account widget test exhausted local OPTS SSO application 13 at its previous 100/minute limit. The live rate counter read 100 with Retry-After 5; EDTS logged an SSO office lookup HTTP 429 at 00:23:03 UTC. Aggregate local SSO endpoint traffic peaked at 239 requests in one minute during the test, across both EDTS and Connect consumers. Local Connect's separate 600/minute consumer was not exhausted. This establishes a local integration budget problem, not an office-wide capacity measurement.

For this local test only, OPTS application's budget was changed from 100 to 600/minute. No secrets, employee grants, other applications, staging, or production configuration changed. Rollback sets only that local OPTS application back to 100/minute through the SSO application configuration; expect the same widget workload to throttle again. The earlier 100/minute recommendation in `docs/sso-directory-access.md` predates widget traffic; remote adoption requires the capacity gate above, not a blind copy of 600.

Connect's PHP pool independently reported its five-worker limit during the same test. The budget change does not prove that worker saturation or all HTTP 503 responses are solved. A subsequent local recovery sample from 00:25:07 to 00:26:45 UTC (98 seconds) observed 60 widget API responses: 52 HTTP 200, seven HTTP 204, and one HTTP 201. It contained no HTTP 429/503 or other API errors and no new PHP worker-limit warnings. This bounded functional recovery sample is not concurrent office-scale acceptance.

## Production preparation

Use `deploy/connect-widget/production.env.example`; it is disabled by default. Require the exact production EDTS and Connect URLs, separate SSO clients/host key, an accepted staging image, measured capacity, backups with tested restore, reviewed migration compatibility and explicit release approval. Deploy the tested immutable image through MIS; never promote synthetic local/staging users or data. `CONNECT_PILOT_*` remains the configuration namespace for compatibility, but the implementation supports explicit validated production configuration.

## Rollback and operations

Set `CONNECT_PILOT_ENABLED=false`, refresh configuration and recreate EDTS. Disable the corresponding Connect host/key to deny active widget operations. Keep the append-only revocation table and existing records; do not roll back unrelated migrations. Restore the previous immutable EDTS image if needed. Preserve the selected session encryption mode when rolling back an image; changing it again also requires reauthentication. Verify EDTS sign-in, forms, normal navigation and Connect web access independently.

Backups must include the EDTS database, application/session encryption key and private connector configuration. Give production keys separate ownership and rotate by registering a second Connect key before switching EDTS and retiring the old key. The current EDTS adapter accepts one configured incoming key at a time, so perform the switch as a coordinated change and verify new handshakes before retiring the old key.
