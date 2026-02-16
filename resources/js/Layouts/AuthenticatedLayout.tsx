import ApplicationLogo from '@/Components/ApplicationLogo';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/Components/ui/navigation-menu';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { Toaster } from '@/Components/ui/toaster';
import { useToast } from '@/Components/ui/use-toast';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { NotificationBell } from '@/Components/NotificationBell';
import { ChevronDown, Menu } from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;
    const flash = usePage<PageProps>().props.flash;
    const pendingReceiptsCount = usePage<PageProps>().props.pendingReceiptsCount ?? 0;
    const { toast } = useToast();

    const [sheetOpen, setSheetOpen] = useState(false);

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

                            {/* Desktop Navigation with NavigationMenu */}
                            <div className="hidden md:ms-10 md:flex md:items-center">
                                <NavigationMenu>
                                    <NavigationMenuList>
                                        <NavigationMenuItem>
                                            <Link href={route('dashboard')}>
                                                <NavigationMenuLink
                                                    className={navigationMenuTriggerStyle()}
                                                    active={route().current('dashboard')}
                                                >
                                                    Dashboard
                                                </NavigationMenuLink>
                                            </Link>
                                        </NavigationMenuItem>

                                        <NavigationMenuItem>
                                            <Link href={route('procurements.index')}>
                                                <NavigationMenuLink
                                                    className={navigationMenuTriggerStyle()}
                                                    active={route().current('procurements.*')}
                                                >
                                                    Procurements
                                                </NavigationMenuLink>
                                            </Link>
                                        </NavigationMenuItem>

                                        <NavigationMenuItem>
                                            <Link href={route('transactions.index')}>
                                                <NavigationMenuLink
                                                    className={navigationMenuTriggerStyle()}
                                                    active={route().current('transactions.index')}
                                                >
                                                    Transactions
                                                </NavigationMenuLink>
                                            </Link>
                                        </NavigationMenuItem>

                                        {hasAnyRole('Endorser', 'Administrator') && (
                                            <NavigationMenuItem>
                                                <Link href={route('transactions.pending')}>
                                                    <NavigationMenuLink
                                                        className={navigationMenuTriggerStyle()}
                                                        active={route().current('transactions.pending')}
                                                    >
                                                        Pending Receipts
                                                        {pendingReceiptsCount > 0 && (
                                                            <span className="ml-1.5 inline-flex items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-medium text-white">
                                                                {pendingReceiptsCount}
                                                            </span>
                                                        )}
                                                    </NavigationMenuLink>
                                                </Link>
                                            </NavigationMenuItem>
                                        )}

                                        {/* Administrator Only - Admin Dropdown */}
                                        {hasRole('Administrator') && (
                                            <NavigationMenuItem>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <button
                                                            className={navigationMenuTriggerStyle()}
                                                        >
                                                            Admin
                                                            <ChevronDown className="ml-1 h-4 w-4" />
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="start">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.workflows.index')}>
                                                                Workflows
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.users.index')}>
                                                                Users
                                                            </Link>
                                                        </DropdownMenuItem>

                                                        <DropdownMenuSeparator />

                                                        <DropdownMenuLabel>
                                                            Repositories
                                                        </DropdownMenuLabel>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.repositories.offices.index')}>
                                                                Offices
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.repositories.suppliers.index')}>
                                                                Suppliers
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.repositories.particulars.index')}>
                                                                Particulars
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.repositories.fund-types.index')}>
                                                                Fund Types
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('admin.repositories.action-taken.index')}>
                                                                Action Taken
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </NavigationMenuItem>
                                        )}
                                    </NavigationMenuList>
                                </NavigationMenu>
                            </div>
                        </div>

                        <div className="hidden md:ms-6 md:flex md:items-center md:space-x-4">
                            {/* Notification Bell (Story 3.8) */}
                            <NotificationBell />

                            {/* User Dropdown with Avatar */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" className="flex items-center gap-2">
                                        <Avatar className="h-8 w-8">
                                            <AvatarFallback className="bg-primary text-primary-foreground text-xs">
                                                {user.name.substring(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="hidden lg:inline-block">{user.name}</span>
                                        <ChevronDown className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                    <div className="px-2 py-2">
                                        <div className="font-medium text-sm">{user.name}</div>
                                        <div className="text-xs text-muted-foreground">{user.email}</div>
                                        {user.roles && user.roles.length > 0 && (
                                            <div className="text-xs text-muted-foreground">
                                                Role: {user.roles[0].name}
                                            </div>
                                        )}
                                        {user.office && (
                                            <div className="text-xs text-muted-foreground">
                                                Office: {user.office.name}
                                            </div>
                                        )}
                                    </div>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href={route('profile.edit')}>
                                            Profile
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="w-full"
                                        >
                                            Log Out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        {/* Mobile Navigation with Sheet */}
                        <div className="flex items-center md:hidden">
                            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                                <SheetTrigger asChild>
                                    <Button variant="ghost" size="icon">
                                        <Menu className="h-6 w-6" />
                                        <span className="sr-only">Open navigation menu</span>
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="left" className="w-[300px] sm:w-[400px]">
                                    <nav className="flex flex-col gap-4">
                                        {/* Mobile User Info */}
                                        <div className="flex items-center gap-3 pb-4 border-b">
                                            <Avatar className="h-10 w-10">
                                                <AvatarFallback className="bg-primary text-primary-foreground">
                                                    {user.name.substring(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="flex-1 min-w-0">
                                                <div className="font-medium text-sm truncate">{user.name}</div>
                                                <div className="text-xs text-muted-foreground truncate">{user.email}</div>
                                            </div>
                                        </div>

                                        {/* Mobile Navigation Links */}
                                        <div className="flex flex-col gap-2">
                                            <Link
                                                href={route('dashboard')}
                                                onClick={() => setSheetOpen(false)}
                                                className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                            >
                                                Dashboard
                                            </Link>
                                            <Link
                                                href={route('procurements.index')}
                                                onClick={() => setSheetOpen(false)}
                                                className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                            >
                                                Procurements
                                            </Link>
                                            <Link
                                                href={route('transactions.index')}
                                                onClick={() => setSheetOpen(false)}
                                                className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                            >
                                                Transactions
                                            </Link>

                                            {hasAnyRole('Endorser', 'Administrator') && (
                                                <Link
                                                    href={route('transactions.pending')}
                                                    onClick={() => setSheetOpen(false)}
                                                    className="flex items-center gap-2 px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                >
                                                    Pending Receipts
                                                    {pendingReceiptsCount > 0 && (
                                                        <span className="inline-flex items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-medium text-white">
                                                            {pendingReceiptsCount}
                                                        </span>
                                                    )}
                                                </Link>
                                            )}

                                            {/* Administrator Only - Mobile Admin Menu */}
                                            {hasRole('Administrator') && (
                                                <>
                                                    <Separator className="my-2" />
                                                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">
                                                        Admin
                                                    </div>
                                                    <Link
                                                        href={route('admin.workflows.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Workflows
                                                    </Link>
                                                    <Link
                                                        href={route('admin.users.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Users
                                                    </Link>
                                                    <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">
                                                        Repositories
                                                    </div>
                                                    <Link
                                                        href={route('admin.repositories.offices.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Offices
                                                    </Link>
                                                    <Link
                                                        href={route('admin.repositories.suppliers.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Suppliers
                                                    </Link>
                                                    <Link
                                                        href={route('admin.repositories.particulars.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Particulars
                                                    </Link>
                                                    <Link
                                                        href={route('admin.repositories.fund-types.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Fund Types
                                                    </Link>
                                                    <Link
                                                        href={route('admin.repositories.action-taken.index')}
                                                        onClick={() => setSheetOpen(false)}
                                                        className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                                    >
                                                        Action Taken
                                                    </Link>
                                                </>
                                            )}
                                        </div>

                                        <Separator className="my-2" />

                                        {/* Mobile User Actions */}
                                        <div className="flex flex-col gap-2">
                                            <Link
                                                href={route('profile.edit')}
                                                onClick={() => setSheetOpen(false)}
                                                className="block px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                            >
                                                Profile
                                            </Link>
                                            <Link
                                                href={route('logout')}
                                                method="post"
                                                as="button"
                                                onClick={() => setSheetOpen(false)}
                                                className="block w-full text-left px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 rounded-md"
                                            >
                                                Log Out
                                            </Link>
                                        </div>
                                    </nav>
                                </SheetContent>
                            </Sheet>
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
