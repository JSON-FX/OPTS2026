# Epic 3: Workflow Engine & Endorsement System

**Epic Goal:**

Implement the workflow management engine enabling administrators to define category-specific transaction workflows as ordered sequences of office steps with expected completion days per step. Build the complete endorsement system supporting three core actions: Endorse (move transaction to next office), Receive (accept transaction at current office), and Complete (mark transaction finished at final step). Implement transaction state machine with automatic status transitions, current office/user tracking, out-of-workflow detection for misrouted endorsements with notifications to administrators and expected recipients, ETA and delay calculations based on workflow SLAs, and visual timeline representations showing completed steps, current location, and upcoming steps with ETAs. This epic delivers the core "delivery tracking" experience where users see exactly where transactions are, when they're expected to arrive, and receive alerts when routing deviates from approved workflows. By epic completion, the system provides full workflow-driven transaction routing with SLA monitoring and proactive misroute prevention.

---

## Story Summary

| Story | Title | Status | Dependencies |
|-------|-------|--------|--------------|
| 3.1 | Workflow Schema & Model Foundation | Draft | 1.4, 2.1 |
| 3.2 | Workflow Admin Management UI | Draft | 3.1, 1.4, 1.7 |
| 3.3 | Transaction Actions Schema & Models | Draft | 3.1, 2.1, 1.6 |
| 3.4 | Endorse Action Implementation | Draft | 3.3, 3.1, 2.9 |
| 3.5 | Receive Action Implementation | Draft | 3.4, 3.3 |
| 3.6 | Complete Action Implementation | Draft | 3.5, 3.3, 3.1 |
| 3.7 | Transaction State Machine | Draft | 3.4, 3.5, 3.6, 3.3 |
| 3.8 | Out-of-Workflow Detection & Notifications | Draft | 3.4, 3.3 |
| 3.9 | ETA & Delay Calculations | Draft | 3.1, 3.3, 3.5 |
| 3.10 | Timeline Visualization | Draft | 3.9, 3.3, 3.1 |
| 3.11 | Workflow Assignment on Transaction Creation | Draft | 3.1, 3.2, 3.4 |

---

## Story 3.1: Workflow Schema & Model Foundation

**As a** developer,
**I want** database tables and Eloquent models for workflow definitions with ordered office steps and expected completion days,
**so that** the system can store and manage category-specific transaction routing configurations.

**Key Deliverables:**
- `workflow_steps` table migration
- Workflow model enhancement (description, created_by_user_id)
- WorkflowStep model with relationships and navigation methods
- TypeScript interfaces for Workflow and WorkflowStep
- Model factories and seeders

**Story File:** [3.1.workflow-schema-model-foundation.md](../stories/3.1.workflow-schema-model-foundation.md)

---

## Story 3.2: Workflow Admin Management UI

**As an** Administrator,
**I want** to create and manage transaction workflows with ordered office steps and expected completion days,
**so that** I can configure how PR, PO, and VCH transactions route through the organization.

**Key Deliverables:**
- Workflow list page with filters and pagination
- Workflow create form with dynamic step builder
- Workflow edit form with step management
- Workflow detail view with step visualization
- RBAC enforcement (Administrator only)

**Story File:** [3.2.workflow-admin-management-ui.md](../stories/3.2.workflow-admin-management-ui.md)

---

## Story 3.3: Transaction Actions Schema & Models

**As a** developer,
**I want** database tables and Eloquent models to record transaction actions (Endorse, Receive, Complete),
**so that** the system can track the complete endorsement history and audit trail for each transaction.

**Key Deliverables:**
- `transaction_actions` table migration
- Transaction tracking columns (current_step_id, received_at, endorsed_at)
- TransactionAction model with relationships and scopes
- Action type constants (endorse, receive, complete, hold, cancel, bypass)
- TypeScript interfaces

**Story File:** [3.3.transaction-actions-schema-models.md](../stories/3.3.transaction-actions-schema-models.md)

---

## Story 3.4: Endorse Action Implementation

**As an** Endorser or Administrator,
**I want** to endorse a transaction to send it to the next office in the workflow,
**so that** transactions progress through the approval chain.

**Key Deliverables:**
- EndorsementService with endorse() method
- Endorse controller and form request
- Endorse modal/form with target office selection
- Out-of-workflow detection flag
- Transaction detail integration

**Story File:** [3.4.endorse-action-implementation.md](../stories/3.4.endorse-action-implementation.md)

---

## Story 3.5: Receive Action Implementation

**As an** Endorser or Administrator,
**I want** to receive a transaction that has been endorsed to my office,
**so that** I become the current holder and can process or further endorse it.

**Key Deliverables:**
- Pending receipts list page
- Receive action processing with workflow step advancement
- Bulk receive functionality
- Navigation badge showing pending count
- Transaction detail receive button

**Story File:** [3.5.receive-action-implementation.md](../stories/3.5.receive-action-implementation.md)

