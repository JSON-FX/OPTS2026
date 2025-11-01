import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useDebouncedCallback } from 'use-debounce';
import {
    ColumnDef,
    flexRender,
    getCoreRowModel,
    useReactTable,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import { ArrowUpDown, Download, MoreHorizontal, Settings2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { Badge } from '@/Components/ui/badge';
import StatusBadge from '@/Components/StatusBadge';
import RelativeTime from '@/Components/RelativeTime';
import { TransactionListItem, TransactionSearchFilters, PaginatedData } from '@/types/models';

interface TransactionsIndexProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
    transactions: PaginatedData<TransactionListItem>;
    filters: TransactionSearchFilters;
    can: {
        manage: boolean;
    };
    offices: { id: number; name: string }[];
}

export default function Index({ auth, transactions, filters, can, offices = [] }: TransactionsIndexProps) {
    const COLUMN_VISIBILITY_KEY = `transactions_column_visibility_${auth.user.id}`;

    const [sorting, setSorting] = useState<SortingState>([
        { id: filters.sort_by || 'created_at', desc: filters.sort_direction === 'desc' },
    ]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(() => {
        // Load saved column visibility from localStorage
        const saved = localStorage.getItem(COLUMN_VISIBILITY_KEY);
        return saved ? JSON.parse(saved) : {};
    });
    const [showColumnDropdown, setShowColumnDropdown] = useState(false);

    // Save column visibility to localStorage whenever it changes
    useEffect(() => {
        localStorage.setItem(COLUMN_VISIBILITY_KEY, JSON.stringify(columnVisibility));
    }, [columnVisibility, COLUMN_VISIBILITY_KEY]);

    const getDetailRoute = (transaction: TransactionListItem): string => {
        switch (transaction.category) {
            case 'PR':
                return route('purchase-requests.show', transaction.id);
            case 'PO':
                return route('purchase-orders.show', transaction.id);
            case 'VCH':
                return route('vouchers.show', transaction.id);
            default:
                return '#';
        }
    };

    const getEditRoute = (transaction: TransactionListItem): string => {
        switch (transaction.category) {
            case 'PR':
                return route('purchase-requests.edit', transaction.id);
            case 'PO':
                return route('purchase-orders.edit', transaction.id);
            case 'VCH':
                return route('vouchers.edit', transaction.id);
            default:
                return '#';
        }
    };

    const handleDelete = (transaction: TransactionListItem) => {
        if (
            confirm(
                `Are you sure you want to delete transaction ${transaction.reference_number}?`
            )
        ) {
            let deleteRoute = '';
            switch (transaction.category) {
                case 'PR':
                    deleteRoute = route('purchase-requests.destroy', transaction.id);
                    break;
                case 'PO':
                    deleteRoute = route('purchase-orders.destroy', transaction.id);
                    break;
                case 'VCH':
                    deleteRoute = route('vouchers.destroy', transaction.id);
                    break;
            }

            if (deleteRoute) {
                router.delete(deleteRoute);
            }
        }
    };

    const columns: ColumnDef<TransactionListItem>[] = [
        {
            accessorKey: 'reference_number',
            enableHiding: false,
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            const isDesc = column.getIsSorted() === 'desc';
                            router.get(
                                route('transactions.index'),
                                {
                                    ...filters,
                                    sort_by: 'reference_number',
                                    sort_direction: isDesc ? 'asc' : 'desc',
                                },
                                { preserveState: true, preserveScroll: true }
                            );
                        }}
                    >
                        Reference Number
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => row.getValue('reference_number'),
        },
        {
            accessorKey: 'category',
            header: 'Category',
            cell: ({ row }) => {
                const category = row.getValue('category') as string;
                const categoryColors: Record<string, string> = {
                    PR: 'bg-blue-100 text-blue-800',
                    PO: 'bg-green-100 text-green-800',
                    VCH: 'bg-purple-100 text-purple-800',
                };

                return (
                    <Badge variant="outline" className={categoryColors[category]}>
                        {category}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'procurement_id',
            header: 'Procurement ID',
            cell: ({ row }) => (
                <Link
                    href={route('procurements.show', row.getValue('procurement_id'))}
                    className="text-blue-600 hover:underline"
                >
                    #{row.getValue('procurement_id')}
                </Link>
            ),
        },
        {
            accessorKey: 'procurement_end_user_name',
            header: 'End User Office',
            cell: ({ row }) => row.getValue('procurement_end_user_name'),
        },
        {
            accessorKey: 'procurement_purpose',
            header: 'Purpose',
            cell: ({ row }) => {
                const purpose = row.getValue('procurement_purpose') as string;
                const truncated = purpose?.length > 100 ? purpose.substring(0, 100) + '...' : purpose;
                return <span className="text-sm">{truncated || 'N/A'}</span>;
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => <StatusBadge status={row.getValue('status')} />,
        },
        {
            accessorKey: 'created_by_name',
            header: 'Created By',
            cell: ({ row }) => row.getValue('created_by_name'),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            const isDesc = column.getIsSorted() === 'desc';
                            router.get(
                                route('transactions.index'),
                                {
                                    ...filters,
                                    sort_by: 'created_at',
                                    sort_direction: isDesc ? 'asc' : 'desc',
                                },
                                { preserveState: true, preserveScroll: true }
                            );
                        }}
                    >
                        Created Date
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => <RelativeTime timestamp={row.getValue('created_at')} />,
        },
        {
            id: 'actions',
            enableHiding: false,
            header: 'Actions',
            cell: ({ row }) => {
                const transaction = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0">
                                <span className="sr-only">Open menu</span>
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => router.visit(getDetailRoute(transaction))}
                            >
                                View Details
                            </DropdownMenuItem>
                            {can.manage && (
                                <>
                                    <DropdownMenuItem
                                        onClick={() => router.visit(getEditRoute(transaction))}
                                    >
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleDelete(transaction)}>
                                        Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    const table = useReactTable({
        data: transactions.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
        onColumnVisibilityChange: setColumnVisibility,
        state: {
            sorting,
            columnVisibility,
        },
        onSortingChange: setSorting,
    });

    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            route('transactions.index'),
            {
                ...filters,
                reference_number: value,
            },
            { preserveState: true, preserveScroll: true }
        );
    }, 500);

    const handleFilterChange = (key: keyof TransactionSearchFilters, value: string | boolean) => {
        router.get(
            route('transactions.index'),
            {
                ...filters,
                [key]: value,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleQuickFilter = (filterType: string) => {
        const now = new Date();
        let filterParams: Partial<TransactionSearchFilters> = {};

        switch (filterType) {
            case 'my-transactions':
                filterParams = { created_by_me: true };
                break;
            case 'pending':
                filterParams = { status: 'In Progress' };
                break;
            case 'this-week':
                const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                filterParams = {
                    date_from: weekAgo.toISOString().split('T')[0],
                    date_to: now.toISOString().split('T')[0],
                };
                break;
            case 'this-month':
                const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
                filterParams = {
                    date_from: monthStart.toISOString().split('T')[0],
                    date_to: now.toISOString().split('T')[0],
                };
                break;
        }

        router.get(route('transactions.index'), filterParams, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        router.get(route('transactions.index'));
    };

    const hasActiveFilters =
        filters.reference_number ||
        filters.category ||
        filters.status ||
        filters.date_from ||
        filters.date_to ||
        filters.end_user_id ||
        filters.created_by_me;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Transactions
                </h2>
            }
        >
            <Head title="Transactions" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Search and Filters */}
                            <div className="mb-6 space-y-4">
                                {/* Desktop Filters */}
                                <div className="hidden lg:grid lg:grid-cols-3 lg:gap-4">
                                    <Input
                                        type="text"
                                        placeholder="Search by reference number..."
                                        defaultValue={filters.reference_number || ''}
                                        onChange={(e) => debouncedSearch(e.target.value)}
                                    />

                                    <Select
                                        value={filters.category || 'all'}
                                        onValueChange={(value) =>
                                            handleFilterChange(
                                                'category',
                                                value === 'all' ? '' : value
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Categories</SelectItem>
                                            <SelectItem value="PR">PR</SelectItem>
                                            <SelectItem value="PO">PO</SelectItem>
                                            <SelectItem value="VCH">VCH</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Select
                                        value={filters.status || 'all'}
                                        onValueChange={(value) =>
                                            handleFilterChange('status', value === 'all' ? '' : value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Statuses</SelectItem>
                                            <SelectItem value="Created">Created</SelectItem>
                                            <SelectItem value="In Progress">In Progress</SelectItem>
                                            <SelectItem value="Completed">Completed</SelectItem>
                                            <SelectItem value="On Hold">On Hold</SelectItem>
                                            <SelectItem value="Cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Input
                                        type="date"
                                        placeholder="Date From"
                                        value={filters.date_from || ''}
                                        onChange={(e) =>
                                            handleFilterChange('date_from', e.target.value)
                                        }
                                    />

                                    <Input
                                        type="date"
                                        placeholder="Date To"
                                        value={filters.date_to || ''}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                    />

                                    {offices.length > 0 && (
                                        <Select
                                            value={filters.end_user_id?.toString() || 'all'}
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'end_user_id',
                                                    value === 'all' ? '' : value
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="All Offices" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Offices</SelectItem>
                                                {offices.map((office) => (
                                                    <SelectItem
                                                        key={office.id}
                                                        value={office.id.toString()}
                                                    >
                                                        {office.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </div>

                                {/* Mobile Filters - Accordion */}
                                <Accordion type="single" collapsible className="lg:hidden">
                                    <AccordionItem value="filters">
                                        <AccordionTrigger>Filters</AccordionTrigger>
                                        <AccordionContent>
                                            <div className="grid gap-4">
                                                <Input
                                                    type="text"
                                                    placeholder="Search by reference number..."
                                                    defaultValue={filters.reference_number || ''}
                                                    onChange={(e) => debouncedSearch(e.target.value)}
                                                />

                                                <Select
                                                    value={filters.category || 'all'}
                                                    onValueChange={(value) =>
                                                        handleFilterChange(
                                                            'category',
                                                            value === 'all' ? '' : value
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="All Categories" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All Categories</SelectItem>
                                                        <SelectItem value="PR">PR</SelectItem>
                                                        <SelectItem value="PO">PO</SelectItem>
                                                        <SelectItem value="VCH">VCH</SelectItem>
                                                    </SelectContent>
                                                </Select>

                                                <Select
                                                    value={filters.status || 'all'}
                                                    onValueChange={(value) =>
                                                        handleFilterChange(
                                                            'status',
                                                            value === 'all' ? '' : value
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="All Statuses" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All Statuses</SelectItem>
                                                        <SelectItem value="Created">Created</SelectItem>
                                                        <SelectItem value="In Progress">
                                                            In Progress
                                                        </SelectItem>
                                                        <SelectItem value="Completed">Completed</SelectItem>
                                                        <SelectItem value="On Hold">On Hold</SelectItem>
                                                        <SelectItem value="Cancelled">Cancelled</SelectItem>
                                                    </SelectContent>
                                                </Select>

                                                <Input
                                                    type="date"
                                                    placeholder="Date From"
                                                    value={filters.date_from || ''}
                                                    onChange={(e) =>
                                                        handleFilterChange('date_from', e.target.value)
                                                    }
                                                />

                                                <Input
                                                    type="date"
                                                    placeholder="Date To"
                                                    value={filters.date_to || ''}
                                                    onChange={(e) =>
                                                        handleFilterChange('date_to', e.target.value)
                                                    }
                                                />
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                </Accordion>

                                {/* Quick Filters */}
                                <div className="flex flex-wrap gap-2">
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer hover:bg-blue-100"
                                        onClick={() => handleQuickFilter('my-transactions')}
                                    >
                                        My Transactions
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer hover:bg-blue-100"
                                        onClick={() => handleQuickFilter('pending')}
                                    >
                                        Pending
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer hover:bg-blue-100"
                                        onClick={() => handleQuickFilter('this-week')}
                                    >
                                        This Week
                                    </Badge>
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer hover:bg-blue-100"
                                        onClick={() => handleQuickFilter('this-month')}
                                    >
                                        This Month
                                    </Badge>
                                    {hasActiveFilters && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                        >
                                            Clear Filters
                                        </Button>
                                    )}
                                </div>

                                {/* Export CSV & Column Visibility */}
                                <div className="flex justify-end gap-2">
                                    <div className="relative">
                                        <button
                                            onClick={() => setShowColumnDropdown(!showColumnDropdown)}
                                            className="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        >
                                            <Settings2 className="h-4 w-4" />
                                            Columns
                                        </button>

                                        {showColumnDropdown && (
                                            <>
                                                <div
                                                    className="fixed inset-0 z-10"
                                                    onClick={() => setShowColumnDropdown(false)}
                                                />
                                                <div className="absolute right-0 mt-2 w-56 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 z-20">
                                                    <div className="p-3 space-y-2 max-h-96 overflow-y-auto">
                                                        <div className="text-xs font-semibold text-gray-500 uppercase mb-2">
                                                            Toggle Columns
                                                        </div>
                                                        {table
                                                            .getAllColumns()
                                                            .filter((column) => column.getCanHide())
                                                            .map((column) => {
                                                                const columnLabel = column.id
                                                                    .split('_')
                                                                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                                                                    .join(' ');

                                                                return (
                                                                    <label
                                                                        key={column.id}
                                                                        className="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer"
                                                                    >
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={column.getIsVisible()}
                                                                            onChange={(e) => column.toggleVisibility(e.target.checked)}
                                                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                        />
                                                                        <span className="text-sm text-gray-700">
                                                                            {columnLabel}
                                                                        </span>
                                                                    </label>
                                                                );
                                                            })}

                                                        <div className="pt-2 mt-2 border-t border-gray-200">
                                                            <button
                                                                onClick={() => {
                                                                    setColumnVisibility({});
                                                                    localStorage.removeItem(COLUMN_VISIBILITY_KEY);
                                                                }}
                                                                className="w-full text-left px-2 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 rounded"
                                                            >
                                                                Reset to Defaults
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="outline" disabled>
                                                    <Download className="mr-2 h-4 w-4" />
                                                    Export CSV
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>CSV export available in Epic 5</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>

                            {/* Table */}
                            {transactions.data.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            {table.getHeaderGroups().map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map((header) => (
                                                        <TableHead key={header.id}>
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(
                                                                      header.column.columnDef.header,
                                                                      header.getContext()
                                                                  )}
                                                        </TableHead>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableHeader>
                                        <TableBody>
                                            {table.getRowModel().rows.map((row) => (
                                                <TableRow key={row.id}>
                                                    {row.getVisibleCells().map((cell) => (
                                                        <TableCell key={cell.id}>
                                                            {flexRender(
                                                                cell.column.columnDef.cell,
                                                                cell.getContext()
                                                            )}
                                                        </TableCell>
                                                    ))}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <div className="py-12 text-center">
                                    <p className="text-gray-500">
                                        No transactions found matching your filters
                                    </p>
                                    {hasActiveFilters && (
                                        <Button
                                            variant="outline"
                                            className="mt-4"
                                            onClick={clearFilters}
                                        >
                                            Clear Filters
                                        </Button>
                                    )}
                                </div>
                            )}

                            {/* Pagination */}
                            {transactions.data.length > 0 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Showing {transactions.from} to {transactions.to} of{' '}
                                        {transactions.total} results
                                    </div>
                                    <div className="flex gap-2">
                                        {transactions.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.visit(link.url)}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
