import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Send } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription } from '@/Components/ui/alert';

interface WorkflowStep {
    id: number;
    step_order: number;
    office_name: string;
    is_final_step: boolean;
}

interface ActionTakenOption {
    id: number;
    description: string;
}

interface OfficeOption {
    id: number;
    name: string;
}

interface TransactionData {
    id: number;
    reference_number: string;
    category: string;
    status: string;
    current_step_id: number | null;
    current_step: {
        id: number;
        step_order: number;
        office: {
            id: number;
            name: string;
        } | null;
    } | null;
    procurement: {
        id: number;
        purpose: string | null;
        end_user: {
            id: number;
            name: string;
        } | null;
    } | null;
}

interface EntityShowRoute {
    route: string;
    id: number;
}

interface Props {
    transaction: TransactionData;
    workflowSteps: WorkflowStep[];
    actionTakenOptions: ActionTakenOption[];
    officeOptions: OfficeOption[];
    expectedNextOffice: OfficeOption | null;
    entityShowRoute: EntityShowRoute;
    defaultActionTakenId: number | null;
}

export default function Endorse({
    transaction,
    workflowSteps,
    actionTakenOptions,
    officeOptions,
    expectedNextOffice,
    entityShowRoute,
    defaultActionTakenId,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        action_taken_id: defaultActionTakenId?.toString() ?? '',
        to_office_id: expectedNextOffice?.id.toString() || '',
        notes: '',
    });

    const isOutOfWorkflow =
        expectedNextOffice && data.to_office_id !== '' &&
        parseInt(data.to_office_id) !== expectedNextOffice.id;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('transactions.endorse.store', transaction.id));
    };

    const getCategoryColor = (category: string): string => {
        const colors: Record<string, string> = {
            PR: 'bg-blue-100 text-blue-800',
            PO: 'bg-green-100 text-green-800',
            VCH: 'bg-purple-100 text-purple-800',
        };
        return colors[category] || 'bg-gray-100 text-gray-800';
    };

    const currentStepOrder = transaction.current_step?.step_order || 0;
    const totalSteps = workflowSteps.length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route(entityShowRoute.route, entityShowRoute.id)}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Endorse Transaction
                    </h2>
                </div>
            }
        >
            <Head title={`Endorse ${transaction.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">
                    {/* Transaction Summary */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-2xl">
                                        {transaction.reference_number}
                                    </CardTitle>
                                    <CardDescription>
                                        Endorse this transaction to the next office
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant="outline"
                                    className={getCategoryColor(transaction.category)}
                                >
                                    {transaction.category}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-gray-500">Current Office:</span>
                                    <p className="font-medium">
                                        {transaction.current_step?.office?.name || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-gray-500">Status:</span>
                                    <p className="font-medium">{transaction.status}</p>
                                </div>
                                {transaction.procurement && (
                                    <>
                                        <div>
                                            <span className="text-gray-500">End User:</span>
                                            <p className="font-medium">
                                                {transaction.procurement.end_user?.name || 'N/A'}
                                            </p>
                                        </div>
                                        <div className="col-span-2">
                                            <span className="text-gray-500">Purpose:</span>
                                            <p className="font-medium">
                                                {transaction.procurement.purpose || 'N/A'}
                                            </p>
                                        </div>
                                    </>
                                )}
                            </div>

                            {/* Workflow Progress */}
                            {workflowSteps.length > 0 && (
                                <div className="pt-4 border-t">
                                    <p className="text-sm text-gray-500 mb-3">
                                        Workflow Step: {currentStepOrder} of {totalSteps}
                                    </p>
                                    <div className="flex items-center gap-2 overflow-x-auto pb-2">
                                        {workflowSteps.map((step, index) => {
                                            const isCompleted = step.step_order < currentStepOrder;
                                            const isCurrent = step.step_order === currentStepOrder;

                                            return (
                                                <div key={step.id} className="flex items-center">
                                                    <div
                                                        className={`flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium ${
                                                            isCompleted
                                                                ? 'bg-green-500 text-white'
                                                                : isCurrent
                                                                  ? 'bg-blue-500 text-white'
                                                                  : 'bg-gray-200 text-gray-600'
                                                        }`}
                                                    >
                                                        {isCompleted ? (
                                                            <CheckCircle2 className="h-4 w-4" />
                                                        ) : (
                                                            step.step_order
                                                        )}
                                                    </div>
                                                    <span
                                                        className={`ml-2 text-xs whitespace-nowrap ${
                                                            isCurrent
                                                                ? 'font-semibold text-gray-900'
                                                                : 'text-gray-500'
                                                        }`}
                                                    >
                                                        {step.office_name}
                                                    </span>
                                                    {index < workflowSteps.length - 1 && (
                                                        <div
                                                            className={`w-8 h-0.5 mx-2 ${
                                                                isCompleted
                                                                    ? 'bg-green-500'
                                                                    : 'bg-gray-200'
                                                            }`}
                                                        />
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Endorsement Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Endorsement Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-6">
                                {/* Action Taken */}
                                <div className="space-y-2">
                                    <Label htmlFor="action_taken_id">
                                        Action Taken <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.action_taken_id}
                                        onValueChange={(value) =>
                                            setData('action_taken_id', value)
                                        }
                                    >
                                        <SelectTrigger
                                            className={errors.action_taken_id ? 'border-red-500' : ''}
                                        >
                                            <SelectValue placeholder="Select action taken..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {actionTakenOptions.map((action) => (
                                                <SelectItem
                                                    key={action.id}
                                                    value={action.id.toString()}
                                                >
                                                    {action.description}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.action_taken_id && (
                                        <p className="text-sm text-red-500">
                                            {errors.action_taken_id}
                                        </p>
                                    )}
                                </div>

                                {/* Target Office */}
                                <div className="space-y-2">
                                    <Label htmlFor="to_office_id">
                                        Target Office <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.to_office_id}
                                        onValueChange={(value) => setData('to_office_id', value)}
                                    >
                                        <SelectTrigger
                                            className={errors.to_office_id ? 'border-red-500' : ''}
                                        >
                                            <SelectValue placeholder="Select target office..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {officeOptions.map((office) => (
                                                <SelectItem
                                                    key={office.id}
                                                    value={office.id.toString()}
                                                >
                                                    {office.name}
                                                    {expectedNextOffice?.id === office.id &&
                                                        ' (Next in workflow)'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.to_office_id && (
                                        <p className="text-sm text-red-500">{errors.to_office_id}</p>
                                    )}

                                    {/* Out of Workflow Warning */}
                                    {isOutOfWorkflow && (
                                        <Alert variant="destructive" className="bg-yellow-50 border-yellow-200">
                                            <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                            <AlertDescription className="text-yellow-800">
                                                This office is not the expected next step in the
                                                workflow. The endorsement will be marked as
                                                out-of-workflow.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* In Workflow Indicator */}
                                    {expectedNextOffice &&
                                        data.to_office_id !== '' &&
                                        parseInt(data.to_office_id) === expectedNextOffice.id && (
                                            <div className="flex items-center gap-2 text-sm text-green-600">
                                                <CheckCircle2 className="h-4 w-4" />
                                                <span>Next in workflow</span>
                                            </div>
                                        )}
                                </div>

                                {/* Notes */}
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes (Optional)</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Enter any notes for this endorsement..."
                                        rows={4}
                                        maxLength={1000}
                                        className={errors.notes ? 'border-red-500' : ''}
                                    />
                                    <div className="flex justify-between text-xs text-gray-500">
                                        <span>{errors.notes || ''}</span>
                                        <span>{data.notes.length}/1000</span>
                                    </div>
                                </div>

                                {/* Action Buttons */}
                                <div className="flex justify-end gap-4 pt-4 border-t">
                                    <Button variant="outline" type="button" asChild>
                                        <Link href={route(entityShowRoute.route, entityShowRoute.id)}>
                                            Cancel
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        <Send className="mr-2 h-4 w-4" />
                                        {processing ? 'Endorsing...' : 'Endorse Transaction'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
