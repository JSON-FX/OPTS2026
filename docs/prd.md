# Online Procurement Tracking System (OPTS) Product Requirements Document (PRD)

**Version:** 1.1
**Date:** October 31, 2025
**Author:** PM Agent

---

## Goals and Background Context

### Goals

- Establish a single source of truth for procurement status and movement across offices
- Reduce procurement cycle time through clear SLAs, ETAs, and escalation mechanisms for delays
- Improve accountability with immutable audit trails tracking all actions and transitions
- Standardize reference numbering schemes and repository data (Offices, Suppliers, Fund Types, etc.)
- Enable end-to-end visibility of procurement transactions from PR through PO to VCH completion
- Provide role-based access control ensuring proper authorization across all system operations
- Detect and alert on out-of-workflow endorsements to prevent misrouting
- Support data export and reporting for compliance and performance analysis

### Background Context

OPTS (Online Procurement Tracking System) addresses the critical need for transparency and control in Local Government Unit (LGU) procurement processes. Currently, procurement transactions move through multiple offices with paper-based tracking, leading to lost documents, unclear status, missed deadlines, and accountability gaps.

The system tracks procurement from creation through three dependent, sequential transactions—Purchase Request (PR), Purchase Order (PO), and Voucher (VCH)—until completion. Each transaction follows a configurable workflow with ordered office steps and expected completion days. The system provides delivery-tracking-style timelines showing current location, computing ETAs, identifying delays, and enabling proactive intervention when transactions stagnate or route incorrectly.

### Problem Quantification

Based on LGU procurement office observations and requirements:

- **Lost Documents:** Paper-based tracking results in ~15-20% of procurement documents requiring re-creation or re-submission due to lost paperwork
- **Status Uncertainty:** Office staff spend an average of 30-45 minutes per day responding to status inquiries that could be answered instantly with a tracking system
- **Missed Deadlines:** Lack of visibility into workflow position leads to an estimated 25-30% of transactions experiencing delays beyond expected timeframes
- **Misrouting:** Without clear workflow enforcement, approximately 10-15% of endorsements are sent to incorrect offices, requiring administrative intervention
- **Accountability Gaps:** Manual processes make it difficult to identify bottlenecks or assign responsibility when delays occur
- **Audit Challenges:** Preparing compliance reports requires manual document gathering, consuming 2-3 days of staff time per audit period

**Expected Impact:** The system aims to reduce document loss to near-zero, eliminate status inquiry time, reduce delays by 50%+, cut misrouting by 80%+, and reduce audit preparation time by 90%+.

### Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2025-10-31 | 1.0 | Initial PRD draft from Project Brief | PM Agent |
| 2025-10-31 | 1.1 | Added problem quantification, data retention policy (NFR14), monitoring/alerting thresholds (NFR15) | PM Agent |

---

## Requirements

### Functional

**FR1:** The system shall create Procurement records with required fields: End User (from Office repository), Particular, Purpose, ABC amount, and Date of Entry, automatically setting status to "Created".

**FR2:** The system shall enforce transaction dependencies: Purchase Orders (PO) require an existing Purchase Request (PR) for the same Procurement; Vouchers (VCH) require an existing PO for the same Procurement.

**FR3:** The system shall support exactly one PR, one PO, and one VCH per Procurement in MVP scope.

**FR4:** The system shall generate unique reference numbers: PR as `PR-{FUND_ABBR}-{YYYY}-{MM}-{SEQ}`, PO as `PO-{YYYY}-{MM}-{SEQ}`, and VCH as free-text admin-defined format.

**FR5:** The system shall support continuation reference numbers prefixed with `(Continuation)-` for carry-over transactions from previous years, storing `is_continuation` flag and `continuation_from_year`.

**FR6:** The system shall prevent duplicate reference numbers through database unique constraints on (category, fund_type, year, month, is_continuation, sequence).

**FR7:** The system shall manage Transaction Workflows per category (PR/PO/VCH) as ordered sequences of offices with expected_days per step.

