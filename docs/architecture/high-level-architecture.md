# High Level Architecture

## Technical Summary

OPTS is a **monolithic full-stack web application** built with Laravel 12.x (PHP 8.2+) and React 19 (TypeScript) connected via Inertia.js. The architecture follows a traditional three-tier pattern with clear separation of concerns:

**Presentation Layer (Frontend)**
- React 19 components with TypeScript
- Shadcn/UI component library (Radix UI primitives)
- Tailwind CSS for styling
- Client-side rendering via Inertia.js page components

**Application Layer (Backend)**
- Laravel 12.x Controllers handling HTTP requests
- Service classes for business logic (Workflow Engine, Reference Number Generator, ETA Calculator)
- Eloquent Models with typed relationships
- Form Request validation
- Middleware for authentication and RBAC

**Data Layer**
- MySQL 8.0+ relational database
- Eloquent ORM for data access
- Database transactions for atomic operations
- Soft deletes for audit trail preservation

**Cross-Cutting Concerns**
- Session-based authentication (Laravel Breeze)
- RBAC via Spatie Laravel Permission
- Audit logging service (immutable trail)
- Notification system (database-backed)
- Queue workers for async processing (notifications, exports)

## Platform and Infrastructure

**Development Environment:**
- Laravel Sail (Docker) or Laravel Herd (native PHP/MySQL)
- Node.js 18+ for Vite and NPM packages
- MySQL 8.0+ local database
- Redis (optional for development, required for production)

**Production Environment:**
- Linux server (Ubuntu 22.04 LTS recommended)
- Nginx web server with PHP 8.2+ FPM
- MySQL 8.0+ database server
- Redis for cache, sessions, and queue backend
- Supervisor for Laravel queue worker process management
- SSL/TLS via Let's Encrypt or organizational certificate

**Performance Targets (NFR5):**
- 200 concurrent users
- 100,000 transactions in database
- P95 page load time < 2.5 seconds
- Database query P95 < 1 second (NFR15)

## Repository Structure: Monorepo

```
opts2026/                          # Project root
├── app/                           # Laravel application
│   ├── Http/
│   │   ├── Controllers/          # Inertia controllers
│   │   ├── Middleware/           # RBAC, CSRF, Auth
│   │   └── Requests/             # Form validation
│   ├── Models/                   # Eloquent models
│   ├── Services/                 # Business logic services
│   │   ├── WorkflowEngine.php
│   │   ├── ReferenceNumberGenerator.php
│   │   ├── ETACalculator.php
│   │   └── AuditLogger.php
│   ├── Events/                   # Domain events
│   ├── Listeners/                # Event handlers
│   └── Policies/                 # Authorization policies
├── database/
│   ├── migrations/               # Schema migrations
│   ├── seeders/                  # Development/test data
│   └── factories/                # Model factories
├── resources/
│   ├── js/
│   │   ├── Pages/                # Inertia page components (React)
│   │   ├── Components/           # Reusable React components
│   │   ├── Layouts/              # Layout components
│   │   ├── Types/                # TypeScript type definitions
│   │   └── app.tsx               # Inertia app entry point
│   ├── css/
│   │   └── app.css               # Tailwind imports
│   └── views/
│       └── app.blade.php         # Root Inertia template
├── routes/
│   ├── web.php                   # All Inertia routes
│   └── api.php                   # REST API routes (minimal)
├── tests/
│   ├── Feature/                  # Laravel feature tests
│   ├── Unit/                     # Laravel unit tests
│   └── Browser/                  # Playwright E2E tests
├── config/                       # Laravel configuration files
├── storage/                      # Logs, cache, sessions
├── public/                       # Web root (built assets)
├── vite.config.js               # Vite bundler config
├── tsconfig.json                # TypeScript config
├── tailwind.config.js           # Tailwind CSS config
├── phpunit.xml                  # PHP testing config
├── vitest.config.js             # Frontend testing config
└── package.json                 # Node dependencies
```

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT BROWSER                          │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  React 19 Components (TypeScript)                         │  │
│  │  • Dashboard, Procurement List/Detail, Transaction Views │  │
│  │  • Shadcn/UI Components (Forms, Tables, Dialogs)         │  │
│  │  • Tailwind CSS Styling                                  │  │
│  └───────────────────────┬──────────────────────────────────┘  │
└────────────────────────────┼────────────────────────────────────┘
                             │ HTTPS
                             │ Inertia.js Protocol
                             │ (JSON page props + XHR)
