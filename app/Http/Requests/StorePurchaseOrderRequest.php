<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RequiresPurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'procurement_id' => ['required', 'integer', 'exists:procurements,id', app(RequiresPurchaseRequest::class)],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_address' => ['required', 'string', 'max:1000'],
            'contract_price' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'ref_year' => 'required|digits:4|integer|min:2000|max:9999',
            'ref_month' => 'required|digits:2|integer|min:1|max:12',
            'ref_number' => [
                'required',
                'string',
                'max:50',
                new \App\Rules\UniqueReferenceNumber(null, 'PO'),
            ],
            'is_continuation' => 'boolean',
            'workflow_id' => ['nullable', 'integer', 'exists:workflows,id'],
        ];
    }
}
