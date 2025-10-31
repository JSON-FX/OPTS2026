import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function ForbiddenPage() {
    return (
        <GuestLayout>
            <Head title="403 Forbidden" />
            <div className="flex min-h-screen items-center justify-center bg-gray-100">
                <div className="text-center">
                    <h1 className="text-6xl font-bold text-gray-900">403</h1>
                    <p className="mt-4 text-xl text-gray-600">Access Forbidden</p>
                    <p className="mt-2 text-gray-500">
                        You do not have permission to access this page.
                    </p>
                    <Link
                        href={route('dashboard')}
                        className="mt-6 inline-block rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                    >
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
