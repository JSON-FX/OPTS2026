import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Toaster } from '@/Components/ui/toaster';
import { useToast } from '@/Components/ui/use-toast';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;
    const flash = usePage<PageProps>().props.flash;
    const { toast } = useToast();

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    // Helper functions to check user roles
    const hasRole = (roleName: string): boolean => {
        return user.roles?.some(role => role.name === roleName) ?? false;
    };

    const hasAnyRole = (...roleNames: string[]): boolean => {
        return user.roles?.some(role => roleNames.includes(role.name)) ?? false;
    };

    useEffect(() => {
        if (flash?.success) {
            toast({ description: flash.success, variant: 'success' });
        }
        if (flash?.error) {
            toast({ description: flash.error, variant: 'destructive' });
        }
    }, [flash, toast]);

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>

                                <NavLink
                                    href={route('procurements.index')}
                                    active={route().current('procurements.*')}
                                >
                                    Procurements
                                </NavLink>

                                <NavLink
                                    href={route('transactions.index')}
                                    active={route().current('transactions.*')}
                                >
                                    Transactions
                                </NavLink>

                                {/* Administrator Only - Admin Dropdown */}
                                {hasRole('Administrator') && (
                                    <div className="relative flex items-center">
                                        <Dropdown>
                                            <Dropdown.Trigger>
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-gray-500 transition duration-150 ease-in-out hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700 focus:outline-none"
                                                >
                                                    Admin
                                                    <svg
                                                        className="-me-0.5 ms-1 h-4 w-4"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </Dropdown.Trigger>

                                            <Dropdown.Content>
                                                <Dropdown.Link href={route('admin.users.index')}>
                                                    Users
                                                </Dropdown.Link>

                                                <div className="border-t border-gray-100" />

                                                <div className="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">
                                                    Repositories
                                                </div>
                                                <Dropdown.Link href={route('admin.repositories.offices.index')}>
                                                    Offices
                                                </Dropdown.Link>
                                                <Dropdown.Link href={route('admin.repositories.suppliers.index')}>
                                                    Suppliers
                                                </Dropdown.Link>
                                                <Dropdown.Link href={route('admin.repositories.particulars.index')}>
                                                    Particulars
                                                </Dropdown.Link>
                                                <Dropdown.Link href={route('admin.repositories.fund-types.index')}>
                                                    Fund Types
                                                </Dropdown.Link>
                                                <Dropdown.Link href={route('admin.repositories.action-taken.index')}>
                                                    Action Taken
                                                </Dropdown.Link>
                                            </Dropdown.Content>
                                        </Dropdown>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {/* Notification Bell Icon Placeholder */}
                            <button
                                type="button"
                                className="relative rounded-full p-2 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                <Bell className="h-6 w-6" />
                                {/* Placeholder badge for unread notifications (Epic 4) */}
                                <span className="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white" />
                            </button>

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <div className="px-4 py-2 border-b border-gray-100">
                                            <div className="font-medium text-gray-800">{user.name}</div>
                                            <div className="text-sm text-gray-500">{user.email}</div>
                                            {user.roles && user.roles.length > 0 && (
                                                <div className="text-sm text-gray-500">
                                                    Role: {user.roles[0].name}
                                                </div>
                                            )}
                                            {user.office && (
                                                <div className="text-sm text-gray-500">
                                                    Office: {user.office.name}
                                                </div>
                                            )}
                                        </div>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('procurements.index')}
                            active={route().current('procurements.*')}
                        >
                            Procurements
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('transactions.index')}
                            active={route().current('transactions.*')}
                        >
                            Transactions
                        </ResponsiveNavLink>

                        {/* Administrator Only - Admin Menu */}
                        {hasRole('Administrator') && (
                            <>
                                <div className="border-t border-gray-200 pt-2">
                                    <div className="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">
                                        Admin
                                    </div>
                                    <ResponsiveNavLink href={route('admin.users.index')}>
                                        Users
                                    </ResponsiveNavLink>
                                    <div className="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">
                                        Repositories
                                    </div>
                                    <ResponsiveNavLink href={route('admin.repositories.offices.index')}>
                                        Offices
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route('admin.repositories.suppliers.index')}>
                                        Suppliers
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route('admin.repositories.particulars.index')}>
                                        Particulars
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route('admin.repositories.fund-types.index')}>
                                        Fund Types
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink href={route('admin.repositories.action-taken.index')}>
                                        Action Taken
                                    </ResponsiveNavLink>
                                </div>
                            </>
                        )}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                            {user.roles && user.roles.length > 0 && (
                                <div className="text-sm font-medium text-gray-500">
                                    Role: {user.roles[0].name}
                                </div>
                            )}
                            {user.office && (
                                <div className="text-sm font-medium text-gray-500">
                                    Office: {user.office.name}
                                </div>
                            )}
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
            <Toaster />
        </div>
    );
}
