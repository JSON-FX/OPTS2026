<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RequiresPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Endorser', 'Administrator']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'procurement_id' => ['required', 'integer', 'exists:procurements,id', app(RequiresPurchaseOrder::class)],
            'payee' => ['required', 'string', 'max:255'],
            'workflow_id' => ['nullable', 'integer', 'exists:workflows,id'],
        ];
    }
}
