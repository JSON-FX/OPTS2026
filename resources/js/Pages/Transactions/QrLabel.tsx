import { useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import DocumentQrLabel, { downloadDocumentQr, prepareDocumentQr, type QrLabelDocument } from '@/Components/Documents/DocumentQrLabel';
import '../../../css/document-qr-label.css';

interface Props {
    document: QrLabelDocument;
    entityShowRoute: { route: string; id: number };
}

export default function QrLabel({ document, entityShowRoute }: Props) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const [error, setError] = useState('');
    return <div className="ql-page op-root">
        <Head title={'QR label · '+document.reference}/>
        <main className="ql-preview" id="qr-label-main">
            <header className="ql-controls">
                <Button variant="ghost" asChild><Link href={route(entityShowRoute.route, entityShowRoute.id)}><ArrowLeft aria-hidden="true"/>Back to document</Link></Button>
                <div className="ql-preview-heading"><div><h1>Print QR label</h1><p>Attach this QR to the matching paper document.</p></div></div>
            </header>
            <div className="ql-paper"><DocumentQrLabel document={document} ref={canvas}/></div>
            <div className="ql-print-actions"><Button variant="outline" onClick={() => { setError(''); downloadDocumentQr(canvas.current, document.reference).catch(() => setError('The QR could not be downloaded. Reload the page and try again.')); }}><Download aria-hidden="true"/>Download QR</Button><Button onClick={() => { setError(''); prepareDocumentQr(canvas.current).then(() => window.print()).catch(() => setError('The QR is not ready to print. Reload the page and try again.')); }}><Printer aria-hidden="true"/>Print / Save as PDF</Button></div>
            {error && <p role="alert" className="ql-error">{error}</p>}
            <section className="ql-instructions" aria-label="Printing instructions">
                <h2>Before attaching the label</h2>
                <ul><li>Print at actual size (100%) with browser headers and footers off. Keep the white space around the QR.</li><li>Check that the reference matches the paper document. Scan the printed code to check readability.</li><li>If the document reference changes, print a new label and replace the old one.</li></ul>
                <p>The QR contains only the saved reference. It works with the existing endorsement and receipt scan fields.</p>
            </section>
        </main>
    </div>;
}
