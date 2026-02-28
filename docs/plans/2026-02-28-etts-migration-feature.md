# ETTS Data Migration Feature - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an admin UI wizard in OPTS2026 that imports ETTS SQL dumps, maps legacy data, and migrates procurement records with full audit trail preservation.

**Architecture:** SQL dump imported into temporary MySQL database via background job. 5-step wizard UI (Upload > Mappings > Dry Run > Execute > Results). Reference chain resolution groups ETTS PR/PO/VCH into OPTS2026 Procurements. Real-time progress via Laravel Reverb.

**Tech Stack:** Laravel 12 (Jobs, Events, Broadcasting), React 18 + TypeScript + Inertia.js, shadcn/ui, Laravel Reverb (WebSocket), MySQL temp databases.

---

## Task 1: Database Migrations

**Files:**
- Create: `database/migrations/2026_02_28_000001_create_migration_imports_table.php`
- Create: `database/migrations/2026_02_28_000002_create_migration_records_table.php`
- Create: `database/migrations/2026_02_28_000003_add_is_legacy_to_procurements_table.php`
- Create: `database/migrations/2026_02_28_000004_add_is_legacy_to_transactions_table.php`

**Step 1: Create migration_imports table migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('batch_id', 50)->unique();
            $table->string('temp_database', 100)->nullable();
            $table->enum('status', [
                'pending', 'importing', 'analyzing', 'dry_run',
                'migrating', 'completed', 'failed', 'rolled_back',
            ])->default('pending');
            $table->unsignedInteger('total_source_records')->default(0);
            $table->unsignedInteger('migrated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('mapping_data')->nullable();
            $table->json('dry_run_report')->nullable();
            $table->json('validation_report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('imported_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_imports');
    }
};
```

**Step 2: Create migration_records table migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_import_id')
                ->constrained('migration_imports')
                ->cascadeOnDelete();
            $table->string('target_table', 100);
            $table->unsignedBigInteger('target_id');
            $table->string('source_table', 100);
            $table->unsignedBigInteger('source_id');
            $table->json('source_snapshot')->nullable();
            $table->enum('status', ['created', 'skipped', 'failed'])->default('created');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['migration_import_id', 'status']);
            $table->index(['target_table', 'target_id']);
            $table->index(['source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_records');
    }
};
```

**Step 3: Add is_legacy to procurements**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });
    }
};
```

**Step 4: Add is_legacy to transactions**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });
    }
};
```

**Step 5: Run migrations**

Run: `php artisan migrate`
Expected: 4 migrations run successfully

**Step 6: Commit**

```bash
git add database/migrations/2026_02_28_*
git commit -m "feat: add migration tracking tables and is_legacy columns for ETTS import"
```

---

## Task 2: Models

**Files:**
- Create: `app/Models/MigrationImport.php`
- Create: `app/Models/MigrationRecord.php`
- Modify: `app/Models/Procurement.php` - add `is_legacy` to `$fillable` and `casts()`
- Modify: `app/Models/Transaction.php` - add `is_legacy` to `$fillable` and `casts()`
- Modify: `resources/js/types/models.ts` - add new TypeScript interfaces

**Step 1: Create MigrationImport model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationImport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IMPORTING = 'importing';
    public const STATUS_ANALYZING = 'analyzing';
    public const STATUS_DRY_RUN = 'dry_run';
    public const STATUS_MIGRATING = 'migrating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'filename',
        'batch_id',
        'temp_database',
        'status',
        'total_source_records',
        'migrated_count',
        'skipped_count',
        'failed_count',
        'mapping_data',
        'dry_run_report',
        'validation_report',
        'error_message',
        'started_at',
        'completed_at',
        'imported_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'mapping_data' => 'json',
            'dry_run_report' => 'json',
            'validation_report' => 'json',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_source_records' => 'integer',
            'migrated_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(MigrationRecord::class);
    }
}
```

**Step 2: Create MigrationRecord model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationRecord extends Model
{
    protected $fillable = [
        'migration_import_id',
        'target_table',
        'target_id',
        'source_table',
        'source_id',
        'source_snapshot',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'json',
            'target_id' => 'integer',
            'source_id' => 'integer',
        ];
    }

    public function migrationImport(): BelongsTo
    {
        return $this->belongsTo(MigrationImport::class);
    }
}
```

