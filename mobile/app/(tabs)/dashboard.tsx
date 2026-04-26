import { router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError, cancelReservation, fetchDashboard, rescheduleReservation } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { DashboardPayload } from '@/lib/types';

const statusTone: Record<string, boolean> = {
  confirmed: true,
  checked_in: true,
  pending_review: false,
  rescheduled: false,
  cancelled: false,
  completed: true,
};

export default function DashboardScreen() {
  const { user, token, signOut, booting } = useAuth();
  const [payload, setPayload] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [rescheduleForms, setRescheduleForms] = useState<Record<number, { event_date: string; event_time: string }>>({});

  const loadDashboard = useCallback(async (nextRefreshing = false) => {
    if (!token) {
      return;
    }

    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchDashboard(token);
      setPayload(response);
      setRescheduleForms(
        Object.fromEntries(
          response.bookings.map((booking) => [
            booking.id,
            {
              event_date: booking.event_date,
              event_time: booking.event_start_time,
            },
          ]),
        ),
      );
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your reservation history right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => {
    if (token) {
      void loadDashboard();
    }
  }, [loadDashboard, token]);

  async function handleCancel(reservationId: number) {
    try {
      const response = await cancelReservation(token!, reservationId);
      Alert.alert('Booking updated', response.message);
      await loadDashboard();
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to cancel the booking right now.';
      Alert.alert('Cancel failed', message);
    }
  }

  async function handleReschedule(reservationId: number) {
    try {
      const response = await rescheduleReservation(token!, reservationId, rescheduleForms[reservationId]);
      Alert.alert('Booking updated', response.message);
      await loadDashboard();
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to reschedule right now.';
      Alert.alert('Reschedule failed', message);
    }
  }

  function setRescheduleValue(reservationId: number, key: 'event_date' | 'event_time', value: string) {
    setRescheduleForms((current) => ({
      ...current,
      [reservationId]: {
        ...(current[reservationId] ?? { event_date: '', event_time: '' }),
        [key]: value,
      },
    }));
  }

  if (booting) {
    return (
      <AppScreen eyebrow="Customer dashboard" title="Loading session" subtitle="Checking whether you already have a saved mobile token.">
        <Panel>
          <Text style={styles.mutedText}>Preparing your dashboard...</Text>
        </Panel>
      </AppScreen>
    );
  }

  if (!user || !token) {
    return (
      <AppScreen
        eyebrow="Customer dashboard"
        title="Track your bookings in one place"
        subtitle="Sign in to see your submitted reservations, confirmation status, payment proof preview, and total spend.">
        <Panel>
          <SectionHeading label="Next step" title="Create or access your account" />
          <Text style={styles.mutedText}>
            The dashboard uses authenticated Sanctum tokens, so it only appears once you sign in from the mobile app.
          </Text>
          <View style={styles.buttonStack}>
            <AppButton label="Sign in" onPress={() => router.push('/login')} />
            <AppButton label="Create account" onPress={() => router.push('/register')} tone="secondary" />
          </View>
        </Panel>
      </AppScreen>
    );
  }

  return (
    <AppScreen
      eyebrow="Customer dashboard"
      title={`${user.name.split(' ')[0]}'s reservations`}
      subtitle="Live view of the same reservation records your Laravel dashboard already stores."
      scroll={false}
      rightSlot={<AppButton label="Sign out" onPress={() => void signOut()} tone="secondary" />}>
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadDashboard(true)} tintColor={palette.brandRed} />}
        contentContainerStyle={{ gap: 20, paddingBottom: 32 }}>
        {errorMessage ? (
          <Panel style={styles.errorPanel}>
            <SectionHeading label="Connection issue" title="Dashboard data is unavailable right now" />
            <Text style={styles.errorText}>{errorMessage}</Text>
            <AppButton label="Reload dashboard" onPress={() => void loadDashboard()} tone="secondary" />
          </Panel>
        ) : null}

        {loading && !payload ? (
          <Panel>
            <Text style={styles.mutedText}>Loading your reservation history...</Text>
          </Panel>
        ) : (
          <>
            <View style={styles.statGrid}>
              {(payload?.stats ?? []).map((stat) => (
                <Panel key={stat.label} style={styles.statCard}>
                  <Text style={styles.statLabel}>{stat.label}</Text>
                  <Text style={styles.statValue}>{stat.value}</Text>
                </Panel>
              ))}
            </View>

            <Panel>
              <SectionHeading label="Bookings" title="Submitted reservations" />
              {(payload?.bookings ?? []).length === 0 ? (
                <Text style={styles.mutedText}>No reservations yet. Start one from the Booking tab.</Text>
              ) : (
                <View style={styles.bookingStack}>
                  {payload?.bookings.map((booking) => (
                    <View key={booking.id} style={styles.bookingCard}>
                      <View style={styles.bookingHeader}>
                        <View style={{ flex: 1, gap: 4 }}>
                          <Text style={styles.bookingReference}>{booking.booking_reference}</Text>
                          <Text style={styles.bookingTitle}>{booking.package_name}</Text>
                          <Text style={styles.bookingMeta}>
                            {booking.branch} · {booking.event_date} · {booking.event_time}
                          </Text>
                        </View>
                        <Tag label={booking.status.replace('_', ' ')} active={statusTone[booking.status] ?? false} />
                      </View>

                      <View style={styles.receiptBox}>
                        {booking.receipt.line_items.slice(0, 3).map((line) => (
                          <View key={`${booking.id}-${line.label}`} style={styles.receiptRow}>
                            <Text style={styles.receiptLabel}>{line.label}</Text>
                            <Text style={styles.receiptValue}>PHP {line.amount}</Text>
                          </View>
                        ))}
                        <View style={[styles.receiptRow, styles.receiptRowStrong]}>
                          <Text style={styles.receiptStrongLabel}>Total</Text>
                          <Text style={styles.receiptStrongValue}>PHP {booking.receipt.total}</Text>
                        </View>
                      </View>

                      {['pending_review', 'confirmed', 'rescheduled'].includes(booking.status) ? (
                        <View style={styles.actionBox}>
                          <Text style={styles.actionTitle}>Reservation actions</Text>
                          <Field
                            label="New date (YYYY-MM-DD)"
                            value={rescheduleForms[booking.id]?.event_date ?? booking.event_date}
                            onChangeText={(value) => setRescheduleValue(booking.id, 'event_date', value)}
                          />
                          <Field
                            label="New start time (HH:MM)"
                            value={rescheduleForms[booking.id]?.event_time ?? booking.event_start_time}
                            onChangeText={(value) => setRescheduleValue(booking.id, 'event_time', value)}
                          />
                          <View style={styles.actionButtons}>
                            <AppButton label="Reschedule" onPress={() => void handleReschedule(booking.id)} tone="secondary" />
                            <AppButton label="Cancel booking" onPress={() => void handleCancel(booking.id)} tone="ghost" />
                          </View>
                        </View>
                      ) : null}
                    </View>
                  ))}
                </View>
              )}
            </Panel>
          </>
        )}
      </ScrollView>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  mutedText: {
    color: palette.inkMuted,
    lineHeight: 21,
    fontSize: 15,
  },
  errorPanel: {
    borderColor: '#F2B5AA',
    backgroundColor: '#FFF4F2',
  },
  errorText: {
    color: palette.brandRedDark,
    lineHeight: 20,
  },
  buttonStack: {
    gap: 12,
  },
  statGrid: {
    gap: 12,
  },
  statCard: {
    minHeight: 110,
  },
  statLabel: {
    color: palette.inkMuted,
    fontWeight: '700',
    fontSize: 13,
  },
  statValue: {
    color: palette.ink,
    fontWeight: '900',
    fontSize: 28,
  },
  bookingStack: {
    gap: 14,
  },
  bookingCard: {
    backgroundColor: '#FFF8EA',
    borderRadius: 20,
    padding: 16,
    gap: 14,
  },
  bookingHeader: {
    flexDirection: 'row',
    gap: 12,
  },
  bookingReference: {
    color: palette.brandRed,
    fontWeight: '900',
    fontSize: 13,
    letterSpacing: 0.6,
  },
  bookingTitle: {
    color: palette.ink,
    fontSize: 20,
    fontWeight: '900',
  },
  bookingMeta: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  receiptBox: {
    gap: 8,
    backgroundColor: palette.surfaceStrong,
    borderRadius: 16,
    padding: 14,
  },
  receiptRow: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  receiptRowStrong: {
    marginTop: 4,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: palette.border,
  },
  receiptLabel: {
    flex: 1,
    color: palette.inkMuted,
    lineHeight: 19,
  },
  receiptValue: {
    color: palette.ink,
    fontWeight: '700',
  },
  receiptStrongLabel: {
    color: palette.ink,
    fontWeight: '900',
  },
  receiptStrongValue: {
    color: palette.brandRed,
    fontWeight: '900',
  },
  actionBox: {
    gap: 12,
  },
  actionTitle: {
    color: palette.ink,
    fontWeight: '900',
    fontSize: 16,
  },
  actionButtons: {
    gap: 10,
  },
});
