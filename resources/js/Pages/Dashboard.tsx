import { useRef, useState, type ReactNode } from "react";
import { Head, Link, router, usePage, usePoll } from "@inertiajs/react";
import {
    ArrowRight,
    CheckCheck,
    ChevronRight,
    FileText,
    FolderOpen,
    Receipt,
    ShieldCheck,
    ShoppingBag,
} from "lucide-react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import DashboardSettings, {
    loadDashboardSettings,
} from "@/Components/DashboardSettings";
import DocumentDetail from "@/Components/Dashboard/DocumentDetail";
import AttentionPanel from "@/Components/Dashboard/AttentionPanel";
import Announcements from "@/Components/Dashboard/Announcements";
import type { ActivityFeedEntry, StatusCounts } from "@/types/models";
import type {
    DashboardDocument,
    DashboardFilters,
    DashboardProps,
} from "@/types/dashboard";
import DocumentRegister from "@/Components/Dashboard/DocumentRegister";
import DashboardDialogs, {
    type DashboardModal,
} from "@/Components/Dashboard/DashboardDialogs";

function PanelTitle({
    title,
    detail,
    children,
}: {
    title: string;
    detail?: string;
    children?: ReactNode;
}) {
    return (
        <div className="op-panel-title">
            <div>
                <h2>{title}</h2>
                {detail && <p>{detail}</p>}
            </div>
            {children}
        </div>
    );
}

function summaryHint(counts: StatusCounts) {
    if (!counts.total) return "No records yet";
    return (
        ["completed", "in_progress", "created", "on_hold", "cancelled"] as const
    )
        .filter((key) => counts[key] > 0)
        .map(
            (key) =>
                `${counts[key]} ${{ completed: "completed", in_progress: "in progress", created: "created", on_hold: "on hold", cancelled: "cancelled" }[key]}`,
        )
        .join(" · ");
}

function activityUrl(entry: ActivityFeedEntry) {
    if (entry.purchase_request_id)
        return route("purchase-requests.show", entry.purchase_request_id);
    if (entry.purchase_order_id)
        return route("purchase-orders.show", entry.purchase_order_id);
    if (entry.voucher_id) return route("vouchers.show", entry.voucher_id);
    return route("transactions.index", {
        reference_number: entry.transaction_reference_number,
    });
}