┌────────────────────────────┼────────────────────────────────────┐
│                    LARAVEL 12.x SERVER                          │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │         NGINX + PHP-FPM                         │            │
│  └──────────────────────┬─────────────────────────┘            │
│                         │                                       │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │  MIDDLEWARE STACK                               │            │
│  │  • Session Auth • CSRF • RBAC (Spatie)         │            │
│  └──────────────────────┬─────────────────────────┘            │
│                         │                                       │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │  INERTIA CONTROLLERS                            │            │
│  │  • ProcurementController                        │            │
│  │  • TransactionController (PR/PO/VCH)           │            │
│  │  • WorkflowController                           │            │
│  │  • DashboardController                          │            │
│  │  • AdminController (Users, Repositories)       │            │
│  └──────────────────────┬─────────────────────────┘            │
│                         │                                       │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │  SERVICE LAYER (Business Logic)                │            │
│  │  • WorkflowEngine: State machine transitions   │            │
│  │  • ReferenceNumberGenerator: Atomic sequences │            │
│  │  • ETACalculator: SLA/delay computation       │            │
│  │  • AuditLogger: Immutable trail recording     │            │
│  └──────────────────────┬─────────────────────────┘            │
│                         │                                       │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │  ELOQUENT MODELS                                │            │
│  │  • Procurement, PR, PO, VCH (polymorphic)      │            │
│  │  • Workflow, WorkflowStep, TransactionAction   │            │
│  │  • Office, User, Supplier, Particular          │            │
│  └──────────────────────┬─────────────────────────┘            │
│                         │                                       │
│  ┌──────────────────────┴─────────────────────────┐            │
│  │  EVENT/NOTIFICATION SYSTEM                      │            │
│  │  • Laravel Events + Listeners                   │            │
│  │  • Database Notification Queue                  │            │
│  └──────────────────────┬─────────────────────────┘            │
└─────────────────────────┼───────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          │               │               │
┌─────────┴────────┐ ┌───┴────────┐ ┌───┴─────────┐
│  MYSQL 8.0+      │ │   REDIS    │ │  SUPERVISOR │
│  • Procurements  │ │ • Cache    │ │ • Queue     │
│  • Transactions  │ │ • Sessions │ │   Workers   │
│  • Workflows     │ │ • Queues   │ └─────────────┘
│  • Audit Logs    │ └────────────┘
│  • Repositories  │
└──────────────────┘
```

## Architectural Patterns

**1. Monolithic Layered Architecture**
- **Why:** Simplifies deployment, reduces operational complexity, sufficient for 200 concurrent users
- **Trade-off:** Tighter coupling vs. microservices, but appropriate for LGU scale

**2. Server-Side Rendering via Inertia.js**
- **Why:** React components rendered server-side with Laravel routing, no separate SPA backend
- **Benefit:** Type-safe props, simpler data fetching, SEO-friendly, reduced API surface

**3. Service Layer Pattern**
- **Why:** Encapsulates complex business logic (workflow engine, reference generation) outside controllers
- **Benefit:** Testable, reusable, keeps controllers thin

**4. Repository Pattern (Laravel Eloquent Models)**
- **Why:** Eloquent ORM provides built-in repository-like interface with relationships
- **Trade-off:** No separate repository classes unless complexity demands (YAGNI principle)

**5. Event-Driven Notifications**
- **Why:** Decouple notification logic from business operations using Laravel Events
- **Benefit:** Async processing via queues, extensible for future notification channels

**6. Policy-Based Authorization (RBAC)**
- **Why:** Spatie Laravel Permission + Laravel Policies enforce FR24/FR25 requirements
- **Benefit:** Declarative permissions, centralized authorization logic

**7. Database Transaction Pattern**
- **Why:** Reference number generation requires atomic read-increment-write operations
- **Implementation:** Laravel DB::transaction() with row locking (SELECT FOR UPDATE)

**8. State Machine Pattern**
- **Why:** Transaction status transitions follow strict rules (FR8, FR9, FR10)
- **Implementation:** WorkflowEngine service with explicit transition methods

---
