# Project Brief — Online Procurement Tracking System (OPTS)

Version: 1.0
Date: October 30, 2025
Owner: (LGU Procurement/ICT)
Goal: End‑to‑end visibility and control of LGU procurement transactions (PR → PO → VCH) with delivery‑tracking‑style timelines, role‑based access, and auditable endorsements across offices.

Notes on consistency
• Abbreviation for Voucher is standardized as VCH.
• PR "Transaction Workflow" dropdown shows PR workflows (not PO).
• VCH "Transaction Creator" refers to the user who creates the VCH.
• Procurement "Completed" when PR, PO, and VCH are all Completed.

⸻

## 1) Executive Summary

OPTS is a web system that tracks a procurement from creation through three dependent transactions—Purchase Request (PR), Purchase Order (PO), and Voucher (VCH)—until completion. Each transaction moves through a Transaction Workflow (ordered offices with expected days per step). The system records timeline events (endorse, receive, complete), computes ETAs and delays, shows current office and assignee, and alerts admins if endorsements happen outside the approved workflow. Dashboards provide counts, activity, and stagnant items; admins manage users, repositories, workflows, announcements, and can bypass misrouted endorsements.

⸻

## 2) Objectives & Success Metrics

Objectives
    •    Single source of truth for procurement status and movement across offices.
    •    Reduce cycle time via clear SLAs, ETAs, and escalation for delays.
    •    Improve accountability with immutable audit trails.
    •    Standardize reference numbering and repositories (Offices, Suppliers, etc.).

Success Metrics
    •    ≥90% of transactions show an on‑time "Receive" within SLA per step.
    •    ≥80% reduction in mis‑routed endorsements after 3 months.
    •    100% PR/PO/VCH transitions logged with actor, office, timestamp, reason.
    •    <1% duplicate reference numbers per month.

⸻

## 3) Scope

In Scope (MVP)
    •    Procurement creation with high‑level fields and automated status.
    •    Transactions: PR → PO → VCH with dependency rules (PO requires PR, VCH requires PO).
    •    Transaction Workflows per category (PR/PO/VCH) with expected days/step.
    •    Endorse/Receive/Complete flows; "outside workflow" detection + notifications.
    •    Dashboard, lists, search/filter, stagnant view, activity feed.
    •    Repositories: Transaction Categories, Offices, Particulars, Suppliers, Fund Types, Statuses, Action Taken.
    •    Admin: User/Role/Office assignment, Repository & Workflow management, Bypass Endorsement, Page access, Announcements.
    •    Exports: CSV/JSON.

Out of Scope (MVP)
    •    E‑signature, document authoring, bidding modules.
    •    Payment disbursement integration; accounting/ERP integrations.
    •    Mobile apps (responsive web only).
    •    Advanced analytics/BI (CSV/JSON export provided).

⸻

## 4) Users & Access

Capability / Page    Viewer    Endorser    Administrator
Dashboard, Lists (read)    ✓    ✓    ✓
Create Procurement        ✓    ✓
Create PR/PO/VCH        ✓    ✓
Endorse / Receive / Complete        ✓    ✓
Announcements (view)    ✓    ✓    ✓
Announcements (manage)            ✓
Repositories / Workflows            ✓
User & Role Management            ✓
Bypass Endorsement            ✓
Page Access Management            ✓
Exports        ✓    ✓

RBAC is enforced across UI and API.

⸻

## 5) Core Concepts & Repositories

Procurement (high‑level record)
    •    End User (select from Office Repository), Particular (select), Purpose, ABC, Date of Entry, Automated Status.

Transactions (child records of a Procurement)
    •    PR: Fund Type required for reference number; Workflow (PR), Status (auto); Creator; Date of Entry.
    •    PO: Contract Price; Supplier & Address (from Supplier Repository); Workflow (PO); Status; Creator; Date of Entry.
    •    VCH: Reference Number (free text); Payee & Address (Supplier Repo); Workflow (VCH); Status; Creator; Date of Entry.

