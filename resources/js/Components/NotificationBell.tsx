import { useState, useEffect, useRef } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, ExternalLink } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import type { AppNotification, PageProps } from '@/types';

export function NotificationBell({ onViewDocument }: { onViewDocument?: (transactionId: number) => void } = {}) {
    const { notifications, auth } = usePage<PageProps>().props;
    const [isOpen, setIsOpen] = useState(false);
    const [pendingRead, setPendingRead] = useState<string | null>(null);
    const actionAfterClose = useRef<(() => void) | null>(null);
    const popoverContent = useRef<HTMLDivElement | null>(null);
    const unreadCount = notifications?.unread_count ?? 0;
    const recent = notifications?.recent ?? [];

    // Subscribe to private notification channel
    useEffect(() => {
        if (!window.Echo || !auth.user) return;

        try {
            const channel = window.Echo.private(`App.Models.User.${auth.user.id}`);

            let refreshTimer: ReturnType<typeof setTimeout> | undefined;
            channel.notification(() => {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(() => {
                    router.reload({ only: ['notifications', 'auth', 'pendingReceiptsCount', 'pendingDocuments'] });
                }, 200);
            });

            return () => {
                clearTimeout(refreshTimer);
                channel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
            };
        } catch {
            // Graceful degradation: if Echo is misconfigured, fall back to Inertia props
        }
    }, [auth.user?.id]);

    const handleMarkAllAsRead = () => {
        setPendingRead('all');
        router.post(route('notifications.markAllAsRead'), {}, {
            preserveScroll: true,
            onFinish: () => setPendingRead(null),
        });
    };

    const handleNotificationClick = (notification: AppNotification) => {
        // Only follow the server's known document paths on this application's origin.
        let destination = route('transactions.index', {
            reference_number: typeof notification.data.reference_number === 'string' ? notification.data.reference_number : undefined,
            category: ['PR', 'PO', 'VCH'].includes(String(notification.data.category)) ? String(notification.data.category) : undefined,
        });
        let documentAvailable = false;
        if (typeof notification.data.document_url === 'string') {
            try {
                const url = new URL(notification.data.document_url, window.location.origin);
                if (url.origin === window.location.origin && /^\/(purchase-requests|purchase-orders|vouchers)\/\d+\/?$/.test(url.pathname)) {
                    destination = url.pathname;
                    documentAvailable = true;
                }
            } catch {
                // Missing records still have a searchable transaction reference.
            }
        }
        const navigate = () => {
            const transactionId = notification.data.transaction_id;
            if (onViewDocument && documentAvailable && typeof transactionId === 'number' && Number.isSafeInteger(transactionId) && transactionId > 0) {
                if (popoverContent.current) {
                    actionAfterClose.current = () => onViewDocument(transactionId);
                    setIsOpen(false);
                } else {
                    // Escape or an outside click may have closed the menu during the read request.
                    onViewDocument(transactionId);
                }
            } else {
                setIsOpen(false);
                router.visit(destination);
            }
        };
        if (notification.read_at) {
            navigate();
            return;
        }
        setPendingRead(notification.id);
        router.post(route('notifications.markAsRead', notification.id), {}, {
            preserveScroll: true,
            onSuccess: navigate,
            onFinish: () => setPendingRead(null),
        });
    };

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen} modal={false}>
            <PopoverTrigger asChild>
                <Button data-slot="button" data-notification-trigger variant="ghost" size="icon" className="op-notification-trigger relative" aria-label={`Notifications, ${unreadCount} unread`}>
                    <Bell className="h-5 w-5" aria-hidden="true" />
                    {unreadCount > 0 && (
                        <span aria-hidden="true" className="op-notification-count absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent ref={popoverContent} className="op-live op-live-overlay op-notification-dropdown w-[390px] max-w-[calc(100vw-24px)] p-0" align="end" sideOffset={12} aria-label="Notifications" onCloseAutoFocus={(event) => {
                const action = actionAfterClose.current;
                if (action) {
                    event.preventDefault();
                    actionAfterClose.current = null;
                    action();
                }
            }}>
                <div className="op-notification-heading flex items-center justify-between border-b px-4 py-3">
                    <div><h2 className="font-semibold text-sm">Notifications</h2><p className="text-xs text-muted-foreground">{unreadCount === 0 ? "You're up to date" : `${unreadCount} unread`}</p></div>
                    <Button variant="ghost" size="sm" onClick={handleMarkAllAsRead} disabled={unreadCount === 0 || pendingRead !== null}>
                        <CheckCheck aria-hidden="true" />Mark all read
                    </Button>
                </div>
                {recent.length === 0 ? (
                    <div className="op-empty px-6 py-8 text-center"><Bell className="mx-auto mb-3 h-7 w-7 text-muted-foreground" aria-hidden="true" /><h3 className="font-semibold">No notifications yet</h3><p className="mt-2 text-sm text-muted-foreground">Document updates will appear here.</p></div>
                ) : (
                    <ul className="op-notification-list m-0 max-h-80 list-none overflow-y-auto p-0" aria-busy={pendingRead !== null}>
                        {recent.map((notification) => {
                            const actor = [notification.data.received_by_name, notification.data.completed_by_name, notification.data.endorsed_by_name].find((name): name is string => typeof name === 'string' && name.length > 0);
                            return <li key={notification.id}><button
                                onClick={() => handleNotificationClick(notification)}
                                disabled={pendingRead !== null}
                                className={`flex w-full items-start justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-accent disabled:opacity-50 ${notification.read_at ? 'is-read' : 'is-unread bg-accent'}`}
                            >
                                <span className="min-w-0 flex-1">
                                    <strong className="block text-sm font-semibold leading-snug">{notification.message}</strong>
                                    {typeof notification.data.reference_number === 'string' && <small className="mt-1 block text-xs text-muted-foreground">{notification.data.reference_number}</small>}
                                    <small className="mt-1 block text-xs text-muted-foreground">{actor ? `${actor} · ` : ''}{notification.created_at}</small>
                                </span>
                                <span className="op-notification-read-state shrink-0 text-xs text-muted-foreground">{notification.read_at ? 'Read' : 'New'}</span>
                            </button></li>;
                        })}
                    </ul>
                )}
                {!onViewDocument && <div className="border-t px-4 py-2">
                    <Link href={route('notifications.index')} onClick={() => setIsOpen(false)} className="flex min-h-11 items-center justify-center gap-1 text-xs text-primary hover:underline">
                        View all notifications<ExternalLink className="h-3 w-3" aria-hidden="true" />
                    </Link>
                </div>}
            </PopoverContent>
        </Popover>
    );
}
