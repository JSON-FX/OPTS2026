<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Transaction;
use App\Services\ReferenceNumberService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueReferenceNumber implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    /**
     * Create a new rule instance.
     *
     * @param  int|null  $excludeTransactionId  Transaction ID to exclude from uniqueness check (for updates)
     * @param  string|null  $category  Transaction category ('PR' or 'PO') - if null, will attempt to infer
     * @param  string|null  $fundTypeAbbreviation  Fund type abbreviation (required for PR category)
     */
    public function __construct(
        private readonly ?int $excludeTransactionId = null,
        private readonly ?string $category = null,
        private readonly ?string $fundTypeAbbreviation = null
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Build the full reference number from request data
        $service = app(ReferenceNumberService::class);

        $year = $this->data['ref_year'] ?? null;
        $month = $this->data['ref_month'] ?? null;
        $number = $value; // ref_number
        $isContinuation = $this->data['is_continuation'] ?? false;

        if (! $year || ! $month || ! $number) {
            return; // Let other validation rules handle missing fields
        }

        // Build full reference number based on category
        $category = $this->category ?? $this->inferCategory();

        if ($category === 'PR') {
            $fundTypeAbbr = $this->fundTypeAbbreviation ?? $this->getFundTypeAbbreviation();
            if (! $fundTypeAbbr) {
                return; // Cannot validate without fund type
            }
            $fullReferenceNumber = $service->buildPRReferenceNumber(
                $fundTypeAbbr,
                $year,
                $month,
                $number,
                $isContinuation
            );
        } elseif ($category === 'PO') {
            $fullReferenceNumber = $service->buildPOReferenceNumber(
                $year,
                $month,
                $number,
                $isContinuation
            );
        } else {
            return; // Unknown category
        }

        // Check uniqueness
        $query = Transaction::where('reference_number', $fullReferenceNumber);

        if ($this->excludeTransactionId) {
            $query->where('id', '!=', $this->excludeTransactionId);
        }

        if ($query->exists()) {
            $fail("Reference number {$fullReferenceNumber} already exists. Please enter a different number.");
        }
    }

    private function inferCategory(): ?string
    {
        // Attempt to infer category from request data or route
        // For now, return null if not explicitly provided
        return null;
    }

    private function getFundTypeAbbreviation(): ?string
    {
        if ($this->fundTypeAbbreviation) {
            return $this->fundTypeAbbreviation;
        }

        $fundTypeId = $this->data['fund_type_id'] ?? null;
        if ($fundTypeId) {
            $fundType = \App\Models\FundType::find($fundTypeId);

            return $fundType?->abbreviation;
        }

        return null;
    }
}
