<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for endorsing a transaction.
 *
 * Story 3.4 - Endorse Action Implementation
 */
class EndorseTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the controller via policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action_taken_id' => ['required', 'integer', 'exists:action_taken,id'],
            'to_office_id' => ['required', 'integer', 'exists:offices,id'],
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
            'action_taken_id.required' => 'Please select an action taken.',
            'action_taken_id.exists' => 'The selected action is invalid.',
            'to_office_id.required' => 'Please select a target office.',
            'to_office_id.exists' => 'The selected office is invalid.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
