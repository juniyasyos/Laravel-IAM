import { useState, useEffect } from 'react';
import LoginDefaultView from './Login/LoginDefaultView';

interface LoginProps {
  onLogin: (nip: string, password: string) => void;
  isLoading?: boolean;
  error?: string | null;
  devAutofill?: {
    nip?: string;
    password?: string;
  } | null;
}

export default function Login({ onLogin, isLoading = false, error, devAutofill = null }: LoginProps) {
  const [nip, setNip] = useState('');
  const [password, setPassword] = useState('');
  const [focusedInput, setFocusedInput] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);
  const [showError, setShowError] = useState(false);

  // Auto-fill untuk development mode
  useEffect(() => {
    if (devAutofill?.nip && devAutofill?.password) {
      setNip(devAutofill.nip);
      setPassword(devAutofill.password);
      return;
    }

    const isDev = import.meta.env.VITE_APP_ENV === 'dev';
    if (isDev) {
      const devNip = import.meta.env.VITE_DEV_NIP || '';
      const devPassword = import.meta.env.VITE_DEV_PASSWORD || '';
      setNip(devNip);
      setPassword(devPassword);
    }
  }, [devAutofill]);

  useEffect(() => {
    if (error) {
      setShowError(true);

      const timer = setTimeout(() => {
        setShowError(false);
      }, 4000);

      return () => clearTimeout(timer);
    }
  }, [error]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (nip && password && !isLoading) {
      onLogin(nip, password);
    }
  };

  return (
    <LoginDefaultView
      nip={nip}
      setNip={setNip}
      password={password}
      setPassword={setPassword}
      focusedInput={focusedInput}
      setFocusedInput={setFocusedInput}
      showPassword={showPassword}
      setShowPassword={setShowPassword}
      showError={showError}
      onCloseError={() => setShowError(false)}
      handleSubmit={handleSubmit}
      isLoading={Boolean(isLoading)}
      error={error}
    />
  );
}