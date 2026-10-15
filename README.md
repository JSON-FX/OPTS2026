# OPTS 2026

**Online Procurement Tracking System** for Local Government Units (LGUs)

A full-stack web application that tracks procurement documents (Purchase Requests, Purchase Orders, and Vouchers) as they move through government offices, enforcing configurable workflows, SLA timelines, and role-based access control.

<!-- lgu-ecosystem:start -->
## LGU Ecosystem

This project is one of four applications that make up the LGU platform, all running inside the shared Docker container **development**:

| Project     | Role                       | Dev URL                                 | Prod URL                                            |
|-------------|----------------------------|-----------------------------------------|-----------------------------------------------------|
| LGU-SSO     | Authentication API         | http://api.sso.local                    | https://api.sso.lguquezon.local                     |
| lgu-sso-ui  | SSO login portal           | http://sso-portal.local                 | https://sso-portal.lguquezon.local                  |
| lgu-chat    | Real-time messaging        | http://chat.local                       | https://chat.lguquezon.local                        |
| opts2026    | Procurement tracking       | http://opts.local                       | https://opts.lguquezon.local                        |

Authentication works in two hops: apps redirect users to the **login portal** (`sso-portal.*`), and then talk to the **SSO API** (`api.sso.*`) server-to-server to validate tokens and fetch the user profile. The legacy names `sso-ui.*`, `lgu-sso.test`, and `lgu-sso.local` are being phased out — always use `sso-portal.*` and `api.sso.*`.

Dev and production configurations must be identical except for DNS hostnames, secrets, and `APP_ENV`/`NODE_ENV`/`APP_DEBUG`. See the workspace-level [`../CLAUDE.md`](../CLAUDE.md) for the full ecosystem overview, authentication model, and the list of known cross-app issues.
<!-- lgu-ecosystem:end -->

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12.x (PHP 8.2+) |
| Frontend | React 18 + TypeScript |
| Bridge | Inertia.js (no REST API) |
| UI Components | shadcn/ui + Radix UI + Tailwind CSS |
| Database | MySQL 8.0+ (SQLite for development) |
| Real-time | Laravel Reverb (WebSocket) |
| RBAC | Spatie Laravel Permission |
| Testing | PHPUnit + Playwright E2E |

## Features

### Procurement Management
- Create and manage procurements with linked transactions (PR, PO, Voucher)
- Auto-generated reference numbers (e.g., `PR-GF-2026-02-001`)
- Business rule validation for transaction dependencies

### Supported Data

Procurements and their PR, PO, and voucher records are created through the current application. Legacy database imports, SQL uploads, ETTS mapping, import rollback, and import clearing are retired. Offices, users, assignments, and access roles come from SSO. Laravel schema migrations remain part of normal installation and upgrades.

### Workflow Engine
- Configurable multi-step workflows per transaction category
- Endorse, Receive, Hold, Resume, and Complete actions
- Transaction state machine with full audit trail
- Out-of-workflow detection with notifications

### Dashboard & Monitoring
- Summary cards (procurements, PRs, POs, vouchers)
- Office workload table with stagnant transaction detection
- Activity feed with search and pagination
- SLA performance metrics and turnaround tracking
- Out-of-workflow incident tracking

### Notifications
- Database-stored notifications with bell popover
- Real-time delivery via Laravel Reverb WebSocket
- Notification types: received, completed, overdue, out-of-workflow
- Graceful degradation when Reverb is not running

### Roles
| Role | Access |
|------|--------|
| **Administrator** | Full access, user management, workflow configuration |
| **Endorser** | Endorse/receive/complete transactions for assigned office |
| **Viewer** | Read-only access to procurements and transactions |

## Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- MySQL 8.0+ (or SQLite for development)

## Getting Started

### Local Docker Container

With the shared `mysql` and Traefik containers running on `dev-net`, start OPTS separately:

```bash
docker compose up -d --build
```

Open <https://opts.lgu.lan>. The container uses the database credentials in your private `.env`, connects to the shared MySQL server, and preserves uploaded files in the `opts_storage` Docker volume. Startup runs pending migrations and seeds reference data only when the roles table is empty. The web server, queue worker, scheduler, and Reverb run inside the `opts2026` container.