**Step 3: Add is_legacy to Procurement model**

In `app/Models/Procurement.php`, add `'is_legacy'` to `$fillable` array and add `'is_legacy' => 'boolean'` to `casts()`.

**Step 4: Add is_legacy to Transaction model**

In `app/Models/Transaction.php`, add `'is_legacy'` to `$fillable` array and add `'is_legacy' => 'boolean'` to `casts()`.

**Step 5: Add TypeScript interfaces**

In `resources/js/types/models.ts`, add:

```typescript
/**
 * ETTS Migration - Import batch tracking.
 */
export type MigrationImportStatus =
    | 'pending' | 'importing' | 'analyzing' | 'dry_run'
    | 'migrating' | 'completed' | 'failed' | 'rolled_back';

export interface MigrationImport {
    id: number;
    filename: string;
    batch_id: string;
    temp_database: string | null;
    status: MigrationImportStatus;
    total_source_records: number;
    migrated_count: number;
    skipped_count: number;
    failed_count: number;
    mapping_data: MigrationMappingData | null;
    dry_run_report: MigrationDryRunReport | null;
    validation_report: MigrationValidationReport | null;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    imported_by_user_id: number;
    created_at: string;
    updated_at: string;
    imported_by?: User;
}

export interface MigrationMappingData {
    offices: MigrationMappingEntry[];
    users: MigrationMappingEntry[];
    particulars: MigrationMappingEntry[];
    action_taken: MigrationMappingEntry[];
    source_counts: {
        transactions: number;
        endorsements: number;
        events: number;
        users: number;
        offices: number;
    };
}

export interface MigrationMappingEntry {
    source_id: number;
    source_name: string;
    target_id: number | null;
    target_name: string | null;
    status: 'matched' | 'unmatched' | 'new';
}

export interface MigrationDryRunReport {
    procurements_to_create: number;
    transactions_to_create: { pr: number; po: number; vch: number };
    records_to_skip: number;
    orphaned_pos: number;
    orphaned_vchs: number;
    unparseable_dates: number;
    financial_totals: { pr_amount: number; po_amount: number; vch_amount: number };
    warnings: string[];
}

export interface MigrationValidationReport {
    counts: { source: number; created: number; skipped: number; failed: number };
    financial_reconciliation: { etts_total: number; opts_total: number; difference: number };
    orphans: { pos: number; vchs: number };
    integrity_errors: string[];
}

export interface MigrationProgressUpdate {
    import_id: number;
    current: number;
    total: number;
    percentage: number;
    message: string;
    migrated_count: number;
    skipped_count: number;
}
```

Also add `is_legacy?: boolean;` to the `Procurement` and `Transaction` interfaces.

**Step 6: Run tests to verify no regressions**

Run: `php artisan test`
Expected: All existing tests pass

**Step 7: Commit**

```bash
git add app/Models/MigrationImport.php app/Models/MigrationRecord.php app/Models/Procurement.php app/Models/Transaction.php resources/js/types/models.ts
git commit -m "feat: add MigrationImport and MigrationRecord models, is_legacy flag"
```

---

## Task 3: Migration Services

**Files:**
- Create: `app/Services/Migration/DateParser.php`
- Create: `app/Services/Migration/EttsMapper.php`
- Create: `app/Services/Migration/ReferenceChainResolver.php`
- Create: `app/Services/Migration/MigrationReportService.php`
- Create: `config/etts_migration.php`

**Step 1: Create config/etts_migration.php**

Contains all default mapping arrays for offices, statuses, roles. The office mapping maps ETTS `endorsing_offices`/`receiving_offices` abbreviations to OPTS2026 office abbreviations. See design doc for full mapping table.

