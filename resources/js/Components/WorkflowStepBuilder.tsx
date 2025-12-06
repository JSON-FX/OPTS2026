import { Office } from '@/types/models';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Plus, X, ChevronUp, ChevronDown, GripVertical, Star } from 'lucide-react';

export interface StepData {
    office_id: number | null;
    expected_days: number;
}

interface WorkflowStepBuilderProps {
    steps: StepData[];
    offices: Office[];
    onChange: (steps: StepData[]) => void;
    errors?: Record<string, string>;
}

export default function WorkflowStepBuilder({
    steps,
    offices,
    onChange,
    errors = {},
}: WorkflowStepBuilderProps) {
    const addStep = () => {
        onChange([...steps, { office_id: null, expected_days: 1 }]);
    };

    const removeStep = (index: number) => {
        if (steps.length <= 2) {
            return; // Minimum 2 steps required
        }
        const newSteps = steps.filter((_, i) => i !== index);
        onChange(newSteps);
    };

    const updateStep = (index: number, field: keyof StepData, value: number | null) => {
        const newSteps = [...steps];
        newSteps[index] = { ...newSteps[index], [field]: value };
        onChange(newSteps);
    };

    const moveStep = (index: number, direction: 'up' | 'down') => {
        if (
            (direction === 'up' && index === 0) ||
            (direction === 'down' && index === steps.length - 1)
        ) {
            return;
        }

        const newSteps = [...steps];
        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        [newSteps[index], newSteps[targetIndex]] = [newSteps[targetIndex], newSteps[index]];
        onChange(newSteps);
    };

    const totalExpectedDays = steps.reduce((sum, step) => sum + (step.expected_days || 0), 0);

    // Get list of already selected office IDs (excluding current row)
    const getAvailableOffices = (currentIndex: number) => {
        const selectedOfficeIds = steps
            .filter((_, i) => i !== currentIndex)
            .map((s) => s.office_id)
            .filter((id): id is number => id !== null);
        return offices.filter((o) => !selectedOfficeIds.includes(o.id));
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-medium">Workflow Steps</h3>
                <Button type="button" variant="outline" size="sm" onClick={addStep}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Step
                </Button>
            </div>

            {errors.steps && (
                <p className="text-sm text-destructive">{errors.steps}</p>
            )}

            <div className="rounded-lg border bg-card">
                <div className="divide-y">
                    {steps.map((step, index) => {
                        const availableOffices = getAvailableOffices(index);
                        const isFinalStep = index === steps.length - 1;
                        const stepError = errors[`steps.${index}.office_id`] || errors[`steps.${index}.expected_days`];

                        return (
                            <div
                                key={index}
                                className={`flex items-center gap-3 p-4 ${stepError ? 'bg-destructive/5' : ''}`}
                            >
                                {/* Drag handle / step indicator */}
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <GripVertical className="h-5 w-5" />
                                    <span className="w-6 text-center font-medium">{index + 1}.</span>
                                </div>

                                {/* Reorder buttons */}
                                <div className="flex flex-col gap-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-5 w-5"
                                        disabled={index === 0}
                                        onClick={() => moveStep(index, 'up')}
                                    >
                                        <ChevronUp className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-5 w-5"
                                        disabled={index === steps.length - 1}
                                        onClick={() => moveStep(index, 'down')}
                                    >
                                        <ChevronDown className="h-4 w-4" />
                                    </Button>
                                </div>

                                {/* Office select */}
                                <div className="flex-1">
                                    <Select
                                        value={step.office_id?.toString() || ''}
                                        onValueChange={(v) => updateStep(index, 'office_id', v ? parseInt(v) : null)}
                                    >
                                        <SelectTrigger className={errors[`steps.${index}.office_id`] ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Select office..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {/* Include currently selected office if any */}
                                            {step.office_id && !availableOffices.find(o => o.id === step.office_id) && (
                                                <SelectItem value={step.office_id.toString()}>
                                                    {offices.find(o => o.id === step.office_id)?.name || 'Unknown'}
                                                </SelectItem>
                                            )}
                                            {availableOffices.map((office) => (
                                                <SelectItem key={office.id} value={office.id.toString()}>
                                                    {office.name} ({office.abbreviation})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors[`steps.${index}.office_id`] && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {errors[`steps.${index}.office_id`]}
                                        </p>
                                    )}
                                </div>

                                {/* Expected days */}
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground whitespace-nowrap">
                                        Expected Days:
                                    </span>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={step.expected_days}
                                        onChange={(e) =>
                                            updateStep(index, 'expected_days', Math.max(1, parseInt(e.target.value) || 1))
                                        }
                                        className={`w-20 ${errors[`steps.${index}.expected_days`] ? 'border-destructive' : ''}`}
                                    />
                                </div>

                                {/* Final step indicator */}
                                {isFinalStep && (
                                    <div className="flex items-center gap-1 text-amber-600">
                                        <Star className="h-4 w-4 fill-current" />
                                        <span className="text-sm font-medium">Final</span>
                                    </div>
                                )}

                                {/* Remove button */}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={steps.length <= 2}
                                    onClick={() => removeStep(index)}
                                    className="text-muted-foreground hover:text-destructive"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        );
                    })}
                </div>

                {/* Summary footer */}
                <div className="border-t bg-muted/50 px-4 py-3">
                    <p className="text-sm text-muted-foreground">
                        Total: <span className="font-medium">{steps.length} steps</span>,{' '}
                        <span className="font-medium">{totalExpectedDays} expected days</span>
                    </p>
                </div>
            </div>

            {steps.length < 2 && (
                <p className="text-sm text-destructive">
                    A workflow must have at least 2 steps.
                </p>
            )}
        </div>
    );
}
