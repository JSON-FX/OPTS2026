<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'exists:roles,name'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
