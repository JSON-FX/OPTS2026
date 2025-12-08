<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TransactionAction model for recording transaction endorsement history and audit trail.
 *
 * Story 3.3 - Transaction Actions Schema & Models
 *
 * @property int $id
 * @property int $transaction_id
 * @property string $action_type
 * @property int|null $action_taken_id
 * @property int|null $from_office_id
 * @property int|null $to_office_id
 * @property int $from_user_id
 * @property int|null $to_user_id
 * @property int|null $workflow_step_id
 * @property bool $is_out_of_workflow
 * @property string|null $notes
 * @property string|null $reason
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Transaction $transaction
 * @property-read ActionTaken|null $actionTaken
 * @property-read Office|null $fromOffice
 * @property-read Office|null $toOffice
 * @property-read User $fromUser
 * @property-read User|null $toUser
 * @property-read WorkflowStep|null $workflowStep
 */
class TransactionAction extends Model
{
    use HasFactory;

    /**
     * Action type constants.
     */
    public const TYPE_ENDORSE = 'endorse';

    public const TYPE_RECEIVE = 'receive';

    public const TYPE_COMPLETE = 'complete';

    public const TYPE_HOLD = 'hold';

    public const TYPE_CANCEL = 'cancel';

    public const TYPE_BYPASS = 'bypass';

    /**
     * All valid action types.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_ENDORSE,
        self::TYPE_RECEIVE,
        self::TYPE_COMPLETE,
        self::TYPE_HOLD,
        self::TYPE_CANCEL,
        self::TYPE_BYPASS,
    ];

    /**
     * Indicates if the model should be timestamped.
     * Only created_at is used, no updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'action_type',
        'action_taken_id',
        'from_office_id',
        'to_office_id',
        'from_user_id',
        'to_user_id',
        'workflow_step_id',
        'is_out_of_workflow',
        'notes',
        'reason',
        'ip_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_out_of_workflow' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the transaction this action belongs to.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the action taken repository entry.
     *
     * @return BelongsTo<ActionTaken, $this>
     */
    public function actionTaken(): BelongsTo
    {
        return $this->belongsTo(ActionTaken::class, 'action_taken_id');
    }

    /**
     * Get the office sending the transaction.
     *
     * @return BelongsTo<Office, $this>
     */
    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    /**
     * Get the office receiving the transaction.
     *
     * @return BelongsTo<Office, $this>
     */
    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'to_office_id');
    }

    /**
     * Get the user performing the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the target user (for receive actions).
     *
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Get the workflow step when action occurred.
     *
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    /**
     * Scope to filter by action type.
     *
     * @param  Builder<TransactionAction>  $query
     * @return Builder<TransactionAction>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope to filter out-of-workflow actions.
     *
     * @param  Builder<TransactionAction>  $query
     * @return Builder<TransactionAction>
     */
    public function scopeOutOfWorkflow(Builder $query): Builder
    {
        return $query->where('is_out_of_workflow', true);
    }

    /**
     * Scope to filter actions for a specific transaction.
     *
     * @param  Builder<TransactionAction>  $query
     * @return Builder<TransactionAction>
     */
    public function scopeForTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope to filter actions by office (from or to).
     *
     * @param  Builder<TransactionAction>  $query
     * @return Builder<TransactionAction>
     */
    public function scopeByOffice(Builder $query, int $officeId): Builder
    {
        return $query->where(function (Builder $q) use ($officeId) {
            $q->where('from_office_id', $officeId)
                ->orWhere('to_office_id', $officeId);
        });
    }
}
