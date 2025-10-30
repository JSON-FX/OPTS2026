# OPTS Development Team Handoff

**Project:** Online Procurement Tracking System (OPTS)
**Date:** October 31, 2025
**Status:** ✅ Ready for Development

---

## 📋 Documentation Overview

All project documentation has been completed and is ready for development team review.

### Product Requirements Document (PRD v1.1)

**Location:** `docs/prd/`

**Key Files:**
- [`docs/prd/index.md`](docs/prd/index.md) - Complete table of contents with navigation
- [`docs/prd.md`](docs/prd.md) - Single-file version (661 lines)

**Contents:**
- ✅ **Goals and Background** - 8 project goals, LGU procurement context, problem quantification
- ✅ **Requirements** - 35 Functional (FR1-FR35) + 15 Non-Functional (NFR1-NFR15)
- ✅ **UI Design Goals** - 22 core screens, UX vision, WCAG 2.1 AA accessibility
- ✅ **Technical Assumptions** - Laravel 12.x, React 19, MySQL 8.0+, Inertia.js
- ✅ **Epic Breakdown** - 5 epics with 52+ detailed user stories
- ✅ **PM Checklist** - 100% complete, validated, READY FOR ARCHITECT

**Key Metrics:**
- Reduce document loss from 15-20% to near-zero
- Eliminate 30-45 min/day spent on status inquiries
- Reduce delays by 50%+
- Cut misrouting by 80%+
- Reduce audit prep time by 90%+

### Architecture Document (v1.0)

**Location:** `docs/architecture/`

**Key Files:**
- [`docs/architecture/index.md`](docs/architecture/index.md) - Architecture navigation
- [`docs/architecture.md`](docs/architecture.md) - Complete architecture (comprehensive)

**Contents:**
- ✅ **Tech Stack** - 30+ technologies with versions and justifications
- ✅ **Data Models** - Complete TypeScript interfaces for all domain entities
- ✅ **API Specification** - Inertia.js routes + minimal REST endpoints
- ✅ **Components** - Frontend (React) and Backend (Laravel Services)
- ✅ **Core Workflows** - 8 critical business processes with sequence diagrams
- ✅ **Database Schema** - 17 tables with DDL, indexes, constraints
- ✅ **Frontend Architecture** - React 19 + TypeScript + Inertia.js patterns
- ✅ **Backend Architecture** - Laravel 12.x service layer, controllers, models
- ✅ **Deployment** - Production environment, zero-downtime deployment process
- ✅ **Security & Performance** - NFR compliance strategies
- ✅ **Testing Strategy** - Unit, integration, E2E testing approaches

---

## 🚀 Getting Started

### Prerequisites

Ensure your development environment has:

- **PHP 8.2+** with extensions: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath
- **Composer 2.x**
- **Node.js 18+** with NPM
- **MySQL 8.0+**
- **Git 2.x**
- **Redis 7.x** (optional for dev, required for production)

### Initial Setup Commands

```bash
# 1. Clone repository (when ready)
git clone <repository-url> opts2026
cd opts2026

# 2. Install Laravel 12.x with Breeze React starter
composer create-project laravel/laravel .
composer require laravel/breeze --dev
php artisan breeze:install react

# 3. Install dependencies
composer install
npm install

# 4. Environment configuration
cp .env.example .env
php artisan key:generate

# Edit .env with your database credentials:
# DB_DATABASE=opts2026
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# 5. Database setup
php artisan migrate
php artisan db:seed

# 6. Install Spatie Permission
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# 7. Start development servers (two terminals)
# Terminal 1:
php artisan serve

# Terminal 2:
npm run dev
```

Visit http://localhost:8000 to see the application.

---

## 📚 Key Technical Decisions

### Architecture: Monolithic Full-Stack

