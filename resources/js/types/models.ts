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
    created_by_user_id: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relationships
    procurement?: Procurement;
    created_by?: User;
}

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
