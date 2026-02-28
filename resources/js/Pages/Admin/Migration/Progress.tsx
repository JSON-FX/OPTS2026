import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import type { MigrationImport, MigrationProgressData, MigrationProgressUpdate } from '@/types/models';
import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    import: MigrationImport;
}

export default function MigrationProgressPage({ import: migrationImport }: Props) {
    const [wsProgress, setWsProgress] = useState<MigrationProgressUpdate | null>(null);
    const [error, setError] = useState<string | null>(migrationImport.error_message);
    const [wsLogs, setWsLogs] = useState<string[]>([]);
    const logRef = useRef<HTMLDivElement>(null);

    // Use WebSocket data if available, otherwise use polled progress_data
    const polledData: MigrationProgressData | null = migrationImport.progress_data;
    const percentage = wsProgress?.percentage ?? polledData?.percentage ?? 0;
    const current = wsProgress?.current ?? polledData?.current ?? 0;
    const total = wsProgress?.total ?? polledData?.total ?? 0;
    const message = wsProgress?.message ?? polledData?.message ?? 'Initializing migration...';
    const migratedCount = wsProgress?.migrated_count ?? polledData?.migrated_count ?? migrationImport.migrated_count;
    const skippedCount = wsProgress?.skipped_count ?? polledData?.skipped_count ?? migrationImport.skipped_count;
    const failedCount = polledData?.failed_count ?? migrationImport.failed_count;
    const logs = wsLogs.length > 0 ? wsLogs : (polledData?.log ?? []);

    const isCompleted = migrationImport.status === 'completed';
    const isFailed = migrationImport.status === 'failed';
    const isRunning = !isCompleted && !isFailed;

    useEffect(() => {
        if (isCompleted) {
            return;
        }

        if (isFailed) {
            setError(migrationImport.error_message);
            return;
        }

        // Listen for WebSocket events via Echo
        if (typeof window !== 'undefined' && (window as any).Echo) {
            const channel = (window as any).Echo.private(`migration.${migrationImport.id}`);

            channel.listen('MigrationProgress', (data: MigrationProgressUpdate) => {
                setWsProgress(data);
                setWsLogs(prev => [...prev, data.message].slice(-100));
            });

            channel.listen('MigrationCompleted', () => {
                router.visit(route('admin.migration.results', migrationImport.id));
            });

            channel.listen('MigrationFailed', (data: { message: string }) => {
                setError(data.message);
            });

            return () => {
                channel.stopListening('MigrationProgress');
                channel.stopListening('MigrationCompleted');
                channel.stopListening('MigrationFailed');
            };
        }

        // Fallback polling when WebSocket is not available
        const interval = setInterval(() => {
            router.reload({ only: ['import'] });
        }, 2000);
        return () => clearInterval(interval);
    }, [migrationImport.id, migrationImport.status]);

    // Auto-scroll log
    useEffect(() => {
        if (logRef.current) {
            logRef.current.scrollTop = logRef.current.scrollHeight;
        }
    }, [logs]);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Migration Progress</h2>}
        >
            <Head title="Migration Progress" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {error && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Migration Failed</AlertTitle>
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Progress Bar */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {isRunning && <Loader2 className="h-5 w-5 animate-spin" />}
                                {isCompleted && <CheckCircle2 className="h-5 w-5 text-green-600" />}
                                {isFailed && <AlertCircle className="h-5 w-5 text-red-600" />}
                                {isCompleted ? 'Migration Complete' : isFailed ? 'Migration Failed' : 'Migrating ETTS Data'}
                            </CardTitle>
                            <CardDescription>
                                {message}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Progress value={isCompleted ? 100 : percentage} className="mb-2" />
                            <p className="text-sm text-muted-foreground text-right">
                                {isCompleted ? 100 : percentage}%
                                {total > 0 && ` (${current} / ${total} groups)`}
                            </p>
                        </CardContent>
                    </Card>

                    {/* Counters */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Migrated</CardDescription>
                                <CardTitle className="text-2xl text-green-600">
                                    {migratedCount}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Skipped</CardDescription>
                                <CardTitle className="text-2xl text-yellow-600">
                                    {skippedCount}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Failed</CardDescription>
                                <CardTitle className="text-2xl text-red-600">
                                    {failedCount}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    {/* Activity Log */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                Activity Log
                                {isRunning && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                ref={logRef}
                                className="h-72 overflow-y-auto rounded-md bg-slate-950 p-4 font-mono text-xs text-green-400"
                            >
                                {logs.length === 0 ? (
                                    <p className="text-slate-500">Waiting for progress updates...</p>
                                ) : (
                                    logs.map((log, idx) => (
                                        <div
                                            key={idx}
                                            className={`py-0.5 ${log.includes('Failed:') || log.includes('FATAL') ? 'text-red-400' : log.includes('completed') || log.includes('Finished') ? 'text-emerald-400' : ''}`}
                                        >
                                            {log}
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    {isCompleted && (
                        <div className="flex justify-end">
                            <Button onClick={() => router.visit(route('admin.migration.results', migrationImport.id))}>
                                View Results
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
