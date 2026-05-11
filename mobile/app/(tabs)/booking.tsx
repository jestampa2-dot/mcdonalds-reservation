import * as ImagePicker from 'expo-image-picker';
import { router } from 'expo-router';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Image,
  Linking,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';

import {
  CustomerButton,
  CustomerCard,
  CustomerChip,
  CustomerField,
  CustomerHeader,
  CustomerPage,
  HeaderIconButton,
  McLogo,
  SectionEyebrow,
  SectionTitle,
  SheetSurface,
} from '@/components/customer-ui';
import { palette } from '@/constants/palette';
import { ApiError, createReservation, fetchBookingOptions } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { readCacheEnvelope, writeCache } from '@/lib/cache';
import { addHoursToTime, formatCurrency, formatLongDate, formatMonthLabel, formatTimeLabel } from '@/lib/formatters';
import type { AvailabilityDate, BookingOptionsPayload } from '@/lib/types';

const bookingOptionsCacheKey = 'mobile-cache:booking-options';
const bookingOptionsCacheTtlMs = 1000 * 60 * 30;

type PickedImage = {
  uri: string;
  name: string;
  type: string;
};

type ManualSelection = {
  option_code: string;
  quantity: number;
};

function canStartAtTime(time: string, dateAvailability: AvailabilityDate | null, durationHours: number) {
  if (!dateAvailability) {
    return false;
  }

  const slotMap = Object.fromEntries(dateAvailability.slots.map((slot) => [slot.time, slot]));

  for (let offset = 0; offset < durationHours; offset += 1) {
    const slot = slotMap[addHoursToTime(time, offset)];
    if (!slot || slot.full) {
      return false;
    }
  }

  return true;
}

function monthFromDate(date: string) {
  return date.slice(0, 7);
}

