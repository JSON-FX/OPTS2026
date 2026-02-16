<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for holding a transaction.
 *
 * Story 3.7 - Transaction State Machine
 */
class HoldTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Administrator');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to place a transaction on hold.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
