<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction model representing a PR/PO/VCH transaction.
 *
 * Story 3.3 - Added transaction actions relationship and tracking columns.
 *
 * @property int $id
 * @property int $procurement_id
 * @property string $category
 * @property string $reference_number
 * @property bool $is_continuation
 * @property string $status
 * @property int|null $workflow_id
 * @property int|null $current_office_id
 * @property int|null $current_user_id
 * @property int|null $current_step_id
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $endorsed_at
 * @property int $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, TransactionAction> $actions
 * @property-read WorkflowStep|null $currentStep
 * @property-read Collection<int, TransactionAction> $actionsHistory
 * @property-read TransactionAction|null $lastAction
 * @property-read array{office: Office|null, user: User|null}|null $currentHolder
 */
class Transaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_PURCHASE_REQUEST = 'PR';

    public const CATEGORY_PURCHASE_ORDER = 'PO';

    public const CATEGORY_VOUCHER = 'VCH';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'procurement_id',
        'category',
        'reference_number',
        'is_continuation',
        'status',
        'workflow_id',
        'current_office_id',
        'current_user_id',
        'current_step_id',
        'received_at',
        'endorsed_at',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => 'string',
            'status' => 'string',
            'is_continuation' => 'boolean',
            'received_at' => 'datetime',
            'endorsed_at' => 'datetime',
        ];
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function purchaseRequest(): HasOne
    {
        return $this->hasOne(PurchaseRequest::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(Voucher::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get all actions for this transaction.
     *
     * @return HasMany<TransactionAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(TransactionAction::class);
    }

    /**
     * Get the current workflow step.
     *
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    /**
     * Get actions ordered by created_at descending (most recent first).
     *
     * @return Collection<int, TransactionAction>
     */
    public function getActionsHistoryAttribute(): Collection
    {
        return $this->actions()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get the most recent action.
     */
    public function getLastActionAttribute(): ?TransactionAction
    {
        return $this->actions()->orderBy('created_at', 'desc')->first();
    }

    /**
     * Get the current holder (office and user).
     *
     * @return array{office: Office|null, user: User|null}|null
     */
    public function getCurrentHolderAttribute(): ?array
    {
        if ($this->current_office_id === null && $this->current_user_id === null) {
            return null;
        }

        return [
            'office' => $this->current_office_id
                ? Office::find($this->current_office_id)
                : null,
            'user' => $this->current_user_id
                ? User::find($this->current_user_id)
                : null,
        ];
    }

    /**
     * Check if transaction is at a specific workflow step order.
     */
    public function isAtStep(int $stepOrder): bool
    {
        if ($this->currentStep === null) {
            return false;
        }

        return $this->currentStep->step_order === $stepOrder;
    }

    /**
     * Check if transaction has been received by current office.
     */
    public function hasBeenReceivedByCurrentOffice(): bool
    {
        return $this->received_at !== null;
    }
}
