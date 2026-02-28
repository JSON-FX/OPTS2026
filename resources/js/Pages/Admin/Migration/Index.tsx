import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import type { MigrationImport, PaginatedData } from '@/types/models';
import { Loader2, Trash2, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    imports: PaginatedData<MigrationImport>;
}

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'completed': return 'default';
        case 'migrating': case 'importing': case 'analyzing': case 'dry_run': return 'secondary';
        case 'failed': return 'destructive';
        case 'rolled_back': return 'outline';
        default: return 'outline';
    }
};

export default function Index({ imports }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<{
        sql_file: File | null;
    }>({
        sql_file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(route('admin.migration.upload'), {
            forceFormData: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">ETTS Data Migration</h2>}
        >
            <Head title="Data Migration" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Migration Imports</CardTitle>
                                <CardDescription>
                                    Import ETTS SQL dumps to migrate legacy procurement records.
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Clear All Procurements
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Clear all procurement data?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This will permanently delete ALL procurements, transactions,
                                            purchase requests, purchase orders, vouchers, and transaction actions.
                                            Migration records will also be cleared. This action cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                            onClick={() => router.post(route('admin.migration.clear-all'))}
                                        >
                                            Yes, Clear Everything
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button>
                                        <Upload className="mr-2 h-4 w-4" />
                                        New Import
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <form onSubmit={handleSubmit}>
                                        <DialogHeader>
                                            <DialogTitle>Upload ETTS SQL Dump</DialogTitle>
                                            <DialogDescription>
                                                Select an SQL dump file exported from ETTS to begin the migration process.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div className="grid gap-4 py-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="sql_file">SQL File</Label>
                                                <Input
                                                    id="sql_file"
                                                    type="file"
                                                    accept=".sql,.txt"
                                                    onChange={(e) => setData('sql_file', e.target.files?.[0] ?? null)}
                                                />
                                                {errors.sql_file && (
                                                    <p className="text-sm text-destructive">{errors.sql_file}</p>
                                                )}
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <Button type="submit" disabled={processing || !data.sql_file}>
                                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                                Upload &amp; Start Import
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {imports.data.length === 0 ? (
                                <div className="py-12 text-center text-muted-foreground">
                                    No imports yet. Click "New Import" to get started.
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Filename</TableHead>
                                            <TableHead>Batch ID</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Records</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {imports.data.map((imp) => (
                                            <TableRow key={imp.id}>
                                                <TableCell className="font-medium">{imp.filename}</TableCell>
                                                <TableCell className="font-mono text-xs">{imp.batch_id.substring(0, 8)}...</TableCell>
                                                <TableCell>
                                                    <Badge variant={statusVariant(imp.status)}>
                                                        {imp.status.replace('_', ' ')}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span className="text-green-600">{imp.migrated_count}</span>
                                                    {' / '}
                                                    <span className="text-yellow-600">{imp.skipped_count}</span>
                                                    {' / '}
                                                    <span className="text-red-600">{imp.failed_count}</span>
                                                </TableCell>
                                                <TableCell>{new Date(imp.created_at).toLocaleDateString()}</TableCell>
                                                <TableCell>
                                                    {imp.status === 'analyzing' && (
                                                        <Link href={route('admin.migration.mappings', imp.id)}>
                                                            <Button variant="outline" size="sm">Mappings</Button>
                                                        </Link>
                                                    )}
                                                    {imp.status === 'dry_run' && (
                                                        <Link href={route('admin.migration.dry-run-results', imp.id)}>
                                                            <Button variant="outline" size="sm">Dry Run</Button>
                                                        </Link>
                                                    )}
                                                    {imp.status === 'migrating' && (
                                                        <Link href={route('admin.migration.progress', imp.id)}>
                                                            <Button variant="outline" size="sm">Progress</Button>
                                                        </Link>
                                                    )}
                                                    {(imp.status === 'completed' || imp.status === 'failed') && (
                                                        <Link href={route('admin.migration.results', imp.id)}>
                                                            <Button variant="outline" size="sm">Results</Button>
                                                        </Link>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
