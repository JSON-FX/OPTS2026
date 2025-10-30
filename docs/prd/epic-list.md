# Epic List

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
