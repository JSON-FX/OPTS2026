<?php

declare(strict_types=1);

namespace App\Jobs\Migration;

use App\Events\Migration\MigrationFailed;
use App\Models\MigrationImport;
use App\Services\Migration\EttsMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeMappingsJob implements ShouldQueue
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
            $mappingData = $mapper->autoMapAll();

            $this->import->update([
                'mapping_data' => $mappingData,
                'status' => MigrationImport::STATUS_ANALYZING,
            ]);

        } catch (\Throwable $e) {
            Log::error('AnalyzeMappingsJob failed', [
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
