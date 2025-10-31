# Core Workflows

This document outlines the primary user workflows delivered by OPTS. Use it as a reference when implementing UI flows or validating end-to-end behavior.

## Procurement Lifecycle Overview

1. **Create Procurement**
   - Actor: Endorser/Administrator.
   - Steps:
     1. Navigate to `/procurements`.
     2. Click “New Procurement” → `/procurements/create`.
     3. Fill in required fields (End User, Particular, Purpose, ABC Amount, Date of Entry).
     4. Submit; system creates procurement with status `Created`, redirects to detail view.
   - Notifications: Success toast displaying procurement ID.

2. **Manage Procurement**
   - View list with filters/sorting.
   - Access detail view showing linked transactions and status history.
   - Edit limited fields unless downstream transactions exist.
   - Archive (soft delete) when no transactions are linked.

3. **Transaction Creation (Future Stories)**
   - Adds Purchase Request (PR), Purchase Order (PO), Voucher (VCH) sequentially.
   - Each creation triggers reference number generation and status updates.

## Navigation Flow

- **Authenticated Layout**
  - Side navigation displays modules based on role.
  - Top bar shows breadcrumbs and contextual actions (e.g., “New Procurement” button).
- **Routing**
  - Inertia links handle client-side transitions; maintain query params for filters.

## Validation & Error Handling

- Form validation errors render inline with inputs and in summary (if available).
- Non-success responses trigger error toasts with actionable guidance.
- Confirmation modals appear before destructive actions (archive/delete).

## Access Control

- Viewer: read-only access (list/detail).
- Endorser: create/edit limited fields, cannot delete when transactions exist.
- Administrator: full CRUD including archive/restore.

## Reporting & Audit (Planned)

- Procurement status history recorded automatically; visible on detail page.
- Future dashboards (Epic 4) will surface aggregate counts and SLAs.

Refer back to these workflows to ensure UI/UX decisions align with expected user journeys.

