export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'professional' | 'guardian';
    email_verified_at?: string;
    mfa_enabled?: boolean;
}

export interface ChildAuthSummary {
    id: number;
    first_name: string;
    preferred_name: string | null;
    visual_experience: '3-6' | '7-13' | '14-18';
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        child: ChildAuthSummary | null;
    };
    flash: {
        status: string | null;
        newDeviceCode?: string | null;
        newDevicePin?: string | null;
    };
};
