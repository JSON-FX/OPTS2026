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
import { Badge } from '@/Components/ui/badge';
import DelaySeverityBadge from '@/Components/DelaySeverityBadge';
import { AlertTriangle, CheckCircle2, Search } from 'lucide-react';
import type { StagnantTransaction, TransactionCategory } from '@/types/models';
import { cn } from '@/lib/utils';

const categoryColors: Record<TransactionCategory, string> = {
    PR: 'bg-blue-100 text-blue-700 border-blue-200',
    PO: 'bg-purple-100 text-purple-700 border-purple-200',
    VCH: 'bg-orange-100 text-orange-700 border-orange-200',
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

function getTransactionUrl(entry: StagnantTransaction): string {
    switch (entry.category) {
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

const columns: ColumnDef<StagnantTransaction>[] = [
    {
        accessorKey: 'category',
        header: 'Type',
        cell: ({ row }) => (
            <Badge
                variant="outline"
                className={cn('text-xs', categoryColors[row.original.category])}
            >
                {row.original.category}
            </Badge>
        ),
    },
    {
        accessorKey: 'reference_number',
        header: 'Reference',
        cell: ({ row }) => (
            <Link
                href={getTransactionUrl(row.original)}
                className="font-medium text-blue-600 hover:underline whitespace-nowrap"
            >
                {row.original.reference_number}
            </Link>
        ),
    },
    {
        accessorKey: 'current_office_name',
        header: 'Office',
        cell: ({ row }) => (
            <span className="whitespace-nowrap">{row.original.current_office_name}</span>
        ),
    },
    {
        accessorKey: 'delay_severity',
        header: 'Status',
        cell: ({ row }) => (
            <DelaySeverityBadge
                severity={row.original.delay_severity}
                delayDays={row.original.delay_days}
                className="text-xs"
            />
        ),
    },
    {
        accessorKey: 'days_at_current_step',
        header: 'Days at Step',
        cell: ({ row }) => (
            <span className="text-sm text-muted-foreground whitespace-nowrap">
                {row.original.days_at_current_step}d
            </span>
        ),
    },
];

interface StagnantPanelProps {
    entries: StagnantTransaction[];
    userOfficeId: number | null;
}

export default function StagnantPanel({ entries, userOfficeId }: StagnantPanelProps) {
    const [globalFilter, setGlobalFilter] = useState('');
    const [pageSize, setPageSize] = useState(10);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <AlertTriangle className="h-5 w-5 text-amber-500" />
                    Needs Attention
                </CardTitle>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <div className="py-8 text-center text-muted-foreground">
                        <CheckCircle2 className="mx-auto mb-3 h-10 w-10 text-green-400" />
                        <p>All transactions are on track</p>
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
                                placeholder="Search stagnant..."
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
