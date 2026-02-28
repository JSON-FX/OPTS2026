import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Office } from '@/types/models';

export default function Edit({
    offices,
}: PageProps<{ offices: Office[] }>) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <section className="max-w-xl">
                            <header>
                                <h2 className="text-lg font-medium text-gray-900">
                                    Profile Information
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Your profile information is managed through LGU-SSO.
                                </p>
                            </header>

                            <div className="mt-6 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Name
                                    </label>
                                    <p className="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                        {user.name}
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Email
                                    </label>
                                    <p className="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                        {user.email}
                                    </p>
                                </div>

                                {user.sso_position && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Position
                                        </label>
                                        <p className="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                            {user.sso_position}
                                        </p>
                                    </div>
                                )}

                                {user.office && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Office
                                        </label>
                                        <p className="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                            {user.office.name}
                                        </p>
                                    </div>
                                )}

                                {user.roles && user.roles.length > 0 && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Role
                                        </label>
                                        <p className="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                            {user.roles[0].name}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
