# Tech Stack

## Definitive Technology Choices

| Category | Technology | Version | Justification |
|----------|-----------|---------|---------------|
| **Backend Framework** | Laravel | 12.x | Latest stable version with React starter kit, Inertia.js support, improved performance, includes Breeze auth scaffolding |
| **Backend Language** | PHP | 8.2+ | Required for Laravel 12.x, provides modern language features (enums, readonly properties, union types) |
| **Database** | MySQL | 8.0+ | Window functions, JSON support, proven reliability for government/audit use cases, 7-year retention support (NFR14) |
| **ORM** | Eloquent | (Laravel) | Built-in Laravel ORM with relationship management, soft deletes, query builder |
| **Frontend Framework** | React | 19 | Latest stable version included in Laravel 12.x Breeze React starter, TypeScript support, modern hooks API |
| **Type System** | TypeScript | 5.x | Type safety across frontend, improved IDE support, reduced runtime errors, Breeze React starter default |
| **SSR Bridge** | Inertia.js | 1.x | Server-side rendering bridge between Laravel and React, eliminates traditional REST API layer, typed props |
| **UI Component Library** | Shadcn/UI | Latest | Included by default in Laravel 12.x Breeze React starter, built on Radix UI primitives (WCAG 2.1 AA accessible), Tailwind-based |
| **CSS Framework** | Tailwind CSS | 3.x | Utility-first CSS framework, included in Breeze starter, responsive design, theme customization |
| **Icon Library** | Lucide React | Latest | Shadcn/UI integrated icon library, consistent design language, tree-shakeable |
| **Build Tool** | Vite | 5.x | Laravel 12.x default bundler (replaces Laravel Mix), fast HMR, optimized production builds |
| **Package Manager** | NPM | 10.x | Node package management (or pnpm/yarn alternative) |
| **PHP Package Manager** | Composer | 2.x | PHP dependency management |
| **Authentication** | Laravel Breeze | (Laravel 12.x) | Session-based auth scaffolding included in starter kit, supports login/register/password reset |
| **Authorization/RBAC** | Spatie Laravel Permission | 6.x | Industry-standard RBAC package, supports roles (Viewer, Endorser, Administrator) and permissions (FR24, FR25) |
| **Validation** | Laravel Form Requests | (Laravel) | Server-side validation with custom request classes, integrates with Inertia error handling |
| **Queue System** | Laravel Queue | (Laravel) | Background job processing for notifications, exports; database driver for dev, Redis for production |
| **Cache** | Redis | 7.x | Cache, sessions, and queue backend in production; file cache acceptable for dev |
| **Session Storage** | Database/Redis | - | Database sessions for dev, Redis sessions for production performance |
| **Email** | Laravel Mail | (Laravel) | Email notifications (Phase 2 feature), queue-backed sending |
| **Notifications** | Laravel Notifications | (Laravel) | Database-backed notification system for bell menu (FR31), supports multiple channels |
| **Logging** | Laravel Log | (Laravel) | Application logging with daily rotation, separate audit trail table (NFR4, NFR10) |
| **Error Tracking (Dev)** | Laravel Telescope | (Laravel) | Real-time debugging, query profiling, exception tracking in development |
| **Metrics (Production)** | Laravel Pulse | (Laravel 12.x) | Server and application metrics, performance monitoring (NFR15) |
| **Code Style (PHP)** | Laravel Pint | (Laravel) | PHP code formatter based on PHP-CS-Fixer, Laravel conventions |
| **Code Style (JS/TS)** | ESLint + Prettier | Latest | TypeScript/React linting and formatting |
| **Testing (Backend)** | PHPUnit / Pest | 10.x / 2.x | Laravel feature/unit testing, Pest for expressive syntax (optional) |
| **Testing (Frontend)** | Vitest | 1.x | Modern Vite-native test runner replacing Jest, React Testing Library integration |
| **E2E Testing** | Playwright | 1.x | Cross-browser end-to-end testing with TypeScript support |
| **Web Server** | Nginx | 1.24+ | Production reverse proxy with PHP-FPM, SSL/TLS termination |
| **Process Manager** | Supervisor | 4.x | Laravel queue worker process management in production |
| **Development Environment** | Laravel Sail / Herd | Latest | Docker-based (Sail) or native (Herd) local development environment |
| **Version Control** | Git | 2.x | Source code version control |
| **Backup** | Custom + mysqldump | - | Daily automated MySQL backups with 30-day retention (NFR6: RPO ≤ 24h, RTO ≤ 4h) |

## Technology Rationale Summary

**Why Laravel 12.x + React 19 via Inertia.js:**
- **Rapid Development:** Breeze starter eliminates 40+ hours of authentication/UI setup
- **Type Safety:** TypeScript across full stack reduces bugs and improves maintainability
- **Monolithic Simplicity:** Inertia eliminates need for separate REST API backend, reducing code duplication
- **Scalability:** Proven stack handling 200+ concurrent users with proper caching (Redis) and database optimization
- **Ecosystem Maturity:** Laravel's extensive package ecosystem (Spatie Permission, Telescope, Pulse) accelerates development
- **Accessibility:** Shadcn/UI built on Radix UI ensures WCAG 2.1 AA compliance out of the box (NFR9)

**Why MySQL 8.0+ (not PostgreSQL):**
- PRD Technical Assumptions explicitly specify MySQL
- Window functions and JSON support required for reporting queries
- Familiar to most LGU IT teams
- Excellent Laravel/Eloquent integration

**Why Monolithic Architecture (not Microservices):**
- 200 concurrent users and 100k transactions fit well within single-server capacity
- Simplified deployment and operations for LGU context (limited DevOps resources)
- Faster development velocity for MVP
- Can extract microservices later if specific scaling bottlenecks emerge

**Why Spatie Laravel Permission:**
- De facto standard for Laravel RBAC (10M+ downloads)
- Supports role-based (Viewer, Endorser, Admin) and permission-based access control
- Gate/Policy integration for controller/Blade/React authorization
- Database-backed permissions with caching for performance

**Why Vitest (not Jest):**
- Native Vite integration (faster test execution)
- ESM-first design matching Vite's approach
- Same configuration as Vite build, reducing tooling complexity
- Included in modern Laravel 12.x React setups

---
