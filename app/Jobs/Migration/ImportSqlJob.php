<?php

declare(strict_types=1);

namespace App\Jobs\Migration;

use App\Events\Migration\MigrationFailed;
use App\Models\MigrationImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ImportSqlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public MigrationImport $import,
    ) {}

    public function handle(): void
    {
        try {
            $this->import->update(['status' => MigrationImport::STATUS_IMPORTING]);

            $tempDb = config('etts_migration.temp_db_prefix') . $this->import->batch_id;

            // Create temp database
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$tempDb}`");

            $this->import->update(['temp_database' => $tempDb]);

            // Import SQL dump via PDO (avoids MariaDB client compat issues)
            $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path("migration_uploads/{$this->import->filename}");

            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new \RuntimeException("Could not read SQL file: {$filePath}");
            }

            // Configure temp database connection
            config([
                'database.connections.etts_temp' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $tempDb]
                ),
            ]);

            DB::connection('etts_temp')->unprepared($sql);

            // Validate ETTS tables exist
            $requiredTables = ['transactions'];
            foreach ($requiredTables as $table) {
                if (!DB::connection('etts_temp')->getSchemaBuilder()->hasTable($table)) {
                    throw new \RuntimeException("Required ETTS table '{$table}' not found in SQL dump.");
                }
            }

            // Count source records
            $totalRecords = DB::connection('etts_temp')->table('transactions')->count();

            $this->import->update([
                'total_source_records' => $totalRecords,
                'status' => MigrationImport::STATUS_ANALYZING,
            ]);

            // Dispatch next job
            AnalyzeMappingsJob::dispatch($this->import);

        } catch (\Throwable $e) {
            Log::error('ImportSqlJob failed', [
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