Repositories
    •    Transaction Category (Name, Abbrev: PR, PO, VCH)
    •    Office (Name, Type, Abbrev)
    •    Particular (Name)
    •    Supplier (Name, Address, Contact No?)
    •    Fund Type Category (Name, Abbrev: GF, TF, SEF)
    •    Status (Created, In Progress, On Hold, Cancelled, Completed)
    •    Action Taken (e.g., For Mode of Procurement, For Mayor's Signature, …)

⸻

## 6) State Machines & Business Rules

Procurement Status
    •    Created → automatic on Procurement creation.
    •    In Progress → when first PR is created (or, if later expanded, when any child transaction exists).
    •    On Hold / Cancelled → admin‑set with reason (terminal unless admin reverses per policy).
    •    Completed → when PR, PO, and VCH all have Completed status.

Transaction Status (PR/PO/VCH)
    •    Created → on transaction creation.
    •    In Progress → first Endorse logged.
    •    On Hold / Cancelled → admin‑set with reason.
    •    Completed → explicit completion at final workflow step (Receive → Complete).

Dependencies
    •    Cannot create PO without an existing PR for the same Procurement.
    •    Cannot create VCH without an existing PO for the same Procurement.
    •    One of each (PR, PO, VCH) per Procurement in MVP (multi‑PR/PO/VCH per procurement may be a Phase 2 design decision).

Endorsement Rules
    •    Endorse = move from current step's office to the next office.
    •    Receive = target office accepts and becomes current holder.
    •    If target office ≠ expected next office (per workflow):
    •    Log as out‑of‑workflow; send notifications to Admin and the expected receiving office's assigned user(s).
    •    Admin may use Bypass Endorsement to correct.

⸻

## 7) Reference Numbering

PR: PR-{FUND_ABBR}-{YYYY}-{MM}-{SEQ} → e.g., PR-GF-2025-01-001
PO: PO-{YYYY}-{MM}-{SEQ} → e.g., PO-2025-01-001
VCH: free text (admin‑defined yearly patterns allowed)

Continuation Cases
    •    Prefix with (Continuation)- for carry‑overs (e.g., (Continuation)-PR-GF-2024-01-1095).
    •    Store flags: is_continuation: boolean, continuation_from_year: YYYY.
    •    Sequence uniqueness: {category, fund (for PR), year, month, is_continuation}.

Open Decision: Reset sequence monthly (implied by {MM}) vs annually. Default: monthly; configurable per category.

⸻

## 8) Timeline, ETA & Stagnation Logic

Each workflow step has an expected_days SLA.
    •    ETA for current step = receive_timestamp_of_step_start + expected_days.
    •    ETA to completion = sum of remaining steps' expected_days from now.
    •    Delay (days) = max(0, today - ETA_for_current_step).
    •    Stagnant = (a) Delay > 0, or (b) no movement for idle_threshold_days (default 2, configurable).
    •    UI shows a tracking timeline: prior steps (with timestamps) → current office/user → next offices with ETAs.

⸻

## 9) Pages & UX (MVP)

Dashboard
    •    Cards: totals for PR, PO, VCH; total Procurements.
    •    Table: Offices handling transactions (PR/PO/VCH counts) → click to modal list.
    •    Activity feed: "{user} endorsed {transaction} to {office} {time_ago}"; "{transaction} {ref} completed".
    •    Stagnant panel: delayed items with days overdue.

Procurement
    •    New Procurement (End User, Particular, Purpose, ABC, Date).
    •    Procurement List & Detail: progressive disclosure showing PR/PO/VCH info as created; current office & user.

Transactions
    •    New Transaction (PR/PO/VCH) → shows only workflows for that category.
    •    Transaction List & Detail: timeline, current holder, endorsements log, actions (Endorse / Receive / Complete), history & audit.

Endorsement
    •    Endorse Transaction: select Action Taken (reason) from repository; select receiving office (default = next in workflow).
    •    Receive Transaction: shows current process (Action Taken), confirm receive.
    •    Endorsement Lists.

Exports
    •    CSV / JSON for procurement and linked transactions (filters by date range, office, status, category).

Administrator
    •    User Management (add/edit/remove; assign office; assign role).
    •    Repository Management.
    •    Workflow Management (define category + ordered offices + expected days).
    •    Bypass Endorsement (correct misroutes).
    •    Page Access Management (role → page matrix).
    •    Announcement Management (Normal / Advisory / Emergency; banner + bell).

Notifications
    •    Bell menu with: Out‑of‑workflow route, received items, overdue items, completion, admin notices.

⸻

## 10) Non‑Functional Requirements
    •    Security: RBAC; least privilege; password hashing; transport encryption (HTTPS/TLS); audit trail for all state changes; optional 2FA (Phase 2).
    •    Performance: P95 page load < 2.5s @ 200 concurrent users, 100k transactions.
    •    Reliability: Daily backups; RPO ≤ 24h; RTO ≤ 4h.
    •    Auditability: Immutable event log (endorse/receive/complete/hold/cancel/bypass) with actor, office, IP, timestamp, reason.
    •    Data Integrity: Reference number uniqueness constraints at DB.
    •    Localization: Currency display for ABC/Contract Price; date format YYYY-MM-DD.
    •    Accessibility: WCAG 2.1 AA baseline.
    •    Observability: App logs + metrics; error tracking; SLA dashboards for step delays.

⸻

## 11) Data Model (conceptual)
    •    Procurement (1) — (1) PR — (1) PO — (1) VCH (MVP)
    •    Transaction (abstract parent) → concrete PR/PO/VCH details table per type
    •    TransactionWorkflow (category) — (N) WorkflowStep (ordered)
    •    EndorsementEvent (transaction_id, from_office_id, to_office_id, action_taken_id, actor_user_id, timestamp, note)
    •    Office, User, Role, UserOffice (assignment)
    •    Supplier, Particular, FundType, Status, ActionTaken
    •    Announcement, Notification

Consider polymorphic Transaction with child tables: PRDetails, PODetails, VCHDetails.

⸻

## 12) Validation & Constraints (examples)
    •    Procurement: End User, Particular, Purpose, ABC, Date of Entry are required.
    •    PR requires Fund Type to compute reference number.
    •    Reference numbers are immutable after assignment; collisions prevented by DB unique indexes.
    •    PO requires Supplier (address auto‑fills from Supplier repository).
    •    VCH requires Payee (from Supplier repository).
    •    Status transitions follow state machines; only Admin can set On Hold/Cancelled.
    •    Dependency checks on create (PO needs PR; VCH needs PO).
    •    Workflow for a transaction must belong to the same category (PR/PO/VCH).

⸻

## 13) Sample API (REST, illustrative)

```
POST   /procurements
GET    /procurements?status=&end_user=&q=&page=
GET    /procurements/{id}
PATCH  /procurements/{id}        # limited fields; admin for hold/cancel

POST   /transactions              # {procurement_id, category: PR|PO|VCH, ...}
GET    /transactions?category=&office=&status=&q=&page=
GET    /transactions/{id}
PATCH  /transactions/{id}         # complete, admin hold/cancel

POST   /transactions/{id}/endorse # {to_office_id, action_taken_id, note}
POST   /transactions/{id}/receive # receiver confirms; assigns current user
POST   /transactions/{id}/complete

GET    /workflows?category=
POST   /workflows                  # admin
POST   /workflows/{id}/steps       # [{order, office_id, expected_days}, ...]

GET    /repositories/offices|suppliers|fund-types|particulars|categories|statuses|actions
POST   /admin/bypass-endorsement   # {transaction_id, correct_office_id, reason}
GET    /notifications
GET    /announcements
POST   /announcements              # admin
GET    /exports?format=csv|json&from=&to=&filters=...
```

⸻

## 14) Example JSON Schemas (condensed)

**Procurement**

```json
{
  "id": "uuid",
  "end_user_office_id": "uuid",
  "particular_id": "uuid",
  "purpose": "string",
  "abc_amount": 0,
  "date_of_entry": "2025-01-15",
  "status": "Created|In Progress|On Hold|Cancelled|Completed",
  "current_office_id": "uuid",
  "current_user_id": "uuid",
  "pr_id": "uuid|null",
  "po_id": "uuid|null",
  "vch_id": "uuid|null",
  "created_at": "ts",
  "updated_at": "ts"
}
```

**PRDetails**

```json
{
  "id": "uuid",
  "procurement_id": "uuid",
  "date_of_entry": "2025-01-20",
  "reference_no": "PR-GF-2025-01-001",
  "fund_type_id": "uuid",
  "creator_user_id": "uuid",
  "workflow_id": "uuid",
  "status": "Created|In Progress|On Hold|Cancelled|Completed",
  "is_continuation": false,
  "continuation_from_year": null,
  "current_office_id": "uuid",
  "current_user_id": "uuid"
}
```

**PODetails**

```json
{
  "id": "uuid",
  "procurement_id": "uuid",
  "date_of_entry": "2025-02-05",
  "reference_no": "PO-2025-02-001",
  "contract_price": 0,
  "supplier_id": "uuid",
  "supplier_address": "string",
  "creator_user_id": "uuid",
  "workflow_id": "uuid",
  "status": "Created|In Progress|On Hold|Cancelled|Completed",
  "is_continuation": false,
  "continuation_from_year": null,
  "current_office_id": "uuid",
  "current_user_id": "uuid"
}
```

**VCHDetails**

```json
{
  "id": "uuid",
  "procurement_id": "uuid",
  "date_of_entry": "2025-02-20",
  "reference_no": "string",
  "payee_supplier_id": "uuid",
  "payee_address": "string",
  "creator_user_id": "uuid",
  "workflow_id": "uuid",
  "status": "Created|In Progress|On Hold|Cancelled|Completed",
  "current_office_id": "uuid",
  "current_user_id": "uuid"
}
```

**Workflow & Steps**

```json
{
  "id": "uuid",
  "transaction_category_id": "uuid",
  "name": "PR Standard Route",
  "steps": [
    { "order": 1, "office_id": "uuid", "expected_days": 2 },
    { "order": 2, "office_id": "uuid", "expected_days": 3 }
  ],
  "active": true
}
```

**Endorsement Event**

```json
{
  "id": "uuid",
  "transaction_id": "uuid",
  "from_office_id": "uuid",
  "to_office_id": "uuid",
  "action_taken_id": "uuid",
  "actor_user_id": "uuid",
  "type": "ENDORSE|RECEIVE|COMPLETE|BYPASS",
  "notes": "string",
  "created_at": "ts",
  "out_of_workflow": false
}
```

⸻

## 15) Reference Number Generation (illustrative SQL/pseudocode)
    •    PR unique key: (category='PR', fund_type_id, year, month, is_continuation, seq)
    •    PO unique key: (category='PO', year, month, is_continuation, seq)

**Pseudo**

```sql
BEGIN TX
  SELECT seq FROM sequences
   WHERE category='PR' AND fund_type_id=? AND year=? AND month=? AND is_continuation=?
   FOR UPDATE;
  IF not exists THEN INSERT seq=1 ... ELSE seq=seq+1 UPDATE ...
  Format: PR-{FUND}-{YYYY}-{MM}-{seq:03}
COMMIT
```

⸻

## 16) Audit & Compliance
    •    Every status change and endorsement/receive/complete/bypass recorded with user, office, timestamp, reason, IP.
    •    Readable activity feed + exportable audit log.
    •    Admin overrides (On Hold, Cancel, Bypass) require reason and are visually flagged.

⸻

## 17) Reporting & Exports
    •    CSV/JSON with filters: date range, office, status, category, fund type, supplier.
    •    Standard reports:
    •    Turnaround by step/office (avg days; overdue count).
    •    Out‑of‑workflow incidents.
    •    Volume per month by category & fund type.
    •    Stagnant transactions.

⸻

## 18) Risks & Mitigations
    •    Misrouting → Out‑of‑workflow detection, admin bypass, clear UI nudges for expected next office.
    •    Duplicate refs → DB unique constraints + atomic sequence generation.
    •    Delays → SLA visibility, notifications, stagnant board, escalation policy.
    •    User error → Role‑scoped forms, required fields, inline validation, "are you sure?" on terminal actions.
    •    Scope creep → MVP boundary plus Phase 2 backlog (multi‑PR/PO/VCH; integrations; mobile).

⸻

## 19) Assumptions & Open Decisions
    •    One PR, one PO, one VCH per Procurement (MVP).
    •    Monthly sequence reset; configurable per category.
    •    Idle threshold for "stagnant" = 2 days; configurable.
    •    Supplier Address is canonical in Supplier Repository; displayed read‑only in PO/VCH.
    •    Allowed reversal of On Hold/Cancelled only by Admin; Completed is terminal (reopen requires admin policy).
    •    Single current office & current user per transaction at any time.

⸻

## 20) MVP Acceptance Criteria (examples)

**Create Procurement**
    •    Given valid fields (End User, Particular, Purpose, ABC, Date)
When a user saves
Then a Procurement is created with status Created and appears in lists.

**Create PR & Auto‑progress Procurement**
    •    Given a Procurement with status Created
When an Endorser creates a PR with a PR workflow
Then PR status is Created and Procurement status becomes In Progress.

**Endorse → Receive → Complete**
    •    Given a PR at step 1 office
When the current office endorses to the next office and the next office receives
Then PR status becomes In Progress, current office/user update, and timeline shows ETA based on step SLA.
    •    When final step is completed
Then PR status becomes Completed and is immutable.

**Dependencies**
    •    When creating a PO without an existing PR
Then the system blocks creation with a clear error.

**Outside‑Workflow Detection**
    •    When a transaction is endorsed to a non‑next office
Then log as out‑of‑workflow and notify Admin and expected receiving office's assignee.

**Procurement Completion**
    •    Given PR, PO, VCH all Completed
Then Procurement becomes Completed automatically.

**Exports**
    •    When a user requests CSV for a date range
Then file downloads with joined Procurement + Transactions data.

⸻

## 21) Phase 2 (Backlog Ideas)
    •    Multiple PR/PO/VCH per Procurement with linking rules.
    •    Attachments (PR docs, PO, invoices, receipts).
    •    Email/SMS notifications; calendar reminders.
    •    Org charts & load balancing across users in an office.
    •    Analytics dashboards; SLA heatmaps.
    •    SSO, MFA, and fine‑grained permissions per action.

⸻

## Appendix A — Minimal ER (text)
    •    Procurement(id, end_user_office_id, particular_id, purpose, abc_amount, date_of_entry, status, current_office_id, current_user_id, pr_id, po_id, vch_id, …)
    •    PRDetails(id, procurement_id, fund_type_id, reference_no, workflow_id, status, is_continuation, current_office_id, current_user_id, …)
    •    PODetails(id, procurement_id, supplier_id, contract_price, reference_no, workflow_id, status, is_continuation, …)
    •    VCHDetails(id, procurement_id, payee_supplier_id, reference_no, workflow_id, status, …)
    •    TransactionWorkflow(id, transaction_category_id, name, active)
    •    WorkflowStep(id, workflow_id, step_order, office_id, expected_days)
    •    EndorsementEvent(id, transaction_id, from_office_id, to_office_id, action_taken_id, actor_user_id, type, out_of_workflow, timestamp, note)
    •    Office(id, name, type, abbrev), Supplier(id, name, address, contact_no), Particular(id, name), FundType(id, name, abbrev), ActionTaken(id, name), Status(id, name)
    •    User(id, name, email, role_id), Role(id, name), UserOffice(id, user_id, office_id)
    •    Announcement(id, kind, title, body, active, start_at, end_at)
    •    Notification(id, user_id, type, payload, read_at)

⸻

## Next Steps

This Project Brief provides the full context for OPTS. The next phase is to work with the PM to create a detailed PRD section by section, asking for any necessary clarification or suggesting improvements.
