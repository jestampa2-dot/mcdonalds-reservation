import * as ImagePicker from 'expo-image-picker';
import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import { ApiError, createReservation, fetchBookingOptions } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { AvailabilityDate, BookingOptionsPayload } from '@/lib/types';

type PickedImage = {
  uri: string;
  name: string;
  type: string;
};

type ManualSelection = {
  option_code: string;
  quantity: number;
};

function formatTimeLabel(time: string) {
  const [hours, minutes] = time.split(':').map(Number);
  const meridian = hours >= 12 ? 'PM' : 'AM';
  const hour12 = hours % 12 || 12;
  return `${hour12}:${String(minutes).padStart(2, '0')} ${meridian}`;
}

function addHoursToTime(time: string, hoursToAdd: number) {
  const [hours, minutes] = time.split(':').map(Number);
  const totalMinutes = hours * 60 + minutes + hoursToAdd * 60;
  const nextHours = Math.floor(totalMinutes / 60) % 24;
  const nextMinutes = totalMinutes % 60;

  return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`;
}

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

export default function BookingScreen() {
  const { token, user } = useAuth();
  const [payload, setPayload] = useState<BookingOptionsPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [paymentProof, setPaymentProof] = useState<PickedImage | null>(null);
  const [notes, setNotes] = useState('');
  const [guests, setGuests] = useState('10');
  const [eventType, setEventType] = useState('birthday');
  const [branchCode, setBranchCode] = useState('');
  const [eventDate, setEventDate] = useState('');
  const [eventTime, setEventTime] = useState('');
  const [durationHours, setDurationHours] = useState(4);
  const [roomChoice, setRoomChoice] = useState('');
  const [packageCode, setPackageCode] = useState('');
  const [menuBundles, setMenuBundles] = useState<string[]>([]);
  const [addOns, setAddOns] = useState<string[]>([]);
  const [manualSelections, setManualSelections] = useState<ManualSelection[]>([]);

  async function loadBookingOptions(nextRefreshing = false) {
    try {
      if (nextRefreshing) {
        setRefreshing(true);
      } else {
        setLoading(true);
      }

      const response = await fetchBookingOptions();
      const eventTypes = Object.keys(response.catalog.eventTypes);
      const nextEventType = eventTypes[0] ?? 'birthday';
      const supportedBranches = Object.values(response.catalog.branches).filter((branch) => branch.supports[nextEventType]);
      const nextBranch = supportedBranches[0]?.code ?? Object.keys(response.catalog.branches)[0] ?? '';
      const nextPackages = response.catalog.packages[nextEventType] ?? [];

      setPayload(response);
      setEventType(nextEventType);
      setBranchCode(nextBranch);
      setEventDate(response.defaults.event_date);
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
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }

  useEffect(() => {
    void loadBookingOptions();
  }, []);

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

  const branchAvailability = useMemo(() => {
    return payload?.availability.branches.find((branch) => branch.code === branchCode) ?? null;
  }, [branchCode, payload]);

  const dateCards = useMemo(() => branchAvailability?.dates ?? [], [branchAvailability]);
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
  }, [dateCards, eventDate, selectedDateAvailability]);

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
    .filter(Boolean);

  const estimatedTotal = useMemo(() => {
    if (!payload || !selectedPackage) {
      return 0;
    }

    const date = new Date(`${eventDate}T12:00:00`);
    const isWeekend = [0, 6].includes(date.getDay());
    const isHoliday = payload.catalog.pricing.holidays.includes(eventDate);
    const multiplier = isHoliday
      ? payload.catalog.pricing.holiday_multiplier
      : isWeekend
        ? payload.catalog.pricing.weekend_multiplier
        : 1;

    const includedHours = 4;
    const extensionHours = Math.max(durationHours - includedHours, 0);
    const subtotal =
      selectedPackage.price +
      selectedBundles.reduce((sum, item) => sum + item.price, 0) +
      selectedAddOns.reduce((sum, item) => sum + item.price, 0) +
      selectedManualItems.reduce((sum, item) => sum + item.lineTotal, 0) +
      extensionHours * payload.catalog.pricing.extension_hourly_rate;

    return subtotal * multiplier;
  }, [durationHours, eventDate, payload, selectedAddOns, selectedBundles, selectedManualItems, selectedPackage]);

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
    if (!token || !user) {
      Alert.alert('Sign in required', 'Create an account or sign in first so the reservation can be attached to your customer profile.', [
        { text: 'Later', style: 'cancel' },
        { text: 'Sign in', onPress: () => router.push('/login') },
      ]);
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
      formData.append('guests', guests);
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

      formData.append('payment_proof', {
        uri: paymentProof.uri,
        name: paymentProof.name,
        type: paymentProof.type,
      } as any);

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
    <AppScreen
      eyebrow="Reservation flow"
      title="Build your event package"
      subtitle="This mobile screen uses the live booking catalog, availability map, and pricing rules from Laravel."
      scroll={false}>
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadBookingOptions(true)} tintColor={palette.brandRed} />}
        contentContainerStyle={{ gap: 20, paddingBottom: 32 }}>
        {loading || !payload ? (
          <Panel>
            <Text style={styles.loadingText}>Loading booking options...</Text>
          </Panel>
        ) : (
          <>
            <Panel>
              <SectionHeading label="1. Event type" title="Choose the celebration" />
              <View style={styles.tagWrap}>
                {Object.entries(payload.catalog.eventTypes).map(([code, item]) => (
                  <Tag key={code} label={item.label} active={eventType === code} onPress={() => setEventType(code)} />
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="2. Branch and schedule" title="Pick an open slot" />
              <Text style={styles.helperText}>Supported branches</Text>
              <View style={styles.tagWrap}>
                {supportedBranches.map((branch) => (
                  <Tag key={branch.code} label={`${branch.name} · ${branch.city}`} active={branchCode === branch.code} onPress={() => setBranchCode(branch.code)} />
                ))}
              </View>
              <Text style={styles.helperText}>Date</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalList}>
                {dateCards.slice(0, 18).map((dateCard) => (
                  <Pressable key={dateCard.date} onPress={() => setEventDate(dateCard.date)} style={[styles.dateCard, eventDate === dateCard.date ? styles.dateCardActive : null]}>
                    <Text style={[styles.dateCardDay, eventDate === dateCard.date ? styles.dateCardDayActive : null]}>
                      {new Date(`${dateCard.date}T12:00:00`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}
                    </Text>
                    <Text style={[styles.dateCardMeta, eventDate === dateCard.date ? styles.dateCardMetaActive : null]}>
                      {dateCard.available_slots} starts
                    </Text>
                  </Pressable>
                ))}
              </ScrollView>
              <Text style={styles.helperText}>Start time</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalList}>
                {startSlots.map((slot) => (
                  <Tag key={slot.time} label={slot.label} active={eventTime === slot.time} onPress={() => setEventTime(slot.time)} />
                ))}
              </ScrollView>
              <Text style={styles.helperText}>Duration</Text>
              <View style={styles.tagWrap}>
                {allowedDurations.map((duration) => (
                  <Tag key={duration} label={`${duration}h`} active={durationHours === duration} onPress={() => setDurationHours(duration)} />
                ))}
              </View>
              <Text style={styles.summaryText}>
                {selectedDateAvailability ? `${formatTimeLabel(eventTime)} to ${formatTimeLabel(addHoursToTime(eventTime, durationHours))}` : 'Select a date to continue'}
              </Text>
            </Panel>

            <Panel>
              <SectionHeading label="3. Package" title="Start with the main package" />
              <View style={styles.optionStack}>
                {packages.map((item) => (
                  <Pressable key={item.code} onPress={() => setPackageCode(item.code)} style={[styles.optionCard, packageCode === item.code ? styles.optionCardActive : null]}>
                    <View style={styles.optionHeader}>
                      <Text style={styles.optionTitle}>{item.name}</Text>
                      <Text style={styles.optionPrice}>
                        {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(item.price)}
                      </Text>
                    </View>
                    <Text style={styles.optionMeta}>{item.guest_range}</Text>
                    {item.features.map((feature) => (
                      <Text key={feature} style={styles.optionFeature}>
                        • {feature}
                      </Text>
                    ))}
                  </Pressable>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="4. Extras" title="Bundles, add-ons, and manual menu picks" />
              <Text style={styles.helperText}>Menu bundles</Text>
              <View style={styles.optionStack}>
                {payload.catalog.menuBundles.map((bundle) => (
                  <Pressable key={bundle.code} onPress={() => toggleCode(menuBundles, setMenuBundles, bundle.code)} style={[styles.miniOption, menuBundles.includes(bundle.code) ? styles.miniOptionActive : null]}>
                    <Text style={styles.miniOptionTitle}>{bundle.name}</Text>
                    <Text style={styles.miniOptionPrice}>
                      {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(bundle.price)}
                    </Text>
                  </Pressable>
                ))}
              </View>

              <Text style={styles.helperText}>Add-ons</Text>
              <View style={styles.optionStack}>
                {payload.catalog.addOns.map((item) => (
                  <Pressable key={item.code} onPress={() => toggleCode(addOns, setAddOns, item.code)} style={[styles.miniOption, addOns.includes(item.code) ? styles.miniOptionActive : null]}>
                    <Text style={styles.miniOptionTitle}>{item.name}</Text>
                    <Text style={styles.miniOptionPrice}>
                      {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(item.price)}
                    </Text>
                  </Pressable>
                ))}
              </View>

              {payload.catalog.menuCategories.length > 0 ? (
                <>
                  <Text style={styles.helperText}>Manual menu</Text>
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.horizontalList}>
                    {payload.catalog.menuCategories.map((category) => (
                      <Tag key={category.code} label={category.name} active={activeCategory === category.code} onPress={() => setActiveCategory(category.code)} />
                    ))}
                  </ScrollView>
                  {(payload.catalog.menuCategories.find((item) => item.code === activeCategory)?.items ?? []).map((item) => (
                    <View key={item.code} style={styles.manualItemCard}>
                      <Text style={styles.manualItemTitle}>{item.name}</Text>
                      {item.description ? <Text style={styles.manualItemDescription}>{item.description}</Text> : null}
                      {item.options.map((option) => {
                        const quantity = manualSelections.find((entry) => entry.option_code === option.code)?.quantity ?? 0;
                        return (
                          <View key={option.code} style={styles.manualOptionRow}>
                            <View style={{ flex: 1 }}>
                              <Text style={styles.manualOptionLabel}>{option.label}</Text>
                              <Text style={styles.manualOptionPrice}>
                                {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(option.price)}
                              </Text>
                            </View>
                            <View style={styles.quantityControls}>
                              <AppButton label="−" onPress={() => updateManualSelection(option.code, 'decrement')} tone="ghost" />
                              <Text style={styles.quantityText}>{quantity}</Text>
                              <AppButton label="+" onPress={() => updateManualSelection(option.code, 'increment')} tone="secondary" />
                            </View>
                          </View>
                        );
                      })}
                    </View>
                  ))}
                </>
              ) : null}
            </Panel>

            <Panel>
              <SectionHeading label="5. Customer details" title="Finalize the request" />
              <Field label="Number of guests" value={guests} onChangeText={setGuests} placeholder="10" keyboardType="numeric" />
              <Text style={styles.helperText}>Room choice</Text>
              <View style={styles.tagWrap}>
                {payload.roomChoices.map((choice) => (
                  <Tag key={choice.code} label={choice.label} active={roomChoice === choice.code} onPress={() => setRoomChoice(choice.code)} />
                ))}
              </View>
              <Field label="Notes for the crew" value={notes} onChangeText={setNotes} placeholder="Theme, special setup, dietary notes..." multiline />
              <View style={styles.proofPanel}>
                <View style={{ gap: 4, flex: 1 }}>
                  <Text style={styles.proofTitle}>Payment proof</Text>
                  <Text style={styles.proofSubtitle}>Required by the current Laravel booking flow.</Text>
                </View>
                <AppButton label={paymentProof ? 'Replace image' : 'Pick image'} onPress={() => void selectPaymentProof()} tone="secondary" />
              </View>
              {paymentProof ? <Image source={{ uri: paymentProof.uri }} style={styles.previewImage} /> : null}
            </Panel>

            <Panel>
              <SectionHeading label="Summary" title="Estimated total" />
              <Text style={styles.totalAmount}>
                {new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(estimatedTotal)}
              </Text>
              <Text style={styles.summaryDetail}>
                {selectedPackage?.name ?? 'No package selected'} · {guests} guests · {durationHours} hour{durationHours > 1 ? 's' : ''}
              </Text>
              <Text style={styles.summaryDetail}>
                {selectedManualItems.length} manual picks · {selectedBundles.length} bundles · {selectedAddOns.length} add-ons
              </Text>
              <View style={styles.submitStack}>
                <AppButton label={user ? 'Submit reservation' : 'Sign in to submit'} onPress={() => void submitReservation()} loading={submitting} />
                {!user ? <AppButton label="Create account first" onPress={() => router.push('/register')} tone="ghost" /> : null}
              </View>
            </Panel>
          </>
        )}
      </ScrollView>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  loadingText: {
    color: palette.inkMuted,
    fontSize: 15,
  },
  helperText: {
    color: palette.inkMuted,
    fontWeight: '700',
    fontSize: 13,
    marginBottom: 4,
  },
  tagWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  horizontalList: {
    gap: 10,
  },
  dateCard: {
    borderRadius: 18,
    backgroundColor: '#FFF6DE',
    paddingHorizontal: 16,
    paddingVertical: 14,
    minWidth: 110,
    gap: 4,
  },
  dateCardActive: {
    backgroundColor: palette.brandRed,
  },
  dateCardDay: {
    color: palette.ink,
    fontSize: 16,
    fontWeight: '900',
  },
  dateCardDayActive: {
    color: palette.surfaceStrong,
  },
  dateCardMeta: {
    color: palette.inkMuted,
    fontSize: 12,
    fontWeight: '700',
  },
  dateCardMetaActive: {
    color: '#FFE9DD',
  },
  summaryText: {
    color: palette.brandRedDark,
    fontWeight: '800',
    fontSize: 14,
  },
  optionStack: {
    gap: 12,
  },
  optionCard: {
    borderRadius: 20,
    backgroundColor: '#FFF7E4',
    padding: 16,
    gap: 6,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  optionCardActive: {
    borderColor: palette.brandRed,
    backgroundColor: '#FFF1ED',
  },
  optionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
    alignItems: 'center',
  },
  optionTitle: {
    flex: 1,
    color: palette.ink,
    fontSize: 18,
    fontWeight: '900',
  },
  optionPrice: {
    color: palette.brandRed,
    fontSize: 17,
    fontWeight: '900',
  },
  optionMeta: {
    color: palette.inkMuted,
    textTransform: 'uppercase',
    letterSpacing: 1.1,
    fontWeight: '700',
    fontSize: 12,
  },
  optionFeature: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  miniOption: {
    borderRadius: 18,
    padding: 15,
    backgroundColor: '#FFF7E4',
    gap: 4,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  miniOptionActive: {
    backgroundColor: '#FFF1ED',
    borderColor: palette.brandRed,
  },
  miniOptionTitle: {
    color: palette.ink,
    fontWeight: '800',
  },
  miniOptionPrice: {
    color: palette.inkMuted,
  },
  manualItemCard: {
    gap: 12,
    borderRadius: 20,
    backgroundColor: '#FFF8EA',
    padding: 16,
  },
  manualItemTitle: {
    color: palette.ink,
    fontWeight: '900',
    fontSize: 18,
  },
  manualItemDescription: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  manualOptionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 4,
  },
  manualOptionLabel: {
    color: palette.ink,
    fontWeight: '700',
  },
  manualOptionPrice: {
    color: palette.inkMuted,
    marginTop: 2,
  },
  quantityControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  quantityText: {
    minWidth: 18,
    textAlign: 'center',
    color: palette.ink,
    fontWeight: '900',
    fontSize: 16,
  },
  proofPanel: {
    flexDirection: 'row',
    gap: 12,
    alignItems: 'center',
  },
  proofTitle: {
    color: palette.ink,
    fontSize: 16,
    fontWeight: '900',
  },
  proofSubtitle: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  previewImage: {
    width: '100%',
    height: 180,
    borderRadius: 22,
    resizeMode: 'cover',
  },
  totalAmount: {
    color: palette.brandRed,
    fontSize: 34,
    fontWeight: '900',
  },
  summaryDetail: {
    color: palette.inkMuted,
    lineHeight: 20,
  },
  submitStack: {
    gap: 12,
    marginTop: 6,
  },
});
