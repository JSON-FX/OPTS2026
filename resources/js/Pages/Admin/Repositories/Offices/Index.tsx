import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Office, PaginatedData } from '@/types/models';
import { useState } from 'react';

interface OfficeWithCount extends Office {
    users_count: number;
}

interface Props extends PageProps {
    offices: PaginatedData<OfficeWithCount>;
}

export default function Index({ auth, offices }: Props) {
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [sortColumn, setSortColumn] = useState<'name' | 'type' | 'abbreviation' | 'created_at'>('name');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    const handleDelete = (officeId: number, userCount: number) => {
        const message = userCount > 0
            ? `This office has ${userCount} user(s) assigned. They will lose their office assignment. Are you sure you want to delete this office?`
            : 'Are you sure you want to delete this office?';

        if (confirm(message)) {
            router.delete(route('admin.repositories.offices.destroy', officeId), {
                preserveScroll: true,
            });
        }
    };

    const handleSort = (column: 'name' | 'type' | 'abbreviation' | 'created_at') => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const filteredOffices = offices.data
        .filter(office => {
            const matchesSearch =
                office.name.toLowerCase().includes(search.toLowerCase()) ||
                office.abbreviation.toLowerCase().includes(search.toLowerCase());
            const matchesType = !typeFilter || office.type === typeFilter;
            return matchesSearch && matchesType;
        })
        .sort((a, b) => {
            let aValue: string | number = a[sortColumn];
            let bValue: string | number = b[sortColumn];

            if (sortColumn === 'created_at') {
                aValue = new Date(aValue as string).getTime();
                bValue = new Date(bValue as string).getTime();
            } else {
                aValue = (aValue as string).toLowerCase();
                bValue = (bValue as string).toLowerCase();
            }

            if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

    const uniqueTypes = Array.from(new Set(offices.data.map(office => office.type))).sort();

    const SortIcon = ({ column }: { column: string }) => {
        if (sortColumn !== column) return null;
        return sortDirection === 'asc' ? ' ↑' : ' ↓';
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Office Management
                </h2>
            }
        >
            <Head title="Office Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex gap-4">
                                    <input
                                        type="text"
                                        placeholder="Search by name or abbreviation..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <select
                                        value={typeFilter}
                                        onChange={(e) => setTypeFilter(e.target.value)}
                                        className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">All Types</option>
                                        {uniqueTypes.map(type => (
                                            <option key={type} value={type}>{type}</option>
                                        ))}
                                    </select>
                                </div>
                                <Link
                                    href={route('admin.repositories.offices.create')}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                >
                                    Create Office
                                </Link>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-300">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('name')}
                                            >
                                                Name<SortIcon column="name" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('type')}
                                            >
                                                Type<SortIcon column="type" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('abbreviation')}
                                            >
                                                Abbreviation<SortIcon column="abbreviation" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('created_at')}
                                            >
                                                Created<SortIcon column="created_at" />
                                            </th>
                                            <th className="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {filteredOffices.map((office) => (
                                            <tr key={office.id}>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-900">{office.name}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{office.type}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{office.abbreviation}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {new Date(office.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-right text-sm font-medium">
                                                    <Link
                                                        href={route('admin.repositories.offices.edit', office.id)}
                                                        className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(office.id, office.users_count)}
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {filteredOffices.length === 0 && (
                                <div className="text-center py-8 text-gray-500">
                                    No offices found.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
