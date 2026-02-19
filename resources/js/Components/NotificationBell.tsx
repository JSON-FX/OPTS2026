import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Bell, AlertTriangle, CheckCircle, Clock, ExternalLink, Inbox } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import type { AppNotification, PageProps } from '@/types';

function getNotificationIcon(type: string) {
    switch (type) {
        case 'out_of_workflow':
            return <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0" />;
        case 'received':
            return <Inbox className="h-4 w-4 text-blue-500 shrink-0" />;
        case 'overdue':
            return <Clock className="h-4 w-4 text-red-500 shrink-0" />;
        case 'completed':
            return <CheckCircle className="h-4 w-4 text-green-500 shrink-0" />;
        default:
            return <Bell className="h-4 w-4 text-gray-400 shrink-0" />;
    }
}

// Map Laravel FQCN to custom notification type (broadcast overrides toArray type)
const NOTIFICATION_TYPE_MAP: Record<string, string> = {
    'App\\Notifications\\TransactionReceivedNotification': 'received',
    'App\\Notifications\\TransactionCompletedNotification': 'completed',
    'App\\Notifications\\TransactionOverdueNotification': 'overdue',
    'App\\Notifications\\OutOfWorkflowNotification': 'out_of_workflow',
};

function resolveNotificationType(broadcastType: string): string {
    return NOTIFICATION_TYPE_MAP[broadcastType] ?? broadcastType;
}

export function NotificationBell() {
    const { notifications, auth } = usePage<PageProps>().props;
    const [isOpen, setIsOpen] = useState(false);
    const [realtimeNotifications, setRealtimeNotifications] = useState<AppNotification[]>([]);
    const [realtimeUnreadDelta, setRealtimeUnreadDelta] = useState(0);

    // Merge Inertia shared props with real-time arrivals
    const baseUnreadCount = notifications?.unread_count ?? 0;
    const baseRecent = notifications?.recent ?? [];
    const unreadCount = baseUnreadCount + realtimeUnreadDelta;

    // Prepend real-time notifications, deduplicating by id
    const baseIds = new Set(baseRecent.map(n => n.id));
    const uniqueRealtime = realtimeNotifications.filter(n => !baseIds.has(n.id));
    const recent = [...uniqueRealtime, ...baseRecent].slice(0, 10);

    // Reset real-time state when Inertia shared props update (page navigation)
    useEffect(() => {
        setRealtimeNotifications([]);
        setRealtimeUnreadDelta(0);
    }, [notifications]);

    // Subscribe to private notification channel
    useEffect(() => {
        if (!window.Echo || !auth.user) return;

        try {
            const channel = window.Echo.private(`App.Models.User.${auth.user.id}`);

            channel.notification((notification: Record<string, unknown>) => {
                const appNotification: AppNotification = {
                    id: notification.id as string,
                    type: resolveNotificationType((notification.type as string) ?? 'unknown'),
                    message: (notification.message as string) ?? '',
                    read_at: null,
                    created_at: new Date().toISOString(),
                    data: notification,
                };

                setRealtimeNotifications(prev => [appNotification, ...prev]);
                setRealtimeUnreadDelta(prev => prev + 1);
            });

            return () => {
                channel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated');
            };
        } catch {
            // Graceful degradation: if Echo is misconfigured, fall back to Inertia props
        }
    }, [auth.user?.id]);

    const handleMarkAsRead = (id: string) => {
        router.post(route('notifications.markAsRead', id), {}, {
            preserveScroll: true,
        });
    };

    const handleMarkAllAsRead = () => {
        router.post(route('notifications.markAllAsRead'), {}, {
            preserveScroll: true,
        });
    };

    const handleNotificationClick = (notification: AppNotification) => {
        if (!notification.read_at) {
            handleMarkAsRead(notification.id);
        }
        setIsOpen(false);
    };

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80 p-0" align="end">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h3 className="font-semibold text-sm">Notifications</h3>
                    {unreadCount > 0 && (
                        <button
                            onClick={handleMarkAllAsRead}
                            className="text-xs text-blue-600 hover:text-blue-800"
                        >
                            Mark all as read
                        </button>
                    )}
                </div>

                <div className="max-h-80 overflow-y-auto">
                    {recent.length === 0 ? (
                        <div className="py-8 text-center text-sm text-gray-500">
                            No notifications
                        </div>
                    ) : (
                        recent.map((notification) => (
                            <button
                                key={notification.id}
                                onClick={() => handleNotificationClick(notification)}
                                className={`flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors ${
                                    !notification.read_at ? 'bg-blue-50/50' : ''
                                }`}
                            >
                                <div className="mt-0.5">
                                    {getNotificationIcon(notification.type)}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className={`text-sm leading-snug ${!notification.read_at ? 'font-medium' : 'text-gray-600'}`}>
                                        {notification.message}
                                    </p>
                                    <p className="text-xs text-gray-400 mt-1">
                                        {notification.created_at}
                                    </p>
                                </div>
                                {!notification.read_at && (
                                    <span className="mt-1.5 h-2 w-2 rounded-full bg-blue-500 shrink-0" />
                                )}
                            </button>
                        ))
                    )}
                </div>

                <div className="border-t px-4 py-2">
                    <a
                        href={route('notifications.index')}
                        onClick={(e) => {
                            e.preventDefault();
                            setIsOpen(false);
                            router.get(route('notifications.index'));
                        }}
                        className="flex items-center justify-center gap-1 text-xs text-blue-600 hover:text-blue-800"
                    >
                        View all notifications
                        <ExternalLink className="h-3 w-3" />
                    </a>
                </div>
            </PopoverContent>
        </Popover>
    );
}
