import type {
    ActivityFeedEntry,
    DashboardSummary,
    OfficeWorkload,
    SlaPerformanceData,
    StagnantTransaction,
} from "./models";
import type { PageProps } from "@/types";

export interface DashboardDocument {
    id: number;
    reference_number: string;
    category: "PR" | "PO" | "VCH";
    status: string;
    procurement_id: number;
    purpose: string;
    current_office_id: number | null;
    current_office_name: string | null;
    current_office_abbreviation: string | null;
    created_at: string;
    url: string | null;
    received_at: string | null;
    next_action: { label: string; url: string } | null;
    attention: DashboardAttentionEntry | null;
    current_step_id: number | null;
    workflow_steps: {
        id: number;
        office_id: number | null;
        step_order: number;
        office_name: string;
        office_abbreviation: string;
        expected_days: number;
    }[];
}

export interface DashboardAttentionEntry extends StagnantTransaction {
    current_office_id: number;
    current_office_abbreviation: string;
    expected_days: number | null;
    idle_days: number;
    reason: "overdue" | "idle";
    awaiting_receipt: boolean;
    url: string | null;
    status?: string;
    current_step_order?: number | null;
    total_steps?: number;
}

export interface DashboardNotice {
    id: string;
    kind: "update" | "maintenance" | "emergency" | "reminder";
    title: string;
    message: string;
    detail?: string;
    audience?: string;
    publisher?: string;
}

export interface DashboardFilters {
    office_id: number | null;
    category: "all" | "PR" | "PO" | "VCH";
    search: string;
    active_only: boolean;
    sort: "newest" | "reference_asc" | "reference_desc";
}

export interface DashboardProps extends PageProps {
    summary: DashboardSummary;
    officeWorkload: OfficeWorkload[];
    activityFeed: ActivityFeedEntry[];
    stagnantTransactions: DashboardAttentionEntry[];
    slaPerformance: SlaPerformanceData;
    annualIncidentCount: number;
    userOfficeId: number | null;
    offices: { id: number; name: string; abbreviation: string }[];
    filters: DashboardFilters;
    attentionMeta: {
        total: number;
        overdue_count: number;
        idle_count: number;
        idle_threshold_days: number;
    };
    announcements: DashboardNotice[];
    selectedDocument: DashboardDocument | null;
    queueDocuments: DashboardProps["documents"] | null;
    queueFilters: DashboardFilters | null;
    pendingDocuments: DashboardDocument[];
    documents: {
        data: DashboardDocument[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}
