import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import DashboardLayout from '@/Layouts/DashboardLayout';
import type { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CircleHelp } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

export default function Edit() {
    const [helpOpen, setHelpOpen] = useState(false);
    const { auth: { user }, selectedYear, availableYears } = usePage<PageProps>().props;
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({ year: selectedYear });

    useEffect(() => { setData('year', selectedYear); }, [selectedYear]);

    const details = [
        ['Name', user.name],
        ['Email', user.email ?? 'Not provided'],
        ['Position', user.sso_position],
        ['Office', user.office ? `${user.office.abbreviation} · ${user.office.name}` : 'No assigned office'],
        ['Access', user.roles?.map(role => role.name).join(', ') || 'Viewer'],
    ].filter((detail): detail is [string, string] => Boolean(detail[1]));

    return (
        <DashboardLayout title="Settings" mainId="settings-main" rootClassName="op-settings-page">
            <Head title="Settings" />
            <main id="settings-main" className="op-main" tabIndex={-1}>
                <div className="op-page-head"><div><h1>Settings</h1><p>Your reporting preferences and SSO profile.</p></div></div>
                <div className="op-settings-grid">
                    <section className="op-settings-card" aria-labelledby="reporting-title">
                        <h2 id="reporting-title">Reporting year</h2>
                        <p>Select the year shown across Dashboard, Procurements, and Transactions.</p>
                        <form onSubmit={(event) => {
                            event.preventDefault();
                            put(route('user.selected-year.update'), { preserveScroll: true });
                        }}>
                            <Label htmlFor="reporting-year">Year</Label>
                            <Select value={String(data.year)} onValueChange={(year) => setData('year', Number(year))} disabled={processing}>
                                <SelectTrigger id="reporting-year" aria-invalid={Boolean(errors.year)} aria-describedby={errors.year ? 'reporting-year-error' : undefined}><SelectValue /></SelectTrigger>
                                <SelectContent className="op-live op-live-overlay">{availableYears.map(year => <SelectItem key={year} value={String(year)}>{year}</SelectItem>)}</SelectContent>
                            </Select>
                            {errors.year && <p id="reporting-year-error" className="op-settings-error" role="alert">{errors.year}</p>}
                            <div className="op-settings-save"><Button type="submit" disabled={processing || data.year === selectedYear}>{processing ? 'Saving…' : 'Save settings'}</Button><span role="status">{recentlySuccessful ? 'Settings saved.' : ''}</span></div>
                        </form>
                    </section>
                    <section className="op-settings-card" aria-labelledby="profile-title">
                        <h2 id="profile-title">SSO profile</h2>
                        <p>Your identity, office, and access are managed through LGU SSO.</p>
                        <dl className="op-settings-profile">{details.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
                        <p>Contact your SSO administrator to update this information.</p>
                    </section>
                    <section className="op-settings-card" aria-labelledby="help-title">
                        <h2 id="help-title">Help & reference</h2>
                        <p>Learn how documents, office routes, and your access work together.</p>
                        <Button type="button" variant="outline" onClick={() => setHelpOpen(true)}><CircleHelp className="mr-2 h-4 w-4" aria-hidden="true" />Open help & reference</Button>
                    </section>
                </div>
                <Dialog open={helpOpen} onOpenChange={setHelpOpen}>
                    <DialogContent overlayClassName="op-modal-overlay" className="op-root op-live op-dashboard-dialog op-help-dialog">
                        <DialogHeader><DialogTitle>Help & reference</DialogTitle><DialogDescription>Procurement tracking for the Local Government of Quezon, Bukidnon.</DialogDescription></DialogHeader>
                        <div className="space-y-4 text-sm">
                            <p>Each procurement links its purchase request, purchase order, and voucher. Open a document to see its current office and workflow timeline.</p>
                            <p>Viewers inspect records. Endorsers process documents within their assigned office. Administrators manage workflows and directories.</p>
                            <p>Your identity, office, and access come from LGU SSO. Contact your SSO administrator for changes.</p>
                        </div>
                    </DialogContent>
                </Dialog>
                <footer className="op-compat-footer">Local Government of Quezon, Bukidnon</footer>
            </main>
        </DashboardLayout>
    );
}
