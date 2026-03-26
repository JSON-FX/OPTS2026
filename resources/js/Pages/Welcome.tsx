import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({
    auth,
}: PageProps) {
    return (
        <>
            <Head title="OPTS - Online Performance Tracking System" />
            <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-amber-50/30 flex flex-col">
                {/* Nav */}
                <nav className="w-full px-6 py-4 flex items-center justify-between max-w-6xl mx-auto">
                    <div className="flex items-center gap-3">
                        <img
                            src="/lgu-seal.png"
                            alt="LGU Quezon"
                            className="h-10 w-10 rounded-full shadow-sm"
                        />
                        <div>
                            <span className="font-bold text-slate-900 text-sm tracking-tight">
                                OPTS
                            </span>
                            <span className="hidden sm:inline text-slate-400 text-sm ml-1.5">
                                | Municipality of Quezon
                            </span>
                        </div>
                    </div>
                    <div>
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="inline-flex items-center px-5 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm"
                            >
                                Go to Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="inline-flex items-center px-5 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm"
                            >
                                Sign In
                            </Link>
                        )}
                    </div>
                </nav>

                {/* Hero */}
                <main className="flex-1 flex items-center justify-center px-6">
                    <div className="max-w-2xl text-center">
                        <div className="flex justify-center mb-8">
                            <img
                                src="/lgu-seal.png"
                                alt="Municipality of Quezon Official Seal"
                                className="h-28 w-28 sm:h-36 sm:w-36 drop-shadow-lg"
                            />
                        </div>

                        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight leading-tight">
                            Online Performance
                            <br />
                            <span className="text-amber-700">Tracking System</span>
                        </h1>

                        <p className="mt-4 text-base sm:text-lg text-slate-500 max-w-lg mx-auto leading-relaxed">
                            Municipality of Quezon, Province of Bukidnon
                        </p>

                        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-flex items-center px-8 py-3 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-colors shadow-md text-sm"
                                >
                                    Open Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center px-8 py-3 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800 transition-colors shadow-md text-sm"
                                >
                                    Sign In with LGU-SSO
                                </Link>
                            )}
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="w-full px-6 py-6 text-center">
                    <p className="text-xs text-slate-400">
                        &copy; {new Date().getFullYear()} Local Government of Quezon Bukidnon. All rights reserved.
                    </p>
                </footer>
            </div>
        </>
    );
}
