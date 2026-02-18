<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowStep model representing a single step in a workflow.
 *
 * Each step defines an office in the transaction routing sequence,
 * with an expected number of days to complete the step.
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $office_id
 * @property int $step_order
 * @property int $expected_days
 * @property bool $is_final_step
 * @property int|null $action_taken_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read Office $office
 * @property-read ActionTaken|null $actionTaken
 * @property-read bool $is_first_step
 */
class WorkflowStep extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'office_id',
        'step_order',
        'expected_days',
        'is_final_step',
        'action_taken_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'expected_days' => 'integer',
            'is_final_step' => 'boolean',
            'action_taken_id' => 'integer',
        ];
    }

    /**
     * Get the workflow this step belongs to.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the office for this step.
     *
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Get the default action taken for this step.
     *
     * @return BelongsTo<ActionTaken, $this>
     */
    public function actionTaken(): BelongsTo
    {
        return $this->belongsTo(ActionTaken::class);
    }

    /**
     * Scope a query to order by step_order ascending.
     *
     * @param  Builder<WorkflowStep>  $query
     * @return Builder<WorkflowStep>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('step_order', 'asc');
    }

    /**
     * Determine if this is the first step in the workflow.
     */
    public function getIsFirstStepAttribute(): bool
    {
        return $this->step_order === 1;
    }

    /**
     * Get the next step in the workflow.
     *
     * @return WorkflowStep|null Null if this is the final step
     */
    public function getNextStep(): ?WorkflowStep
    {
        return static::query()
            ->where('workflow_id', $this->workflow_id)
            ->where('step_order', $this->step_order + 1)
            ->first();
    }

    /**
     * Get the previous step in the workflow.
     *
     * @return WorkflowStep|null Null if this is the first step
     */
    public function getPreviousStep(): ?WorkflowStep
    {
        if ($this->step_order <= 1) {
            return null;
        }

        return static::query()
            ->where('workflow_id', $this->workflow_id)
            ->where('step_order', $this->step_order - 1)
            ->first();
    }
}
