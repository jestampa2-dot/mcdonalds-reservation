import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import 'react-native-reanimated';

import { AppLockGate } from '@/components/app-lock-gate';
import { AuthProvider } from '@/lib/auth';
import { AppPreferencesProvider } from '@/lib/preferences';
import { ThemeProvider, useAppTheme } from '@/lib/theme';

export const unstable_settings = {
  anchor: '(tabs)',
};

function AppNavigator() {
  const { colors, isDark } = useAppTheme();

  return (
    <AuthProvider>
      <AppLockGate>
        <Stack screenOptions={{ contentStyle: { backgroundColor: colors.background } }}>
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="login" options={{ headerShown: false }} />
          <Stack.Screen name="register" options={{ headerShown: false }} />
        </Stack>
      </AppLockGate>
      <StatusBar style={isDark ? 'light' : 'dark'} />
    </AuthProvider>
  );
}

export default function RootLayout() {
  return (
    <ThemeProvider>
      <AppPreferencesProvider>
        <AppNavigator />
      </AppPreferencesProvider>
    </ThemeProvider>
  );
}
