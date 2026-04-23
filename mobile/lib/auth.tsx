import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, PropsWithChildren, useContext, useEffect, useState } from 'react';

import { fetchCurrentUser, login, logout, register } from '@/lib/api';
import type { MobileUser } from '@/lib/types';

type LoginPayload = {
  email: string;
  password: string;
};

type RegisterPayload = {
  name: string;
  email: string;
  phone: string;
  birth_date: string;
  gender: string;
  address_line: string;
  city: string;
  province: string;
  postal_code: string;
  password: string;
  password_confirmation: string;
};

type AuthContextValue = {
  user: MobileUser | null;
  token: string | null;
  booting: boolean;
  signIn: (payload: LoginPayload) => Promise<void>;
  signUp: (payload: RegisterPayload) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

const tokenKey = 'mcd-mobile-token';
const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<MobileUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    let active = true;

    async function hydrate() {
      try {
        const savedToken = await AsyncStorage.getItem(tokenKey);

        if (!savedToken) {
          return;
        }

        const response = await fetchCurrentUser(savedToken);

        if (!active) {
          return;
        }

        setToken(savedToken);
        setUser(response.user);
      } catch {
        await AsyncStorage.removeItem(tokenKey);
      } finally {
        if (active) {
          setBooting(false);
        }
      }
    }

    void hydrate();

    return () => {
      active = false;
    };
  }, []);

  async function persistSession(nextToken: string, nextUser: MobileUser) {
    setToken(nextToken);
    setUser(nextUser);
    await AsyncStorage.setItem(tokenKey, nextToken);
  }

  async function signIn(payload: LoginPayload) {
    const response = await login(payload);
    await persistSession(response.token, response.user);
  }

  async function signUp(payload: RegisterPayload) {
    const response = await register(payload);
    await persistSession(response.token, response.user);
  }

  async function signOut() {
    if (token) {
      try {
        await logout(token);
      } catch {
        // Ignore network logout failures and clear the local token anyway.
      }
    }

    setUser(null);
    setToken(null);
    await AsyncStorage.removeItem(tokenKey);
  }

  async function refreshUser() {
    if (!token) {
      return;
    }

    const response = await fetchCurrentUser(token);
    setUser(response.user);
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        booting,
        signIn,
        signUp,
        signOut,
        refreshUser,
      }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider.');
  }

  return context;
}
