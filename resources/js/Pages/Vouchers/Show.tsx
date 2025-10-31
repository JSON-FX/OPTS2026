import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import type { Voucher, PurchaseRequest, PurchaseOrder } from '@/types/models';

interface Props {
    voucher: Voucher;
    purchaseRequest?: PurchaseRequest;
    purchaseOrder?: PurchaseOrder;
    canEdit: boolean;
}

export default function Show({ voucher, purchaseRequest, purchaseOrder, canEdit }: Props) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
        }).format(amount);
    };

    const handleDelete = () => {
        if (
            confirm(
                'Deleting vouchers may violate audit trail requirements. Are you sure? This action creates a deletion record.'
            )
        ) {
            router.delete(route('vouchers.destroy', voucher.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Voucher Details</h2>
            }
        >
            <Head title={`Voucher ${voucher.transaction?.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Voucher Header */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-3xl font-bold">{voucher.transaction?.reference_number}</h3>
                            <span className="inline-block mt-2 px-3 py-1 rounded-full bg-gray-100 text-sm">
                                {voucher.transaction?.status}
                            </span>
                        </div>
                        <div className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="font-semibold">Created By:</span>{' '}
                                    {voucher.transaction?.created_by?.name || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created At:</span>{' '}
                                    {new Date(voucher.transaction?.created_at || '').toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Voucher Details */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-medium">Voucher Details</h3>
                        </div>
                        <div className="p-6">
                            <div>
                                <p className="text-sm text-gray-600 mb-1">Payee</p>
                                <p className="font-medium text-lg">{voucher.payee}</p>
                            </div>
                        </div>
                    </div>

                    {/* Related Purchase Order */}
                    {purchaseOrder && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Related Purchase Order</h3>
                            </div>
                            <div className="p-6">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">PO Reference Number</p>
                                    <Link
                                        href={route('purchase-orders.show', purchaseOrder.id)}
                                        className="text-blue-600 hover:underline font-medium text-lg"
                                    >
                                        {purchaseOrder.transaction?.reference_number || 'N/A'}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}

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

                    {/* Related Procurement */}
                    {voucher.transaction?.procurement && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Related Procurement</h3>
                            </div>
                            <div className="p-6 space-y-2">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">Procurement ID</p>
                                    <Link
                                        href={route('procurements.show', voucher.transaction.procurement_id)}
                                        className="text-blue-600 hover:underline font-medium"
                                    >
                                        #{voucher.transaction.procurement.id}
                                    </Link>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">End User</p>
                                    <p className="font-medium">{voucher.transaction.procurement.end_user?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">Particular</p>
                                    <p className="font-medium">
                                        {voucher.transaction.procurement.particular?.description || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Edit/Delete Actions */}
                    {canEdit && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 flex gap-4">
                                <Link
                                    href={route('vouchers.edit', voucher.id)}
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
