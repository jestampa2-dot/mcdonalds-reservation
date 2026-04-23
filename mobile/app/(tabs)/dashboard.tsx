import { router } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { fetchDashboard } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { DashboardPayload } from '@/lib/types';

const statusTone: Record<string, boolean> = {
  confirmed: true,
  checked_in: true,
  pending_review: false,
  rescheduled: false,
  cancelled: false,
};

export default function DashboardScreen() {
  const { user, token, signOut, booting } = useAuth();
  const [payload, setPayload] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

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

      const response = await fetchDashboard(token);
      setPayload(response);
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
});
