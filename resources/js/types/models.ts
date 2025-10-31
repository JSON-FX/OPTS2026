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
 */
export interface Transaction {
    id: number;
    procurement_id: number;
    category: TransactionCategory;
    reference_number: string;
    status: TransactionStatus;
    workflow_id: number | null;
    current_office_id: number | null;
    current_user_id: number | null;
    created_by_user_id: number;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

/**
 * Story 2.1 - Purchase Request specific fields.
 */
export interface PurchaseRequest {
    id: number;
    transaction_id: number;
    supplier_id: number;
    purpose: string;
    estimated_budget: number;
    date_of_pr: string;
    created_at: string;
    updated_at: string;
}

/**
 * Story 2.1 - Purchase Order specific fields.
 */
export interface PurchaseOrder {
    id: number;
    transaction_id: number;
    supplier_id: number;
    supplier_address: string;
    purchase_request_id: number;
    particulars: string;
    fund_type_id: number;
    total_cost: number;
    date_of_po: string;
    delivery_date: string | null;
    delivery_term: number | null;
    payment_term: number | null;
    amount_in_words: string;
    mode_of_procurement: string;
    created_at: string;
    updated_at: string;
}

/**
 * Story 2.1 - Voucher specific fields.
 */
export interface Voucher {
    id: number;
    transaction_id: number;
    purchase_order_id: number;
    supplier_id: number;
    obr_number: string | null;
    particulars: string;
    gross_amount: number;
    created_at: string;
    updated_at: string;
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
