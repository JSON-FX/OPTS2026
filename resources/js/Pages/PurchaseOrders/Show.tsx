import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Send, PackageCheck, CheckCircle2, PauseCircle, XCircle, PlayCircle, AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import CompleteTransactionModal from '@/Components/CompleteTransactionModal';
import HoldTransactionModal from '@/Components/HoldTransactionModal';
import CancelTransactionModal from '@/Components/CancelTransactionModal';
import ResumeTransactionModal from '@/Components/ResumeTransactionModal';
import OutOfWorkflowBanner from '@/Components/OutOfWorkflowBanner';
import TransactionTimeline from '@/Components/Timeline/TransactionTimeline';
import ActionHistory from '@/Components/ActionHistory';
import StatusBadge from '@/Components/StatusBadge';
import DelaySeverityBadge from '@/Components/DelaySeverityBadge';
import type { DelaySeverity, TransactionTimeline as TransactionTimelineType, ActionHistoryEntry } from '@/types/models';

interface Transaction {
    id: number;
    reference_number: string;
    is_continuation: boolean;
    status: string;
    created_at: string;
    procurement_id: number;
    current_office_id: number | null;
    received_at: string | null;
    eta_current_step: string | null;
    eta_completion: string | null;
    delay_days: number;
    is_stagnant: boolean;
    delay_severity: DelaySeverity;
    days_at_current_step: number;
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
    current_step?: {
        id: number;
        step_order: number;
        is_final_step: boolean;
        office?: {
            id: number;
            name: string;
        };
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

interface ActionTakenOption {
    id: number;
    description: string;
}

interface Props {
    purchaseOrder: PurchaseOrder;
    purchaseRequest?: PurchaseRequest;
    canEdit: boolean;
    canDelete: boolean;
    canEndorse: boolean;
    cannotEndorseReason: string | null;
    canReceive: boolean;
    cannotReceiveReason: string | null;
    canComplete: boolean;
    cannotCompleteReason: string | null;
    actionTakenOptions: ActionTakenOption[];
    defaultActionTakenId: number | null;
    canHold: boolean;
    cannotHoldReason: string | null;
    canCancel: boolean;
    cannotCancelReason: string | null;
    canResume: boolean;
    cannotResumeReason: string | null;
    outOfWorkflowInfo: { is_out_of_workflow: boolean; expected_office_name: string | null; actual_office_name: string | null } | null;
    timeline: TransactionTimelineType;
    actionHistory: ActionHistoryEntry[];
}

export default function Show({ purchaseOrder, purchaseRequest, canEdit, canDelete, canEndorse, cannotEndorseReason, canReceive, cannotReceiveReason, canComplete, cannotCompleteReason, actionTakenOptions, defaultActionTakenId, canHold, cannotHoldReason, canCancel, cannotCancelReason, canResume, cannotResumeReason, outOfWorkflowInfo, timeline, actionHistory }: Props) {
    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [showCompleteModal, setShowCompleteModal] = useState(false);
    const [showHoldModal, setShowHoldModal] = useState(false);
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [showResumeModal, setShowResumeModal] = useState(false);
    const [receiveNotes, setReceiveNotes] = useState('');
    const [processing, setProcessing] = useState(false);

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

    const transaction = purchaseOrder.transaction;

    const handleReceive = () => {
        setProcessing(true);
        router.post(
            route('transactions.receive.store', transaction.id),
            { notes: receiveNotes || null },
            {
                onFinish: () => {
                    setProcessing(false);
                    setShowReceiveModal(false);
                    setReceiveNotes('');
                },
            }
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Purchase Order Details
                </h2>
            }
        >
            <Head title={`Purchase Order ${transaction.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <h3 className="text-3xl font-bold">{transaction.reference_number}</h3>
                                    {Boolean(transaction.is_continuation) && (
                                        <span className="inline-block px-3 py-1 rounded-full bg-purple-100 text-purple-800 text-sm font-semibold">
                                            CONTINUATION
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    {/* Receive Button */}
                                    {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span>
                                                    <Button
                                                        onClick={() => setShowReceiveModal(true)}
                                                        disabled={!canReceive}
                                                        variant={canReceive ? 'default' : 'secondary'}
                                                        className={
                                                            canReceive
                                                                ? 'bg-blue-600 hover:bg-blue-700'
                                                                : 'cursor-not-allowed'
                                                        }
                                                    >
                                                        <PackageCheck className="mr-2 h-4 w-4" />
                                                        Receive
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            {!canReceive && cannotReceiveReason && (
                                                <TooltipContent>
                                                    <p>{cannotReceiveReason}</p>
                                                </TooltipContent>
                                            )}
                                        </Tooltip>
                                    </TooltipProvider>
                                    )}

                                    {/* Endorse Button */}
                                    {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span>
                                                    <Button
                                                        onClick={() =>
                                                            router.get(
                                                                route(
                                                                    'transactions.endorse.create',
                                                                    transaction.id
                                                                )
                                                            )
                                                        }
                                                        disabled={!canEndorse}
                                                        className={
                                                            canEndorse
                                                                ? 'bg-green-600 hover:bg-green-700'
                                                                : 'bg-gray-400 cursor-not-allowed'
                                                        }
                                                    >
                                                        <Send className="mr-2 h-4 w-4" />
                                                        Endorse
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            {!canEndorse && cannotEndorseReason && (
                                                <TooltipContent>
                                                    <p>{cannotEndorseReason}</p>
                                                </TooltipContent>
                                            )}
                                        </Tooltip>
                                    </TooltipProvider>
                                    )}

                                    {/* Complete Button */}
                                    {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span>
                                                    <Button
                                                        onClick={() => setShowCompleteModal(true)}
                                                        disabled={!canComplete}
                                                        variant={canComplete ? 'default' : 'secondary'}
                                                        className={
                                                            canComplete
                                                                ? 'bg-green-600 hover:bg-green-700'
                                                                : 'cursor-not-allowed'
                                                        }
                                                    >
                                                        <CheckCircle2 className="mr-2 h-4 w-4" />
                                                        Complete
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            {!canComplete && cannotCompleteReason && (
                                                <TooltipContent>
                                                    <p>{cannotCompleteReason}</p>
                                                </TooltipContent>
                                            )}
                                        </Tooltip>
                                    </TooltipProvider>
                                    )}

                                    {/* Hold Button (Admin only) */}
                                    {canHold && (
                                        <Button
                                            onClick={() => setShowHoldModal(true)}
                                            className="bg-yellow-600 hover:bg-yellow-700"
                                        >
                                            <PauseCircle className="mr-2 h-4 w-4" />
                                            Hold
                                        </Button>
                                    )}

                                    {/* Resume Button (Admin only) */}
                                    {canResume && (
                                        <Button
                                            onClick={() => setShowResumeModal(true)}
                                        >
                                            <PlayCircle className="mr-2 h-4 w-4" />
                                            Resume
                                        </Button>
                                    )}

                                    {/* Cancel Button (Admin only) */}
                                    {canCancel && (
                                        <Button
                                            onClick={() => setShowCancelModal(true)}
                                            variant="destructive"
                                        >
                                            <XCircle className="mr-2 h-4 w-4" />
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <StatusBadge status={transaction.status} />
                                {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                    <DelaySeverityBadge
                                        severity={transaction.delay_severity}
                                        delayDays={transaction.delay_days}
                                    />
                                )}
                            </div>
                        </div>

                        {/* Delay Warning Banner */}
                        {transaction.delay_severity === 'overdue' && transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                            <div className="mx-6 mb-4 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                                <AlertTriangle className="h-5 w-5 text-red-600 shrink-0" />
                                <div>
                                    <p className="text-sm font-medium text-red-800">
                                        This transaction is overdue by {transaction.delay_days} business day(s).
                                    </p>
                                    <p className="text-sm text-red-600">
                                        Current step ETA was {transaction.eta_current_step ? new Date(transaction.eta_current_step).toLocaleDateString() : 'N/A'}.
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="p-6 space-y-4">
                            {/* ETA Information */}
                            {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                <div className="grid grid-cols-2 gap-4 rounded-lg bg-gray-50 p-4 mb-4">
                                    <div>
                                        <span className="text-sm text-gray-500">Current Step ETA</span>
                                        <p className="font-medium">
                                            {transaction.eta_current_step
                                                ? new Date(transaction.eta_current_step).toLocaleDateString()
                                                : 'N/A'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-sm text-gray-500">Overall Completion ETA</span>
                                        <p className="font-medium">
                                            {transaction.eta_completion
                                                ? new Date(transaction.eta_completion).toLocaleDateString()
                                                : 'N/A'}
                                        </p>
                                    </div>
                                    <div>
                                        <span className="text-sm text-gray-500">Days at Current Step</span>
                                        <p className="font-medium">{transaction.days_at_current_step} business day(s)</p>
                                    </div>
                                    <div>
                                        <span className="text-sm text-gray-500">Delay</span>
                                        <p className="font-medium">
                                            {transaction.delay_days > 0
                                                ? `${transaction.delay_days} business day(s) overdue`
                                                : 'On schedule'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="font-semibold">Created By:</span>{' '}
                                    {transaction.created_by?.name || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created At:</span>{' '}
                                    {new Date(transaction.created_at).toLocaleString()}
                                </div>
                                {transaction.current_step?.office && (
                                    <div>
                                        <span className="font-semibold">Current Office:</span>{' '}
                                        {transaction.current_step.office.name}
                                    </div>
                                )}
                                {transaction.received_at && (
                                    <div>
                                        <span className="font-semibold">Received At:</span>{' '}
                                        {new Date(transaction.received_at).toLocaleString()}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Out-of-Workflow Warning Banner (Story 3.8) */}
                    <OutOfWorkflowBanner outOfWorkflowInfo={outOfWorkflowInfo} />

                    {/* Transaction Timeline (Story 3.10) */}
                    <TransactionTimeline
                        timeline={timeline}
                        delaySeverity={transaction.delay_severity}
                    />

                    {/* Action History (Story 3.10) */}
                    <ActionHistory actions={actionHistory} />

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
                    {transaction.procurement && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Procurement Details</h3>
                            </div>
                            <div className="p-6">
                                <div className="grid grid-cols-2 gap-6">
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Procurement ID</p>
                                        <Link
                                            href={route('procurements.show', transaction.procurement_id)}
                                            className="text-blue-600 hover:underline font-medium"
                                        >
                                            #{transaction.procurement.id}
                                        </Link>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">End User Office</p>
                                        <p className="font-medium">{transaction.procurement.end_user?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">Particular</p>
                                        <p className="font-medium">{transaction.procurement.particular?.description || transaction.procurement.particular?.name || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 mb-1">ABC Amount</p>
                                        <p className="font-medium text-lg">
                                            {new Intl.NumberFormat('en-PH', {
                                                style: 'currency',
                                                currency: 'PHP',
                                            }).format(transaction.procurement.abc_amount || 0)}
                                        </p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-sm text-gray-600 mb-1">Purpose</p>
                                        <p className="font-medium">{transaction.procurement.purpose}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {(canEdit || canDelete) && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 flex gap-4">
                                {canEdit && (
                                    <Link
                                        href={route('purchase-orders.edit', purchaseOrder.id)}
                                        className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
                                    >
                                        Edit
                                    </Link>
                                )}
                                {canDelete && (
                                    <button
                                        onClick={handleDelete}
                                        className="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500"
                                    >
                                        Delete
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Complete Transaction Modal */}
            <CompleteTransactionModal
                open={showCompleteModal}
                onOpenChange={setShowCompleteModal}
                transactionId={transaction.id}
                referenceNumber={transaction.reference_number}
                category="PO"
                status={transaction.status}
                actionTakenOptions={actionTakenOptions || []}
                defaultActionTakenId={defaultActionTakenId}
            />

            {/* Hold Transaction Modal */}
            <HoldTransactionModal
                open={showHoldModal}
                onOpenChange={setShowHoldModal}
                transactionId={transaction.id}
                referenceNumber={transaction.reference_number}
            />

            {/* Cancel Transaction Modal */}
            <CancelTransactionModal
                open={showCancelModal}
                onOpenChange={setShowCancelModal}
                transactionId={transaction.id}
                referenceNumber={transaction.reference_number}
            />

            {/* Resume Transaction Modal */}
            <ResumeTransactionModal
                open={showResumeModal}
                onOpenChange={setShowResumeModal}
                transactionId={transaction.id}
                referenceNumber={transaction.reference_number}
            />

            {/* Receive Confirmation Modal */}
            <Dialog open={showReceiveModal} onOpenChange={setShowReceiveModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Receipt</DialogTitle>
                        <DialogDescription>
                            You are about to receive this transaction at your office.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="rounded-lg bg-gray-50 p-4 space-y-2">
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Reference Number</span>
                                <span className="text-sm font-medium">{transaction.reference_number}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-sm text-gray-500">Status</span>
                                <span className="text-sm font-medium">{transaction.status}</span>
                            </div>
                            {transaction.current_step?.office && (
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Current Office</span>
                                    <span className="text-sm font-medium">{transaction.current_step.office.name}</span>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="receive-notes">Notes (optional)</Label>
                            <Textarea
                                id="receive-notes"
                                value={receiveNotes}
                                onChange={(e) => setReceiveNotes(e.target.value)}
                                placeholder="Add any notes about this receipt..."
                                rows={3}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowReceiveModal(false)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button onClick={handleReceive} disabled={processing}>
                            {processing ? 'Processing...' : 'Confirm Receipt'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
