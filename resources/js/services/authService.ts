import api from './api';
import type { AuthResponse, LoginCredentials, RegisterData, User } from '../types';

export const authService = {
    async login(credentials: LoginCredentials): Promise<AuthResponse> {
        const response = await api.post('/api/auth/login', credentials);
        return response.data;
    },

    async register(data: RegisterData): Promise<AuthResponse> {
        const response = await api.post('/api/auth/register', data);
        return response.data;
    },

    async getCurrentUser(): Promise<User> {
        const response = await api.get('/api/users/me');
        return response.data.user;
    },

    async logout(): Promise<void> {
        await api.post('/api/auth/logout');
    },

    async refreshToken(): Promise<AuthResponse> {
        const response = await api.post('/api/auth/refresh');
        return response.data;
    },

    /**
     * Create Laravel session from Passport token.
     * This allows accessing backend panel routes that require session authentication.
     */
    async createSessionFromToken(accessToken: string): Promise<void> {
        await api.post('/api/auth/session-from-token', { access_token: accessToken });
    },
};