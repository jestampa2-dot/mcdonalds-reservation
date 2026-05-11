import { Redirect, router } from 'expo-router';
import { startTransition, useState } from 'react';
import { Alert, Keyboard, StyleSheet, Text, View } from 'react-native';

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
import { palette } from '@/constants/palette';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export default function LoginScreen() {
  const { signIn, user, booting } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

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

const styles = StyleSheet.create({
  pageContent: {
    paddingHorizontal: 18,
    gap: 16,
  },
  content: {
    gap: 16,
  },
  heroTitle: {
    color: palette.ink,
    fontSize: 28,
    fontWeight: '900',
    lineHeight: 32,
  },
  heroText: {
    color: '#665446',
    lineHeight: 20,
    fontSize: 14,
  },
  stack: {
    gap: 10,
  },
  tip: {
    color: '#6A5647',
    fontSize: 13,
    lineHeight: 20,
  },
  code: {
    color: palette.brandRed,
    fontWeight: '800',
  },
});
