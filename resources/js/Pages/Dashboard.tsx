import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { DataTable } from '@/Components/DataTable';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, FileText, ShoppingCart, Receipt, FolderOpen, AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import ActivityFeed from '@/Components/ActivityFeed';
import StagnantPanel from '@/Components/StagnantPanel';
import OfficePerformanceTable from '@/Components/OfficePerformanceTable';
import IncidentSummaryCard from '@/Components/IncidentSummaryCard';
import VolumeSummaryCard from '@/Components/VolumeSummaryCard';
import OfficeWorkloadModal from '@/Components/OfficeWorkloadModal';
import DashboardSettings, { loadDashboardSettings, type DashboardCardVisibility } from '@/Components/DashboardSettings';
import type { DashboardSummary, OfficeWorkload, StatusCounts, ActivityFeedEntry, StagnantTransaction, SlaPerformanceData } from '@/types/models';
import type { PageProps } from '@/types';

interface Props extends PageProps {
    summary: DashboardSummary;
    officeWorkload: OfficeWorkload[];
    activityFeed: ActivityFeedEntry[];
    stagnantTransactions: StagnantTransaction[];
    slaPerformance: SlaPerformanceData;
    userOfficeId: number | null;
}

const statusColors: Record<string, string> = {
    created: 'bg-gray-100 text-gray-700 border-gray-200',
    in_progress: 'bg-blue-100 text-blue-700 border-blue-200',
    completed: 'bg-green-100 text-green-700 border-green-200',
    on_hold: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    cancelled: 'bg-red-100 text-red-700 border-red-200',
};

const statusLabels: Record<string, string> = {
    created: 'Created',
    in_progress: 'In Progress',
    completed: 'Completed',
    on_hold: 'On Hold',
    cancelled: 'Cancelled',
};

function SummaryCard({
    title,
    icon: Icon,
    counts,
}: {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    counts: StatusCounts;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <Icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{counts.total}</div>
                {counts.total > 0 && (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {(Object.keys(statusLabels) as Array<keyof typeof statusLabels>).map(
                            (key) => {
                                const value = counts[key as keyof StatusCounts];
                                if (typeof value !== 'number' || value === 0) return null;
                                return (
                                    <Badge
                                        key={key}
                                        variant="outline"
                                        className={statusColors[key]}
                                    >
                                        {statusLabels[key]} {value}
                                    </Badge>
                                );
                            }
                        )}
                    </div>
                )}
                {counts.total === 0 && (
                    <p className="mt-1 text-xs text-muted-foreground">No records yet</p>
                )}
            </CardContent>
        </Card>
    );
}

function SortableHeader({
    column,
    children,
}: {
    column: { toggleSorting: (desc: boolean) => void; getIsSorted: () => false | 'asc' | 'desc' };
    children: React.ReactNode;
}) {
    return (
        <Button
            variant="ghost"
            size="sm"
            className="-ml-3 h-8"
            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
            {children}
            <ArrowUpDown className="ml-2 h-3 w-3" />
        </Button>
    );
}

