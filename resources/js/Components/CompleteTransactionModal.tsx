import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Alert, AlertDescription } from '@/Components/ui/alert';

interface ActionTakenOption {
    id: number;
    description: string;
}

interface CompleteTransactionModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number;
    referenceNumber: string;
    category: string;
    status: string;
    actionTakenOptions: ActionTakenOption[];
    defaultActionTakenId?: number | null;
}

export default function CompleteTransactionModal({
    open,
    onOpenChange,
    transactionId,
    referenceNumber,
    category,
    status,
    actionTakenOptions,
    defaultActionTakenId,
}: CompleteTransactionModalProps) {
    const [actionTakenId, setActionTakenId] = useState('');
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setActionTakenId(defaultActionTakenId?.toString() ?? '');
        }
    }, [open, defaultActionTakenId]);

    const handleSubmit = () => {
        const newErrors: Record<string, string> = {};
        if (!actionTakenId) {
            newErrors.action_taken_id = 'Please select an action taken.';
        }
        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setProcessing(true);
        setErrors({});
        router.post(
            route('transactions.complete.store', transactionId),
            {
                action_taken_id: actionTakenId,
                notes: notes || null,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                },
                onSuccess: () => {
                    onOpenChange(false);
                    setActionTakenId('');
                    setNotes('');
                },
                onError: (errs) => {
                    setErrors(errs as Record<string, string>);
                },
            }
        );
    };

    const handleClose = () => {
        if (!processing) {
            onOpenChange(false);
            setActionTakenId(defaultActionTakenId?.toString() ?? '');
            setNotes('');
            setErrors({});
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                        Complete Transaction
                    </DialogTitle>
                    <DialogDescription>
                        Mark this transaction as completed. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Transaction Summary */}
                    <div className="rounded-lg bg-gray-50 p-4 space-y-2">
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Reference Number</span>
                            <span className="text-sm font-medium">{referenceNumber}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Category</span>
                            <span className="text-sm font-medium">{category}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-sm text-gray-500">Status</span>
                            <span className="text-sm font-medium">{status}</span>
                        </div>
                    </div>

                    {/* Warning */}
                    <Alert variant="destructive" className="bg-amber-50 border-amber-200">
                        <AlertTriangle className="h-4 w-4 text-amber-600" />
                        <AlertDescription className="text-amber-800">
                            This action will mark the transaction as completed and cannot be undone.
                        </AlertDescription>
                    </Alert>

                    {/* Action Taken */}
                    <div className="space-y-2">
                        <Label htmlFor="complete-action-taken">
                            Action Taken <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={actionTakenId}
                            onValueChange={(value) => {
                                setActionTakenId(value);
                                setErrors((prev) => ({ ...prev, action_taken_id: '' }));
                            }}
                        >
                            <SelectTrigger
                                className={errors.action_taken_id ? 'border-red-500' : ''}
                            >
                                <SelectValue placeholder="Select action taken..." />
                            </SelectTrigger>
                            <SelectContent>
                                {actionTakenOptions.map((action) => (
                                    <SelectItem
                                        key={action.id}
                                        value={action.id.toString()}
                                    >
                                        {action.description}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.action_taken_id && (
                            <p className="text-sm text-red-500">{errors.action_taken_id}</p>
                        )}
                    </div>

                    {/* Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="complete-notes">Notes (optional)</Label>
                        <Textarea
                            id="complete-notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Add any notes about this completion..."
                            rows={3}
                            maxLength={1000}
                        />
                        <div className="text-xs text-gray-500 text-right">
                            {notes.length}/1000
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
                        className="bg-green-600 hover:bg-green-700"
                    >
                        {processing ? 'Completing...' : 'Complete Transaction'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
