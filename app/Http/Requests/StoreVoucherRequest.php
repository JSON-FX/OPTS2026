<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    /**
     * Story 2.8 AC#15 - RBAC: Endorser and Administrator can create VCH.
     */
    public function authorize(): bool
    {
        return $this->user()?->canProcessTransactions() ?? false;
    }

    /**
     * Story 2.8 AC#6,7,8 - Reference number (manual input) and payee validation.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'max:50', 'unique:transactions,reference_number'],
            'payee' => ['required', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'integer', new \App\Rules\ActiveSsoWorkflow('VCH')],
        ];
    }
}
