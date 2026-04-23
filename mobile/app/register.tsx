import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';

const genders = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Non-binary', value: 'non_binary' },
  { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];

export default function RegisterScreen() {
  const { signUp } = useAuth();
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    birth_date: '',
    gender: 'prefer_not_to_say',
    address_line: '',
    city: '',
    province: '',
    postal_code: '',
    password: '',
    password_confirmation: '',
  });
  const [submitting, setSubmitting] = useState(false);

  function setValue(key: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit() {
    try {
      setSubmitting(true);
      await signUp(form);
      router.replace('/(tabs)/dashboard');
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to create the account right now.';
      Alert.alert('Registration failed', message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppScreen
      eyebrow="New customer"
      title="Create your mobile account"
      subtitle="This uses the same reservation backend as your web app, with a token ready for Expo.">
      <Panel>
        <SectionHeading label="Profile" title="Customer details" />
        <Field label="Full name" value={form.name} onChangeText={(value) => setValue('name', value)} placeholder="Your full name" />
        <Field label="Email address" value={form.email} onChangeText={(value) => setValue('email', value)} placeholder="name@example.com" keyboardType="email-address" />
        <Field label="Phone number" value={form.phone} onChangeText={(value) => setValue('phone', value)} placeholder="09xx xxx xxxx" keyboardType="phone-pad" />
        <Field label="Birth date (YYYY-MM-DD)" value={form.birth_date} onChangeText={(value) => setValue('birth_date', value)} placeholder="2001-04-15" />
        <View style={styles.genderRow}>
          {genders.map((option) => (
            <Tag
              key={option.value}
              label={option.label}
              active={form.gender === option.value}
              onPress={() => setValue('gender', option.value)}
            />
          ))}
        </View>
        <Field label="Street address" value={form.address_line} onChangeText={(value) => setValue('address_line', value)} placeholder="Street, barangay, landmark" />
        <Field label="City" value={form.city} onChangeText={(value) => setValue('city', value)} placeholder="City" />
        <Field label="Province" value={form.province} onChangeText={(value) => setValue('province', value)} placeholder="Province" />
        <Field label="Postal code" value={form.postal_code} onChangeText={(value) => setValue('postal_code', value)} placeholder="Optional" keyboardType="numeric" />
        <Field label="Password" value={form.password} onChangeText={(value) => setValue('password', value)} secureTextEntry placeholder="Minimum secure password" />
        <Field
          label="Confirm password"
          value={form.password_confirmation}
          onChangeText={(value) => setValue('password_confirmation', value)}
          secureTextEntry
          placeholder="Repeat your password"
        />
        <View style={styles.actions}>
          <AppButton label="Create account" onPress={handleSubmit} loading={submitting} />
          <AppButton label="I already have an account" onPress={() => router.replace('/login')} tone="secondary" />
        </View>
      </Panel>
      <Text style={styles.note}>
        Birth date should use the <Text style={styles.code}>YYYY-MM-DD</Text> format because the Laravel API validates it that way.
      </Text>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  genderRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  actions: {
    gap: 12,
  },
  note: {
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
