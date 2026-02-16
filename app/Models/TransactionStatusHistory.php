<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransactionStatusHistory model for logging transaction status changes.
 *
 * Story 3.6 - Complete Action Implementation
 *
 * @property int $id
 * @property int $transaction_id
 * @property string|null $old_status
 * @property string $new_status
 * @property string|null $reason
 * @property int $changed_by_user_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TransactionStatusHistory extends Model
{
    protected $table = 'transaction_status_history';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'old_status',
        'new_status',
        'reason',
        'changed_by_user_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
