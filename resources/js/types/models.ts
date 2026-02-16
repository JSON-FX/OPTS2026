export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    office_id?: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    office?: Office;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface Office {
    id: number;
    name: string;
    type: string;
    abbreviation: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Supplier {
    id: number;
    name: string;
    address: string;
    contact_person: string | null;
    contact_number: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

/**
 * Business rule validation response for procurement transaction dependencies.
 * Used by frontend to check if transaction creation/deletion is allowed.
 *
 * @example
 * ```ts
 * const validation: BusinessRuleValidation = {
 *   canProceed: false,
 *   errorMessage: 'You must create a Purchase Request before adding a Purchase Order'
 * };
 * ```
 */
export interface BusinessRuleValidation {
    canProceed: boolean;
    errorMessage?: string;
}

export interface Particular {
    id: number;
    description: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface FundType {
    id: number;
    name: string;
    abbreviation: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface ActionTaken {
    id: number;
    description: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface UserFormData {
    name: string;
    email: string;
    password?: string;
    password_confirmation?: string;
    role: string;
    office_id?: number;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

/**
 * Story 2.1 - Database Schema: Shared status enums across procurement lifecycle.
 */
export type ProcurementStatus = 'Created' | 'In Progress' | 'Completed' | 'On Hold' | 'Cancelled';
export type TransactionStatus = ProcurementStatus;
export type TransactionCategory = 'PR' | 'PO' | 'VCH';

/**
 * Story 2.1 - Procurement aggregate root.
 */
export interface Procurement {
    id: number;
    end_user_id: number;
    particular_id: number;
    purpose: string | null;
    abc_amount: number;
    date_of_entry: string;
    status: ProcurementStatus;
    created_by_user_id: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    end_user?: Office;
    particular?: Particular;
    creator?: User;
    purchase_request?: PurchaseRequest;
    purchase_order?: PurchaseOrder;
    voucher?: Voucher;
    status_history?: ProcurementStatusHistory[];
    transactions_count?: number;
}

/**
 * Story 2.1 - Base transaction record shared by PR/PO/VCH.
 * Story 2.6 - Added is_continuation field for manual reference numbers.
 * Story 3.3 - Added tracking columns and actions relationship.
 * Story 3.9 - Added ETA & delay computed attributes.
 */
export interface Transaction {
    id: number;
    procurement_id: number;
    category: TransactionCategory;
    reference_number: string;
    is_continuation: boolean;
    status: TransactionStatus;
    workflow_id: number | null;
    current_office_id: number | null;
    current_user_id: number | null;
    current_step_id: number | null;
    received_at: string | null;
    endorsed_at: string | null;
    created_by_user_id: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Story 3.9 - ETA computed attributes
    eta_current_step: string | null;
    eta_completion: string | null;
    delay_days: number;
    is_stagnant: boolean;
    delay_severity: DelaySeverity;
    days_at_current_step: number;

    // Relationships
    procurement?: Procurement;
    created_by?: User;
    current_step?: WorkflowStep;
    actions?: TransactionAction[];
}

/**
 * Story 3.9 - Delay severity levels for transaction ETA tracking.
 */
export type DelaySeverity = 'on_track' | 'warning' | 'overdue';

/**
 * Story 2.5 - Purchase Request specific fields.
 * Extends Transaction via transaction_id FK.
 */
export interface PurchaseRequest {
    id: number;
    transaction_id: number;
    fund_type_id: number;
    created_at: string;
    updated_at: string;
    transaction?: Transaction;
    fund_type?: FundType;
}

/**
 * Story 2.1 - Purchase Order specific fields.
 */
/**
 * Story 2.6/2.7 - Purchase Order transaction with manual reference numbers.
 */
export interface PurchaseOrder {
    id: number;
    transaction_id: number;
    supplier_id: number;
    supplier_address: string; // Snapshot, immutable after creation
    contract_price: number;
    created_at: string;
    updated_at: string;

    // Relationships
    transaction?: Transaction;
    supplier?: Supplier;
}

/**
 * Story 2.8 - Voucher transaction with auto-generated reference numbers.
 */
export interface Voucher {
    id: number;
    transaction_id: number;
    payee: string; // Free-text field, max 255 chars
    created_at: string;
    updated_at: string;

    // Relationships
    transaction?: Transaction;
}

/**
 * Story 2.1 - Status history audit trail entries.
 */
export interface ProcurementStatusHistory {
    id: number;
    procurement_id: number;
    old_status: ProcurementStatus | null;
    new_status: ProcurementStatus;
    reason: string | null;
    changed_by_user_id: number;
    created_at: string;
    changed_by?: User;
}

export interface TransactionStatusHistory {
    id: number;
    transaction_id: number;
    old_status: TransactionStatus | null;
    new_status: TransactionStatus;
    reason: string | null;
    changed_by_user_id: number;
    created_at: string;
}

/**
 * Story 2.1 - Reference number sequence counter per year/category.
 */
export interface ReferenceSequence {
    id: number;
    category: TransactionCategory;
    year: number;
    last_sequence: number;
    created_at: string;
    updated_at: string;
}

export type ProcurementFormFields = 'end_user_id' | 'particular_id' | 'purpose' | 'abc_amount' | 'date_of_entry';

export type ProcurementFormData = Record<ProcurementFormFields, string>;

/**
 * Story 2.10 - Flattened transaction list item combining transaction + procurement fields.
 */
export interface TransactionListItem {
    id: number;
    reference_number: string;
    category: TransactionCategory;
    status: TransactionStatus;
    procurement_id: number;
    procurement_end_user_name: string;
    procurement_purpose: string;
    created_by_name: string;
    created_at: string;
}

/**
 * Story 2.10 - Transaction search filter form values.
 */
export interface TransactionSearchFilters {
    reference_number?: string;
    category?: TransactionCategory | '';
    status?: TransactionStatus | '';
    date_from?: string;
    date_to?: string;
    end_user_id?: number | '';
    current_office_id?: number | '';
    created_by_me?: boolean;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

/**
 * Story 3.1 - Workflow definition for transaction routing.
 *
 * A workflow defines the ordered sequence of offices that a transaction
 * must pass through, with expected completion days per step.
 */
export interface Workflow {
    id: number;
    category: TransactionCategory;
    name: string;
    description: string | null;
    is_active: boolean;
    created_by_user_id: number | null;
    created_at: string;
    updated_at: string;
    steps?: WorkflowStep[];
    steps_count?: number;
    created_by?: User;
}

/**
 * Story 3.1 - Individual step within a workflow.
 *
 * Each step represents an office in the transaction routing sequence,
 * with an expected number of days to complete the step.
 */
export interface WorkflowStep {
    id: number;
    workflow_id: number;
    office_id: number;
    step_order: number;
    expected_days: number;
    is_final_step: boolean;
    created_at: string;
    updated_at: string;
    workflow?: Workflow;
    office?: Office;
}

/**
 * Story 3.3 - Action type for transaction actions.
 */
export type ActionType = 'endorse' | 'receive' | 'complete' | 'hold' | 'cancel' | 'bypass';

/**
 * Story 3.3 - Transaction action record for endorsement history and audit trail.
 *
 * Each action captures who did what, from where to where, when, and at what
 * workflow step the action occurred.
 */
export interface TransactionAction {
    id: number;
    transaction_id: number;
    action_type: ActionType;
    action_taken_id: number | null;
    from_office_id: number | null;
    to_office_id: number | null;
    from_user_id: number;
    to_user_id: number | null;
    workflow_step_id: number | null;
    is_out_of_workflow: boolean;
    notes: string | null;
    reason: string | null;
    ip_address: string | null;
    created_at: string;

    // Relationships
    transaction?: Transaction;
    action_taken?: ActionTaken;
    from_office?: Office;
    to_office?: Office;
    from_user?: User;
    to_user?: User;
    workflow_step?: WorkflowStep;
}

/**
 * Flattened activity timeline entry for the procurement show page.
 * Combines transaction action data with transaction context.
 */
export interface ActivityTimelineEntry {
    id: number;
    action_type: ActionType;
    transaction_category: TransactionCategory;
    transaction_reference_number: string;
    from_user: { id: number; name: string } | null;
    to_user: { id: number; name: string } | null;
    from_office: { id: number; name: string; abbreviation: string } | null;
    to_office: { id: number; name: string; abbreviation: string } | null;
    action_taken: string | null;
    notes: string | null;
    reason: string | null;
    is_out_of_workflow: boolean;
    created_at: string;
}

/**
 * Story 3.10 - Timeline step status for visual timeline display.
 */
export type TimelineStepStatus = 'completed' | 'current' | 'upcoming';

/**
 * Story 3.10 - Individual step in the transaction timeline.
 */
export interface TimelineStep {
    step_order: number;
    office: { id: number; name: string; abbreviation?: string };
    expected_days: number;
    is_final_step: boolean;
    status: TimelineStepStatus;
    // For completed steps
    completed_at?: string;
    completed_by?: { id: number; name: string };
    actual_days?: number | null;
    // For current step
    current_holder?: { id: number; name: string } | null;
    days_at_step?: number;
    eta?: string | null;
    is_overdue?: boolean;
    // For upcoming steps
    estimated_arrival?: string;
}

/**
 * Story 3.10 - Full transaction timeline data structure.
 */
export interface TransactionTimeline {
    steps: TimelineStep[];
    progress_percentage: number;
    total_steps: number;
    completed_steps: number;
    is_out_of_workflow: boolean;
}

/**
 * Story 3.10 - Action history entry for display below timeline.
 */
export interface ActionHistoryEntry {
    id: number;
    action_type: ActionType;
    from_user: { id: number; name: string } | null;
    to_user: { id: number; name: string } | null;
    from_office: { id: number; name: string; abbreviation: string } | null;
    to_office: { id: number; name: string; abbreviation: string } | null;
    action_taken: string | null;
    notes: string | null;
    reason: string | null;
    is_out_of_workflow: boolean;
    workflow_step_order: number | null;
    created_at: string;
}

/**
 * Story 4.1.2 - Activity feed entry for dashboard recent activity panel.
 */
export interface ActivityFeedEntry {
    id: number;
    action_type: ActionType;
    transaction_reference_number: string;
    transaction_id: number;
    transaction_category: TransactionCategory;
    actor_name: string;
    from_office: string | null;
    to_office: string | null;
    is_out_of_workflow: boolean;
    purchase_request_id?: number;
    purchase_order_id?: number;
    voucher_id?: number;
    created_at: string;
}

/**
 * Story 4.1.2 - Stagnant transaction entry for dashboard needs-attention panel.
 */
export interface StagnantTransaction {
    id: number;
    reference_number: string;
    category: TransactionCategory;
    current_office_name: string;
    delay_days: number;
    delay_severity: DelaySeverity;
    days_at_current_step: number;
    purchase_request_id?: number;
    purchase_order_id?: number;
    voucher_id?: number;
}

/**
 * Story 4.1.1 - Dashboard summary card status counts.
 */
export interface StatusCounts {
    total: number;
    created: number;
    in_progress: number;
    completed: number;
    on_hold: number;
    cancelled: number;
}

/**
 * Story 4.1.1 - Dashboard summary data for all categories.
 */
export interface DashboardSummary {
    procurements: StatusCounts;
    purchase_requests: StatusCounts;
    purchase_orders: StatusCounts;
    vouchers: StatusCounts;
}

/**
 * Story 4.1.1 - Office workload row for dashboard table.
 */
export interface OfficeWorkload {
    office_id: number;
    office_name: string;
    office_abbreviation: string;
    pr_count: number;
    po_count: number;
    vch_count: number;
    total: number;
    stagnant_count: number;
}
