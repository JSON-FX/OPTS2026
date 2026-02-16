import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

interface ResumeTransactionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number;
    referenceNumber: string;
}

export default function ResumeTransactionModal({
    open,
    onOpenChange,
    transactionId,
    referenceNumber,
}: ResumeTransactionModalProps) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleSubmit = () => {
        setProcessing(true);
        router.post(
            route('transactions.resume.store', transactionId),
            { reason: reason || null },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onOpenChange(false);
                    setReason('');
                },
            }
        );
    };

    const handleClose = () => {
        if (!processing) {
            onOpenChange(false);
            setReason('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <PlayCircle className="h-5 w-5 text-blue-600" />
                        Resume Transaction
                    </DialogTitle>
                    <DialogDescription>
                        Resume transaction {referenceNumber} from hold. The transaction will
                        return to "In Progress" at its current workflow step.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="resume-reason">Reason (optional)</Label>
                        <Textarea
                            id="resume-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Enter reason for resuming this transaction..."
                            rows={3}
                            maxLength={1000}
                        />
                        <div className="text-xs text-gray-500 text-right">
                            {reason.length}/1000
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={processing}
                    >
                        {processing ? 'Resuming...' : 'Resume Transaction'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
