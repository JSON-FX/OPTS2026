# Requirements

## Functional

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

## Non-Functional

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
