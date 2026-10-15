import { useState, type ReactNode } from 'react';
import { Check, CheckCircle2, ChevronsUpDown, Clock3, PauseCircle, XCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import type { Office } from './types';
export function Field({ id, label, required, error, hint, children }: { id: string; label: string; required?: boolean; error?: string; hint?: string; children: ReactNode }) {
    return <div className="dw-field"><Label htmlFor={id}>{label}{required && ' *'}</Label>{children}{hint && <p id={id+'-hint'} className="dw-hint">{hint}</p>}{error && <p id={id+'-error'} className="dw-field-error" role="alert">{error}</p>}</div>;
}
export function Combo({ id, label, value, onChange, options, placeholder, error, disabled }: { id: string; label: string; value: string; onChange: (value: string) => void; options: { value: string; label: string }[]; placeholder: string; error?: string; disabled?: boolean }) {
    const [open, setOpen] = useState(false);
    return <Popover open={open} onOpenChange={setOpen}><PopoverTrigger asChild><Button id={id} type="button" variant="outline" role="combobox" aria-label={label} aria-expanded={open} aria-invalid={!!error} aria-describedby={error ? id+'-error' : undefined} disabled={disabled} className="dw-combo"><span>{options.find(option => option.value === value)?.label ?? placeholder}</span><ChevronsUpDown /></Button></PopoverTrigger><PopoverContent align="start" className="op-root op-live-overlay dw-floating dw-combo-popup"><Command><CommandInput placeholder={'Search '+label.toLowerCase()+'…'} aria-label={'Search '+label.toLowerCase()} /><CommandList><CommandEmpty>No matching options.</CommandEmpty><CommandGroup>{options.map(option => <CommandItem key={option.value} value={option.value+' '+option.label} onSelect={() => { onChange(option.value); setOpen(false); }}>{option.label}<Check className={'dw-choice-check '+(value === option.value ? '' : 'dw-hidden')} /></CommandItem>)}</CommandGroup></CommandList></Command></PopoverContent></Popover>;
}
export function OfficeLabel({ office }: { office?: Office | null }) {
    if (!office) return <span>Not assigned</span>;
    if (!office.abbreviation) return <span>{office.name}</span>;
    return <TooltipProvider><Tooltip><TooltipTrigger asChild><span className="dw-office" tabIndex={0}>{office.abbreviation}</span></TooltipTrigger><TooltipContent className="op-live-overlay">{office.name}</TooltipContent></Tooltip></TooltipProvider>;
}
export function DocumentStatus({ status }: { status: string }) {
    const Icon = status === 'Completed' ? CheckCircle2 : status === 'Cancelled' ? XCircle : status === 'On Hold' ? PauseCircle : Clock3;
    return <Badge className={'dw-status dw-status-'+status.toLowerCase().replaceAll(' ', '-')}><Icon aria-hidden="true"/>{status}</Badge>;
}
