# Epic 1: Foundation, Authentication & Repository Management

**Epic Goal:**

Establish the complete project infrastructure with Laravel 12.x, React 19, TypeScript, and Inertia.js configured for development. Implement secure authentication using Laravel Breeze, role-based access control with three distinct roles (Viewer, Endorser, Administrator), and comprehensive administrative interfaces for managing all master reference data repositories. This epic delivers a fully functional admin portal where administrators can log in, manage users and their roles/office assignments, and populate the system with organizational data (Offices, Suppliers, Particulars, Fund Types, Statuses, Action Taken, Transaction Categories) required for procurement operations. By epic completion, the system has a secure foundation, user management, and all reference data management capabilities ready to support procurement transaction workflows.

## Story 1.1: Project Setup & Configuration

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

## Story 1.2: Database Schema Foundation & Core Migrations

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

## Story 1.3: RBAC Implementation with User Management

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

## Story 1.4: Office Repository Management

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

## Story 1.5: Supplier Repository Management

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

## Story 1.6: Additional Repository Management (Particulars, Fund Types, Action Taken)

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

## Story 1.7: Navigation, Layout & Access Control

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