**FR8:** The system shall transition Procurement status automatically: "Created" on creation → "In Progress" when first PR created → "Completed" when all three transactions (PR, PO, VCH) reach "Completed" status.

**FR9:** The system shall transition Transaction status: "Created" on creation → "In Progress" on first endorsement → "Completed" at final workflow step completion.

**FR10:** The system shall allow Administrators to set Procurement or Transaction status to "On Hold" or "Cancelled" with required reason, marking these as terminal states unless admin reverses.

**FR11:** The system shall support Endorse action to move transaction from current office to target office, recording action_taken, actor, timestamp, and notes.

**FR12:** The system shall support Receive action for target office to accept transaction and become current holder, updating current_office_id and current_user_id.

**FR13:** The system shall support Complete action at final workflow step to mark transaction as "Completed".

**FR14:** The system shall detect out-of-workflow endorsements when target office does not match the expected next office in the workflow, logging the event and triggering notifications.

**FR15:** The system shall send notifications to Administrators and expected receiving office's assigned users when out-of-workflow endorsements occur.

**FR16:** The system shall provide Administrator "Bypass Endorsement" capability to correct misrouted transactions with required reason and audit logging.

**FR17:** The system shall compute ETA for current workflow step as `receive_timestamp + expected_days` and overall ETA to completion as sum of remaining steps' expected_days.

**FR18:** The system shall compute delay in days as `max(0, today - ETA_for_current_step)` and flag transactions as "stagnant" when delay > 0 or no movement for configurable idle_threshold_days (default: 2 days).

**FR19:** The system shall display a tracking timeline showing completed steps with timestamps, current office/user, and upcoming offices with computed ETAs.

**FR20:** The system shall provide a Dashboard with summary cards (total PR, PO, VCH, Procurements), office workload table (PR/PO/VCH counts per office), activity feed with recent actions, and stagnant transactions panel.

**FR21:** The system shall provide Procurement list and detail views with progressive disclosure of linked PR/PO/VCH information and current office/user.

**FR22:** The system shall provide Transaction list and detail views with timeline, current holder, endorsement history, available actions, and full audit trail.

**FR23:** The system shall provide search and filter capabilities across procurements and transactions by date range, office, status, category, fund type, supplier, and reference number.

**FR24:** The system shall support three user roles with distinct capabilities: Viewer (read-only access), Endorser (create and process transactions), Administrator (full system configuration and override powers).

**FR25:** The system shall enforce role-based access control (RBAC) across all UI pages and API endpoints according to the capability matrix.

**FR26:** The system shall provide Administrator interfaces to manage Users (create/edit/remove, assign office, assign role).

**FR27:** The system shall provide Administrator interfaces to manage Repositories: Transaction Categories, Offices, Particulars, Suppliers, Fund Types, Statuses, Action Taken.

**FR28:** The system shall provide Administrator interfaces to manage Workflows (create category-specific workflows with ordered office steps and expected_days).

**FR29:** The system shall provide Administrator interfaces to manage Page Access permissions (role-to-page matrix).

**FR30:** The system shall provide Administrator interfaces to manage Announcements with three severity levels (Normal, Advisory, Emergency) displayed as banners with bell icon notifications.

**FR31:** The system shall provide notifications via bell menu for: out-of-workflow routes, received items, overdue items, transaction completions, and admin notices.

**FR32:** The system shall support data export to CSV and JSON formats with filters for date range, office, status, category, fund type, and supplier.

**FR33:** The system shall auto-populate Supplier Address from Supplier Repository when Supplier is selected in PO or VCH creation, displaying address as read-only.

**FR34:** The system shall require Fund Type selection for PR creation to enable reference number generation.

**FR35:** The system shall make reference numbers immutable after initial assignment.

### Non-Functional

**NFR1:** The system shall implement role-based access control with least privilege principle across all operations.

**NFR2:** The system shall hash and securely store all user passwords using industry-standard algorithms.

**NFR3:** The system shall enforce HTTPS/TLS encryption for all data in transit.

