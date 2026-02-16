<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;
    }

    /**
     * Story 2.7 AC#13-14 - Validation for PO update with supplier change support.
     * Note: supplier_address is auto-updated from supplier model if supplier changes.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchaseOrder = \App\Models\PurchaseOrder::findOrFail($this->route('id'));
        $transactionId = $purchaseOrder->transaction_id;

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id,deleted_at,NULL'],
            'contract_price' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99', 'decimal:0,2'],
            'ref_year' => 'required|digits:4|integer|min:2000|max:9999',
            'ref_month' => 'required|digits:2|numeric|min:1|max:12',
            'ref_number' => [
                'required',
                'string',
                'max:50',
                new \App\Rules\UniqueReferenceNumber($transactionId, 'PO'),
            ],
            'is_continuation' => 'boolean',
            'workflow_id' => ['nullable', 'integer', 'exists:workflows,id'],
        ];
    }
}