Run the same command after changing the app to rebuild its source and frontend assets. Single sign-on also requires active `SSO_CLIENT_ID` and `SSO_CLIENT_SECRET` values in `.env` and the exact callback `https://opts.lgu.lan/auth/sso/callback` registered with SSO.

### Quick Setup

```bash
# Clone and install
git clone <repository-url>
cd opts2026
composer setup
```

The `composer setup` script handles: composer install, .env creation, key generation, migrations, npm install, and build.

### Manual Setup

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate

# Configure database in .env (MySQL or keep SQLite default)
php artisan migrate

# Seed demo data
php artisan db:seed

# Frontend
npm install
npm run build
```

### Development Server

```bash
composer dev
```

This starts all services concurrently:
- Laravel dev server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Log tail (`php artisan pail`)
- Vite HMR (`npm run dev`)

### Real-time Notifications (Optional)

```bash
php artisan reverb:start
```

Starts the WebSocket server on port 8080. The app works without Reverb — notifications fall back to Inertia shared props on page navigation.

## Accounts and office directory

SSO owns accounts, office assignments, and application grants. Seeding does not create users or operational offices. Sign in through the SSO portal with an OPTS grant. See [SSO directory and access mapping](docs/sso-directory-access.md) for role mapping, automatic refresh, and local acceptance testing.

## Testing

### Backend Tests

```bash
# Run all tests
composer test

# Run a specific test file
php artisan test tests/Feature/TransactionEndorseTest.php

# Run a specific test method
php artisan test --filter="test_endorser_can_endorse_transaction"
```

**Coverage**: 54 test files (8 unit + 46 feature)

### E2E Tests

```bash
# Install browsers (first time)
npx playwright install

# Run E2E tests
npm run test:e2e

# Run with UI mode
npm run test:e2e:ui
```

**Coverage**: 8 test suites (smoke, admin, workflow, state machine, etc.)

### Code Formatting

```bash
./vendor/bin/pint
```

## Project Structure

```
app/
├── Http/Controllers/        # 14 controllers (CRUD + transaction actions)
├── Models/                  # 16 Eloquent models
├── Notifications/           # 4 notification classes (broadcast + database)
├── Services/                # Business logic (EndorsementService, etc.)
resources/js/
├── Components/              # Reusable React components
│   └── ui/                  # shadcn/ui primitives
├── Layouts/                 # App layout with nav + notification bell
├── Pages/                   # Inertia page components
│   ├── Admin/               # User & workflow management
│   ├── Dashboard/           # Dashboard panels
│   ├── Procurements/        # Procurement CRUD
│   ├── PurchaseRequests/    # PR management
│   ├── PurchaseOrders/      # PO management
│   ├── Vouchers/            # Voucher management
│   ├── Transactions/        # Transaction list & pending receipts
│   └── Notifications/       # Notification center
├── types/                   # TypeScript type definitions
└── bootstrap.ts             # Axios + Laravel Echo initialization
database/
├── migrations/              # 26+ migrations
└── seeders/                 # Demo data seeders
tests/
├── Unit/                    # Service-level unit tests
├── Feature/                 # HTTP/integration tests
└── e2e/                     # Playwright E2E tests
docs/
├── stories/                 # 35 user stories (BMad methodology)
└── qa/gates/                # QA gate review files
```

## Workflows

Administrators configure PR, PO, and voucher workflows using active SSO offices. Existing workflows and transaction history remain stored. Workflows containing inactive or unmapped offices cannot be assigned to new transactions.

Each step has configurable expected turnaround days for SLA tracking. Default seeding does not create workflows.

## Environment Configuration

Key environment variables beyond Laravel defaults:

```env
# Broadcasting (real-time notifications)
BROADCAST_CONNECTION=reverb

# Reverb WebSocket Server
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080

# Frontend WebSocket client (exposed to Vite)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# OPTS-specific
OPTS_IDLE_THRESHOLD_DAYS=2    # Days before a transaction is flagged as stagnant
```

## Scheduled Commands

```bash
# Check for overdue transactions and send notifications (runs daily at 8:00 AM)
php artisan opts:check-overdue
```

## License

This project is proprietary software developed for LGU procurement tracking.