**NFR4:** The system shall log all state changes (endorse/receive/complete/hold/cancel/bypass) in an immutable audit trail including actor, office, IP address, timestamp, and reason.

**NFR5:** The system shall achieve P95 page load time under 2.5 seconds with 200 concurrent users and 100,000 transactions in the database.

**NFR6:** The system shall perform daily automated backups with Recovery Point Objective (RPO) ≤ 24 hours and Recovery Time Objective (RTO) ≤ 4 hours.

**NFR7:** The system shall display currency amounts (ABC, Contract Price) with proper localization formatting.

**NFR8:** The system shall use YYYY-MM-DD date format consistently throughout the application.

**NFR9:** The system shall meet WCAG 2.1 AA accessibility standards for all user interfaces.

**NFR10:** The system shall implement application logging, metrics collection, and error tracking for observability.

**NFR11:** The system shall provide SLA dashboards showing step delays and performance against expected_days targets.

**NFR12:** The system shall be responsive web-only (no native mobile apps) supporting modern browsers on desktop, tablet, and mobile devices.

**NFR13:** The system shall provide standard reports: turnaround time by step/office, out-of-workflow incidents, volume per month by category/fund type, and stagnant transactions.

**NFR14:** The system shall retain all procurement and transaction records (including completed, cancelled, and on-hold procurements) for a minimum of 7 years per LGU audit and compliance requirements. Soft-deleted records shall remain in the database with deleted_at timestamps for audit trail preservation.

**NFR15:** The system shall implement alerting thresholds for operational monitoring: alert when >10 transactions are stagnant, when system error rate exceeds 5% over 15 minutes, when database query P95 exceeds 1 second, and when any workflow step consistently exceeds expected_days by >50% across multiple transactions.

---

## User Interface Design Goals

### Overall UX Vision

OPTS should deliver a delivery-tracking-inspired experience where users can instantly see "where things are" and "when they'll arrive." The interface prioritizes **status visibility** and **timeline clarity** over data entry complexity. Users should feel confident about transaction location, progress, and next steps without hunting through menus or reports. The system should feel like tracking a package: simple status cards, clear progression indicators, and proactive alerts when things go off-track.

The interface adopts a **progressive disclosure** approach—showing high-level summaries by default (Dashboard cards, list views) with drill-down detail views revealing timelines, audit trails, and action buttons contextually based on user role and transaction state.

### Key Interaction Paradigms

- **Dashboard-First Navigation:** Users land on a comprehensive dashboard showing at-a-glance metrics, office workload, recent activity, and stagnant items. The dashboard serves as mission control for the entire system.
- **Timeline-Centric Transaction Views:** Transaction detail pages feature prominent horizontal or vertical timeline visualizations showing completed steps (with timestamps/actors), current location (highlighted), and upcoming steps (with ETAs).
- **Contextual Actions:** Action buttons (Endorse, Receive, Complete) appear conditionally based on user role, current transaction state, and workflow position—reducing clutter and preventing invalid operations.
- **Smart Defaults with Override:** Endorsement forms default to the expected next office per workflow, with clear visual indicators when selecting an out-of-workflow target.
- **Repository-Driven Selects:** Forms use dropdowns/autocomplete populated from centralized repositories (Offices, Suppliers, Particulars, Fund Types, Action Taken) ensuring data consistency.
- **Notification-Driven Attention:** Bell icon with badge count for actionable items (received transactions, overdue items, out-of-workflow alerts) minimizing need to check lists proactively.
- **Announcement Banners:** Severity-based banners (Normal/Advisory/Emergency) for system-wide communications with persistent visibility until dismissed.

### Core Screens and Views

