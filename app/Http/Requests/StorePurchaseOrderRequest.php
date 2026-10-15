<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canProcessTransactions() ?? false;
    }

    /**
     * Story 2.7 AC#6,8,18 - Validation for manual reference numbers and contract price.
     * Note: Procurement comes from route binding, supplier_address is fetched from supplier model.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id,deleted_at,NULL'],
            'contract_price' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'manual_reference' => ['sometimes', 'boolean'],
            'reference_number' => ['exclude_unless:manual_reference,true', 'required', 'string', 'max:50', 'unique:transactions,reference_number'],
            'ref_year' => ['exclude_if:manual_reference,true', 'required', 'digits:4', 'integer', 'in:'.now()->year],
            'ref_month' => ['exclude_if:manual_reference,true', 'required', 'digits:2', 'numeric', 'min:1', 'max:12'],
            'is_continuation' => 'boolean',
            'workflow_id' => ['nullable', 'integer', new \App\Rules\ActiveSsoWorkflow('PO')],
        ];
    }
}
