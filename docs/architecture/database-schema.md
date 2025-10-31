# Database Schema Overview

This document summarizes the core MySQL schema powering OPTS. It supplements per-story migration details and should be kept in sync with the latest schema changes.

## Naming Conventions

- Table names: snake_case plural (e.g., `procurements`).
- Primary keys: auto-increment `id` bigint.
- Foreign keys: `{related_table_singular}_id`.
- Timestamp columns: `created_at`, `updated_at`, `deleted_at` (for soft deletes).

## Core Tables

### users
- Authentication and RBAC via Spatie Permission.
- Key columns: `name`, `email`, `password`, `office_id`, `is_active`, `email_verified_at`.
- Relationships: `belongsTo` Office, `hasMany` Procurements (created_by).

### offices
- Represents organizational units (end users).
- Columns: `name`, `type`, `abbreviation`, `is_active`, soft deletes.
- Used as foreign key for Procurements (`end_user_id`) and transactions (`current_office_id`).

### particulars
- Catalog of procurement line items/categories.
- Columns: `description`, `is_active`, soft deletes.

### suppliers
- Supplier master data.
- Columns: `name`, `address`, `contact_person`, `contact_number`, `is_active`, soft deletes.

### procurements
- Root entity for the procurement lifecycle.
- Columns: `end_user_id`, `particular_id`, `purpose`, `abc_amount (decimal 15,2)`, `date_of_entry`, `status (enum)`, `created_by_user_id`, timestamps, soft deletes.
- Indexes: `status`, `date_of_entry`.

### transactions
- Polymorphic transaction record for PR/PO/VCH.
- Columns: `procurement_id`, `category (enum)`, `reference_number (unique)`, `status`, `workflow_id (nullable)`, `current_office_id`, `current_user_id`, `created_by_user_id`, timestamps, soft deletes.
- Indexes: `procurement_id`, `category`, `status`, `reference_number`.

### purchase_requests / purchase_orders / vouchers
- Type-specific transaction tables linked `1:1` to `transactions`.
- Enforce unique `transaction_id`.
- Additional columns capture category-specific data (e.g., `fund_type_id`, `supplier_address`, `gross_amount`).

### procurement_status_history / transaction_status_history
- Audit tables capturing status transitions.
- Columns: `{entity}_id`, `old_status`, `new_status`, `reason`, `changed_by_user_id`, `created_at`.
- Cascade delete with parent entity.

### reference_sequences
- Stores per-category, per-year counters for reference number generation.
- Columns: `category`, `year`, `last_sequence`.
- Unique index on (`category`, `year`).

## Supporting Tables

- permission/role tables via Spatie (see migration `2025_10_30_212156_create_permission_tables.php`).
- action_taken, fund_types (Epic 1 master data).

## Index & Constraint Guidelines

- Always define explicit `restrictOnDelete` or appropriate cascade behavior to maintain referential integrity.
- Add composite indexes for frequent filter combinations (e.g., status + date).
- Ensure unique constraints are tested via migration tests.

## Future Schema Extensions

- Workflow definitions (Epic 3) will introduce `workflows` with ordered steps.
- Reference number history may require archival tables once automation is complete.
- Analytics/reporting tables will be added in later epics for dashboards.

Use this overview when planning migrations or writing data-access code to understand existing relationships.