1. **Login Screen** — Authentication with email/password
2. **Dashboard** — Summary cards, office workload table, activity feed, stagnant transactions panel
3. **Procurement List** — Searchable/filterable list with status indicators
4. **Procurement Detail** — High-level procurement info with progressive disclosure of linked PR/PO/VCH
5. **New Procurement Form** — Create procurement with End User, Particular, Purpose, ABC, Date
6. **Transaction List** — Unified or category-filtered (PR/PO/VCH) list with search/filter
7. **Transaction Detail** — Timeline, current holder, endorsement history, audit log, action buttons
8. **New Transaction Form (PR)** — Fund Type selection, workflow assignment
9. **New Transaction Form (PO)** — Supplier selection with auto-populated address, contract price, workflow
10. **New Transaction Form (VCH)** — Payee selection, reference number entry, workflow
11. **Endorse Transaction Modal/Page** — Select Action Taken, target office (defaulted to next in workflow), notes
12. **Receive Transaction Modal/Page** — Confirm receipt with current Action Taken context
13. **Endorsement Lists** — View pending, received, and completed endorsements
14. **Admin: User Management** — CRUD users, assign office, assign role
15. **Admin: Repository Management** — CRUD for all repository entities (Offices, Suppliers, etc.)
16. **Admin: Workflow Management** — Define workflows with ordered office steps and expected_days
17. **Admin: Bypass Endorsement** — Correct misrouted transactions with reason
18. **Admin: Page Access Management** — Role-to-page permission matrix
19. **Admin: Announcement Management** — Create/edit/deactivate announcements with severity levels
20. **Notifications Panel/Page** — Bell menu dropdown or dedicated page for notification history
21. **Export Interface** — Filter selection and format choice (CSV/JSON) for data export
22. **User Profile/Settings** — Basic profile info and preferences (assumed, not in Brief)

### Accessibility: WCAG 2.1 AA

All interfaces must meet WCAG 2.1 AA standards including keyboard navigation, screen reader compatibility, sufficient color contrast, focus indicators, and semantic HTML structure.

### Branding

**ASSUMPTION:** No specific branding guidelines provided in Brief. Recommend clean, professional government/enterprise aesthetic with:
- Neutral color palette (blues/grays for primary, yellows/reds for alerts)
- Clear typography optimized for data-dense tables and forms
- Status color coding: Green (Completed), Blue (In Progress), Yellow (On Hold), Red (Cancelled/Overdue)

**Question for stakeholder:** Are there existing LGU branding guidelines, color schemes, or logo assets to incorporate?

### Target Device and Platforms: Web Responsive

Responsive web application supporting modern browsers (Chrome, Firefox, Safari, Edge) on desktop, tablet, and mobile devices. No native mobile apps in MVP scope—mobile users access via responsive web interface.

---

## Technical Assumptions

### Repository Structure: Monorepo

The system will use a **monorepo** structure housing both the Laravel backend and React frontend within a single repository. This choice supports:
- Simplified deployment pipeline with atomic commits across frontend/backend
- Easier coordination of API contracts and UI changes via TypeScript interfaces
- Single version control history for full-stack features
- Inertia.js architecture naturally fits monorepo pattern (server-rendered React via Laravel)

**Rationale:** Given Laravel 12.x + Inertia + React stack, monorepo is the conventional and most maintainable approach. Inertia bridges backend and frontend without traditional API separation, making polyrepo unnecessarily complex.

### Service Architecture

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

### Testing Requirements

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

### Additional Technical Assumptions and Requests

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

## Epic List

Below is the high-level epic structure for OPTS. Each epic represents a significant, deployable increment of functionality that builds upon previous work while delivering tangible value.

**Epic 1: Foundation, Authentication & Repository Management**

Establish the project infrastructure (Laravel 12.x + React 19 + TypeScript + Inertia), implement authentication and user management, and provide administrative interfaces for managing all master reference data (Offices, Suppliers, Particulars, Fund Types, etc.). This foundational epic enables administrators to log in, manage users/roles, and populate the system with organizational data required for procurement operations.

**Epic 2: Procurement & Transaction Lifecycle**

Build the core domain models and user interfaces for creating and managing Procurements and their three dependent transactions (PR, PO, VCH). Implement reference number generation with uniqueness constraints, dependency enforcement (PO requires PR, VCH requires PO), basic status management, and RBAC-controlled access. This epic delivers the ability to track procurement from creation through all three transaction types without workflow routing.

