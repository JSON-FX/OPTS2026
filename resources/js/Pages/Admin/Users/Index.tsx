import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { SsoDirectoryPage } from '@/types/models';
import { FormEvent, useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props extends PageProps {
    directory: SsoDirectoryPage;
    filters: { search: string };
}

export default function Index({ directory, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);

    const loadPage = (page: number, term = filters.search) => {
        router.get(route('admin.users.index'), { search: term, page }, {
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
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">SSO User Directory</h2>}>
            <Head title="SSO User Directory" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <section className="rounded-lg bg-white p-6 shadow-sm space-y-4">
                        <h3 className="font-semibold">Access comes from SSO</h3>
                        <p className="text-sm text-muted-foreground">
                            Only people with current OPTS access are listed. Names, offices, positions and access roles are managed in SSO.
                            A job title never grants permission to process transactions.
                        </p>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">SSO roles and their OPTS permissions</caption>
                                <thead><tr className="border-b"><th className="p-2">SSO role</th><th className="p-2">OPTS role</th><th className="p-2">Access</th></tr></thead>
                                <tbody className="divide-y">
                                    <tr><td className="p-2">Guest</td><td className="p-2">Viewer</td><td className="p-2">View dashboards and procurement details. Cannot create, receive, endorse or complete transactions.</td></tr>
                                    <tr><td className="p-2">Standard</td><td className="p-2">Endorser</td><td className="p-2">Manage procurement records. Receive, endorse and complete transactions assigned to their active office.</td></tr>
                                    <tr><td className="p-2">Administrator / Super administrator</td><td className="p-2">Administrator</td><td className="p-2">Manage workflows and configuration, view directories and process transactions under office eligibility rules.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section className="rounded-lg bg-white p-6 shadow-sm space-y-6" aria-label="Eligible users">
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <div className="space-y-2">
                                <Label htmlFor="directory-search">Search people by name</Label>
                                <Input id="directory-search" type="search" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search names..." />
                            </div>
                            <Button type="submit" disabled={loading}>Search</Button>
                        </form>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm" aria-busy={loading}>
                                <thead className="border-b"><tr><th className="p-3">Name</th><th className="p-3">SSO role</th><th className="p-3">OPTS access</th><th className="p-3">Office</th><th className="p-3">Position</th></tr></thead>
                                <tbody className="divide-y">
                                    {directory.data.map((user) => (
                                        <tr key={user.uuid}>
                                            <td className="p-3 font-medium">{user.full_name}</td>
                                            <td className="p-3 capitalize">{user.role.replace(/_/g, ' ')}</td>
                                            <td className="p-3"><Badge variant={user.opts_role === 'Administrator' ? 'default' : 'secondary'}>{user.opts_role}</Badge></td>
                                            <td className="p-3" title={user.office_name ?? undefined}>{user.office_abbreviation ?? 'No office assigned'}</td>
                                            <td className="p-3">{user.position_title ?? 'No position assigned'}</td>
                                        </tr>
                                    ))}
                                    {directory.data.length === 0 && <tr><td colSpan={5} className="p-6 text-center text-muted-foreground">No eligible users found.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm text-muted-foreground">Page {directory.meta.page}</span>
                            <div className="flex gap-2">
                                <Button variant="outline" disabled={loading || directory.meta.page <= 1} onClick={() => loadPage(directory.meta.page - 1)}>Previous</Button>
                                <Button variant="outline" disabled={loading || !directory.meta.has_more} onClick={() => loadPage(directory.meta.page + 1)}>Next</Button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
