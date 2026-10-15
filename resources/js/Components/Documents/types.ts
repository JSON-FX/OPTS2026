import type { ActionHistoryEntry, TransactionTimeline } from '@/types/models';
import type { ReferenceNumbering } from '@/Components/Forms/DocumentReferenceFields';
import type { WorkflowPreview } from '@/Components/WorkflowPreviewCard';
export type DocumentKind = 'PR' | 'PO' | 'VCH';
export interface Office { id?: number; name: string; abbreviation?: string; }
export interface LinkedDocument { id: number; transaction?: { reference_number: string; status?: string }; }
export interface Procurement {
    id: number; purpose: string | null; abc_amount: string | number; date_of_entry?: string;
    end_user?: Office; particular?: { description?: string; name?: string };
    purchase_request?: LinkedDocument | null; purchase_order?: LinkedDocument | null; voucher?: LinkedDocument | null;
}
export interface DocumentRecord extends LinkedDocument {
    fund_type_id?: number; fund_type?: { name: string; abbreviation: string; deleted_at?: string | null };
    supplier_id?: number; supplier?: { name: string; address?: string }; supplier_address?: string;
    contract_price?: string | number; payee?: string;
    transaction: {
        id: number; reference_number: string; status: string; procurement_id: number; procurement?: Procurement;
        is_continuation?: boolean; workflow_id?: number | null; workflow?: Workflow | null; current_office_id?: number | null;
        current_office?: Office | null; received_at?: string | null; created_at: string; created_by?: { name: string };
        eta_current_step?: string | null; eta_completion?: string | null; days_at_current_step?: number; delay_days?: number;
        current_step?: { expected_days?: number; is_final_step?: boolean };
    };
}
export interface Workflow { id: number; name: string; steps: { step_order: number; expected_days: number; office: Office | null }[]; }
export interface EditorProps {
    kind: DocumentKind; procurement?: Procurement; record?: DocumentRecord;
    purchaseRequest?: LinkedDocument; purchaseOrder?: LinkedDocument;
    fundTypes?: { id: number; name: string; abbreviation: string; deleted_at?: string | null }[];
    suppliers?: { id: number; name: string; address?: string }[];
    workflows?: Workflow[]; workflowPreview?: WorkflowPreview | null; referenceNumbering?: ReferenceNumbering;
}
export interface DetailProps {
    kind: DocumentKind; record: DocumentRecord; canEdit?: boolean; canDelete?: boolean;
    canEndorse?: boolean; cannotEndorseReason?: string | null; canReceive?: boolean; cannotReceiveReason?: string | null;
    canComplete?: boolean; cannotCompleteReason?: string | null; canHold?: boolean; cannotHoldReason?: string | null;
    canCancel?: boolean; cannotCancelReason?: string | null; canResume?: boolean; cannotResumeReason?: string | null;
    actionTakenOptions?: { id: number; description: string }[]; defaultActionTakenId?: number | null;
    outOfWorkflowInfo?: { is_out_of_workflow: boolean; expected_office_name: string | null; actual_office_name: string | null } | null;
    timeline?: TransactionTimeline; actionHistory?: ActionHistoryEntry[];
}
export const documentNames = { PR: 'Purchase request', PO: 'Purchase order', VCH: 'Voucher' };
export const documentRoutes = { PR: 'purchase-requests', PO: 'purchase-orders', VCH: 'vouchers' };
export const money = (amount: string | number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount));
export const dateTime = (value?: string | null, time = false) => value ? new Date(value).toLocaleString('en-PH', { day: 'numeric', month: 'short', year: 'numeric', ...(time ? { hour: 'numeric', minute: '2-digit' } as const : {}) }) : 'Not recorded';
