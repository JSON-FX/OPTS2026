<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Rules\ScannedDocumentReference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for receiving a single transaction.
 *
 * Story 3.5 - Receive Action Implementation
 */
class ReceiveTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction && $this->user()->can('receive', $transaction);
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
            'scanned_reference.required' => 'Scan the document QR code before confirming.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
