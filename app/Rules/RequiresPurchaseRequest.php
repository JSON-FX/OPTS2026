<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Procurement;
use App\Services\ProcurementBusinessRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RequiresPurchaseRequest implements ValidationRule
{
    public function __construct(
        private readonly ProcurementBusinessRules $businessRules
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $procurement = Procurement::find($value);

        if (! $procurement) {
            $fail('The selected procurement does not exist.');

            return;
        }

        if (! $this->businessRules->canCreatePO($procurement)) {
            $fail('You must create a Purchase Request before adding a Purchase Order for this procurement.');
        }
    }
}
