import type { Role, Office } from './models';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    office_id?: number;
    is_active: boolean;
    roles?: Role[];
    office?: Office;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
