import { useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import DocumentQrLabel, { downloadDocumentQr, type QrLabelDocument } from './DocumentQrLabel';
import '../../../css/document-qr-label.css';

export default function DocumentQrDialog({ document, transactionId, open, onOpenChange }: {
    document: QrLabelDocument; transactionId: number; open: boolean; onOpenChange: (open: boolean) => void;
}) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const [error, setError] = useState('');
    return <Dialog open={open} onOpenChange={value => { setError(''); onOpenChange(value); }}><DialogContent className="op-root op-live-overlay ql-dialog">
        <DialogHeader className="sr-only"><DialogTitle>Document QR</DialogTitle><DialogDescription>Download the QR for {document.reference} or open its printable label.</DialogDescription></DialogHeader>
        <DocumentQrLabel document={document} ref={canvas}/>
        <Button variant="outline" className="ql-download" onClick={() => { setError(''); downloadDocumentQr(canvas.current, document.reference).catch(() => setError('The QR could not be downloaded. Reload the page and try again.')); }}><Download aria-hidden="true"/>Download QR</Button>
        {error && <p role="alert" className="ql-error">{error}</p>}
        <Button variant="ghost" asChild><Link href={route('transactions.qr-label', transactionId)}><Printer aria-hidden="true"/>Print label</Link></Button>
    </DialogContent></Dialog>;
}
