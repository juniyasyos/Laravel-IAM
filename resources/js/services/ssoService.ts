import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8010/api';

export const ssoService = {
    /**
     * Step 1: Get authorization code dari backend
     * Frontend call ini dengan access_token yang sudah ada di header
     */
    async getAdminAuthCode(): Promise<{ auth_code: string; redirect_url: string }> {
        const token = localStorage.getItem('access_token');
        console.log('SSO: Token retrieved:', token ? '✓ Token exists' : '✗ No token');

        if (!token) {
            throw new Error('No access token found');
        }

        console.log('SSO: Requesting auth code with token:', token.substring(0, 20) + '...');

        const response = await axios.post(
            `${API_BASE_URL}/sso/admin/auth-code`,
            {},
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
            }
        );

        console.log('SSO: Received auth code response:', response.status);
        return response.data;
    },

    /**
     * Step 2: Exchange authorization code untuk session token
     * Admin Panel call ini untuk mendapatkan session
     */
    async exchangeCodeForSession(code: string): Promise<{
        session_token: string;
        user: any;
        expires_in: number;
    }> {
        const response = await axios.post(`${API_BASE_URL}/sso/admin/exchange-code`, {
            code,
        });

        return response.data;
    },

    /**
     * Step 3: Verify session token validity
     */
    async verifySession(sessionToken: string): Promise<{ user: any; valid: boolean }> {
        const response = await axios.post(`${API_BASE_URL}/sso/admin/verify-session`, {
            session_token: sessionToken,
        });

        return response.data;
    },

    /**
     * Handle admin panel redirect dengan auth code
     * Frontend panggil ini ketika user klik Admin Panel button
     */
    async redirectToAdminPanel(): Promise<void> {
        try {
            console.log('SSO: Starting redirect to admin panel...');

            // Get auth code dari backend
            const { auth_code, redirect_url } = await this.getAdminAuthCode();
            console.log('SSO: Auth code received:', auth_code.substring(0, 10) + '...');

            // Redirect ke Admin Panel dengan code sebagai parameter
            // Admin Panel akan receive code ini dan exchange untuk session
            const adminPanelUrl = new URL(redirect_url);
            adminPanelUrl.searchParams.append('code', auth_code);
            adminPanelUrl.searchParams.append('state', this.generateState());

            console.log('SSO: Redirecting to:', adminPanelUrl.toString());
            window.open(adminPanelUrl.toString(), '_blank');
        } catch (error) {
            console.error('Failed to redirect to admin panel:', error);
            throw error;
        }
    },

    /**
     * Generate random state untuk security (CSRF protection)
     */
    generateState(): string {
        const state = Math.random().toString(36).substring(2, 15);
        sessionStorage.setItem('sso_state', state);
        return state;
    },

    /**
     * Verify state parameter (untuk security)
     */
    verifyState(state: string): boolean {
        const savedState = sessionStorage.getItem('sso_state');
        sessionStorage.removeItem('sso_state');
        return state === savedState;
    },
};
