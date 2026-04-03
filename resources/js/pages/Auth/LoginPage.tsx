import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import Login from '../../components/Login';
import { useAuth } from '../../hooks/useAuth';

export default function LoginPage() {
  const { isAuthenticated, isLoading, error } = useAuth();
  const page = usePage() as any;
  const inertiaAuth = (page.props.auth ?? {}) as any;
  const validationError = page.props?.errors?.nip || page.props?.errors?.password || null;
  const devAutofill = page.props?.devAutofill ?? null;

  useEffect(() => {
    if (inertiaAuth?.user || isAuthenticated) {
      router.visit('/dashboard');
    }
  }, [isAuthenticated, inertiaAuth?.user]);

  const handleLogin = async (nip: string, password: string) => {
    try {
      await router.post('/login', { nip, password });
    } catch (err) {
      // Error is handled in the store
    }
  };

  return (
    <Login
      onLogin={handleLogin}
      isLoading={isLoading}
      error={validationError || error}
      devAutofill={devAutofill}
    />
  );
}