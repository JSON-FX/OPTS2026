<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Workflow model representing transaction routing configurations.
 *
 * A workflow defines the ordered sequence of offices that a transaction
 * must pass through, with expected completion days per step.
 *
 * @property int $id
 * @property string $category PR|PO|VCH
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkflowStep> $steps
 * @property-read User|null $createdBy
 * @property-read int $steps_count
 */
class Workflow extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'name',
        'description',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the workflow steps ordered by step_order.
     *
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    /**
     * Get the user who created this workflow.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope a query to only include active workflows.
     *
     * @param  Builder<Workflow>  $query
     * @return Builder<Workflow>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by transaction category.
     *
     * @param  Builder<Workflow>  $query
     * @param  string  $category  PR|PO|VCH
     * @return Builder<Workflow>
     */
    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Get the count of workflow steps.
     */
    public function getStepsCountAttribute(): int
    {
        return $this->steps()->count();
    }

    /**
     * Get the first step in the workflow (step_order = 1).
     */
    public function getFirstStep(): ?WorkflowStep
    {
        return $this->steps()->where('step_order', 1)->first();
    }

    /**
     * Get the final step in the workflow (is_final_step = true).
     */
    public function getLastStep(): ?WorkflowStep
    {
        return $this->steps()->where('is_final_step', true)->first();
    }

    /**
     * Get the workflow step at a specific order position.
     *
     * @param  int  $order  The step order position
     */
    public function getStepByOrder(int $order): ?WorkflowStep
    {
        return $this->steps()->where('step_order', $order)->first();
    }

    /**
     * Get the next step after the given step order.
     *
     * @param  int  $currentStepOrder  The current step order position
     * @return WorkflowStep|null Null if at end of workflow
     */
    public function getNextStep(int $currentStepOrder): ?WorkflowStep
    {
        return $this->steps()->where('step_order', $currentStepOrder + 1)->first();
    }
}
