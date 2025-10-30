# Technical Assumptions

## Repository Structure: Monorepo

The system will use a **monorepo** structure housing both the Laravel backend and React frontend within a single repository. This choice supports:
- Simplified deployment pipeline with atomic commits across frontend/backend
- Easier coordination of API contracts and UI changes via TypeScript interfaces
- Single version control history for full-stack features
- Inertia.js architecture naturally fits monorepo pattern (server-rendered React via Laravel)

**Rationale:** Given Laravel 12.x + Inertia + React stack, monorepo is the conventional and most maintainable approach. Inertia bridges backend and frontend without traditional API separation, making polyrepo unnecessarily complex.

## Service Architecture

**Monolithic Laravel application** with server-side rendered React components via Inertia.js.

**Architecture Details:**
- **Backend Framework:** Laravel 12.x (latest stable version as of October 2025)
- **Frontend Framework:** React 19 via Inertia.js React adapter
- **Type Safety:** TypeScript (included by default in Laravel 12.x Breeze React starter)
- **Routing:** Laravel handles all routes; Inertia renders React components server-side without traditional SPA routing
- **Data Layer:** Eloquent ORM with MySQL 8.0+
- **API Style:** RESTful endpoints as documented in Brief section 13, with Inertia handling most UI interactions via props (reducing traditional REST API surface)
- **State Management:** React component state + Inertia page props (TypeScript-typed); avoid Redux/Zustand unless complexity demands it

**Rationale:** Monolith with Inertia provides rapid development, simpler deployment, and sufficient scalability for LGU use case (target: 200 concurrent users, 100k transactions per NFR5). Microservices would add unnecessary complexity for MVP scope. TypeScript provides type safety across the full stack, reducing runtime errors.