- **Backend:** Laravel 12.x (PHP 8.2+)
- **Frontend:** React 19 with TypeScript
- **Bridge:** Inertia.js (server-side rendering, no traditional REST API)
- **Database:** MySQL 8.0+
- **UI Library:** Shadcn/UI (included in Laravel 12.x Breeze React)
- **Styling:** Tailwind CSS
- **Authentication:** Laravel Breeze (session-based)
- **Authorization:** Spatie Laravel Permission (RBAC)

**Rationale:** Monolithic architecture appropriate for 200 concurrent users, 100k transactions. Inertia.js eliminates API complexity while maintaining type safety.

### Critical Services to Implement

1. **WorkflowEngine** (`app/Services/WorkflowEngine.php`)
   - Handles endorse/receive/complete actions
   - Detects out-of-workflow routing
   - Manages transaction state transitions

2. **ReferenceNumberGenerator** (`app/Services/ReferenceNumberGenerator.php`)
   - Atomic sequence generation with database locking
   - Formats: `PR-{FUND}-{YYYY}-{MM}-{SEQ}`, `PO-{YYYY}-{MM}-{SEQ}`
   - Prevents duplicate reference numbers (FR6)

3. **ETACalculator** (`app/Services/ETACalculator.php`)
   - Computes SLA-based ETAs
   - Identifies stagnant transactions
   - Builds timeline visualizations

4. **AuditLogger** (`app/Services/AuditLogger.php`)
   - Immutable audit trail (NFR4)
   - 7-year retention compliance (NFR14)

### Database Schema Highlights

**17 Core Tables:**
- `procurements` - Main procurement records
- `transactions` - Base transaction table (polymorphic)
- `purchase_requests`, `purchase_orders`, `vouchers` - Transaction types
- `reference_numbers` - Atomic sequence tracking with uniqueness constraints
- `workflows`, `workflow_steps` - Configurable routing
- `transaction_actions` - Endorsement history with out-of-workflow detection
- `audit_logs` - Immutable compliance trail
- `users`, `roles`, `permissions` - RBAC via Spatie
- Repository tables: `offices`, `suppliers`, `particulars`, `fund_types`, `action_taken`

**Critical Constraints:**
- UNIQUE constraint on `reference_numbers` (category, fund_type_id, year, month, is_continuation, sequence)
- Soft deletes on `procurements` and `transactions` (7-year retention)
- Foreign keys with CASCADE/RESTRICT policies

---

## 🎯 Development Roadmap

### Epic 1: Foundation, Authentication & Repository Management

**Priority:** IMMEDIATE START

**Stories:**
1. **Story 1.1:** Project Setup & Configuration
   - Initialize Laravel 12.x + Breeze React
   - Configure database, environment
   - Setup Git repository

2. **Story 1.2:** Database Schema Foundation & Core Migrations
   - Create all 17 table migrations in dependency order
   - Add indexes, constraints, foreign keys
   - Create seeders for development data

3. **Story 1.3:** RBAC Implementation with User Management
   - Install Spatie Laravel Permission
   - Create 3 roles: Viewer, Endorser, Administrator
   - Build user management UI (Admin)

4. **Story 1.4-1.6:** Repository Management
   - Offices, Suppliers, Particulars, Fund Types, Action Taken
   - CRUD interfaces for administrators
   - Validation and uniqueness checks

5. **Story 1.7:** Navigation, Layout & Access Control
   - AppLayout with sidebar, navbar, notification bell
   - Role-based route protection
   - Announcement banner system

**Acceptance Criteria:** See `docs/prd/epic-1-foundation-authentication-repository-management.md`

### Epic 2: Procurement & Transaction Lifecycle

**Stories:** 2.1 through 2.10
- Procurement creation and management
- PR/PO/VCH creation with dependency enforcement
- Reference number generation
- Basic status management

### Epic 3: Workflow Engine & Endorsement System

**Stories:** 3.1 through 3.12
- Workflow configuration
- Endorse → Receive → Complete actions
- Out-of-workflow detection
- ETA calculations and timeline visualization

### Epic 4: Dashboard, Monitoring & Notifications

