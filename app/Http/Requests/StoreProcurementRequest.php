<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'end_user_id' => ['required', 'integer', 'exists:offices,id'],
            'particular_id' => ['required', 'integer', 'exists:particulars,id'],
            'purpose' => ['required', 'string', 'max:1000'],
            'abc_amount' => ['required', 'numeric', 'min:0.01'],
            'date_of_entry' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
