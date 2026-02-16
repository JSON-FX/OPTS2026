import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import WorkflowPreviewCard, { WorkflowPreview } from '@/Components/WorkflowPreviewCard';

interface FundType {
    id: number;
    name: string;
    abbreviation: string;
}

interface Procurement {
    id: number;
    end_user?: { name: string };
    particular?: { description: string };
    purpose: string;
    abc_amount: number;
}

interface WorkflowStep {
    id: number;
    step_order: number;
    office: { id: number; name: string; abbreviation: string } | null;
    expected_days: number;
}

interface Workflow {
    id: number;
    name: string;
    description: string | null;
    category: string;
    steps: WorkflowStep[];
}

interface Props {
    procurement: Procurement;
    fundTypes: FundType[];
    workflows: Workflow[];
    workflowPreview: WorkflowPreview | null;
}

export default function Create({ procurement, fundTypes, workflows, workflowPreview }: Props) {
    const currentDate = new Date();
    const { data, setData, post, processing, errors } = useForm({
        fund_type_id: '',
        workflow_id: null as number | null,
        is_continuation: false,
        ref_year: currentDate.getFullYear().toString(),
        ref_month: String(currentDate.getMonth() + 1).padStart(2, '0'),
        ref_number: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('procurements.purchase-requests.store', procurement.id));
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const formatReferencePreview = () => {
        if (!data.fund_type_id || !data.ref_year || !data.ref_month || !data.ref_number) {
            return '(Select fund type and enter reference number details)';
        }
        const prefix = data.is_continuation ? 'CONT-' : '';
        const fundTypeAbbr = fundTypes.find(ft => ft.id.toString() === data.fund_type_id)?.abbreviation || '';
        return `${prefix}PR-${fundTypeAbbr}-${data.ref_year}-${data.ref_month}-${data.ref_number}`;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Purchase Request
                    </h2>
                </div>
            }
        >
            <Head title="Create Purchase Request" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Breadcrumb */}
                    <div className="text-sm text-gray-600">
                        <Link href={route('dashboard')} className="hover:text-gray-900">Home</Link>
                        {' > '}
                        <Link href={route('procurements.index')} className="hover:text-gray-900">Procurements</Link>
                        {' > '}
                        <Link href={route('procurements.show', procurement.id)} className="hover:text-gray-900">
                            Procurement #{procurement.id}
                        </Link>
                        {' > '}
                        <span>Create Purchase Request</span>
                    </div>

                    {/* Procurement Summary Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Procurement Summary</h3>
                            <p className="text-sm text-gray-600">Read-only procurement information</p>
                        </div>
                        <div className="p-6 space-y-2">
                            <div>
                                <span className="font-semibold">Procurement ID:</span>{' '}
                                <Link
                                    href={route('procurements.show', procurement.id)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    #{procurement.id}
                                </Link>
                            </div>
                            <div>
                                <span className="font-semibold">End User:</span> {procurement.end_user?.name || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Particular:</span> {procurement.particular?.description || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Purpose:</span> {procurement.purpose}
                            </div>
                            <div>
                                <span className="font-semibold">ABC Amount:</span> {formatCurrency(procurement.abc_amount)}
                            </div>
                        </div>
                    </div>

                    {/* PR Creation Form */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Purchase Request Details</h3>
                        </div>
                        <div className="p-6">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <InputLabel htmlFor="fund_type_id" value="Fund Type *" />
                                    <select
                                        id="fund_type_id"
                                        value={data.fund_type_id}
                                        onChange={(e) => setData('fund_type_id', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select fund type</option>
                                        {fundTypes.map((fundType) => (
                                            <option key={fundType.id} value={fundType.id.toString()}>
                                                {fundType.name} ({fundType.abbreviation})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.fund_type_id} className="mt-2" />
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center">
                                        <input
                                            id="is_continuation"
                                            type="checkbox"
                                            checked={data.is_continuation}
                                            onChange={(e) => setData('is_continuation', e.target.checked)}
                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                        <label htmlFor="is_continuation" className="ml-2 text-sm text-gray-700">
                                            This is a continuation PR from a previous year
                                        </label>
                                    </div>
                                    <InputError message={errors.is_continuation} className="mt-2" />

                                    <div>
                                        <InputLabel value="Reference Number Components *" />
                                        <div className="grid grid-cols-3 gap-4 mt-2">
                                            <div>
                                                <InputLabel htmlFor="ref_year" value="Year (YYYY)" />
                                                <TextInput
                                                    id="ref_year"
                                                    type="text"
                                                    value={data.ref_year}
                                                    onChange={(e) => setData('ref_year', e.target.value)}
                                                    maxLength={4}
                                                    pattern="\d{4}"
                                                    className="mt-1 block w-full"
                                                    placeholder="2025"
                                                />
                                                <InputError message={errors.ref_year} className="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="ref_month" value="Month (MM)" />
                                                <TextInput
                                                    id="ref_month"
                                                    type="text"
                                                    value={data.ref_month}
                                                    onChange={(e) => setData('ref_month', e.target.value)}
                                                    maxLength={2}
                                                    pattern="(0[1-9]|1[0-2])"
                                                    className="mt-1 block w-full"
                                                    placeholder="10"
                                                />
                                                <InputError message={errors.ref_month} className="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="ref_number" value="Number" />
                                                <TextInput
                                                    id="ref_number"
                                                    type="text"
                                                    value={data.ref_number}
                                                    onChange={(e) => setData('ref_number', e.target.value)}
                                                    maxLength={50}
                                                    className="mt-1 block w-full"
                                                    placeholder="001"
                                                />
                                                <InputError message={errors.ref_number} className="mt-2" />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
                                        <p className="text-sm font-medium text-gray-700 mb-2">Reference Number Preview:</p>
                                        <p className="text-2xl font-bold text-indigo-600">{formatReferencePreview()}</p>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="workflow_id" value="Workflow" />
                                    <select
                                        id="workflow_id"
                                        value={data.workflow_id?.toString() ?? ''}
                                        onChange={(e) => setData('workflow_id', e.target.value ? Number(e.target.value) : null)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Select workflow (optional)</option>
                                        {workflows.map((workflow) => (
                                            <option key={workflow.id} value={workflow.id.toString()}>
                                                {workflow.name}
                                                {workflow.steps.length > 0 && ` (${workflow.steps.length} steps)`}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.workflow_id} className="mt-2" />
                                    {data.workflow_id && (() => {
                                        const selected = workflows.find(w => w.id === data.workflow_id);
                                        if (!selected || selected.steps.length === 0) return null;
                                        return (
                                            <div className="mt-2 text-sm text-gray-500">
                                                <span className="font-medium">Route: </span>
                                                {selected.steps
                                                    .sort((a, b) => a.step_order - b.step_order)
                                                    .map(s => s.office?.abbreviation || s.office?.name || 'Unknown')
                                                    .join(' \u2192 ')}
                                            </div>
                                        );
                                    })()}
                                    {workflowPreview && (
                                        <div className="mt-3">
                                            <WorkflowPreviewCard preview={workflowPreview} />
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-4">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Purchase Request'}
                                    </PrimaryButton>
                                    <button
                                        type="button"
                                        onClick={() => window.history.back()}
                                        className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
