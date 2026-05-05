import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, PropsWithChildren, useContext, useEffect, useState } from 'react';

import { ApiError, fetchCurrentUser, fetchDashboard, fetchProfile, login, logout, register } from '@/lib/api';
import { removeCaches, writeCache } from '@/lib/cache';
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
const sessionKey = 'mcd-mobile-session';
const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<MobileUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    let active = true;

    async function hydrate() {
      try {
        const [savedToken, savedSession] = await Promise.all([
          AsyncStorage.getItem(tokenKey),
          AsyncStorage.getItem(sessionKey),
        ]);

        if (!savedToken) {
          setBooting(false);
          return;
        }

        if (savedSession) {
          try {
            const parsedSession = JSON.parse(savedSession) as { token: string; user: MobileUser | null };

            if (parsedSession.user && active) {
              setToken(parsedSession.token || savedToken);
              setUser(parsedSession.user);
              setBooting(false);
            }
          } catch {
            // Ignore corrupted cached sessions and continue with server hydration.
          }
        }

        const response = await fetchCurrentUser(savedToken);

        if (!active) {
          return;
        }

        setToken(savedToken);
        setUser(response.user);
        await AsyncStorage.setItem(sessionKey, JSON.stringify({ token: savedToken, user: response.user }));
      } catch (error) {
        if (!active) {
          return;
        }

        if (error instanceof ApiError && error.status === 401) {
          await AsyncStorage.removeItem(tokenKey);
          await AsyncStorage.removeItem(sessionKey);
          setToken(null);
          setUser(null);
        }
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

  async function warmSessionData(nextToken: string, nextUser: MobileUser) {
    try {
      const [dashboardResponse, profileResponse] = await Promise.all([
        fetchDashboard(nextToken),
        fetchProfile(nextToken),
      ]);

      await Promise.all([
        writeCache(`mobile-cache:dashboard:${nextUser.id}`, dashboardResponse),
        writeCache(`mobile-cache:profile:${nextUser.id}`, profileResponse.profile),
      ]);
    } catch {
      // Keep login fast and ignore prefetch failures.
    }
  }

  async function persistSession(nextToken: string, nextUser: MobileUser) {
    setToken(nextToken);
    setUser(nextUser);
    await Promise.all([
      AsyncStorage.setItem(tokenKey, nextToken),
      AsyncStorage.setItem(sessionKey, JSON.stringify({ token: nextToken, user: nextUser })),
    ]);
    void warmSessionData(nextToken, nextUser);
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
    const activeUserId = user?.id ?? null;

    if (token) {
      try {
        await logout(token);
      } catch {
        // Ignore network logout failures and clear the local token anyway.
      }
    }

    setUser(null);
    setToken(null);
    await Promise.all([
      AsyncStorage.removeItem(tokenKey),
      AsyncStorage.removeItem(sessionKey),
      removeCaches([
        'mobile-cache:home',
        'mobile-cache:booking-options',
        ...(activeUserId ? [`mobile-cache:dashboard:${activeUserId}`, `mobile-cache:profile:${activeUserId}`] : []),
      ]),
    ]);
  }

  async function refreshUser() {
    if (!token) {
      return;
    }

    const response = await fetchCurrentUser(token);
    setUser(response.user);
    await AsyncStorage.setItem(sessionKey, JSON.stringify({ token, user: response.user }));
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
