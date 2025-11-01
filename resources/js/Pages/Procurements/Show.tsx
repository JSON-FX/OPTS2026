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
import StatusBadge from '@/Components/StatusBadge';
import RelativeTime from '@/Components/RelativeTime';
import ExpandableText from '@/Components/ExpandableText';
import { TooltipProvider, Tooltip, TooltipTrigger, TooltipContent } from '@/Components/ui/tooltip';
import { ArrowRight } from 'lucide-react';

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
    canCreatePO?: boolean;
    canCreateVCH?: boolean;
}

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

export default function Show({ auth, procurement, can, canCreatePR, canCreatePO, canCreateVCH }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <nav className="mb-2 text-sm text-gray-500">
                            <Link href={route('dashboard')} className="hover:text-gray-700">
                                Home
                            </Link>
                            {' > '}
                            <Link href={route('procurements.index')} className="hover:text-gray-700">
                                Procurements
                            </Link>
                            {' > '}
                            <span className="text-gray-900">Procurement #{procurement.id}</span>
                        </nav>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Procurement #{procurement.id}
                        </h2>
                    </div>
                    {can.manage && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={route('procurements.edit', procurement.id)}
                                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                    >
                                        Edit Procurement
                                    </Link>
                                </TooltipTrigger>
                                {procurement.transactions_count && procurement.transactions_count > 0 && (
                                    <TooltipContent>
                                        Can only edit Purpose, ABC Amount, and Date fields because transactions exist
                                    </TooltipContent>
                                )}
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
            }
        >
            <Head title={`Procurement #${procurement.id}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {/* Procurement Summary Card */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900">Procurement Summary</h3>
                        </div>
                        <div className="p-6">
                            <dl className="grid gap-4 sm:grid-cols-2">
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
                                <div className="sm:col-span-2">
                                    <dt className="text-sm font-medium text-gray-500">Purpose</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.purpose ? (
                                            <ExpandableText text={procurement.purpose} maxLength={500} />
                                        ) : (
                                            '—'
                                        )}
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
                                    <dd className="mt-1">
                                        <StatusBadge status={procurement.status} />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created By</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {procurement.creator?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created</dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        <RelativeTime timestamp={procurement.created_at} />
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {/* Status History Card */}
                    {procurement.status_history && procurement.status_history.length > 0 && (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 p-6">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-lg font-medium text-gray-900">Status History</h3>
                                    {procurement.status_history.length >= 5 && (
                                        <button
                                            type="button"
                                            className="text-sm text-blue-600 hover:underline"
                                        >
                                            View All History
                                        </button>
                                    )}
                                </div>
                            </div>
                            <div className="p-6">
                                <div className="space-y-4">
                                    {procurement.status_history.map((history, index) => (
                                        <div key={history.id} className="flex items-start gap-4">
                                            <div className="flex-shrink-0">
                                                <div className="h-2 w-2 rounded-full bg-blue-600 mt-2"></div>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <StatusBadge status={history.old_status || 'Created'} />
                                                    <ArrowRight className="h-4 w-4 text-gray-400" />
                                                    <StatusBadge status={history.new_status} />
                                                </div>
                                                <p className="mt-1 text-sm text-gray-900">
                                                    {history.changed_by?.name ?? 'System'}
                                                    {' · '}
                                                    <RelativeTime timestamp={history.created_at} />
                                                </p>
                                                {history.reason && (
                                                    <p className="mt-1 text-sm text-gray-600">
                                                        Reason: {history.reason}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Linked Transactions Header */}
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-lg font-medium text-gray-900">Linked Transactions</h3>
                        </div>
                        <div className="p-6 space-y-4">
                            {/* Purchase Request Card */}
                            <div className={`rounded-lg border p-4 ${!procurement.purchase_request ? 'bg-gray-50 border-gray-300' : 'border-gray-200'}`}>
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
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
                                                <div className="flex items-center gap-2 text-sm text-gray-600">
                                                    <span>Status:</span>
                                                    <StatusBadge status={procurement.purchase_request.transaction?.status || 'Created'} />
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Created by: {procurement.purchase_request.transaction?.created_by?.name || 'N/A'}
                                                    {' · '}
                                                    {procurement.purchase_request.transaction?.created_at && (
                                                        <RelativeTime timestamp={procurement.purchase_request.transaction.created_at} />
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="mt-2 text-sm text-gray-500">Not yet created</div>
                                        )}
                                    </div>
                                    {!procurement.purchase_request && can.manage && canCreatePR && (
                                        <Link
                                            href={route('procurements.purchase-requests.create', procurement.id)}
                                            className="ml-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                        >
                                            Add Purchase Request
                                        </Link>
                                    )}
                                </div>
                            </div>

                            {/* Purchase Order Card */}
                            <div className={`rounded-lg border p-4 ${!procurement.purchase_order ? 'bg-gray-50 border-gray-300' : 'border-gray-200'}`}>
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <span className="font-semibold text-gray-900">Purchase Order:</span>
                                        {procurement.purchase_order ? (
                                            <div className="mt-2 space-y-1">
                                                <div>
                                                    <Link
                                                        href={route(
                                                            'purchase-orders.show',
                                                            procurement.purchase_order.id
                                                        )}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        {procurement.purchase_order.transaction?.reference_number}
                                                    </Link>
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Supplier: {procurement.purchase_order.supplier?.name || 'N/A'}
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Address: {procurement.purchase_order.supplier_address || 'N/A'}
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Contract Price: {currencyFormatter.format(Number(procurement.purchase_order.contract_price))}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-gray-600">
                                                    <span>Status:</span>
                                                    <StatusBadge status={procurement.purchase_order.transaction?.status || 'Created'} />
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Created by: {procurement.purchase_order.transaction?.created_by?.name || 'N/A'}
                                                    {' · '}
                                                    {procurement.purchase_order.transaction?.created_at && (
                                                        <RelativeTime timestamp={procurement.purchase_order.transaction.created_at} />
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="mt-2 text-sm text-gray-500">Not yet created</div>
                                        )}
                                    </div>
                                    {!procurement.purchase_order && can.manage && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <span>
                                                        <Link
                                                            href={canCreatePO ? route('procurements.purchase-orders.create', procurement.id) : '#'}
                                                            className={`ml-4 inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold shadow-sm ${
                                                                canCreatePO
                                                                    ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                                                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                            }`}
                                                            {...(!canCreatePO && { onClick: (e) => e.preventDefault() })}
                                                        >
                                                            Add Purchase Order
                                                        </Link>
                                                    </span>
                                                </TooltipTrigger>
                                                {!canCreatePO && (
                                                    <TooltipContent>
                                                        Purchase Request required before creating Purchase Order
                                                    </TooltipContent>
                                                )}
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}
                                </div>
                            </div>

                            {/* Voucher Card */}
                            <div className={`rounded-lg border p-4 ${!procurement.voucher ? 'bg-gray-50 border-gray-300' : 'border-gray-200'}`}>
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <span className="font-semibold text-gray-900">Voucher:</span>
                                        {procurement.voucher ? (
                                            <div className="mt-2 space-y-1">
                                                <div>
                                                    <Link
                                                        href={route(
                                                            'vouchers.show',
                                                            procurement.voucher.id
                                                        )}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        {procurement.voucher.transaction?.reference_number}
                                                    </Link>
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Payee: {procurement.voucher.payee || 'N/A'}
                                                </div>
                                                <div className="flex items-center gap-2 text-sm text-gray-600">
                                                    <span>Status:</span>
                                                    <StatusBadge status={procurement.voucher.transaction?.status || 'Created'} />
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Created by: {procurement.voucher.transaction?.created_by?.name || 'N/A'}
                                                    {' · '}
                                                    {procurement.voucher.transaction?.created_at && (
                                                        <RelativeTime timestamp={procurement.voucher.transaction.created_at} />
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="mt-2 text-sm text-gray-500">Not yet created</div>
                                        )}
                                    </div>
                                    {!procurement.voucher && can.manage && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <span>
                                                        <Link
                                                            href={canCreateVCH ? route('procurements.vouchers.create', procurement.id) : '#'}
                                                            className={`ml-4 inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold shadow-sm ${
                                                                canCreateVCH
                                                                    ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                                                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                                            }`}
                                                            {...(!canCreateVCH && { onClick: (e) => e.preventDefault() })}
                                                        >
                                                            Add Voucher
                                                        </Link>
                                                    </span>
                                                </TooltipTrigger>
                                                {!canCreateVCH && (
                                                    <TooltipContent>
                                                        Purchase Order required before creating Voucher
                                                    </TooltipContent>
                                                )}
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
