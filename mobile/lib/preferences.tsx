import AsyncStorage from '@react-native-async-storage/async-storage';
import * as LocalAuthentication from 'expo-local-authentication';
import * as Notifications from 'expo-notifications';
import { PropsWithChildren, createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Platform } from 'react-native';

import type { EventInvitation, ReservationRecord } from '@/lib/types';

export type AppLanguage = 'en' | 'fil';
export type DateFormatPreference = 'mdy' | 'dmy' | 'ymd';
export type TimeFormatPreference = '12-hour' | '24-hour';
export type DefaultEventPreference = 'birthday' | 'business' | 'table';

export type AppPreferences = {
  pushNotifications: boolean;
  reminderNotifications: boolean;
  soundAndVibration: boolean;
  emailNotifications: boolean;
  appLockEnabled: boolean;
  language: AppLanguage;
  dateFormat: DateFormatPreference;
  timeFormat: TimeFormatPreference;
  defaultEventType: DefaultEventPreference;
};

type ProtectedPreferenceKey = 'pushNotifications' | 'appLockEnabled';
type DirectPreferenceKey = Exclude<keyof AppPreferences, ProtectedPreferenceKey>;

type PreferenceResult = {
  success: boolean;
  message?: string;
};

type AppPreferencesContextValue = {
  preferences: AppPreferences;
  ready: boolean;
  lastBiometricVerificationAt: number;
  setPreference: <Key extends DirectPreferenceKey>(key: Key, value: AppPreferences[Key]) => void;
  setPushNotifications: (enabled: boolean) => Promise<PreferenceResult>;
  setAppLockEnabled: (enabled: boolean) => Promise<PreferenceResult>;
  syncReservationReminders: (bookings: ReservationRecord[]) => Promise<void>;
  notifyNewInvitations: (invitations: EventInvitation[]) => Promise<void>;
};

const storageKey = 'mcdonalds-reservations:app-preferences';
const reminderKind = 'booking-reminder';
const audibleChannelId = 'booking-reminders';
const silentChannelId = 'booking-reminders-silent';
const invitationChannelId = 'event-invitations';
const invitationSilentChannelId = 'event-invitations-silent';
const invitationNotificationStorageKey = 'mcdonalds-reservations:notified-invitations';

const defaultPreferences: AppPreferences = {
  pushNotifications: false,
  reminderNotifications: true,
  soundAndVibration: true,
  emailNotifications: true,
  appLockEnabled: false,
  language: 'en',
  dateFormat: 'mdy',
  timeFormat: '12-hour',
  defaultEventType: 'birthday',
};

const AppPreferencesContext = createContext<AppPreferencesContextValue | null>(null);

async function cancelBookingReminders() {
  if (Platform.OS === 'web') {
    return;
  }

  const scheduled = await Notifications.getAllScheduledNotificationsAsync();
  const reminderIds = scheduled
    .filter((request) => request.content.data?.kind === reminderKind)
    .map((request) => request.identifier);

  await Promise.all(reminderIds.map((identifier) => Notifications.cancelScheduledNotificationAsync(identifier)));
}

