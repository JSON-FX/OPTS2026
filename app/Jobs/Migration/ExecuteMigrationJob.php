<?php

declare(strict_types=1);

namespace App\Jobs\Migration;

use App\Events\Migration\MigrationCompleted;
use App\Events\Migration\MigrationFailed;
use App\Events\Migration\MigrationProgress;
use App\Models\MigrationImport;
use App\Models\MigrationRecord;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\Migration\DateParser;
use App\Services\Migration\EttsMapper;
use App\Services\Migration\MigrationReportService;
use App\Services\Migration\ReferenceChainResolver;
use App\Services\WorkflowAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private int $migratedCount = 0;
    private int $skippedCount = 0;
    private int $failedCount = 0;
    private array $progressLog = [];

    public function __construct(
        public MigrationImport $import,
    ) {}

    public function handle(): void
    {
        try {
            $this->import->update([
                'status' => MigrationImport::STATUS_MIGRATING,
                'started_at' => now(),
            ]);

            $this->logProgress('Migration started');

            // Configure temp database connection
            config([
                'database.connections.etts_temp' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $this->import->temp_database]
                ),
            ]);

            $mapper = new EttsMapper($this->import->temp_database);
            $resolver = new ReferenceChainResolver();
            $dateParser = new DateParser();

            $this->logProgress('Loading ETTS transactions from temp database...');

            // Get and group ETTS transactions
            $ettsTransactions = DB::connection('etts_temp')
                ->table('transactions')
                ->get();

            $this->logProgress("Loaded {$ettsTransactions->count()} ETTS transactions");
            $this->logProgress('Resolving reference chains (PR → PO → VCH)...');

            $groups = $resolver->resolve($ettsTransactions);

            $this->logProgress('Resolved ' . count($groups) . ' procurement groups');

            // Filter out orphan groups (no PR) if exclude_orphans is enabled
            if ($this->import->exclude_orphans) {
                $orphanCount = count(array_filter($groups, fn(array $g) => $g['pr'] === null));
                $groups = array_values(array_filter($groups, fn(array $g) => $g['pr'] !== null));
                $this->skippedCount += $orphanCount;
                if ($orphanCount > 0) {
                    $this->logProgress("Filtered out {$orphanCount} orphan groups (no linked PR)");
                }
            }

            $totalGroups = count($groups);
            $this->logProgress("Processing {$totalGroups} procurement groups...");
            $this->persistProgress(0, $totalGroups);

            // Process groups in chunks
            $chunks = array_chunk($groups, 500);
            $processedGroups = 0;

            foreach ($chunks as $chunk) {
                foreach ($chunk as $group) {
                    try {
                        DB::transaction(function () use ($group, $mapper, $dateParser) {
                            $this->processGroup($group, $mapper, $dateParser);
                        });
                    } catch (\Throwable $e) {
                        $this->failedCount++;
                        $prRef = $group['pr']->reference_id ?? 'orphan';
                        Log::warning('Migration group failed', [
                            'import_id' => $this->import->id,
                            'error' => $e->getMessage(),
                            'pr_ref' => $prRef,
                        ]);
                        $this->logProgress("Failed: group {$prRef} — {$e->getMessage()}");
                    }

                    $processedGroups++;

                    // Persist progress every 10 groups
                    if ($processedGroups % 10 === 0 || $processedGroups === $totalGroups) {
                        $this->persistProgress($processedGroups, $totalGroups);

                        // Also broadcast for WebSocket clients
                        $percentage = $totalGroups > 0
                            ? (int) round(($processedGroups / $totalGroups) * 100)
                            : 100;

                        try {
                            MigrationProgress::dispatch(
                                $this->import->id,
                                $processedGroups,
                                $totalGroups,
                                $percentage,
                                "Processing group {$processedGroups} of {$totalGroups}...",
                                $this->migratedCount,
                                $this->skippedCount,
                            );
                        } catch (\Throwable) {
                            // WebSocket not available — polling will pick up progress_data
                        }
                    }
                }
            }

            $this->logProgress("Finished processing groups: {$this->migratedCount} migrated, {$this->skippedCount} skipped, {$this->failedCount} failed");

            // Migrate endorsements to transaction_actions
            $this->logProgress('Migrating endorsement records...');
            $this->persistProgress($totalGroups, $totalGroups, 'Migrating endorsements...');
            $this->migrateEndorsements($mapper, $dateParser);

            // Migrate events to transaction_actions
            $this->logProgress('Migrating event records...');
            $this->persistProgress($totalGroups, $totalGroups, 'Migrating events...');
            $this->migrateEvents($mapper, $dateParser);

            // Update current office locations on migrated transactions
            $this->logProgress('Updating transaction current office locations...');
            $this->persistProgress($totalGroups, $totalGroups, 'Updating current office locations...');
            $this->updateCurrentOfficeIds($mapper);

            // Assign workflows to legacy transactions
            $this->logProgress('Assigning workflows to legacy transactions...');
            $this->persistProgress($totalGroups, $totalGroups, 'Assigning workflows...');
            $this->assignWorkflowsToLegacyTransactions();

            // Generate validation report
            $this->logProgress('Generating validation report...');
            $this->persistProgress($totalGroups, $totalGroups, 'Generating validation report...');
            $reportService = new MigrationReportService();
            $validationReport = $reportService->generateValidationReport($this->import);

            $this->logProgress('Migration completed successfully');

            $this->import->update([
                'status' => MigrationImport::STATUS_COMPLETED,
                'completed_at' => now(),
                'migrated_count' => $this->migratedCount,
                'skipped_count' => $this->skippedCount,
                'failed_count' => $this->failedCount,
                'validation_report' => $validationReport,
                'progress_data' => $this->buildProgressData($totalGroups, $totalGroups, 'Migration completed'),
            ]);

            try {
                MigrationCompleted::dispatch($this->import->id);
            } catch (\Throwable) {
                // WebSocket not available
            }

        } catch (\Throwable $e) {
            Log::error('ExecuteMigrationJob failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            $this->logProgress("FATAL ERROR: {$e->getMessage()}");

            $this->import->update([
                'status' => MigrationImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'migrated_count' => $this->migratedCount,
                'skipped_count' => $this->skippedCount,
                'failed_count' => $this->failedCount,
                'progress_data' => $this->buildProgressData(0, 0, "Failed: {$e->getMessage()}"),
            ]);

            try {
                MigrationFailed::dispatch($this->import->id, $e->getMessage());
            } catch (\Throwable) {
                // WebSocket not available
            }
        }
    }

    private function processGroup(array $group, EttsMapper $mapper, DateParser $dateParser): void
    {
        $pr = $group['pr'] ?? null;
        $pos = $group['pos'] ?? [];
        $vchs = $group['vchs'] ?? [];

        // Determine procurement data from PR (or first available transaction)
        $sourceTransaction = $pr ?? $pos[0] ?? $vchs[0] ?? null;
        if (!$sourceTransaction) {
            return;
        }

        // Dedup check: skip if already migrated
        $existing = MigrationRecord::where('migration_import_id', $this->import->id)
            ->where('source_table', 'transactions')
            ->where('source_id', $sourceTransaction->id)
            ->exists();

        if ($existing) {
            $this->skippedCount++;
            return;
        }

        // Parse date
        $dateOfEntry = $dateParser->parse($sourceTransaction->date_of_entry ?? null) ?? now();

        // Map office (ETTS uses offices_id which corresponds to endorsing_offices)
        $endUserId = null;
        if (isset($sourceTransaction->offices_id)) {
            $endUserId = $mapper->mapOffice((int) $sourceTransaction->offices_id, 'endorsing_offices');
        }

        // Map particular (ETTS uses pr_descriptions_id)
        $particularId = null;
        if ($pr && isset($pr->pr_descriptions_id)) {
            $particularId = $mapper->mapParticular((int) $pr->pr_descriptions_id);
        }
        if (!$particularId) {
            $particularId = \App\Models\Particular::firstOrCreate(
                ['description' => 'Uncategorized (ETTS Migration)'],
                ['is_active' => true]
            )->id;
        }

        // Map status (ETTS uses statuses_id)
        $status = isset($sourceTransaction->statuses_id)
            ? $mapper->mapStatus((int) $sourceTransaction->statuses_id)
            : 'Completed';

        // Create Procurement
        $procurement = Procurement::create([
            'end_user_id' => $endUserId ?? \App\Models\Office::first()?->id,
            'particular_id' => $particularId,
            'purpose' => $sourceTransaction->description ?? null,
            'abc_amount' => $sourceTransaction->amount ?? 0,
            'date_of_entry' => $dateOfEntry,
            'status' => $status,
            'created_by_user_id' => \App\Models\User::first()?->id ?? 1,
            'is_legacy' => true,
        ]);

        MigrationRecord::create([
            'migration_import_id' => $this->import->id,
            'target_table' => 'procurements',
            'target_id' => $procurement->id,
            'source_table' => 'transactions',
            'source_id' => $sourceTransaction->id,
            'source_snapshot' => (array) $sourceTransaction,
            'status' => 'created',
        ]);

        $this->migratedCount++;

        // Create PR transaction
        if ($pr) {
            $this->createTransaction($procurement, $pr, 'PR', $mapper, $dateParser);
        }

        // Create PO transactions
        foreach ($pos as $po) {
            $this->createTransaction($procurement, $po, 'PO', $mapper, $dateParser);
        }

        // Create VCH transactions
        foreach ($vchs as $vch) {
            $this->createTransaction($procurement, $vch, 'VCH', $mapper, $dateParser);
        }
    }

    private function createTransaction(
        Procurement $procurement,
        object $source,
        string $category,
        EttsMapper $mapper,
        DateParser $dateParser,
    ): void {
        $status = isset($source->statuses_id)
            ? $mapper->mapStatus((int) $source->statuses_id)
            : 'Completed';

        $referenceNumber = $this->normalizeReferenceNumber(
            $source->reference_id ?? '',
            $category,
            $source->id,
        );

        // Ensure reference number is unique (ETTS may have duplicates across categories)
        $referenceNumber = $this->ensureUniqueReference($referenceNumber, $source->id);

        $transaction = Transaction::create([
            'procurement_id' => $procurement->id,
            'category' => $category,
            'reference_number' => $referenceNumber,
            'is_continuation' => false,
            'status' => $status,
            'created_by_user_id' => \App\Models\User::first()?->id ?? 1,
            'is_legacy' => true,
        ]);

        MigrationRecord::create([
            'migration_import_id' => $this->import->id,
            'target_table' => 'transactions',
            'target_id' => $transaction->id,
            'source_table' => 'transactions',
            'source_id' => $source->id,
            'source_snapshot' => (array) $source,
            'status' => 'created',
        ]);

        $this->migratedCount++;

        // Create category-specific detail record
        match ($category) {
            'PR' => $this->createPurchaseRequest($transaction, $source, $mapper),
            'PO' => $this->createPurchaseOrder($transaction, $source),
            'VCH' => $this->createVoucher($transaction, $source),
        };
    }

    private function createPurchaseRequest(Transaction $transaction, object $source, EttsMapper $mapper): void
    {
        $fundTypeId = isset($source->reference_id)
            ? $mapper->mapFundType($source->reference_id)
            : null;

        if (!$fundTypeId) {
            $fundTypeId = \App\Models\FundType::first()?->id ?? 1;
        }

        PurchaseRequest::create([
            'transaction_id' => $transaction->id,
            'fund_type_id' => $fundTypeId,
        ]);
    }

    private function createPurchaseOrder(Transaction $transaction, object $source): void
    {
        $supplierId = \App\Models\Supplier::firstOrCreate(
            ['name' => 'Unknown Supplier (ETTS Legacy)'],
            [
                'address' => 'N/A',
                'contact_person' => null,
                'contact_number' => null,
                'is_active' => true,
            ]
        )->id;

        PurchaseOrder::create([
            'transaction_id' => $transaction->id,
            'supplier_id' => $supplierId,
            'supplier_address' => $source->address ?? 'N/A',
            'contract_price' => $source->amount ?? 0,
        ]);
    }

    private function createVoucher(Transaction $transaction, object $source): void
    {
        Voucher::create([
            'transaction_id' => $transaction->id,
            'payee' => $source->client ?? 'Unknown Payee (ETTS)',
        ]);
    }

    private function migrateEndorsements(EttsMapper $mapper, DateParser $dateParser): void
    {
        try {
            $endorsements = DB::connection('etts_temp')->table('endorsements')->get();

            foreach ($endorsements as $endorsement) {
                $migrationRecord = MigrationRecord::where('migration_import_id', $this->import->id)
                    ->where('source_table', 'transactions')
                    ->where('source_id', $endorsement->transactions_id ?? 0)
                    ->where('target_table', 'transactions')
                    ->where('status', 'created')
                    ->first();

                if (!$migrationRecord) {
                    continue;
                }

                $fromOfficeId = isset($endorsement->endorsing_offices_id)
                    ? $mapper->mapOffice((int) $endorsement->endorsing_offices_id, 'endorsing_offices')
                    : null;
                $toOfficeId = isset($endorsement->receiving_offices_id)
                    ? $mapper->mapOffice((int) $endorsement->receiving_offices_id, 'receiving_offices')
                    : null;

                DB::table('transaction_actions')->insert([
                    'transaction_id' => $migrationRecord->target_id,
                    'action_type' => 'endorse',
                    'from_office_id' => $fromOfficeId,
                    'to_office_id' => $toOfficeId,
                    'from_user_id' => \App\Models\User::first()?->id ?? 1,
                    'is_out_of_workflow' => false,
                    'created_at' => $dateParser->parse($endorsement->date_endorsed ?? $endorsement->created_at ?? null) ?? now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Endorsement migration partially failed', ['error' => $e->getMessage()]);
        }
    }

    private function migrateEvents(EttsMapper $mapper, DateParser $dateParser): void
    {
        try {
            $events = DB::connection('etts_temp')->table('events')->get();

            foreach ($events as $event) {
                $migrationRecord = MigrationRecord::where('migration_import_id', $this->import->id)
                    ->where('source_table', 'transactions')
                    ->where('source_id', $event->transactions_id ?? 0)
                    ->where('target_table', 'transactions')
                    ->where('status', 'created')
                    ->first();

                if (!$migrationRecord) {
                    continue;
                }

                DB::table('transaction_actions')->insert([
                    'transaction_id' => $migrationRecord->target_id,
                    'action_type' => 'endorse',
                    'from_user_id' => \App\Models\User::first()?->id ?? 1,
                    'notes' => $event->report ?? null,
                    'is_out_of_workflow' => false,
                    'created_at' => $dateParser->parse($event->created_at ?? null) ?? now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Event migration partially failed', ['error' => $e->getMessage()]);
        }
    }

    private function updateCurrentOfficeIds(EttsMapper $mapper): void
    {
        $migrationRecords = MigrationRecord::where('migration_import_id', $this->import->id)
            ->where('target_table', 'transactions')
            ->where('status', 'created')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($migrationRecords as $record) {
            $transaction = Transaction::find($record->target_id);
            if (! $transaction) {
                $skipped++;

                continue;
            }

            // Find the latest endorsement action with a destination office
            $lastEndorsement = DB::table('transaction_actions')
                ->where('transaction_id', $transaction->id)
                ->where('action_type', 'endorse')
                ->whereNotNull('to_office_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($lastEndorsement) {
                $transaction->update([
                    'current_office_id' => $lastEndorsement->to_office_id,
                ]);
                $updated++;
            } else {
                // Fallback: use the ETTS originating office from source_snapshot
                $sourceSnapshot = $record->source_snapshot;
                $ettsOfficeId = $sourceSnapshot['offices_id'] ?? null;

                if ($ettsOfficeId) {
                    $optsOfficeId = $mapper->mapOffice((int) $ettsOfficeId, 'endorsing_offices');
                    if ($optsOfficeId) {
                        $transaction->update([
                            'current_office_id' => $optsOfficeId,
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }
        }

        $this->logProgress("Updated current office for {$updated} transactions ({$skipped} skipped)");
    }

    private function assignWorkflowsToLegacyTransactions(): void
    {
        $workflowService = app(WorkflowAssignmentService::class);

        // Cache active workflows per category
        $workflows = [];
        foreach (['PR', 'PO', 'VCH'] as $category) {
            $workflows[$category] = $workflowService->getActiveWorkflow($category);
        }

        $migrationRecords = MigrationRecord::where('migration_import_id', $this->import->id)
            ->where('target_table', 'transactions')
            ->where('status', 'created')
            ->get();

        $assigned = 0;
        $noStep = 0;
        $noWorkflow = 0;

        foreach ($migrationRecords as $record) {
            $transaction = Transaction::find($record->target_id);
            if (!$transaction) {
                continue;
            }

            $workflow = $workflows[$transaction->category] ?? null;
            if (!$workflow) {
                $noWorkflow++;
                continue;
            }

            // Find workflow step matching the transaction's current office
            $matchingStep = null;
            if ($transaction->current_office_id) {
                $matchingStep = $workflow->steps()
                    ->where('office_id', $transaction->current_office_id)
                    ->orderBy('step_order')
                    ->first();
            }

            $updateData = [
                'workflow_id' => $workflow->id,
                'current_step_id' => $matchingStep?->id,
                'received_at' => $transaction->received_at ?? $transaction->created_at,
            ];

            $transaction->update($updateData);
            $assigned++;

            if (!$matchingStep) {
                $noStep++;
            }
        }

        $this->logProgress("Assigned workflows: {$assigned} transactions ({$noStep} without matching step, {$noWorkflow} without active workflow)");
    }

    /**
     * Normalize an ETTS reference number:
     * - "PO - 741" or "PO 591" → "PO-591"
     * - "GF - 809" → "GF-809"
     * - Pure numeric "531" for PO → "PO-531"
     * - Empty → fallback "ETTS-{category}-{id}"
     */
    private function normalizeReferenceNumber(string $ref, string $category, int $sourceId): string
    {
        $ref = trim($ref);

        if ($ref === '') {
            return "ETTS-{$category}-{$sourceId}";
        }

        // Collapse whitespace around hyphens: "PO - 741" → "PO-741"
        $ref = preg_replace('/\s*-\s*/', '-', $ref);

        // Handle "PO 591" (prefix followed by space then number) → "PO-591"
        $ref = preg_replace('/^([A-Z]+)\s+(\d+.*)$/', '$1-$2', $ref);

        return $ref;
    }

    /**
     * Ensure the reference number doesn't collide with an existing transaction.
     * ETTS data can have the same reference across different process types.
     */
    private function ensureUniqueReference(string $ref, int $sourceId): string
    {
        if (! Transaction::withTrashed()->where('reference_number', $ref)->exists()) {
            return $ref;
        }

        // Append ETTS source ID to disambiguate
        $unique = "{$ref}-E{$sourceId}";

        // In the unlikely event even that collides, add a counter
        $counter = 2;
        while (Transaction::withTrashed()->where('reference_number', $unique)->exists()) {
            $unique = "{$ref}-E{$sourceId}-{$counter}";
            $counter++;
        }

        return $unique;
    }

    private function logProgress(string $message): void
    {
        $timestamp = now()->format('H:i:s');
        $this->progressLog[] = "[{$timestamp}] {$message}";

        // Keep only the last 100 entries
        if (count($this->progressLog) > 100) {
            $this->progressLog = array_slice($this->progressLog, -100);
        }
    }

    private function buildProgressData(int $current, int $total, string $message = ''): array
    {
        $percentage = $total > 0 ? (int) round(($current / $total) * 100) : 100;

        return [
            'percentage' => $percentage,
            'current' => $current,
            'total' => $total,
            'message' => $message ?: "Processing group {$current} of {$total}...",
            'migrated_count' => $this->migratedCount,
            'skipped_count' => $this->skippedCount,
            'failed_count' => $this->failedCount,
            'log' => $this->progressLog,
        ];
    }

    private function persistProgress(int $current, int $total, ?string $message = null): void
    {
        $this->import->update([
            'migrated_count' => $this->migratedCount,
            'skipped_count' => $this->skippedCount,
            'failed_count' => $this->failedCount,
            'progress_data' => $this->buildProgressData(
                $current,
                $total,
                $message ?? "Processing group {$current} of {$total}...",
            ),
        ]);
    }
}
