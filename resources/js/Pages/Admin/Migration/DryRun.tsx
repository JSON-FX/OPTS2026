import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import type { MigrationImport } from '@/types/models';
import { AlertTriangle, ArrowLeft, CheckCircle2, Loader2, Play, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    import: MigrationImport;
}

export default function DryRun({ import: migrationImport }: Props) {
    const report = migrationImport.dry_run_report;
    const [executing, setExecuting] = useState(false);
    const [excludeOrphans, setExcludeOrphans] = useState(migrationImport.exclude_orphans ?? true);

    // Auto-refresh while waiting for dry run to complete
    useEffect(() => {
        if (!report && migrationImport.status !== 'failed') {
            const interval = setInterval(() => {
                router.reload({ only: ['import'] });
            }, 2000);
            return () => clearInterval(interval);
        }
    }, [report, migrationImport.status]);

    if (migrationImport.status === 'failed') {
        return (
            <AuthenticatedLayout
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dry Run Results</h2>}
            >
                <Head title="Dry Run Results" />
                <div className="py-12">
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Dry Run Failed</AlertTitle>
                            <AlertDescription>
                                {migrationImport.error_message || 'An unknown error occurred during the dry run.'}
                            </AlertDescription>
                        </Alert>
                        <div className="mt-4">
                            <Link href={route('admin.migration.mappings', migrationImport.id)}>
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to Mappings
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    if (!report) {
        return (
            <AuthenticatedLayout
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dry Run Results</h2>}
            >
                <Head title="Dry Run Results" />
                <div className="py-12">
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <Card>
                            <CardContent className="py-12">
                                <div className="flex flex-col items-center space-y-4">
                                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                    <div className="text-center">
                                        <p className="font-medium">Running dry run analysis...</p>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            Resolving reference chains from {migrationImport.total_source_records ?? 0} source records
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-6 text-sm text-muted-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            SQL imported
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            Mappings analyzed
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                            Dry run
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">Page will refresh automatically</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    const hasOrphans = report && (report.orphaned_pos > 0 || report.orphaned_vchs > 0);

    const handleExecute = () => {
        setExecuting(true);
        router.post(route('admin.migration.execute', migrationImport.id), {
            exclude_orphans: excludeOrphans,
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dry Run Results</h2>}
        >
            <Head title="Dry Run Results" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Procurements to Create</CardDescription>
                                <CardTitle className="text-2xl">{report.procurements_to_create}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>PR / PO / VCH</CardDescription>
                                <CardTitle className="text-2xl">
                                    {report.transactions_to_create.pr} / {report.transactions_to_create.po} / {report.transactions_to_create.vch}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Orphans to Skip</CardDescription>
                                <CardTitle className="text-2xl">
                                    {excludeOrphans ? (report.orphaned_pos + report.orphaned_vchs) : 0}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Unparseable Dates</CardDescription>
                                <CardTitle className="text-2xl">{report.unparseable_dates}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    {/* Orphan Records */}
                    {hasOrphans && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <ShieldAlert className="h-5 w-5 text-amber-500" />
                                        <CardTitle>Orphaned Records</CardTitle>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Label htmlFor="exclude-orphans" className="text-sm font-normal text-muted-foreground">
                                            {excludeOrphans ? 'Excluded from migration' : 'Included in migration'}
                                        </Label>
                                        <Switch
                                            id="exclude-orphans"
                                            checked={excludeOrphans}
                                            onCheckedChange={setExcludeOrphans}
                                        />
                                    </div>
                                </div>
                                <CardDescription>
                                    {report.orphaned_pos > 0 && <span>{report.orphaned_pos} PO(s) without a linked PR. </span>}
                                    {report.orphaned_vchs > 0 && <span>{report.orphaned_vchs} VCH(s) without a linked PR. </span>}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {excludeOrphans ? (
                                    <Alert>
                                        <CheckCircle2 className="h-4 w-4" />
                                        <AlertTitle>Orphans will be skipped</AlertTitle>
                                        <AlertDescription>
                                            POs and VCHs without a linked Purchase Request will not be migrated.
                                            This ensures all procurements follow the PR → PO → VCH chain.
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>Orphans will be included</AlertTitle>
                                        <AlertDescription>
                                            Orphaned POs and VCHs will be migrated as standalone procurements without a Purchase Request.
                                            This breaks the recommended PR → PO → VCH chain.
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Financial Totals */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Financial Totals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow>
                                        <TableCell>Purchase Requests</TableCell>
                                        <TableCell className="text-right">
                                            {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_totals.pr_amount)}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell>Purchase Orders</TableCell>
                                        <TableCell className="text-right">
                                            {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_totals.po_amount)}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell>Vouchers</TableCell>
                                        <TableCell className="text-right">
                                            {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(report.financial_totals.vch_amount)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    {/* Warnings */}
                    {report.warnings.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Warnings ({report.warnings.length})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="list-disc pl-5 space-y-1 max-h-60 overflow-y-auto">
                                    {report.warnings.map((warning, idx) => (
                                        <li key={idx} className="text-sm text-muted-foreground">{warning}</li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}

                    {/* Actions */}
                    <div className="flex justify-between">
                        <Link href={route('admin.migration.mappings', migrationImport.id)}>
                            <Button variant="outline">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Mappings
                            </Button>
                        </Link>
                        <Button onClick={handleExecute} disabled={executing}>
                            {executing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <Play className="mr-2 h-4 w-4" />
                            Execute Migration
                        </Button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
