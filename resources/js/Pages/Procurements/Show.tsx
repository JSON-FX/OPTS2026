import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import {
    Office,
    Particular,
    Procurement,
    ProcurementStatusHistory,
    PurchaseOrder,
    PurchaseRequest,
    Voucher,
} from '@/types/models';

interface Props extends PageProps {
    procurement: Procurement & {
        end_user?: Pick<Office, 'id' | 'name' | 'abbreviation'>;
        particular?: Pick<Particular, 'id' | 'description'>;
        creator?: { id: number; name: string };
        purchase_request?: PurchaseRequest | null;
        purchase_order?: PurchaseOrder | null;
        voucher?: Voucher | null;
        status_history?: (ProcurementStatusHistory & {
            changed_by?: { id: number; name: string };
        })[];
        transactions_count?: number;
    };
    can: {
        manage: boolean;
    };
    canCreatePR?: boolean;
}

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

export default function Show({ auth, procurement, can, canCreatePR }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Procurement #{procurement.id}
                    </h2>
                    {can.manage && (
                        <Link
                            href={route('procurements.edit', procurement.id)}
                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                            Edit Procurement
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={`Procurement #${procurement.id}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900">Overview</h3>
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">End User Office</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.end_user?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Particular</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.particular?.description ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Purpose</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.purpose ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">ABC Amount</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {currencyFormatter.format(Number(procurement.abc_amount))}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Date of Entry</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {new Date(procurement.date_of_entry).toLocaleDateString()}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Status</dt>
                                    <dd className="mt-1 inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-800">
                                        {procurement.status}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created By</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.creator?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Linked Transactions</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.transactions_count ?? 0}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900">Linked Transactions</h3>
                            <div className="mt-4 space-y-4">
                                {/* Purchase Request */}
                                <div className="rounded-lg border border-gray-200 p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <span className="font-semibold text-gray-900">Purchase Request:</span>
                                            {procurement.purchase_request ? (
                                                <div className="mt-2 space-y-1">
                                                    <div>
                                                        <Link
                                                            href={route(
                                                                'purchase-requests.show',
                                                                procurement.purchase_request.id
                                                            )}
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            {procurement.purchase_request.transaction?.reference_number}
                                                        </Link>
                                                    </div>
                                                    <div className="text-sm text-gray-600">
                                                        Fund Type:{' '}
                                                        {procurement.purchase_request.fund_type?.name || 'N/A'}
                                                    </div>
                                                    <div className="text-sm text-gray-600">
                                                        Status: {procurement.purchase_request.transaction?.status}
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="mt-2 text-sm text-gray-500">Not yet created</div>
                                            )}
                                        </div>
                                        {!procurement.purchase_request && canCreatePR && can.manage && (
                                            <Link
                                                href={route(
                                                    'procurements.purchase-requests.create',
                                                    procurement.id
                                                )}
                                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                            >
                                                Add Purchase Request
                                            </Link>
                                        )}
                                    </div>
                                </div>

                                {/* Purchase Order */}
                                <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
                                    <span className="font-semibold text-gray-900">Purchase Order:</span>
                                    <div className="mt-2 text-sm text-gray-500">
                                        {procurement.purchase_order ? 'Created' : 'Not yet created'}
                                    </div>
                                </div>

                                {/* Voucher */}
                                <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
                                    <span className="font-semibold text-gray-900">Voucher:</span>
                                    <div className="mt-2 text-sm text-gray-500">
                                        {procurement.voucher ? 'Created' : 'Not yet created'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900">Status History</h3>
                            <div className="mt-4 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-700">Date</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-700">From</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-700">To</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-700">Changed By</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-700">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {(procurement.status_history ?? []).length > 0 ? (
                                            procurement.status_history!.map((entry) => (
                                                <tr key={`${entry.id}-${entry.created_at}`}>
                                                    <td className="whitespace-nowrap px-3 py-2 text-gray-900">
                                                        {new Date(entry.created_at).toLocaleString()}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-gray-600">
                                                        {entry.old_status ?? '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-gray-600">
                                                        {entry.new_status}
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 text-gray-600">
                                                        {entry.changed_by?.name ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-gray-600">
                                                        {entry.reason ?? '—'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={5} className="px-3 py-4 text-center text-gray-500">
                                                    No status changes recorded yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-between">
                        <Link
                            href={route('procurements.index')}
                            className="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            Back to list
                        </Link>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