Key mappings:
- `office_mapping`: 34 ETTS office abbrs → OPTS2026 office abbrs (19 direct, 8→MMO, 5 new inactive, 1 supplier)
- `status_mapping`: [1=>'Created', 2=>'In Progress', 3=>'Cancelled', 4=>'Completed']
- `role_mapping`: ['Guest'=>'Viewer', 'Standard'=>'Endorser', 'Administrator'=>'Administrator']
- `unmapped_offices`: offices to create as inactive
- `fund_type_prefixes`: ['GF', 'TF', 'SEF'] for parsing PR reference numbers

**Step 2: Create DateParser service**

`app/Services/Migration/DateParser.php` - Tries 8+ date formats on ETTS VARCHAR dates, returns Carbon or null. Logs unparseable values.

**Step 3: Create EttsMapper service**

`app/Services/Migration/EttsMapper.php` - Resolves office/user/particular/status/action_taken mappings. Uses config arrays + database lookups. Caches resolved IDs for performance.

Key methods:
- `mapOffice(int $ettsOfficeId, string $sourceTable): ?int` - Maps ETTS office ID (from endorsing_offices or receiving_offices table) to OPTS2026 office ID
- `mapUser(int $ettsUserId): ?int` - Maps ETTS user to OPTS2026 user by email match
- `mapParticular(int $ettsPrDescriptionId): int` - Maps pr_description to particular
- `mapStatus(int $ettsStatusId): string` - Maps status ID to enum string
- `mapActionTaken(int $ettsActionTakenId): ?int` - Maps action_taken by description match
- `mapFundType(string $referenceId): ?int` - Parses fund type from PR reference_id prefix

**Step 4: Create ReferenceChainResolver service**

`app/Services/Migration/ReferenceChainResolver.php` - Groups ETTS transactions into procurement groups.

Key method: `resolve(Collection $ettsTransactions): array`

Algorithm:
1. Index PRs by `reference_id` → HashMap
2. For each PO: lookup `sub_reference_id` in PR index → link
3. For each VCH: lookup `sub_reference_id` in PO index → link
4. Return array of groups: `[['pr' => $pr, 'pos' => [...], 'vchs' => [...]], ...]`
5. Orphans get their own groups with null pr/pos

**Step 5: Create MigrationReportService**

`app/Services/Migration/MigrationReportService.php` - Generates validation reports.

Key methods:
- `generateDryRunReport(array $groups, EttsMapper $mapper): array`
- `generateValidationReport(MigrationImport $import): array`
- `generateFinancialReconciliation(MigrationImport $import): array`

**Step 6: Commit**

```bash
git add app/Services/Migration/ config/etts_migration.php
git commit -m "feat: add ETTS migration services - DateParser, EttsMapper, ReferenceChainResolver"
```

---

## Task 4: Reverb Broadcasting Events

**Files:**
- Create: `app/Events/Migration/MigrationProgress.php`
- Create: `app/Events/Migration/MigrationCompleted.php`
- Create: `app/Events/Migration/MigrationFailed.php`

**Step 1: Create MigrationProgress event**

```php
<?php

declare(strict_types=1);

namespace App\Events\Migration;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MigrationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $importId,
        public int $current,
        public int $total,
        public int $percentage,
        public string $message,
        public int $migratedCount,
        public int $skippedCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("migration.{$this->importId}")];
    }
}
```

**Step 2: Create MigrationCompleted event** (same pattern, no data payload needed beyond importId)

**Step 3: Create MigrationFailed event** (includes error message)

**Step 4: Add channel authorization**

In `routes/channels.php`:
```php
Broadcast::channel('migration.{importId}', function ($user) {
    return $user->hasRole('Administrator');
});
```

**Step 5: Commit**

```bash
git add app/Events/Migration/ routes/channels.php
git commit -m "feat: add Reverb broadcast events for migration progress"
```

---

## Task 5: Background Jobs

**Files:**
- Create: `app/Jobs/Migration/ImportSqlJob.php`
- Create: `app/Jobs/Migration/AnalyzeMappingsJob.php`
- Create: `app/Jobs/Migration/DryRunMigrationJob.php`
- Create: `app/Jobs/Migration/ExecuteMigrationJob.php`

**Step 1: Create ImportSqlJob**