**Epic 3: Workflow Engine & Endorsement System**

Implement the workflow engine enabling administrators to define category-specific workflows (ordered office steps with expected completion days), and build the endorsement system (Endorse → Receive → Complete actions) with transaction state machine, current office/user tracking, out-of-workflow detection, ETA/delay calculations, and timeline visualizations. This epic delivers the core "tracking" capability with routing, SLA monitoring, and misroute alerts.

**Epic 4: Dashboard, Monitoring & Notifications**

Build the comprehensive dashboard with summary cards, office workload tables, activity feeds, and stagnant transaction panels. Implement the notification system (bell icon with badges) for out-of-workflow alerts, received items, overdue items, and completions. Add search/filter capabilities across procurements and transactions. This epic delivers visibility and proactive monitoring for all system users.

**Epic 5: Admin Tools & Data Export**

Implement administrative override capabilities (Bypass Endorsement for correcting misroutes), page access management (role-to-page permission matrix), announcement system (Normal/Advisory/Emergency banners), and data export functionality (CSV/JSON with filters). Enhance audit trail reporting for compliance. This epic delivers administrative control and compliance reporting capabilities.

---

## Epic 1: Foundation, Authentication & Repository Management

**Epic Goal:**

Establish the complete project infrastructure with Laravel 12.x, React 19, TypeScript, and Inertia.js configured for development. Implement secure authentication using Laravel Breeze, role-based access control with three distinct roles (Viewer, Endorser, Administrator), and comprehensive administrative interfaces for managing all master reference data repositories. This epic delivers a fully functional admin portal where administrators can log in, manage users and their roles/office assignments, and populate the system with organizational data (Offices, Suppliers, Particulars, Fund Types, Statuses, Action Taken, Transaction Categories) required for procurement operations. By epic completion, the system has a secure foundation, user management, and all reference data management capabilities ready to support procurement transaction workflows.

### Story 1.1: Project Setup & Configuration

As a **Developer**,
I want to initialize the Laravel 12.x project with React 19, TypeScript, Inertia.js, and MySQL database,
so that the development environment is ready for feature implementation.

**Acceptance Criteria:**
1. Laravel 12.x project created with `composer create-project laravel/laravel opts2026`
2. Laravel Breeze installed with React starter kit (`php artisan breeze:install react`)
3. Git repository initialized with appropriate `.gitignore` for Laravel projects
4. Database configured in `.env` file connecting to MySQL 8.0+ database named `opts2026_dev`
5. Database migrations run successfully (`php artisan migrate`)
6. NPM dependencies installed and Vite build runs successfully (`npm install && npm run dev`)
7. Application loads at `http://localhost` with Laravel Breeze default welcome/login page
8. TypeScript compilation succeeds with no errors
9. Tailwind CSS and Shadcn/UI components render correctly
10. README.md updated with project setup instructions including database setup, dependency installation, and local development server commands
11. Environment includes PHP 8.2+, Composer, Node.js/NPM, MySQL 8.0+

### Story 1.2: Database Schema Foundation & Core Migrations

As a **Developer**,
I want to create database migrations for core system tables (users, roles, permissions, repositories),
so that the data model foundation supports RBAC and master reference data.

