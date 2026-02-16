import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { DataTable } from '@/Components/DataTable';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowRight, Inbox, CheckCircle2, AlertTriangle, Activity, Search } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import type { ActivityFeedEntry } from '@/types/models';

const actionIcons: Record<string, React.ComponentType<{ className?: string }>> = {
    endorse: ArrowRight,
    receive: Inbox,
    complete: CheckCircle2,
};

const actionLabels: Record<string, string> = {
    endorse: 'Endorsed',
    receive: 'Received',
    complete: 'Completed',
};

const actionColors: Record<string, string> = {
    endorse: 'bg-blue-100 text-blue-700 border-blue-200',
    receive: 'bg-green-100 text-green-700 border-green-200',
    complete: 'bg-purple-100 text-purple-700 border-purple-200',
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

function getTransactionUrl(entry: ActivityFeedEntry): string {
    switch (entry.transaction_category) {
        case 'PR':
            return entry.purchase_request_id
                ? route('purchase-requests.show', entry.purchase_request_id)
                : '#';
        case 'PO':
            return entry.purchase_order_id
                ? route('purchase-orders.show', entry.purchase_order_id)
                : '#';
        case 'VCH':
            return entry.voucher_id
                ? route('vouchers.show', entry.voucher_id)
                : '#';
        default:
            return '#';
    }
}

const columns: ColumnDef<ActivityFeedEntry>[] = [
    {
        accessorKey: 'action_type',
        header: 'Action',
        cell: ({ row }) => {
            const type = row.original.action_type;
            const Icon = actionIcons[type] ?? ArrowRight;
            return (
                <div className="flex items-center gap-2">
                    <Icon className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <Badge variant="outline" className={actionColors[type]}>
                        {actionLabels[type] ?? type}
                    </Badge>
                    {row.original.is_out_of_workflow && (
                        <AlertTriangle className="h-3.5 w-3.5 text-amber-500 flex-shrink-0" />
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'transaction_reference_number',
        header: 'Reference',
        cell: ({ row }) => (
            <Link
                href={getTransactionUrl(row.original)}
                className="font-medium text-blue-600 hover:underline whitespace-nowrap"
            >
                {row.original.transaction_reference_number}
            </Link>
        ),
    },
    {
        accessorKey: 'actor_name',
        header: 'Actor',
        cell: ({ row }) => (
            <span className="whitespace-nowrap">{row.original.actor_name}</span>
        ),
    },
    {
        id: 'route',
        header: 'Route',
        cell: ({ row }) => {
            const { from_office, to_office } = row.original;
            if (!from_office && !to_office) return <span className="text-muted-foreground">—</span>;
            return (
                <span className="text-sm whitespace-nowrap">
                    {from_office ?? '—'} → {to_office ?? '—'}
                </span>
            );
        },
    },
    {
        accessorKey: 'created_at',
        header: 'When',
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground whitespace-nowrap">
                {row.original.created_at}
            </span>
        ),
    },
];

interface ActivityFeedProps {
    entries: ActivityFeedEntry[];
}

export default function ActivityFeed({ entries }: ActivityFeedProps) {
    const [globalFilter, setGlobalFilter] = useState('');
    const [pageSize, setPageSize] = useState(10);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Activity className="h-5 w-5" />
                    Recent Activity
                </CardTitle>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <div className="py-8 text-center text-muted-foreground">
                        <Activity className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        <p>No recent activity</p>
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={entries}
                        pageSize={pageSize}
                        globalFilter={globalFilter}
                        onGlobalFilterChange={setGlobalFilter}
                    >
                        <div className="relative flex-1">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search activity..."
                                value={globalFilter}
                                onChange={(e) => setGlobalFilter(e.target.value)}
                                className="pl-8 h-9"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-muted-foreground whitespace-nowrap">Show</span>
                            <Select
                                value={String(pageSize)}
                                onValueChange={(value) => setPageSize(Number(value))}
                            >
                                <SelectTrigger className="h-9 w-[70px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {PAGE_SIZE_OPTIONS.map((size) => (
                                        <SelectItem key={size} value={String(size)}>
                                            {size}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </DataTable>
                )}
            </CardContent>
        </Card>
    );
}
