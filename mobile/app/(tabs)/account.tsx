import { router, useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, RefreshControl, StyleSheet, Text, View, useWindowDimensions } from 'react-native';

import {
  AvatarBadge,
  CustomerButton,
  CustomerCard,
  CustomerChip,
  CustomerField,
  CustomerHeader,
  CustomerPage,
  SectionEyebrow,
  SectionTitle,
  SheetSurface,
} from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import { ApiError, deleteProfile, fetchProfile, updateProfile, updateProfilePassword } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { readCacheEnvelope, writeCache } from '@/lib/cache';
import { getInitials } from '@/lib/formatters';
import type { ProfilePayload } from '@/lib/types';

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Non-binary', value: 'non_binary' },
  { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];
const profileRefreshIntervalMs = 1000 * 60;

export default function AccountScreen() {
  const { token, user, refreshUser, signOut } = useAuth();
  const [profile, setProfile] = useState<ProfilePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
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
  const { width } = useWindowDimensions();
  const isWide = width >= 760;
  const hasProfileRef = useRef(false);
  const lastLoadedAtRef = useRef(0);
  const profileCacheKey = user ? `mobile-cache:profile:${user.id}` : null;

  const loadProfile = useCallback(async (nextRefreshing = false) => {
    if (!token) {
      return;
    }

    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else if (!hasProfileRef.current) {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchProfile(token);
      hasProfileRef.current = true;
      lastLoadedAtRef.current = Date.now();
      setProfile(response.profile);
      if (profileCacheKey) {
        await writeCache(profileCacheKey, response.profile);
      }
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
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your account right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [profileCacheKey, token]);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }

    let active = true;

    if (!profileCacheKey) {
      return;
    }

    void (async () => {
      const cachedProfile = await readCacheEnvelope<ProfilePayload>(profileCacheKey, 1000 * 60 * 10);

      if (active && cachedProfile) {
        hasProfileRef.current = true;
        lastLoadedAtRef.current = cachedProfile.savedAt;
        setProfile(cachedProfile.data);
        setLoading(false);
        setProfileForm({
          name: cachedProfile.data.name ?? '',
          email: cachedProfile.data.email ?? '',
          phone: cachedProfile.data.phone ?? '',
          birth_date: cachedProfile.data.birth_date ?? '',
          gender: cachedProfile.data.gender ?? 'prefer_not_to_say',
          address_line: cachedProfile.data.address_line ?? '',
          city: cachedProfile.data.city ?? '',
          province: cachedProfile.data.province ?? '',
          postal_code: cachedProfile.data.postal_code ?? '',
        });
      }
    })();

    return () => {
      active = false;
    };
  }, [profileCacheKey, token]);

  useFocusEffect(
    useCallback(() => {
      if (token && Date.now() - lastLoadedAtRef.current > profileRefreshIntervalMs) {
        void loadProfile();
      }
    }, [loadProfile, token]),
  );

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
      lastLoadedAtRef.current = Date.now();
      if (profileCacheKey) {
        await writeCache(profileCacheKey, response.profile);
      }
      await refreshUser();
      Alert.alert('Profile updated', response.message);
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to update your profile right now.';
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
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to update your password right now.';
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
      <CustomerPage contentContainerStyle={styles.pageContent}>
        <CustomerHeader title="My Account" subtitle="Sign in to manage your profile." rightSlot={<AvatarBadge label="?" />} />
        <SheetSurface>
          <CustomerCard>
            <SectionEyebrow>Customer access</SectionEyebrow>
            <SectionTitle>Sign in to manage your account</SectionTitle>
            <Text style={styles.helperText}>Profile updates, password changes, and booking history stay tied to your mobile customer record.</Text>
            <View style={styles.stack}>
              <CustomerButton label="Open sign in" onPress={() => router.push('/login')} />
              <CustomerButton label="Create account" onPress={() => router.push('/register')} tone="secondary" />
            </View>
          </CustomerCard>
        </SheetSurface>
      </CustomerPage>
    );
  }

  return (
    <CustomerPage
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadProfile(true)} tintColor={palette.brandRed} />}
      contentContainerStyle={styles.pageContent}>
      <CustomerHeader
        title="My Account"
        subtitle="Profile, security, and verification details."
        rightSlot={<AvatarBadge label={getInitials(profile?.name ?? user.name) || 'U'} />}
      />
      <SheetSurface>
        {errorMessage ? (
          <CustomerCard tone="pink">
            <SectionEyebrow>Connection issue</SectionEyebrow>
            <SectionTitle>Account data is unavailable</SectionTitle>
            <Text style={styles.helperText}>{errorMessage}</Text>
            <CustomerButton label="Reload profile" onPress={() => void loadProfile()} tone="ghost" />
          </CustomerCard>
        ) : null}

        <CustomerCard>
          <View style={styles.summaryHeader}>
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={styles.summaryName}>{profile?.name ?? user.name}</Text>
              <Text style={styles.summaryEmail}>{profile?.email ?? user.email}</Text>
            </View>
            <CustomerButton label="Sign out" onPress={() => void signOut()} tone="ghost" compact />
          </View>

          <View style={styles.summaryGrid}>
            <CustomerCard tone="cream" style={styles.infoPill}>
              <Text style={styles.infoLabel}>Role</Text>
              <Text style={styles.infoValue}>{profile?.role ?? user.role}</Text>
            </CustomerCard>
            <CustomerCard tone="cream" style={styles.infoPill}>
              <Text style={styles.infoLabel}>Status</Text>
              <Text style={styles.infoValue}>{profile?.email_verified_at ? 'Verified' : 'Verification pending'}</Text>
            </CustomerCard>
            <CustomerCard tone="cream" style={styles.infoPill}>
              <Text style={styles.infoLabel}>Location</Text>
              <Text style={styles.infoValue}>{profile?.city || profile?.province ? profile?.full_address : 'Add your location'}</Text>
            </CustomerCard>
          </View>
        </CustomerCard>

        {loading ? <Text style={styles.helperText}>Loading your profile details...</Text> : null}

        <View style={[styles.panelGrid, isWide ? styles.panelGridWide : null]}>
          <CustomerCard style={[styles.panelCard, isWide ? styles.panelCardWide : null]}>
            <SectionEyebrow>Profile</SectionEyebrow>
            <SectionTitle>Account details</SectionTitle>
            <CustomerField label="Full name" value={profileForm.name} onChangeText={(value) => setProfileValue('name', value)} />
            <CustomerField label="Email" value={profileForm.email} onChangeText={(value) => setProfileValue('email', value)} keyboardType="email-address" />
            <CustomerField label="Phone number" value={profileForm.phone} onChangeText={(value) => setProfileValue('phone', value)} keyboardType="phone-pad" />
            <CustomerField label="Birth date" value={profileForm.birth_date} onChangeText={(value) => setProfileValue('birth_date', value)} placeholder="YYYY-MM-DD" />
            <View style={styles.chipRow}>
              {genderOptions.map((option) => (
                <CustomerChip
                  key={option.value}
                  label={option.label}
                  active={profileForm.gender === option.value}
                  onPress={() => setProfileValue('gender', option.value)}
                />
              ))}
            </View>
            <CustomerField label="Street address" value={profileForm.address_line} onChangeText={(value) => setProfileValue('address_line', value)} />
            <CustomerField label="City" value={profileForm.city} onChangeText={(value) => setProfileValue('city', value)} />
            <CustomerField label="Province" value={profileForm.province} onChangeText={(value) => setProfileValue('province', value)} />
            <CustomerField label="Postal code" value={profileForm.postal_code} onChangeText={(value) => setProfileValue('postal_code', value)} keyboardType="numeric" />
            <CustomerButton label="Save account details" onPress={() => void saveProfile()} loading={savingProfile} />
          </CustomerCard>

          <CustomerCard style={[styles.panelCard, isWide ? styles.panelCardWide : null]}>
            <SectionEyebrow>Security</SectionEyebrow>
            <SectionTitle>Update password</SectionTitle>
            <CustomerField label="Current password" value={passwordForm.current_password} onChangeText={(value) => setPasswordValue('current_password', value)} secureTextEntry />
            <CustomerField label="New password" value={passwordForm.password} onChangeText={(value) => setPasswordValue('password', value)} secureTextEntry />
            <CustomerField
              label="Confirm password"
              value={passwordForm.password_confirmation}
              onChangeText={(value) => setPasswordValue('password_confirmation', value)}
              secureTextEntry
            />
            <CustomerButton label="Update password" onPress={() => void savePassword()} loading={savingPassword} />

            {user.role !== 'customer' ? (
              <CustomerCard tone="cream">
                <SectionEyebrow>Role tools</SectionEyebrow>
                <SectionTitle>Operations workspace</SectionTitle>
                <Text style={styles.helperText}>You still have access to the hidden staff/admin mobile tools from here.</Text>
                <CustomerButton label="Open operations" onPress={() => router.push('/(tabs)/operations')} tone="secondary" />
              </CustomerCard>
            ) : null}
          </CustomerCard>
        </View>

        <CustomerCard tone="pink">
          <SectionEyebrow>Danger zone</SectionEyebrow>
          <SectionTitle>Delete account</SectionTitle>
          <Text style={styles.helperText}>Enter your current password to permanently remove this customer account and its access token.</Text>
          <CustomerField
            label="Current password"
            value={passwordForm.delete_password}
            onChangeText={(value) => setPasswordValue('delete_password', value)}
            secureTextEntry
          />
          <CustomerButton label="Delete account" onPress={() => void removeAccount()} loading={deleting} tone="danger" />
        </CustomerCard>
      </SheetSurface>
    </CustomerPage>
  );
}

const styles = StyleSheet.create({
  pageContent: {
    gap: 0,
  },
  stack: {
    gap: 10,
  },
  helperText: {
    color: '#675446',
    lineHeight: 20,
    fontSize: 14,
  },
  summaryHeader: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'flex-start',
  },
  summaryName: {
    color: palette.ink,
    fontSize: 30,
    fontWeight: '900',
    lineHeight: 34,
  },
  summaryEmail: {
    color: '#675446',
    fontSize: 15,
  },
  summaryGrid: {
    gap: 10,
  },
  infoPill: {
    paddingVertical: 12,
  },
  infoLabel: {
    color: '#7A604B',
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  infoValue: {
    color: palette.ink,
    fontSize: 14,
    fontWeight: '700',
  },
  panelGrid: {
    gap: 12,
  },
  panelGridWide: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  panelCard: {
    gap: 12,
  },
  panelCardWide: {
    flex: 1,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
});
