import { router, useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Image, KeyboardAvoidingView, Linking, Modal, Platform, RefreshControl, StyleSheet, Text, View } from 'react-native';

import {
  CustomerButton,
  CustomerCard,
  CustomerChip,
  CustomerField,
  CustomerHeader,
  CustomerPage,
  McLogo,
  MetricTile,
  SectionEyebrow,
  SectionTitle,
  SheetSurface,
} from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import {
  ApiError,
  cancelReservation,
  cancelReservationInvitation,
  createReservationInvitation,
  fetchDashboard,
  getReservationPassUrl,
  getReservationPaymentProofUrl,
  resolveApiUrl,
  respondToReservationInvitation,
  rescheduleReservation,
} from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { readCacheEnvelope, writeCache } from '@/lib/cache';
import { formatLongDate, formatStatusLabel, formatTimeLabel } from '@/lib/formatters';
import { useAppPreferences } from '@/lib/preferences';
import { AppThemeColors, useAppTheme } from '@/lib/theme';
import type { DashboardPayload, EventInvitation } from '@/lib/types';

const metricIcons: Record<string, 'calendar-clock' | 'cash-multiple' | 'check-decagram'> = {
  Upcoming: 'calendar-clock',
  'Confirmed spend': 'cash-multiple',
  'Pending approvals': 'check-decagram',
};
const dashboardRefreshIntervalMs = 1000 * 30;

function statusTone(status: string): 'yellow' | 'green' | 'pink' | 'neutral' {
  if (['confirmed', 'checked_in', 'completed'].includes(status)) {
    return 'green';
  }

  if (status === 'cancelled') {
    return 'neutral';
  }

  if (status === 'rescheduled') {
    return 'pink';
  }

  return 'yellow';
}

const emptyInvitationForm = { name: '', phone: '', email: '' };

function invitationTone(status: EventInvitation['status']): 'yellow' | 'green' | 'pink' | 'neutral' {
  if (status === 'accepted') return 'green';
  if (status === 'declined' || status === 'revoked') return 'neutral';
  return 'yellow';
}

