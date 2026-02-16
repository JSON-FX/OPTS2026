<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Endorser', 'Administrator']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fund_type_id' => 'required|exists:fund_types,id,deleted_at,NULL',
            'ref_year' => 'required|digits:4|integer|min:2000|max:9999',
            'ref_month' => 'required|digits:2|numeric|min:1|max:12',
            'ref_number' => [
                'required',
                'string',
                'max:50',
                new \App\Rules\UniqueReferenceNumber(null, 'PR'),
            ],
            'is_continuation' => 'boolean',
            'workflow_id' => 'nullable|exists:workflows,id',
        ];
    }
}
