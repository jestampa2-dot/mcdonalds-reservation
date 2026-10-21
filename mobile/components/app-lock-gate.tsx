import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as LocalAuthentication from 'expo-local-authentication';
import { PropsWithChildren, useCallback, useEffect, useRef, useState } from 'react';
import { AppState, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { CustomerButton, McLogo } from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import { useAuth } from '@/lib/auth';
import { useAppPreferences } from '@/lib/preferences';
import { useAppTheme } from '@/lib/theme';

export function AppLockGate({ children }: PropsWithChildren) {
  const { token, signOut } = useAuth();
  const { preferences, ready, lastBiometricVerificationAt } = useAppPreferences();
  const { colors } = useAppTheme();
  const [locked, setLocked] = useState(false);
  const [authenticating, setAuthenticating] = useState(false);
  const authenticatingRef = useRef(false);
  const appStateRef = useRef(AppState.currentState);

  const unlock = useCallback(async () => {
    if (Platform.OS === 'web' || authenticatingRef.current) {
      return;
    }

    authenticatingRef.current = true;
    setAuthenticating(true);

    try {
      const result = await LocalAuthentication.authenticateAsync({
        promptMessage: 'Unlock McDonald\'s Reservations',
        promptSubtitle: 'Use your fingerprint or face recognition',
        cancelLabel: 'Cancel',
      });

      if (result.success) {
        setLocked(false);
      }
    } finally {
      authenticatingRef.current = false;
      setAuthenticating(false);
    }
  }, []);

  useEffect(() => {
    if (!ready || !token || !preferences.appLockEnabled || Platform.OS === 'web') {
      setLocked(false);
      return;
    }

    if (Date.now() - lastBiometricVerificationAt < 10_000) {
      setLocked(false);
      return;
    }

    setLocked(true);
    void unlock();
  }, [lastBiometricVerificationAt, preferences.appLockEnabled, ready, token, unlock]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      const wasInactive = appStateRef.current === 'background' || appStateRef.current === 'inactive';
      appStateRef.current = nextState;

      if (!token || !preferences.appLockEnabled || Platform.OS === 'web') {
        return;
      }

      if (nextState === 'background' || nextState === 'inactive') {
        setLocked(true);
      } else if (nextState === 'active' && wasInactive) {
        void unlock();
      }
    });

    return () => subscription.remove();
  }, [preferences.appLockEnabled, token, unlock]);

  if (!locked) {
    return children;
  }

  return (
    <SafeAreaView style={[styles.page, { backgroundColor: colors.background }]}>
      <View style={styles.brandBand}>
        <McLogo size={72} />
      </View>
      <View style={[styles.panel, { backgroundColor: colors.surface }]}>
        <View style={[styles.iconWrap, { backgroundColor: colors.surfaceMuted }]}>
          <MaterialCommunityIcons name="fingerprint" size={42} color={colors.brandText} />
        </View>
        <Text style={[styles.title, { color: colors.text }]}>App locked</Text>
        <Text style={[styles.body, { color: colors.textMuted }]}>Verify your identity to open your reservations and account details.</Text>
        <CustomerButton label="Unlock app" onPress={() => void unlock()} loading={authenticating} icon="fingerprint" />
        <CustomerButton label="Log out instead" onPress={() => void signOut()} tone="ghost" />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  page: {
    flex: 1,
  },
  brandBand: {
    minHeight: 210,
    backgroundColor: palette.brandYellow,
    alignItems: 'center',
    justifyContent: 'center',
  },
  panel: {
    flex: 1,
    paddingHorizontal: 28,
    paddingTop: 44,
    gap: 18,
    alignItems: 'center',
  },
  iconWrap: {
    width: 84,
    height: 84,
    borderRadius: 42,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: '900',
  },
  body: {
    maxWidth: 320,
    textAlign: 'center',
    fontSize: 15,
    lineHeight: 22,
    marginBottom: 6,
  },
});
