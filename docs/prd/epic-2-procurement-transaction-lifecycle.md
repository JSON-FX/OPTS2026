# Epic 2: Procurement & Transaction Lifecycle

**Epic Goal:**

Build the core domain models and user interfaces for creating and managing Procurements and their three dependent transactions (Purchase Request, Purchase Order, Voucher). Implement manual reference number input with fund type and continuation tracking for PR/PO transactions, enforce database-level uniqueness constraints, enforce business rule dependencies (PO requires existing PR, VCH requires existing PO), and provide CRUD operations with role-based access control. This epic delivers the complete procurement lifecycle tracking capability allowing Endorsers and Administrators to create procurements with custom reference numbering schemes, add dependent transactions sequentially, view procurement and transaction details with progressive disclosure of linked data, and track basic status transitions (Created → In Progress → Completed). By epic completion, users can manage the full procurement record structure without workflow routing (workflow engine delivered in Epic 3).

## Story 2.1: Database Schema - Procurements & Transactions Tables

As a **Developer**,
I want to create database migrations for procurements and transactions tables,
so that the data model supports the procurement lifecycle with proper relationships and constraints.

**Acceptance Criteria:**

1. Migration created for `procurements` table with columns: id, end_user_id (FK to offices), particular_id (FK to particulars), purpose (text), abc_amount (decimal 15,2), date_of_entry (date), status (enum: Created, In Progress, Completed, On Hold, Cancelled), created_by_user_id (FK to users), created_at, updated_at, deleted_at
2. Migration created for `transactions` table with columns: id, procurement_id (FK to procurements), category (enum: PR, PO, VCH), reference_number (string unique, indexed), status (enum: Created, In Progress, Completed, On Hold, Cancelled), workflow_id (FK nullable to workflows, Epic 3), current_office_id (FK nullable to offices), current_user_id (FK nullable to users), created_by_user_id (FK to users), created_at, updated_at, deleted_at
3. Migration created for `purchase_requests` table with columns: id, transaction_id (FK to transactions), fund_type_id (FK to fund_types), created_at, updated_at
4. Migration created for `purchase_orders` table with columns: id, transaction_id (FK to transactions), supplier_id (FK to suppliers), supplier_address (text), contract_price (decimal 15,2), created_at, updated_at
5. Migration created for `vouchers` table with columns: id, transaction_id (FK to transactions), payee (string 255), created_at, updated_at
6. Migration created for `procurement_status_history` table with columns: id, procurement_id (FK to procurements), old_status (enum), new_status (enum), reason (text nullable), changed_by_user_id (FK to users), changed_at (timestamp), created_at
7. Migration created for `transaction_status_history` table with columns: id, transaction_id (FK to transactions), old_status (enum), new_status (enum), reason (text nullable), changed_by_user_id (FK to users), changed_at (timestamp), created_at
8. Migration created for `reference_sequences` table with columns: id, category (string), year (integer), last_number (integer), created_at, updated_at
9. Unique constraint on transactions.reference_number with database index
10. Unique composite index on reference_sequences (category, year)
11. Foreign key constraints with ON DELETE RESTRICT for referential integrity on all FK relationships
12. Indexes created on frequently queried fields: transactions (procurement_id, category, status, reference_number), procurements (end_user_id, particular_id, status, created_by_user_id)
13. Soft deletes implemented on procurements and transactions tables (SoftDeletes trait)
14. Status default values: 'Created' for both procurements.status and transactions.status
15. All migrations run successfully with `php artisan migrate`
16. Database seeder validates statuses table has required values from Epic 1 (Created, In Progress, On Hold, Cancelled, Completed)

**Database Relationship Pattern:**
- Single `transactions` table stores all transaction types (PR, PO, VCH) differentiated by `category` field
- Separate type-specific tables (`purchase_requests`, `purchase_orders`, `vouchers`) have FK to `transactions.id` for additional fields
- Laravel models: Transaction (base model), PurchaseRequest/PurchaseOrder/Voucher (extend Transaction with hasOne relationship to type-specific table)

