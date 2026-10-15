import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Inbox, ScanLine } from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Button } from '@/Components/ui/button';
import { dateTime, documentNames, type DocumentKind } from '@/Components/Documents/types';
import type { PaginatedData } from '@/types/models';
import '../../../css/documents.css';
import '../../../css/qr-handoff.css';

interface PendingTransaction {
    id: number;
    reference_number: string;
    category: DocumentKind;
    status: string;
    endorsed_at: string | null;
    procurement_id: number;
    from_office_name: string;
    procurement?: {
        id: number;
        purpose: string | null;
        end_user?: { id: number; name: string };
    };
}

export default function Pending({ transactions }: { transactions: PaginatedData<PendingTransaction> }) {
    const previous = transactions.links[0]?.url;
    const next = transactions.links[transactions.links.length - 1]?.url;
    return <DashboardLayout title="Pending receipts" rootClassName="op-documents dw-root qh-root" mainId="pending-main">
        <Head title="Pending receipts"/>
        <main className="op-main dw-main" id="pending-main" tabIndex={-1}>
            <div className="qh-pending-head"><div><h1>Pending receipts</h1><p>Documents waiting to be received at your assigned office.</p></div><span className="qh-pending-count">{transactions.total} pending {transactions.total === 1 ? 'document' : 'documents'}</span></div>
            <section className="dw-panel" aria-label="Documents awaiting receipt">
                <div className="qh-pending-note"><ScanLine aria-hidden="true"/><p>Scan each paper document before confirming its receipt. Each document requires its own matching reference.</p></div>
                {transactions.data.length ? <table className="qh-pending-table"><caption className="sr-only">Documents awaiting receipt at your assigned office</caption><thead><tr><th scope="col">Document</th><th scope="col">From office</th><th scope="col">Endorsed</th><th scope="col"><span className="sr-only">Receive action</span></th></tr></thead><tbody>{transactions.data.map(transaction => <tr key={transaction.id}><td data-label="Document"><strong>{transaction.reference_number}</strong><p>{transaction.procurement?.purpose ?? 'No purpose recorded.'}</p><Link href={route('procurements.show', transaction.procurement_id)}>Procurement #{transaction.procurement_id}</Link></td><td data-label="From office">{transaction.from_office_name}<small>{documentNames[transaction.category]}</small></td><td data-label="Endorsed">{transaction.endorsed_at ? dateTime(transaction.endorsed_at, true) : 'Not recorded'}<small>Awaiting receipt</small></td><td data-label="Action"><Button asChild><Link href={route('transactions.receive.create', transaction.id)} aria-label={'Scan and receive '+transaction.reference_number}><ScanLine aria-hidden="true"/>Scan &amp; receive</Link></Button></td></tr>)}</tbody></table> : <div className="qh-pending-empty"><Inbox aria-hidden="true"/><h2>No pending receipts</h2><p>No documents are waiting to be received in your selected reporting year. New endorsements to your office will appear here.</p><Button variant="outline" asChild><Link href={route('transactions.index')}>View transactions</Link></Button></div>}
                <footer className="qh-pending-footer"><span>{transactions.total ? `${transactions.from}–${transactions.to} of ${transactions.total} documents` : '0 documents'}</span><div><Button variant="ghost" size="icon" aria-label="Previous page" disabled={!previous} asChild={!!previous}>{previous ? <Link href={previous}><ChevronLeft aria-hidden="true"/></Link> : <ChevronLeft aria-hidden="true"/>}</Button><span>{transactions.current_page} / {transactions.last_page}</span><Button variant="ghost" size="icon" aria-label="Next page" disabled={!next} asChild={!!next}>{next ? <Link href={next}><ChevronRight aria-hidden="true"/></Link> : <ChevronRight aria-hidden="true"/>}</Button></div></footer>
            </section>
            <footer className="dw-footer">Local Government of Quezon, Bukidnon<span>Online Procurement Tracking System</span></footer>
        </main>
    </DashboardLayout>;
}