export default function Dashboard({
    summary,
    officeWorkload,
    activityFeed,
    stagnantTransactions,
    slaPerformance,
    annualIncidentCount,
    userOfficeId,
    offices,
    filters,
    documents,
    attentionMeta,
    announcements,
    selectedDocument,
    pendingDocuments,
    queueDocuments,
    queueFilters,
}: DashboardProps) {
    // Queue requests own their payload; a background poll must not replace it.
    usePoll(30_000, {
        except: ["queueDocuments", "queueFilters"],
        preserveUrl: true,
    });
    const {
        auth,
        selectedYear,
        pendingReceiptsCount = 0,
    } = usePage<DashboardProps>().props;
    const viewer =
        auth.user.roles?.some((role) => role.name === "Viewer") ?? true;
    const [visibility, setVisibility] = useState(() =>
        loadDashboardSettings(auth.user.id),
    );
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [modal, setModal] = useState<DashboardModal>(null);
    const [previousModal, setPreviousModal] = useState<DashboardModal>(null);
    const returnFocus = useRef<HTMLElement | null>(null);
    const [metricsTab, setMetricsTab] = useState("workload");
    const [loading, setLoading] = useState(false);
    const [queueLoading, setQueueLoading] = useState(false);
    const [queueError, setQueueError] = useState<string | null>(null);
    const [queueContext, setQueueContext] = useState<DashboardFilters | null>(
        queueFilters,
    );
    const rows = documents.data;
    const selected =
        rows.find((document) => document.id === selectedId) ?? rows[0];
    const modalSelected =
        selectedDocument?.id === selectedId
            ? selectedDocument
            : (pendingDocuments.find(
                  (document) => document.id === selectedId,
              ) ??
              queueDocuments?.data.find(
                  (document) => document.id === selectedId,
              ) ??
              rows.find((document) => document.id === selectedId));
    const office = offices.find((item) => item.id === filters.office_id);
    const scopeLabel =
        filters.office_id == null
            ? "all offices"
            : (office?.abbreviation ?? "selected office");
    const scopeValue =
        filters.office_id == null
            ? "all"
            : filters.office_id === userOfficeId
              ? "mine"
              : "selected";
    const activeMetricsTab =
        metricsTab === "workload" && !visibility.officeWorkload
            ? "performance"
            : metricsTab === "performance" && !visibility.performanceMetrics
              ? "workload"
              : metricsTab;

    const visitFilters = (
        changes: Partial<DashboardFilters>,
        onSuccess?: () => void,
    ) => {
        const next = { ...filters, ...changes };
        router.get(
            route("dashboard"),
            {
                ...(next.office_id != null
                    ? { office_id: next.office_id }
                    : {}),
                ...(next.category !== "all" ? { category: next.category } : {}),
                ...(next.search ? { search: next.search } : {}),
                ...(next.active_only ? { active_only: 1 } : {}),
                ...(next.sort !== "newest" ? { sort: next.sort } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoading(true),
                onSuccess,
                onFinish: () => setLoading(false),
            },
        );
    };
    const rememberFocus = () => {
        if (modal) return;
        const active = document.activeElement;
        returnFocus.current =
            active instanceof HTMLElement && active !== document.body
                ? active
                : document.querySelector<HTMLButtonElement>(
                      window.matchMedia("(max-width:800px)").matches
                          ? ".op-mobile-navigation button"
                          : ".op-desktop-collapse button",
                  );
    };
    const showModal = (next: DashboardModal) => {
        rememberFocus();
        setPreviousModal(null);
        setModal(next);
    };
    const selectDocument = (
        document: DashboardDocument,
        forceModal = false,
    ) => {
        setSelectedId(document.id);
        if (forceModal || window.matchMedia("(max-width: 1100px)").matches) {
            rememberFocus();
            if (modal === "queue" || modal === "pending")
                setPreviousModal(modal);
            setModal("detail");
        }
    };
    const queueQuery = (context: DashboardFilters, page = 1) => ({
        queue_office_id: context.office_id,
        queue_category: context.category,
        queue_search: context.search,
        queue_sort: context.sort,
        queue_active_only: context.active_only ? 1 : 0,
        queue_page: page,
    });
    const requestQueue = (next: DashboardFilters, page = 1) => {
        setQueueContext(next);
        setQueueError(null);
        router.get(
            route("dashboard"),
            {
                ...filters,
                active_only: filters.active_only ? 1 : 0,
                page: documents.current_page,
                ...queueQuery(next, page),
            },
            {
                only: [
                    "queueDocuments",
                    "queueFilters",
                    "auth",
                    "pendingReceiptsCount",
                    "pendingDocuments",
                ],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setQueueLoading(true),
                onSuccess: (page) =>
                    setQueueContext(
                        page.props.queueFilters as DashboardFilters,
                    ),
                onError: () => {
                    setQueueContext(queueFilters);
                    setQueueError(
                        "Unable to load office documents. Close this queue and try again.",
                    );
                },
                onFinish: () => setQueueLoading(false),
            },
        );
    };
    const openWorkload = (
        officeId: number,
        category: DashboardFilters["category"],
    ) => {
        showModal("queue");
        requestQueue({
            office_id: officeId,
            category,
            search: "",
            sort: "newest",
            active_only: true,
        });
    };
    const openDocument = (id: number) => {
        rememberFocus();
        const row = [
            ...documents.data,
            ...pendingDocuments,
            ...(queueDocuments?.data ?? []),
        ].find((document) => document.id === id);
        if (row) {
            selectDocument(row, true);
            return;
        }
        router.get(
            route("dashboard"),
            {
                ...filters,
                active_only: filters.active_only ? 1 : 0,
                page: documents.current_page,
                document_id: id,
                ...(queueContext
                    ? queueQuery(
                          queueContext,
                          queueDocuments?.current_page ?? 1,
                      )
                    : {}),
            },
            {
                only: ["selectedDocument", "auth", "pendingReceiptsCount"],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
                onSuccess: (page) => {
                    if (page.props.selectedDocument) {
                        setSelectedId(id);
                        if (modal === "queue" || modal === "pending")
                            setPreviousModal(modal);
                        setModal("detail");
                    }
                },
            },
        );
    };
    const closeModal = () => {
        if (modal === "detail" && previousModal) {
            setModal(previousModal);
            setPreviousModal(null);
        } else {
            setModal(null);
            setPreviousModal(null);
        }
    };
    const chooseOffice = (id: number | null) =>
        visitFilters({ office_id: id, active_only: false });
    const categoryCounts = [
        summary.purchase_requests.total,
        summary.purchase_orders.total,
        summary.vouchers.total,
    ];
    const volumeTotal = categoryCounts.reduce((sum, value) => sum + value, 0);

    return (
        <DashboardLayout
            onHelp={() => showModal("help")}
            onPendingReceipts={() => showModal("pending")}
            onViewDocument={(id) => {
                openDocument(id);
                returnFocus.current = document.querySelector(
                    "[data-notification-trigger]",
                );
            }}
        >
            <Head title="Dashboard" />
            <main id="dashboard-main" className="op-main" aria-busy={loading}>
                <Announcements notices={announcements} />
                <div className="op-page-head">
                    <div>
                        <h1>Dashboard</h1>
                        <p>
                            {viewer
                                ? "A clear view of procurement across your offices."
                                : "Your documents, office workload, and the next step."}
                        </p>
                    </div>
                    <div className="op-page-filters">
                        <DashboardSettings
                            userId={auth.user.id}
                            visibility={visibility}
                            onChange={(next) => {
                                if (
                                    next.officeWorkload &&
                                    !visibility.officeWorkload
                                )
                                    setMetricsTab("workload");
                                else if (
                                    next.performanceMetrics &&
                                    !visibility.performanceMetrics
                                )
                                    setMetricsTab("performance");
                                setVisibility(next);
                            }}
                        />
                    </div>
                </div>
                <div className="op-scope-bar">
                    <Tabs
                        value={scopeValue}
                        onValueChange={(value) => {
                            if (value !== "selected")
                                chooseOffice(
                                    value === "all" ? null : userOfficeId,
                                );
                        }}
                    >
                        <TabsList>
                            <TabsTrigger value="all" disabled={loading}>
                                All offices
                            </TabsTrigger>
                            <TabsTrigger
                                value="mine"
                                disabled={loading || userOfficeId == null}
                            >
                                My office{" "}
                                {auth.user.office && (
                                    <span>{auth.user.office.abbreviation}</span>
                                )}
                            </TabsTrigger>
                            {scopeValue === "selected" && (
                                <TabsTrigger
                                    value="selected"
                                    disabled={loading}
                                >
                                    Selected office <span>{scopeLabel}</span>
                                </TabsTrigger>
                            )}
                        </TabsList>
                    </Tabs>
                    <div>
                        <ShieldCheck />
                        <span>
                            {viewer
                                ? "View-only access"
                                : "Office permissions apply"}
                        </span>
                    </div>
                </div>
                <section
                    className="op-count-register"
                    aria-label={`Document summary · ${scopeLabel}`}
                >
                    {[
                        {
                            label: "Procurements",
                            icon: FolderOpen,
                            counts: summary.procurements,
                        },
                        {
                            label: "Purchase requests",
                            icon: FileText,
                            counts: summary.purchase_requests,
                        },
                        {
                            label: "Purchase orders",
                            icon: ShoppingBag,
                            counts: summary.purchase_orders,
                        },
                        {
                            label: "Vouchers",
                            icon: Receipt,
                            counts: summary.vouchers,
                        },
                    ].map(({ label, icon: Icon, counts }) => (
                        <div key={label}>
                            <div>
                                <span>{label}</span>
                                <Icon />
                            </div>
                            <strong>{counts.total}</strong>
                            <small>{summaryHint(counts)}</small>
                        </div>
                    ))}
                </section>
                {
                    <AttentionPanel
                        entries={stagnantTransactions}
                        meta={attentionMeta}
                        onView={openDocument}
                    />
                }
                {(visibility.officeWorkload ||
                    visibility.performanceMetrics ||
                    visibility.recentActivity) && (
                    <div
                        className={`op-overview-grid ${!visibility.recentActivity ? "op-overview-metrics-only" : ""} ${!(visibility.officeWorkload || visibility.performanceMetrics) ? "op-overview-activity-only" : ""}`}
                    >
                        {(visibility.officeWorkload ||
                            visibility.performanceMetrics) && (
                            <section className="op-metrics-panel">
                                <Tabs
                                    value={activeMetricsTab}
                                    onValueChange={setMetricsTab}
                                >
                                    <div className="op-metrics-head">
                                        <TabsList>
                                            {visibility.officeWorkload && (
                                                <TabsTrigger value="workload">
                                                    Office workload
                                                </TabsTrigger>
                                            )}
                                            {visibility.performanceMetrics && (
                                                <TabsTrigger value="performance">
                                                    Turnaround
                                                </TabsTrigger>
                                            )}
                                        </TabsList>
                                        <Select
                                            disabled={loading}
                                            value={
                                                filters.office_id == null
                                                    ? "all"
                                                    : String(filters.office_id)
                                            }
                                            onValueChange={(value) =>
                                                chooseOffice(
                                                    value === "all"
                                                        ? null
                                                        : Number(value),
                                                )
                                            }
                                        >
                                            <SelectTrigger aria-label="Filter office workload">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="op-live-overlay">
                                                <SelectItem value="all">
                                                    All offices
                                                </SelectItem>
                                                {offices.map((item) => (
                                                    <SelectItem
                                                        key={item.id}
                                                        value={String(item.id)}
                                                    >
                                                        {item.abbreviation}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <TabsContent value="workload">
                                        <p className="op-workload-note">
                                            Active documents awaiting
                                            processing. Completed records remain
                                            in the document register.
                                        </p>
                                        <div className="op-workload">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Office
                                                        </TableHead>
                                                        <TableHead>
                                                            PR
                                                        </TableHead>
                                                        <TableHead>
                                                            PO
                                                        </TableHead>
                                                        <TableHead>
                                                            VCH
                                                        </TableHead>
                                                        <TableHead>
                                                            Needs attention
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {officeWorkload.map(
                                                        (item) => (
                                                            <TableRow
                                                                key={
                                                                    item.office_id
                                                                }
                                                                data-my-office={
                                                                    item.office_id ===
                                                                    userOfficeId
                                                                }
                                                            >
                                                                <TableCell>
                                                                    <strong>
                                                                        {
                                                                            item.office_abbreviation
                                                                        }
                                                                    </strong>
                                                                    <small>
                                                                        {
                                                                            item.office_name
                                                                        }
                                                                    </small>
                                                                </TableCell>
                                                                {(
                                                                    [
                                                                        "pr_count",
                                                                        "po_count",
                                                                        "vch_count",
                                                                    ] as const
                                                                ).map(
                                                                    (
                                                                        key,
                                                                        index,
                                                                    ) => (
                                                                        <TableCell
                                                                            key={
                                                                                key
                                                                            }
                                                                        >
                                                                            {item[
                                                                                key
                                                                            ] >
                                                                            0 ? (
                                                                                <Button
                                                                                    variant="link"
                                                                                    className="op-workload-number"
                                                                                    disabled={
                                                                                        loading
                                                                                    }
                                                                                    aria-label={`View ${item.office_abbreviation} ${["PR", "PO", "VCH"][index]} workload`}
                                                                                    onClick={() =>
                                                                                        openWorkload(
                                                                                            item.office_id,
                                                                                            [
                                                                                                "PR",
                                                                                                "PO",
                                                                                                "VCH",
                                                                                            ][
                                                                                                index
                                                                                            ] as DashboardFilters["category"],
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        item[
                                                                                            key
                                                                                        ]
                                                                                    }
                                                                                </Button>
                                                                            ) : (
                                                                                <span className="op-zero op-workload-number">
                                                                                    0
                                                                                </span>
                                                                            )}
                                                                        </TableCell>
                                                                    ),
                                                                )}
                                                                <TableCell>
                                                                    {item.stagnant_count >
                                                                    0 ? (
                                                                        <span className="op-warning-count">
                                                                            {
                                                                                item.stagnant_count
                                                                            }
                                                                        </span>
                                                                    ) : (
                                                                        <span className="op-zero op-workload-number">
                                                                            0
                                                                        </span>
                                                                    )}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                            {officeWorkload.length === 0 && (
                                                <p className="op-inline-empty">
                                                    No workflow offices or
                                                    active documents in this
                                                    view.
                                                </p>
                                            )}
                                        </div>
                                    </TabsContent>
                                    <TabsContent value="performance">
                                        <p className="op-workload-note">
                                            Average business days from receipt
                                            to endorsement or completion in{" "}
                                            {selectedYear}.
                                        </p>
                                        <div className="op-workload">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Office
                                                        </TableHead>
                                                        <TableHead>
                                                            Average days
                                                        </TableHead>
                                                        <TableHead>
                                                            Target days
                                                        </TableHead>
                                                        <TableHead>
                                                            Actions
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {slaPerformance.office_performance.map(
                                                        (item) => (
                                                            <TableRow
                                                                key={
                                                                    item.office_id
                                                                }
                                                            >
                                                                <TableCell>
                                                                    <strong>
                                                                        {
                                                                            item.office_abbreviation
                                                                        }
                                                                    </strong>
                                                                    <small>
                                                                        {
                                                                            item.office_name
                                                                        }
                                                                    </small>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        item.avg_turnaround_days
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        item.expected_days
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        item.actions_count
                                                                    }
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                            {slaPerformance.office_performance
                                                .length === 0 && (
                                                <p className="op-inline-empty">
                                                    No completed turnaround
                                                    measurements in this view
                                                    yet.
                                                </p>
                                            )}
                                        </div>
                                        <Button
                                            variant="outline"
                                            className="op-back-workload"
                                            disabled={
                                                !visibility.officeWorkload
                                            }
                                            onClick={() =>
                                                setMetricsTab("workload")
                                            }
                                        >
                                            Back to workload
                                        </Button>
                                    </TabsContent>
                                </Tabs>
                            </section>
                        )}
                        {visibility.recentActivity && (
                            <section className="op-activity-panel">
                                <PanelTitle
                                    title="Recent activity"
                                    detail={`${selectedYear} · ${scopeLabel}`}
                                />
                                <div className="op-activity-list">
                                    {activityFeed.slice(0, 4).map((entry) => (
                                        <Link
                                            className="op-activity-item"
                                            key={entry.id}
                                            href={activityUrl(entry)}
                                            onClick={(event) => {
                                                if (
                                                    !event.metaKey &&
                                                    !event.ctrlKey &&
                                                    !event.shiftKey &&
                                                    !event.altKey
                                                ) {
                                                    event.preventDefault();
                                                    openDocument(
                                                        entry.transaction_id,
                                                    );
                                                }
                                            }}
                                        >
                                            <span
                                                className={`op-activity-icon ${entry.action_type === "complete" ? "is-done" : ""}`}
                                            >
                                                {entry.action_type ===
                                                "complete" ? (
                                                    <CheckCheck />
                                                ) : entry.action_type ===
                                                  "receive" ? (
                                                    <Receipt />
                                                ) : (
                                                    <ArrowRight />
                                                )}
                                            </span>
                                            <span>
                                                <strong>
                                                    {entry.action_type ===
                                                    "endorse"
                                                        ? `${entry.from_office ?? "Office"} to ${entry.to_office ?? "office"}`
                                                        : entry.action_type ===
                                                            "complete"
                                                          ? "Document workflow complete"
                                                          : `${entry.to_office ?? "Office"} received document`}
                                                </strong>
                                                <small>
                                                    {
                                                        entry.transaction_reference_number
                                                    }
                                                </small>
                                                <small>
                                                    {entry.actor_name} ·{" "}
                                                    {entry.created_at}
                                                </small>
                                            </span>
                                        </Link>
                                    ))}
                                    {activityFeed.length === 0 && (
                                        <p className="op-inline-empty">
                                            No activity in this view. Updates
                                            appear as documents are processed.
                                        </p>
                                    )}
                                </div>
                                {activityFeed.length > 4 && (
                                    <details className="op-more-activity">
                                        <summary>
                                            View {activityFeed.length - 4} more
                                            recent actions
                                        </summary>
                                        {activityFeed.slice(4).map((entry) => (
                                            <Link
                                                key={entry.id}
                                                href={activityUrl(entry)}
                                                onClick={(event) => {
                                                    if (
                                                        !event.metaKey &&
                                                        !event.ctrlKey &&
                                                        !event.shiftKey &&
                                                        !event.altKey
                                                    ) {
                                                        event.preventDefault();
                                                        openDocument(
                                                            entry.transaction_id,
                                                        );
                                                    }
                                                }}
                                                className="op-activity-item"
                                            >
                                                <span>
                                                    <strong>
                                                        {entry.action_type} ·{" "}
                                                        {
                                                            entry.transaction_reference_number
                                                        }
                                                    </strong>
                                                    <small>
                                                        {entry.actor_name} ·{" "}
                                                        {entry.created_at}
                                                    </small>
                                                </span>
                                            </Link>
                                        ))}
                                    </details>
                                )}
                            </section>
                        )}
                    </div>
                )}
                <section
                    className="op-main-panel op-case-panel"
                    aria-label="Document register"
                >
                    <div className="op-case-list">
                        <PanelTitle
                            title={
                                filters.office_id == null
                                    ? "Document register"
                                    : filters.office_id === userOfficeId
                                      ? "My office documents"
                                      : `${scopeLabel} documents`
                            }
                            detail="Select a document to see its route and current office."
                        >
                            <Badge variant="secondary" className="op-badge">
                                {documents.total}
                            </Badge>
                        </PanelTitle>
                        <DocumentRegister
                            documents={documents}
                            filters={filters}
                            selectedYear={selectedYear}
                            loading={loading}
                            selectedId={selectedId}
                            onFilters={visitFilters}
                            onSelect={selectDocument}
                        />
                    </div>
                    <aside
                        className="op-case-detail"
                        aria-label="Selected document details"
                    >
                        {selected ? (
                            <DocumentDetail
                                document={selected}
                                attention={
                                    selected.attention ??
                                    stagnantTransactions.find(
                                        (entry) => entry.id === selected.id,
                                    )
                                }
                                viewer={viewer}
                                canProcess={auth.user.can_process_transactions}
                            />
                        ) : (
                            <div className="op-empty">
                                <FolderOpen />
                                <h3>Select a document</h3>
                                <p>
                                    Its current office and route will appear
                                    here.
                                </p>
                            </div>
                        )}
                    </aside>
                </section>
                {visibility.transactionVolume && (
                    <div className="op-bottom-reports">
                        <section className="op-volume-panel">
                            <PanelTitle
                                title={
                                    visibility.transactionVolume
                                        ? "Document volume"
                                        : "Workflow incidents"
                                }
                                detail={`${volumeTotal} documents in ${selectedYear} · ${scopeLabel}`}
                            />
                            {visibility.transactionVolume && (
                                <div>
                                    {[
                                        "Purchase requests",
                                        "Purchase orders",
                                        "Vouchers",
                                    ].map((label, index) => (
                                        <div
                                            className="op-volume-row"
                                            key={label}
                                        >
                                            <span>{label}</span>
                                            <div aria-hidden="true">
                                                <i
                                                    style={{
                                                        width: `${volumeTotal ? (categoryCounts[index] / Math.max(...categoryCounts)) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                            <strong>
                                                {categoryCounts[index]}
                                            </strong>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {
                                <div className="op-incident-line">
                                    <ShieldCheck />
                                    <span>
                                        Out-of-workflow incidents ·{" "}
                                        {selectedYear}
                                    </span>
                                    <strong>{annualIncidentCount}</strong>
                                </div>
                            }
                        </section>
                    </div>
                )}
                <footer className="op-page-footer">
                    <span>Local Government of Quezon, Bukidnon</span>
                    <span>Updates every 30 seconds · {selectedYear}</span>
                </footer>
            </main>
            <DashboardDialogs
                modal={modal}
                onClose={closeModal}
                selected={modalSelected}
                viewer={viewer}
                canProcess={auth.user.can_process_transactions}
                attention={stagnantTransactions}
                attentionMeta={attentionMeta}
                pendingDocuments={pendingDocuments}
                pendingCount={pendingReceiptsCount}
                queueDocuments={queueDocuments}
                queueFilters={queueFilters}
                queueLoading={queueLoading}
                queueChangingOffice={
                    queueContext?.office_id !== queueFilters?.office_id
                }
                queueError={queueError}
                onQueueFilters={(changes) => {
                    const current = queueContext ?? queueFilters;
                    if (current) requestQueue({ ...current, ...changes });
                }}
                onQueuePage={(page) => {
                    const current = queueContext ?? queueFilters;
                    if (current) requestQueue(current, page);
                }}
                queueOfficeLabel={
                    offices.find(
                        (office) =>
                            office.id ===
                            (queueContext ?? queueFilters)?.office_id,
                    )?.abbreviation ?? "selected office"
                }
                selectedYear={selectedYear}
                selectedId={selectedId}
                onSelect={(document) => selectDocument(document, true)}
                onView={openDocument}
                returnFocus={returnFocus}
            />
        </DashboardLayout>
    );
}
