import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { ActionTaken, PaginatedData } from '@/types/models';
import { useState } from 'react';

interface Props extends PageProps {
    actionTaken: PaginatedData<ActionTaken>;
}

export default function Index({ auth, actionTaken }: Props) {
    const [search, setSearch] = useState('');
    const [sortColumn, setSortColumn] = useState<'description' | 'created_at'>('description');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    const handleDelete = (actionTakenId: number) => {
        if (confirm('Are you sure you want to delete this action taken?')) {
            router.delete(route('admin.repositories.action-taken.destroy', actionTakenId), {
                preserveScroll: true,
            });
        }
    };

    const handleSort = (column: 'description' | 'created_at') => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const filteredActionTaken = actionTaken.data
        .filter(action => {
            const matchesSearch = action.description.toLowerCase().includes(search.toLowerCase());
            return matchesSearch;
        })
        .sort((a, b) => {
            let aValue: string | number = a[sortColumn] ?? '';
            let bValue: string | number = b[sortColumn] ?? '';

            if (sortColumn === 'created_at') {
                aValue = new Date(aValue as string).getTime();
                bValue = new Date(bValue as string).getTime();
            } else {
                aValue = String(aValue).toLowerCase();
                bValue = String(bValue).toLowerCase();
            }

            if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

    const SortIcon = ({ column }: { column: string }) => {
        if (sortColumn !== column) return null;
        return sortDirection === 'asc' ? ' ↑' : ' ↓';
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Action Taken Management
                </h2>
            }
        >
            <Head title="Action Taken Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex gap-4">
                                    <input
                                        type="text"
                                        placeholder="Search by description..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <Link
                                    href={route('admin.repositories.action-taken.create')}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                >
                                    Create Action Taken
                                </Link>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-300">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('description')}
                                            >
                                                Description<SortIcon column="description" />
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
                                        {filteredActionTaken.map((action) => (
                                            <tr key={action.id}>
                                                <td className="px-3 py-4 text-sm text-gray-900">{action.description}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {new Date(action.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-right text-sm font-medium">
                                                    <Link
                                                        href={route('admin.repositories.action-taken.edit', action.id)}
                                                        className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(action.id)}
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

                            {filteredActionTaken.length === 0 && (
                                <div className="text-center py-8 text-gray-500">
                                    No action taken records found.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