Responsibilities:
1. Update import status to `importing`
2. Create temp database `etts_import_{batch_id}`
3. Run `mysql` CLI: `mysql -u USER -pPASS TEMP_DB < /path/to/dump.sql`
4. Validate ETTS tables exist (transactions, endorsements, events, etc.)
5. Count source records, store in `total_source_records`
6. Update status to `analyzing`
7. Dispatch `AnalyzeMappingsJob`

Error handling: catch exceptions, update status to `failed`, store error_message.

**Step 2: Create AnalyzeMappingsJob**

Responsibilities:
1. Connect to temp database using dynamic config
2. Read ETTS offices (endorsing_offices, receiving_offices), users, pr_descriptions, action_takens
3. Auto-map each against OPTS2026 data using EttsMapper
4. Store mapping results in `migration_imports.mapping_data` as JSON
5. Update status to `analyzing` (ready for review)

**Step 3: Create DryRunMigrationJob**

Responsibilities:
1. Read confirmed mappings from `migration_imports.mapping_data`
2. Use ReferenceChainResolver to group ETTS transactions
3. For each group, simulate: check dedup (composite key), count creates/skips
4. Generate dry run report, store in `migration_imports.dry_run_report`
5. Update status to `dry_run`

**Step 4: Create ExecuteMigrationJob**

This is the core job. Responsibilities:
1. Update status to `migrating`, set `started_at`
2. Chunk procurement groups (500 per chunk)
3. For each group, in a DB transaction:
   a. Check dedup (reference_id + date_of_entry + category)
   b. Create Procurement with `is_legacy = true`
   c. Create Transaction(s) with `is_legacy = true`
   d. Create PurchaseRequest / PurchaseOrder / Voucher detail records
   e. Create MigrationRecord entries for each created record
   f. Broadcast MigrationProgress event
4. After all groups: migrate endorsements → transaction_actions
5. Migrate events → transaction_actions (with notes)
6. Create status history entries
7. Update reference_sequences table
8. Generate validation report
9. Update status to `completed`, set `completed_at`
10. Broadcast MigrationCompleted event

**Step 5: Commit**

```bash
git add app/Jobs/Migration/
git commit -m "feat: add background jobs for ETTS import pipeline"
```

---

## Task 6: Controller & Routes

**Files:**
- Create: `app/Http/Controllers/Admin/MigrationController.php`
- Modify: `routes/web.php`

**Step 1: Create MigrationController**

10 methods following existing admin controller patterns:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Migration\AnalyzeMappingsJob;
use App\Jobs\Migration\DryRunMigrationJob;
use App\Jobs\Migration\ExecuteMigrationJob;
use App\Jobs\Migration\ImportSqlJob;
use App\Models\MigrationImport;
use App\Models\MigrationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MigrationController extends Controller
{
    public function index(): Response; // List all imports
    public function upload(Request $request): RedirectResponse; // Handle file upload
    public function mappings(MigrationImport $import): Response; // Show mappings
    public function saveMappings(Request $request, MigrationImport $import): RedirectResponse;
    public function dryRun(MigrationImport $import): RedirectResponse; // Dispatch dry run
    public function dryRunResults(MigrationImport $import): Response; // Show results
    public function execute(MigrationImport $import): RedirectResponse; // Dispatch migration
    public function progress(MigrationImport $import): Response; // Show progress page
    public function results(MigrationImport $import): Response; // Show final results
    public function rollback(MigrationImport $import): RedirectResponse; // Rollback import
}
```

**Step 2: Add routes to routes/web.php**

Inside the existing `middleware(['auth', 'role:Administrator'])` group:

```php
// ETTS Data Migration
Route::get('admin/migration', [App\Http\Controllers\Admin\MigrationController::class, 'index'])
    ->name('admin.migration.index');
Route::post('admin/migration/upload', [App\Http\Controllers\Admin\MigrationController::class, 'upload'])
    ->name('admin.migration.upload');
Route::get('admin/migration/{import}/mappings', [App\Http\Controllers\Admin\MigrationController::class, 'mappings'])
    ->name('admin.migration.mappings');
Route::post('admin/migration/{import}/mappings', [App\Http\Controllers\Admin\MigrationController::class, 'saveMappings'])
    ->name('admin.migration.save-mappings');
