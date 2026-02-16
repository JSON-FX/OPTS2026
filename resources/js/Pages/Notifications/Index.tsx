import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Bell, Check, CheckCheck, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

interface NotificationItem {
    id: string;
    type: string;
    message: string;
    read_at: string | null;
    created_at: string;
    created_at_raw: string;
    data: Record<string, unknown>;
}

interface PaginatedNotifications {
    data: NotificationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
    type: string;
    status: string;
}

interface Props {
    notifications: PaginatedNotifications;
    filters: Filters;
}

function getNotificationIcon(type: string) {
    switch (type) {
        case 'out_of_workflow':
            return <AlertTriangle className="h-5 w-5 text-amber-500" />;
        default:
            return <Bell className="h-5 w-5 text-gray-400" />;
    }
}

export default function Index({ notifications, filters }: Props) {
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

    const handleFilterChange = (key: string, value: string) => {
        router.get(route('notifications.index'), {
            ...filters,
            [key]: value,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

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

    const handleDelete = (id: string) => {
        router.delete(route('notifications.destroy', id), {
            preserveScroll: true,
        });
        setSelectedIds((prev) => {
            const next = new Set(prev);
            next.delete(id);
            return next;
        });
    };

    const toggleSelect = (id: string) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleBulkMarkAsRead = () => {
        selectedIds.forEach((id) => {
            router.post(route('notifications.markAsRead', id), {}, {
                preserveScroll: true,
            });
        });
        setSelectedIds(new Set());
    };

    const handleBulkDelete = () => {
        selectedIds.forEach((id) => {
            router.delete(route('notifications.destroy', id), {
                preserveScroll: true,
            });
        });
        setSelectedIds(new Set());
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Notifications
                </h2>
            }
        >
            <Head title="Notifications" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                    {/* Filters and Actions */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-4 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Select
                                    value={filters.type}
                                    onValueChange={(v) => handleFilterChange('type', v)}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Filter by type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="out_of_workflow">Out of Workflow</SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={filters.status}
                                    onValueChange={(v) => handleFilterChange('status', v)}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Filter by status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="unread">Unread</SelectItem>
                                        <SelectItem value="read">Read</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2">
                                {selectedIds.size > 0 && (
                                    <>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleBulkMarkAsRead}
                                        >
                                            <Check className="mr-1 h-4 w-4" />
                                            Mark Selected Read ({selectedIds.size})
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={handleBulkDelete}
                                        >
                                            <Trash2 className="mr-1 h-4 w-4" />
                                            Delete Selected ({selectedIds.size})
                                        </Button>
                                    </>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleMarkAllAsRead}
                                >
                                    <CheckCheck className="mr-1 h-4 w-4" />
                                    Mark All Read
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Notification List */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        {notifications.data.length === 0 ? (
                            <div className="py-16 text-center text-gray-500">
                                <Bell className="mx-auto h-12 w-12 text-gray-300 mb-4" />
                                <p className="text-lg font-medium">No notifications</p>
                                <p className="text-sm mt-1">You're all caught up.</p>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {notifications.data.map((notification) => (
                                    <div
                                        key={notification.id}
                                        className={`flex items-start gap-4 px-6 py-4 ${
                                            !notification.read_at ? 'bg-blue-50/50' : ''
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedIds.has(notification.id)}
                                            onChange={() => toggleSelect(notification.id)}
                                            className="mt-1 rounded border-gray-300"
                                        />
                                        <div className="mt-0.5">
                                            {getNotificationIcon(notification.type)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className={`text-sm leading-snug ${
                                                !notification.read_at ? 'font-medium text-gray-900' : 'text-gray-600'
                                            }`}>
                                                {notification.message}
                                            </p>
                                            <div className="flex items-center gap-2 mt-1">
                                                <span className="text-xs text-gray-400">
                                                    {notification.created_at}
                                                </span>
                                                {notification.type === 'out_of_workflow' && (
                                                    <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                        Out of Workflow
                                                    </span>
                                                )}
                                                {!notification.read_at && (
                                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                        Unread
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {!notification.read_at && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => handleMarkAsRead(notification.id)}
                                                    title="Mark as read"
                                                >
                                                    <Check className="h-4 w-4" />
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8 text-gray-400 hover:text-red-500"
                                                onClick={() => handleDelete(notification.id)}
                                                title="Delete"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Pagination */}
                    {notifications.last_page > 1 && (
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                Showing {notifications.from} to {notifications.to} of {notifications.total} notifications
                            </p>
                            <div className="flex items-center gap-1">
                                {notifications.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                router.get(link.url, {}, { preserveState: true });
                                            }
                                        }}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
