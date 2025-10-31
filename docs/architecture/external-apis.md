# External APIs & Integrations

OPTS currently runs as a self-contained system without live integrations. This document tracks planned or optional external dependencies so engineering and operations can prepare for future work.

## Current State

- **Email Delivery (Phase 2+)** – Laravel Mail configured for local `log` driver. Production deployment will choose SMTP or transactional provider (e.g., SendGrid).
- **Notifications** – Database channel only; push/real-time channels deferred to later epics.
- **No external procurement APIs** – All procurement lifecycle data is stored internally.

## Planned Integrations (Future Epics)

| Integration            | Purpose                              | Status        |
|------------------------|--------------------------------------|---------------|
| LGU Document Archive   | Push completed procurement packages | Backlog (Epic 5) |
| SMS Gateway            | Time-sensitive alerts                | Backlog       |
| Analytics Platform     | Export metrics dashboards            | Backlog       |

When integrating with an external API:

1. Document endpoints, authentication, payload formats here.
2. Encapsulate remote calls in dedicated services (`app/Services/Integrations/...`).
3. Provide sandbox credentials for development/testing.
4. Add resilience (retry/backoff) and observability (logging, alerts).

Update this file whenever a new integration is added or existing ones change.

