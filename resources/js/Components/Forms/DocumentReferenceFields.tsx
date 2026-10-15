import { useId } from 'react';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface ReferenceNumbering {
    year: string;
    month: string;
    next_number: string;
}

interface Props {
    category: 'PR' | 'PO' | 'VCH';
    numbering: ReferenceNumbering;
    year: string;
    month: string;
    onYearChange: (value: string) => void;
    onMonthChange: (value: string) => void;
    fundAbbreviation?: string;
    continuation?: boolean;
    onContinuationChange?: (value: boolean) => void;
    manual?: boolean;
    onManualChange?: (value: boolean) => void;
    reference?: string;
    onReferenceChange?: (value: string) => void;
    errors: Record<string, string | undefined>;
}

export default function DocumentReferenceFields({ category, numbering, year, month, onYearChange, onMonthChange,
    fundAbbreviation, continuation = false, onContinuationChange, manual = false, onManualChange,
    reference = '', onReferenceChange, errors }: Props) {
    const id = useId();
    const usesManual = category !== 'PR' && manual;
    const estimate = year === numbering.year ? numbering.next_number : '';
    const prefix = `${continuation && category !== 'VCH' ? 'CONT-' : ''}${category}${category === 'PR' ? `-${fundAbbreviation || 'FUND'}` : ''}`;
    const preview = `${prefix}-${year || 'YYYY'}-${month || 'MM'}-${estimate || '(assigned on save)'}`;

    return <section className="space-y-4" aria-label="Document reference">
        <div>
            <h3 className="font-semibold text-foreground">Reference number</h3>
            <p className="mt-1 text-sm text-muted-foreground">{category === 'PR'
                ? 'PR references are assigned automatically and cannot be overridden.'
                : 'Use automatic numbering, or enter the reference issued by your office.'}</p>
        </div>
        {category !== 'PR' && <label className="flex items-center gap-2 text-sm" htmlFor={id + '-manual'}>
            <Checkbox id={id + '-manual'} checked={usesManual} onChange={event => onManualChange?.(event.target.checked)} />
            Use an office-issued reference number
        </label>}
        {usesManual ? <div>
            <InputLabel htmlFor={id + '-reference'} value="Office-issued reference number *" />
            <TextInput id={id + '-reference'} className="mt-1 block w-full" value={reference}
                onChange={event => onReferenceChange?.(event.target.value)} required maxLength={50}
                aria-invalid={!!errors.reference_number} aria-describedby={id + '-reference-help'} />
            <p id={id + '-reference-help'} className="mt-1 text-sm text-muted-foreground">Enter the full reference exactly as issued. It must be unique.</p>
            <InputError message={errors.reference_number} className="mt-2" />
        </div> : <>
            {category !== 'VCH' && <label className="flex items-center gap-2 text-sm" htmlFor={id + '-continuation'}>
                <Checkbox id={id + '-continuation'} checked={continuation} onChange={event => onContinuationChange?.(event.target.checked)} />
                Continuation from a previous year
            </label>}
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel htmlFor={id + '-year'} value="Reference year *" />
                    <TextInput id={id + '-year'} className="mt-1 block w-full" value={year} onChange={event => onYearChange(event.target.value)}
                        inputMode="numeric" maxLength={4} pattern="[0-9]{4}" required aria-invalid={!!errors.ref_year} />
                    <InputError message={errors.ref_year} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor={id + '-month'} value="Reference month *" />
                    <TextInput id={id + '-month'} className="mt-1 block w-full" value={month} onChange={event => onMonthChange(event.target.value)}
                        inputMode="numeric" maxLength={2} pattern="(0[1-9]|1[0-2])" required aria-invalid={!!errors.ref_month} />
                    <InputError message={errors.ref_month} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor={id + '-number'} value="Next number (estimate)" />
                    <TextInput id={id + '-number'} className="mt-1 block w-full bg-muted" value={estimate || 'Assigned on save'} readOnly aria-describedby={id + '-automatic-help'} />
                </div>
            </div>
            <div className="rounded-md border border-border bg-muted/40 p-4" aria-live="polite">
                <p className="text-sm text-muted-foreground">Suggested reference</p>
                <p className="mt-1 break-words font-semibold text-foreground">{preview}</p>
                <p id={id + '-automatic-help'} className="mt-2 text-sm text-muted-foreground">The final number is assigned when saved. Each document type restarts at 001 each year.</p>
            </div>
            <InputError message={errors.ref_number || errors.reference_number} />
        </>}
        <InputError message={errors.manual_reference || errors.is_continuation} />
    </section>;
}