**Setup Sequence:**
1. Install Laravel 12.x: `composer create-project laravel/laravel opts2026`
2. Install Laravel Breeze with React/Inertia: `composer require laravel/breeze --dev && php artisan breeze:install react`
3. This automatically configures:
   - Inertia.js server-side (https://inertiajs.com/server-side-setup)
   - Inertia.js client-side (https://inertiajs.com/client-side-setup)
   - React 19 with TypeScript
   - Tailwind CSS
   - Shadcn/UI component library
   - Vite build configuration
4. Install dependencies: `npm install`

## Testing Requirements

**Full Testing Pyramid** approach with TypeScript support:

- **Unit Tests:**
  - **Backend:** Laravel Feature/Unit tests using PHPUnit/Pest for models, services, business logic
  - **Frontend:** React component tests using Vitest + React Testing Library with TypeScript
- **Integration Tests:** Laravel Feature tests covering API endpoints, database interactions, workflow state transitions, RBAC enforcement
- **End-to-End Tests:** Browser automation testing critical user journeys (create procurement → PR → PO → VCH workflows) using Laravel Dusk or Playwright with TypeScript
- **Type Safety Tests:** TypeScript compilation as a testing layer (type errors caught at build time)
- **Manual Testing:** Convenience database seeding and factory methods for manual workflow testing

**Testing Priorities:**
- High coverage for state machine transitions (FR8, FR9, FR10)
- Comprehensive testing of reference number uniqueness and generation (FR4, FR5, FR6)
- Validation testing for dependency enforcement (FR2, FR3)
- RBAC permission testing across all endpoints (FR25)
- Audit trail integrity testing (NFR4)
- Out-of-workflow detection and notification testing (FR14, FR15)

**Rationale:** Full pyramid ensures confidence in complex business rules (workflows, state machines, dependencies). Government/audit context demands high reliability. Laravel 12.x + TypeScript provides excellent testing foundations. Vitest replaces Jest as the modern, faster testing framework for Vite projects.

## Additional Technical Assumptions and Requests

**Database:**
- **MySQL 8.0+** (required for window functions, JSON support, better performance)
- Use Laravel migrations for schema version control with rollback support
- Implement database unique constraints for reference number uniqueness (FR6, NFR4)
- Use database transactions with row-level locking for atomic reference number generation (Brief section 15)
- Implement soft deletes (`deleted_at` timestamps) for audit trail preservation
- Database indexing strategy:
  - Foreign keys (office_id, user_id, procurement_id, etc.)
  - Status fields, reference numbers, timestamps
  - Composite indexes for reference number uniqueness checks
  - Full-text indexes for search functionality if needed

**Authentication & Authorization:**
- **Laravel Breeze** (included in Laravel 12.x React starter) for authentication scaffolding
- **Spatie Laravel Permission** package for RBAC implementation (FR24, FR25)
  - Role-based permissions: Viewer, Endorser, Administrator
  - Permission-based page access control
- Session-based authentication (Inertia convention, not token-based)
- Password hashing via Laravel's bcrypt/argon2id (NFR2)
- CSRF protection via Laravel middleware (enabled by default)

**Frontend Styling & Components:**
- **Tailwind CSS** (included by default with Laravel 12.x Breeze React starter)
- **Shadcn/UI** (included by default with Laravel 12.x Breeze React starter)
  - Built on Radix UI primitives for accessible components (NFR9 WCAG 2.1 AA)
  - Pre-configured components: Form, Button, Dialog, Dropdown, Table, Toast, Badge, Card, Select, Input, Textarea
  - Customizable via Tailwind classes
  - Copy-paste component approach with full source control
- **Icons:** Lucide React (Shadcn's integrated icon library, included by default)
- **Fonts:** Inter or similar modern sans-serif for government/enterprise aesthetic
- **Color Palette:** Define status colors in Tailwind config:
  - Success/Completed: Green shades
  - In Progress: Blue shades
  - Warning/On Hold: Yellow/Amber shades
  - Error/Cancelled/Overdue: Red shades
  - Neutral: Gray shades

**TypeScript Configuration:**
- Strict mode enabled for maximum type safety
- Shared types between Laravel backend (PHP) and React frontend (TS) via:
  - Laravel TypeScript package (e.g., `laravel-typescript`) or manual type definitions
  - Inertia.js typed page props
  - API response type definitions
- Type definitions for all domain models (Procurement, PR, PO, VCH, Workflow, etc.)

**Observability & Logging:**
- **Laravel Log** channels for application logging (NFR10)
  - Daily log rotation
  - Separate channels for audit trail, application errors, performance
- **Error Tracking:**
  - Development: Laravel Telescope (real-time debugging)
  - Production: Sentry or Flare for error tracking and monitoring
- **Performance Monitoring:**
  - Laravel Pulse for server and application metrics
  - Query performance tracking via Telescope/Debugbar (dev)
- **Audit Logging:** Custom audit log table for immutable event tracking (NFR4)
  - Log all state changes with user, office, IP, timestamp, reason
  - Separate from application logs for compliance

**Deployment & Infrastructure:**
- **Target Environment:** Linux server (Ubuntu 22.04 LTS or similar)
- **Web Server:** Nginx with PHP 8.2+ FPM
- **Process Manager:** Supervisor for Laravel queue workers (notifications)
- **Daily Automated Backups:**
  - MySQL database dumps with 30-day retention
  - Application files backup (weekly)
  - RPO ≤ 24h, RTO ≤ 4h (NFR6)
- **SSL/TLS:** Let's Encrypt or organizational certificate (NFR3 HTTPS enforcement)
- **Environment Configuration:**
  - `.env` files for environment-specific config (never commit)
  - Laravel config caching in production
- **PHP Version:** PHP 8.2+ (required for Laravel 12.x)

**Development Tooling:**
- **Package Management:**
  - Composer for PHP dependencies
  - NPM (or pnpm/yarn) for JavaScript dependencies
- **Asset Bundling:** Vite (Laravel 12.x default, replaces Laravel Mix/Webpack)
- **Version Control:** Git with `.gitignore` for Laravel projects
- **Code Quality:**
  - **Backend:** Laravel Pint (PHP code style) or PHP-CS-Fixer
  - **Frontend:** ESLint + Prettier for TypeScript/React
  - Pre-commit hooks via Husky (optional but recommended)
- **Development Environment:**
  - Laravel Sail (Docker-based local development) or
  - Laravel Herd (native PHP/MySQL environment)
  - Laragon/XAMPP alternatives for Windows

**Localization & Formatting:**
- Laravel's localization features for date formatting (YYYY-MM-DD per NFR8)
- Number formatting for currency (ABC amount, Contract Price per NFR7)
  - PHP `number_format()` or Laravel `Number` facade
  - TypeScript Intl.NumberFormat for client-side formatting
- Timezone: Configure server timezone to match LGU location (Philippines assumed)
- Prepared for multi-language support (out of MVP scope but architecture-ready)

**Notification System:**
- Database-backed notifications using Laravel Notifications table
- Bell icon badge count via Inertia shared props pattern (global data)
- Notification types: Out-of-workflow, Received items, Overdue items, Completion, Admin notices
- Real-time updates: Polling (simple MVP approach) or Laravel Reverb/Pusher (Phase 2)
- Email notifications (Phase 2): Laravel Mail with queue processing

**File Storage:**
- Laravel Filesystem abstraction ready for future file attachments (Phase 2: PR docs, PO, invoices)
- Local storage driver for MVP
- S3-compatible driver prepared for production scaling (MinIO, AWS S3, etc.)

**Caching & Performance:**
- **Query Optimization:**
  - Eloquent eager loading to prevent N+1 queries (critical for timeline/audit trail views)
  - Laravel query caching for static repositories (Offices, Suppliers, Particulars, etc.)
- **Cache Driver:**
  - File cache for development
  - Redis for production (session, cache, queue)
- **Session Storage:** Database or Redis in production
- **Queue System:**
  - Database queue driver for MVP
  - Redis queue for production (notifications, exports, heavy processing)

**Security Considerations:**
- CSRF protection via Laravel middleware (enabled by default with Inertia)
- SQL injection prevention via Eloquent ORM and prepared statements
- XSS prevention via React's default JSX escaping and Laravel Blade escaping
- Input validation using Laravel Form Requests with TypeScript type validation on frontend
- Rate limiting on sensitive endpoints (login, exports, API endpoints)
- IP address logging for audit trail (NFR4)
- Content Security Policy (CSP) headers
- HTTPS enforcement in production (redirect HTTP to HTTPS)
- Security headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy

**API Documentation:**
- TypeScript types serve as API contract documentation
- Consider Laravel Scribe or similar for REST API endpoint documentation if external API access needed (likely not needed for Inertia-only app)
- Inertia page props documentation via JSDoc comments in TypeScript

**Development Workflow:**
- Feature branch workflow with pull requests
- Continuous Integration: GitHub Actions or GitLab CI for automated testing
- Code review required before merging to main/production branches
- Semantic versioning for releases
- Database seeding for development and testing environments

---
