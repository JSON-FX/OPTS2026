import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

interface Transaction {
    id: number;
    reference_number: string;
    is_continuation: boolean;
}

interface PurchaseOrder {
    id: number;
    transaction: Transaction;
}

interface Props {
    purchaseOrder: PurchaseOrder;
}

// Parse existing reference number to extract components
function parseReferenceNumber(refNumber: string): { year: string; month: string; number: string; isContinuation: boolean } {
    const isContinuation = refNumber.startsWith('CONT-');
    const cleanRef = isContinuation ? refNumber.substring(5) : refNumber;

    // Format: PO-{YEAR}-{MONTH}-{NUMBER}
    const parts = cleanRef.split('-');
    if (parts.length >= 4 && parts[0] === 'PO') {
        return {
            year: parts[1],
            month: parts[2],
            number: parts.slice(3).join('-'), // Handle numbers with hyphens
            isContinuation
        };
    }

    // Fallback
    return { year: '', month: '', number: '', isContinuation: false };
}

export default function Edit({ purchaseOrder }: Props) {
    const parsedRef = parseReferenceNumber(purchaseOrder.transaction.reference_number);

    const { data, setData, put, processing, errors } = useForm({
        supplier_id: '',
        supplier_address: '',
        purchase_request_id: '',
        particulars: '',
        fund_type_id: '',
        total_cost: '',
        date_of_po: '',
        delivery_date: '',
        delivery_term: '',
        payment_term: '',
        amount_in_words: '',
        mode_of_procurement: '',
        is_continuation: parsedRef.isContinuation,
        ref_year: parsedRef.year,
        ref_month: parsedRef.month,
        ref_number: parsedRef.number,
        workflow_id: null as number | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('purchase-orders.update', purchaseOrder.id));
    };

    const formatReferencePreview = () => {
        if (!data.ref_year || !data.ref_month || !data.ref_number) {
            return '(Complete all reference number fields)';
        }
        const prefix = data.is_continuation ? 'CONT-' : '';
        return `${prefix}PO-${data.ref_year}-${data.ref_month}-${data.ref_number}`;
    };

    const isReferenceChanged = formatReferencePreview() !== purchaseOrder.transaction.reference_number;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Purchase Order
                </h2>
            }
        >
            <Head title="Edit Purchase Order" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Edit Purchase Order Details</h3>
                        </div>
                        <div className="p-6">
                            <form onSubmit={submit} className="space-y-6">
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
                                            This is a continuation PO from a previous year
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
                                                    Previous reference: <span className="font-mono">{purchaseOrder.transaction.reference_number}</span>
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
                                        {processing ? 'Updating...' : 'Update Purchase Order'}
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
