import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export default function LoginScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    try {
      setSubmitting(true);
      await signIn({ email, password });
      router.replace('/(tabs)/dashboard');
    } catch (error) {
      const message = error instanceof ApiError ? error.errors?.email?.[0] ?? error.message : 'Unable to sign in right now.';
      Alert.alert('Sign in failed', message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppScreen
      eyebrow="Customer access"
      title="Welcome back"
      subtitle="Sign in to submit reservations, upload payment proof, and track approvals.">
      <Panel>
        <SectionHeading label="Account" title="Sign in" />
        <Field label="Email address" value={email} onChangeText={setEmail} placeholder="name@example.com" keyboardType="email-address" />
        <Field label="Password" value={password} onChangeText={setPassword} placeholder="Your password" secureTextEntry />
        <View style={styles.buttonStack}>
          <AppButton label="Sign in" onPress={handleSubmit} loading={submitting} />
          <AppButton label="Create a new account" onPress={() => router.push('/register')} tone="secondary" />
          <AppButton label="Back to mobile home" onPress={() => router.replace('/(tabs)')} tone="ghost" />
        </View>
      </Panel>
      <Text style={styles.tip}>
        Tip: if you test on a real phone, set <Text style={styles.code}>EXPO_PUBLIC_API_BASE_URL</Text> to your computer&apos;s LAN address, not <Text style={styles.code}>127.0.0.1</Text>.
      </Text>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  buttonStack: {
    gap: 12,
  },
  tip: {
    color: palette.inkMuted,
    fontSize: 13,
    lineHeight: 20,
    paddingHorizontal: 4,
  },
  code: {
    fontWeight: '800',
    color: palette.brandRed,
  },
});
