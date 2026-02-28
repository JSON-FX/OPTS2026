import type { Role, Office } from './models';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    office_id?: number;
    is_active: boolean;
    sso_uuid?: string;
    sso_position?: string;
    last_sso_login_at?: string;
    roles?: Role[];
    office?: Office;
}

export interface AppNotification {
    id: string;
    type: string;
    message: string;
    read_at: string | null;
    created_at: string;
    data: Record<string, unknown>;
}

export interface NotificationsData {
    unread_count: number;
    recent: AppNotification[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
    };
    pendingReceiptsCount?: number;
    notifications?: NotificationsData;
};
