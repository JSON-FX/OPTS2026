import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Workflow, WorkflowStep, Office, User, TransactionCategory } from '@/types/models';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { ArrowLeft, Pencil, Clock, Building2, Star } from 'lucide-react';

interface WorkflowWithRelations extends Workflow {
    steps: (WorkflowStep & { office: Office })[];
    created_by?: User;
}

interface Props extends PageProps {
    workflow: WorkflowWithRelations;
    totalExpectedDays: number;
}

export default function Show({ auth, workflow, totalExpectedDays }: Props) {
    const getCategoryBadge = (cat: TransactionCategory) => {
        const variants: Record<TransactionCategory, 'default' | 'secondary' | 'outline'> = {
            PR: 'default',
            PO: 'secondary',
            VCH: 'outline',
        };
        return <Badge variant={variants[cat]}>{cat}</Badge>;
    };

    const getCategoryLabel = (cat: TransactionCategory) => {
        const labels: Record<TransactionCategory, string> = {
            PR: 'Purchase Request',
            PO: 'Purchase Order',
            VCH: 'Voucher',
        };
        return labels[cat];
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Workflow Details
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('admin.workflows.index')}>
                            <Button variant="outline">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                        <Link href={route('admin.workflows.edit', workflow.id)}>
                            <Button>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Workflow: ${workflow.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                    {/* Workflow Info Card */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between">
                                <div>
                                    <CardTitle className="text-2xl">{workflow.name}</CardTitle>
                                    <CardDescription className="mt-1">
                                        {getCategoryLabel(workflow.category)}
                                    </CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    {getCategoryBadge(workflow.category)}
                                    <Badge variant={workflow.is_active ? 'default' : 'secondary'}>
                                        {workflow.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {workflow.description && (
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground mb-1">
                                        Description
                                    </h4>
                                    <p className="text-sm">{workflow.description}</p>
                                </div>
                            )}

                            <div className="flex gap-8 text-sm">
                                <div>
                                    <span className="text-muted-foreground">Created:</span>{' '}
                                    <span className="font-medium">
                                        {new Date(workflow.created_at).toLocaleDateString()}
                                    </span>
                                </div>
                                {workflow.created_by && (
                                    <div>
                                        <span className="text-muted-foreground">Created by:</span>{' '}
                                        <span className="font-medium">{workflow.created_by.name}</span>
                                    </div>
                                )}
                            </div>

                            {/* Summary Stats */}
                            <div className="flex gap-6 pt-4 border-t">
                                <div className="flex items-center gap-2">
                                    <Building2 className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-2xl font-bold">{workflow.steps.length}</p>
                                        <p className="text-xs text-muted-foreground">Total Steps</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <p className="text-2xl font-bold">{totalExpectedDays}</p>
                                        <p className="text-xs text-muted-foreground">Expected Days</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Steps Visualization Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Workflow Steps</CardTitle>
                            <CardDescription>
                                Transaction routing sequence with expected completion days per step
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {workflow.steps.map((step, index) => {
                                    const progressPercent =
                                        ((index + 1) / workflow.steps.length) * 100;
                                    const daysPercent =
                                        (step.expected_days / totalExpectedDays) * 100;

                                    return (
                                        <div key={step.id} className="relative">
                                            {/* Connector line */}
                                            {index < workflow.steps.length - 1 && (
                                                <div className="absolute left-6 top-12 h-8 w-0.5 bg-border" />
                                            )}

                                            <div className="flex items-start gap-4">
                                                {/* Step number circle */}
                                                <div
                                                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 ${
                                                        step.is_final_step
                                                            ? 'border-amber-500 bg-amber-50 text-amber-700'
                                                            : 'border-primary bg-primary/5 text-primary'
                                                    }`}
                                                >
                                                    {step.is_final_step ? (
                                                        <Star className="h-5 w-5 fill-current" />
                                                    ) : (
                                                        <span className="text-lg font-bold">{step.step_order}</span>
                                                    )}
                                                </div>

                                                {/* Step details */}
                                                <div className="flex-1 pt-1">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <h4 className="font-medium">
                                                                {step.office.name}
                                                            </h4>
                                                            <p className="text-sm text-muted-foreground">
                                                                {step.office.abbreviation}
                                                            </p>
                                                        </div>
                                                        <div className="text-right">
                                                            <Badge variant="outline" className="font-mono">
                                                                <Clock className="mr-1 h-3 w-3" />
                                                                {step.expected_days} day{step.expected_days > 1 ? 's' : ''}
                                                            </Badge>
                                                            {step.is_final_step && (
                                                                <Badge className="ml-2" variant="secondary">
                                                                    Final Step
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Default Action Taken */}
                                                    {step.action_taken && (
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            Default action: <span className="font-medium">{step.action_taken.description}</span>
                                                        </p>
                                                    )}

                                                    {/* Progress bar for expected days proportion */}
                                                    <div className="mt-2">
                                                        <Progress value={daysPercent} className="h-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Total summary */}
                            <div className="mt-6 pt-4 border-t flex justify-between items-center">
                                <span className="text-sm text-muted-foreground">
                                    Complete workflow duration
                                </span>
                                <Badge variant="secondary" className="text-base px-3 py-1">
                                    <Clock className="mr-2 h-4 w-4" />
                                    {totalExpectedDays} total expected days
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
