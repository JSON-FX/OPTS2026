<?php

declare(strict_types=1);

namespace App\Jobs\Migration;

use App\Events\Migration\MigrationFailed;
use App\Models\MigrationImport;
use App\Services\Migration\EttsMapper;
use App\Services\Migration\MigrationReportService;
use App\Services\Migration\ReferenceChainResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DryRunMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public MigrationImport $import,
    ) {}

    public function handle(): void
    {
        try {
            // Configure temp database connection
            config([
                'database.connections.etts_temp' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $this->import->temp_database]
                ),
            ]);

            $mapper = new EttsMapper($this->import->temp_database);
            $resolver = new ReferenceChainResolver();
            $reportService = new MigrationReportService();

            // Get all ETTS transactions
            $ettsTransactions = DB::connection('etts_temp')
                ->table('transactions')
                ->get();

            // Group into procurement chains
            $groups = $resolver->resolve($ettsTransactions);

            // Generate dry run report
            $report = $reportService->generateDryRunReport($groups, $mapper);

            $this->import->update([
                'dry_run_report' => $report,
                'status' => MigrationImport::STATUS_DRY_RUN,
            ]);

        } catch (\Throwable $e) {
            Log::error('DryRunMigrationJob failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            $this->import->update([
                'status' => MigrationImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            MigrationFailed::dispatch($this->import->id, $e->getMessage());
        }
    }
}
