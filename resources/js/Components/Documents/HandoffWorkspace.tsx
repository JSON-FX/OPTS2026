import { useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRight, ArrowUpRight, Check, CheckCircle2, FileText,
    Keyboard, ScanLine, Send, ShieldCheck, TriangleAlert,
} from 'lucide-react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import type { PageProps } from '@/types';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog';
import { Combo, DocumentStatus, Field, OfficeLabel } from './DocumentControls';
import { documentNames } from './types';
import type { HandoffProps } from './handoff-types';
import '../../../css/documents.css';
import '../../../css/qr-handoff.css';

/** A scanner submits the QR payload as keyboard input, followed by Enter. */
export default function HandoffWorkspace({
    mode, transaction, workflowSteps, actionTakenOptions = [], officeOptions = [],
    expectedNextOffice, fromOffice, entityShowRoute, defaultActionTakenId,
}: HandoffProps & { mode: 'endorse' | 'receive' }) {
    const id = useId();
    const { auth } = usePage<PageProps>().props;
    const [raw, setRaw] = useState('');
    const [matched, setMatched] = useState(false);
    const [scanError, setScanError] = useState('');
    const [focused, setFocused] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [help, setHelp] = useState(false);
    const scanInput = useRef<HTMLInputElement>(null);
    const details = useRef<HTMLFieldSetElement>(null);
    const confirmationButton = useRef<HTMLButtonElement>(null);
    const form = useForm({
        scanned_reference: '',
        action_taken_id: defaultActionTakenId?.toString() ?? '',
        to_office_id: expectedNextOffice?.id.toString() ?? '',
        notes: '',
    });
    const receiving = mode === 'receive';
    const title = receiving ? 'Receive document' : 'Endorse document';
    const documentUrl = route(entityShowRoute.route, entityShowRoute.id);
    const backUrl = receiving ? route('transactions.pending') : documentUrl;
    const role = auth.user.roles?.some(item => item.name === 'Administrator')
        ? 'Administrator' : auth.user.roles?.some(item => item.name === 'Endorser') ? 'Endorser' : 'Viewer';
    const readonly = !auth.user.can_process_transactions;
    const sourceOffice = receiving ? fromOffice : transaction.current_office ?? transaction.current_step?.office;
    const destination = receiving ? transaction.current_office : officeOptions.find(office => String(office.id) === form.data.to_office_id);
    const outOfWorkflow = !receiving && !!expectedNextOffice && !!destination && destination.id !== expectedNextOffice.id;
    const canConfirm = matched && !readonly && !form.processing && (receiving || (!!form.data.action_taken_id && !!destination));
    const selectedAction = actionTakenOptions.find(action => String(action.id) === form.data.action_taken_id);
    const steps = [...workflowSteps].sort((a, b) => a.step_order - b.step_order);
    const currentOrder = transaction.current_step?.step_order ?? 0;
    const receivingStep = receiving && transaction.current_office?.id != null
        ? steps.find(step => step.office_id === transaction.current_office?.id && step.step_order >= currentOrder)
        : undefined;
    const fieldError = scanError || form.errors.scanned_reference;

    useEffect(() => {
        if (!readonly) scanInput.current?.focus({ preventScroll: true });
    }, [readonly, transaction.id]);

    useEffect(() => {
        if (!matched) return;
        if (receiving) confirmationButton.current?.focus({ preventScroll: true });
        else details.current?.querySelector<HTMLButtonElement>('[role="combobox"]')?.focus({ preventScroll: true });
    }, [matched, receiving]);

    const checkScan = (event: FormEvent) => {
        event.preventDefault();
        if (readonly || form.processing || matched) return;
        const reference = raw.replace(/[\r\n]+$/g, '');
        form.clearErrors();
        if (!reference) {
            setScanError('Scan the document QR before checking its reference.');
            scanInput.current?.focus({ preventScroll: true });
            return;
        }
        if (reference !== transaction.reference_number) {
            setScanError('This is a different document. Check the paper and scan the QR for '+transaction.reference_number+'.');
            scanInput.current?.focus({ preventScroll: true });
            return;
        }
        setRaw(reference);
        setScanError('');
        form.setData('scanned_reference', reference);
        setMatched(true);
    };

    const scanAgain = () => {
        setMatched(false);
        setRaw('');
        setScanError('');
        form.setData('scanned_reference', '');
        form.clearErrors('scanned_reference');
        requestAnimationFrame(() => scanInput.current?.focus({ preventScroll: true }));
    };

    const confirmHandoff = () => {
        if (!canConfirm) return;
        form.post(route(receiving ? 'transactions.receive.store' : 'transactions.endorse.store', transaction.id), {
            onSuccess: () => setConfirming(false),
            onError: errors => {
                setConfirming(false);
                if (errors.scanned_reference) {
                    setMatched(false);
                    form.setData('scanned_reference', '');
                    requestAnimationFrame(() => scanInput.current?.focus({ preventScroll: true }));
                }
            },
        });
    };

    return <DashboardLayout
        title={title}
        rootClassName="op-documents dw-root qh-root"
        mainId="handoff-main"
        breadcrumbs={[
            { label: 'Transactions', href: route('transactions.index') },
            { label: transaction.reference_number, href: documentUrl },
            { label: receiving ? 'Receive' : 'Endorse' },
        ]}
    >
        <Head title={title+' · '+transaction.reference_number}/>
        <main id="handoff-main" className="op-main dw-main qh-main" tabIndex={-1}>
            <Link href={backUrl} className="qh-back"><ArrowLeft aria-hidden="true"/>{receiving ? 'Pending receipts' : 'Back to document'}</Link>
            <div className="op-page-head qh-page-head">
                <div><h1>{title}</h1><p>{receiving ? 'Scan the paper document, check its details, then confirm receipt.' : 'Scan the paper document before sending it to the next office.'}</p></div>
                <Button variant="outline" onClick={() => setHelp(true)}><Keyboard aria-hidden="true"/>Scanner setup</Button>
            </div>
            <div className="qh-scope"><ShieldCheck aria-hidden="true"/><span>Assigned office <OfficeLabel office={auth.user.office}/> · {role}</span><span>SSO role and office</span></div>
            {readonly && <div className="qh-access" role="status"><ShieldCheck aria-hidden="true"/><div><strong>Document information only</strong><p>Your role cannot endorse or receive documents.</p></div></div>}
            <div className="qh-grid">
                <section className="dw-panel qh-task" aria-labelledby={id+'-task-heading'}>
                    <header className="qh-task-title"><div className="qh-number" aria-hidden="true">1</div><div><h2 id={id+'-task-heading'}>Scan the document QR</h2><p>Use the scanner gun connected to this device. The QR contains the document reference.</p></div></header>
                    <div className="qh-expected"><FileText aria-hidden="true"/><div><span>Expected {documentNames[transaction.category].toLowerCase()}</span><strong>{transaction.reference_number}</strong></div><DocumentStatus status={transaction.status}/></div>
                    <form className="qh-scan-form" onSubmit={checkScan}>
                        <div className={'qh-scanner-status'+(focused ? ' is-ready' : '')}><ScanLine aria-hidden="true"/><span>{matched ? 'Reference checked' : readonly ? 'Scanning unavailable' : focused ? 'Ready for scanner input' : 'Click the field to ready the scanner'}</span>{!readonly && !matched && <span>Enter to check</span>}</div>
                        <Label htmlFor={id+'-scan'}>Scanned reference</Label>
                        <div className="qh-scan-row"><Input
                            id={id+'-scan'} ref={scanInput} value={raw}
                            onChange={event => { setRaw(event.target.value); setScanError(''); form.clearErrors('scanned_reference'); }}
                            onFocus={() => setFocused(true)} onBlur={() => setFocused(false)}
                            autoComplete="off" autoCapitalize="none" spellCheck={false}
                            placeholder="Scan QR or paste its reference" maxLength={512}
                            disabled={readonly || matched || form.processing}
                            aria-invalid={!!fieldError} aria-describedby={id+'-scan-hint'+(fieldError ? ' '+id+'-scan-error' : '')}
                        /><Button type="submit" disabled={readonly || matched || form.processing}><ScanLine aria-hidden="true"/>Check scan</Button></div>
                        <p id={id+'-scan-hint'} className="qh-muted">A matching reference unlocks the details. Confirm the handoff below to record it.</p>
                        {fieldError && <div className="qh-error" id={id+'-scan-error'} role="alert"><TriangleAlert aria-hidden="true"/><div><strong>The reference could not be confirmed</strong><p>{fieldError}</p></div></div>}
                        {matched && <div className="qh-match" role="status"><CheckCircle2 aria-hidden="true"/><div><strong>Reference matches</strong><p>{transaction.reference_number}{transaction.procurement ? ' · Procurement #'+transaction.procurement.id : ''}</p></div><Button type="button" variant="ghost" onClick={scanAgain} disabled={form.processing}>Scan again</Button></div>}
                    </form>
                    <section className={'qh-handoff-details'+(!matched ? ' is-locked' : '')} aria-labelledby={id+'-details-heading'}>
                        <header className="qh-task-title"><div className="qh-number" aria-hidden="true">2</div><div><h2 id={id+'-details-heading'}>{receiving ? 'Confirm receipt details' : 'Prepare the handoff'}</h2><p>{matched ? 'Check the office and record any useful processing notes.' : 'These fields unlock after the reference matches.'}</p></div></header>
                        <div className="qh-office-transfer"><div><span>From</span><strong>{sourceOffice?.name ?? 'Origin not recorded'}</strong></div><ArrowRight aria-hidden="true"/><div><span>{receiving ? 'Receiving office' : 'Send to'}</span><strong>{destination?.name ?? 'Choose an office below'}</strong></div></div>
                        <fieldset ref={details} disabled={!matched || readonly || form.processing}>
                            <legend className="sr-only">Handoff details</legend>
                            {!receiving && <>
                                <Field id={id+'-action'} label="Action taken" required error={form.errors.action_taken_id}><Combo id={id+'-action'} label="Action taken" value={form.data.action_taken_id} onChange={value => form.setData('action_taken_id', value)} options={actionTakenOptions.map(action => ({ value: String(action.id), label: action.description }))} placeholder="Select action taken" error={form.errors.action_taken_id} disabled={!matched || form.processing}/></Field>
                                <Field id={id+'-office'} label="Send to office" required error={form.errors.to_office_id} hint={expectedNextOffice ? 'Next in workflow: '+expectedNextOffice.name : 'Choose an active office from the SSO directory.'}><Combo id={id+'-office'} label="Send to office" value={form.data.to_office_id} onChange={value => form.setData('to_office_id', value)} options={officeOptions.map(office => ({ value: String(office.id), label: (office.abbreviation ? office.abbreviation+' · ' : '')+office.name }))} placeholder="Select receiving office" error={form.errors.to_office_id} disabled={!matched || form.processing}/></Field>
                                {outOfWorkflow && <div className="qh-workflow-warning" role="status"><TriangleAlert aria-hidden="true"/><p>This office is outside the expected next workflow step. The endorsement will be recorded as out of workflow.</p></div>}
                            </>}
                            <Field id={id+'-notes'} label="Notes (optional)" error={form.errors.notes}><Textarea id={id+'-notes'} value={form.data.notes} onChange={event => form.setData('notes', event.target.value)} placeholder={receiving ? 'Condition of the document or receiving notes' : 'Instructions for the receiving office'} maxLength={1000} aria-invalid={!!form.errors.notes} aria-describedby={form.errors.notes ? id+'-notes-error' : undefined}/></Field>
                        </fieldset>
                        {Object.entries(form.errors).filter(([key]) => !['scanned_reference', 'action_taken_id', 'to_office_id', 'notes'].includes(key)).map(([key, message]) => <p className="qh-field-error qh-general-error" role="alert" key={key}>{message}</p>)}
                    </section>
                    <footer className="qh-task-footer"><p>{receiving ? 'Only confirm after the actual paper document is in your office.' : 'The receiving office must scan the same QR when the paper arrives.'} Your office and workflow eligibility are checked again when saving.</p><div><Button variant="outline" asChild disabled={form.processing}><Link href={backUrl}>Cancel</Link></Button><Button ref={confirmationButton} disabled={!canConfirm} onClick={() => setConfirming(true)}>{receiving ? <Check aria-hidden="true"/> : <Send aria-hidden="true"/>}{receiving ? 'Confirm receipt' : 'Review endorsement'}</Button></div></footer>
                </section>
                <aside className="qh-context" aria-label="Document context"><section className="dw-panel qh-document"><div className="qh-context-heading"><FileText aria-hidden="true"/><h2>{documentNames[transaction.category]}</h2></div><strong className="qh-reference">{transaction.reference_number}</strong><p>{transaction.procurement?.purpose ?? 'No purpose recorded.'}</p><dl><div><dt>Procurement</dt><dd>{transaction.procurement ? <Link href={route('procurements.show', transaction.procurement.id)}>#{transaction.procurement.id}</Link> : 'Not recorded'}</dd></div><div><dt>End user office</dt><dd>{transaction.procurement?.end_user?.name ?? 'Not recorded'}</dd></div><div><dt>Paper QR</dt><dd>Exact document reference</dd></div></dl><Link className="qh-link" href={documentUrl}>View document<ArrowUpRight aria-hidden="true"/></Link><div className="qh-route"><h3>Document route</h3>{steps.length ? <ol>{steps.map(step => {
                    const completed = step.step_order < currentOrder || (receiving && step.step_order === currentOrder && step.office_id !== transaction.current_office?.id);
                    const current = receivingStep ? step.id === receivingStep.id : step.id === transaction.current_step_id;
                    return <li key={step.id} className={completed ? 'is-complete' : current ? 'is-current' : ''}><span>{completed ? <Check aria-hidden="true"/> : step.step_order}</span><div><strong>{step.office_name}</strong><small>{completed ? 'Step completed' : current ? receiving ? 'Awaiting your receipt' : 'Current office' : 'Upcoming office'}</small></div></li>;
                })}</ol> : <p className="qh-muted">No workflow assigned.</p>}</div></section></aside>
            </div>
            <footer className="qh-page-footer">Local Government of Quezon, Bukidnon<span>QR endorsement & receipt</span></footer>
        </main>
        <Dialog open={confirming} onOpenChange={open => { if (!form.processing) setConfirming(open); }}><DialogContent className="op-root op-live-overlay dw-dialog qh-dialog" onEscapeKeyDown={event => { if (form.processing) event.preventDefault(); }} onPointerDownOutside={event => { if (form.processing) event.preventDefault(); }}><DialogHeader><DialogTitle>{receiving ? 'Confirm physical receipt?' : 'Endorse this document?'}</DialogTitle><DialogDescription>{receiving ? 'Confirm that your office has the paper document for this reference.' : 'The paper document will be sent to the selected office for its own QR receipt check.'}</DialogDescription></DialogHeader><div className="qh-confirm-summary"><CheckCircle2 aria-hidden="true"/><div><strong>{transaction.reference_number}</strong><span>Scanned reference matches</span></div></div><dl className="qh-confirm-facts"><div><dt>From</dt><dd>{sourceOffice?.name ?? 'Origin not recorded'}</dd></div><div><dt>{receiving ? 'Received at' : 'Endorse to'}</dt><dd>{destination?.name ?? 'Not assigned'}</dd></div>{!receiving && <div><dt>Action taken</dt><dd>{selectedAction?.description}</dd></div>}{form.data.notes && <div><dt>Notes</dt><dd>{form.data.notes}</dd></div>}</dl>{outOfWorkflow && <p className="qh-workflow-warning"><TriangleAlert aria-hidden="true"/>This endorsement is outside the expected workflow.</p>}<DialogFooter><Button variant="outline" disabled={form.processing} onClick={() => setConfirming(false)}>Go back</Button><Button disabled={!canConfirm} onClick={confirmHandoff}>{form.processing ? 'Saving…' : receiving ? 'Confirm receipt' : 'Confirm endorsement'}</Button></DialogFooter></DialogContent></Dialog>
        <Dialog open={help} onOpenChange={setHelp}><DialogContent className="op-root op-live-overlay dw-dialog qh-dialog"><DialogHeader><DialogTitle>Use a QR scanner gun</DialogTitle><DialogDescription>Set up the scanner for document endorsement and receipt.</DialogDescription></DialogHeader><ol className="qh-help"><li>Connect the scanner to this device using USB or Bluetooth keyboard mode.</li><li>Configure the scanner to send Enter after each QR code.</li><li>Click the scanned reference field, then scan the QR printed on the paper document.</li><li>Check the matching reference, review the office, then confirm the action.</li></ol><div className="qh-help-note"><Keyboard aria-hidden="true"/><p>The scanner enters the document reference like a keyboard. Keep the scan field focused. A scan alone does not save an endorsement or receipt.</p></div><DialogFooter><Button onClick={() => { setHelp(false); requestAnimationFrame(() => scanInput.current?.focus({ preventScroll: true })); }}>Ready to scan</Button></DialogFooter></DialogContent></Dialog>
    </DashboardLayout>;
}