**Stories:** 4.1 through 4.8
- Dashboard with summary cards
- Office workload tables
- Activity feeds
- Notification system (bell icon)

### Epic 5: Admin Tools & Data Export

**Stories:** 5.1 through 5.6
- Bypass endorsement (admin override)
- Page access management
- Announcement system
- CSV/JSON data export

---

## 🧪 Testing Requirements

### Backend Testing (PHPUnit/Pest)

**Priority Test Coverage:**
- ✅ Reference number uniqueness (FR6)
- ✅ Workflow state transitions (FR8, FR9)
- ✅ Dependency enforcement (FR2: PO requires PR, VCH requires PO)
- ✅ Out-of-workflow detection (FR14)
- ✅ RBAC authorization (FR25)
- ✅ Audit trail integrity (NFR4)

**Example:**
```php
test('cannot create PO without existing PR', function () {
    $procurement = Procurement::factory()->create();

    $response = $this->actingAs($user)->post("/procurements/{$procurement->id}/po", [
        'supplier_id' => $supplier->id,
        'contract_price' => 10000,
    ]);

    $response->assertSessionHasErrors();
});
```

### Frontend Testing (Vitest)

- Component unit tests (StatusBadge, TimelineVisualization)
- Form validation tests
- Permission-based rendering tests

### E2E Testing (Playwright)

- Complete procurement workflow (Create → PR → PO → VCH → Complete)
- Out-of-workflow detection and admin bypass
- User role permission boundaries

---

## 🔐 Security Checklist

- ✅ HTTPS enforcement in production (NFR3)
- ✅ CSRF protection (Laravel + Inertia default)
- ✅ Password hashing (bcrypt/argon2id - NFR2)
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ XSS prevention (React JSX escaping)
- ✅ RBAC enforcement (Spatie + Policies)
- ✅ IP address logging (audit trail - NFR4)
- ✅ Input validation (Form Requests + TypeScript)
- ✅ Security headers (X-Frame-Options, CSP, etc.)

---

## 📊 Performance Targets

**NFR5 Requirements:**
- 200 concurrent users
- 100,000 transactions in database
- P95 page load < 2.5 seconds
- Database query P95 < 1 second (NFR15)

**Optimization Strategies:**
- Eloquent eager loading (prevent N+1 queries)
- Redis caching for repository data
- Database indexes on foreign keys, status fields
- Queue workers for async operations (notifications, exports)
- Vite code splitting (automatic)
- Laravel production optimizations (config:cache, route:cache)

---

## 📞 Support & Questions

**Documentation References:**
- Full PRD: `docs/prd.md` or `docs/prd/index.md`
- Full Architecture: `docs/architecture.md` or `docs/architecture/index.md`
- Original Brief: `docs/brief.md`

**Key Requirements:**
- FR1-FR35: Functional requirements
- NFR1-NFR15: Non-functional requirements
- 5 Epics: Epic 1 (start here) through Epic 5

**Next Steps:**
1. Review PRD and Architecture documents
2. Setup development environment
3. Begin Epic 1, Story 1.1 (Project Setup)
4. Create Git repository with proper .gitignore
5. Setup CI/CD pipeline (GitHub Actions recommended)

---

## ✅ Handoff Checklist

- ✅ Product Requirements Document (v1.1) complete
- ✅ Architecture Document (v1.0) complete
- ✅ Tech stack defined with versions
- ✅ Database schema designed (17 tables)
- ✅ Core workflows documented (8 sequences)
- ✅ Service layer specifications provided
- ✅ Frontend component architecture defined
- ✅ Testing strategy established
- ✅ Deployment process documented
- ✅ Security requirements specified
- ✅ Performance targets defined
- ✅ Development roadmap (5 epics, 52+ stories)

**Status:** 🟢 READY FOR DEVELOPMENT

---

*Generated: October 31, 2025*
*Project: OPTS (Online Procurement Tracking System)*
*For: LGU Procurement Office*
