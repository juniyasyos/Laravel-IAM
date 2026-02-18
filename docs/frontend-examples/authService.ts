// src/services/authService.ts
import api from './api';

export interface LoginRequest {
    email: string;
    password: string;
}

export interface RegisterRequest {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface AuthResponse {
    message: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    access_token: string;
    token_type: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface UserResponse {
    user: User;
}

class AuthService {
    async login(credentials: LoginRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/auth/login', credentials);
        if (response.data.access_token) {
            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('user', JSON.stringify(response.data.user));
        }
        return response.data;
    }

    async register(data: RegisterRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>('/auth/register', data);
        if (response.data.access_token) {
            localStorage.setItem('access_token', response.data.access_token);
            localStorage.setItem('user', JSON.stringify(response.data.user));
        }
        return response.data;
    }

    async logout(): Promise<void> {
        try {
            await api.post('/auth/logout');
        } finally {
            // clear local storage for this tab
            localStorage.removeItem('access_token');
            localStorage.removeItem('user');

            // notify other tabs (storage event + BroadcastChannel)
            try {
                localStorage.setItem('iam:logout', Date.now().toString());
            } catch (e) {
                // ignore (storage may be unavailable in some environments)
            }

            try {
                const bc = new BroadcastChannel('iam-auth');
                bc.postMessage('logout');
                bc.close();
            } catch (e) {
                // BroadcastChannel might not be supported — that's okay
            }
        }
    }

    async getCurrentUser(): Promise<UserResponse> {
        return api.get<UserResponse>('/auth/me').then(res => res.data);
    }

    async refreshToken(): Promise<{ access_token: string }> {
        return api.post('/auth/refresh').then(res => res.data);
    }

    getStoredUser(): User | null {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    }

    getStoredToken(): string | null {
        return localStorage.getItem('access_token');
    }

    isAuthenticated(): boolean {
        return !!this.getStoredToken();
    }

    clearAuth(): void {
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
    }
}

export default new AuthService();
