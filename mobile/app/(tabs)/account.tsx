import * as ImagePicker from 'expo-image-picker';
import Constants from 'expo-constants';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Linking, RefreshControl, Share, StyleSheet, Text, View } from 'react-native';

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
import { SettingsDivider, SettingsModal, SettingsRow } from '@/components/settings-ui';
import { ApiError, deleteProfile, fetchProfile, updateProfile, updateProfilePassword } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { readCacheEnvelope, writeCache } from '@/lib/cache';
import { getInitials } from '@/lib/formatters';
import { useAppPreferences } from '@/lib/preferences';
import { AppThemeColors, useAppTheme } from '@/lib/theme';
import type { ProfilePayload } from '@/lib/types';

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Non-binary', value: 'non_binary' },
  { label: 'Prefer not to say', value: 'prefer_not_to_say' },
];
const profileRefreshIntervalMs = 1000 * 60;

type PickedProfilePhoto = {
  uri: string;
  name: string;
  type: string;
};

export default function AccountScreen() {
  const { colors, preference, setMode, fontSize, setFontSize } = useAppTheme();
  const styles = useMemo(() => createStyles(colors), [colors]);
  const { preferences, setPreference, setPushNotifications, setAppLockEnabled } = useAppPreferences();
  const { token, user, refreshUser, signOut } = useAuth();
  const [profile, setProfile] = useState<ProfilePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [savingNotifications, setSavingNotifications] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [infoModal, setInfoModal] = useState<'faq' | 'privacy' | 'about' | null>(null);
  const [profilePhoto, setProfilePhoto] = useState<PickedProfilePhoto | null>(null);
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
    phone: '',
    sms_notifications_enabled: false,
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
  const hasProfileRef = useRef(false);
  const lastLoadedAtRef = useRef(0);
  const profileCacheKey = user ? `mobile-cache:profile:${user.id}` : null;
  const activePhotoUrl = profilePhoto?.uri ?? profile?.profile_photo_url ?? user?.profile_photo_url ?? null;
  const appVersion = Constants.expoConfig?.version ?? '1.2.0';

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
      setProfilePhoto(null);
      if (profileCacheKey) {
        await writeCache(profileCacheKey, response.profile);
      }
      setProfileForm({
        name: response.profile.name ?? '',
        email: response.profile.email ?? '',
        phone: response.profile.phone ?? '',
        sms_notifications_enabled: response.profile.sms_notifications_enabled ?? false,
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
          sms_notifications_enabled: cachedProfile.data.sms_notifications_enabled ?? false,
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

  function setProfileValue<Key extends keyof typeof profileForm>(key: Key, value: (typeof profileForm)[Key]) {
    setProfileForm((current) => ({ ...current, [key]: value }));
  }

  function setPasswordValue(key: keyof typeof passwordForm, value: string) {
    setPasswordForm((current) => ({ ...current, [key]: value }));
  }

  async function chooseProfilePhoto() {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission needed', 'Allow photo library access so you can choose a profile picture.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.75,
    });

    if (result.canceled || !result.assets.length) {
      return;
    }

    const asset = result.assets[0];
    setProfilePhoto({
      uri: asset.uri,
      name: asset.fileName ?? `profile-photo-${Date.now()}.jpg`,
      type: asset.mimeType ?? 'image/jpeg',
    });
  }

  async function saveProfile() {
    if (!token) {
      return;
    }

    try {
      setSavingProfile(true);
      const payload = profilePhoto ? new FormData() : profileForm;

      if (profilePhoto && payload instanceof FormData) {
        Object.entries(profileForm).forEach(([key, value]) => {
          payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
        });
        payload.append('profile_photo', {
          uri: profilePhoto.uri,
          name: profilePhoto.name,
          type: profilePhoto.type,
        } as any);
      }

      const response = await updateProfile(token, payload);
      setProfile(response.profile);
      setProfilePhoto(null);
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

  function confirmDeleteAccount() {
    if (!passwordForm.delete_password.trim()) {
      Alert.alert('Password required', 'Enter your current password before deleting your account.');
      return;
    }

    Alert.alert(
      'Permanently delete account?',
      'Your profile, mobile access, and account data will be removed. This action cannot be undone.',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Delete account', style: 'destructive', onPress: () => void removeAccount() },
      ],
    );
  }

  function confirmSignOut() {
    Alert.alert('Log out?', 'You will need to sign in again to view your bookings and QR passes.', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Log out',
        style: 'destructive',
        onPress: () => {
          void signOut().then(() => router.replace('/(tabs)'));
        },
      },
    ]);
  }

  async function handlePushNotifications(enabled: boolean) {
    try {
      const result = await setPushNotifications(enabled);

      if (!result.success) {
        Alert.alert('Notifications unavailable', result.message);
      }
    } catch {
      Alert.alert('Notifications unavailable', 'Unable to update notification permission right now.');
    }
  }

  async function handleSmsNotifications(enabled: boolean) {
    if (!token) {
      return;
    }

    const nextForm = { ...profileForm, sms_notifications_enabled: enabled };
    setProfileForm(nextForm);

    try {
      setSavingNotifications(true);
      const response = await updateProfile(token, nextForm);
      setProfile(response.profile);
      lastLoadedAtRef.current = Date.now();
      if (profileCacheKey) {
        await writeCache(profileCacheKey, response.profile);
      }
      await refreshUser();
    } catch (error) {
      setProfileForm((current) => ({ ...current, sms_notifications_enabled: !enabled }));
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to update SMS notifications.';
      Alert.alert('SMS setting not saved', message);
    } finally {
      setSavingNotifications(false);
    }
  }

  async function handleAppLock(enabled: boolean) {
    try {
      const result = await setAppLockEnabled(enabled);

      if (!result.success) {
        Alert.alert('App lock unavailable', result.message);
      }
    } catch {
      Alert.alert('App lock unavailable', 'Unable to verify biometric security right now.');
    }
  }

  async function openSupportShare(kind: 'contact' | 'report') {
    const heading = kind === 'contact' ? 'Support request' : 'Problem report';
    const prompt = kind === 'contact'
      ? 'Please describe what you need help with.'
      : 'Please describe what happened, what you expected, and the steps that caused the problem.';

    await Share.share({
      title: `${heading} - McDonalds Reservations`,
      message: `${heading}\n\n${prompt}\n\nApp version: ${appVersion}`,
    });
  }

  async function openAppPermissions() {
    try {
      await Linking.openSettings();
    } catch {
      Alert.alert('Open phone settings', 'Open this app in your phone settings to manage camera, photos, notifications, and biometric access.');
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
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadProfile(true)} tintColor={colors.brandText} />}
      contentContainerStyle={styles.pageContent}>
      <CustomerHeader
        title="My Account"
        subtitle="Profile, preferences, security, and support."
        rightSlot={<AvatarBadge label={getInitials(profile?.name ?? user.name) || 'U'} imageUrl={activePhotoUrl} />}
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
            <View style={styles.summaryIdentity}>
              <AvatarBadge label={getInitials(profile?.name ?? user.name) || 'U'} imageUrl={activePhotoUrl} size={70} />
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={styles.summaryName}>{profile?.name ?? user.name}</Text>
                <Text style={styles.summaryEmail}>{profile?.email ?? user.email}</Text>
              </View>
            </View>
            <CustomerButton label="Log out" onPress={confirmSignOut} tone="ghost" compact />
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

        <CustomerCard style={styles.panelCard}>
          <SectionEyebrow>1. Account settings</SectionEyebrow>
          <SectionTitle>Profile information</SectionTitle>
          <View style={styles.photoEditor}>
            <AvatarBadge label={getInitials(profileForm.name || user.name) || 'U'} imageUrl={activePhotoUrl} size={88} />
            <View style={styles.photoEditorCopy}>
              <Text style={styles.helperText}>Keep your name, email, phone number, and profile photo up to date.</Text>
              <CustomerButton
                label={profilePhoto ? 'Replace selected photo' : activePhotoUrl ? 'Change profile photo' : 'Choose profile photo'}
                onPress={() => void chooseProfilePhoto()}
                tone="secondary"
                compact
              />
              {profilePhoto ? <Text style={styles.photoPendingText}>New photo selected. Tap save to upload it.</Text> : null}
            </View>
          </View>
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
          <CustomerButton label="Save profile" onPress={() => void saveProfile()} loading={savingProfile} />

          {user.role !== 'customer' ? (
            <CustomerButton label="Open operations workspace" onPress={() => router.push('/(tabs)/operations')} tone="secondary" />
          ) : null}
        </CustomerCard>

        <CustomerCard style={styles.panelCard}>
          <SectionEyebrow>2. Notifications</SectionEyebrow>
          <SectionTitle>Choose how we notify you</SectionTitle>
          <SettingsRow
            icon="bell-outline"
            title="Push notifications"
            subtitle="Allow alerts from the installed mobile app."
            switchValue={preferences.pushNotifications}
            onSwitchChange={(value) => void handlePushNotifications(value)}
          />
          <SettingsDivider />
          <SettingsRow
            icon="calendar-clock"
            title="Reminder notifications"
            subtitle={preferences.pushNotifications ? 'Remind me before confirmed events.' : 'Turn on push notifications first.'}
            switchValue={preferences.pushNotifications && preferences.reminderNotifications}
            onSwitchChange={(value) => setPreference('reminderNotifications', value)}
            disabled={!preferences.pushNotifications}
          />
          <SettingsDivider />
          <SettingsRow
            icon="volume-high"
            title="Sound and vibration"
            subtitle="Use sound and vibration for booking reminders."
            switchValue={preferences.pushNotifications && preferences.soundAndVibration}
            onSwitchChange={(value) => setPreference('soundAndVibration', value)}
            disabled={!preferences.pushNotifications}
          />
          <SettingsDivider />
          <SettingsRow
            icon="email-outline"
            title="Email notifications"
            subtitle="Save your preference for reservation and account email updates."
            switchValue={preferences.emailNotifications}
            onSwitchChange={(value) => setPreference('emailNotifications', value)}
          />
          {user.role === 'customer' ? (
            <>
              <SettingsDivider />
              <SettingsRow
                icon="message-text-outline"
                title="SMS booking updates"
                subtitle="Receive status changes at your saved phone number."
                switchValue={profileForm.sms_notifications_enabled}
                onSwitchChange={(value) => void handleSmsNotifications(value)}
                disabled={savingNotifications}
              />
            </>
          ) : null}
        </CustomerCard>

        <CustomerCard tone="cream" style={styles.panelCard}>
          <SectionEyebrow>3. Appearance</SectionEyebrow>
          <SectionTitle>Customize the app</SectionTitle>
          <Text style={styles.settingLabel}>Theme</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="Light" active={preference === 'light'} onPress={() => setMode('light')} />
            <CustomerChip label="Dark" active={preference === 'dark'} onPress={() => setMode('dark')} tone="neutral" />
            <CustomerChip label="Follow system" active={preference === 'system'} onPress={() => setMode('system')} tone="green" />
          </View>
          <Text style={styles.settingLabel}>Font size</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="Standard" active={fontSize === 'standard'} onPress={() => setFontSize('standard')} />
            <CustomerChip label="Large" active={fontSize === 'large'} onPress={() => setFontSize('large')} tone="neutral" />
          </View>
          <Text style={styles.helperText}>The app still respects your phone&apos;s accessibility text scaling.</Text>
        </CustomerCard>

        <CustomerCard style={styles.panelCard}>
          <SectionEyebrow>4. Privacy & security</SectionEyebrow>
          <SectionTitle>Protect your account</SectionTitle>
          <SettingsRow
            icon="fingerprint"
            title="Biometric app lock"
            subtitle="Require fingerprint or face recognition after leaving the app."
            switchValue={preferences.appLockEnabled}
            onSwitchChange={(value) => void handleAppLock(value)}
          />
          <SettingsDivider />
          <SettingsRow
            icon="shield-key-outline"
            title="Manage app permissions"
            subtitle="Review camera, photos, notifications, and biometric access."
            onPress={() => void openAppPermissions()}
          />
          <SettingsDivider />
          <SettingsRow
            icon="file-document-outline"
            title="Privacy policy"
            subtitle="See how account and reservation data is used."
            onPress={() => setInfoModal('privacy')}
          />

          <View style={styles.subsection}>
            <SectionTitle>Change password</SectionTitle>
            <CustomerField label="Current password" value={passwordForm.current_password} onChangeText={(value) => setPasswordValue('current_password', value)} secureTextEntry />
            <CustomerField label="New password" value={passwordForm.password} onChangeText={(value) => setPasswordValue('password', value)} secureTextEntry />
            <CustomerField
              label="Confirm password"
              value={passwordForm.password_confirmation}
              onChangeText={(value) => setPasswordValue('password_confirmation', value)}
              secureTextEntry
            />
            <CustomerButton label="Update password" onPress={() => void savePassword()} loading={savingPassword} />
          </View>

          <View style={[styles.subsection, styles.dangerSection]}>
            <SectionTitle>Delete account</SectionTitle>
            <Text style={styles.helperText}>Enter your current password to permanently remove your account and access token.</Text>
            <CustomerField
              label="Current password"
              value={passwordForm.delete_password}
              onChangeText={(value) => setPasswordValue('delete_password', value)}
              secureTextEntry
            />
            <CustomerButton label="Delete account" onPress={confirmDeleteAccount} loading={deleting} tone="danger" />
          </View>
        </CustomerCard>

        <CustomerCard style={styles.panelCard}>
          <SectionEyebrow>5. General preferences</SectionEyebrow>
          <SectionTitle>Booking defaults</SectionTitle>
          <Text style={styles.settingLabel}>Language</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="English" active={preferences.language === 'en'} onPress={() => setPreference('language', 'en')} />
            <CustomerChip label="Filipino dates" active={preferences.language === 'fil'} onPress={() => setPreference('language', 'fil')} tone="green" />
          </View>
          <Text style={styles.settingLabel}>Date format</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="Month / Day / Year" active={preferences.dateFormat === 'mdy'} onPress={() => setPreference('dateFormat', 'mdy')} />
            <CustomerChip label="Day / Month / Year" active={preferences.dateFormat === 'dmy'} onPress={() => setPreference('dateFormat', 'dmy')} tone="neutral" />
            <CustomerChip label="YYYY-MM-DD" active={preferences.dateFormat === 'ymd'} onPress={() => setPreference('dateFormat', 'ymd')} tone="green" />
          </View>
          <Text style={styles.settingLabel}>Time format</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="12-hour" active={preferences.timeFormat === '12-hour'} onPress={() => setPreference('timeFormat', '12-hour')} />
            <CustomerChip label="24-hour" active={preferences.timeFormat === '24-hour'} onPress={() => setPreference('timeFormat', '24-hour')} tone="neutral" />
          </View>
          <Text style={styles.settingLabel}>Default event type</Text>
          <View style={styles.chipRow}>
            <CustomerChip label="Birthday" active={preferences.defaultEventType === 'birthday'} onPress={() => setPreference('defaultEventType', 'birthday')} />
            <CustomerChip label="Business" active={preferences.defaultEventType === 'business'} onPress={() => setPreference('defaultEventType', 'business')} tone="neutral" />
            <CustomerChip label="Table" active={preferences.defaultEventType === 'table'} onPress={() => setPreference('defaultEventType', 'table')} tone="green" />
          </View>
        </CustomerCard>

        <CustomerCard style={styles.panelCard}>
          <SectionEyebrow>6. Help & support</SectionEyebrow>
          <SectionTitle>Get help</SectionTitle>
          <SettingsRow icon="frequently-asked-questions" title="Frequently asked questions" onPress={() => setInfoModal('faq')} />
          <SettingsDivider />
          <SettingsRow icon="lifebuoy" title="Contact support" subtitle="Share a support request through an app on your phone." onPress={() => void openSupportShare('contact')} />
          <SettingsDivider />
          <SettingsRow icon="bug-outline" title="Report a problem" subtitle="Include the app version in a problem report." onPress={() => void openSupportShare('report')} />
          <SettingsDivider />
          <SettingsRow icon="information-outline" title="About the app" value={`v${appVersion}`} onPress={() => setInfoModal('about')} />
        </CustomerCard>

        <CustomerCard tone="pink" style={styles.panelCard}>
          <SectionEyebrow>7. Logout</SectionEyebrow>
          <SectionTitle>Securely end this session</SectionTitle>
          <Text style={styles.helperText}>Your saved login token and cached customer data will be removed from this phone.</Text>
          <CustomerButton label="Log out" onPress={confirmSignOut} tone="danger" icon="logout" />
        </CustomerCard>
      </SheetSurface>

      <SettingsModal visible={infoModal === 'faq'} title="Frequently asked questions" onClose={() => setInfoModal(null)}>
        <CustomerCard>
          <SectionTitle>Where is my QR pass?</SectionTitle>
          <Text style={styles.helperText}>Open Dashboard. The QR pass appears after an administrator confirms your reservation.</Text>
        </CustomerCard>
        <CustomerCard>
          <SectionTitle>Why is my booking pending?</SectionTitle>
          <Text style={styles.helperText}>The branch still needs to review your schedule, package, and payment proof.</Text>
        </CustomerCard>
        <CustomerCard>
          <SectionTitle>How do reminders work?</SectionTitle>
          <Text style={styles.helperText}>Enable push and reminder notifications. Confirmed events are scheduled for a reminder before the party starts.</Text>
        </CustomerCard>
        <CustomerCard>
          <SectionTitle>Why can&apos;t the app connect?</SectionTitle>
          <Text style={styles.helperText}>Keep your phone and Laravel server on the same Wi-Fi, then confirm the mobile API address is reachable.</Text>
        </CustomerCard>
      </SettingsModal>

      <SettingsModal visible={infoModal === 'privacy'} title="Privacy policy" onClose={() => setInfoModal(null)}>
        <CustomerCard>
          <SectionTitle>Information we use</SectionTitle>
          <Text style={styles.helperText}>The app stores your profile, contact details, reservation choices, payment proof, and event status so the branch can process your booking.</Text>
        </CustomerCard>
        <CustomerCard>
          <SectionTitle>Device access</SectionTitle>
          <Text style={styles.helperText}>Camera access is used for staff QR scanning. Photo access is used only when you choose a profile image or payment proof. Biometrics stay on your device.</Text>
        </CustomerCard>
        <CustomerCard>
          <SectionTitle>Your controls</SectionTitle>
          <Text style={styles.helperText}>You can update your profile, change notification preferences, revoke phone permissions, log out, or permanently delete your account here.</Text>
        </CustomerCard>
      </SettingsModal>

      <SettingsModal visible={infoModal === 'about'} title="About the app" onClose={() => setInfoModal(null)}>
        <CustomerCard tone="yellow">
          <SectionEyebrow>McDonalds Reservations</SectionEyebrow>
          <SectionTitle>Mobile event booking</SectionTitle>
          <Text style={styles.helperText}>Book events, upload payment proof, track approval, show a QR pass, and stay connected with staff and administrators.</Text>
          <Text style={styles.versionText}>Version {appVersion}</Text>
        </CustomerCard>
      </SettingsModal>
    </CustomerPage>
  );
}

