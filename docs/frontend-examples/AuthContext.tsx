// src/context/AuthContext.tsx
import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import authService, { User } from '../services/authService';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Load user from localStorage on mount + listen for cross-tab logout
  useEffect(() => {
    const storedToken = authService.getStoredToken();
    const storedUser = authService.getStoredUser();

    if (storedToken && storedUser) {
      setUser(storedUser);
    }

    setIsLoading(false);

    // handle logout notifications from other tabs
    const handleLogoutEvent = () => {
      authService.clearAuth();
      setUser(null);
    };

    const handleStorage = (e: StorageEvent) => {
      if (e.key === 'iam:logout' || (e.key === 'access_token' && e.newValue === null)) {
        handleLogoutEvent();
      }
    };

    window.addEventListener('storage', handleStorage);

    let bc: BroadcastChannel | null = null;
    try {
      bc = new BroadcastChannel('iam-auth');
      bc.onmessage = (ev) => {
        if (ev.data === 'logout') {
          handleLogoutEvent();
        }
      };
    } catch (err) {
      /* BroadcastChannel not supported */
    }

    return () => {
      window.removeEventListener('storage', handleStorage);
      if (bc) bc.close();
    };
  }, []);

  const login = async (email: string, password: string) => {
    setIsLoading(true);
    try {
      const response = await authService.login({ email, password });
      setUser(response.user);
    } catch (error) {
      authService.clearAuth();
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string
  ) => {
    setIsLoading(true);
    try {
      const response = await authService.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setUser(response.user);
    } catch (error) {
      authService.clearAuth();
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await authService.logout();
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  const refreshUser = async () => {
    try {
      const response = await authService.getCurrentUser();
      setUser(response.user);
    } catch (error) {
      authService.clearAuth();
      setUser(null);
      throw error;
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        isAuthenticated: !!user,
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
