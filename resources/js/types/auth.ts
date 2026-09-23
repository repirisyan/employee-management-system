export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    two_factor_enabled?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    employee?: {
        id: number;
        nip: string;
        avatar?: string | null;
        avatar_url?: string | null;
        role?: {
            id: number;
            name: string;
            slug: string;
        };
        department?: {
            id: number;
            name: string;
            code?: string | null;
        };
    };
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
