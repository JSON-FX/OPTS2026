import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { PageProps } from '@/types';
import {
    Office,
    PaginatedData,
    Particular,
    Procurement,
    ProcurementStatus,
} from '@/types/models';

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
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [sortColumn, setSortColumn] = useState<'id' | 'end_user' | 'abc_amount' | 'date_of_entry' | 'created_at'>(
        'created_at',
    );
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

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

    const toggleSort = (column: typeof sortColumn) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const sortedProcurements = useMemo(() => {
        const items = [...procurements.data];

        return items.sort((a, b) => {
            let valueA: string | number | Date = '';
            let valueB: string | number | Date = '';

            switch (sortColumn) {
                case 'id':
                    valueA = a.id;
                    valueB = b.id;
                    break;
                case 'end_user':
                    valueA = a.end_user?.name?.toLowerCase() ?? '';
                    valueB = b.end_user?.name?.toLowerCase() ?? '';
                    break;
                case 'abc_amount':
                    valueA = Number(a.abc_amount);
                    valueB = Number(b.abc_amount);
                    break;
                case 'date_of_entry':
                    valueA = new Date(a.date_of_entry);
                    valueB = new Date(b.date_of_entry);
                    break;
                case 'created_at':
                default:
                    valueA = new Date(a.created_at);
                    valueB = new Date(b.created_at);
                    break;
            }

            if (valueA < valueB) return sortDirection === 'asc' ? -1 : 1;
            if (valueA > valueB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }, [procurements.data, sortColumn, sortDirection]);

    const handlePagination = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const handleArchive = (item: ProcurementListItem) => {
        const hasTransactions = item.transactions_count > 0;
        const confirmationMessage = hasTransactions
            ? 'This procurement has linked transactions. Archiving will hide it from active lists. Proceed?'
            : 'Archive this procurement? You can restore it later if needed.';

        if (confirm(confirmationMessage)) {
            router.delete(route('procurements.destroy', item.id), {
                preserveScroll: true,
            });
        }
    };

    const SortIcon = ({ column }: { column: typeof sortColumn }) => {
        if (sortColumn !== column) return null;
        return sortDirection === 'asc' ? <span> ↑</span> : <span> ↓</span>;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Procurements
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

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer"
                                                onClick={() => toggleSort('id')}
                                            >
                                                ID<SortIcon column="id" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer"
                                                onClick={() => toggleSort('end_user')}
                                            >
                                                End User Office<SortIcon column="end_user" />
                                            </th>
                                            <th className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                Particular
                                            </th>
                                            <th className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                Purpose
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-right text-sm font-semibold text-gray-900 cursor-pointer"
                                                onClick={() => toggleSort('abc_amount')}
                                            >
                                                ABC Amount<SortIcon column="abc_amount" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer"
                                                onClick={() => toggleSort('date_of_entry')}
                                            >
                                                Date of Entry<SortIcon column="date_of_entry" />
                                            </th>
                                            <th className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                Status
                                            </th>
                                            <th className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                                Created By
                                            </th>
                                            <th className="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {sortedProcurements.map((procurement) => (
                                            <tr key={procurement.id}>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                                    {procurement.id}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {procurement.end_user?.name ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {procurement.particular?.description ?? '—'}
                                                </td>
                                                <td className="px-3 py-4 text-sm text-gray-500">
                                                    {procurement.purpose ? `${procurement.purpose.slice(0, 100)}${
                                                        procurement.purpose.length > 100 ? '…' : ''
                                                    }` : '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-right text-sm text-gray-900">
                                                    {currencyFormatter.format(Number(procurement.abc_amount))}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {new Date(procurement.date_of_entry).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${
                                                            statusColors[procurement.status] ?? 'bg-gray-100 text-gray-800'
                                                        }`}
                                                    >
                                                        {procurement.status}
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {procurement.creator?.name ?? '—'}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-3">
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
                                                                    onClick={() => handleArchive(procurement)}
                                                                    className="text-red-600 hover:text-red-900"
                                                                >
                                                                    Archive
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-6 flex flex-wrap items-center gap-2">
                                {procurements.links.map((link, index) => (
                                    <button
                                        key={index}
                                        disabled={link.url === null}
                                        onClick={() => handlePagination(link.url)}
                                        className={`rounded-md px-3 py-1 text-sm font-medium ${
                                            link.active
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-white text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                                        } ${link.url === null ? 'cursor-not-allowed opacity-50' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
