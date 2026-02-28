<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Migration\DryRunMigrationJob;
use App\Jobs\Migration\ExecuteMigrationJob;
use App\Jobs\Migration\ImportSqlJob;
use App\Models\MigrationImport;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MigrationController extends Controller
{
    public function index(): Response
    {
        $imports = MigrationImport::with('importedBy')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Migration/Index', [
            'imports' => $imports,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'sql_file' => ['required', 'file', 'max:524288', function (string $attribute, mixed $value, \Closure $fail) {
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['sql', 'txt'])) {
                    $fail('The SQL file must be a .sql or .txt file.');
                }
            }],
        ]);

        $file = $request->file('sql_file');
        $filename = $file->getClientOriginalName();
        $batchId = Str::uuid()->toString();

        // Store the file
        $file->storeAs('migration_uploads', $filename, 'local');

        $import = MigrationImport::create([
            'filename' => $filename,
            'batch_id' => $batchId,
            'status' => MigrationImport::STATUS_PENDING,
            'imported_by_user_id' => $request->user()->id,
        ]);

        // Dispatch import job
        ImportSqlJob::dispatch($import);

        return redirect()->route('admin.migration.mappings', $import)
            ->with('success', 'SQL file uploaded. Import is being processed...');
    }

    public function mappings(MigrationImport $import): Response
    {
        $import->load('importedBy');

        $offices = Office::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation']);

        return Inertia::render('Admin/Migration/Mappings', [
            'import' => $import,
            'offices' => $offices,
        ]);
    }

    public function saveMappings(Request $request, MigrationImport $import): RedirectResponse
    {
        $request->validate([
            'mapping_data' => 'required|array',
        ]);

        $import->update([
            'mapping_data' => $request->input('mapping_data'),
        ]);

        // Dispatch dry run
        DryRunMigrationJob::dispatch($import);

        return redirect()->route('admin.migration.dry-run-results', $import)
            ->with('success', 'Mappings saved. Running dry run...');
    }

    public function dryRun(MigrationImport $import): RedirectResponse
    {
        DryRunMigrationJob::dispatch($import);

        return redirect()->route('admin.migration.dry-run-results', $import)
            ->with('success', 'Dry run started...');
    }

    public function dryRunResults(MigrationImport $import): Response
    {
        $import->load('importedBy');

        return Inertia::render('Admin/Migration/DryRun', [
            'import' => $import,
        ]);
    }

    public function execute(Request $request, MigrationImport $import): RedirectResponse
    {
        if ($import->status !== MigrationImport::STATUS_DRY_RUN) {
            return back()->with('error', 'Migration can only be executed after a dry run.');
        }

        $import->update([
            'exclude_orphans' => $request->boolean('exclude_orphans', true),
        ]);

        ExecuteMigrationJob::dispatch($import);

        return redirect()->route('admin.migration.progress', $import)
            ->with('success', 'Migration started...');
    }

    public function progress(MigrationImport $import): Response
    {
        $import->load('importedBy');

        return Inertia::render('Admin/Migration/Progress', [
            'import' => $import,
        ]);
    }

    public function results(MigrationImport $import): Response
    {
        $import->load('importedBy');

        return Inertia::render('Admin/Migration/Results', [
            'import' => $import,
        ]);
    }

    public function rollback(MigrationImport $import): RedirectResponse
    {
        if (! in_array($import->status, [MigrationImport::STATUS_COMPLETED, MigrationImport::STATUS_FAILED])) {
            return back()->with('error', 'Only completed or failed imports can be rolled back.');
        }

        // Delete all created records in reverse order
        $records = $import->records()
            ->where('status', 'created')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($records as $record) {
            try {
                \Illuminate\Support\Facades\DB::table($record->target_table)
                    ->where('id', $record->target_id)
                    ->delete();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Rollback: Could not delete record', [
                    'table' => $record->target_table,
                    'id' => $record->target_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clean up temp database
        if ($import->temp_database) {
            try {
                \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS `{$import->temp_database}`");
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Rollback: Could not drop temp database', [
                    'database' => $import->temp_database,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $import->update([
            'status' => MigrationImport::STATUS_ROLLED_BACK,
        ]);

        return redirect()->route('admin.migration.index')
            ->with('success', 'Import has been rolled back successfully.');
    }

    public function clearAllProcurements(): RedirectResponse
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $deleted = [
                'purchase_requests' => DB::table('purchase_requests')->count(),
                'purchase_orders' => DB::table('purchase_orders')->count(),
                'vouchers' => DB::table('vouchers')->count(),
                'transaction_actions' => DB::table('transaction_actions')->count(),
                'transactions' => DB::table('transactions')->count(),
                'procurements' => DB::table('procurements')->count(),
                'migration_records' => DB::table('migration_records')->count(),
            ];

            DB::table('purchase_requests')->truncate();
            DB::table('purchase_orders')->truncate();
            DB::table('vouchers')->truncate();
            DB::table('transaction_actions')->truncate();
            DB::table('transactions')->truncate();
            DB::table('procurements')->truncate();
            DB::table('migration_records')->truncate();

            // Reset migration imports to rolled_back status
            MigrationImport::whereIn('status', [
                MigrationImport::STATUS_COMPLETED,
                MigrationImport::STATUS_FAILED,
            ])->update(['status' => MigrationImport::STATUS_ROLLED_BACK]);

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $total = array_sum($deleted);

            Log::info('Clear all procurements executed', $deleted);

            return redirect()->route('admin.migration.index')
                ->with('success', "Cleared all procurement data ({$total} records deleted).");
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            Log::error('Clear all procurements failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Failed to clear data: ' . $e->getMessage());
        }
    }
}
