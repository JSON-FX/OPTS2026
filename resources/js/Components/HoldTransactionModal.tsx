import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PauseCircle } from 'lucide-react';
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

interface HoldTransactionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number;
    referenceNumber: string;
}

export default function HoldTransactionModal({
    open,
    onOpenChange,
    transactionId,
    referenceNumber,
}: HoldTransactionModalProps) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = () => {
        if (!reason.trim()) {
            setErrors({ reason: 'A reason is required to place a transaction on hold.' });
            return;
        }

        setProcessing(true);
        setErrors({});
        router.post(
            route('transactions.hold.store', transactionId),
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
                        <PauseCircle className="h-5 w-5 text-yellow-600" />
                        Hold Transaction
                    </DialogTitle>
                    <DialogDescription>
                        Place transaction {referenceNumber} on hold. This will prevent any
                        endorsement or workflow actions until resumed.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="hold-reason">
                            Reason <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="hold-reason"
                            value={reason}
                            onChange={(e) => {
                                setReason(e.target.value);
                                setErrors({});
                            }}
                            placeholder="Enter reason for placing this transaction on hold..."
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
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={processing}
                        className="bg-yellow-600 hover:bg-yellow-700"
                    >
                        {processing ? 'Processing...' : 'Place on Hold'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
