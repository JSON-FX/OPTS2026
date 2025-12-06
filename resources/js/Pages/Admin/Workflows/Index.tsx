import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { PaginatedData, Workflow, WorkflowStep, Office, TransactionCategory } from '@/types/models';
import { useState, useMemo } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Plus, Search, ArrowUpDown, Eye, Pencil, Trash2 } from 'lucide-react';

interface WorkflowWithSteps extends Workflow {
    steps: (WorkflowStep & { office: Office })[];
    steps_count: number;
}

interface Filters {
    category: string;
    status: string;
    search: string;
    sort: string;
    direction: string;
}

interface Props extends PageProps {
    workflows: PaginatedData<WorkflowWithSteps>;
    filters: Filters;
}

export default function Index({ auth, workflows, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleSearch = () => {
        router.get(
            route('admin.workflows.index'),
            {
                search,
                category,
                status,
                sort: filters.sort,
                direction: filters.direction,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleFilterChange = (key: 'category' | 'status', value: string) => {
        const newFilters = {
            search,
            category: key === 'category' ? value : category,
            status: key === 'status' ? value : status,
            sort: filters.sort,
            direction: filters.direction,
        };

        if (key === 'category') setCategory(value);
        if (key === 'status') setStatus(value);

        router.get(route('admin.workflows.index'), newFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSort = (column: string) => {
        const newDirection =
            filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

        router.get(
            route('admin.workflows.index'),
            {
                search,
                category,
                status,
                sort: column,
                direction: newDirection,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleDelete = (workflow: WorkflowWithSteps) => {
        const message = `Are you sure you want to delete the workflow "${workflow.name}"? This action cannot be undone.`;

        if (confirm(message)) {
            router.delete(route('admin.workflows.destroy', workflow.id), {
                preserveScroll: true,
            });
        }
    };

    const SortableHeader = ({
        column,
        children,
    }: {
        column: string;
        children: React.ReactNode;
    }) => (
        <TableHead
            className="cursor-pointer hover:bg-muted/50"
            onClick={() => handleSort(column)}
        >
            <div className="flex items-center gap-1">
                {children}
                <ArrowUpDown className="h-4 w-4" />
                {filters.sort === column && (
                    <span className="text-xs">
                        {filters.direction === 'asc' ? '↑' : '↓'}
                    </span>
                )}
            </div>
        </TableHead>
    );

    const getCategoryBadge = (cat: TransactionCategory) => {
        const variants: Record<TransactionCategory, 'default' | 'secondary' | 'outline'> = {
            PR: 'default',
            PO: 'secondary',
            VCH: 'outline',
        };
        return <Badge variant={variants[cat]}>{cat}</Badge>;
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Workflow Management
                </h2>
            }
        >
            <Head title="Workflow Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Filters and Actions */}
                            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex flex-1 flex-wrap gap-4">
                                    <div className="flex gap-2">
                                        <Input
                                            placeholder="Search by name..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                            className="w-64"
                                        />
                                        <Button variant="outline" size="icon" onClick={handleSearch}>
                                            <Search className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <Select
                                        value={category}
                                        onValueChange={(v) => handleFilterChange('category', v)}
                                    >
                                        <SelectTrigger className="w-32">
                                            <SelectValue placeholder="Category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All</SelectItem>
                                            <SelectItem value="PR">PR</SelectItem>
                                            <SelectItem value="PO">PO</SelectItem>
                                            <SelectItem value="VCH">VCH</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={status}
                                        onValueChange={(v) => handleFilterChange('status', v)}
                                    >
                                        <SelectTrigger className="w-32">
                                            <SelectValue placeholder="Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All</SelectItem>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Link href={route('admin.workflows.create')}>
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        New Workflow
                                    </Button>
                                </Link>
                            </div>

                            {/* Table */}
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <SortableHeader column="name">Name</SortableHeader>
                                            <SortableHeader column="category">Category</SortableHeader>
                                            <SortableHeader column="steps_count">Steps</SortableHeader>
                                            <TableHead>Status</TableHead>
                                            <SortableHeader column="created_at">Created</SortableHeader>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {workflows.data.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                                                    No workflows found.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            workflows.data.map((workflow) => (
                                                <TableRow key={workflow.id}>
                                                    <TableCell className="font-medium">
                                                        {workflow.name}
                                                    </TableCell>
                                                    <TableCell>
                                                        {getCategoryBadge(workflow.category)}
                                                    </TableCell>
                                                    <TableCell>{workflow.steps_count}</TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant={workflow.is_active ? 'default' : 'secondary'}
                                                        >
                                                            {workflow.is_active ? 'Active' : 'Inactive'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        {new Date(workflow.created_at).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Link href={route('admin.workflows.show', workflow.id)}>
                                                                <Button variant="ghost" size="icon">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                            <Link href={route('admin.workflows.edit', workflow.id)}>
                                                                <Button variant="ghost" size="icon">
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            </Link>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleDelete(workflow)}
                                                            >
                                                                <Trash2 className="h-4 w-4 text-destructive" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Pagination */}
                            {workflows.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <p className="text-sm text-muted-foreground">
                                        Showing {workflows.from} to {workflows.to} of {workflows.total} results
                                    </p>
                                    <div className="flex gap-2">
                                        {workflows.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url)}
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
