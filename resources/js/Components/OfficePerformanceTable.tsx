import { DataTable } from '@/Components/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown } from 'lucide-react';
import type { OfficePerformance } from '@/types/models';

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

const ratingStyles: Record<string, string> = {
    good: 'bg-green-100 text-green-700 border-green-200',
    warning: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    poor: 'bg-red-100 text-red-700 border-red-200',
};

const ratingLabels: Record<string, string> = {
    good: 'Good',
    warning: 'Warning',
    poor: 'Poor',
};

interface Props {
    data: OfficePerformance[];
    userOfficeId: number | null;
}

export default function OfficePerformanceTable({ data, userOfficeId }: Props) {
    const columns: ColumnDef<OfficePerformance>[] = [
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
            accessorKey: 'avg_turnaround_days',
            header: ({ column }) => (
                <SortableHeader column={column}>Avg Days</SortableHeader>
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.avg_turnaround_days}</span>
            ),
        },
        {
            accessorKey: 'expected_days',
            header: ({ column }) => (
                <SortableHeader column={column}>Expected Days</SortableHeader>
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">{row.original.expected_days}</span>
            ),
        },
        {
            accessorKey: 'performance_rating',
            header: ({ column }) => (
                <SortableHeader column={column}>Performance</SortableHeader>
            ),
            cell: ({ row }) => {
                const rating = row.original.performance_rating;
                return (
                    <Badge variant="outline" className={ratingStyles[rating]}>
                        {ratingLabels[rating]}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'actions_count',
            header: ({ column }) => (
                <SortableHeader column={column}>Actions</SortableHeader>
            ),
            cell: ({ row }) => (
                <span>{row.original.actions_count}</span>
            ),
        },
    ];

    return (
        <DataTable
            columns={columns}
            data={data}
            getRowClassName={(row) =>
                row.office_id === userOfficeId ? 'bg-blue-50/50' : undefined
            }
        />
    );
}
