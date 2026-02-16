import { useState } from 'react';
import { router } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
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
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { AlertTriangle } from 'lucide-react';

interface CancelTransactionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number;
    referenceNumber: string;
}

export default function CancelTransactionModal({
    open,
    onOpenChange,
    transactionId,
    referenceNumber,
}: CancelTransactionModalProps) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = () => {
        if (!reason.trim()) {
            setErrors({ reason: 'A reason is required to cancel a transaction.' });
            return;
        }

        setProcessing(true);
        setErrors({});
        router.post(
            route('transactions.cancel.store', transactionId),
            { reason },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    onOpenChange(false);
                    setReason('');
                },
                onError: (errs) => setErrors(errs as Record<string, string>),
            }
        );
    };

    const handleClose = () => {
        if (!processing) {
            onOpenChange(false);
            setReason('');
            setErrors({});
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <XCircle className="h-5 w-5 text-red-600" />
                        Cancel Transaction
                    </DialogTitle>
                    <DialogDescription>
                        Cancel transaction {referenceNumber}. This is a terminal action.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <Alert variant="destructive" className="bg-red-50 border-red-200">
                        <AlertTriangle className="h-4 w-4 text-red-600" />
                        <AlertDescription className="text-red-800">
                            Cancelling a transaction is permanent and cannot be undone. No further
                            actions will be possible on this transaction.
                        </AlertDescription>
                    </Alert>

                    <div className="space-y-2">
                        <Label htmlFor="cancel-reason">
                            Reason <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="cancel-reason"
                            value={reason}
                            onChange={(e) => {
                                setReason(e.target.value);
                                setErrors({});
                            }}
                            placeholder="Enter reason for cancelling this transaction..."
                            rows={3}
                            maxLength={1000}
                        />
                        {errors.reason && (
                            <p className="text-sm text-red-500">{errors.reason}</p>
                        )}
                        <div className="text-xs text-gray-500 text-right">
                            {reason.length}/1000
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={processing}>
                        Keep Transaction
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={processing}
                        variant="destructive"
                    >
                        {processing ? 'Cancelling...' : 'Cancel Transaction'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
