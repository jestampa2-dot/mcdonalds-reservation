import { Redirect, router } from 'expo-router';
import { startTransition, useState } from 'react';
import { Alert, Keyboard, StyleSheet, Text, View } from 'react-native';

import {
  CustomerButton,
  CustomerCard,
  CustomerChip,
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

const genders = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Non-binary', value: 'non_binary' },
  { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];

export default function RegisterScreen() {
  const { signUp, user, booting } = useAuth();
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

  function getRegistrationError(nextForm = form) {
    const requiredFields: (keyof typeof form)[] = [
      'name',
      'email',
      'phone',
      'birth_date',
      'address_line',
      'city',
      'province',
      'password',
      'password_confirmation',
    ];
    const missingField = requiredFields.find((key) => !nextForm[key].trim());

    if (missingField) {
      return 'Complete all required customer details before creating the account.';
    }

    if (!nextForm.email.includes('@')) {
      return 'Enter a valid email address.';
    }

    if (nextForm.password !== nextForm.password_confirmation) {
      return 'Password and confirm password must match.';
    }

    if (nextForm.password.length < 8) {
      return 'Password must be at least 8 characters.';
    }

    return '';
  }

  async function handleSubmit() {
    const normalizedForm = {
      ...form,
      email: form.email.trim().toLowerCase(),
      name: form.name.trim(),
      phone: form.phone.trim(),
      birth_date: form.birth_date.trim(),
      address_line: form.address_line.trim(),
      city: form.city.trim(),
      province: form.province.trim(),
      postal_code: form.postal_code.trim(),
    };
    const validationError = getRegistrationError(normalizedForm);

    if (validationError) {
      Alert.alert('Check your details', validationError);
      return;
    }

    try {
      Keyboard.dismiss();
      setSubmitting(true);
      await signUp(normalizedForm);
      startTransition(() => {
        router.replace('/(tabs)/dashboard');
      });
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to create the account right now.';
      Alert.alert('Registration failed', message);
    } finally {
      setSubmitting(false);
    }
  }

  if (!booting && user) {
    return <Redirect href="/(tabs)/dashboard" />;
  }

  const canSubmit = !getRegistrationError();

  return (
    <CustomerPage contentContainerStyle={styles.pageContent}>
      <CustomerHeader title="Create account" subtitle="Set up your customer profile for mobile reservations." rightSlot={<McLogo />} />
      <View style={styles.content}>
        <CustomerCard tone="yellow">
          <SectionEyebrow>New customer</SectionEyebrow>
          <Text style={styles.heroTitle}>Create your mobile booking account</Text>
          <Text style={styles.heroText}>This customer profile is stored in the same database your admin and reservation dashboard already use.</Text>
        </CustomerCard>

        <CustomerCard>
          <SectionTitle>Customer details</SectionTitle>
          <CustomerField label="Full name" value={form.name} onChangeText={(value) => setValue('name', value)} placeholder="Your full name" />
          <CustomerField
            label="Email address"
            value={form.email}
            onChangeText={(value) => setValue('email', value)}
            placeholder="name@gmail.com"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="email"
            textContentType="emailAddress"
          />
          <CustomerField label="Phone number" value={form.phone} onChangeText={(value) => setValue('phone', value)} placeholder="09xx xxx xxxx" keyboardType="phone-pad" />
          <CustomerField label="Birth date" value={form.birth_date} onChangeText={(value) => setValue('birth_date', value)} placeholder="YYYY-MM-DD" />
          <View style={styles.genderRow}>
            {genders.map((option) => (
              <CustomerChip
                key={option.value}
                label={option.label}
                active={form.gender === option.value}
                onPress={() => setValue('gender', option.value)}
              />
            ))}
          </View>
          <CustomerField label="Street address" value={form.address_line} onChangeText={(value) => setValue('address_line', value)} placeholder="Street, barangay, municipal" />
          <CustomerField label="City" value={form.city} onChangeText={(value) => setValue('city', value)} placeholder="City" />
          <CustomerField label="Province" value={form.province} onChangeText={(value) => setValue('province', value)} placeholder="Province" />
          <CustomerField label="Postal code" value={form.postal_code} onChangeText={(value) => setValue('postal_code', value)} placeholder="Optional" keyboardType="numeric" />
          <CustomerField
            label="Password"
            value={form.password}
            onChangeText={(value) => setValue('password', value)}
            secureTextEntry
            placeholder="Minimum secure password"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="new-password"
            textContentType="newPassword"
          />
          <CustomerField
            label="Confirm password"
            value={form.password_confirmation}
            onChangeText={(value) => setValue('password_confirmation', value)}
            secureTextEntry
            placeholder="Repeat your password"
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="new-password"
            textContentType="newPassword"
          />
          <View style={styles.actions}>
            <CustomerButton label="Create account" onPress={handleSubmit} loading={submitting} disabled={!canSubmit} />
            <CustomerButton label="I already have an account" onPress={() => router.replace('/login')} tone="secondary" />
          </View>
        </CustomerCard>

        <Text style={styles.note}>
          Birth date should use the <Text style={styles.code}>YYYY-MM-DD</Text> format because the Laravel API validates it that way.
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
  genderRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  actions: {
    gap: 10,
  },
  note: {
    color: '#6A5647',
    fontSize: 13,
    lineHeight: 20,
  },
  code: {
    color: palette.brandRed,
    fontWeight: '800',
  },
});
