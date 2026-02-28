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
        'exclude_orphans',
        'progress_data',
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
            'exclude_orphans' => 'boolean',
            'progress_data' => 'json',
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