## Story 2.2: Reference Number Generation Service (Foundation)

As a **Developer**,
I want to implement a reference number generation service with atomic sequence management,
so that transactions have unique, sequential reference numbers by category and year.

**Acceptance Criteria:**

1. Service class created: `app/Services/ReferenceNumberService.php`
2. Method `generateReferenceNumber(category: string): string` generates reference numbers in format: `{CATEGORY}-{YYYY}-{NNNNNN}` (e.g., PR-2025-000001, PO-2025-000042, VCH-2025-000123)
3. Sequence counter resets to 000001 at start of each calendar year per category
4. Service uses `reference_sequences` table (created in Story 2.1) with database transaction and row-level locking (`lockForUpdate()`) to ensure atomicity
5. Concurrent request handling: multiple simultaneous requests for same category/year generate different sequential numbers without collision
6. Service throws `ReferenceNumberException` if unable to acquire lock after timeout (5 seconds)
7. Service auto-creates sequence record for new category/year combinations starting at last_number = 1
8. Sequence overflow handling: If sequence exceeds 999999, service extends to 7+ digits (PR-2025-0000001) to prevent errors
9. Reference sequences records retained indefinitely for audit trail and compliance
10. Unit tests validate: sequential generation, year rollover, unique constraints, exception handling
11. Integration test validates: 100 concurrent requests generate 100 unique reference numbers with no duplicates
12. Service is injectable via Laravel service container (singleton binding)

**Note:** This service is repurposed in Story 2.6 to support manual reference number input with validation instead of auto-generation for PR/PO transactions.

## Story 2.3: Procurement CRUD Operations

As an **Endorser or Administrator**,
I want to create, view, edit, and manage procurements,
so that I can initiate and track procurement requests.

**Acceptance Criteria:**