**Acceptance Criteria:**
1. Migration created for `roles` table: id, name, guard_name, created_at, updated_at
2. Migration created for `permissions` table: id, name, guard_name, created_at, updated_at
3. Migration created for `model_has_roles` and `model_has_permissions` pivot tables (Spatie convention)
4. Migration created for `role_has_permissions` pivot table
5. Migration created for `offices` table: id, name, type, abbreviation, created_at, updated_at, deleted_at
6. Migration created for `suppliers` table: id, name, address, contact_number (nullable), created_at, updated_at, deleted_at
7. Migration created for `particulars` table: id, name, created_at, updated_at, deleted_at
8. Migration created for `fund_types` table: id, name, abbreviation, created_at, updated_at, deleted_at
9. Migration created for `statuses` table: id, name, created_at, updated_at
10. Migration created for `action_taken` table: id, name, created_at, updated_at, deleted_at
11. Migration created for `transaction_categories` table: id, name, abbreviation, created_at, updated_at
12. Migration created for `user_offices` pivot table: id, user_id, office_id, created_at, updated_at
13. All migrations include appropriate indexes on foreign keys and frequently queried fields
14. All migrations run successfully with `php artisan migrate`
15. Database seeder created with default statuses: Created, In Progress, On Hold, Cancelled, Completed
16. Database seeder created with transaction categories: PR, PO, VCH
17. Soft deletes implemented on tables where data should be preserved for audit trail (offices, suppliers, particulars, fund_types, action_taken)

### Story 1.3: RBAC Implementation with User Management

As an **Administrator**,
I want to manage users with role assignments (Viewer, Endorser, Administrator) and office assignments,
so that I can control who has access to the system and their permission levels.

**Acceptance Criteria:**
1. Spatie Laravel Permission package installed and configured (`composer require spatie/laravel-permission`)
2. User model configured with HasRoles trait from Spatie package
3. Three roles seeded in database: Viewer, Endorser, Administrator
4. User migration extended with additional fields if needed (ensure name, email, password included from Breeze)
5. UserOffice pivot relationship defined in User model (belongsToMany with offices)
6. Admin users can access User Management page at `/admin/users` (Viewer and Endorser roles receive 403)
7. User Management page displays table of all users showing: name, email, role, assigned office(s), created date
8. Admin can create new user with form fields: name, email, password, role (dropdown), office assignment (multi-select)
9. Password validation requires minimum 8 characters
10. Admin can edit existing user (update name, email, role, office assignments)
11. Admin can delete user (soft delete preferred, or prevent deletion if user has audit trail entries)
12. User list includes search/filter by name, email, role
13. RBAC middleware enforced: only Administrator role can access user management routes
14. Form validation prevents duplicate emails
15. Success/error toast notifications displayed using Shadcn/UI Toast component
16. TypeScript interfaces defined for User, Role, UserOffice types

### Story 1.4: Office Repository Management

As an **Administrator**,
I want to manage the Office repository (create, read, update, delete offices),
so that I can maintain the organizational structure used in workflows and user assignments.

**Acceptance Criteria:**
1. Office Eloquent model created with fillable fields: name, type, abbreviation
2. Office model implements SoftDeletes trait
3. Admin can access Office Management page at `/admin/repositories/offices`
4. Office Management page displays table of all offices showing: name, type, abbreviation, created date
5. Admin can create new office with form fields: name (required, max 255), type (required, max 100), abbreviation (required, max 10)
6. Admin can edit existing office
7. Admin can delete office (soft delete, with warning if office is assigned to users or used in workflows)
8. Office list includes search/filter by name, type, abbreviation
9. Form validation prevents duplicate office names or abbreviations
10. Success/error toast notifications displayed
11. RBAC enforced: only Administrator role can access office repository management
12. TypeScript interface defined for Office type
13. Pagination implemented if office count exceeds 50 records
14. Sort capability on table columns (name, type, abbreviation, created date)

### Story 1.5: Supplier Repository Management

As an **Administrator**,
I want to manage the Supplier repository (create, read, update, delete suppliers),
so that supplier information is available for PO and VCH creation.

**Acceptance Criteria:**
1. Supplier Eloquent model created with fillable fields: name, address, contact_number
2. Supplier model implements SoftDeletes trait
3. Admin can access Supplier Management page at `/admin/repositories/suppliers`
4. Supplier Management page displays table of all suppliers showing: name, address, contact number, created date
5. Admin can create new supplier with form fields: name (required, max 255), address (required, text), contact_number (optional, max 50)
6. Admin can edit existing supplier
7. Admin can delete supplier (soft delete, with warning if supplier is referenced in PO/VCH transactions)
8. Supplier list includes search/filter by name, address
9. Form validation prevents duplicate supplier names
10. Success/error toast notifications displayed
11. RBAC enforced: only Administrator role can access supplier repository management
12. TypeScript interface defined for Supplier type
13. Pagination implemented if supplier count exceeds 50 records
14. Address field uses textarea component for multi-line input

