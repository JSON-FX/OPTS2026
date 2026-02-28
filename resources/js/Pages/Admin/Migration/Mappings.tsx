import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/Components/ui/accordion';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import type { MigrationImport, MigrationMappingData, MigrationMappingEntry, Office } from '@/types/models';
import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    import: MigrationImport;
    offices: Pick<Office, 'id' | 'name' | 'abbreviation'>[];
}

export default function Mappings({ import: migrationImport, offices }: Props) {
    const mappingData = migrationImport.mapping_data;
    const [localMappings, setLocalMappings] = useState<MigrationMappingData | null>(mappingData);
    const [processing, setProcessing] = useState(false);

    // Sync localMappings when mappingData arrives from server
    useEffect(() => {
        if (mappingData && !localMappings) {
            setLocalMappings(mappingData);
        }
    }, [mappingData]);

    // Auto-refresh while waiting for analysis to complete
    useEffect(() => {
        if (!mappingData && migrationImport.status !== 'failed') {
            const interval = setInterval(() => {
                router.reload({ only: ['import'] });
            }, 2000);
            return () => clearInterval(interval);
        }
    }, [mappingData, migrationImport.status]);

    if (!mappingData || !localMappings) {
        const isImporting = migrationImport.status === 'importing' || migrationImport.status === 'pending';
        const isAnalyzing = migrationImport.status === 'analyzing';
        const isFailed = migrationImport.status === 'failed';

        return (
            <AuthenticatedLayout
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Mapping Review</h2>}
            >
                <Head title="Mapping Review" />
                <div className="py-12">
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                        <Card>
                            <CardContent className="py-12">
                                <div className="flex flex-col items-center space-y-4">
                                    {isFailed ? (
                                        <AlertCircle className="h-8 w-8 text-destructive" />
                                    ) : (
                                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                    )}
                                    <div className="text-center">
                                        <p className="font-medium">
                                            {isFailed
                                                ? 'Import failed'
                                                : isImporting
                                                    ? 'Importing SQL dump...'
                                                    : 'Analyzing ETTS data...'}
                                        </p>
                                        {isFailed && migrationImport.error_message && (
                                            <p className="text-sm text-destructive mt-1">{migrationImport.error_message}</p>
                                        )}
                                        {!isFailed && (
                                            <p className="text-sm text-muted-foreground mt-1">
                                                {migrationImport.total_source_records
                                                    ? `Found ${migrationImport.total_source_records} source transactions`
                                                    : 'Reading SQL dump and creating temp database'}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center space-x-6 text-sm text-muted-foreground">
                                        <span className="flex items-center gap-1.5">
                                            {isImporting ? (
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            )}
                                            Import SQL
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            {isAnalyzing ? (
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                            ) : isImporting ? (
                                                <span className="h-4 w-4 rounded-full border-2 border-muted-foreground/30" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            )}
                                            Analyze mappings
                                        </span>
                                    </div>
                                    {!isFailed && (
                                        <p className="text-xs text-muted-foreground">Page will refresh automatically</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    const updateOfficeMapping = (index: number, targetId: number | null) => {
        const updated = { ...localMappings };
        updated.offices = [...updated.offices];
        updated.offices[index] = {
            ...updated.offices[index],
            target_id: targetId,
            target_name: offices.find(o => o.id === targetId)?.name ?? null,
            status: targetId ? 'matched' : 'unmatched',
        };
        setLocalMappings(updated);
    };

    const handleSave = () => {
        setProcessing(true);
        router.post(
            route('admin.migration.save-mappings', migrationImport.id),
            { mapping_data: localMappings } as never,
            { onFinish: () => setProcessing(false) },
        );
    };

    const matchedCount = (entries: MigrationMappingEntry[]) => entries.filter(e => e.status === 'matched').length;
    const unmatchedCount = (entries: MigrationMappingEntry[]) => entries.filter(e => e.status === 'unmatched').length;
    const newCount = (entries: MigrationMappingEntry[]) => entries.filter(e => e.status === 'new').length;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Mapping Review</h2>}
        >
            <Head title="Mapping Review" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Source Counts Summary */}
                    <div className="grid gap-4 md:grid-cols-5">
                        {Object.entries(localMappings.source_counts).map(([key, count]) => (
                            <Card key={key}>
                                <CardHeader className="pb-2">
                                    <CardDescription className="capitalize">{key}</CardDescription>
                                    <CardTitle className="text-2xl">{count}</CardTitle>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>

                    {/* Mapping Sections */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Mappings</CardTitle>
                            <CardDescription>
                                Review and adjust how ETTS data maps to OPTS2026 records.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Accordion type="multiple" defaultValue={['offices', 'users', 'particulars', 'action_taken']}>
                                {/* Offices */}
                                <AccordionItem value="offices">
                                    <AccordionTrigger>
                                        Offices
                                        <div className="ml-auto mr-4 flex gap-2">
                                            <Badge variant="default">{matchedCount(localMappings.offices)} matched</Badge>
                                            {unmatchedCount(localMappings.offices) > 0 && (
                                                <Badge variant="destructive">{unmatchedCount(localMappings.offices)} unmatched</Badge>
                                            )}
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>ETTS Office</TableHead>
                                                    <TableHead>OPTS2026 Office</TableHead>
                                                    <TableHead>Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {localMappings.offices.map((entry, idx) => (
                                                    <TableRow key={idx}>
                                                        <TableCell>{entry.source_name}</TableCell>
                                                        <TableCell>
                                                            <Select
                                                                value={entry.target_id?.toString() ?? ''}
                                                                onValueChange={(val) => updateOfficeMapping(idx, val ? parseInt(val) : null)}
                                                            >
                                                                <SelectTrigger className="w-[250px]">
                                                                    <SelectValue placeholder="Select office..." />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {offices.map((office) => (
                                                                        <SelectItem key={office.id} value={office.id.toString()}>
                                                                            {office.name} ({office.abbreviation})
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </TableCell>
                                                        <TableCell>
                                                            {entry.status === 'matched' ? (
                                                                <CheckCircle2 className="h-5 w-5 text-green-500" />
                                                            ) : (
                                                                <AlertCircle className="h-5 w-5 text-yellow-500" />
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </AccordionContent>
                                </AccordionItem>

                                {/* Users */}
                                <AccordionItem value="users">
                                    <AccordionTrigger>
                                        Users
                                        <div className="ml-auto mr-4 flex gap-2">
                                            <Badge variant="default">{matchedCount(localMappings.users)} matched</Badge>
                                            {newCount(localMappings.users) > 0 && (
                                                <Badge variant="secondary">{newCount(localMappings.users)} new</Badge>
                                            )}
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>ETTS User</TableHead>
                                                    <TableHead>OPTS2026 Match</TableHead>
                                                    <TableHead>Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {localMappings.users.map((entry, idx) => (
                                                    <TableRow key={idx}>
                                                        <TableCell>{entry.source_name}</TableCell>
                                                        <TableCell>{entry.target_name ?? '—'}</TableCell>
                                                        <TableCell>
                                                            <Badge variant={entry.status === 'matched' ? 'default' : 'secondary'}>
                                                                {entry.status}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </AccordionContent>
                                </AccordionItem>

                                {/* Particulars */}
                                <AccordionItem value="particulars">
                                    <AccordionTrigger>
                                        Particulars
                                        <div className="ml-auto mr-4 flex gap-2">
                                            <Badge variant="default">{matchedCount(localMappings.particulars)} matched</Badge>
                                            {newCount(localMappings.particulars) > 0 && (
                                                <Badge variant="secondary">{newCount(localMappings.particulars)} new</Badge>
                                            )}
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>ETTS Description</TableHead>
                                                    <TableHead>OPTS2026 Match</TableHead>
                                                    <TableHead>Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {localMappings.particulars.map((entry, idx) => (
                                                    <TableRow key={idx}>
                                                        <TableCell>{entry.source_name}</TableCell>
                                                        <TableCell>{entry.target_name ?? 'Will be created'}</TableCell>
                                                        <TableCell>
                                                            <Badge variant={entry.status === 'matched' ? 'default' : 'secondary'}>
                                                                {entry.status}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </AccordionContent>
                                </AccordionItem>

                                {/* Action Taken */}
                                <AccordionItem value="action_taken">
                                    <AccordionTrigger>
                                        Action Taken
                                        <div className="ml-auto mr-4 flex gap-2">
                                            <Badge variant="default">{matchedCount(localMappings.action_taken)} matched</Badge>
                                            {newCount(localMappings.action_taken) > 0 && (
                                                <Badge variant="secondary">{newCount(localMappings.action_taken)} new</Badge>
                                            )}
                                        </div>
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>ETTS Action</TableHead>
                                                    <TableHead>OPTS2026 Match</TableHead>
                                                    <TableHead>Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {localMappings.action_taken.map((entry, idx) => (
                                                    <TableRow key={idx}>
                                                        <TableCell>{entry.source_name}</TableCell>
                                                        <TableCell>{entry.target_name ?? 'Will be created'}</TableCell>
                                                        <TableCell>
                                                            <Badge variant={entry.status === 'matched' ? 'default' : 'secondary'}>
                                                                {entry.status}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>

                            <div className="mt-6 flex justify-end">
                                <Button onClick={handleSave} disabled={processing}>
                                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Save Mappings &amp; Run Dry Run
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
