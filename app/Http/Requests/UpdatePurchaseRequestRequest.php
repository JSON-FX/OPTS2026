<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canProcessTransactions();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fund_type_id' => 'required|exists:fund_types,id,deleted_at,NULL',
            'workflow_id' => 'nullable|exists:workflows,id',
        ];
    }
}
