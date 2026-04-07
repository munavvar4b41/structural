export type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    can_manage_company_settings: boolean;
    can_manage_users: boolean;
    can_manage_projects: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