export default function BookingScreen() {
  const { token, user } = useAuth();
  const [payload, setPayload] = useState<BookingOptionsPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [paymentProof, setPaymentProof] = useState<PickedImage | null>(null);
  const [notes, setNotes] = useState('');
  const [guests, setGuests] = useState('10');
  const [eventType, setEventType] = useState('birthday');
  const [branchCode, setBranchCode] = useState('');
  const [eventDate, setEventDate] = useState('');
  const [calendarMonth, setCalendarMonth] = useState('');
  const [eventTime, setEventTime] = useState('');
  const [durationHours, setDurationHours] = useState(4);
  const [roomChoice, setRoomChoice] = useState('');
  const [packageCode, setPackageCode] = useState('');
  const [menuBundles, setMenuBundles] = useState<string[]>([]);
  const [addOns, setAddOns] = useState<string[]>([]);
  const [manualSelections, setManualSelections] = useState<ManualSelection[]>([]);
  const hasPayloadRef = useRef(false);
  const { width } = useWindowDimensions();
  const isWide = width >= 760;

  const loadBookingOptions = useCallback(async (nextRefreshing = false) => {
    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else if (!hasPayloadRef.current) {
        setLoading(true);
      }

      setErrorMessage('');
      const response = await fetchBookingOptions();
      const eventTypes = Object.keys(response.catalog.eventTypes);
      const nextEventType = eventTypes[0] ?? 'birthday';
      const supportedBranches = Object.values(response.catalog.branches).filter((branch) => branch.supports[nextEventType]);
      const nextBranch = supportedBranches[0]?.code ?? Object.keys(response.catalog.branches)[0] ?? '';
      const nextPackages = response.catalog.packages[nextEventType] ?? [];

      hasPayloadRef.current = true;
      setPayload(response);
      await writeCache(bookingOptionsCacheKey, response);
      setEventType(nextEventType);
      setBranchCode(nextBranch);
      setEventDate(response.defaults.event_date);
      setCalendarMonth(monthFromDate(response.defaults.event_date));
      setEventTime(response.defaults.event_time);
      setDurationHours(response.defaults.duration_hours);
      setRoomChoice(response.defaults.room_choice);
      setPackageCode(nextPackages[0]?.code ?? '');
      setMenuBundles(response.catalog.menuBundles[0] ? [response.catalog.menuBundles[0].code] : []);
      setAddOns([]);
      setManualSelections([]);
      setNotes('');
      setGuests('10');
      setPaymentProof(null);
      setActiveCategory(response.catalog.menuCategories[0]?.code ?? null);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load booking options right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    void (async () => {
      const cachedPayload = await readCacheEnvelope<BookingOptionsPayload>(bookingOptionsCacheKey, bookingOptionsCacheTtlMs);

      if (!active || !cachedPayload) {
        void loadBookingOptions();
        return;
      }

      const eventTypes = Object.keys(cachedPayload.data.catalog.eventTypes);
      const nextEventType = eventTypes[0] ?? 'birthday';
      const supportedCachedBranches = Object.values(cachedPayload.data.catalog.branches).filter((branch) => branch.supports[nextEventType]);
      const nextBranch = supportedCachedBranches[0]?.code ?? Object.keys(cachedPayload.data.catalog.branches)[0] ?? '';
      const nextPackages = cachedPayload.data.catalog.packages[nextEventType] ?? [];

      hasPayloadRef.current = true;
      setPayload(cachedPayload.data);
      setEventType(nextEventType);
      setBranchCode(nextBranch);
      setEventDate(cachedPayload.data.defaults.event_date);
      setCalendarMonth(monthFromDate(cachedPayload.data.defaults.event_date));
      setEventTime(cachedPayload.data.defaults.event_time);
      setDurationHours(cachedPayload.data.defaults.duration_hours);
      setRoomChoice(cachedPayload.data.defaults.room_choice);
      setPackageCode(nextPackages[0]?.code ?? '');
      setMenuBundles(cachedPayload.data.catalog.menuBundles[0] ? [cachedPayload.data.catalog.menuBundles[0].code] : []);
      setAddOns([]);
      setManualSelections([]);
      setNotes('');
      setGuests('10');
      setPaymentProof(null);
      setActiveCategory(cachedPayload.data.catalog.menuCategories[0]?.code ?? null);
      setLoading(false);
    })();

    return () => {
      active = false;
    };
  }, [loadBookingOptions]);

  const supportedBranches = useMemo(() => {
    if (!payload) {
      return [];
    }

    return Object.values(payload.catalog.branches).filter((branch) => branch.supports[eventType]);
  }, [eventType, payload]);

  useEffect(() => {
    if (!supportedBranches.some((branch) => branch.code === branchCode)) {
      setBranchCode(supportedBranches[0]?.code ?? '');
    }
  }, [branchCode, supportedBranches]);

  const selectedBranch = useMemo(
    () => supportedBranches.find((branch) => branch.code === branchCode) ?? supportedBranches[0] ?? null,
    [branchCode, supportedBranches],
  );

  const branchAvailability = useMemo(() => {
    return payload?.availability.branches.find((branch) => branch.code === branchCode) ?? null;
  }, [branchCode, payload]);

  const dateCards = useMemo(() => branchAvailability?.dates ?? [], [branchAvailability]);
  const monthOptions = useMemo(() => Array.from(new Set(dateCards.map((item) => monthFromDate(item.date)))), [dateCards]);
  const selectedDateAvailability = useMemo(
    () => dateCards.find((item) => item.date === eventDate) ?? dateCards[0] ?? null,
    [dateCards, eventDate],
  );

  useEffect(() => {
    if (!selectedDateAvailability) {
      return;
    }

    if (!dateCards.some((item) => item.date === eventDate)) {
      setEventDate(selectedDateAvailability.date);
    }

    const nextMonth = monthFromDate(selectedDateAvailability.date);
    if (nextMonth !== calendarMonth) {
      setCalendarMonth(nextMonth);
    }
  }, [calendarMonth, dateCards, eventDate, selectedDateAvailability]);

  useEffect(() => {
    if (!calendarMonth && monthOptions[0]) {
      setCalendarMonth(monthOptions[0]);
    }
  }, [calendarMonth, monthOptions]);

  const startSlots = useMemo(
    () => (selectedDateAvailability?.slots ?? []).filter((slot) => canStartAtTime(slot.time, selectedDateAvailability, durationHours)),
    [durationHours, selectedDateAvailability],
  );

  const allowedDurations = useMemo(() => {
    if (!selectedDateAvailability) {
      return [];
    }

    return Array.from({ length: 16 }, (_, index) => index + 1).filter((duration) =>
      canStartAtTime(eventTime, selectedDateAvailability, duration),
    );
  }, [eventTime, selectedDateAvailability]);

  useEffect(() => {
    if (!startSlots.some((slot) => slot.time === eventTime)) {
      setEventTime(startSlots[0]?.time ?? payload?.defaults.event_time ?? '10:00');
    }
  }, [eventTime, payload?.defaults.event_time, startSlots]);

  useEffect(() => {
    if (!allowedDurations.includes(durationHours)) {
      setDurationHours(allowedDurations[0] ?? payload?.defaults.duration_hours ?? 4);
    }
  }, [allowedDurations, durationHours, payload?.defaults.duration_hours]);

  const packages = useMemo(() => payload?.catalog.packages[eventType] ?? [], [eventType, payload]);

  useEffect(() => {
    if (!packages.some((item) => item.code === packageCode)) {
      setPackageCode(packages[0]?.code ?? '');
    }
  }, [packageCode, packages]);

  const roomChoices = useMemo(() => payload?.roomChoices ?? [], [payload]);

  useEffect(() => {
    if (!roomChoices.some((choice) => choice.code === roomChoice)) {
      setRoomChoice(roomChoices[0]?.code ?? '');
    }
  }, [roomChoice, roomChoices]);

  const manualOptionIndex = useMemo(() => {
    return (payload?.catalog.menuCategories ?? [])
      .flatMap((category) =>
        category.items.flatMap((item) =>
          item.options.map((option) => ({
            categoryCode: category.code,
            itemCode: item.code,
            itemName: item.name,
            optionCode: option.code,
            optionLabel: option.label,
            price: option.price,
          })),
        ),
      )
      .reduce<Record<string, { categoryCode: string; itemCode: string; itemName: string; optionCode: string; optionLabel: string; price: number }>>((carry, option) => {
        carry[option.optionCode] = option;
        return carry;
      }, {});
  }, [payload]);

  const selectedPackage = packages.find((item) => item.code === packageCode);
  const selectedBundles = (payload?.catalog.menuBundles ?? []).filter((bundle) => menuBundles.includes(bundle.code));
  const selectedAddOns = (payload?.catalog.addOns ?? []).filter((item) => addOns.includes(item.code));
  const selectedManualItems = manualSelections
    .map((selection) => {
      const option = manualOptionIndex[selection.option_code];
      if (!option) {
        return null;
      }

      return {
        ...option,
        quantity: selection.quantity,
        lineTotal: option.price * selection.quantity,
      };
    })
    .filter((item): item is NonNullable<typeof item> => Boolean(item));

  const pricingPreview = useMemo(() => {
    if (!payload || !selectedPackage) {
      return {
        subtotal: 0,
        multiplier: 1,
        total: 0,
        pricingRuleLabel: 'Regular day rate',
        extensionTotal: 0,
      };
    }

    const date = new Date(`${eventDate}T12:00:00`);
    const isWeekend = [0, 6].includes(date.getDay());
    const isHoliday = payload.catalog.pricing.holidays.includes(eventDate);
    const multiplier = isHoliday
      ? payload.catalog.pricing.holiday_multiplier
      : isWeekend
        ? payload.catalog.pricing.weekend_multiplier
        : 1;
    const pricingRuleLabel = isHoliday ? 'Holiday rate' : isWeekend ? 'Weekend rate' : 'Regular day rate';
    const includedHours = 4;
    const extensionHours = Math.max(durationHours - includedHours, 0);
    const extensionTotal = extensionHours * payload.catalog.pricing.extension_hourly_rate;
    const subtotal =
      selectedPackage.price +
      selectedBundles.reduce((sum, item) => sum + item.price, 0) +
      selectedAddOns.reduce((sum, item) => sum + item.price, 0) +
      selectedManualItems.reduce((sum, item) => sum + item.lineTotal, 0) +
      extensionTotal;

    return {
      subtotal,
      multiplier,
      total: subtotal * multiplier,
      pricingRuleLabel,
      extensionTotal,
    };
  }, [durationHours, eventDate, payload, selectedAddOns, selectedBundles, selectedManualItems, selectedPackage]);

  const selectedRoom = roomChoices.find((choice) => choice.code === roomChoice);
  const receiptLines = useMemo(() => {
    const lines = [];

    if (selectedPackage) {
      lines.push({ label: selectedPackage.name, amount: selectedPackage.price });
    }

    selectedBundles.forEach((bundle) => lines.push({ label: bundle.name, amount: bundle.price }));
    selectedAddOns.forEach((item) => lines.push({ label: item.name, amount: item.price }));
    selectedManualItems.forEach((item) => lines.push({ label: `${item.quantity}x ${item.optionLabel}`, amount: item.lineTotal }));

    if (pricingPreview.extensionTotal > 0) {
      lines.push({ label: `${durationHours - 4}h extension`, amount: pricingPreview.extensionTotal });
    }

    return lines;
  }, [durationHours, pricingPreview.extensionTotal, selectedAddOns, selectedBundles, selectedManualItems, selectedPackage]);

  const visibleMenuItems = payload?.catalog.menuCategories.find((item) => item.code === activeCategory)?.items ?? [];

  const calendarCells = useMemo(() => {
    if (!calendarMonth) {
      return [];
    }

    const [year, month] = calendarMonth.split('-').map(Number);
    const firstDate = new Date(year, month - 1, 1, 12, 0, 0);
    const leadingBlanks = (firstDate.getDay() + 6) % 7;
    const totalDays = new Date(year, month, 0, 12, 0, 0).getDate();
    const dateMap = Object.fromEntries(
      dateCards
        .filter((item) => monthFromDate(item.date) === calendarMonth)
        .map((item) => [Number(item.date.slice(-2)), item]),
    );
    const cells: ({ type: 'blank' } | { type: 'day'; day: number; item: AvailabilityDate | null })[] = [];

    for (let index = 0; index < leadingBlanks; index += 1) {
      cells.push({ type: 'blank' });
    }

    for (let day = 1; day <= totalDays; day += 1) {
      cells.push({ type: 'day', day, item: dateMap[day] ?? null });
    }

    return cells;
  }, [calendarMonth, dateCards]);

  function toggleCode(list: string[], setList: (value: string[]) => void, code: string) {
    setList(list.includes(code) ? list.filter((item) => item !== code) : [...list, code]);
  }

  function updateManualSelection(optionCode: string, direction: 'increment' | 'decrement') {
    setManualSelections((current) => {
      const existing = current.find((item) => item.option_code === optionCode);

      if (!existing && direction === 'increment') {
        return [...current, { option_code: optionCode, quantity: 1 }];
      }

      if (!existing) {
        return current;
      }

      const nextQuantity = direction === 'increment' ? existing.quantity + 1 : existing.quantity - 1;

      if (nextQuantity <= 0) {
        return current.filter((item) => item.option_code !== optionCode);
      }

      return current.map((item) =>
        item.option_code === optionCode ? { ...item, quantity: nextQuantity } : item,
      );
    });
  }

  async function selectPaymentProof() {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [4, 3],
      quality: 0.8,
    });

    if (result.canceled || !result.assets[0]) {
      return;
    }

    const asset = result.assets[0];
    setPaymentProof({
      uri: asset.uri,
      name: asset.fileName ?? `payment-proof-${Date.now()}.jpg`,
      type: asset.mimeType ?? 'image/jpeg',
    });
  }

  async function submitReservation() {
    const guestCount = Number(guests);

    if (!token || !user) {
      Alert.alert('Sign in required', 'Create an account or sign in first so your reservation can be attached to your customer profile.', [
        { text: 'Later', style: 'cancel' },
        { text: 'Sign in', onPress: () => router.push('/login') },
      ]);
      return;
    }

    if (!selectedBranch || !selectedDateAvailability || !selectedPackage || !roomChoice) {
      Alert.alert('Booking incomplete', 'Please choose a branch, schedule, room, and package before submitting.');
      return;
    }

    if (!Number.isInteger(guestCount) || guestCount < 2) {
      Alert.alert('Invalid guest count', 'Enter at least 2 guests before submitting the reservation.');
      return;
    }

    if (guestCount > selectedBranch.max_guests) {
      Alert.alert('Guest limit exceeded', `This branch currently supports up to ${selectedBranch.max_guests} guests for one booking.`);
      return;
    }

    if (!startSlots.some((slot) => slot.time === eventTime)) {
      Alert.alert('Invalid time slot', 'Please choose an available start time before submitting.');
      return;
    }

    if (!paymentProof) {
      Alert.alert('Payment proof required', 'Select an image before submitting the reservation.');
      return;
    }

    try {
      setSubmitting(true);
      const formData = new FormData();
      formData.append('event_type', eventType);
      formData.append('branch_code', branchCode);
      formData.append('event_date', eventDate);
      formData.append('event_time', eventTime);
      formData.append('duration_hours', String(durationHours));
      formData.append('room_choice', roomChoice);
      formData.append('guests', String(guestCount));
      formData.append('package_code', packageCode);
      selectedBundles.forEach((bundle, index) => formData.append(`menu_bundles[${index}]`, bundle.code));
      selectedAddOns.forEach((item, index) => formData.append(`add_ons[${index}]`, item.code));
      manualSelections.forEach((item, index) => {
        formData.append(`manual_menu_items[${index}][option_code]`, item.option_code);
        formData.append(`manual_menu_items[${index}][quantity]`, String(item.quantity));
      });

      if (notes.trim()) {
        formData.append('notes', notes.trim());
      }

      formData.append(
        'payment_proof',
        {
          uri: paymentProof.uri,
          name: paymentProof.name,
          type: paymentProof.type,
        } as any,
      );

      const response = await createReservation(formData, token);
      Alert.alert('Reservation submitted', `${response.message}\n\nReference: ${response.reservation.booking_reference}`);
      router.push('/(tabs)/dashboard');
    } catch (error) {
      const message = error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Unable to submit the reservation right now.';
      Alert.alert('Booking failed', message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <CustomerPage
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadBookingOptions(true)} tintColor={palette.brandRed} />}
      contentContainerStyle={styles.pageContent}>
      <CustomerHeader
        title="Book Event"
        subtitle="Customer booking flow connected to the live reservation database."
        leftSlot={<HeaderIconButton icon="chevron-left" onPress={() => router.push('/(tabs)')} />}
        rightSlot={<McLogo />}
        centered
      />
      <SheetSurface>
        {errorMessage ? (
          <CustomerCard tone="pink">
            <SectionEyebrow>Connection issue</SectionEyebrow>
            <SectionTitle>Booking data is unavailable</SectionTitle>
            <Text style={styles.helperText}>{errorMessage}</Text>
            <CustomerButton label="Reload booking data" onPress={() => void loadBookingOptions()} tone="ghost" />
          </CustomerCard>
        ) : null}

        {loading && !payload ? <Text style={styles.helperText}>Loading booking options...</Text> : null}

        {payload ? (
          <>
            <CustomerCard tone="cream">
              <SectionEyebrow>Choose a celebration</SectionEyebrow>
              <View style={styles.chipRow}>
                {Object.entries(payload.catalog.eventTypes).map(([code, item]) => (
                  <CustomerChip key={code} label={item.label} active={eventType === code} onPress={() => setEventType(code)} />
                ))}
              </View>
            </CustomerCard>

            <CustomerCard>
              <SectionEyebrow>Choose a branch</SectionEyebrow>
              <SectionTitle>Book and manage events</SectionTitle>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalScroll}>
                {supportedBranches.map((branch) => (
                  <Pressable
                    key={branch.code}
                    onPress={() => setBranchCode(branch.code)}
                    style={[styles.branchCard, branchCode === branch.code ? styles.branchCardActive : null]}>
                    <Text style={styles.branchCardTitle}>{branch.name}</Text>
                    <Text style={styles.branchCardMeta}>{branch.city}</Text>
                    <Text style={styles.branchCardGuests}>Up to {branch.max_guests} guests</Text>
                    <View style={styles.chipRow}>
                      {Object.entries(branch.supports)
                        .filter(([, supported]) => supported)
                        .map(([type]) => (
                          <CustomerChip
                            key={`${branch.code}-${type}`}
                            label={type.charAt(0).toUpperCase() + type.slice(1)}
                            tone={type === 'business' ? 'pink' : 'green'}
                          />
                        ))}
                    </View>
                    {branch.map_url ? (
                      <CustomerButton
                        label="Open in Maps"
                        onPress={() => void Linking.openURL(branch.map_url!)}
                        tone="ghost"
                        compact
                      />
                    ) : null}
                  </Pressable>
                ))}
              </ScrollView>
              {selectedBranch?.map_url ? (
                <CustomerButton
                  label="Open selected branch in Maps"
                  onPress={() => void Linking.openURL(selectedBranch.map_url!)}
                  tone="ghost"
                  icon="map-marker-outline"
                  compact
                />
              ) : null}
            </CustomerCard>

            <CustomerCard>
              <View style={styles.sectionHeaderRow}>
                <View style={{ gap: 2 }}>
                  <SectionEyebrow>Reservation schedule</SectionEyebrow>
                  <SectionTitle>Pick your date and time</SectionTitle>
                </View>
                <CustomerButton label="Refresh now" onPress={() => void loadBookingOptions(true)} tone="secondary" compact />
              </View>

              <View style={styles.monthSwitcher}>
                <CustomerButton
                  label="<"
                  onPress={() => {
                    const currentIndex = monthOptions.indexOf(calendarMonth);
                    if (currentIndex > 0) {
                      setCalendarMonth(monthOptions[currentIndex - 1]);
                    }
                  }}
                  tone="ghost"
                  compact
                />
                <Text style={styles.monthLabel}>{calendarMonth ? formatMonthLabel(calendarMonth) : 'Choose a month'}</Text>
                <CustomerButton
                  label=">"
                  onPress={() => {
                    const currentIndex = monthOptions.indexOf(calendarMonth);
                    if (currentIndex >= 0 && currentIndex < monthOptions.length - 1) {
                      setCalendarMonth(monthOptions[currentIndex + 1]);
                    }
                  }}
                  tone="ghost"
                  compact
                />
              </View>

              <View style={styles.weekRow}>
                {['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map((day) => (
                  <Text key={day} style={styles.weekLabel}>
                    {day}
                  </Text>
                ))}
              </View>

              <View style={styles.calendarGrid}>
                {calendarCells.map((cell, index) =>
                  cell.type === 'blank' ? (
                    <View key={`blank-${index}`} style={styles.blankDay} />
                  ) : (
                    <Pressable
                      key={cell.item?.date ?? `${calendarMonth}-${cell.day}`}
                      disabled={!cell.item}
                      onPress={() => {
                        if (cell.item) {
                          setEventDate(cell.item.date);
                        }
                      }}
                      style={[
                        styles.dayCell,
                        cell.item?.date === eventDate ? styles.dayCellActive : null,
                        cell.item?.status === 'full' ? styles.dayCellFull : null,
                      ]}>
                      <Text
                        style={[
                          styles.dayCellText,
                          cell.item?.date === eventDate ? styles.dayCellTextActive : null,
                          cell.item?.status === 'limited' ? styles.dayCellTextLimited : null,
                          cell.item?.status === 'full' ? styles.dayCellTextFull : null,
                        ]}>
                        {cell.day}
                      </Text>
                    </Pressable>
                  ),
                )}
              </View>

              <View style={styles.timeCardRow}>
                <CustomerCard tone="cream" style={styles.timeCard}>
                  <Text style={styles.timeCardLabel}>Start Time</Text>
                  <Text style={styles.timeCardValue}>{formatTimeLabel(eventTime)}</Text>
                  <Text style={styles.timeCardMeta}>{formatLongDate(eventDate)}</Text>
                </CustomerCard>
                <CustomerCard tone="cream" style={styles.timeCard}>
                  <Text style={styles.timeCardLabel}>Due Time</Text>
                  <Text style={styles.timeCardValue}>{formatTimeLabel(addHoursToTime(eventTime, durationHours))}</Text>
                  <Text style={styles.timeCardMeta}>{durationHours} hour span</Text>
                </CustomerCard>
              </View>

              <Text style={styles.inputLabel}>Available start times</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalScroll}>
                {startSlots.map((slot) => (
                  <CustomerChip key={slot.time} label={slot.label} active={eventTime === slot.time} onPress={() => setEventTime(slot.time)} />
                ))}
              </ScrollView>

              <Text style={styles.inputLabel}>Duration</Text>
              <View style={styles.chipRow}>
                {allowedDurations.map((duration) => (
                  <CustomerChip key={duration} label={`${duration} hour${duration > 1 ? 's' : ''}`} active={durationHours === duration} onPress={() => setDurationHours(duration)} />
                ))}
              </View>

              <Text style={styles.inputLabel}>Guests</Text>
              <CustomerField label="Expected guests" value={guests} onChangeText={setGuests} placeholder="10" keyboardType="numeric" />

              <Text style={styles.inputLabel}>Room choice</Text>
              <View style={styles.chipRow}>
                {payload.roomChoices.map((choice) => (
                  <CustomerChip key={choice.code} label={choice.label} active={roomChoice === choice.code} onPress={() => setRoomChoice(choice.code)} tone="neutral" />
                ))}
              </View>
            </CustomerCard>

            <CustomerCard>
              <SectionEyebrow>Featured packages</SectionEyebrow>
              <View style={styles.packageGrid}>
                {packages.map((item) => (
                  <Pressable
                    key={item.code}
                    onPress={() => setPackageCode(item.code)}
                    style={[styles.packageCard, packageCode === item.code ? styles.packageCardActive : null, isWide ? styles.packageCardWide : null]}>
                    <Text style={styles.packageGuestRange}>{item.guest_range}</Text>
                    <Text style={styles.packageTitle}>{item.name}</Text>
                    <Text style={styles.packagePrice}>{formatCurrency(item.price)}</Text>
                    <View style={styles.packageFeatures}>
                      {item.features.slice(0, 3).map((feature) => (
                        <Text key={feature} style={styles.packageFeature}>
                          - {feature}
                        </Text>
                      ))}
                    </View>
                    <CustomerButton label={packageCode === item.code ? 'Selected' : 'Avail now'} onPress={() => setPackageCode(item.code)} compact />
                  </Pressable>
                ))}
              </View>
            </CustomerCard>

            <CustomerCard tone="cream">
              <SectionEyebrow>Extras</SectionEyebrow>
              <Text style={styles.inputLabel}>Bundle upgrades</Text>
              <View style={styles.chipRow}>
                {payload.catalog.menuBundles.map((bundle) => (
                  <CustomerChip
                    key={bundle.code}
                    label={`${bundle.name} - ${formatCurrency(bundle.price)}`}
                    active={menuBundles.includes(bundle.code)}
                    onPress={() => toggleCode(menuBundles, setMenuBundles, bundle.code)}
                  />
                ))}
              </View>

              <Text style={styles.inputLabel}>Service add-ons</Text>
              <View style={styles.chipRow}>
                {payload.catalog.addOns.map((item) => (
                  <CustomerChip
                    key={item.code}
                    label={`${item.name} - ${formatCurrency(item.price)}`}
                    active={addOns.includes(item.code)}
                    onPress={() => toggleCode(addOns, setAddOns, item.code)}
                    tone="green"
                  />
                ))}
              </View>
            </CustomerCard>

            <CustomerCard>
              <SectionEyebrow>Manual food and drinks</SectionEyebrow>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalScroll}>
                {payload.catalog.menuCategories.map((category) => (
                  <CustomerChip
                    key={category.code}
                    label={category.name}
                    active={activeCategory === category.code}
                    onPress={() => setActiveCategory(category.code)}
                    tone="neutral"
                  />
                ))}
              </ScrollView>

              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalScroll}>
                {visibleMenuItems.map((item) => (
                  <CustomerCard key={item.code} style={styles.menuCard}>
                    <View style={styles.menuImageMock}>
                      <Text style={styles.menuImageLabel}>{item.name}</Text>
                    </View>
                    {item.options.map((option) => {
                      const quantity = manualSelections.find((entry) => entry.option_code === option.code)?.quantity ?? 0;
                      return (
                        <View key={option.code} style={styles.menuOptionBlock}>
                          <Text style={styles.menuPrice}>{formatCurrency(option.price)}</Text>
                          <Text style={styles.menuOptionLabel}>{option.label}</Text>
                          <View style={styles.stepperRow}>
                            <Pressable onPress={() => updateManualSelection(option.code, 'decrement')} style={styles.stepperButton}>
                              <Text style={styles.stepperButtonText}>-</Text>
                            </Pressable>
                            <Text style={styles.stepperValue}>{quantity}</Text>
                            <Pressable onPress={() => updateManualSelection(option.code, 'increment')} style={styles.stepperButton}>
                              <Text style={styles.stepperButtonText}>+</Text>
                            </Pressable>
                          </View>
                        </View>
                      );
                    })}
                  </CustomerCard>
                ))}
              </ScrollView>
            </CustomerCard>

            <CustomerCard>
              <SectionEyebrow>Booking receipt</SectionEyebrow>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Event type:</Text>
                <Text style={styles.summaryValue}>{payload.catalog.eventTypes[eventType]?.label ?? eventType}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Branch:</Text>
                <Text style={styles.summaryValue}>{selectedBranch?.name ?? '-'}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Room:</Text>
                <Text style={styles.summaryValue}>{selectedRoom?.label ?? roomChoice}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Event time:</Text>
                <Text style={styles.summaryValue}>
                  {formatLongDate(eventDate)}{'\n'}
                  {formatTimeLabel(eventTime)} - {formatTimeLabel(addHoursToTime(eventTime, durationHours))}
                </Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Duration:</Text>
                <Text style={styles.summaryValue}>{durationHours} hour</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Manual tray:</Text>
                <Text style={styles.summaryValue}>{selectedManualItems.length > 0 ? `${selectedManualItems.length} selected lines` : 'No items added.'}</Text>
              </View>
            </CustomerCard>

            <CustomerCard tone="cream">
              <SectionEyebrow>Receipt review</SectionEyebrow>
              {receiptLines.map((line) => (
                <View key={line.label} style={styles.receiptRow}>
                  <Text style={styles.receiptLabel}>{line.label}</Text>
                  <Text style={styles.receiptValue}>{formatCurrency(line.amount)}</Text>
                </View>
              ))}
              <View style={styles.receiptRow}>
                <Text style={styles.receiptLabel}>Subtotal</Text>
                <Text style={styles.receiptValue}>{formatCurrency(pricingPreview.subtotal)}</Text>
              </View>
              <View style={styles.receiptRow}>
                <Text style={styles.receiptLabel}>{pricingPreview.pricingRuleLabel}</Text>
                <Text style={styles.receiptValue}>{pricingPreview.multiplier.toFixed(2)}x</Text>
              </View>
              <View style={[styles.receiptRow, styles.receiptRowStrong]}>
                <Text style={styles.receiptStrongLabel}>Total before confirmation</Text>
                <Text style={styles.receiptStrongValue}>{formatCurrency(pricingPreview.total)}</Text>
              </View>
            </CustomerCard>

            <CustomerCard>
              <SectionEyebrow>Payment and notes</SectionEyebrow>
              <Text style={styles.helperText}>Upload proof of payment so your admin can confirm this reservation from the same database-backed dashboard.</Text>
              <CustomerButton
                label={paymentProof ? 'Replace payment proof' : 'Choose payment proof'}
                onPress={() => void selectPaymentProof()}
                tone="secondary"
              />
              {paymentProof ? <Image source={{ uri: paymentProof.uri }} style={styles.previewImage} /> : null}
              <CustomerField label="Notes" value={notes} onChangeText={setNotes} placeholder="Theme, setup notes, or special requests" multiline />
              <CustomerButton
                label={user ? 'Confirm Reservation' : 'Sign in to confirm'}
                onPress={() => void submitReservation()}
                loading={submitting}
              />
              {!user ? <CustomerButton label="Create customer account" onPress={() => router.push('/register')} tone="ghost" /> : null}
            </CustomerCard>
          </>
        ) : null}
      </SheetSurface>
    </CustomerPage>
  );
}