---

## Story 3.6: Complete Action Implementation

**As an** Endorser or Administrator at the final workflow step,
**I want** to mark a transaction as complete,
**so that** the procurement progresses and the transaction is finalized.

**Key Deliverables:**
- Complete action at final workflow step only
- Transaction status transition to "Completed"
- Procurement status update (when VCH completes with PR+PO done)
- Complete confirmation modal
- Status history logging

**Story File:** [3.6.complete-action-implementation.md](../stories/3.6.complete-action-implementation.md)

---

## Story 3.7: Transaction State Machine

**As a** developer,
**I want** a robust state machine governing transaction status transitions with validation rules,
**so that** transactions progress through valid states only and all transitions are properly logged.

**Key Deliverables:**
- TransactionStateMachine service
- Valid transition rules enforcement
- Admin Hold/Cancel/Resume actions
- Status badges with colors
- Status history display

**Story File:** [3.7.transaction-state-machine.md](../stories/3.7.transaction-state-machine.md)

---

## Story 3.8: Out-of-Workflow Detection & Notifications

**As an** Administrator,
**I want** to be notified when transactions are endorsed to offices outside the expected workflow,
**so that** I can identify and correct misrouting promptly.

**Key Deliverables:**
- Out-of-workflow detection on endorsement
- Notifications table and Laravel notification system
- Bell icon with unread count badge
- Notification panel/dropdown
- Out-of-workflow warning banner on transaction detail

**Story File:** [3.8.out-of-workflow-detection-notifications.md](../stories/3.8.out-of-workflow-detection-notifications.md)

---

## Story 3.9: ETA & Delay Calculations

**As a** user,
**I want** to see estimated completion times and delay indicators for transactions,
**so that** I can track progress against SLAs and identify bottlenecks.

**Key Deliverables:**
- EtaCalculationService with business days handling
- Current step ETA and completion ETA calculations
- Delay detection (days overdue, stagnant flag)
- Delay severity levels (on_track, warning, overdue)
- Transaction model accessors for ETA data

**Story File:** [3.9.eta-delay-calculations.md](../stories/3.9.eta-delay-calculations.md)

---

## Story 3.10: Timeline Visualization

**As a** user,
**I want** to see a visual timeline showing completed workflow steps, current position, and upcoming steps with ETAs,
**so that** I can understand transaction progress at a glance like tracking a package delivery.

**Key Deliverables:**
- TransactionTimeline React component
- Completed/current/upcoming step displays
- Progress bar with percentage
- Action history integration
- Responsive horizontal/vertical layout

**Story File:** [3.10.timeline-visualization.md](../stories/3.10.timeline-visualization.md)

---

## Story 3.11: Workflow Assignment on Transaction Creation

**As an** Endorser or Administrator creating a transaction,
**I want** the appropriate workflow automatically assigned based on transaction category,
**so that** the transaction follows the correct approval path from creation.

**Key Deliverables:**
- WorkflowAssignmentService
- Automatic workflow assignment on transaction creation
- Workflow preview on transaction create forms
- Optional "Create & Endorse" immediate endorsement
- Validation for active workflow requirement

**Story File:** [3.11.workflow-assignment-transaction-creation.md](../stories/3.11.workflow-assignment-transaction-creation.md)

---

## Acceptance Criteria Summary

By the end of Epic 3, the system will:

1. **Workflow Management**: Administrators can define workflows with ordered office steps and expected completion days per category (PR, PO, VCH)

2. **Endorsement System**: Users can endorse transactions to next offices, receive pending transactions, and complete transactions at final steps

3. **State Machine**: Transaction status transitions are governed by rules (Created → In Progress → Completed, with Hold/Cancel options)

4. **Out-of-Workflow Detection**: System detects and notifies when transactions are endorsed outside expected workflow, enabling prompt correction

5. **ETA & Delays**: Users see expected completion dates and delay indicators for SLA monitoring

6. **Timeline Visualization**: Delivery-tracking-style timeline shows completed steps, current position, and upcoming steps with ETAs

7. **Automatic Assignment**: Transactions are automatically assigned appropriate workflows on creation

---

## Technical Architecture

### New Services
- `WorkflowAssignmentService` - Assigns workflows to transactions
- `EndorsementService` - Handles endorse/receive/complete actions
- `TransactionStateMachine` - Governs status transitions
- `EtaCalculationService` - Computes ETAs and delays
- `TimelineService` - Generates timeline data

### New Models
- `WorkflowStep` - Ordered steps within a workflow
- `TransactionAction` - Records all endorsement actions

### New Tables
- `workflow_steps` - Workflow step definitions
- `transaction_actions` - Endorsement action history
- `notifications` - Laravel notifications

### Key Patterns
- State machine for transaction status
- Event-driven notifications
- Service layer for business logic
- Timeline data structures for visualization
