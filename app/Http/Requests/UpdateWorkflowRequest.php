<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorkflowRequest extends FormRequest
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
            // Validate unique offices in steps
            if ($this->has('steps') && is_array($this->input('steps'))) {
                $officeIds = collect($this->input('steps'))->pluck('office_id')->filter();

                if ($officeIds->count() !== $officeIds->unique()->count()) {
                    $validator->errors()->add('steps', 'Each office can only appear once in the workflow.');
                }
            }

            // Prevent category change if transactions exist
            $workflow = $this->route('workflow');
            if ($workflow && $this->input('category') !== $workflow->category) {
                $hasTransactions = Transaction::where('workflow_id', $workflow->id)->exists();

                if ($hasTransactions) {
                    $validator->errors()->add('category', 'Cannot change category when transactions are using this workflow.');
                }
            }

            // Prevent removing steps that have active transactions
            if ($workflow && $this->has('steps') && is_array($this->input('steps'))) {
                $incomingOfficeIds = collect($this->input('steps'))->pluck('office_id')->filter()->all();

                $stepsBeingRemoved = WorkflowStep::where('workflow_id', $workflow->id)
                    ->whereNotIn('office_id', $incomingOfficeIds)
                    ->get();

                foreach ($stepsBeingRemoved as $step) {
                    $hasActiveTransactions = Transaction::where('current_step_id', $step->id)
                        ->whereNotIn('status', ['Completed', 'Cancelled'])
                        ->exists();

                    if ($hasActiveTransactions) {
                        $officeName = $step->office->name ?? "ID {$step->office_id}";
                        $validator->errors()->add(
                            'steps',
                            "Cannot remove the \"{$officeName}\" step because it has active transactions."
                        );
                    }
                }
            }
        });
    }
}