export function AppPreferencesProvider({ children }: PropsWithChildren) {
  const [preferences, setPreferences] = useState(defaultPreferences);
  const [ready, setReady] = useState(false);
  const [lastBiometricVerificationAt, setLastBiometricVerificationAt] = useState(0);
  const latestBookingsRef = useRef<ReservationRecord[]>([]);

  useEffect(() => {
    let active = true;

    void AsyncStorage.getItem(storageKey)
      .then((savedPreferences) => {
        if (!active || !savedPreferences) {
          return;
        }

        const parsed = JSON.parse(savedPreferences) as Partial<AppPreferences>;
        setPreferences((current) => ({ ...current, ...parsed }));
      })
      .catch(() => undefined)
      .finally(() => {
        if (active) {
          setReady(true);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (Platform.OS === 'web') {
      return;
    }

    try {
      Notifications.setNotificationHandler({
        handleNotification: async () => ({
          shouldShowBanner: preferences.pushNotifications,
          shouldShowList: preferences.pushNotifications,
          shouldPlaySound: preferences.pushNotifications && preferences.soundAndVibration,
          shouldSetBadge: false,
        }),
      });
    } catch {
      // Notification support must never prevent the app from opening.
    }
  }, [preferences.pushNotifications, preferences.soundAndVibration]);

  useEffect(() => {
    if (Platform.OS !== 'android' || !ready || !preferences.pushNotifications) {
      return;
    }

    async function configureNotificationChannels() {
      try {
        await Promise.all([
          Notifications.setNotificationChannelAsync(audibleChannelId, {
            name: 'Booking reminders',
            description: 'Upcoming reservation reminders with sound and vibration.',
            importance: Notifications.AndroidImportance.HIGH,
            sound: 'default',
            enableVibrate: true,
            vibrationPattern: [0, 250, 180, 250],
          }),
          Notifications.setNotificationChannelAsync(silentChannelId, {
            name: 'Silent booking reminders',
            description: 'Upcoming reservation reminders without sound or vibration.',
            importance: Notifications.AndroidImportance.DEFAULT,
            sound: null,
            enableVibrate: false,
          }),
          Notifications.setNotificationChannelAsync(invitationChannelId, {
            name: 'Event invitations',
            description: 'Invitations from other McDonald\'s Reservations customers.',
            importance: Notifications.AndroidImportance.HIGH,
            sound: 'default',
            enableVibrate: true,
            vibrationPattern: [0, 250, 180, 250],
          }),
          Notifications.setNotificationChannelAsync(invitationSilentChannelId, {
            name: 'Silent event invitations',
            description: 'Event invitation alerts without sound or vibration.',
            importance: Notifications.AndroidImportance.DEFAULT,
            sound: null,
            enableVibrate: false,
          }),
        ]);
      } catch {
        // Channel creation is optional and should not affect startup.
      }
    }

    void configureNotificationChannels();
  }, [preferences.pushNotifications, ready]);

  const persist = useCallback((nextPreferences: AppPreferences) => {
    setPreferences(nextPreferences);
    void AsyncStorage.setItem(storageKey, JSON.stringify(nextPreferences)).catch(() => undefined);
  }, []);

  const setPreference = useCallback(<Key extends DirectPreferenceKey,>(key: Key, value: AppPreferences[Key]) => {
    setPreferences((current) => {
      const nextPreferences = { ...current, [key]: value };
      void AsyncStorage.setItem(storageKey, JSON.stringify(nextPreferences)).catch(() => undefined);

      if ((key === 'reminderNotifications' || key === 'soundAndVibration') && value === false) {
        void cancelBookingReminders().catch(() => undefined);
      }

      return nextPreferences;
    });
  }, []);

  const setPushNotifications = useCallback(async (enabled: boolean): Promise<PreferenceResult> => {
    if (!enabled) {
      persist({ ...preferences, pushNotifications: false });
      await cancelBookingReminders().catch(() => undefined);
      return { success: true };
    }

    if (Platform.OS === 'web') {
      return { success: false, message: 'Notification permission is available in the installed mobile app.' };
    }

    const existingPermission = await Notifications.getPermissionsAsync();
    const permission = existingPermission.granted ? existingPermission : await Notifications.requestPermissionsAsync();

    if (!permission.granted) {
      return { success: false, message: 'Allow notifications in your phone settings to turn this on.' };
    }

    persist({ ...preferences, pushNotifications: true });
    return { success: true };
  }, [persist, preferences]);

  const setAppLockEnabled = useCallback(async (enabled: boolean): Promise<PreferenceResult> => {
    if (!enabled) {
      persist({ ...preferences, appLockEnabled: false });
      return { success: true };
    }

    if (Platform.OS === 'web') {
      return { success: false, message: 'Biometric app lock is available in the installed mobile app.' };
    }

    const [hasHardware, isEnrolled] = await Promise.all([
      LocalAuthentication.hasHardwareAsync(),
      LocalAuthentication.isEnrolledAsync(),
    ]);

    if (!hasHardware || !isEnrolled) {
      return {
        success: false,
        message: 'Set up fingerprint or face recognition in your phone settings before enabling app lock.',
      };
    }

    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Enable biometric app lock',
      promptSubtitle: 'Confirm your identity',
      cancelLabel: 'Cancel',
    });

    if (!result.success) {
      return { success: false, message: 'Biometric verification was not completed.' };
    }

    setLastBiometricVerificationAt(Date.now());
    persist({ ...preferences, appLockEnabled: true });
    return { success: true };
  }, [persist, preferences]);

  const syncReservationReminders = useCallback(async (bookings: ReservationRecord[]) => {
    latestBookingsRef.current = bookings;

    if (Platform.OS === 'web') {
      return;
    }

    await cancelBookingReminders();

    if (!preferences.pushNotifications || !preferences.reminderNotifications) {
      return;
    }

    const now = Date.now();
    const activeBookings = bookings.filter((booking) => ['confirmed', 'rescheduled'].includes(booking.status));

    await Promise.all(
      activeBookings.map(async (booking) => {
        const eventAt = new Date(`${booking.event_date}T${booking.event_start_time}:00`).getTime();
        const dayBefore = eventAt - 24 * 60 * 60 * 1000;
        const twoHoursBefore = eventAt - 2 * 60 * 60 * 1000;
        const reminderAt = dayBefore > now ? dayBefore : twoHoursBefore;

        if (!Number.isFinite(reminderAt) || reminderAt <= now) {
          return;
        }

        await Notifications.scheduleNotificationAsync({
          content: {
            title: 'Your event is coming up',
            body: `${booking.package_name} at ${booking.branch}. Open the app to view your QR pass.`,
            data: { kind: reminderKind, reservationId: booking.id },
            sound: preferences.soundAndVibration ? 'default' : false,
          },
          trigger: {
            type: Notifications.SchedulableTriggerInputTypes.DATE,
            date: new Date(reminderAt),
            channelId: preferences.soundAndVibration ? audibleChannelId : silentChannelId,
          },
        });
      }),
    );
  }, [preferences.pushNotifications, preferences.reminderNotifications, preferences.soundAndVibration]);

  const notifyNewInvitations = useCallback(async (invitations: EventInvitation[]) => {
    if (Platform.OS === 'web' || !preferences.pushNotifications) {
      return;
    }

    const savedIds = await AsyncStorage.getItem(invitationNotificationStorageKey).catch(() => null);
    const notifiedIds = new Set<number>(savedIds ? JSON.parse(savedIds) : []);
    const pendingInvitations = invitations.filter((invitation) => invitation.status === 'pending');
    const newInvitations = pendingInvitations.filter((invitation) => !notifiedIds.has(invitation.id));

    await Promise.all(
      newInvitations.map((invitation) =>
        Notifications.scheduleNotificationAsync({
          content: {
            title: `${invitation.inviter_name ?? 'A customer'} invited you`,
            body: `${invitation.package_name ?? 'McDonald\'s event'} at ${invitation.branch ?? 'McDonald\'s'}. Open the app to reply.`,
            data: { kind: 'event-invitation', invitationId: invitation.id },
            sound: preferences.soundAndVibration ? 'default' : false,
          },
          trigger: null,
        }),
      ),
    );

    invitations.forEach((invitation) => notifiedIds.add(invitation.id));
    await AsyncStorage.setItem(invitationNotificationStorageKey, JSON.stringify([...notifiedIds])).catch(() => undefined);
  }, [preferences.pushNotifications, preferences.soundAndVibration]);

  useEffect(() => {
    if (!ready || latestBookingsRef.current.length === 0) {
      return;
    }

    void syncReservationReminders(latestBookingsRef.current).catch(() => undefined);
  }, [ready, syncReservationReminders]);

  const value = useMemo<AppPreferencesContextValue>(
    () => ({
      preferences,
      ready,
      lastBiometricVerificationAt,
      setPreference,
      setPushNotifications,
      setAppLockEnabled,
      syncReservationReminders,
      notifyNewInvitations,
    }),
    [lastBiometricVerificationAt, notifyNewInvitations, preferences, ready, setAppLockEnabled, setPreference, setPushNotifications, syncReservationReminders],
  );

  return <AppPreferencesContext.Provider value={value}>{children}</AppPreferencesContext.Provider>;
}

export function useAppPreferences() {
  const context = useContext(AppPreferencesContext);

  if (!context) {
    throw new Error('useAppPreferences must be used inside AppPreferencesProvider.');
  }

  return context;
}
