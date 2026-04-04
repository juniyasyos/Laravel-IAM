export interface User {
    id: number;
    name: string;
    email?: string;
    nip?: string;
    role?: string;
    email_verified_at?: string;
    two_factor_secret?: string | null;
    two_factor_recovery_codes?: string | null;
    two_factor_confirmed_at?: string | null;
    active?: boolean;
    created_at: string;
    updated_at: string;
    applications?: UserApplication[];
    accessible_apps?: string[];
    access_profiles?: AccessProfile[];
    direct_roles?: DirectRole[];
}

export interface AuthResponse {
    user: User;
    access_token: string;
    token_type: string;
}

export interface LoginCredentials {
    nip: string;
    password: string;
}

export interface RegisterData {
    name: string;
    nip: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export interface Application {
    id: string;
    name: string;
    description?: string;
    status?: 'Siapro' | 'Beta' | 'Ready' | 'Offline' | 'Siap Diakses' | 'Dalam Pengembangan';
    url?: string;
    access?: string;
    notifications?: number;
    app_key?: string;
    app_url?: string;
    logo_url?: string;
    enabled?: boolean;
    roles?: Role[];
    redirect_uris?: string[];
    isOnline?: boolean;
}

export interface Role {
    id?: number;
    slug: string;
    name: string;
    is_system?: boolean;
    description?: string;
}

export interface UserApplication {
    app_key: string;
    name: string;
    description: string;
    enabled: boolean;
    roles: Role[];
}

export interface AccessibleApp {
    app_key: string;
    name?: string;
    description?: string;
    url?: string;
    status?: string;
    access?: string;
    notifications?: number;
}

export interface AccessProfile {
    id: number;
    slug: string;
    name: string;
    description: string;
    is_system: boolean;
    roles_count: number;
    roles: {
        app_key: string;
        role_slug: string;
        role_name: string;
    }[];
}

export interface DirectRole {
    app_key: string;
    role_id: number;
    role_slug: string;
    role_name: string;
    is_system: boolean;
}

export interface UserInfoResponse {
    sub: string;
    user: User;
    timestamp: string;
}