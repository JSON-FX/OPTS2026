<!-- lgu-ecosystem:start -->
# LGU Ecosystem Context

This project is one of four interconnected apps in the LGU platform:

| Project     | Role                        | Dev host           | Prod host                      |
|-------------|-----------------------------|--------------------|--------------------------------|
| lgu-sso-backend     | Auth server (API)           | `api.sso.local`    | `api.sso.lguquezon.local`      |
| lgu-sso-portal  | SSO login portal            | `sso-portal.local` | `sso-portal.lguquezon.local`   |
| lgu-chat    | Real-time messaging         | `chat.local`       | `chat.lguquezon.local`         |
| opts2026    | Procurement tracking        | `opts.local`       | `opts.lguquezon.local`         |

All four apps run inside the shared Docker container **development**.

## Two SSO hosts, two purposes

- **Login redirect (user-facing)** → `sso-portal.local` / `sso-portal.lguquezon.local`
- **SSO API (server-to-server)** → `api.sso.local` / `api.sso.lguquezon.local`

Never `sso-ui.*`, never `lgu-sso.test`, never `lgu-sso.local`. If you see any of those in code, env, docs, or registered redirect URIs, record the hit under root `CLAUDE.md` Open Issue #2 — don't fix it during unrelated work.

## Dev ↔ Prod parity rule

Dev and production must differ **only** in DNS hostnames, secrets, and `APP_ENV` / `NODE_ENV` / `APP_DEBUG`. Everything else — cookie names, cookie domains (with the correct parent), SameSite/Secure flags, session drivers, CORS, SSO endpoints, client-ID formats, redirect URI patterns — must match.

If a feature works in dev but fails in prod, the first question is: *what differs beyond DNS and secrets?*

## Open cross-app issues

Known problems in the ecosystem (cross-app SSO cookie sharing, legacy hostname purge, redirect/token loss, dev↔prod drift). These are **not diagnosed** and must not be fixed during unrelated work — each needs its own brainstorm → plan cycle. See `../CLAUDE.md` → Open Issues.

## Further reading

- `../CLAUDE.md` — canonical ecosystem doc (SSO model, full open-issues list, cookie domain subtleties).
- `../lgu-sso-portal/LGU-SSO-INTEGRATION-GUIDE.md` — step-by-step SSO integration.
<!-- lgu-ecosystem:end -->

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OPTS 2026 — Online Procurement Tracking System for LGUs. Tracks procurement documents (Purchase Requests, Purchase Orders, Vouchers) through configurable multi-step office workflows with SLA monitoring and role-based access.

**Stack**: Laravel 12 + React 18 + TypeScript + Inertia.js + shadcn/ui (new-york style) + Tailwind CSS + MySQL 8

## Common Commands

```bash
# Development (starts Laravel server, queue worker, log tail, Vite HMR concurrently)
composer dev

# Backend tests (clears config cache first, uses in-memory SQLite)
composer test

# Single test file
php artisan test tests/Feature/TransactionEndorseTest.php

# Single test method
php artisan test --filter="test_endorser_can_endorse_transaction"

# E2E tests (requires app running on localhost:8000)
npm run test:e2e
npm run test:e2e:ui     # Playwright UI mode

# Code formatting (PHP)
./vendor/bin/pint

# TypeScript check + frontend build
npm run build

# WebSocket server for real-time notifications (optional)
php artisan reverb:start

# Check overdue transactions (scheduled command)
php artisan opts:check-overdue
```

## Architecture

### Supported Data and Retired Imports

Legacy ETTS imports were retired on 2026-10-14. Do not add SQL upload, legacy mapping, import jobs, or import rollback back into the application. Use the current procurement/PR/PO/voucher model and SSO directories. Historical import plans are archived context only. Preserve Laravel schema migration history and existing records; retirement does not authorize dropping data.

### Inertia.js Bridge — No REST API

All frontend-backend communication goes through Inertia.js. Controllers return `Inertia::render('PageName', $props)` — there are no JSON API endpoints. Shared props (auth user, pending receipts count, notifications) are injected via `HandleInertiaRequests` middleware.

Use `router.visit()`, `router.post()`, or Inertia `useForm()` on the frontend — never `fetch()` or `axios` for app data.

### Path Aliases

TypeScript path alias `@/*` maps to `resources/js/*` (configured in `tsconfig.json`). Use `@/Components/ui/button` not relative paths.

### Frontend Conventions

- **shadcn/ui** (new-york style, Slate base color, CSS variables): components live in `resources/js/Components/ui/`. Install new ones via `npx shadcn@latest add <component>`.
- **Pages**: `resources/js/Pages/` — each maps to an Inertia route. Domain pages are in subdirectories (Procurements, PurchaseRequests, PurchaseOrders, Vouchers, Transactions, Admin, Dashboard, Notifications).
- **Layouts**: `AuthenticatedLayout` (main app shell with nav + notification bell) and `GuestLayout`.
- **Types**: `resources/js/types/models.ts` has Eloquent model interfaces; `resources/js/types/index.d.ts` has `PageProps` and shared Inertia types.
- **Routing**: Use Ziggy's `route()` helper for named Laravel routes in React components.

### Backend Patterns

- **Service Layer**: Business logic lives in `app/Services/` — `TransactionStateMachine` (state transitions), `EndorsementService` (endorse/receive/complete), `WorkflowService`, `ReferenceNumberService`, `DashboardService`, etc. Controllers are thin wrappers.
- **Transaction State Machine**: Governs status transitions (Created → In Progress → Completed/On Hold/Cancelled). Located in `app/Services/TransactionStateMachine.php`.
- **RBAC**: Spatie Laravel Permission with three roles: Viewer, Endorser, Administrator. Route groups enforce roles via `role:` middleware.
- **Notifications**: 4 notification classes in `app/Notifications/` broadcast via Laravel Reverb (WebSocket). App degrades gracefully without Reverb running.

### Route Structure

Routes are grouped by role in `routes/web.php`:
- **Authenticated** (`auth`): Dashboard, procurements (resource), transaction list, document show pages, notifications, profile
- **Endorser|Administrator**: Transaction actions (endorse, receive, complete), document CRUD (PR, PO, Voucher)
- **Administrator only**: Hold/cancel/resume transactions, admin panel (workflows, users, repositories: offices/suppliers/particulars/fund-types/action-taken)

### Testing

- **PHPUnit** with in-memory SQLite (`phpunit.xml`). Unit tests in `tests/Unit/Services/`, feature tests in `tests/Feature/`.
- **Playwright E2E** in `tests/e2e/`. Has auth setup project, runs against Chromium + mobile (iPhone 13). Requires app running at `localhost:8000`.

### BMad Methodology

Stories are tracked in `docs/stories/`. When implementing stories as the BMad Dev Agent, update story task status: set to "Ready for review" when all tasks are completed, "In Progress" otherwise.