export default function Dashboard({ summary, officeWorkload, activityFeed, stagnantTransactions, slaPerformance, userOfficeId }: Props) {
    // Auto-refresh dashboard data every 30 seconds (pauses when tab is hidden)
    usePoll(30_000);

    const { auth } = usePage<PageProps>().props;
    const [visibility, setVisibility] = useState<DashboardCardVisibility>(() =>
        loadDashboardSettings(auth.user.id)
    );

    const [modalOpen, setModalOpen] = useState(false);
    const [modalParams, setModalParams] = useState<{
        officeId: number;
        officeName: string;
        category: string;
        count: number;
    } | null>(null);

    const openWorkloadModal = (officeId: number, officeName: string, category: string, count: number) => {
        setModalParams({ officeId, officeName, category, count });
        setModalOpen(true);
    };

    const columns: ColumnDef<OfficeWorkload>[] = [
        {
            accessorKey: 'office_name',
            header: ({ column }) => (
                <SortableHeader column={column}>Office</SortableHeader>
            ),
            cell: ({ row }) => (
                <div>
                    <span className="font-medium">{row.original.office_name}</span>
                    <span className="ml-1 text-xs text-muted-foreground">
                        ({row.original.office_abbreviation})
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'pr_count',
            header: ({ column }) => (
                <SortableHeader column={column}>PR</SortableHeader>
            ),
            cell: ({ row }) => {
                const count = row.original.pr_count;
                if (count === 0) return <span className="text-muted-foreground">0</span>;
                return (
                    <button
                        className="text-blue-600 hover:underline font-medium"
                        onClick={() =>
                            openWorkloadModal(
                                row.original.office_id,
                                `${row.original.office_name} (${row.original.office_abbreviation})`,
                                'PR',
                                count,
                            )
                        }
                    >
                        {count}
                    </button>
                );
            },
        },
        {
            accessorKey: 'po_count',
            header: ({ column }) => (
                <SortableHeader column={column}>PO</SortableHeader>
            ),
            cell: ({ row }) => {
                const count = row.original.po_count;
                if (count === 0) return <span className="text-muted-foreground">0</span>;
                return (
                    <button
                        className="text-blue-600 hover:underline font-medium"
                        onClick={() =>
                            openWorkloadModal(
                                row.original.office_id,
                                `${row.original.office_name} (${row.original.office_abbreviation})`,
                                'PO',
                                count,
                            )
                        }
                    >
                        {count}
                    </button>
                );
            },
        },
        {
            accessorKey: 'vch_count',
            header: ({ column }) => (
                <SortableHeader column={column}>VCH</SortableHeader>
            ),
            cell: ({ row }) => {
                const count = row.original.vch_count;
                if (count === 0) return <span className="text-muted-foreground">0</span>;
                return (
                    <button
                        className="text-blue-600 hover:underline font-medium"
                        onClick={() =>
                            openWorkloadModal(
                                row.original.office_id,
                                `${row.original.office_name} (${row.original.office_abbreviation})`,
                                'VCH',
                                count,
                            )
                        }
                    >
                        {count}
                    </button>
                );
            },
        },
        {
            accessorKey: 'total',
            header: ({ column }) => (
                <SortableHeader column={column}>Total</SortableHeader>
            ),
            cell: ({ row }) => (
                <span className="font-semibold">{row.original.total}</span>
            ),
        },
        {
            accessorKey: 'stagnant_count',
            header: ({ column }) => (
                <SortableHeader column={column}>Stagnant</SortableHeader>
            ),
            cell: ({ row }) => {
                const count = row.original.stagnant_count;
                if (count === 0) {
                    return <span className="text-muted-foreground">0</span>;
                }
                return (
                    <Badge variant="outline" className="bg-red-100 text-red-700 border-red-200">
                        <AlertTriangle className="mr-1 h-3 w-3" />
                        {count}
                    </Badge>
                );
            },
        },
    ];

    const hasData =
        summary.procurements.total > 0 ||
        summary.purchase_requests.total > 0 ||
        summary.purchase_orders.total > 0 ||
        summary.vouchers.total > 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Dashboard
                    </h2>
                    <DashboardSettings
                        userId={auth.user.id}
                        visibility={visibility}
                        onChange={setVisibility}
                    />
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Summary Cards */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryCard
                            title="Procurements"
                            icon={FolderOpen}
                            counts={summary.procurements}
                        />
                        <SummaryCard
                            title="Purchase Requests"
                            icon={FileText}
                            counts={summary.purchase_requests}
                        />
                        <SummaryCard
                            title="Purchase Orders"
                            icon={ShoppingCart}
                            counts={summary.purchase_orders}
                        />
                        <SummaryCard
                            title="Vouchers"
                            icon={Receipt}
                            counts={summary.vouchers}
                        />
                    </div>

                    {/* Office Workload Table */}
                    {visibility.officeWorkload && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Office Workload</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {officeWorkload.length === 0 ? (
                                    <div className="py-8 text-center text-muted-foreground">
                                        <FolderOpen className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                        <p>No active workflows configured</p>
                                    </div>
                                ) : (
                                    <DataTable
                                        columns={columns}
                                        data={officeWorkload}
                                        getRowClassName={(row) =>
                                            row.office_id === userOfficeId
                                                ? 'bg-blue-50/50'
                                                : undefined
                                        }
                                    />
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Recent Activity */}
                    {visibility.recentActivity && (
                        <ActivityFeed entries={activityFeed} />
                    )}

                    {/* Needs Attention */}
                    {visibility.needsAttention && (
                        <StagnantPanel
                            entries={stagnantTransactions}
                            userOfficeId={userOfficeId}
                        />
                    )}

                    {/* Performance Metrics */}
                    {visibility.performanceMetrics && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Office Turnaround (Last 30 Days)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {slaPerformance.office_performance.length === 0 ? (
                                    <p className="py-4 text-center text-muted-foreground">
                                        No completed actions in the last 30 days
                                    </p>
                                ) : (
                                    <OfficePerformanceTable
                                        data={slaPerformance.office_performance}
                                        userOfficeId={userOfficeId}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Incidents + Volume */}
                    {(visibility.outOfWorkflow || visibility.transactionVolume) && (
                        <div className="grid gap-4 md:grid-cols-2">
                            {visibility.outOfWorkflow && (
                                <IncidentSummaryCard data={slaPerformance.incidents} />
                            )}
                            {visibility.transactionVolume && (
                                <VolumeSummaryCard data={slaPerformance.volume} />
                            )}
                        </div>
                    )}
                </div>
            </div>

            <OfficeWorkloadModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                officeId={modalParams?.officeId ?? null}
                officeName={modalParams?.officeName ?? ''}
                category={modalParams?.category ?? ''}
                count={modalParams?.count ?? 0}
            />
        </AuthenticatedLayout>
    );
}
