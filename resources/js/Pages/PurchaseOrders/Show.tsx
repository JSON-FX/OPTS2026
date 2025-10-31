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

interface PurchaseOrder {
    id: number;
    transaction: Transaction;
    supplier?: {
        id: number;
        name: string;
    };
    supplier_address: string;
    contract_price: number;
}

interface PurchaseRequest {
    id: number;
    transaction?: {
        id: number;
        reference_number: string;
    };
}

interface Props {
    purchaseOrder: PurchaseOrder;
    purchaseRequest?: PurchaseRequest;
    canEdit: boolean;
}

export default function Show({ purchaseOrder, purchaseRequest, canEdit }: Props) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };
    const handleDelete = () => {
        if (confirm('Deleting this Purchase Order will prevent Voucher creation. This action will be logged. Continue?')) {
            router.delete(route('purchase-orders.destroy', purchaseOrder.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Purchase Order Details
                </h2>
            }
        >
            <Head title={`Purchase Order ${purchaseOrder.transaction.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <div className="flex items-center gap-3">
                                <h3 className="text-3xl font-bold">{purchaseOrder.transaction.reference_number}</h3>
                                {Boolean(purchaseOrder.transaction.is_continuation) && (
                                    <span className="inline-block px-3 py-1 rounded-full bg-purple-100 text-purple-800 text-sm font-semibold">
                                        CONTINUATION
                                    </span>
                                )}
                            </div>
                            <span className="inline-block mt-2 px-3 py-1 rounded-full bg-gray-100 text-sm">
                                {purchaseOrder.transaction.status}
                            </span>
                        </div>
                        <div className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="font-semibold">Created By:</span>{' '}
                                    {purchaseOrder.transaction.created_by?.name || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created At:</span>{' '}
                                    {new Date(purchaseOrder.transaction.created_at).toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Purchase Order Details */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Purchase Order Details</h3>
                        </div>
                        <div className="p-6">
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">Supplier</p>
                                    <p className="font-medium text-lg">{purchaseOrder.supplier?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">Contract Price</p>
                                    <p className="font-medium text-lg text-green-600">
                                        {formatCurrency(purchaseOrder.contract_price)}
                                    </p>
                                </div>
                                <div className="col-span-2">
                                    <p className="text-sm text-gray-600 mb-1">Supplier Address (Snapshot)</p>
                                    <p className="font-medium">{purchaseOrder.supplier_address}</p>
                                    <p className="text-xs text-gray-500 mt-1">
                                        This is a snapshot of the supplier's address at the time of PO creation
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Related Purchase Request */}
                    {purchaseRequest && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Related Purchase Request</h3>
                            </div>
                            <div className="p-6">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">PR Reference Number</p>
                                    <Link
                                        href={route('purchase-requests.show', purchaseRequest.id)}
                                        className="text-blue-600 hover:underline font-medium text-lg"
                                    >
                                        {purchaseRequest.transaction?.reference_number || 'N/A'}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Procurement Details */}
                    {purchaseOrder.transaction.procurement && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Procurement Details</h3>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Procurement ID</p>
                                        <Link
                                            href={route('procurements.show', purchaseOrder.transaction.procurement_id)}
                                            className="text-blue-600 hover:underline font-medium"
                                        >
                                            #{purchaseOrder.transaction.procurement.id}
                                        </Link>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">End User Office</p>
                                        <p className="font-medium">{purchaseOrder.transaction.procurement.end_user?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Particular</p>
                                        <p className="font-medium">{purchaseOrder.transaction.procurement.particular?.description || purchaseOrder.transaction.procurement.particular?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">ABC Amount</p>
                                        <p className="font-medium text-lg">
                                            {new Intl.NumberFormat('en-PH', {
                                                style: 'currency',
                                                currency: 'PHP',
                                            }).format(purchaseOrder.transaction.procurement.abc_amount || 0)}
                                        </p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-sm text-gray-600 mb-1">Purpose</p>
                                        <p className="font-medium">{purchaseOrder.transaction.procurement.purpose}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {canEdit && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 flex gap-4">
                                <Link
                                    href={route('purchase-orders.edit', purchaseOrder.id)}
                                    className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={handleDelete}
                                    className="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
