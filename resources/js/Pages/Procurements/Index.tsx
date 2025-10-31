/**
 * EXPERIMENTAL: Shadcn Data-Table Implementation
 *
 * To use this version:
 * 1. Install dependency: npm install @tanstack/react-table
 * 2. Rename current Index.tsx to Index-Original.tsx (if not already backed up)
 * 3. Rename this file to Index.tsx
 *
 * To revert:
 * - Rename Index-Original-Backup.tsx to Index.tsx
 *
 * Note: This is a temporary experiment. If you like it, we'll keep it.
 */

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';
import { PageProps } from '@/types';
import {
    Office,
    PaginatedData,
    Particular,
    Procurement,
    ProcurementStatus,
} from '@/types/models';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowUpDown, ChevronLeft, ChevronRight, Settings2 } from 'lucide-react';

type ProcurementListItem = Omit<
    Procurement,
    'end_user' | 'particular' | 'creator'
> & {
    end_user: Pick<Office, 'id' | 'name' | 'abbreviation'> | null;
    particular: Pick<Particular, 'id' | 'description'> | null;
    creator: { id: number; name: string } | null;
    transactions_count: number;
};

interface Filters {
    search: string;
    status: ProcurementStatus | '';
    end_user_id: number | null;
    particular_id: number | null;
    date_from: string;
    date_to: string;
    my_procurements: boolean;
}

interface FilterOptions {
    statuses: ProcurementStatus[];
    offices: Pick<Office, 'id' | 'name'>[];
    particulars: Pick<Particular, 'id' | 'description'>[];
}

interface Props extends PageProps {
    procurements: PaginatedData<ProcurementListItem>;
    filters: Filters;
    options: FilterOptions;
    can: {
        manage: boolean;
    };
}

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const statusColors: Record<string, string> = {
    Created: 'bg-blue-100 text-blue-800',
    'In Progress': 'bg-yellow-100 text-yellow-800',
    Completed: 'bg-green-100 text-green-800',
    'On Hold': 'bg-orange-100 text-orange-800',
    Cancelled: 'bg-red-100 text-red-800',
};