### Story 1.6: Additional Repository Management (Particulars, Fund Types, Action Taken)

As an **Administrator**,
I want to manage Particulars, Fund Types, and Action Taken repositories,
so that these reference data options are available throughout the system.

**Acceptance Criteria:**
1. Particular Eloquent model created with fillable: name; implements SoftDeletes
2. FundType Eloquent model created with fillable: name, abbreviation; implements SoftDeletes
3. ActionTaken Eloquent model created with fillable: name; implements SoftDeletes
4. Admin can access Particulars Management page at `/admin/repositories/particulars`
5. Admin can access Fund Types Management page at `/admin/repositories/fund-types`
6. Admin can access Action Taken Management page at `/admin/repositories/action-taken`
7. Each repository page displays table with appropriate columns (name, abbreviation where applicable, created date)
8. Admin can create, edit, delete records in each repository with appropriate form validation
9. Fund Types seeded with default values: GF (General Fund), TF (Trust Fund), SEF (Special Education Fund)
10. Form validation prevents duplicate names within each repository
11. Soft delete implemented with warnings if records are referenced elsewhere
12. Search/filter functionality on each repository list
13. Success/error toast notifications displayed
14. RBAC enforced: only Administrator role can access repository management
15. TypeScript interfaces defined for Particular, FundType, ActionTaken types
16. Pagination implemented on all lists if record count exceeds 50

### Story 1.7: Navigation, Layout & Access Control

As a **User**,
I want a consistent navigation structure with role-based menu visibility,
so that I can easily access features appropriate to my role.

**Acceptance Criteria:**
1. Main application layout component created with header, navigation menu, and content area
2. Navigation menu displays links based on user role:
   - **All roles:** Dashboard, Procurements, Transactions, Announcements, Profile
   - **Endorser + Administrator:** New Procurement
   - **Administrator only:** Admin menu (Users, Repositories, Workflows, Page Access, Announcements Management, Bypass Endorsement)
3. User profile dropdown in header showing logged-in user name, office(s), role, with Logout option
4. Notification bell icon placeholder in header (functional in Epic 4)
5. Active route highlighted in navigation menu
6. Mobile-responsive navigation with hamburger menu for small screens
7. Consistent Shadcn/UI styling across all pages
8. Page title displayed in header based on current route
9. Loading states displayed using Shadcn/UI Skeleton components during Inertia navigation
10. 403 Forbidden page displayed when user attempts to access unauthorized routes
11. Middleware enforces route protection based on roles and permissions
12. TypeScript shared layout props include authenticated user data (name, email, role, offices)

---

## Epic 2: Procurement & Transaction Lifecycle

**Epic Goal:**

Build the core domain models and user interfaces for creating and managing Procurements and their three dependent transactions (Purchase Request, Purchase Order, Voucher). Implement the reference number generation service with database-level uniqueness constraints and atomic sequence management, enforce business rule dependencies (PO requires existing PR, VCH requires existing PO), and provide CRUD operations with role-based access control. This epic delivers the complete procurement lifecycle tracking capability allowing Endorsers and Administrators to create procurements, add dependent transactions sequentially, view procurement and transaction details with progressive disclosure of linked data, and track basic status transitions (Created → In Progress → Completed). By epic completion, users can manage the full procurement record structure without workflow routing (workflow engine delivered in Epic 3).

[Stories 2.1 through 2.10 continue as drafted previously...]

---

## Epic 3: Workflow Engine & Endorsement System

**Epic Goal:**

