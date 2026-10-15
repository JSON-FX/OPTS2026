<?php

namespace App\Rules;

use App\Models\Office;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveSsoOffice implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $office = Office::find($value);
        // EnsureSsoEligibility refreshes the complete reference snapshot before mutations.
        if (! $office?->sso_id || ! $office->is_active) {
            $fail('Select an active office from SSO.');
        }
    }
}