export default function Index({ auth, procurements, filters, options, can }: Props) {
    const COLUMN_VISIBILITY_KEY = `procurements_column_visibility_${auth.user.id}`;

    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [sorting, setSorting] = useState<SortingState>([
        { id: 'created_at', desc: true }
    ]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(() => {
        // Load saved column visibility from localStorage
        const saved = localStorage.getItem(COLUMN_VISIBILITY_KEY);
        return saved ? JSON.parse(saved) : {};
    });
    const [showColumnDropdown, setShowColumnDropdown] = useState(false);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    // Save column visibility to localStorage whenever it changes
    useEffect(() => {
        localStorage.setItem(COLUMN_VISIBILITY_KEY, JSON.stringify(columnVisibility));
    }, [columnVisibility, COLUMN_VISIBILITY_KEY]);

    const columns: ColumnDef<ProcurementListItem>[] = [
        {
            accessorKey: 'id',
            enableHiding: false,
            header: ({ column }) => {
                return (
                    <button
                        className="flex items-center gap-1 hover:text-gray-900"
                        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    >
                        ID
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </button>
                );
            },
            cell: ({ row }) => <div className="font-medium">{row.getValue('id')}</div>,
        },
        {
            accessorKey: 'end_user',
            id: 'end_user',
            header: ({ column }) => {
                return (
                    <button
                        className="flex items-center gap-1 hover:text-gray-900"
                        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    >
                        End User Office
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </button>
                );
            },
            cell: ({ row }) => {
                const endUser = row.original.end_user;
                return <div className="min-w-[200px] whitespace-normal">{endUser?.name ?? '—'}</div>;
            },
            sortingFn: (rowA, rowB) => {
                const a = rowA.original.end_user?.name?.toLowerCase() ?? '';
                const b = rowB.original.end_user?.name?.toLowerCase() ?? '';
                return a.localeCompare(b);
            },
        },
        {
            accessorKey: 'particular',
            id: 'particular',
            header: 'Particular',
            cell: ({ row }) => {
                const particular = row.original.particular;
                return <div className="min-w-[200px] whitespace-normal">{particular?.description ?? '—'}</div>;
            },
        },
        {
            accessorKey: 'purpose',
            header: 'Purpose',
            cell: ({ row }) => {
                const purpose = row.getValue('purpose') as string;
                return (
                    <div className="min-w-[300px] max-w-md whitespace-normal">
                        {purpose || '—'}
                    </div>
                );
            },
        },
        {
            accessorKey: 'abc_amount',
            header: ({ column }) => {
                return (
                    <button
                        className="flex items-center gap-1 hover:text-gray-900"
                        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    >
                        ABC Amount
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </button>
                );
            },
            cell: ({ row }) => {
                const amount = parseFloat(row.getValue('abc_amount'));
                return <div className="min-w-[120px] text-right font-medium whitespace-nowrap">{currencyFormatter.format(amount)}</div>;
            },
        },
        {
            accessorKey: 'date_of_entry',
            header: ({ column }) => {
                return (
                    <button
                        className="flex items-center gap-1 hover:text-gray-900"
                        onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                    >
                        Date of Entry
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </button>
                );
            },
            cell: ({ row }) => {
                const date = new Date(row.getValue('date_of_entry'));
                return <div className="min-w-[100px] whitespace-nowrap">{date.toLocaleDateString()}</div>;
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const status = row.getValue('status') as string;
                return (
                    <span
                        className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${
                            statusColors[status] ?? 'bg-gray-100 text-gray-800'
                        }`}
                    >
                        {status}
                    </span>
                );
            },
        },
        {
            accessorKey: 'creator',
            id: 'creator',
            header: 'Created By',
            cell: ({ row }) => {
                const creator = row.original.creator;
                return <div className="min-w-[150px] whitespace-normal">{creator?.name ?? '—'}</div>;
            },
        },
        {
            id: 'actions',
            enableHiding: false,
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const procurement = row.original;
                const handleArchive = () => {
                    const hasTransactions = procurement.transactions_count > 0;
                    const confirmationMessage = hasTransactions
                        ? 'This procurement has linked transactions. Archiving will hide it from active lists. Proceed?'
                        : 'Archive this procurement? You can restore it later if needed.';

                    if (confirm(confirmationMessage)) {
                        router.delete(route('procurements.destroy', procurement.id), {
                            preserveScroll: true,
                        });
                    }
                };

                return (
                    <div className="flex items-center justify-end gap-3 min-w-[180px]">
                        <Link
                            href={route('procurements.show', procurement.id)}
                            className="text-indigo-600 hover:text-indigo-900"
                        >
                            View
                        </Link>
                        {can.manage && (
                            <>
                                <Link
                                    href={route('procurements.edit', procurement.id)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    onClick={handleArchive}
                                    className="text-red-600 hover:text-red-900"
                                >
                                    Archive
                                </button>
                            </>
                        )}
                    </div>
                );
            },
        },
    ];

    const table = useReactTable({
        data: procurements.data,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        state: {
            sorting,
            columnFilters,
            columnVisibility,
        },
    });

    const applyFilters = () => {
        const params: Record<string, unknown> = {};

        if (localFilters.search) params.search = localFilters.search;
        if (localFilters.status) params.status = localFilters.status;
        if (localFilters.end_user_id) params.end_user_id = localFilters.end_user_id;
        if (localFilters.particular_id) params.particular_id = localFilters.particular_id;
        if (localFilters.date_from) params.date_from = localFilters.date_from;
        if (localFilters.date_to) params.date_to = localFilters.date_to;
        if (localFilters.my_procurements) params.my_procurements = 1;

        router.get(route('procurements.index'), params as Record<string, string | number | boolean>, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setLocalFilters({
            search: '',
            status: '',
            end_user_id: null,
            particular_id: null,
            date_from: '',
            date_to: '',
            my_procurements: false,
        });

        router.get(route('procurements.index'), {}, {
            preserveScroll: true,
            replace: true,
        });
    };

    const submitFilters = (event: FormEvent) => {
        event.preventDefault();
        applyFilters();
    };

    const handlePagination = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Procurements <span className="text-sm text-gray-500">(Shadcn Data-Table)</span>
                    </h2>
                    {can.manage && (
                        <Link
                            href={route('procurements.create')}
                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                            New Procurement
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Procurements" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Filters Form */}
                            <form onSubmit={submitFilters} className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">
                                <div className="lg:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Search</label>
                                    <input
                                        type="text"
                                        value={localFilters.search}
                                        onChange={(event) => setLocalFilters({ ...localFilters, search: event.target.value })}
                                        placeholder="Search by purpose, office, or ID"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Status</label>
                                    <select
                                        value={localFilters.status}
                                        onChange={(event) => setLocalFilters({ ...localFilters, status: event.target.value as ProcurementStatus | '' })}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">All Statuses</option>
                                        {options.statuses.map((statusOption) => (
                                            <option key={statusOption} value={statusOption}>
                                                {statusOption}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">End User Office</label>
                                    <select
                                        value={localFilters.end_user_id ?? ''}
                                        onChange={(event) =>
                                            setLocalFilters({
                                                ...localFilters,
                                                end_user_id: event.target.value ? Number(event.target.value) : null,
                                            })
                                        }
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">All Offices</option>
                                        {options.offices.map((office) => (
                                            <option key={office.id} value={office.id}>
                                                {office.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Particular</label>
                                    <select
                                        value={localFilters.particular_id ?? ''}
                                        onChange={(event) =>
                                            setLocalFilters({
                                                ...localFilters,
                                                particular_id: event.target.value ? Number(event.target.value) : null,
                                            })
                                        }
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">All Particulars</option>
                                        {options.particulars.map((particular) => (
                                            <option key={particular.id} value={particular.id}>
                                                {particular.description}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Date From</label>
                                    <input
                                        type="date"
                                        value={localFilters.date_from}
                                        onChange={(event) => setLocalFilters({ ...localFilters, date_from: event.target.value })}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Date To</label>
                                    <input
                                        type="date"
                                        value={localFilters.date_to}
                                        onChange={(event) => setLocalFilters({ ...localFilters, date_to: event.target.value })}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        id="my_procurements"
                                        type="checkbox"
                                        checked={localFilters.my_procurements}
                                        onChange={(event) =>
                                            setLocalFilters({
                                                ...localFilters,
                                                my_procurements: event.target.checked,
                                            })
                                        }
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <label htmlFor="my_procurements" className="text-sm text-gray-700">
                                        My Procurements
                                    </label>
                                </div>

                                <div className="flex items-center gap-3 lg:col-span-2">
                                    <button
                                        type="submit"
                                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                    >
                                        Apply Filters
                                    </button>
                                    <button
                                        type="button"
                                        onClick={resetFilters}
                                        className="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    >
                                        Reset
                                    </button>
                                </div>
                            </form>

                            {/* Column Visibility Toggle */}
                            <div className="flex justify-end mb-4">
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
                            </div>

                            {/* Shadcn Data Table */}
                            <div className="rounded-md border overflow-x-auto">
                                <table className="w-full caption-bottom text-sm">
                                    <thead className="[&_tr]:border-b">
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <tr key={headerGroup.id} className="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                                                {headerGroup.headers.map((header) => (
                                                    <th
                                                        key={header.id}
                                                        className="h-12 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0 whitespace-nowrap"
                                                    >
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(
                                                                header.column.columnDef.header,
                                                                header.getContext()
                                                            )}
                                                    </th>
                                                ))}
                                            </tr>
                                        ))}
                                    </thead>
                                    <tbody className="[&_tr:last-child]:border-0">
                                        {table.getRowModel().rows?.length ? (
                                            table.getRowModel().rows.map((row) => (
                                                <tr
                                                    key={row.id}
                                                    className="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
                                                >
                                                    {row.getVisibleCells().map((cell) => (
                                                        <td key={cell.id} className="p-4 align-middle [&:has([role=checkbox])]:pr-0">
                                                            {flexRender(
                                                                cell.column.columnDef.cell,
                                                                cell.getContext()
                                                            )}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={columns.length} className="h-24 text-center">
                                                    No results.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            <div className="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div className="text-sm text-muted-foreground">
                                    Showing {procurements.from ?? 0} to {procurements.to ?? 0} of {procurements.total} results
                                </div>
                                <div className="flex flex-wrap items-center justify-center gap-2">
                                    <button
                                        onClick={() => handlePagination(procurements.links[0]?.url)}
                                        disabled={!procurements.links[0]?.url}
                                        className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        <span className="hidden sm:inline">Previous</span>
                                    </button>
                                    {procurements.links.slice(1, -1).map((link, index) => (
                                        <button
                                            key={index}
                                            onClick={() => handlePagination(link.url)}
                                            disabled={link.url === null}
                                            className={`inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-9 px-3 ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                                    : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                    <button
                                        onClick={() => handlePagination(procurements.links[procurements.links.length - 1]?.url)}
                                        disabled={!procurements.links[procurements.links.length - 1]?.url}
                                        className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
                                    >
                                        <span className="hidden sm:inline">Next</span>
                                        <ChevronRight className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
