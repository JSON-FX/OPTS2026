# Epic List

Below is the high-level epic structure for OPTS. Each epic represents a significant, deployable increment of functionality that builds upon previous work while delivering tangible value.

**Epic 0.1: shadcn/ui Foundation & Global Components** *(Brownfield Enhancement)*

Establish the shadcn/ui component foundation by installing all required components via Claude MCP tools, migrating global layouts (AuthenticatedLayout, GuestLayout) to shadcn NavigationMenu/Sheet/DropdownMenu, integrating global Toaster for flash messages, and updating all authentication pages (Login, Register, ForgotPassword, ResetPassword) to use shadcn Form components. This foundation epic creates the baseline for all subsequent UI migrations with zero functionality regression.

**Epic 0.2: shadcn/ui Administrative Interfaces** *(Brownfield Enhancement)*

Migrate all administrative CRUD interfaces—User Management and 6 repository types (Offices, Suppliers, Particulars, Departments, FundTypes, Workflows)—to shadcn DataTable and Form components. Standardize table features (sort, filter, paginate, select) and ensure consistent admin UX across all repository management pages. Establishes the DataTable pattern for domain pages.

**Epic 0.3: shadcn/ui Procurement & Finalization** *(Brownfield Enhancement)*

Complete the shadcn/ui migration by updating all procurement and transaction management pages (Procurements, Purchase Requests, Purchase Orders, Vouchers, Procurement Detail, Transaction Search, Dashboard) with DataTable and Form components. Run comprehensive Playwright testing, execute MCP audit, update documentation, and remove all legacy components. Delivers 100% component standardization across the application.

**Epic 1: Foundation, Authentication & Repository Management**

Establish the project infrastructure (Laravel 12.x + React 19 + TypeScript + Inertia), implement authentication and user management, and provide administrative interfaces for managing all master reference data (Offices, Suppliers, Particulars, Fund Types, etc.). This foundational epic enables administrators to log in, manage users/roles, and populate the system with organizational data required for procurement operations.

**Epic 2: Procurement & Transaction Lifecycle**

Build the core domain models and user interfaces for creating and managing Procurements and their three dependent transactions (PR, PO, VCH). Implement reference number generation with uniqueness constraints, dependency enforcement (PO requires PR, VCH requires PO), basic status management, and RBAC-controlled access. This epic delivers the ability to track procurement from creation through all three transaction types without workflow routing.

**Epic 3: Workflow Engine & Endorsement System**

Implement the workflow engine enabling administrators to define category-specific workflows (ordered office steps with expected completion days), and build the endorsement system (Endorse → Receive → Complete actions) with transaction state machine, current office/user tracking, out-of-workflow detection, ETA/delay calculations, and timeline visualizations. This epic delivers the core "tracking" capability with routing, SLA monitoring, and misroute alerts.

**Epic 4: Dashboard, Monitoring & Notifications**

Build the comprehensive dashboard with summary cards, office workload tables, activity feeds, and stagnant transaction panels. Implement the notification system (bell icon with badges) for out-of-workflow alerts, received items, overdue items, and completions. Add search/filter capabilities across procurements and transactions. This epic delivers visibility and proactive monitoring for all system users.

> **Sub-Epics:**

**Epic 4.1: Dashboard & Summary Views** *(Brownfield Enhancement)*

Replace the placeholder Dashboard page with a comprehensive command center: summary cards (PR/PO/VCH/Procurement counts by status), office workload table with drill-down, activity feed showing recent endorsements/receives/completions, stagnant transactions panel, and SLA performance metrics. Stories: 4.1.1 (Summary Cards & Workload), 4.1.2 (Activity Feed & Stagnant Panel), 4.1.3 (SLA Performance).

**Epic 4.2: Notification System Expansion** *(Brownfield Enhancement)*

Expand the out-of-workflow notification system (Story 3.8) to cover all FR31 notification types: received items, overdue alerts, completion notices, and admin announcements with severity banners. Add notification preferences per user. Stories: 4.2.1 (Received/Overdue/Completion Notifications), 4.2.2 (Admin Announcements), 4.2.3 (Notification Preferences).

**Epic 4.3: Enhanced Search & Filtering** *(Brownfield Enhancement)*

Enhance all list views with comprehensive search/filter capabilities (FR23): office, fund type, supplier, date range, delay status filters. Standardize the filter experience across all list pages with a reusable FilterBar component. Stories: 4.3.1 (Transaction Search Enhancement), 4.3.2 (Standardized List Filtering).

**Epic 5: Admin Tools & Data Export**

Implement administrative override capabilities (Bypass Endorsement for correcting misroutes), page access management (role-to-page permission matrix), announcement system (Normal/Advisory/Emergency banners), and data export functionality (CSV/JSON with filters). Enhance audit trail reporting for compliance. This epic delivers administrative control and compliance reporting capabilities.

---
