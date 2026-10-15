import { NotificationBell } from '@/Components/NotificationBell';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger,
} from '@/Components/ui/sheet';
import { Toaster } from '@/Components/ui/toaster';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { useToast } from '@/Components/ui/use-toast';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2, ChevronDown, ChevronRight, FileText, FolderOpen,
    LayoutDashboard, Menu, PanelLeft, Receipt, SlidersHorizontal,
} from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';

const adminLinks = [
    ['Workflows', 'admin.workflows.index'],
    ['Users', 'admin.users.index'],
    ['Offices', 'admin.repositories.offices.index'],
    ['Suppliers', 'admin.repositories.suppliers.index'],
    ['Particulars', 'admin.repositories.particulars.index'],
    ['Fund types', 'admin.repositories.fund-types.index'],
    ['Action taken', 'admin.repositories.action-taken.index'],
] as const;

interface DashboardLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
    mainId?: string;
    rootClassName?: string;
    toastFlash?: boolean;
    onHelp?: () => void;
    onPendingReceipts?: () => void;
    onViewDocument?: (transactionId: number) => void;
}

export default function DashboardLayout({ children, title, breadcrumbs, mainId = 'dashboard-main', rootClassName = '', toastFlash = true, onPendingReceipts, onViewDocument }: DashboardLayoutProps) {
    const { auth, flash, pendingReceiptsCount = 0 } = usePage<PageProps>().props;
    const user = auth.user;
    const administrator = user.roles?.some(role => role.name === 'Administrator') ?? false;
    const endorser = user.roles?.some(role => role.name === 'Endorser') ?? false;
    const roleLabel = administrator ? 'Administrator' : endorser ? 'Endorser' : 'Viewer';
    const storageKey = `opts_dashboard_sidebar_${user.id}`;
    const [collapsed, setCollapsed] = useState(() => {
        try { return localStorage.getItem(storageKey) === 'collapsed'; } catch { return false; }
    });
    const [mobileOpen, setMobileOpen] = useState(false);
    const actionAfterSheetClose = useRef<(() => void) | null>(null);
    const { toast } = useToast();
    const isDocumentCreate = route().current('procurements.purchase-requests.*') || route().current('procurements.purchase-orders.*') || route().current('procurements.vouchers.*');
    const isProcurement = route().current('procurements.*') && !isDocumentCreate;
    const isTransaction = isDocumentCreate || route().current('transactions.*') || route().current('purchase-requests.*') || route().current('purchase-orders.*') || route().current('vouchers.*');
    const currentAdminLink = adminLinks.find(([, routeName]) => route().current(routeName.replace(/\.index$/, '.*')));
    const pageTitle = title ?? (route().current('profile.*') ? 'Settings' : route().current('notifications.*') ? 'Notifications' : currentAdminLink?.[0] ?? (isProcurement ? 'Procurements' : isTransaction ? (route().current('transactions.pending') ? 'Pending receipts' : 'Transactions') : 'Dashboard'));
    const trail = breadcrumbs ?? [{ label: pageTitle }];

    const openDashboardAction = (action: () => void) => {
        if (mobileOpen) {
            actionAfterSheetClose.current = action;
            setMobileOpen(false);
        } else {
            action();
        }
    };

    useEffect(() => {
        try { setCollapsed(localStorage.getItem(storageKey) === 'collapsed'); } catch { setCollapsed(false); }
    }, [storageKey]);

    useEffect(() => {
        if (!toastFlash) return;
        if (flash?.success) toast({ description: flash.success, variant: 'success' });
        if (flash?.error) toast({ description: flash.error, variant: 'destructive' });
    }, [flash, toast, toastFlash]);

    const toggleCollapsed = () => {
        const next = !collapsed;
        setCollapsed(next);
        try { localStorage.setItem(storageKey, next ? 'collapsed' : 'expanded'); } catch { /* Storage is optional. */ }
    };

    const navigation = (mobile: boolean) => {
        const items = [
            { name: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard },
            { name: 'Procurements', routeName: 'procurements.index', icon: FolderOpen },
            { name: 'Transactions', routeName: 'transactions.index', icon: FileText },
            ...(user.can_process_transactions ? [{ name: 'Pending receipts', routeName: 'transactions.pending', icon: Receipt }] : []),
        ];
        return <>
            <nav aria-label={mobile ? 'Mobile navigation' : 'Main navigation'}>
                {items.map(({ name, routeName, icon: Icon }) => {
                    const pendingAction = routeName === 'transactions.pending' ? onPendingReceipts : undefined;
                    const active = routeName === 'dashboard' ? route().current('dashboard*') : routeName === 'procurements.index' ? isProcurement : routeName === 'transactions.pending' ? route().current(routeName) : isTransaction && !route().current('transactions.pending');
                    const contents = <><Icon aria-hidden="true" /><span>{name}</span>{routeName === 'transactions.pending' && <span className="op-nav-count">{pendingReceiptsCount}</span>}</>;
                    return <Tooltip key={name}>
                        <TooltipTrigger asChild>
                            {pendingAction ? (
                                <Button variant="ghost" data-slot="button" className={`op-nav-item ${active ? 'is-active' : ''}`} aria-label={name} onClick={() => openDashboardAction(pendingAction)}>
                                    {contents}
                                </Button>
                            ) : (
                                <Button variant="ghost" data-slot="button" className={`op-nav-item ${active ? 'is-active' : ''}`} asChild>
                                    <Link href={route(routeName)} aria-label={name} aria-current={active ? 'page' : undefined} onClick={() => setMobileOpen(false)}>
                                        {contents}
                                    </Link>
                                </Button>
                            )}
                        </TooltipTrigger>
                        {collapsed && !mobile && <TooltipContent side="right">{name}</TooltipContent>}
                    </Tooltip>;
                })}
            </nav>
            {administrator && <div className="op-nav-admin">
                <span>Administration</span>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button data-slot="button" variant="ghost" className={currentAdminLink ? 'is-active' : undefined} aria-label="Workflows and directories" title={collapsed && !mobile ? 'Workflows and directories' : undefined}>
                            <SlidersHorizontal aria-hidden="true" /><span className="op-admin-label">Workflows & directories</span><ChevronRight aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="op-live op-live-overlay" side={mobile ? 'bottom' : 'right'} align="start">
                        <DropdownMenuLabel>Administration</DropdownMenuLabel>
                        {adminLinks.map(([label, routeName]) => <DropdownMenuItem key={routeName} asChild>
                            <Link href={route(routeName)} aria-current={currentAdminLink?.[1] === routeName ? 'page' : undefined} onClick={() => setMobileOpen(false)}>{label}</Link>
                        </DropdownMenuItem>)}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>}
        </>;
    };

    const accountFooter = <div className="op-sidebar-bottom">
        <div className="op-assigned-office" title={user.office?.name ?? 'No assigned office'}>
            <Building2 aria-hidden="true" /><span>{user.office?.abbreviation ?? 'No office'}<small>{user.office?.name ?? 'No assigned office'}</small></span>
        </div>
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button data-slot="button" variant="ghost" className="op-account" aria-label={`Account menu for ${user.name}`}>
                    <Avatar data-slot="avatar" className="h-[33px] w-[33px] shrink-0">
                        <AvatarFallback data-slot="avatar-fallback">{user.name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase()}</AvatarFallback>
                    </Avatar>
                    <span><strong>{user.name}</strong><small>{roleLabel}</small></span><ChevronDown aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="op-live op-live-overlay" side="top" align="start">
                <DropdownMenuLabel>{user.name}</DropdownMenuLabel>
                <DropdownMenuItem disabled>{roleLabel}{user.office ? ` · ${user.office.abbreviation}` : ''}</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild><Link href={route('profile.edit')} onClick={() => setMobileOpen(false)}>Settings</Link></DropdownMenuItem>
                <DropdownMenuItem asChild><Link href={route('logout')} method="post" as="button" className="w-full" onClick={() => setMobileOpen(false)}>Log out</Link></DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>;

    return <TooltipProvider>
        <div className={`op-root op-casebook op-without-controls op-live ${collapsed ? 'op-sidebar-collapsed' : ''} ${rootClassName}`}>
            <a className="op-skip" href={`#${mainId}`}>Skip to {pageTitle}</a>
            <div className="op-shell">
                <aside id="dashboard-sidebar" className="op-sidebar" aria-label="Workspace navigation">
                    <Link className="op-brand" href={route('dashboard')} aria-label="OPTS Dashboard">
                        <img src="/lgu-seal.png" alt="Municipality of Quezon seal" width="42" height="42" />
                        <span><strong>OPTS</strong><small>Quezon, Bukidnon</small></span>
                    </Link>
                    {navigation(false)}{accountFooter}
                </aside>
                <div className="op-workspace">
                    <header className="op-header">
                        <div className="op-mobile-navigation">
                            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                                <SheetTrigger asChild><Button data-slot="button" variant="ghost" size="icon" aria-label="Open navigation"><Menu aria-hidden="true" /></Button></SheetTrigger>
                                <SheetContent side="left" overlayClassName="op-modal-overlay" className="op-root op-live op-mobile-sheet overflow-y-auto" onCloseAutoFocus={(event) => {
                                    const action = actionAfterSheetClose.current;
                                    if (action) {
                                        event.preventDefault();
                                        actionAfterSheetClose.current = null;
                                        action();
                                    }
                                }}>
                                    <SheetHeader><SheetTitle>OPTS</SheetTitle><SheetDescription>Quezon, Bukidnon</SheetDescription></SheetHeader>
                                    {navigation(true)}{accountFooter}
                                </SheetContent>
                            </Sheet>
                        </div>
                        <div className="op-desktop-collapse"><Button data-slot="button" variant="ghost" size="icon" aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'} aria-expanded={!collapsed} aria-controls="dashboard-sidebar" onClick={toggleCollapsed}><PanelLeft aria-hidden="true" /></Button></div>
                        <nav className="op-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span>{trail.map((crumb, index) => <span className="op-breadcrumb-segment" key={`${crumb.label}-${index}`}><ChevronRight aria-hidden="true" />{crumb.href && index < trail.length - 1 ? <Link href={crumb.href}>{crumb.label}</Link> : <strong aria-current={index === trail.length - 1 ? 'page' : undefined}>{crumb.label}</strong>}</span>)}</nav>
                        <div className="op-header-actions"><NotificationBell onViewDocument={onViewDocument} /></div>
                    </header>
                    {children}
                </div>
            </div>
            <Toaster />
        </div>
    </TooltipProvider>;
}
