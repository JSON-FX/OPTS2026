import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Supplier, PaginatedData } from '@/types/models';
import { useState } from 'react';

interface Props extends PageProps {
    suppliers: PaginatedData<Supplier>;
}

export default function Index({ auth, suppliers }: Props) {
    const [search, setSearch] = useState('');
    const [sortColumn, setSortColumn] = useState<'name' | 'address' | 'contact_person' | 'contact_number' | 'created_at'>('name');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

    const handleDelete = (supplierId: number) => {
        if (confirm('Are you sure you want to delete this supplier?')) {
            router.delete(route('admin.repositories.suppliers.destroy', supplierId), {
                preserveScroll: true,
            });
        }
    };

    const handleSort = (column: 'name' | 'address' | 'contact_person' | 'contact_number' | 'created_at') => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const filteredSuppliers = suppliers.data
        .filter(supplier => {
            const matchesSearch =
                supplier.name.toLowerCase().includes(search.toLowerCase()) ||
                supplier.address.toLowerCase().includes(search.toLowerCase());
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
                    Supplier Management
                </h2>
            }
        >
            <Head title="Supplier Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <div className="flex gap-4">
                                    <input
                                        type="text"
                                        placeholder="Search by name or address..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <Link
                                    href={route('admin.repositories.suppliers.create')}
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                >
                                    Create Supplier
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
                                                onClick={() => handleSort('address')}
                                            >
                                                Address<SortIcon column="address" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('contact_person')}
                                            >
                                                Contact Person<SortIcon column="contact_person" />
                                            </th>
                                            <th
                                                className="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 cursor-pointer hover:bg-gray-100"
                                                onClick={() => handleSort('contact_number')}
                                            >
                                                Contact Number<SortIcon column="contact_number" />
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
                                        {filteredSuppliers.map((supplier) => (
                                            <tr key={supplier.id}>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-900">{supplier.name}</td>
                                                <td className="px-3 py-4 text-sm text-gray-500 max-w-xs truncate">{supplier.address}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{supplier.contact_person || '-'}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{supplier.contact_number || '-'}</td>
                                                <td className="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                    {new Date(supplier.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-4 text-right text-sm font-medium">
                                                    <Link
                                                        href={route('admin.repositories.suppliers.edit', supplier.id)}
                                                        className="text-indigo-600 hover:text-indigo-900 mr-4"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(supplier.id)}
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

                            {filteredSuppliers.length === 0 && (
                                <div className="text-center py-8 text-gray-500">
                                    No suppliers found.
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
