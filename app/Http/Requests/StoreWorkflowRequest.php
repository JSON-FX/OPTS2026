<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkflowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:PR,PO,VCH'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:2'],
            'steps.*.office_id' => ['required', 'integer', 'exists:offices,id'],
            'steps.*.expected_days' => ['required', 'integer', 'min:1'],
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
            'steps.min' => 'A workflow must have at least 2 steps.',
            'steps.*.office_id.required' => 'Each step must have an office selected.',
            'steps.*.office_id.exists' => 'The selected office does not exist.',
            'steps.*.expected_days.required' => 'Each step must have expected days specified.',
            'steps.*.expected_days.min' => 'Expected days must be at least 1.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('steps') || ! is_array($this->input('steps'))) {
                return;
            }

            $officeIds = collect($this->input('steps'))->pluck('office_id')->filter();

            if ($officeIds->count() !== $officeIds->unique()->count()) {
                $validator->errors()->add('steps', 'Each office can only appear once in the workflow.');
            }
        });
    }
}
