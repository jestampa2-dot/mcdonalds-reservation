import { Redirect, router } from 'expo-router';
import { startTransition, useMemo, useState } from 'react';
import { Alert, Keyboard, StyleSheet, Text, View } from 'react-native';
import { GoogleSigninButton } from '@react-native-google-signin/google-signin';

import {
  CustomerButton,
  CustomerCard,
  CustomerField,
  CustomerHeader,
  CustomerPage,
  McLogo,
  SectionEyebrow,
  SectionTitle,
} from '@/components/customer-ui';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { chooseGoogleAccount, googleSignInErrorMessage, isGoogleSignInConfigured } from '@/lib/google-auth';
import { AppThemeColors, useAppTheme } from '@/lib/theme';

export default function LoginScreen() {
  const { colors } = useAppTheme();
  const styles = useMemo(() => createStyles(colors), [colors]);
  const { signIn, signInWithGoogle, user, booting } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [googleSubmitting, setGoogleSubmitting] = useState(false);
  const googleEnabled = isGoogleSignInConfigured();

  async function handleSubmit() {
    const normalizedEmail = email.trim().toLowerCase();

    if (!normalizedEmail || !password) {
      Alert.alert('Missing details', 'Enter your email and password before signing in.');
      return;
    }

    try {
      Keyboard.dismiss();
      setSubmitting(true);
      await signIn({ email: normalizedEmail, password });
      startTransition(() => {
        router.replace('/(tabs)/dashboard');
      });
    } catch (error) {
      const message = error instanceof ApiError ? error.errors?.email?.[0] ?? error.message : 'Unable to sign in right now.';
      Alert.alert('Sign in failed', message);
    } finally {
      setSubmitting(false);
    }
  }

  async function handleGoogleSignIn() {
    try {
      Keyboard.dismiss();
      setGoogleSubmitting(true);
      const idToken = await chooseGoogleAccount();

      if (!idToken) {
        return;
      }

      const needsProfileCompletion = await signInWithGoogle(idToken);
      startTransition(() => {
        router.replace(needsProfileCompletion ? '/(tabs)/account' : '/(tabs)/dashboard');
      });
    } catch (error) {
      const message = error instanceof ApiError
        ? error.errors?.google?.[0] ?? error.message
        : googleSignInErrorMessage(error);

      if (message) {
        Alert.alert('Google sign-in failed', message);
      }
    } finally {
      setGoogleSubmitting(false);
    }
  }

  if (!booting && user) {
    return <Redirect href="/(tabs)/dashboard" />;
  }

  return (
    <CustomerPage contentContainerStyle={styles.pageContent}>
      <CustomerHeader title="Welcome back" subtitle="Sign in to submit reservations and track admin approval updates." rightSlot={<McLogo />} />
      <View style={styles.content}>
        <CustomerCard tone="yellow">
          <SectionEyebrow>Customer access</SectionEyebrow>
          <Text style={styles.heroTitle}>Sign in to your mobile booking account</Text>
          <Text style={styles.heroText}>Your reservations, payment proof, and dashboard status stay connected to the same Laravel admin workflow.</Text>
        </CustomerCard>

        <CustomerCard>
          <SectionTitle>Sign in</SectionTitle>
          {googleEnabled ? (
            <View style={styles.googleArea}>
              <GoogleSigninButton
                size={GoogleSigninButton.Size.Wide}
                onPress={() => void handleGoogleSignIn()}
                disabled={submitting || googleSubmitting}
              />
              <Text style={styles.divider}>or use your email and password</Text>
            </View>
          ) : null}
          <CustomerField
            label="Email address"
            value={email}
            onChangeText={setEmail}
            placeholder="name@example.com"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="email"
            textContentType="emailAddress"
            autoFocus
            returnKeyType="next"
          />
          <CustomerField
            label="Password"
            value={password}
            onChangeText={setPassword}
            placeholder="Your password"
            secureTextEntry
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="password"
            textContentType="password"
            returnKeyType="done"
            onSubmitEditing={() => void handleSubmit()}
          />
          <View style={styles.stack}>
            <CustomerButton label="Sign in" onPress={handleSubmit} loading={submitting} disabled={!email.trim() || !password} />
            <CustomerButton label="Create a new account" onPress={() => router.push('/register')} tone="secondary" />
            <CustomerButton label="Back to home" onPress={() => router.replace('/(tabs)')} tone="ghost" />
          </View>
        </CustomerCard>

        <Text style={styles.tip}>
          If you test on a real phone, set <Text style={styles.code}>EXPO_PUBLIC_API_BASE_URL</Text> to your computer&apos;s LAN address instead of <Text style={styles.code}>127.0.0.1</Text>.
        </Text>
      </View>
    </CustomerPage>
  );
}

const createStyles = (colors: AppThemeColors) => StyleSheet.create({
  pageContent: {
    paddingHorizontal: 18,
    gap: 16,
  },
  content: {
    gap: 16,
  },
  heroTitle: {
    color: colors.text,
    fontSize: 28,
    fontWeight: '900',
    lineHeight: 32,
  },
  heroText: {
    color: colors.textMuted,
    lineHeight: 20,
    fontSize: 14,
  },
  stack: {
    gap: 10,
  },
  googleArea: {
    alignItems: 'center',
    gap: 12,
    marginBottom: 4,
  },
  divider: {
    color: colors.textSubtle,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
  },
  tip: {
    color: colors.textMuted,
    fontSize: 13,
    lineHeight: 20,
  },
  code: {
    color: colors.brandText,
    fontWeight: '800',
  },
});
