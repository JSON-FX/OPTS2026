import type { PageProps } from "@/types";
import type { DashboardDocument } from "@/types/dashboard";
import type { ProcurementStatus } from "@/types/models";

export interface DirectoryOptions {
    offices: { id: number; name: string; abbreviation: string }[];
    particulars: { id: number; description: string }[];
}
export interface ProcurementRow {
    id: number;
    end_user_id: number;
    particular_id: number;
    purpose: string;
    abc_amount: string | number;
    date_of_entry: string;
    status: ProcurementStatus;
    created_at: string;
    created_by_user_id: number;
    end_user: DirectoryOptions["offices"][number] | null;
    particular: DirectoryOptions["particulars"][number] | null;
    creator: { id: number; name: string } | null;
    transactions_count: number;
    documents: DashboardDocument[];
    purchase_request?: {
        transaction?: {
            created_by?: { id: number; name: string } | null;
        } | null;
    } | null;
    purchase_order?: {
        transaction?: {
            created_by?: { id: number; name: string } | null;
        } | null;
    } | null;
    voucher?: {
        transaction?: {
            created_by?: { id: number; name: string } | null;
        } | null;
    } | null;
    status_history?: {
        id: number;
        old_status: string | null;
        new_status: string;
        reason?: string | null;
        created_at: string;
        changed_by?: { name: string } | null;
    }[];
}
export interface ProcurementFilters {
    search: string;
    status: string;
    end_user_id: number | null;
    particular_id: number | null;
    date_from: string;
    date_to: string;
    my_procurements: boolean;
    sort: string;
    per_page: number;
}
export interface ProcurementActivity {
    id: number | string;
    action_type: string;
    transaction_id?: number;
    transaction_category: "PR" | "PO" | "VCH";
    transaction_reference_number: string;
    from_user: { id: number; name: string } | null;
    to_user: { id: number; name: string } | null;
    from_office: DirectoryOptions["offices"][number] | null;
    to_office: DirectoryOptions["offices"][number] | null;
    action_taken: string | null;
    notes: string | null;
    reason: string | null;
    is_out_of_workflow: boolean;
    created_at: string;
}
export interface ProcurementPageProps extends PageProps {
    procurements?: {
        data: ProcurementRow[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
    };
    procurement?: ProcurementRow;
    filters?: ProcurementFilters;
    options?: DirectoryOptions & { statuses: ProcurementStatus[] };
    formOptions: DirectoryOptions & { today: string };
    statusCounts?: Record<string, number> & { total: number };
    activityTimeline?: ProcurementActivity[];
    can: { manage: boolean };
    canCreatePR?: boolean;
    canCreatePO?: boolean;
    canCreateVCH?: boolean;
    initialModal?: "create" | "edit";
}
export type DocumentKind = "PR" | "PO" | "VCH";
export type ProcurementDocument = {
    kind: DocumentKind;
    reference: string;
    status: string;
    office: string;
    officeName: string;
    raw: DashboardDocument;
};
export type ProcurementRecord = {
    id: number;
    officeId: string;
    office: string;
    officeName: string;
    particularId: string;
    particular: string;
    purpose: string;
    amount: number;
    date: string;
    creator: string;
    status: ProcurementStatus;
    documents: ProcurementDocument[];
    raw: ProcurementRow;
};
export const procurementStatuses: ProcurementStatus[] = [
    "Created",
    "In Progress",
    "Completed",
    "On Hold",
    "Cancelled",
];
export const documentName = (kind: DocumentKind) =>
    ({ PR: "Purchase request", PO: "Purchase order", VCH: "Voucher" })[kind];
export const money = (value: number) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(value);
export const entryDate = (value: string) =>
    new Intl.DateTimeFormat("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        timeZone: "Asia/Manila",
    }).format(
        new Date(value.length === 10 ? value + "T12:00:00+08:00" : value),
    );
export const activityDate = (value: string) =>
    new Intl.DateTimeFormat("en-PH", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
        timeZone: "Asia/Manila",
    }).format(new Date(value));
export function procurementRecord(row: ProcurementRow): ProcurementRecord {
    return {
        id: row.id,
        officeId: String(row.end_user_id),
        office: row.end_user?.abbreviation ?? "—",
        officeName: row.end_user?.name ?? "Unassigned office",
        particularId: String(row.particular_id),
        particular: row.particular?.description ?? "—",
        purpose: row.purpose,
        amount: Number(row.abc_amount),
        date: row.date_of_entry.slice(0, 10),
        creator: row.creator?.name ?? "Unknown user",
        status: row.status,
        raw: row,
        documents: (row.documents ?? []).map((doc) => ({
            kind: doc.category,
            reference: doc.reference_number,
            status: doc.status,
            office: doc.current_office_abbreviation ?? "—",
            officeName: doc.current_office_name ?? "Unassigned office",
            raw: doc,
        })),
    };
}