Route::post('admin/migration/{import}/dry-run', [App\Http\Controllers\Admin\MigrationController::class, 'dryRun'])
    ->name('admin.migration.dry-run');
Route::get('admin/migration/{import}/dry-run', [App\Http\Controllers\Admin\MigrationController::class, 'dryRunResults'])
    ->name('admin.migration.dry-run-results');
Route::post('admin/migration/{import}/execute', [App\Http\Controllers\Admin\MigrationController::class, 'execute'])
    ->name('admin.migration.execute');
Route::get('admin/migration/{import}/progress', [App\Http\Controllers\Admin\MigrationController::class, 'progress'])
    ->name('admin.migration.progress');
Route::get('admin/migration/{import}/results', [App\Http\Controllers\Admin\MigrationController::class, 'results'])
    ->name('admin.migration.results');
Route::post('admin/migration/{import}/rollback', [App\Http\Controllers\Admin\MigrationController::class, 'rollback'])
    ->name('admin.migration.rollback');
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/MigrationController.php routes/web.php
git commit -m "feat: add MigrationController and routes for ETTS import wizard"
```

---

## Task 7: Frontend Pages - Index & Upload (Steps 1)

**Files:**
- Create: `resources/js/Pages/Admin/Migration/Index.tsx`
- Create: `resources/js/Pages/Admin/Migration/Upload.tsx`

**Step 1: Create Index page**

Dashboard showing past imports in a DataTable:
- Columns: Filename, Batch ID, Status (badge), Records (migrated/skipped/failed), Date, Actions
- "New Import" button linking to Upload page
- Status badges: green=completed, yellow=migrating, red=failed, gray=rolled_back

Uses: `AuthenticatedLayout`, `DataTable`, `Badge`, `Button`

**Step 2: Create Upload page**

Simple form:
- File input accepting `.sql` files
- "Upload & Start Import" button
- Processing state while uploading
- Redirects to Mappings page on success

Uses: `useForm()`, `Input`, `Button`, `Card`

**Step 3: Commit**

```bash
git add resources/js/Pages/Admin/Migration/Index.tsx resources/js/Pages/Admin/Migration/Upload.tsx
git commit -m "feat: add Migration Index and Upload pages"
```

---

## Task 8: Frontend Pages - Mappings (Step 2)

**Files:**
- Create: `resources/js/Pages/Admin/Migration/Mappings.tsx`

**Step 1: Create Mappings page**

Accordion sections for each mapping category:
- **Offices**: Table showing ETTS office name → OPTS2026 office dropdown (Select component). Green checkmark for matched, yellow warning for unmatched.
- **Users**: Table showing ETTS user email → matched/new status. Count badges.
- **Particulars**: Table showing matched count and new items to create.
- **Action Taken**: Table showing all matched items.
- Source counts summary card at top.
- "Save Mappings & Run Dry Run" button at bottom.

Uses: `Accordion`, `Select`, `Badge`, `Button`, `Card`, `Table`

**Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Migration/Mappings.tsx
git commit -m "feat: add Migration Mappings review page"
```

---

## Task 9: Frontend Pages - Dry Run (Step 3)

**Files:**
- Create: `resources/js/Pages/Admin/Migration/DryRun.tsx`

**Step 1: Create DryRun results page**

Shows the dry run report with:
- Summary cards: Procurements to create, Transactions by type, Records to skip
- Orphan warnings (POs without PR, VCHs without PO) in Alert component
- Financial totals table
- Warnings list
- Two buttons: "Go Back to Mappings" and "Execute Migration"

Uses: `Card`, `Alert`, `Table`, `Badge`, `Button`

**Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Migration/DryRun.tsx
git commit -m "feat: add Migration Dry Run results page"
```

---

## Task 10: Frontend Pages - Progress (Step 4)

**Files:**
- Create: `resources/js/Pages/Admin/Migration/Progress.tsx`

**Step 1: Create Progress page with Reverb WebSocket**

Real-time progress display:
- Progress bar component showing percentage
- Live log area with scrolling messages
- Running counters: Migrated, Skipped, Failed
- Cancel button
- Auto-redirect to Results page when MigrationCompleted event fires

WebSocket listener using Echo:
```typescript
useEffect(() => {
    const channel = window.Echo.private(`migration.${import.id}`);
    channel.listen('MigrationProgress', (data: MigrationProgressUpdate) => {
        setProgress(data);
    });
    channel.listen('MigrationCompleted', () => {
        router.visit(route('admin.migration.results', import.id));
    });
    channel.listen('MigrationFailed', (data: { message: string }) => {
        setError(data.message);
    });
    return () => channel.stopListening();
}, [import.id]);
```

Uses: `Progress`, `Card`, `Button`, `Alert`

**Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Migration/Progress.tsx
git commit -m "feat: add Migration Progress page with Reverb WebSocket"
```

---

## Task 11: Frontend Pages - Results (Step 5)

**Files:**
- Create: `resources/js/Pages/Admin/Migration/Results.tsx`

**Step 1: Create Results page**

Final validation results:
- Summary cards: Total created, skipped, failed
- Financial reconciliation table (ETTS total vs OPTS total, difference)
- Integrity check results (green checks or red warnings)
- Orphan report expandable section
- "Rollback This Import" button with AlertDialog confirmation
- "Download Report" button
- Link back to Index

Uses: `Card`, `Table`, `AlertDialog`, `Badge`, `Button`, `Alert`

**Step 2: Commit**

```bash
git add resources/js/Pages/Admin/Migration/Results.tsx
git commit -m "feat: add Migration Results page with rollback option"
```

---

## Task 12: Navigation & Legacy Badge

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx` - add Migration nav link under Admin
- Modify: Procurement/Transaction list pages - show `[ETTS]` badge for `is_legacy` records

**Step 1: Add Migration link to admin navigation**

In the existing admin dropdown/sidebar, add "Data Migration" link to `route('admin.migration.index')`.

**Step 2: Add legacy badge to procurement/transaction displays**

Where reference numbers are displayed, check `is_legacy` and show a small `<Badge variant="outline">ETTS</Badge>` next to it.

**Step 3: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.tsx
git commit -m "feat: add Migration nav link and ETTS legacy badge"
```

---

## Task 13: Integration Testing

**Files:**
- Create: `tests/Feature/Migration/MigrationControllerTest.php`
- Create: `tests/Unit/Migration/ReferenceChainResolverTest.php`
- Create: `tests/Unit/Migration/DateParserTest.php`
- Create: `tests/Unit/Migration/EttsMapperTest.php`

**Step 1: Write ReferenceChainResolver unit tests**

Test cases:
- PR+PO+VCH chain resolves correctly
- Orphaned PO creates standalone group
- Orphaned VCH creates standalone group
- Multiple POs linked to same PR
- Empty collection returns empty groups

**Step 2: Write DateParser unit tests**

Test cases:
- Parses `Y-m-d` format
- Parses `m/d/Y` format
- Returns null for empty string
- Returns null for garbage string
- Handles whitespace

**Step 3: Write EttsMapper unit tests**

Test cases:
- Maps known office abbreviation
- Returns null for unknown office
- Maps status IDs correctly
- Maps fund type from reference prefix

**Step 4: Write MigrationController feature tests**

Test cases:
- Index page loads for admin
- Index page blocked for non-admin
- Upload stores file and creates import record
- Rollback deletes created records

**Step 5: Run all tests**

Run: `php artisan test`
Expected: All tests pass

**Step 6: Commit**

```bash
git add tests/Feature/Migration/ tests/Unit/Migration/
git commit -m "test: add unit and feature tests for ETTS migration"
```

---

## Task 14: Final Verification

**Step 1: Run full test suite**

Run: `php artisan test`
Expected: All tests pass

**Step 2: Run npm build**

Run: `npm run build`
Expected: No TypeScript errors, build succeeds

**Step 3: Manual verification**

1. Start dev server: `php artisan serve` + `npm run dev`
2. Log in as Administrator
3. Navigate to Settings > Data Migration
4. Verify empty state shows "No imports yet" message
5. Verify Upload page renders with file input
6. Verify all routes respond correctly

**Step 4: Commit any remaining changes**

```bash
git add -A
git commit -m "feat: complete ETTS data migration feature"
```