const styles = StyleSheet.create({
  pageContent: {
    gap: 0,
  },
  helperText: {
    color: '#675446',
    fontSize: 14,
    lineHeight: 20,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  horizontalScroll: {
    gap: 10,
  },
  branchCard: {
    width: 248,
    borderRadius: 22,
    backgroundColor: '#FFF7EA',
    padding: 16,
    gap: 10,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  branchCardActive: {
    borderColor: palette.brandRed,
    backgroundColor: '#FFF1EE',
  },
  branchCardTitle: {
    color: palette.ink,
    fontSize: 18,
    fontWeight: '900',
  },
  branchCardMeta: {
    color: '#675446',
    fontSize: 14,
  },
  branchCardGuests: {
    alignSelf: 'flex-start',
    color: '#8B6A07',
    backgroundColor: '#FFF2C8',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    overflow: 'hidden',
    fontWeight: '700',
    fontSize: 11,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    alignItems: 'center',
  },
  monthSwitcher: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 12,
  },
  monthLabel: {
    color: palette.ink,
    fontSize: 16,
    fontWeight: '800',
  },
  weekRow: {
    flexDirection: 'row',
  },
  weekLabel: {
    flex: 1,
    textAlign: 'center',
    color: '#77A6E8',
    fontSize: 12,
    fontWeight: '700',
  },
  calendarGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  blankDay: {
    width: '13%',
    aspectRatio: 1,
  },
  dayCell: {
    width: '13%',
    aspectRatio: 1,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F6F1E7',
  },
  dayCellActive: {
    backgroundColor: '#F5E08A',
  },
  dayCellFull: {
    backgroundColor: '#F9E5E5',
  },
  dayCellText: {
    color: '#857567',
    fontWeight: '700',
  },
  dayCellTextActive: {
    color: palette.ink,
    fontWeight: '900',
  },
  dayCellTextLimited: {
    color: '#4C9CF1',
  },
  dayCellTextFull: {
    color: '#E98C8C',
  },
  timeCardRow: {
    flexDirection: 'row',
    gap: 10,
  },
  timeCard: {
    flex: 1,
    minWidth: 0,
  },
  timeCardLabel: {
    color: '#7A604B',
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  timeCardValue: {
    color: palette.ink,
    fontSize: 26,
    fontWeight: '900',
  },
  timeCardMeta: {
    color: '#6F6359',
    fontSize: 13,
  },
  inputLabel: {
    color: '#5F5146',
    fontSize: 13,
    fontWeight: '800',
  },
  packageGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  packageCard: {
    width: '47%',
    borderRadius: 22,
    backgroundColor: '#FFFFFF',
    padding: 14,
    gap: 8,
    borderWidth: 1,
    borderColor: '#ECE3D6',
  },
  packageCardWide: {
    width: '31%',
  },
  packageCardActive: {
    borderColor: palette.brandRed,
    backgroundColor: '#FFF3EF',
  },
  packageGuestRange: {
    color: '#8A7B6A',
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
  },
  packageTitle: {
    color: palette.ink,
    fontSize: 18,
    fontWeight: '900',
    lineHeight: 22,
  },
  packagePrice: {
    color: palette.brandRed,
    fontSize: 22,
    fontWeight: '900',
  },
  packageFeatures: {
    minHeight: 60,
    gap: 3,
  },
  packageFeature: {
    color: '#6B5A4D',
    fontSize: 13,
    lineHeight: 17,
  },
  menuCard: {
    width: 200,
    gap: 12,
  },
  menuImageMock: {
    height: 90,
    borderRadius: 14,
    backgroundColor: '#F5E7D8',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
  },
  menuImageLabel: {
    color: palette.ink,
    fontWeight: '900',
    textAlign: 'center',
  },
  menuOptionBlock: {
    gap: 6,
  },
  menuPrice: {
    color: palette.brandRed,
    fontSize: 13,
    fontWeight: '800',
  },
  menuOptionLabel: {
    color: palette.ink,
    fontWeight: '700',
    fontSize: 13,
  },
  stepperRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  stepperButton: {
    width: 28,
    height: 28,
    borderRadius: 8,
    backgroundColor: '#FF4B45',
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepperButtonText: {
    color: '#FFFFFF',
    fontWeight: '900',
    fontSize: 18,
    lineHeight: 20,
  },
  stepperValue: {
    color: palette.ink,
    fontWeight: '900',
    minWidth: 12,
    textAlign: 'center',
  },
  summaryRow: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  summaryLabel: {
    color: '#6B5A4D',
    fontWeight: '700',
    width: 84,
  },
  summaryValue: {
    flex: 1,
    color: palette.ink,
    fontWeight: '700',
    textAlign: 'right',
    lineHeight: 19,
  },
  receiptRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  receiptLabel: {
    color: '#6B5A4D',
    flex: 1,
    lineHeight: 19,
  },
  receiptValue: {
    color: palette.ink,
    fontWeight: '700',
  },
  receiptRowStrong: {
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#E4DBCF',
  },
  receiptStrongLabel: {
    color: palette.ink,
    fontSize: 16,
    fontWeight: '900',
  },
  receiptStrongValue: {
    color: palette.brandRed,
    fontSize: 16,
    fontWeight: '900',
  },
  previewImage: {
    width: '100%',
    height: 200,
    borderRadius: 18,
    resizeMode: 'cover',
  },
});
