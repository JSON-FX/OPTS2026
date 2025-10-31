import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

interface FundType {
    id: number;
    name: string;
    abbreviation: string;
    deleted_at: string | null;
}

interface Transaction {
    id: number;
    reference_number: string;
    is_continuation: boolean;
}

interface PurchaseRequest {
    id: number;
    fund_type_id: number;
    workflow_id: number | null;
    transaction: Transaction;
    fund_type?: FundType;
}

interface Props {
    purchaseRequest: PurchaseRequest;
    fundTypes: FundType[];
}

// Parse existing reference number to extract components
function parseReferenceNumber(refNumber: string): { year: string; month: string; number: string; isContinuation: boolean } {
    const isContinuation = refNumber.startsWith('CONT-');
    const cleanRef = isContinuation ? refNumber.substring(5) : refNumber;

    // Format: PR-{FUNDTYPE}-{YEAR}-{MONTH}-{NUMBER}
    const parts = cleanRef.split('-');
    if (parts.length >= 5 && parts[0] === 'PR') {
        return {
            year: parts[2],
            month: parts[3],
            number: parts.slice(4).join('-'), // Handle numbers with hyphens
            isContinuation
        };
    }

    // Fallback
    return { year: '', month: '', number: '', isContinuation: false };
}

export default function Edit({ purchaseRequest, fundTypes }: Props) {
    const parsedRef = parseReferenceNumber(purchaseRequest.transaction.reference_number);

    const { data, setData, put, processing, errors } = useForm({
        fund_type_id: purchaseRequest.fund_type_id.toString(),
        workflow_id: purchaseRequest.workflow_id,
        is_continuation: parsedRef.isContinuation,
        ref_year: parsedRef.year,
        ref_month: parsedRef.month,
        ref_number: parsedRef.number,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('purchase-requests.update', purchaseRequest.id));
    };

    const formatReferencePreview = () => {
        if (!data.fund_type_id || !data.ref_year || !data.ref_month || !data.ref_number) {
            return '(Complete all reference number fields)';
        }
        const prefix = data.is_continuation ? 'CONT-' : '';
        const fundTypeAbbr = fundTypes.find(ft => ft.id.toString() === data.fund_type_id)?.abbreviation || '';
        return `${prefix}PR-${fundTypeAbbr}-${data.ref_year}-${data.ref_month}-${data.ref_number}`;
    };

    const isFundTypeSoftDeleted = purchaseRequest.fund_type?.deleted_at !== null;
    const isReferenceChanged = formatReferencePreview() !== purchaseRequest.transaction.reference_number;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Purchase Request
                </h2>
            }
        >
            <Head title="Edit Purchase Request" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {isFundTypeSoftDeleted && (
                        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            Selected fund type is no longer active. Please choose another.
                        </div>
                    )}

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Edit Purchase Request Details</h3>
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
                                        {isReferenceChanged && (
                                            <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded p-3">
                                                <p className="text-sm text-yellow-800">
                                                    <strong>⚠️ Warning:</strong> Changing the reference number will affect system records.
                                                    Previous reference: <span className="font-mono">{purchaseRequest.transaction.reference_number}</span>
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="workflow_id" value="Workflow" />
                                    <select
                                        id="workflow_id"
                                        disabled
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-100"
                                    >
                                        <option>Workflow routing available in Epic 3</option>
                                    </select>
                                    <p className="mt-2 text-sm text-gray-500">Workflow routing will be available in Epic 3</p>
                                </div>

                                <div className="flex gap-4">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Updating...' : 'Update Purchase Request'}
                                    </PrimaryButton>
                                    <button
                                        type="button"
                                        onClick={() => window.history.back()}
                                        className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
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
