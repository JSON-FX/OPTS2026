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
