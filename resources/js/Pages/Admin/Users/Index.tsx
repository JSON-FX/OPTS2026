import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { User, Role, Office, PaginatedData } from '@/types/models';
import { useState } from 'react';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/Components/DataTable';
import { Badge } from '@/Components/ui/badge';
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
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { MoreHorizontal, ArrowUpDown, Pencil, Trash2 } from 'lucide-react';

interface Props extends PageProps {
    users: PaginatedData<User>;
    roles: Role[];
    offices: Office[];
}

export default function Index({ users, roles, offices }: Props) {
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');
    const [officeFilter, setOfficeFilter] = useState('all');
    const [deleteUserId, setDeleteUserId] = useState<number | null>(null);

    const handleDelete = (userId: number) => {
        setDeleteUserId(userId);
    };

    const confirmDelete = () => {
        if (deleteUserId) {
            router.delete(route('admin.users.destroy', deleteUserId), {
                preserveScroll: true,
                onSuccess: () => setDeleteUserId(null),
            });
        }
    };

    const columns: ColumnDef<User>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
        },
        {
            accessorKey: 'email',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Email
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
        },
        {
            id: 'role',
            accessorFn: (row) => row.roles?.[0]?.name || 'No role',
            header: 'Role',
            cell: ({ row }) => {
                const role = row.original.roles?.[0]?.name || 'No role';
                const variant =
                    role === 'Administrator'
                        ? 'default'
                        : role === 'Endorser'
                          ? 'secondary'
                          : 'outline';
                return <Badge variant={variant}>{role}</Badge>;
            },
            filterFn: (row, _id, filterValue) => {
                if (!filterValue || filterValue === 'all') return true;
                return row.original.roles?.[0]?.name === filterValue;
            },
        },
        {
            id: 'office',
            accessorFn: (row) => row.office?.name || '',
            header: 'Office',
            cell: ({ row }) => row.original.office?.name || '-',
            filterFn: (row, _id, filterValue) => {
                if (!filterValue || filterValue === 'all') return true;
                return String(row.original.office_id) === filterValue;
            },
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                >
                    Created
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) =>
                new Date(row.getValue('created_at')).toLocaleDateString(),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
                            <MoreHorizontal className="h-4 w-4" />
                            <span className="sr-only">Open menu</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onClick={() =>
                                router.visit(
                                    route('admin.users.edit', row.original.id)
                                )
                            }
                        >
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => handleDelete(row.original.id)}
                            className="text-destructive focus:text-destructive"
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    // Apply client-side filters to the current page data
    const filteredData = users.data.filter((user) => {
        const matchesSearch =
            !search ||
            user.name.toLowerCase().includes(search.toLowerCase()) ||
            user.email.toLowerCase().includes(search.toLowerCase());
        const matchesRole =
            roleFilter === 'all' || user.roles?.[0]?.name === roleFilter;
        const matchesOffice =
            officeFilter === 'all' ||
            String(user.office_id) === officeFilter;
        return matchesSearch && matchesRole && matchesOffice;
    });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    User Management
                </h2>
            }
        >
            <Head title="User Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <DataTable columns={columns} data={filteredData}>
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                    <Input
                                        placeholder="Search by name or email..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="max-w-sm"
                                    />
                                    <Select
                                        value={roleFilter}
                                        onValueChange={setRoleFilter}
                                    >
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue placeholder="All Roles" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All Roles
                                            </SelectItem>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.id}
                                                    value={role.name}
                                                >
                                                    {role.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={officeFilter}
                                        onValueChange={setOfficeFilter}
                                    >
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue placeholder="All Offices" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All Offices
                                            </SelectItem>
                                            {offices.map((office) => (
                                                <SelectItem
                                                    key={office.id}
                                                    value={String(office.id)}
                                                >
                                                    {office.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </DataTable>
                        </div>
                    </div>
                </div>
            </div>

            <AlertDialog
                open={deleteUserId !== null}
                onOpenChange={(open) => !open && setDeleteUserId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. This will permanently
                            delete the user account.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AuthenticatedLayout>
    );
}