const createStyles = (colors: AppThemeColors) => StyleSheet.create({
  pageContent: {
    gap: 0,
  },
  stack: {
    gap: 10,
  },
  helperText: {
    color: colors.textMuted,
    lineHeight: 20,
    fontSize: 14,
  },
  summaryHeader: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'flex-start',
  },
  summaryIdentity: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  summaryName: {
    color: colors.text,
    fontSize: 30,
    fontWeight: '900',
    lineHeight: 34,
  },
  summaryEmail: {
    color: colors.textMuted,
    fontSize: 15,
  },
  summaryGrid: {
    gap: 10,
  },
  infoPill: {
    paddingVertical: 12,
  },
  infoLabel: {
    color: colors.textMuted,
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  infoValue: {
    color: colors.text,
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
  photoEditor: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 12,
    borderRadius: 18,
    backgroundColor: colors.surfaceAlt,
  },
  photoEditorCopy: {
    flex: 1,
    gap: 8,
  },
  photoPendingText: {
    color: colors.brandText,
    fontSize: 12,
    fontWeight: '800',
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  settingLabel: {
    color: colors.text,
    fontSize: 13,
    fontWeight: '900',
    marginTop: 4,
  },
  subsection: {
    gap: 12,
    marginTop: 8,
    paddingTop: 18,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  dangerSection: {
    padding: 14,
    borderRadius: 8,
    borderTopWidth: 0,
    backgroundColor: colors.dangerSurface,
  },
  versionText: {
    color: colors.brandText,
    fontSize: 14,
    fontWeight: '900',
  },
});
