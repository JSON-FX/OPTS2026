# Code Review — LC-029 OPTS Connect pilot adapter

**Scope:** OPTS pilot adapter, signed host routes, authenticated loader, and focused tests.  
**Result:** No technical issues detected in the implemented adapter.

The reviewed trust boundaries use a server-held key, raw-body HMAC, a 30-second timestamp window, a 70-second one-use nonce, exact host and key identifiers, exact HTTPS origins, and fresh SSO authorization. The browser receives only a public origin and same-origin launch URL. Confirmed grant denial records a durable revocation digest and denies the old binding after a restored grant. A transient SSO outage returns 503 without revoking the host session. Logout clears the local binding before the best-effort Connect callback; session invalidation still completes during Connect failure.

The committed OPTS callback's query token remains a release blocker. The example SSO API URL also includes `/api/v1`, while the current service appends that path. Reconcile both with the maintained code-exchange revision before enabling staging. Live host registration and browser acceptance remain open. Full suite and full formatter failures reproduce at the clean base or affect only untouched files, as recorded in the implementation report.
