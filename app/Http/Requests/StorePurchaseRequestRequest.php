<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canProcessTransactions();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fund_type_id' => 'required|exists:fund_types,id,deleted_at,NULL',
            'manual_reference' => ['sometimes', 'boolean'],
            'reference_number' => ['exclude_unless:manual_reference,true', 'required', 'string', 'max:50', 'unique:transactions,reference_number'],
            'ref_year' => ['exclude_if:manual_reference,true', 'required', 'digits:4', 'integer', 'in:'.now()->year],
            'ref_month' => ['exclude_if:manual_reference,true', 'required', 'digits:2', 'numeric', 'min:1', 'max:12'],
            'is_continuation' => 'boolean',
            'workflow_id' => ['nullable', 'integer', new \App\Rules\ActiveSsoWorkflow('PR')],
        ];
    }
}
