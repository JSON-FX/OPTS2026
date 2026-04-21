<!-- lgu-ecosystem:start -->
# LGU Ecosystem Context

This project is one of four interconnected apps in the LGU platform:

| Project     | Role                        | Dev host           | Prod host                      |
|-------------|-----------------------------|--------------------|--------------------------------|
| LGU-SSO     | Auth server (API)           | `api.sso.local`    | `api.sso.lguquezon.local`      |
| lgu-sso-ui  | SSO login portal            | `sso-portal.local` | `sso-portal.lguquezon.local`   |
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
- `lgu-sso-ui/LGU-SSO-INTEGRATION-GUIDE.md` — step-by-step SSO integration.
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

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **opts2026** (5766 symbols, 10667 relationships, 77 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/opts2026/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/opts2026/context` | Codebase overview, check index freshness |
| `gitnexus://repo/opts2026/clusters` | All functional areas |
| `gitnexus://repo/opts2026/processes` | All execution flows |
| `gitnexus://repo/opts2026/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
