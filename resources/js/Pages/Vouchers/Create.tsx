import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import type { Procurement, PurchaseRequest, PurchaseOrder } from '@/types/models';

interface Props {
    procurement: Procurement;
    purchaseRequest: PurchaseRequest;
    purchaseOrder: PurchaseOrder;
}

export default function Create({ procurement, purchaseRequest, purchaseOrder }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        reference_number: '',
        payee: '',
        workflow_id: null as number | null,
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('procurements.vouchers.store', procurement.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create Voucher
                </h2>
            }
        >
            <Head title="Create Voucher" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
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
                                <span className="font-semibold">Particular:</span>{' '}
                                {procurement.particular?.description || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Purpose:</span> {procurement.purpose}
                            </div>
                            <div>
                                <span className="font-semibold">ABC Amount:</span>{' '}
                                {formatCurrency(procurement.abc_amount)}
                            </div>
                        </div>
                    </div>

                    {/* Related Purchase Request Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Related Purchase Request</h3>
                        </div>
                        <div className="p-6 space-y-2">
                            <div>
                                <span className="font-semibold">PR Reference Number:</span>{' '}
                                <Link
                                    href={route('purchase-requests.show', purchaseRequest.id)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    {purchaseRequest.transaction?.reference_number || 'N/A'}
                                </Link>
                            </div>
                            <div>
                                <span className="font-semibold">Fund Type:</span>{' '}
                                {purchaseRequest.fund_type?.name} ({purchaseRequest.fund_type?.abbreviation})
                            </div>
                        </div>
                    </div>

                    {/* Related Purchase Order Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Related Purchase Order</h3>
                        </div>
                        <div className="p-6 space-y-2">
                            <div>
                                <span className="font-semibold">PO Reference Number:</span>{' '}
                                <Link
                                    href={route('purchase-orders.show', purchaseOrder.id)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    {purchaseOrder.transaction?.reference_number || 'N/A'}
                                </Link>
                            </div>
                            <div>
                                <span className="font-semibold">Supplier:</span> {purchaseOrder.supplier?.name || 'N/A'}
                            </div>
                            <div>
                                <span className="font-semibold">Contract Price:</span>{' '}
                                {formatCurrency(purchaseOrder.contract_price)}
                            </div>
                        </div>
                    </div>

                    {/* Voucher Form */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Voucher Details</h3>
                        </div>
                        <div className="p-6">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <InputLabel htmlFor="reference_number" value="Reference Number *" />
                                    <TextInput
                                        id="reference_number"
                                        type="text"
                                        value={data.reference_number}
                                        onChange={(e) => setData('reference_number', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                        maxLength={50}
                                    />
                                    <InputError message={errors.reference_number} className="mt-2" />
                                    <p className="mt-1 text-sm text-gray-600">
                                        Free-text format (e.g., VCH-2025-001, VCH-GAA-2025-10-042). Admin-defined patterns allowed per year.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel htmlFor="payee" value="Payee *" />
                                    <TextInput
                                        id="payee"
                                        type="text"
                                        value={data.payee}
                                        onChange={(e) => setData('payee', e.target.value)}
                                        className="mt-1 block w-full"
                                        placeholder="Enter payee name"
                                        required
                                        maxLength={255}
                                    />
                                    <InputError message={errors.payee} className="mt-2" />
                                    <p className="mt-1 text-sm text-gray-600">
                                        Free-text field (max 255 characters). Can differ from PO supplier if needed.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel htmlFor="workflow_id" value="Workflow" />
                                    <select
                                        id="workflow_id"
                                        value={data.workflow_id ?? ''}
                                        onChange={(e) =>
                                            setData('workflow_id', e.target.value ? Number(e.target.value) : null)
                                        }
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        disabled
                                    >
                                        <option value="">Workflow routing available in Epic 3</option>
                                    </select>
                                    <p className="mt-1 text-sm text-gray-600">Workflow routing will be enabled in Epic 3</p>
                                </div>

                                <div className="flex items-center gap-4">
                                    <PrimaryButton disabled={processing}>Create Voucher</PrimaryButton>
                                    <Link
                                        href={route('procurements.show', procurement.id)}
                                        className="text-sm text-gray-600 hover:text-gray-900"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
