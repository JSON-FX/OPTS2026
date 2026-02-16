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
    is_continuation?: boolean;
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

interface Voucher {
    id: number;
    transaction_id: number;
    payee: string;
    transaction?: Transaction;
}

interface PurchaseRequest {
    id: number;
    transaction?: {
        id: number;
        reference_number: string;
    };
}

interface PurchaseOrder {
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
    voucher: Voucher;
    purchaseRequest?: PurchaseRequest;
    purchaseOrder?: PurchaseOrder;
    canEdit: boolean;
    canEndorse: boolean;
    cannotEndorseReason: string | null;
    canReceive: boolean;
    cannotReceiveReason: string | null;
    canComplete: boolean;
    cannotCompleteReason: string | null;
    actionTakenOptions: ActionTakenOption[];
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

export default function Show({ voucher, purchaseRequest, purchaseOrder, canEdit, canEndorse, cannotEndorseReason, canReceive, cannotReceiveReason, canComplete, cannotCompleteReason, actionTakenOptions, canHold, cannotHoldReason, canCancel, cannotCancelReason, canResume, cannotResumeReason, outOfWorkflowInfo, timeline, actionHistory }: Props) {
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
        if (
            confirm(
                'Deleting vouchers may violate audit trail requirements. Are you sure? This action creates a deletion record.'
            )
        ) {
            router.delete(route('vouchers.destroy', voucher.id));
        }
    };

    const transaction = voucher.transaction;

    const handleReceive = () => {
        if (!transaction) return;
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
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Voucher Details</h2>
            }
        >
            <Head title={`Voucher ${transaction?.reference_number}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                    {/* Voucher Header */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <h3 className="text-3xl font-bold">{transaction?.reference_number}</h3>
                                </div>

                                {transaction && (
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
                                )}
                            </div>
                            {transaction && (
                                <div className="mt-2 flex items-center gap-2">
                                    <StatusBadge status={transaction.status} />
                                    {transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
                                        <DelaySeverityBadge
                                            severity={transaction.delay_severity}
                                            delayDays={transaction.delay_days}
                                        />
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Delay Warning Banner */}
                        {transaction && transaction.delay_severity === 'overdue' && transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
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
                            {transaction && transaction.status !== 'Completed' && transaction.status !== 'Cancelled' && (
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
                                    {transaction?.created_by?.name || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-semibold">Created At:</span>{' '}
                                    {new Date(transaction?.created_at || '').toLocaleString()}
                                </div>
                                {transaction?.current_step?.office && (
                                    <div>
                                        <span className="font-semibold">Current Office:</span>{' '}
                                        {transaction.current_step.office.name}
                                    </div>
                                )}
                                {transaction?.received_at && (
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
                    {timeline && (
                        <TransactionTimeline
                            timeline={timeline}
                            delaySeverity={transaction?.delay_severity}
                        />
                    )}

                    {/* Action History (Story 3.10) */}
                    {actionHistory && (
                        <ActionHistory actions={actionHistory} />
                    )}

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
                    {transaction?.procurement && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6 border-b border-gray-200">
                                <h3 className="text-lg font-medium">Related Procurement</h3>
                            </div>
                            <div className="p-6 space-y-2">
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
                                    <p className="text-sm text-gray-600 mb-1">End User</p>
                                    <p className="font-medium">{transaction.procurement.end_user?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">Particular</p>
                                    <p className="font-medium">
                                        {transaction.procurement.particular?.description || 'N/A'}
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

            {/* Complete Transaction Modal */}
            {transaction && (
            <>
            <CompleteTransactionModal
                open={showCompleteModal}
                onOpenChange={setShowCompleteModal}
                transactionId={transaction.id}
                referenceNumber={transaction.reference_number}
                category="VCH"
                status={transaction.status}
                actionTakenOptions={actionTakenOptions || []}
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
            </>
            )}
        </AuthenticatedLayout>
    );
}
