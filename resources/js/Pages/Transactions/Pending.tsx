import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CheckSquare, Inbox } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import RelativeTime from '@/Components/RelativeTime';
import DelaySeverityBadge from '@/Components/DelaySeverityBadge';
import { PaginatedData } from '@/types/models';
import type { DelaySeverity } from '@/types/models';

interface PendingTransaction {
    id: number;
    reference_number: string;
    category: string;
    status: string;
    endorsed_at: string | null;
    procurement_id: number;
    from_office_name: string;
    eta_completion: string | null;
    delay_days: number;
    delay_severity: DelaySeverity;
    is_stagnant: boolean;
    procurement?: {
        id: number;
        purpose: string | null;
        end_user?: {
            id: number;
            name: string;
        };
    };
}

interface PendingProps {
    transactions: PaginatedData<PendingTransaction>;
}

export default function Pending({ transactions }: PendingProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [showBulkModal, setShowBulkModal] = useState(false);
    const [currentTransaction, setCurrentTransaction] = useState<PendingTransaction | null>(null);
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    const allSelected = transactions.data.length > 0 && selectedIds.length === transactions.data.length;

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedIds([]);
        } else {
            setSelectedIds(transactions.data.map((t) => t.id));
        }
    };

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const openReceiveModal = (transaction: PendingTransaction) => {
        setCurrentTransaction(transaction);
        setNotes('');
        setShowReceiveModal(true);
    };

    const openBulkModal = () => {
        setNotes('');
        setShowBulkModal(true);
    };

    const handleReceive = () => {
        if (!currentTransaction) return;
        setProcessing(true);
        router.post(
            route('transactions.receive.store', currentTransaction.id),
            { notes: notes || null },
            {
                onFinish: () => {
                    setProcessing(false);
                    setShowReceiveModal(false);
                    setCurrentTransaction(null);
                    setNotes('');
                },
            }
        );
    };

    const handleBulkReceive = () => {
        setProcessing(true);
        router.post(
            route('transactions.receive.bulk'),
            { transaction_ids: selectedIds, notes: notes || null },
            {
                onFinish: () => {
                    setProcessing(false);
                    setShowBulkModal(false);
                    setSelectedIds([]);
                    setNotes('');
                },
            }
        );
    };

    const categoryColors: Record<string, string> = {
        PR: 'bg-blue-100 text-blue-800',
        PO: 'bg-green-100 text-green-800',
        VCH: 'bg-purple-100 text-purple-800',
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Pending Receipts
                </h2>
            }
        >
            <Head title="Pending Receipts" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Bulk Actions Bar */}
                            {selectedIds.length > 0 && (
                                <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-50 p-3">
                                    <span className="text-sm font-medium text-blue-800">
                                        {selectedIds.length} transaction(s) selected
                                    </span>
                                    <Button
                                        onClick={openBulkModal}
                                        size="sm"
                                    >
                                        <CheckSquare className="mr-2 h-4 w-4" />
                                        Receive Selected
                                    </Button>
                                </div>
                            )}

                            {/* Table */}
                            {transactions.data.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-[50px]">
                                                    <Checkbox
                                                        checked={allSelected}
                                                        onCheckedChange={toggleSelectAll}
                                                        aria-label="Select all"
                                                    />
                                                </TableHead>
                                                <TableHead>Reference Number</TableHead>
                                                <TableHead>Category</TableHead>
                                                <TableHead>From Office</TableHead>
                                                <TableHead>Endorsed At</TableHead>
                                                <TableHead>ETA</TableHead>
                                                <TableHead>Delay</TableHead>
                                                <TableHead className="text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {transactions.data.map((transaction) => (
                                                <TableRow key={transaction.id}>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={selectedIds.includes(transaction.id)}
                                                            onCheckedChange={() => toggleSelect(transaction.id)}
                                                            aria-label={`Select ${transaction.reference_number}`}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="font-medium">
                                                        {transaction.reference_number}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="outline"
                                                            className={categoryColors[transaction.category] || ''}
                                                        >
                                                            {transaction.category}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>{transaction.from_office_name}</TableCell>
                                                    <TableCell>
                                                        {transaction.endorsed_at ? (
                                                            <RelativeTime timestamp={transaction.endorsed_at} />
                                                        ) : (
                                                            'N/A'
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {transaction.eta_completion ? (
                                                            <span className="text-sm text-gray-700">
                                                                {new Date(transaction.eta_completion).toLocaleDateString()}
                                                            </span>
                                                        ) : (
                                                            <span className="text-sm text-gray-400">N/A</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <DelaySeverityBadge
                                                            severity={transaction.delay_severity}
                                                            delayDays={transaction.delay_days}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            size="sm"
                                                            onClick={() => openReceiveModal(transaction)}
                                                        >
                                                            Receive
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <div className="py-12 text-center">
                                    <Inbox className="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 className="mt-2 text-sm font-semibold text-gray-900">
                                        No pending receipts
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        There are no transactions waiting to be received at your office.
                                    </p>
                                </div>
                            )}

                            {/* Pagination */}
                            {transactions.data.length > 0 && transactions.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Showing {transactions.from} to {transactions.to} of{' '}
                                        {transactions.total} results
                                    </div>
                                    <div className="flex gap-2">
                                        {transactions.links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() => link.url && router.visit(link.url)}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Single Receive Confirmation Modal */}
            <Dialog open={showReceiveModal} onOpenChange={setShowReceiveModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Receipt</DialogTitle>
                        <DialogDescription>
                            You are about to receive this transaction.
                        </DialogDescription>
                    </DialogHeader>

                    {currentTransaction && (
                        <div className="space-y-3">
                            <div className="rounded-lg bg-gray-50 p-4 space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Reference Number</span>
                                    <span className="text-sm font-medium">{currentTransaction.reference_number}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Category</span>
                                    <Badge
                                        variant="outline"
                                        className={categoryColors[currentTransaction.category] || ''}
                                    >
                                        {currentTransaction.category}
                                    </Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">From Office</span>
                                    <span className="text-sm font-medium">{currentTransaction.from_office_name}</span>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="receive-notes">Notes (optional)</Label>
                                <Textarea
                                    id="receive-notes"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Add any notes about this receipt..."
                                    rows={3}
                                />
                            </div>
                        </div>
                    )}

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

            {/* Bulk Receive Confirmation Modal */}
            <Dialog open={showBulkModal} onOpenChange={setShowBulkModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Bulk Receipt</DialogTitle>
                        <DialogDescription>
                            You are about to receive {selectedIds.length} transaction(s).
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="rounded-lg bg-gray-50 p-4">
                            <p className="text-sm text-gray-700">
                                <span className="font-medium">{selectedIds.length}</span> transaction(s) will be marked as received at your office.
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="bulk-receive-notes">Notes (optional)</Label>
                            <Textarea
                                id="bulk-receive-notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Add any notes about this bulk receipt..."
                                rows={3}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowBulkModal(false)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button onClick={handleBulkReceive} disabled={processing}>
                            {processing ? 'Processing...' : `Receive ${selectedIds.length} Transaction(s)`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
