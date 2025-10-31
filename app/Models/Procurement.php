<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procurement extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_CREATED = 'Created';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_ON_HOLD = 'On Hold';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ON_HOLD,
        self::STATUS_CANCELLED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'end_user_id',
        'particular_id',
        'purpose',
        'abc_amount',
        'date_of_entry',
        'status',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_entry' => 'date',
            'abc_amount' => 'decimal:2',
        ];
    }

    public function endUser(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'end_user_id');
    }

    public function particular(): BelongsTo
    {
        return $this->belongsTo(Particular::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProcurementStatusHistory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function purchaseRequest(): HasOneThrough
    {
        return $this->hasOneThrough(
            PurchaseRequest::class,
            Transaction::class,
            'procurement_id',
            'transaction_id'
        )->where('transactions.category', Transaction::CATEGORY_PURCHASE_REQUEST);
    }

    public function purchaseOrder(): HasOneThrough
    {
        return $this->hasOneThrough(
            PurchaseOrder::class,
            Transaction::class,
            'procurement_id',
            'transaction_id'
        )->where('transactions.category', Transaction::CATEGORY_PURCHASE_ORDER);
    }

    public function voucher(): HasOneThrough
    {
        return $this->hasOneThrough(
            Voucher::class,
            Transaction::class,
            'procurement_id',
            'transaction_id'
        )->where('transactions.category', Transaction::CATEGORY_VOUCHER);
    }

    public function hasTransactions(): bool
    {
        return $this->transactions()->exists();
    }
}
