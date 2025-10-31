import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Transaction {
    id: number;
    reference_number: string;
    is_continuation: boolean;
    status: string;
    created_at: string;
    procurement_id: number;
    procurement?: {
        id: number;
        end_user?: { name: string };
        particular?: { name: string; description?: string };
        purpose: string;
        abc_amount: number;
    };
    created_by?: {
        name: string;
    };
}

interface FundType {
    id: number;
    name: string;
    abbreviation: string;
}

interface PurchaseRequest {
    id: number;
    transaction: Transaction;
    fund_type?: FundType;
}

interface Props {
    purchaseRequest: PurchaseRequest;
    canEdit: boolean;
    canDelete: boolean;
}

export default function Show({ purchaseRequest, canEdit, canDelete }: Props) {
    const handleDelete = () => {
        if (confirm('Deleting this Purchase Request will prevent PO/VCH creation. This action will be logged. Continue?')) {
            router.delete(route('purchase-requests.destroy', purchaseRequest.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Purchase Request Details
                </h2>
            }
        >
            <Head title={`Purchase Request ${purchaseRequest.transaction.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <div className="flex items-center gap-3">
                                <h3 className="text-3xl font-bold">{purchaseRequest.transaction.reference_number}</h3>
                                {Boolean(purchaseRequest.transaction.is_continuation) && (
                                    <span className="inline-block px-3 py-1 rounded-full bg-purple-100 text-purple-800 text-sm font-semibold">
                                        CONTINUATION
                                    </span>
                                )}
                            </div>
                            <span className="inline-block mt-2 px-3 py-1 rounded-full bg-gray-100 text-sm">
                                {purchaseRequest.transaction.status}
                            </span>
                        </div>
                        <div className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="font-semibold">Fund Type:</span>{' '}
                                    {purchaseRequest.fund_type
                                        ? `${purchaseRequest.fund_type.name} (${purchaseRequest.fund_type.abbreviation})`
                                        : 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created By:</span>{' '}
                                    {purchaseRequest.transaction.created_by?.name || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created At:</span>{' '}
                                    {new Date(purchaseRequest.transaction.created_at).toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </div>

                    {purchaseRequest.transaction.procurement && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Procurement Details</h3>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Procurement ID</p>
                                        <Link
                                            href={route('procurements.show', purchaseRequest.transaction.procurement_id)}
                                            className="text-blue-600 hover:underline font-medium"
                                        >
                                            #{purchaseRequest.transaction.procurement.id}
                                        </Link>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">End User Office</p>
                                        <p className="font-medium">{purchaseRequest.transaction.procurement.end_user?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Particular</p>
                                        <p className="font-medium">{purchaseRequest.transaction.procurement.particular?.description || purchaseRequest.transaction.procurement.particular?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">ABC Amount</p>
                                        <p className="font-medium text-lg">
                                            {new Intl.NumberFormat('en-PH', {
                                                style: 'currency',
                                                currency: 'PHP',
                                            }).format(purchaseRequest.transaction.procurement.abc_amount || 0)}
                                        </p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-sm text-gray-600 mb-1">Purpose</p>
                                        <p className="font-medium">{purchaseRequest.transaction.procurement.purpose}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {(canEdit || canDelete) && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 flex gap-4">
                                {canEdit && (
                                    <button
                                        onClick={() => router.get(route('purchase-requests.edit', purchaseRequest.id))}
                                        className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                    >
                                        Edit
                                    </button>
                                )}
                                {canDelete && (
                                    <button
                                        onClick={handleDelete}
                                        className="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
