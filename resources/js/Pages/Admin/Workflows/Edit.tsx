import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Office, Workflow, WorkflowStep, TransactionCategory } from '@/types/models';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import WorkflowStepBuilder, { StepData } from '@/Components/WorkflowStepBuilder';
import { useState, useEffect, useMemo } from 'react';
import { AlertTriangle } from 'lucide-react';

interface WorkflowWithSteps extends Workflow {
    steps: (WorkflowStep & { office: Office })[];
}

interface ActionTakenOption {
    id: number;
    description: string;
}

interface Props extends PageProps {
    workflow: WorkflowWithSteps;
    offices: Office[];
    actionTakenOptions: ActionTakenOption[];
    creationActionTakenMap: Record<string, number | null>;
    hasActiveTransactions: boolean;
}

export default function Edit({ workflow, offices, actionTakenOptions, creationActionTakenMap, hasActiveTransactions }: Props) {
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

    const initialSteps = useMemo(() =>
        workflow.steps.map((step, index) => ({
            office_id: step.office_id as number | null,
            expected_days: step.expected_days,
            action_taken_id: step.action_taken_id
                ?? (index === 0 ? (creationActionTakenMap[workflow.category] ?? null) : null),
        })),
        [workflow.steps, workflow.category, creationActionTakenMap]
    );

    const { data, setData, put, processing, errors } = useForm({
        name: workflow.name,
        category: workflow.category as TransactionCategory,
        description: workflow.description || '',
        is_active: workflow.is_active,
        steps: initialSteps,
    });

    // Track unsaved changes
    useEffect(() => {
        const hasChanges =
            data.name !== workflow.name ||
            data.category !== workflow.category ||
            data.description !== (workflow.description || '') ||
            data.is_active !== workflow.is_active ||
            JSON.stringify(data.steps) !== JSON.stringify(initialSteps);
        setHasUnsavedChanges(hasChanges);
    }, [data, workflow, initialSteps]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.workflows.update', workflow.id));
    };

    const handleCancel = () => {
        if (hasUnsavedChanges) {
            if (confirm('You have unsaved changes. Are you sure you want to leave?')) {
                router.visit(route('admin.workflows.index'));
            }
        } else {
            router.visit(route('admin.workflows.index'));
        }
    };

    const handleStepsChange = (steps: StepData[]) => {
        setData('steps', steps);
    };

    // Convert errors object for step builder
    const stepErrors: Record<string, string> = {};
    Object.entries(errors).forEach(([key, value]) => {
        if (key.startsWith('steps')) {
            stepErrors[key] = value;
        }
    });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Workflow
                </h2>
            }
        >
            <Head title="Edit Workflow" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
                            {/* Warning for active transactions */}
                            {hasActiveTransactions && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        This workflow has active transactions. Changes may affect
                                        in-progress transactions. Proceed with caution.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Enter workflow name"
                                    className={errors.name ? 'border-destructive' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            {/* Category */}
                            <div className="space-y-2">
                                <Label htmlFor="category">Category *</Label>
                                <Select
                                    value={data.category}
                                    onValueChange={(v) => {
                                        const cat = v as TransactionCategory;
                                        setData((prev) => {
                                            const newSteps = [...prev.steps];
                                            if (newSteps.length > 0) {
                                                newSteps[0] = { ...newSteps[0], action_taken_id: creationActionTakenMap[cat] ?? null };
                                            }
                                            return { ...prev, category: cat, steps: newSteps };
                                        });
                                    }}
                                    disabled={hasActiveTransactions}
                                >
                                    <SelectTrigger className={errors.category ? 'border-destructive' : ''}>
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="PR">Purchase Request (PR)</SelectItem>
                                        <SelectItem value="PO">Purchase Order (PO)</SelectItem>
                                        <SelectItem value="VCH">Voucher (VCH)</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.category && (
                                    <p className="text-sm text-destructive">{errors.category}</p>
                                )}
                                {hasActiveTransactions && (
                                    <p className="text-sm text-muted-foreground">
                                        Category cannot be changed while transactions are using this workflow.
                                    </p>
                                )}
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('description', e.target.value)}
                                    placeholder="Enter workflow description (optional)"
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}
                            </div>

                            {/* Active toggle */}
                            <div className="flex items-center gap-3">
                                <Switch
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked: boolean) => setData('is_active', checked)}
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>

                            {/* Step Builder */}
                            <div className="pt-4 border-t">
                                {hasActiveTransactions && (
                                    <Alert className="mb-4">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            Modifying steps may affect transactions currently in progress.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <WorkflowStepBuilder
                                    steps={data.steps}
                                    offices={offices}
                                    actionTakenOptions={actionTakenOptions}
                                    onChange={handleStepsChange}
                                    errors={stepErrors}
                                />
                            </div>

                            {/* Actions */}
                            <div className="flex justify-end gap-4 pt-4 border-t">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleCancel}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
