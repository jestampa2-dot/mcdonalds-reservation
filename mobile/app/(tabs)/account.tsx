import { router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError, deleteProfile, fetchProfile, updateProfile, updateProfilePassword } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { ProfilePayload } from '@/lib/types';

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Non-binary', value: 'non_binary' },
  { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];

export default function AccountScreen() {
  const { token, user, refreshUser, signOut } = useAuth();
  const [profile, setProfile] = useState<ProfilePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
    phone: '',
    birth_date: '',
    gender: 'prefer_not_to_say',
    address_line: '',
    city: '',
    province: '',
    postal_code: '',
  });
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
    delete_password: '',
  });

  const loadProfile = useCallback(async () => {
    if (!token) {
      return;
    }

    try {
      setLoading(true);
      setErrorMessage('');
      const response = await fetchProfile(token);
      setProfile(response.profile);
      setProfileForm({
        name: response.profile.name ?? '',
        email: response.profile.email ?? '',
        phone: response.profile.phone ?? '',
        birth_date: response.profile.birth_date ?? '',
        gender: response.profile.gender ?? 'prefer_not_to_say',
        address_line: response.profile.address_line ?? '',
        city: response.profile.city ?? '',
        province: response.profile.province ?? '',
        postal_code: response.profile.postal_code ?? '',
      });
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your profile right now.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    void loadProfile();
  }, [loadProfile, token]);

  function setProfileValue(key: keyof typeof profileForm, value: string) {
    setProfileForm((current) => ({ ...current, [key]: value }));
  }

  function setPasswordValue(key: keyof typeof passwordForm, value: string) {
    setPasswordForm((current) => ({ ...current, [key]: value }));
  }

  async function saveProfile() {
    if (!token) {
      return;
    }

    try {
      setSavingProfile(true);
      const response = await updateProfile(token, profileForm);
      setProfile(response.profile);
      await refreshUser();
      Alert.alert('Profile updated', response.message);
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to update the profile right now.';
      Alert.alert('Update failed', message);
    } finally {
      setSavingProfile(false);
    }
  }

  async function savePassword() {
    if (!token) {
      return;
    }

    try {
      setSavingPassword(true);
      const response = await updateProfilePassword(token, {
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      });
      setPasswordForm((current) => ({
        ...current,
        current_password: '',
        password: '',
        password_confirmation: '',
      }));
      Alert.alert('Password updated', response.message);
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to update the password right now.';
      Alert.alert('Password update failed', message);
    } finally {
      setSavingPassword(false);
    }
  }

  async function removeAccount() {
    if (!token) {
      return;
    }

    try {
      setDeleting(true);
      const response = await deleteProfile(token, passwordForm.delete_password);
      Alert.alert('Account deleted', response.message);
      await signOut();
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to delete the account right now.';
      Alert.alert('Delete failed', message);
    } finally {
      setDeleting(false);
    }
  }

  if (!user || !token) {
    return (
      <AppScreen eyebrow="Account" title="Sign in to manage your account" subtitle="Profile editing, password changes, and account deletion are available after mobile sign-in.">
        <Panel>
          <AppButton label="Open sign in" onPress={() => router.push('/login')} tone="secondary" />
        </Panel>
      </AppScreen>
    );
  }

  return (
    <AppScreen
      eyebrow="Account"
      title={profile?.name ?? user.name}
      subtitle={`${profile?.role ?? user.role} account · ${profile?.email_verified_at ? 'Verified email' : 'Verification pending'}`}
      scroll={false}
      rightSlot={<AppButton label="Sign out" onPress={() => void signOut()} tone="secondary" />}>
      <ScrollView contentContainerStyle={{ gap: 20, paddingBottom: 32 }}>
        {errorMessage ? (
          <Panel style={styles.errorPanel}>
            <SectionHeading label="Connection issue" title="Profile data is unavailable right now" />
            <Text style={styles.errorText}>{errorMessage}</Text>
            <AppButton label="Reload profile" onPress={() => void loadProfile()} tone="secondary" />
          </Panel>
        ) : null}

        <Panel>
          <SectionHeading label="Profile" title="Personal details" />
          {loading ? <Text style={styles.helper}>Loading your profile...</Text> : null}
          <Field label="Full name" value={profileForm.name} onChangeText={(value) => setProfileValue('name', value)} />
          <Field label="Email address" value={profileForm.email} onChangeText={(value) => setProfileValue('email', value)} keyboardType="email-address" />
          <Field label="Phone number" value={profileForm.phone} onChangeText={(value) => setProfileValue('phone', value)} keyboardType="phone-pad" />
          <Field label="Birth date (YYYY-MM-DD)" value={profileForm.birth_date} onChangeText={(value) => setProfileValue('birth_date', value)} />
          <View style={styles.tagWrap}>
            {genderOptions.map((option) => (
              <Tag key={option.value} label={option.label} active={profileForm.gender === option.value} onPress={() => setProfileValue('gender', option.value)} />
            ))}
          </View>
          <Field label="Street address" value={profileForm.address_line} onChangeText={(value) => setProfileValue('address_line', value)} />
          <Field label="City" value={profileForm.city} onChangeText={(value) => setProfileValue('city', value)} />
          <Field label="Province" value={profileForm.province} onChangeText={(value) => setProfileValue('province', value)} />
          <Field label="Postal code" value={profileForm.postal_code} onChangeText={(value) => setProfileValue('postal_code', value)} keyboardType="numeric" />
          <AppButton label="Save profile" onPress={() => void saveProfile()} loading={savingProfile} />
        </Panel>

        <Panel>
          <SectionHeading label="Security" title="Update password" />
          <Field label="Current password" value={passwordForm.current_password} onChangeText={(value) => setPasswordValue('current_password', value)} secureTextEntry />
          <Field label="New password" value={passwordForm.password} onChangeText={(value) => setPasswordValue('password', value)} secureTextEntry />
          <Field label="Confirm new password" value={passwordForm.password_confirmation} onChangeText={(value) => setPasswordValue('password_confirmation', value)} secureTextEntry />
          <AppButton label="Update password" onPress={() => void savePassword()} loading={savingPassword} />
        </Panel>

        <Panel style={styles.dangerPanel}>
          <SectionHeading label="Danger zone" title="Delete account" />
          <Text style={styles.helper}>Enter your current password to permanently remove this account.</Text>
          <Field label="Current password" value={passwordForm.delete_password} onChangeText={(value) => setPasswordValue('delete_password', value)} secureTextEntry />
          <AppButton label="Delete account" onPress={() => void removeAccount()} loading={deleting} tone="ghost" />
        </Panel>
      </ScrollView>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  helper: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  errorPanel: {
    borderColor: '#F2B5AA',
    backgroundColor: '#FFF4F2',
  },
  errorText: {
    color: palette.brandRedDark,
    lineHeight: 20,
  },
  tagWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  dangerPanel: {
    borderColor: '#F2B5AA',
    backgroundColor: '#FFF4F2',
  },
});