export default function DashboardScreen() {
  const { colors } = useAppTheme();
  const styles = useMemo(() => createStyles(colors), [colors]);
  const { notifyNewInvitations, preferences, syncReservationReminders } = useAppPreferences();
  const { user, token, booting } = useAuth();
  const [payload, setPayload] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [rescheduleForms, setRescheduleForms] = useState<Record<number, { event_date: string; event_time: string }>>({});
  const [inviteReservationId, setInviteReservationId] = useState<number | null>(null);
  const [invitationForm, setInvitationForm] = useState(emptyInvitationForm);
  const [sendingInvitation, setSendingInvitation] = useState(false);
  const [answeringInvitationId, setAnsweringInvitationId] = useState<number | null>(null);
  const hasPayloadRef = useRef(false);
  const lastLoadedAtRef = useRef(0);
  const dashboardCacheKey = user ? `mobile-cache:dashboard:${user.id}` : null;

  const loadDashboard = useCallback(async (nextRefreshing = false) => {
    if (!token) {
      return;
    }

    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else if (!hasPayloadRef.current) {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchDashboard(token);
      hasPayloadRef.current = true;
      lastLoadedAtRef.current = Date.now();
      setPayload(response);
      void syncReservationReminders(response.bookings).catch(() => undefined);
      void notifyNewInvitations(response.receivedInvitations ?? []).catch(() => undefined);
      if (dashboardCacheKey) {
        await writeCache(dashboardCacheKey, response);
      }
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
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load your bookings right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [dashboardCacheKey, notifyNewInvitations, syncReservationReminders, token]);

  useEffect(() => {
    let active = true;

    if (!dashboardCacheKey) {
      return;
    }

    void (async () => {
      const cachedPayload = await readCacheEnvelope<DashboardPayload>(dashboardCacheKey, 1000 * 60 * 10);

      if (active && cachedPayload) {
        hasPayloadRef.current = true;
        lastLoadedAtRef.current = cachedPayload.savedAt;
        setPayload(cachedPayload.data);
        void syncReservationReminders(cachedPayload.data.bookings).catch(() => undefined);
        void notifyNewInvitations(cachedPayload.data.receivedInvitations ?? []).catch(() => undefined);
        setLoading(false);
        setRescheduleForms(
          Object.fromEntries(
            cachedPayload.data.bookings.map((booking) => [
              booking.id,
              {
                event_date: booking.event_date,
                event_time: booking.event_start_time,
              },
            ]),
          ),
        );
      }
    })();

    return () => {
      active = false;
    };
  }, [dashboardCacheKey, notifyNewInvitations, syncReservationReminders]);

  useFocusEffect(
    useCallback(() => {
      if (token && Date.now() - lastLoadedAtRef.current > dashboardRefreshIntervalMs) {
        void loadDashboard();
      }
    }, [loadDashboard, token]),
  );

  async function handleCancel(reservationId: number) {
    try {
      const response = await cancelReservation(token!, reservationId);
      Alert.alert('Booking updated', response.message);
      await loadDashboard();
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to cancel this booking right now.';
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

  async function handleSendInvitation() {
    if (!inviteReservationId || !token) return;

    try {
      setSendingInvitation(true);
      const response = await createReservationInvitation(token, inviteReservationId, {
        name: invitationForm.name.trim(),
        phone: invitationForm.phone.trim(),
        email: invitationForm.email.trim() || undefined,
      });
      setInviteReservationId(null);
      setInvitationForm(emptyInvitationForm);
      if (response.sms_queued) {
        Alert.alert('Invitation sent', response.message);
      } else {
        const smsBody = `You're invited to ${response.invitation.package_name ?? 'a McDonald\'s event'} at ${response.invitation.branch ?? 'McDonald\'s'} on ${response.invitation.event_date ?? 'the scheduled date'} at ${response.invitation.event_time_label ?? 'the scheduled time'}. Open McD Reservations to reply. Ref ${response.invitation.booking_reference ?? ''}`;
        const smsUrl = `sms:${response.invitation.invitee_phone}?body=${encodeURIComponent(smsBody)}`;

        Alert.alert('Invitation saved', `${response.message} You can send it from your phone instead.`, [
          { text: 'Not now', style: 'cancel' },
          { text: 'Open Messages', onPress: () => void Linking.openURL(smsUrl) },
        ]);
      }
      await loadDashboard();
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to send the invitation.';
      Alert.alert('Invitation failed', message);
    } finally {
      setSendingInvitation(false);
    }
  }

  async function handleInvitationResponse(invitationId: number, status: 'accepted' | 'declined') {
    if (!token) return;

    try {
      setAnsweringInvitationId(invitationId);
      const response = await respondToReservationInvitation(token, invitationId, status);
      Alert.alert(status === 'accepted' ? 'Invitation accepted' : 'Invitation declined', response.message);
      await loadDashboard();
    } catch (error) {
      const message = error instanceof ApiError ? error.message : 'Unable to answer this invitation.';
      Alert.alert('Response failed', message);
    } finally {
      setAnsweringInvitationId(null);
    }
  }

  function handleCancelInvitation(reservationId: number, invitationId: number) {
    Alert.alert('Cancel invitation?', 'The customer will no longer be able to accept this invitation.', [
      { text: 'Keep invitation', style: 'cancel' },
      {
        text: 'Cancel invitation',
        style: 'destructive',
        onPress: () => {
          void (async () => {
            try {
              const response = await cancelReservationInvitation(token!, reservationId, invitationId);
              Alert.alert('Invitation updated', response.message);
              await loadDashboard();
            } catch (error) {
              Alert.alert('Unable to cancel', error instanceof ApiError ? error.message : 'Please try again.');
            }
          })();
        },
      },
    ]);
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
      <CustomerPage contentContainerStyle={styles.pageContent}>
        <CustomerHeader title="My Dashboard" subtitle="Loading your customer session..." rightSlot={<McLogo />} />
        <SheetSurface>
          <CustomerCard>
            <Text style={styles.helperText}>Preparing your live booking dashboard...</Text>
          </CustomerCard>
        </SheetSurface>
      </CustomerPage>
    );
  }

  if (!user || !token) {
    return (
      <CustomerPage contentContainerStyle={styles.pageContent}>
        <CustomerHeader
          title="My Dashboard"
          subtitle="Booking, payments, and event details."
          rightSlot={<McLogo />}
        />
        <SheetSurface>
          <CustomerCard>
            <SectionEyebrow>Customer access</SectionEyebrow>
            <SectionTitle>Sign in to track your reservations</SectionTitle>
            <Text style={styles.helperText}>The dashboard reads the same reservation records your admin reviews and updates from Laravel.</Text>
            <View style={styles.buttonStack}>
              <CustomerButton label="Sign in" onPress={() => router.push('/login')} />
              <CustomerButton label="Create account" onPress={() => router.push('/register')} tone="secondary" />
            </View>
          </CustomerCard>
        </SheetSurface>
      </CustomerPage>
    );
  }

  return (
    <CustomerPage
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadDashboard(true)} tintColor={colors.brandText} />}
      contentContainerStyle={styles.pageContent}>
      <CustomerHeader title="My Dashboard" subtitle="Booking, payments, and event details." rightSlot={<McLogo />} />
      <SheetSurface>
        <View style={styles.toolbarRow}>
          <Text style={styles.toolbarTitle}>Dashboard</Text>
          <View style={styles.toolbarButtons}>
            <CustomerButton label="Refresh" onPress={() => void loadDashboard()} tone="secondary" compact />
            <CustomerButton label="Add Booking" onPress={() => router.push('/(tabs)/booking')} icon="plus" compact />
          </View>
        </View>

        {errorMessage ? (
          <CustomerCard tone="pink">
            <SectionEyebrow>Connection issue</SectionEyebrow>
            <SectionTitle>Dashboard data is unavailable</SectionTitle>
            <Text style={styles.helperText}>{errorMessage}</Text>
            <CustomerButton label="Reload dashboard" onPress={() => void loadDashboard()} tone="ghost" />
          </CustomerCard>
        ) : null}

        {loading && !payload ? <Text style={styles.helperText}>Loading your reservation history...</Text> : null}

        <View style={styles.metricRow}>
          {(payload?.stats ?? []).map((stat) => (
            <MetricTile key={stat.label} icon={metricIcons[stat.label] ?? 'calendar-clock'} label={stat.label} value={stat.value} />
          ))}
        </View>

        {(payload?.receivedInvitations ?? []).length > 0 ? (
          <CustomerCard tone="pink" style={styles.invitationInbox}>
            <SectionEyebrow>Event invitations</SectionEyebrow>
            <SectionTitle>Invitations for you</SectionTitle>
            {(payload?.receivedInvitations ?? []).map((invitation) => (
              <View key={invitation.id} style={styles.invitationRow}>
                <View style={styles.invitationHeader}>
                  <View style={styles.invitationCopy}>
                    <Text style={styles.invitationTitle}>{invitation.inviter_name ?? 'A customer'} invited you</Text>
                    <Text style={styles.invitationMeta}>{invitation.package_name ?? invitation.event_type ?? 'McDonald\'s event'}</Text>
                    <Text style={styles.invitationMeta}>{invitation.branch}</Text>
                    {invitation.event_date ? (
                      <Text style={styles.invitationMeta}>
                        {formatLongDate(invitation.event_date, preferences.dateFormat, preferences.language)} at {invitation.event_time_label}
                      </Text>
                    ) : null}
                  </View>
                  <CustomerChip label={formatStatusLabel(invitation.status)} tone={invitationTone(invitation.status)} />
                </View>
                {invitation.status === 'pending' ? (
                  <View style={styles.invitationActions}>
                    <CustomerButton
                      label="Accept"
                      icon="check"
                      compact
                      disabled={answeringInvitationId === invitation.id}
                      onPress={() => void handleInvitationResponse(invitation.id, 'accepted')}
                    />
                    <CustomerButton
                      label="Decline"
                      icon="close"
                      compact
                      tone="secondary"
                      disabled={answeringInvitationId === invitation.id}
                      onPress={() => void handleInvitationResponse(invitation.id, 'declined')}
                    />
                  </View>
                ) : null}
              </View>
            ))}
          </CustomerCard>
        ) : null}

        <CustomerCard tone="cream" style={styles.summaryCard}>
          <SectionTitle>Upcoming</SectionTitle>
          {(payload?.bookings ?? []).length === 0 ? (
            <View style={styles.emptyWrap}>
              <View style={styles.emptyIcon}>
                <Text style={styles.emptyCalendar}>17</Text>
              </View>
              <Text style={styles.emptyTitle}>No Booking yet.</Text>
              <Text style={styles.emptyText}>Create your first reservation from the Book tab and your admin updates will appear here live.</Text>
            </View>
          ) : (
            <View style={styles.bookingStack}>
              {payload?.bookings.map((booking) => (
                <CustomerCard key={booking.id} style={styles.bookingCard}>
                  <View style={styles.bookingHeader}>
                    <View style={{ flex: 1, gap: 4 }}>
                      <Text style={styles.bookingReference}>{booking.booking_reference}</Text>
                      <Text style={styles.bookingTitle}>{booking.package_name}</Text>
                      <Text style={styles.bookingMeta}>{booking.branch}</Text>
                      <Text style={styles.bookingMeta}>
                        {formatLongDate(booking.event_date, preferences.dateFormat, preferences.language)} -{' '}
                        {preferences.timeFormat === '24-hour'
                          ? formatTimeLabel(booking.event_start_time, '24-hour')
                          : booking.event_start_label ?? formatTimeLabel(booking.event_start_time)}{' '}
                        to{' '}
                        {preferences.timeFormat === '24-hour'
                          ? formatTimeLabel(booking.event_end_time, '24-hour')
                          : booking.event_end_label ?? formatTimeLabel(booking.event_end_time)}
                      </Text>
                    </View>
                    <CustomerChip label={formatStatusLabel(booking.status)} tone={statusTone(booking.status)} />
                  </View>

                  <View style={styles.receiptBox}>
                    {booking.receipt.line_items.slice(0, 4).map((line) => (
                      <View key={`${booking.id}-${line.label}`} style={styles.receiptRow}>
                        <Text style={styles.receiptLabel}>{line.label}</Text>
                        <Text style={styles.receiptValue}>{line.amount}</Text>
                      </View>
                    ))}
                    <View style={[styles.receiptRow, styles.receiptTotalRow]}>
                      <Text style={styles.receiptTotalLabel}>Total</Text>
                      <Text style={styles.receiptTotalValue}>{booking.receipt.total}</Text>
                    </View>
                  </View>

                  <View style={styles.reservationDetails}>
                    <Text style={styles.detailLabel}>Guests</Text>
                    <Text style={styles.detailValue}>{booking.guests}</Text>
                    <Text style={styles.detailLabel}>Check-in code</Text>
                    <Text style={styles.detailValue}>{booking.check_in_code || 'Available after confirmation'}</Text>
                  </View>

                  {booking.can_show_qr_pass ? (
                    <View style={styles.mediaPanel}>
                      <SectionEyebrow>Event start QR pass</SectionEyebrow>
                      <Image
                        source={{
                          uri: getReservationPassUrl(booking.id),
                          headers: { Authorization: `Bearer ${token}` },
                        }}
                        style={styles.qrImage}
                        resizeMode="contain"
                      />
                      <Text style={styles.mediaHelp}>
                        Show this pass to staff when the party is ready. A successful scan marks the event as ongoing for admin.
                      </Text>
                      {booking.qr_check_in_url ? (
                        <CustomerButton
                          label={booking.status === 'checked_in' ? 'Event is ongoing' : 'Start on this device'}
                          onPress={() => {
                            const url = resolveApiUrl(booking.qr_check_in_url);
                            if (url) void Linking.openURL(url);
                          }}
                          tone="secondary"
                          disabled={booking.status === 'checked_in'}
                        />
                      ) : null}
                    </View>
                  ) : booking.status === 'pending_review' ? (
                    <Text style={styles.pendingPass}>Your QR pass will appear after the booking is confirmed.</Text>
                  ) : null}

                  {booking.payment_proof_preview_url ? (
                    <View style={styles.mediaPanel}>
                      <SectionEyebrow>Proof of payment</SectionEyebrow>
                      <Image
                        source={{
                          uri: getReservationPaymentProofUrl(booking.id),
                          headers: { Authorization: `Bearer ${token}` },
                        }}
                        style={styles.proofImage}
                        resizeMode="cover"
                      />
                    </View>
                  ) : null}

                  {['pending_review', 'confirmed', 'rescheduled'].includes(booking.status) ? (
                    <View style={styles.guestInvitePanel}>
                      <View style={styles.invitationHeader}>
                        <View style={styles.invitationCopy}>
                          <SectionEyebrow>Guest invitations</SectionEyebrow>
                          <Text style={styles.invitationMeta}>Invite a customer by SMS and let them RSVP in the app.</Text>
                        </View>
                        <CustomerButton
                          label="Invite"
                          icon="account-plus"
                          compact
                          onPress={() => {
                            setInvitationForm(emptyInvitationForm);
                            setInviteReservationId(booking.id);
                          }}
                        />
                      </View>

                      {(booking.invitations ?? []).map((invitation) => (
                        <View key={invitation.id} style={styles.sentInvitationRow}>
                          <View style={styles.invitationCopy}>
                            <Text style={styles.sentInvitationName}>{invitation.invitee_name}</Text>
                            <Text style={styles.invitationMeta}>{invitation.invitee_phone}</Text>
                          </View>
                          <CustomerChip label={formatStatusLabel(invitation.status)} tone={invitationTone(invitation.status)} />
                          {invitation.status === 'pending' ? (
                            <CustomerButton
                              label="Cancel"
                              compact
                              tone="ghost"
                              onPress={() => handleCancelInvitation(booking.id, invitation.id)}
                            />
                          ) : null}
                        </View>
                      ))}
                    </View>
                  ) : null}

                  <Text style={styles.adminSyncText}>This reservation stays in sync with your admin review and database updates.</Text>

                  {['pending_review', 'confirmed', 'rescheduled'].includes(booking.status) ? (
                    <View style={styles.actionBox}>
                      <CustomerField
                        label="New date"
                        value={rescheduleForms[booking.id]?.event_date ?? booking.event_date}
                        onChangeText={(value) => setRescheduleValue(booking.id, 'event_date', value)}
                        placeholder="YYYY-MM-DD"
                      />
                      <CustomerField
                        label="New time"
                        value={rescheduleForms[booking.id]?.event_time ?? booking.event_start_time}
                        onChangeText={(value) => setRescheduleValue(booking.id, 'event_time', value)}
                        placeholder="HH:MM"
                      />
                      <View style={styles.buttonStack}>
                        <CustomerButton label="Reschedule" onPress={() => void handleReschedule(booking.id)} tone="secondary" />
                        <CustomerButton label="Cancel Booking" onPress={() => void handleCancel(booking.id)} tone="danger" />
                      </View>
                    </View>
                  ) : null}
                </CustomerCard>
              ))}
            </View>
          )}
        </CustomerCard>
      </SheetSurface>

      <Modal
        visible={inviteReservationId !== null}
        transparent
        animationType="fade"
        onRequestClose={() => !sendingInvitation && setInviteReservationId(null)}>
        <KeyboardAvoidingView
          style={styles.modalBackdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={styles.modalPanel}>
            <SectionEyebrow>Invite a customer</SectionEyebrow>
            <SectionTitle>Send an event invitation</SectionTitle>
            <Text style={styles.helperText}>They will receive an SMS and can accept or decline from their customer account.</Text>
            <CustomerField
              label="Customer name"
              value={invitationForm.name}
              onChangeText={(name) => setInvitationForm((current) => ({ ...current, name }))}
              placeholder="Guest name"
              autoCapitalize="words"
            />
            <CustomerField
              label="Philippine mobile number"
              value={invitationForm.phone}
              onChangeText={(phone) => setInvitationForm((current) => ({ ...current, phone }))}
              placeholder="0917 123 4567"
              keyboardType="phone-pad"
            />
            <CustomerField
              label="Email (optional)"
              value={invitationForm.email}
              onChangeText={(email) => setInvitationForm((current) => ({ ...current, email }))}
              placeholder="customer@example.com"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
            />
            <View style={styles.modalActions}>
              <CustomerButton
                label="Close"
                tone="secondary"
                disabled={sendingInvitation}
                onPress={() => setInviteReservationId(null)}
              />
              <CustomerButton
                label={sendingInvitation ? 'Sending...' : 'Send invitation'}
                icon="send"
                disabled={sendingInvitation || !invitationForm.name.trim() || !invitationForm.phone.trim()}
                onPress={() => void handleSendInvitation()}
              />
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </CustomerPage>
  );
}

const createStyles = (colors: AppThemeColors) => StyleSheet.create({
  pageContent: {
    gap: 0,
  },
  helperText: {
    color: colors.textMuted,
    fontSize: 14,
    lineHeight: 20,
  },
  buttonStack: {
    gap: 10,
  },
  toolbarRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  toolbarTitle: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '700',
  },
  toolbarButtons: {
    flexDirection: 'row',
    gap: 8,
  },
  metricRow: {
    flexDirection: 'row',
    gap: 10,
  },
  summaryCard: {
    gap: 14,
  },
  invitationInbox: {
    gap: 12,
  },
  invitationRow: {
    gap: 10,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  invitationHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 10,
  },
  invitationCopy: {
    flex: 1,
    gap: 3,
  },
  invitationTitle: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '900',
  },
  invitationMeta: {
    color: colors.textMuted,
    fontSize: 13,
    lineHeight: 18,
  },
  invitationActions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  emptyWrap: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    minHeight: 240,
  },
  emptyIcon: {
    width: 64,
    height: 64,
    borderRadius: 16,
    backgroundColor: colors.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
    borderTopWidth: 10,
    borderTopColor: palette.brandYellow,
  },
  emptyCalendar: {
    color: colors.textSubtle,
    fontWeight: '900',
    fontSize: 20,
  },
  emptyTitle: {
    color: colors.text,
    fontSize: 22,
    fontWeight: '900',
  },
  emptyText: {
    color: colors.textMuted,
    textAlign: 'center',
    lineHeight: 20,
    maxWidth: 260,
  },
  bookingStack: {
    gap: 14,
  },
  bookingCard: {
    backgroundColor: colors.surface,
  },
  bookingHeader: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-start',
  },
  bookingReference: {
    color: colors.brandText,
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0.6,
  },
  bookingTitle: {
    color: colors.text,
    fontSize: 20,
    fontWeight: '900',
  },
  bookingMeta: {
    color: colors.textMuted,
    lineHeight: 19,
    fontSize: 13,
  },
  receiptBox: {
    borderRadius: 16,
    backgroundColor: colors.surfaceMuted,
    padding: 12,
    gap: 8,
  },
  reservationDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    alignItems: 'center',
  },
  detailLabel: { color: colors.textMuted, fontSize: 12, fontWeight: '800' },
  detailValue: { color: colors.text, fontSize: 13, fontWeight: '900', marginRight: 10 },
  mediaPanel: {
    gap: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    paddingTop: 14,
  },
  qrImage: {
    width: '100%',
    maxWidth: 280,
    aspectRatio: 1,
    alignSelf: 'center',
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
  },
  proofImage: {
    width: '100%',
    height: 220,
    borderRadius: 8,
    backgroundColor: colors.surfaceMuted,
  },
  mediaHelp: { color: colors.textMuted, fontSize: 13, lineHeight: 19 },
  pendingPass: {
    color: colors.text,
    backgroundColor: colors.warningSurface,
    borderLeftWidth: 4,
    borderLeftColor: palette.brandYellow,
    padding: 12,
    lineHeight: 19,
  },
  receiptRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
  },
  receiptLabel: {
    color: colors.textMuted,
    flex: 1,
    lineHeight: 18,
    fontSize: 13,
  },
  receiptValue: {
    color: colors.text,
    fontWeight: '700',
    fontSize: 13,
  },
  receiptTotalRow: {
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  receiptTotalLabel: {
    color: colors.text,
    fontWeight: '900',
    fontSize: 14,
  },
  receiptTotalValue: {
    color: colors.brandText,
    fontWeight: '900',
    fontSize: 14,
  },
  adminSyncText: {
    color: colors.textMuted,
    fontSize: 12,
    lineHeight: 18,
  },
  actionBox: {
    gap: 10,
  },
  guestInvitePanel: {
    gap: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    paddingTop: 14,
  },
  sentInvitationRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  sentInvitationName: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '900',
  },
  modalBackdrop: {
    flex: 1,
    justifyContent: 'center',
    padding: 20,
    backgroundColor: 'rgba(20, 12, 6, 0.58)',
  },
  modalPanel: {
    width: '100%',
    maxWidth: 520,
    alignSelf: 'center',
    maxHeight: '92%',
    gap: 12,
    padding: 18,
    borderRadius: 8,
    backgroundColor: colors.surface,
  },
  modalActions: {
    gap: 8,
  },
});
