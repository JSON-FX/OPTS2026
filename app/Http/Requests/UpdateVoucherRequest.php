<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherRequest extends FormRequest
{
    /**
     * Story 2.8 AC#15 - RBAC: Endorser and Administrator can edit VCH.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;
    }

    /**
     * Story 2.8 AC#12 - Reference number and payee validation (ignore current record for uniqueness).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $voucherId = $this->route('id');
        $voucher = \App\Models\Voucher::findOrFail($voucherId);

        return [
            'reference_number' => ['required', 'string', 'max:50', 'unique:transactions,reference_number,'.$voucher->transaction_id],
            'payee' => ['required', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'integer', 'exists:workflows,id'],
        ];
    }
}
