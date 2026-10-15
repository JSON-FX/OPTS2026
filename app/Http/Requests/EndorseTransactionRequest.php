<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Rules\ScannedDocumentReference;
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
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction && $this->user()->can('endorse', $transaction);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scanned_reference' => ['required', 'string', 'max:255', new ScannedDocumentReference($this->route('transaction')->reference_number)],
            'action_taken_id' => ['required', 'integer', 'exists:action_taken,id'],
            'to_office_id' => ['required', 'integer', new \App\Rules\ActiveSsoOffice],
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
            'scanned_reference.required' => 'Scan the document QR code before confirming.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
