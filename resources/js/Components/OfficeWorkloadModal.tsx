import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { DataTable } from '@/Components/DataTable';
import { ColumnDef, Column } from '@tanstack/react-table';
import StatusBadge from '@/Components/StatusBadge';
import RelativeTime from '@/Components/RelativeTime';
import { ArrowUpDown, Download, Eye, Loader2 } from 'lucide-react';

interface WorkloadTransaction {
    entity_id: number;
    reference_number: string;
    category: string;
    status: string;
    procurement_id: number;
    procurement_purpose: string;
    end_user_office: string;
    created_by_name: string;
    created_at: string;
}

interface OfficeWorkloadModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    officeId: number | null;
    officeName: string;
    category: string;
    count: number;
}

const categoryLabels: Record<string, string> = {
    PR: 'Purchase Requests',
    PO: 'Purchase Orders',
    VCH: 'Vouchers',
};

function SortableHeader({
    column,
    children,
}: {
    column: Column<WorkloadTransaction>;
    children: React.ReactNode;
}) {
    return (
        <button
            className="flex items-center gap-1 hover:text-foreground"
            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
            {children}
            <ArrowUpDown className="ml-1 h-3 w-3" />
        </button>
    );
}

const buildColumns = (
    category: string,
): ColumnDef<WorkloadTransaction>[] => [
    {
        accessorKey: 'reference_number',
        header: ({ column }) => (
            <SortableHeader column={column}>Reference No.</SortableHeader>
        ),
        cell: ({ row }) => (
            <span className="font-medium text-sm">{row.original.reference_number}</span>
        ),
    },
    {
        accessorKey: 'status',
        header: ({ column }) => (
            <SortableHeader column={column}>Status</SortableHeader>
        ),
        cell: ({ row }) => <StatusBadge status={row.original.status} />,
    },
    {
        accessorKey: 'end_user_office',
        header: ({ column }) => (
            <SortableHeader column={column}>End User</SortableHeader>
        ),
        cell: ({ row }) => (
            <span className="text-sm">{row.original.end_user_office}</span>
        ),
    },
    {
        accessorKey: 'procurement_purpose',
        header: ({ column }) => (
            <SortableHeader column={column}>Purpose</SortableHeader>
        ),
        cell: ({ row }) => {
            const purpose = row.original.procurement_purpose;
            const truncated =
                purpose?.length > 60 ? purpose.substring(0, 60) + '...' : purpose;
            return <span className="text-sm">{truncated || 'N/A'}</span>;
        },
    },
    {
        accessorKey: 'created_by_name',
        header: ({ column }) => (
            <SortableHeader column={column}>Created By</SortableHeader>
        ),
        cell: ({ row }) => (
            <span className="text-sm">{row.original.created_by_name}</span>
        ),
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) => (
            <SortableHeader column={column}>Created</SortableHeader>
        ),
        cell: ({ row }) => <RelativeTime timestamp={row.original.created_at} />,
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => {
            const entityRoute =
                category === 'PR'
                    ? 'purchase-requests.show'
                    : category === 'PO'
                      ? 'purchase-orders.show'
                      : 'vouchers.show';
            return (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => router.visit(route(entityRoute, row.original.entity_id))}
                >
                    <Eye className="h-4 w-4 mr-1" />
                    View
                </Button>
            );
        },
    },
];

export default function OfficeWorkloadModal({
    open,
    onOpenChange,
    officeId,
    officeName,
    category,
    count,
}: OfficeWorkloadModalProps) {
    const [transactions, setTransactions] = useState<WorkloadTransaction[]>([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    const columns = useMemo(() => buildColumns(category), [category]);

    useEffect(() => {
        if (open && officeId) {
            setLoading(true);
            setSearch('');
            setStatusFilter('all');

            fetch(
                route('dashboard.workload-detail') +
                    '?' +
                    new URLSearchParams({
                        office_id: String(officeId),
                        category,
                    })
            )
                .then((res) => res.json())
                .then((json) => {
                    setTransactions(json.data);
                })
                .finally(() => setLoading(false));
        } else {
            setTransactions([]);
        }
    }, [open, officeId, category]);

    const filtered = useMemo(() => {
        let result = transactions;

        if (statusFilter !== 'all') {
            result = result.filter((t) => t.status === statusFilter);
        }

        if (search.trim()) {
            const q = search.toLowerCase();
            result = result.filter(
                (t) =>
                    t.reference_number.toLowerCase().includes(q) ||
                    t.procurement_purpose?.toLowerCase().includes(q) ||
                    t.end_user_office?.toLowerCase().includes(q) ||
                    t.created_by_name?.toLowerCase().includes(q)
            );
        }

        return result;
    }, [transactions, search, statusFilter]);

    const statuses = useMemo(() => {
        const unique = new Set(transactions.map((t) => t.status));
        return Array.from(unique).sort();
    }, [transactions]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-5xl max-h-[85vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>
                        {officeName} &mdash; {categoryLabels[category] || category}
                    </DialogTitle>
                    <DialogDescription>
                        {count} active transaction{count !== 1 ? 's' : ''} currently at
                        this office
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        <span className="ml-2 text-muted-foreground">Loading...</span>
                    </div>
                ) : (
                    <div className="flex-1 overflow-y-auto min-h-0">
                        <DataTable
                            columns={columns}
                            data={filtered}
                            pageSize={20}
                            globalFilter={search}
                            onGlobalFilterChange={setSearch}
                        >
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center w-full">
                                <Input
                                    placeholder="Search reference, purpose, office..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="max-w-sm"
                                />
                                <Select
                                    value={statusFilter}
                                    onValueChange={setStatusFilter}
                                >
                                    <SelectTrigger className="w-[160px]">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {s}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <div className="flex items-center gap-2 ml-auto">
                                    <Button variant="outline" size="sm" disabled>
                                        <Download className="h-4 w-4 mr-1" />
                                        Export CSV
                                    </Button>
                                    <span className="text-sm text-muted-foreground">
                                        {filtered.length} of {transactions.length} shown
                                    </span>
                                </div>
                            </div>
                        </DataTable>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
