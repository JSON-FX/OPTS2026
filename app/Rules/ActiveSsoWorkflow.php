<?php

namespace App\Rules;

use App\Models\Workflow;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveSsoWorkflow implements ValidationRule
{
    public function __construct(private string $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = Workflow::whereKey($value)->where('category', $this->category)->where('is_active', true)
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn ($steps) => $steps->whereDoesntHave('office', fn ($offices) => $offices->where('is_active', true)->whereNotNull('sso_id')))
            ->exists();
        if (! $valid) {
            $fail('Select an active workflow whose offices are available in SSO.');
        }
    }
}
