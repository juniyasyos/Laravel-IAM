import { usePage, router } from '@inertiajs/react';
import { useAuthStore } from '../context/AuthContext';
import { useCallback, useMemo } from 'react';

export const useAuth = () => {
    const page = usePage();
    const store = useAuthStore();

    // Get auth from Inertia props if available, fallback to Zustand store
    const inertiaAuth = (page.props.auth ?? {}) as any;
    const user = inertiaAuth?.user || store.user;
    const isAuthenticated = useMemo(() => !!user, [user]);

    const login = useCallback(
        async (email: string, password: string) => {
            try {
                return await store.login({ email, password });
            } catch (error) {
                throw error;
            }
        },
        [store],
    );

    const logout = useCallback(async () => {
        try {
            await store.logout();
            await router.post('/logout');
        } catch (error) {
            console.error('Logout error:', error);
            // Force redirect to login page even if error occurred
            window.location.href = '/';
        }
    }, [store]);

    const register = useCallback(
        async (data: any) => {
            try {
                return await store.register(data);
            } catch (error) {
                throw error;
            }
        },
        [store],
    );

    return {
        user,
        isAuthenticated,
        isLoading: store.isLoading,
        error: store.error,
        login,
        logout,
        register,
        checkAuth: store.checkAuth,
        clearError: store.clearError,
    };
};