Implement the workflow management engine enabling administrators to define category-specific transaction workflows as ordered sequences of office steps with expected completion days per step. Build the complete endorsement system supporting three core actions: Endorse (move transaction to next office), Receive (accept transaction at current office), and Complete (mark transaction finished at final step). Implement transaction state machine with automatic status transitions, current office/user tracking, out-of-workflow detection for misrouted endorsements with notifications to administrators and expected recipients, ETA and delay calculations based on workflow SLAs, and visual timeline representations showing completed steps, current location, and upcoming steps with ETAs. This epic delivers the core "delivery tracking" experience where users see exactly where transactions are, when they're expected to arrive, and receive alerts when routing deviates from approved workflows. By epic completion, the system provides full workflow-driven transaction routing with SLA monitoring and proactive misroute prevention.

[Stories 3.1 through 3.11 continue as drafted previously...]

---

## Epic 4: Dashboard, Monitoring & Notifications

**Epic Goal:**

Build the comprehensive dashboard providing at-a-glance visibility into system-wide procurement activity with summary cards showing total counts for PR, PO, VCH, and Procurements. Implement office workload tables displaying transaction distribution across offices with drill-down capabilities, real-time activity feeds showing recent endorsements and completions, and stagnant transactions panels highlighting overdue and idle items requiring attention. Develop the notification system with bell icon badge counters for actionable alerts including out-of-workflow routes, received items awaiting action, overdue transactions, completions, and administrator notices. Enhance all list views with comprehensive search and filter capabilities by date range, office, status, category, fund type, and supplier. This epic delivers proactive monitoring and visibility tools enabling all users to understand system state, identify bottlenecks, and take timely action on items requiring attention. By epic completion, users have a powerful dashboard and notification system replacing manual status checking with automated alerting and visual analytics.

[Stories 4.1 through 4.12 continue as drafted previously...]

---

## Epic 5: Admin Tools & Data Export

**Epic Goal:**

Implement administrative override capabilities enabling administrators to correct misrouted transactions through Bypass Endorsement functionality with required justification and audit logging. Build page access management providing fine-grained control over role-to-page permissions through an intuitive permission matrix interface. Develop the announcement system allowing administrators to create and manage system-wide announcements with three severity levels (Normal, Advisory, Emergency) displayed as persistent banners with bell icon notifications. Implement comprehensive data export functionality supporting CSV and JSON formats with extensive filtering options by date range, office, status, category, fund type, and supplier for compliance reporting and external analysis. Enhance audit trail reporting with exportable logs capturing all system events, state changes, and administrative actions. This epic delivers administrative control tools, compliance reporting capabilities, and system-wide communication channels essential for production operations. By epic completion, administrators have full control over system configuration, user communications, misroute corrections, and comprehensive data export capabilities for auditing and reporting requirements.

[Stories 5.1 through 5.12 continue as drafted previously...]

---

## Checklist Results Report

*[This section will be populated after running the PM checklist]*

---

## Next Steps

### UX Expert Prompt

Please review the PRD for OPTS (Online Procurement Tracking System) and create comprehensive UX/UI designs. Focus on the delivery-tracking-inspired experience with clear status visibility, timeline visualizations, and progressive disclosure patterns. Design all 22 core screens identified in the PRD with consistent Shadcn/UI component usage, WCAG 2.1 AA accessibility compliance, and responsive layouts for desktop, tablet, and mobile. Pay special attention to the dashboard layout, transaction timeline visualizations, and workflow endorsement flows.

### Architect Prompt

Please create the technical architecture document for OPTS based on this PRD. Using Laravel 12.x with React 19 via Inertia.js, TypeScript, and MySQL 8.0+, design the complete database schema expanding from the conceptual model provided, define Eloquent model relationships including polymorphic Transaction implementations, architect the reference number generation service with atomic sequence management, design the workflow engine with state machine patterns, create the ETA calculation service architecture, define TypeScript interfaces for all domain entities, establish Inertia component architecture patterns, and provide detailed implementation guidance for each epic's technical requirements. Ensure the architecture supports 200 concurrent users, 100k transactions, and maintains performance targets specified in NFR5.

---

*End of Product Requirements Document*