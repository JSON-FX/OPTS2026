<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for bulk receiving transactions.
 *
 * Story 3.5 - Receive Action Implementation
 */
class ReceiveBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must have Endorser or Administrator role
        return $this->user()->hasAnyRole(['Endorser', 'Administrator']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['required', 'integer', 'exists:transactions,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_ids.required' => 'Please select at least one transaction.',
            'transaction_ids.min' => 'Please select at least one transaction.',
            'transaction_ids.*.exists' => 'One or more selected transactions are invalid.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