1. Procurement Eloquent model created with fillable fields: end_user_id, particular_id, purpose, abc_amount, date_of_entry, status, created_by_user_id
2. Procurement model implements SoftDeletes trait
3. Procurement model defines relationships: belongsTo Office (end_user), belongsTo Particular, belongsTo User (created_by), hasOne PurchaseRequest (through Transaction), hasOne PurchaseOrder (through Transaction), hasOne Voucher (through Transaction), hasMany ProcurementStatusHistory
4. All authenticated users (Viewer, Endorser, Administrator) can access Procurements list page at `/procurements`
5. Procurements page displays table showing: Procurement ID, End User Office, Particular, Purpose (truncated to 100 chars), ABC Amount (formatted currency ₱#,###.##), Date of Entry, Status, Created By, Created Date
6. Endorser and Administrator can create new procurement via `/procurements/create` with form fields: End User (dropdown from active offices), Particular (dropdown from active particulars), Purpose (textarea, required, max 1000 chars), ABC Amount (currency input, required, min 0.01), Date of Entry (date picker, required, defaults to today)
7. Form validation: all required fields must be filled; ABC Amount must be positive decimal with max 2 decimal places; Date of Entry cannot be future date
8. On successful creation, procurement created with status='Created' and created_by_user_id=current user; user redirected to procurement detail page
9. Endorser and Administrator can edit existing procurement via `/procurements/{id}/edit`
10. Edit restrictions: If procurement has transactions (PR, PO, or VCH exists), only `purpose`, `abc_amount`, and `date_of_entry` fields editable; `end_user_id` and `particular_id` become read-only with tooltip "Cannot change End User/Particular after transactions created"
11. Procurement list includes pagination (50 per page), search/filter by: End User Office (dropdown), Particular (dropdown), Status (dropdown), Date Range (from/to date pickers), Created By (current user checkbox "My Procurements")
12. Procurement list sortable by: ID, End User Office, ABC Amount, Date of Entry, Created Date (click column headers; default sort: Created Date DESC)
13. RBAC enforced: All roles can view procurement list and details; Endorser and Administrator can create/edit/delete; Viewer sees read-only view with no action buttons
14. Success/error toast notifications displayed using Shadcn/UI Toast component
15. TypeScript interface defined for Procurement type matching Eloquent model in `resources/js/types/models.ts`
16. Soft delete available for procurements without transactions; if transactions exist, show warning modal "Cannot delete procurement with existing transactions. Archive instead?" with Archive button that soft-deletes

## Story 2.4: Transaction Dependencies & Business Rule Validation

As a **System**,
I want to enforce transaction dependency rules (PO requires PR, VCH requires PO),
so that data integrity is maintained and procurement lifecycle is followed correctly.

**Acceptance Criteria:**

1. Business rule service class created: `app/Services/ProcurementBusinessRules.php`
2. Method `canCreatePR(Procurement $procurement): bool` validates procurement has no PR yet (returns false if PR exists)
3. Method `canCreatePO(Procurement $procurement): bool` validates procurement has existing PR and no PO yet
4. Method `canCreateVCH(Procurement $procurement): bool` validates procurement has existing PO and no VCH yet
5. Method `canDeletePR(Procurement $procurement): bool` validates no PO exists before allowing PR deletion
6. Method `canDeletePO(Procurement $procurement): bool` validates no VCH exists before allowing PO deletion
7. Custom validation rule class created: `app/Rules/RequiresPurchaseRequest` validates procurement has PR before allowing PO creation; returns error message "You must create a Purchase Request before adding a Purchase Order for this procurement."
8. Custom validation rule class created: `app/Rules/RequiresPurchaseOrder` validates procurement has PO before allowing VCH creation; returns error message "You must create a Purchase Order before adding a Voucher for this procurement."
9. Form request validation classes created: `app/Http/Requests/StorePurchaseOrderRequest` includes RequiresPurchaseRequest rule
10. Form request validation class created: `app/Http/Requests/StoreVoucherRequest` includes RequiresPurchaseOrder rule
11. Backend validation: API endpoints POST `/procurements/{id}/purchase-orders` and POST `/procurements/{id}/vouchers` return 422 Unprocessable Entity with validation errors if prerequisites missing
12. Referential integrity: Database foreign keys with ON DELETE RESTRICT prevent orphaned records; fund_types, suppliers cannot be deleted if referenced by transactions
13. Delete confirmation modals display context-aware warnings: "Deleting this Purchase Request will prevent PO/VCH creation. Continue?", "Cannot delete Purchase Order because Voucher exists for this procurement."
14. Unit tests validate all business rule methods with edge cases: multiple procurements, null checks, soft-deleted transactions
15. Integration tests validate end-to-end dependency enforcement: creating PO without PR returns 422, creating VCH without PO returns 422, deleting PR with existing PO fails
16. TypeScript types defined for business rule validation responses: `BusinessRuleValidation { canProceed: boolean; errorMessage?: string; }`
17. Override/waiver capability explicitly NOT included in Epic 2; reserved for Epic 5 (Bypass Endorsement); all dependency rules strictly enforced without exceptions

## Story 2.5: Purchase Request (PR) Transaction Management

As an **Endorser or Administrator**,
I want to create and manage Purchase Request (PR) transactions linked to procurements,
so that I can initiate the PR phase of the procurement lifecycle.

**Acceptance Criteria:**

1. PurchaseRequest Eloquent model created with relationship: belongsTo Transaction (transaction_id FK)
2. Transaction Eloquent model with category='PR' creates related PurchaseRequest via hasOne relationship
3. PurchaseRequest fillable fields: transaction_id, fund_type_id
4. From Procurement detail page (`/procurements/{id}`), "Add Purchase Request" button visible only if: (a) no PR exists yet AND (b) user is Endorser or Administrator
5. Button uses business rule service: `ProcurementBusinessRules::canCreatePR()` to determine enabled state
6. PR creation form at `/procurements/{id}/purchase-requests/create` displays: Procurement Summary section (read-only: ID, End User, Particular, Purpose, ABC Amount), Fund Type (dropdown from active fund_types, required), Workflow (dropdown from workflows filtered by category='PR', nullable for Epic 2, required in Epic 3)
7. On PR form submission, system calls `ReferenceNumberService::generateReferenceNumber('PR')` to get unique reference number (Note: Story 2.6 replaces auto-generation with manual input)
8. Transaction creation is atomic using database transaction: (a) Create Transaction record with procurement_id, category='PR', reference_number, status='Created', workflow_id (nullable), created_by_user_id, (b) Create PurchaseRequest record with transaction_id, fund_type_id, (c) Commit or rollback on any failure
9. After successful PR creation, procurement status transitions to 'In Progress' if current status is 'Created' (automatic status update)
10. User redirected to Procurement detail page (`/procurements/{id}`) showing newly created PR in Purchase Request section
11. PR can be edited via `/purchase-requests/{id}/edit` with form fields: Fund Type (can update), Workflow (Epic 3)
12. Edit validation: Fund Type must still be active; if fund_type soft-deleted, show warning "Selected fund type is no longer active. Please choose another."
13. PR soft delete available via Delete button on PR detail page; validation checks business rule `canDeletePR()` - fails if PO exists
14. PR detail view at `/purchase-requests/{id}` displays: Reference Number (prominent header), Fund Type, Status (badge), Created By (user name), Created Date, Related Procurement section (clickable link to procurement), Edit/Delete action buttons (Endorser/Admin only)
15. RBAC enforced: Endorser and Administrator can create/edit PR; Viewer can view PR details (read-only, no action buttons)
16. Success/error toast notifications displayed: "Purchase Request {reference_number} created successfully", "Error creating Purchase Request: {error details}"
17. TypeScript interface defined: `PurchaseRequest extends Transaction` with fund_type_id field and fund_type relationship in `resources/js/types/models.ts`
18. Fund Type deletion prevented (ON DELETE RESTRICT FK constraint) if referenced by existing PR; attempting fund_type delete shows error listing affected PR reference numbers

**Story Dependencies:** Requires Story 2.4 (Business Rules) to be completed first for validation service.

## Story 2.6: Enhanced Reference Number with Manual Input & Continuation Flag

As an **Endorser or Administrator**,
I want to manually input reference numbers with fund type and continuation flags,
so that I can maintain custom reference numbering schemes and track continuation transactions from previous years.

**Acceptance Criteria:**

1. PR reference number format changed from `PR-YYYY-NNNNNN` to `PR-{FundType}-{Year}-{Month}-{Number}` (e.g., `PR-GAA-2025-10-001`)
2. PO reference number format changed from `PO-YYYY-NNNNNN` to `PO-{Year}-{Month}-{Number}` (e.g., `PO-2025-10-001`)
3. Continuation PR format: `CONT-PR-{FundType}-{Year}-{Month}-{Number}` (e.g., `CONT-PR-GAA-2024-12-9999`)
4. Continuation PO format: `CONT-PO-{Year}-{Month}-{Number}` (e.g., `CONT-PO-2024-12-9999`)
5. PR creation form includes manual input fields: Year (4-digit text input), Month (2-digit text input), Number (text input)
6. PR creation form includes checkbox: "☐ This is a continuation PR from a previous year" (unchecked by default)
7. When continuation checkbox checked, system adds `CONT-` prefix to generated reference number
8. PO creation form includes same manual input fields (Year, Month, Number) and continuation checkbox
9. Reference number preview displayed in real-time as user types, showing full formatted reference (e.g., `PR-GAA-2025-10-001` or `CONT-PR-GAA-2024-12-9999`)
10. Form validation: Year must be 4 digits (e.g., 2024, 2025); Month must be 01-12; Number is freeform text (user can enter 001, 9999, ABC, etc.)
11. Uniqueness validation: Before creating transaction, system checks `transactions.reference_number` for exact match; if duplicate found, return 422 error: "Reference number {reference_number} already exists. Please enter a different number."
12. Uniqueness is enforced across ALL transaction types (PR, PO, VCH) - a PO cannot use a reference number already used by a PR
13. Database unique constraint on `transactions.reference_number` column prevents duplicates at database level
14. `ReferenceNumberService` repurposed from auto-generator to validator: method `validateUniqueReference(string $referenceNumber): bool` checks if reference number is available
15. `ReferenceNumberService` no longer generates reference numbers automatically for PR/PO; all reference number construction done in controllers using manual inputs (VCH may still use auto-generation)
16. `reference_sequences` table no longer used for PR/PO creation; table retained for VCH and audit/historical purposes
17. Transaction model adds field `is_continuation` (boolean, default false) to track continuation transactions
18. Migration adds `is_continuation` column to `transactions` table
19. PR/PO edit forms allow updating reference number components (Year, Month, Number, Continuation flag) with same validation rules
20. Edit reference number validation: if new reference number differs from current, check uniqueness; if matches another transaction, show error
21. PR/PO detail pages display full reference number prominently (e.g., `CONT-PR-GAA-2024-12-9999`) with continuation badge if applicable
22. TypeScript interfaces updated: `Transaction` interface includes `is_continuation: boolean` field
23. RBAC enforced: Only Endorser and Administrator can create/edit transactions with manual reference numbers; Viewer can view only
24. Success/error toast notifications: "Purchase Request CONT-PR-GAA-2024-12-9999 created successfully", "Error: Reference number PR-GAA-2025-10-001 already exists"

**Story Dependencies:** Requires Story 2.2 (Reference Number Service Foundation), Story 2.4 (Business Rules), Story 2.5 (PR Management).

**Breaking Changes:**
- Story 2.2's `generateReferenceNumber()` method repurposed for validation only
- Existing PR/PO creation tests need updates to provide manual reference number inputs
- `reference_sequences` table no longer used for PR/PO (VCH still uses it)

## Story 2.7: Purchase Order (PO) Transaction Management

As an **Endorser or Administrator**,
I want to create and manage Purchase Order (PO) transactions linked to procurements,
so that I can record supplier contracts and advance procurement to PO phase.

**Acceptance Criteria:**

1. PurchaseOrder Eloquent model created with relationship: belongsTo Transaction (transaction_id FK)
2. Transaction Eloquent model with category='PO' creates related PurchaseOrder via hasOne relationship
3. PurchaseOrder fillable fields: transaction_id, supplier_id, supplier_address, contract_price
4. From Procurement detail page, "Add Purchase Order" button visible only if: (a) PR exists (b) no PO exists yet AND (c) user is Endorser or Administrator
5. Button uses business rule service: `ProcurementBusinessRules::canCreatePO()` to determine enabled state; disabled with tooltip "Purchase Request required before creating Purchase Order" if PR missing
6. PO creation form at `/procurements/{id}/purchase-orders/create` displays: Procurement Summary (read-only), PR Reference Number (read-only, clickable link), Manual Reference Number fields (Year, Month, Number, Continuation checkbox per Story 2.6), Supplier (dropdown from active suppliers, required, with search), Supplier Address (text field, auto-populated when supplier selected, read-only), Contract Price (currency input ₱#,###.##, required, min 0.01), Workflow (dropdown for category='PO', nullable)
7. Supplier dropdown triggers JavaScript to fetch supplier.address and populate Supplier Address field on selection change
8. On PO form submission, system builds reference number using manual inputs per Story 2.6 format: `PO-{Year}-{Month}-{Number}` or `CONT-PO-{Year}-{Month}-{Number}`
9. Transaction creation atomic: (a) Create Transaction with category='PO', reference_number (built from manual input), is_continuation flag, (b) Create PurchaseOrder with supplier_id, supplier_address (snapshot from supplier at creation time), contract_price
10. Supplier address is immutable snapshot: changes to supplier.address after PO creation do NOT update PO supplier_address (ensures audit trail integrity)
11. After successful PO creation, procurement status remains 'In Progress' (or transitions from 'Created' if somehow still Created)
12. User redirected to Procurement detail page showing both PR and PO sections populated
13. PO can be edited via `/purchase-orders/{id}/edit` with fields: Reference Number components (Year, Month, Number, Continuation), Supplier (can update, triggers address refresh), Contract Price (can update), Workflow
14. Edit validation: If supplier changed, supplier_address auto-updates to new supplier's current address with confirmation modal "Changing supplier will update address to: {new_address}. Continue?"
15. PO soft delete validation: checks `canDeletePO()` business rule - fails with error if Voucher exists: "Cannot delete Purchase Order because Voucher {VCH-ref} exists. Delete the Voucher first."
16. PO detail view at `/purchase-orders/{id}` displays: Reference Number (with continuation badge if applicable), Supplier Name, Supplier Address (read-only), Contract Price (formatted ₱#,###.##), Status badge, Created By, Created Date, Related PR (clickable link), Related Procurement (clickable link)
17. RBAC enforced: Endorser and Administrator can create/edit PO; Viewer can view PO details (read-only)
18. Currency formatting: Contract Price displays with PHP peso symbol (₱) prefix and thousand separators; input validation allows up to 2 decimal places
19. TypeScript interface defined: `PurchaseOrder extends Transaction` with supplier_id, supplier_address, contract_price fields

**Story Dependencies:** Requires Story 2.4 (Business Rules), Story 2.5 (PR Management), Story 2.6 (Enhanced Reference Numbers).

## Story 2.8: Voucher (VCH) Transaction Management

As an **Endorser or Administrator**,
I want to create and manage Voucher (VCH) transactions linked to procurements,
so that I can record payment vouchers and complete the procurement lifecycle.

**Acceptance Criteria:**

1. Voucher Eloquent model created with relationship: belongsTo Transaction (transaction_id FK)
2. Transaction Eloquent model with category='VCH' creates related Voucher via hasOne relationship
3. Voucher fillable fields: transaction_id, payee
4. From Procurement detail page, "Add Voucher" button visible only if: (a) PO exists (b) no VCH exists yet AND (c) user is Endorser or Administrator
5. Button uses business rule service: `ProcurementBusinessRules::canCreateVCH()`; disabled with tooltip "Purchase Order required before creating Voucher" if PO missing
6. VCH creation form at `/procurements/{id}/vouchers/create` displays: Procurement Summary (read-only), PR Reference Number (read-only link), PO Reference Number (read-only link), Payee (text input, required, max 255 chars), Workflow (dropdown for category='VCH', nullable)
7. Payee field is free-text input; no validation against PO supplier name (allows flexibility for payment recipient different from supplier, e.g., third-party payment processors)
8. On VCH form submission, system calls `ReferenceNumberService::generateReferenceNumber('VCH')`
9. Transaction creation atomic: (a) Create Transaction with category='VCH', (b) Create Voucher with payee
10. After successful VCH creation, procurement status remains 'In Progress' (status transitions to Completed handled manually in Story 2.9)
11. User redirected to Procurement detail page showing PR, PO, and VCH sections all populated
12. VCH can be edited via `/vouchers/{id}/edit` with fields: Payee (can update), Workflow
13. VCH soft delete: Delete button available but shows strong warning modal "Deleting vouchers may violate audit trail requirements. Are you sure? This action creates a deletion record." Soft delete only, never hard delete.
14. VCH detail view at `/vouchers/{id}` displays: Reference Number, Payee, Status badge, Created By, Created Date, Related PO (link), Related PR (link), Related Procurement (link)
15. RBAC enforced: Endorser and Administrator can create/edit VCH; Viewer can view VCH details (read-only)
16. Success/error toast notifications: "Voucher {reference_number} created successfully for Payee: {payee}"
17. TypeScript interface defined: `Voucher extends Transaction` with payee field

**Story Dependencies:** Requires Story 2.4 (Business Rules), Story 2.5 (PR), Story 2.7 (PO).

**Note:** VCH may continue to use auto-generated reference numbers from Story 2.2's `generateReferenceNumber('VCH')` method, or adopt manual input in a future iteration.

## Story 2.9: Procurement Detail View with Linked Transactions

As a **User**,
I want to view procurement details with all linked transactions (PR, PO, VCH) in a single page,
so that I can understand the complete procurement lifecycle at a glance.

**Acceptance Criteria:**

1. Procurement detail page at `/procurements/{id}` displays comprehensive procurement information using card-based layout
2. Page sections (top to bottom): Procurement Summary, Status History (latest 5), Purchase Request Details, Purchase Order Details, Voucher Details
3. Procurement Summary card shows: Procurement ID (large header), End User Office, Particular, Purpose (full text, expandable if >500 chars), ABC Amount (₱#,###.##), Date of Entry, Current Status (badge), Created By (user name + avatar), Created Date (relative time "2 days ago" + absolute tooltip)
4. Purchase Request card shows: PR Reference Number (clickable link to `/purchase-requests/{id}`), Fund Type, Status (badge), Created By, Created Date; if PR doesn't exist: gray card with "Not Created" text and "Add Purchase Request" button (enabled/disabled based on business rules)
5. Purchase Order card shows: PO Reference Number (clickable link), Supplier Name, Supplier Address (read-only), Contract Price (₱#,###.##), Status badge, Created By, Created Date; if PO doesn't exist: gray card with "Not Created" and "Add Purchase Order" button (disabled if no PR, tooltip explains why)
6. Voucher card shows: VCH Reference Number (clickable link), Payee, Status badge, Created By, Created Date; if VCH doesn't exist: gray card with "Not Created" and "Add Voucher" button (disabled if no PO)
7. Progressive disclosure: Action buttons (Add PR/PO/VCH) only enabled when prerequisites met; tooltips explain dependencies
8. Status badges color-coded using Shadcn/UI Badge component: Created (gray), In Progress (blue), Completed (green), On Hold (yellow), Cancelled (red)
9. All timestamps displayed in user-friendly format: relative time for recent ("5 minutes ago", "3 hours ago", "2 days ago") with absolute date/time on hover tooltip
10. Currency amounts formatted with thousand separators and PHP peso symbol prefix (₱1,234,567.89)
11. "Edit Procurement" button in Procurement Summary card (top-right); if transactions exist, button shows tooltip "Can only edit Purpose, ABC Amount, and Date fields because transactions exist" instead of being fully disabled
12. Breadcrumb navigation: Home > Procurements > Procurement #{id}
13. RBAC enforced: All roles can view procurement details; action buttons (Add/Edit/Delete) visible only for Endorser/Administrator; Viewer sees complete information in read-only mode
14. Page responsive on mobile: cards stack vertically, tables scroll horizontally, action buttons move to bottom sheet
15. Loading state: Skeleton components displayed while fetching procurement and transaction data (Shadcn/UI Skeleton)
16. Status History section displays timeline of latest 5 status changes with "View All History" link to full history modal

## Story 2.10: Transaction List & Search Functionality

As a **User**,
I want to view and search all transactions across procurements,
so that I can find specific PRs, POs, or VCHs quickly.

**Acceptance Criteria:**

1. Transactions list page at `/transactions` displays unified table of all transaction types (PR, PO, VCH combined)
2. Table columns: Reference Number (sortable), Category (badge: PR/PO/VCH), Procurement ID (clickable link), End User Office, Purpose (truncated), Status (badge), Created By, Created Date (sortable), Actions (dropdown menu)
3. Transactions ordered by Created Date descending (newest first) by default
4. Pagination implemented at 50 transactions per page with page number display and prev/next controls
5. Search bar at top with filters: Reference Number (text input, partial match), Category (dropdown: All/PR/PO/VCH), Status (dropdown: All/Created/In Progress/Completed/On Hold/Cancelled), Date Range (from/to date pickers), End User Office (dropdown with search), Created By Me (checkbox filter)
6. Search executes on form submission (Search button) or real-time with 500ms debounce on text input changes
7. Table headers clickable for sorting: Reference Number (alphanumeric), Category (alphabetical), Created Date (chronological), Status (alphabetical); active sort column shows up/down arrow icon
8. Actions dropdown menu for each row: View Details, Edit (Endorser/Admin only), Delete (Endorser/Admin only with confirmation)
9. View Details navigates to category-specific detail page: `/purchase-requests/{id}`, `/purchase-orders/{id}`, `/vouchers/{id}` based on transaction category
10. RBAC enforced: All roles can view transaction list and details; Viewer sees Actions menu with only "View Details" option
11. Empty state when no results: Shadcn/UI EmptyState component with message "No transactions found matching your filters" and "Clear Filters" button
12. Quick filters bar below search: "My Transactions", "Pending" (status=Created or In Progress), "This Week", "This Month" (clickable chips that apply preset filters)
13. Export button (placeholder for Epic 5): "Export CSV" button displayed with disabled state and tooltip "CSV export available in Epic 5"
14. TypeScript interfaces: `TransactionListItem` (flattened transaction with procurement fields), `TransactionSearchFilters` (filter form values)
15. Mobile responsive: table scrolls horizontally on small screens, filters collapse into expandable panel, search bar stacks vertically

## Story 2.11: Status Transition & History Tracking

As an **Endorser or Administrator**,
I want to transition procurement and transaction statuses with reason tracking,
so that status changes are auditable and reversible if needed.

**Acceptance Criteria:**

1. ProcurementStatusHistory Eloquent model created with fields: procurement_id, old_status, new_status, reason, changed_by_user_id, changed_at (uses history table from Story 2.1)
2. TransactionStatusHistory Eloquent model created with fields: transaction_id, old_status, new_status, reason, changed_by_user_id, changed_at (uses history table from Story 2.1)
3. Status transition service class created: `app/Services/StatusTransitionService.php` with singleton binding
4. Method `transitionProcurementStatus(Procurement $procurement, string $newStatus, ?string $reason): bool` validates allowed transitions, creates history record, updates procurement status, returns true on success
5. Method `transitionTransactionStatus(Transaction $transaction, string $newStatus, ?string $reason): bool` handles transaction status changes (basic transitions for Epic 2, full workflow in Epic 3)
6. Allowed procurement status transitions defined: Created → In Progress, In Progress → Completed, In Progress → On Hold, On Hold → In Progress, any status → Cancelled, Completed → In Progress (reopen with required reason)
7. On procurement detail page, "Change Status" button (Endorser/Admin only) opens modal with: New Status dropdown (shows only valid transitions from current status), Reason textarea (required for Cancelled/On Hold/Reopen, optional for others, max 500 chars), Submit/Cancel buttons
8. Status change validation: (a) Transition must be in allowed list, (b) Cannot transition to Completed unless all created transactions are also Completed (business rule), (c) Reason required for Cancelled/On Hold/Reopen transitions
9. After status change, record created in procurement_status_history with old_status, new_status, reason, changed_by_user_id=current user, changed_at=now()
10. Procurement detail page Status History section displays timeline: Date/Time (formatted), User (name + avatar), Status Change (old → new with arrow icon), Reason (if provided); ordered chronologically newest first, showing latest 5 with "View All" link
11. Transaction status changes follow same pattern using TransactionStatusHistory table (though Epic 2 status changes are manual; workflow automation in Epic 3)
12. Status transition validation prevents invalid transitions: attempting Completed → Created shows error "Cannot reverse completion status. Use Reopen action to return to In Progress."
13. Automatic status transitions: Creating PR automatically transitions procurement from Created → In Progress (only if current status is Created); handled in Story 2.5 PR creation
14. Business rule enforced: Cannot transition procurement to Completed unless all transactions (if any exist) have status=Completed; validation shows error listing incomplete transactions: "Cannot complete procurement: Purchase Request PR-2025-000042 is In Progress"
15. RBAC enforced: Only Endorser and Administrator can change statuses; Viewer can view status history but sees no Change Status button
16. Success/error toast notifications: "Procurement status updated to {new_status}", "Error: {validation error message}"
17. TypeScript interfaces defined: `ProcurementStatusHistory`, `TransactionStatusHistory`, `StatusTransitionRequest { newStatus: string; reason?: string; }`
18. Reopen action: Separate "Reopen" button on Completed procurements opens confirmation modal "Reopening will transition status to In Progress. Provide reason:" with required reason field
19. Status history export: "Download History" button on full history modal exports status changes to CSV (placeholder for Epic 5, button disabled with tooltip)

---
