import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Office, TransactionCategory } from '@/types/models';
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
import WorkflowStepBuilder, { StepData } from '@/Components/WorkflowStepBuilder';
import { useState, useEffect } from 'react';

interface Props extends PageProps {
    offices: Office[];
}

export default function Create({ offices }: Props) {
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        category: '' as TransactionCategory | '',
        description: '',
        is_active: true,
        steps: [
            { office_id: null as number | null, expected_days: 1 },
            { office_id: null as number | null, expected_days: 1 },
        ],
    });

    // Track unsaved changes
    useEffect(() => {
        const hasChanges =
            data.name !== '' ||
            data.category !== '' ||
            data.description !== '' ||
            !data.is_active ||
            data.steps.some((s) => s.office_id !== null);
        setHasUnsavedChanges(hasChanges);
    }, [data]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.workflows.store'));
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
                    Create Workflow
                </h2>
            }
        >
            <Head title="Create Workflow" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <form onSubmit={handleSubmit} className="p-6 space-y-6">
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
                                    onValueChange={(v) => setData('category', v as TransactionCategory)}
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
                                <WorkflowStepBuilder
                                    steps={data.steps}
                                    offices={offices}
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
                                    {processing ? 'Creating...' : 'Create Workflow'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
