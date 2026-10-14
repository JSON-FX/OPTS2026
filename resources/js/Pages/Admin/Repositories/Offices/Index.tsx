import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { Office, PaginatedData } from '@/types/models';
import { FormEvent, useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface DirectoryOffice extends Pick<Office, 'id' | 'name' | 'abbreviation' | 'is_active'> {
    sso_id: number | null;
    sso_synced_at: string | null;
}

interface Props extends PageProps {
    offices: PaginatedData<DirectoryOffice>;
    filters: { search: string };
}

export default function Index({ offices, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);

    const loadPage = (page: number, term = filters.search) => {
        router.get(route('admin.repositories.offices.index'), { search: term, page }, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        loadPage(1, search);
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">SSO Office Directory</h2>}>
            <Head title="SSO Office Directory" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <section className="rounded-lg bg-white p-6 shadow-sm space-y-6">
                        <div className="space-y-2">
                            <h3 className="font-semibold">Managed in SSO</h3>
                            <p className="text-sm text-muted-foreground">Office changes refresh automatically from SSO. Only active, mapped offices can receive new assignments. Historical offices remain available in transaction history.</p>
                        </div>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="office-search">Search offices</Label>
                                <Input id="office-search" type="search" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or abbreviation..." />
                            </div>
                            <Button type="submit" disabled={loading}>Search</Button>
                        </form>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm" aria-busy={loading}>
                                <thead className="border-b"><tr><th className="p-3">Abbreviation</th><th className="p-3">Office</th><th className="p-3">Local ID</th><th className="p-3">SSO ID</th><th className="p-3">Status</th><th className="p-3">Last synced</th></tr></thead>
                                <tbody className="divide-y">
                                    {offices.data.map((office) => (
                                        <tr key={office.id}>
                                            <td className="p-3 font-medium">{office.abbreviation}</td>
                                            <td className="p-3">{office.name}</td>
                                            <td className="p-3">{office.id}</td>
                                            <td className="p-3">{office.sso_id ?? 'Unmapped'}</td>
                                            <td className="p-3"><Badge variant={office.sso_id && office.is_active ? 'default' : 'secondary'}>{!office.sso_id ? 'Unmapped — history only' : office.is_active ? 'Active' : 'Inactive — history only'}</Badge></td>
                                            <td className="p-3">{office.sso_synced_at ? new Date(office.sso_synced_at).toLocaleString() : 'Not synced'}</td>
                                        </tr>
                                    ))}
                                    {offices.data.length === 0 && <tr><td colSpan={6} className="p-6 text-center text-muted-foreground">No offices found.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm text-muted-foreground">Page {offices.current_page} of {offices.last_page}</span>
                            <div className="flex gap-2">
                                <Button variant="outline" disabled={loading || offices.current_page <= 1} onClick={() => loadPage(offices.current_page - 1)}>Previous</Button>
                                <Button variant="outline" disabled={loading || offices.current_page >= offices.last_page} onClick={() => loadPage(offices.current_page + 1)}>Next</Button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
