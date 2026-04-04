import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8010/api';

export const ssoService = {
    /**
     * Redirect ke admin panel
     * Karena dalam satu Laravel, tidak perlu auth code exchange
     */
    async redirectToAdminPanel(): Promise<void> {
        try {
            console.log('SSO: Redirecting to admin panel...');
            window.open('/panel', '_blank');
        } catch (error) {
            console.error('Failed to redirect to admin panel:', error);
            throw error;
        }
    },
};
