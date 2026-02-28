import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import type { MigrationImport } from '@/types/models';
import { AlertTriangle, ArrowLeft, CheckCircle2, Undo2, XCircle } from 'lucide-react';

interface Props {
    import: MigrationImport;
}

export default function Results({ import: migrationImport }: Props) {
    const report = migrationImport.validation_report;

    const handleRollback = () => {
        router.post(route('admin.migration.rollback', migrationImport.id));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Migration Results</h2>}
        >
            <Head title="Migration Results" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Status Banner */}
                    {migrationImport.status === 'completed' && (
                        <Alert>
                            <CheckCircle2 className="h-4 w-4" />
                            <AlertTitle>Migration Completed Successfully</AlertTitle>
                            <AlertDescription>
                                Completed at {migrationImport.completed_at ? new Date(migrationImport.completed_at).toLocaleString() : 'N/A'}
                            </AlertDescription>
                        </Alert>
                    )}
                    {migrationImport.status === 'failed' && (
                        <Alert variant="destructive">
                            <XCircle className="h-4 w-4" />
                            <AlertTitle>Migration Failed</AlertTitle>
                            <AlertDescription>{migrationImport.error_message}</AlertDescription>
                        </Alert>
                    )}

                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Total Source</CardDescription>
                                <CardTitle className="text-2xl">{report?.counts?.source ?? migrationImport.total_source_records}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Created</CardDescription>
                                <CardTitle className="text-2xl text-green-600">{report?.counts?.created ?? migrationImport.migrated_count}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Skipped</CardDescription>
                                <CardTitle className="text-2xl text-yellow-600">{report?.counts?.skipped ?? migrationImport.skipped_count}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Failed</CardDescription>
                                <CardTitle className="text-2xl text-red-600">{report?.counts?.failed ?? migrationImport.failed_count}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    {/* Financial Reconciliation */}
                    {report?.financial_reconciliation && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Financial Reconciliation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Source</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow>
                                            <TableCell>ETTS Total</TableCell>
                                            <TableCell className="text-right">
                                                {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_reconciliation.etts_total)}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell>OPTS Total</TableCell>
                                            <TableCell className="text-right">
                                                {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_reconciliation.opts_total)}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">Difference</TableCell>
                                            <TableCell className={`text-right font-medium ${report.financial_reconciliation.difference !== 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_reconciliation.difference)}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}

                    {/* Integrity Checks */}
                    {report?.integrity_errors && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Integrity Checks</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {report.integrity_errors.length === 0 ? (
                                    <div className="flex items-center gap-2 text-green-600">
                                        <CheckCircle2 className="h-5 w-5" />
                                        All integrity checks passed.
                                    </div>
                                ) : (
                                    <ul className="space-y-2">
                                        {report.integrity_errors.map((error, idx) => (
                                            <li key={idx} className="flex items-center gap-2 text-red-600">
                                                <XCircle className="h-4 w-4" />
                                                {error}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Orphan Report */}
                    {report?.orphans && (report.orphans.pos > 0 || report.orphans.vchs > 0) && (
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Orphaned Records</AlertTitle>
                            <AlertDescription>
                                {report.orphans.pos > 0 && <span>{report.orphans.pos} orphaned PO(s). </span>}
                                {report.orphans.vchs > 0 && <span>{report.orphans.vchs} orphaned VCH(s). </span>}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Actions */}
                    <div className="flex justify-between">
                        <Link href={route('admin.migration.index')}>
                            <Button variant="outline">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Imports
                            </Button>
                        </Link>

                        {(migrationImport.status === 'completed' || migrationImport.status === 'failed') && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        <Undo2 className="mr-2 h-4 w-4" />
                                        Rollback This Import
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This will delete all {migrationImport.migrated_count} records created by this import.
                                            This action cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction onClick={handleRollback}>
                                            Yes, Rollback
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
