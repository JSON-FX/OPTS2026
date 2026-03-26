import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }: PageProps) {
    const year = new Date().getFullYear();

    return (
        <>
            <Head title="OPTS — Online Procurement Tracking System" />

            <div className="min-h-screen flex flex-col bg-white relative overflow-hidden">
                {/* Background pattern */}
                <div className="absolute inset-0 pointer-events-none">
                    <div className="absolute top-0 right-0 w-[800px] h-[800px] bg-gradient-to-bl from-emerald-50 via-teal-50/40 to-transparent rounded-full translate-x-1/3 -translate-y-1/3" />
                    <div className="absolute bottom-0 left-0 w-[600px] h-[600px] bg-gradient-to-tr from-amber-50/60 via-orange-50/30 to-transparent rounded-full -translate-x-1/4 translate-y-1/4" />
                    <div
                        className="absolute inset-0 opacity-[0.03]"
                        style={{
                            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23000000' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
                        }}
                    />
                </div>

                {/* Top nav */}
                <nav className="relative z-10 w-full border-b border-slate-100">
                    <div className="max-w-7xl mx-auto px-6 sm:px-8 py-4 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <img
                                src="/lgu-seal.png"
                                alt="LGU Quezon"
                                className="h-9 w-9 rounded-full ring-2 ring-slate-100"
                            />
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-slate-900 tracking-tight">
                                    OPTS
                                </span>
                                <span className="hidden sm:block text-[11px] text-slate-400 font-medium uppercase tracking-widest">
                                    Quezon, Bukidnon
                                </span>
                            </div>
                        </div>
                        <Link
                            href={auth.user ? route('dashboard') : route('login')}
                            className="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition-all duration-200 shadow-sm hover:shadow-md"
                        >
                            {auth.user ? 'Dashboard' : 'Sign In'}
                            <svg
                                className="w-3.5 h-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                strokeWidth={2.5}
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                />
                            </svg>
                        </Link>
                    </div>
                </nav>

                {/* Hero */}
                <main className="relative z-10 flex-1 flex items-center">
                    <div className="max-w-7xl mx-auto px-6 sm:px-8 w-full">
                        <div className="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                            {/* Left — text content */}
                            <div className="max-w-xl">
                                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 mb-6">
                                    <div className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                    <span className="text-xs font-semibold text-emerald-700 uppercase tracking-wider">
                                        Government System
                                    </span>
                                </div>

                                <h1 className="text-4xl sm:text-5xl lg:text-[3.5rem] font-extrabold text-slate-900 tracking-tight leading-[1.1]">
                                    Online
                                    <br />
                                    Procurement
                                    <br />
                                    <span className="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
                                        Tracking System
                                    </span>
                                </h1>

                                <p className="mt-5 text-base sm:text-lg text-slate-500 leading-relaxed max-w-md">
                                    Streamline and monitor procurement activities for the
                                    Municipality of Quezon, Province of Bukidnon.
                                </p>

                                <div className="mt-8 flex flex-col sm:flex-row gap-3">
                                    <Link
                                        href={auth.user ? route('dashboard') : route('login')}
                                        className="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-xl bg-slate-900 text-white font-semibold text-sm hover:bg-slate-800 transition-all duration-200 shadow-lg shadow-slate-900/10 hover:shadow-xl hover:shadow-slate-900/20"
                                    >
                                        {auth.user ? 'Go to Dashboard' : 'Sign In with LGU-SSO'}
                                        <svg
                                            className="w-4 h-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            strokeWidth={2}
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                            />
                                        </svg>
                                    </Link>
                                </div>

                                {/* Stats */}
                                <div className="mt-12 grid grid-cols-3 gap-6 border-t border-slate-100 pt-8">
                                    <div>
                                        <div className="text-2xl font-bold text-slate-900">
                                            100%
                                        </div>
                                        <div className="text-xs text-slate-400 mt-0.5 font-medium">
                                            Digital Process
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-2xl font-bold text-slate-900">
                                            Real-time
                                        </div>
                                        <div className="text-xs text-slate-400 mt-0.5 font-medium">
                                            Status Tracking
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-2xl font-bold text-slate-900">
                                            Secure
                                        </div>
                                        <div className="text-xs text-slate-400 mt-0.5 font-medium">
                                            SSO Protected
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Right — seal visual */}
                            <div className="hidden lg:flex items-center justify-center">
                                <div className="relative">
                                    {/* Glow ring */}
                                    <div className="absolute inset-0 rounded-full bg-gradient-to-br from-emerald-200/40 via-teal-100/20 to-amber-100/30 blur-2xl scale-110" />
                                    <div className="absolute inset-0 rounded-full border-2 border-dashed border-slate-200/60 scale-125 animate-[spin_60s_linear_infinite]" />
                                    <img
                                        src="/lgu-seal.png"
                                        alt="Municipality of Quezon Official Seal"
                                        className="relative w-72 h-72 xl:w-80 xl:h-80 drop-shadow-2xl"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="relative z-10 w-full border-t border-slate-100">
                    <div className="max-w-7xl mx-auto px-6 sm:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
                        <p className="text-xs text-slate-400">
                            &copy; {year} Local Government of Quezon Bukidnon. All rights reserved.
                        </p>
                        <p className="text-xs text-slate-300">
                            OPTS {year}
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
