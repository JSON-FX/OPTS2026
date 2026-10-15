import { forwardRef } from 'react';
import { QRCodeCanvas } from 'qrcode.react';

export interface QrLabelDocument {
    category: 'PR' | 'PO' | 'VCH';
    reference: string;
    procurementId: number;
}

/** Only the persisted reference is encoded. Display text is never part of the payload. */
const DocumentQrLabel = forwardRef<HTMLCanvasElement, { document: QrLabelDocument; logoSrc?: string }>(({ document, logoSrc = '/lgu-seal.png' }, ref) => {
    return <div className="ql-label">
        <QRCodeCanvas ref={ref} value={document.reference} size={1024} level="H" marginSize={4}
            style={{ width: 132, height: 132 }}
            imageSettings={{ src: logoSrc, height: 148, width: 148, excavate: true }}
            fgColor="#000000" bgColor="#ffffff" title={'Document reference: '+document.reference}
            className="ql-code" role="img" aria-label={'QR code for '+document.reference}/>
        <strong className="ql-reference">{document.reference}</strong>
    </div>;
});
DocumentQrLabel.displayName = 'DocumentQrLabel';
export default DocumentQrLabel;

export async function prepareDocumentQr(canvas: HTMLCanvasElement | null): Promise<HTMLCanvasElement> {
    if (!canvas) throw new Error('QR is not ready.');
    const logo = canvas.parentElement?.querySelector('img');
    if (logo && (!logo.complete || !logo.naturalWidth)) {
        await logo.decode();
        await new Promise<void>(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));
    }
    return canvas;
}

export async function downloadDocumentQr(canvas: HTMLCanvasElement | null, reference: string): Promise<void> {
    const readyCanvas = await prepareDocumentQr(canvas);
    const link = window.document.createElement('a');
    link.download = reference.replace(/[\\/:*?"<>|\u0000-\u001f]/g, '-')+'-qr.png';
    link.href = readyCanvas.toDataURL('image/png');
    link.click();
}
