import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';

interface Procurement {
    id: number;
    purpose: string;
    end_user?: { name: string };
    particular?: { name: string };
}

interface Props {
    procurement: Procurement;
}

export default function Create({ procurement }: Props) {
    const currentDate = new Date();
    const { data, setData, post, processing, errors } = useForm({
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
        is_continuation: false,
        ref_year: currentDate.getFullYear().toString(),
        ref_month: String(currentDate.getMonth() + 1).padStart(2, '0'),
        ref_number: '',
        workflow_id: null as number | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('procurements.purchase-orders.store', procurement.id));
    };

    const formatReferencePreview = () => {
        if (!data.ref_year || !data.ref_month || !data.ref_number) {
            return '(Enter reference number details)';
        }
        const prefix = data.is_continuation ? 'CONT-' : '';
        return `${prefix}PO-${data.ref_year}-${data.ref_month}-${data.ref_number}`;
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Purchase Order
                    </h2>
                </div>
            }
        >
            <Head title="Create Purchase Order" />

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
                        <span>Create Purchase Order</span>
                    </div>

                    {/* Procurement Summary Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Procurement Summary</h3>
                        </div>
                        <div className="p-6 space-y-2">
                            <div>
                                <span className="font-semibold">Procurement ID:</span> #{procurement.id}
                            </div>
                            <div>
                                <span className="font-semibold">End User:</span> {procurement.end_user?.name || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Particular:</span> {procurement.particular?.name || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Purpose:</span> {procurement.purpose}
                            </div>
                        </div>
                    </div>

                    {/* PO Form */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Purchase Order Details</h3>
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
                                        {processing ? 'Creating...' : 'Create Purchase Order'}
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
