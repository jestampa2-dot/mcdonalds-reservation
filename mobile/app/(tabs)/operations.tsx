import { router } from 'expo-router';
import type { Dispatch, SetStateAction } from 'react';
import { useCallback, useEffect, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { AppButton, AppScreen, Field, Panel, SectionHeading, Tag } from '@/components/mobile-ui';
import { palette } from '@/constants/palette';
import {
  ApiError,
  checkInGuest,
  createAdminUser,
  createBranch,
  createInventoryItem,
  createRoomOption,
  deleteAdminUser,
  deleteBranch,
  fetchAvailabilityDay,
  fetchOperations,
  updateAdminBookingStatus,
  updateAdminCrew,
  updateAdminUser,
  updateBookingSettings,
  updateBranch,
  updateEventType,
  updateFloorStatus,
  updateInventoryItem,
  updatePackage,
  updateRoomOption,
  updateServiceAdjustments,
} from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { OperationsPayload } from '@/lib/types';

type AdjustmentState = {
  duration_hours: string;
  extra_menu_bundles: string[];
  extra_add_ons: string[];
};

function normalizeAdjustmentState(booking: any): AdjustmentState {
  return {
    duration_hours: String(booking.duration_hours ?? 4),
    extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
    extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
  };
}

function defaultUserForm() {
  return {
    name: '',
    email: '',
    phone: '',
    role: 'customer',
    password: '',
    password_confirmation: '',
  };
}

function defaultBranchForm() {
  return {
    name: '',
    city: '',
    code: '',
    map_url: '',
    concurrent_limit: '2',
    max_guests: '40',
    supports: ['birthday', 'table'],
  };
}

export default function OperationsScreen() {
  const { user, token, booting } = useAuth();
  const [payload, setPayload] = useState<OperationsPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [dayAvailability, setDayAvailability] = useState<Record<string, any> | null>(null);
  const [selectedAvailability, setSelectedAvailability] = useState({ branchCode: '', date: '' });
  const [checkInCode, setCheckInCode] = useState('');
  const [serviceStatusState, setServiceStatusState] = useState<Record<number, string>>({});
  const [serviceAdjustmentsState, setServiceAdjustmentsState] = useState<Record<number, AdjustmentState>>({});
  const [adminStatusState, setAdminStatusState] = useState<Record<number, string>>({});
  const [adminCrewState, setAdminCrewState] = useState<Record<number, string>>({});
  const [adminAdjustmentState, setAdminAdjustmentState] = useState<Record<number, AdjustmentState>>({});
  const [userCreateForm, setUserCreateForm] = useState(defaultUserForm());
  const [accountEdits, setAccountEdits] = useState<Record<number, any>>({});
  const [branchCreateForm, setBranchCreateForm] = useState(defaultBranchForm());
  const [branchEdits, setBranchEdits] = useState<Record<number, any>>({});
  const [bookingSettingsForm, setBookingSettingsForm] = useState({
    opening_hour: '7',
    closing_hour: '23',
    default_duration_hours: '4',
  });
  const [roomOptionForm, setRoomOptionForm] = useState({
    label: '',
    description: '',
    preferred_event_type: '',
  });
  const [roomOptionState, setRoomOptionState] = useState<Record<number, any>>({});
  const [eventTypeState, setEventTypeState] = useState<Record<number, any>>({});
  const [packageState, setPackageState] = useState<Record<number, any>>({});
  const [inventoryForms, setInventoryForms] = useState<Record<number, any>>({});
  const [newInventoryForms, setNewInventoryForms] = useState<Record<number, any>>({});

  const loadOperations = useCallback(async (nextRefreshing = false) => {
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
      const response = await fetchOperations(token);
      setPayload(response);

      const staffBookings = response.staff?.todayBookings ?? [];
      setServiceStatusState(Object.fromEntries(staffBookings.map((booking: any) => [booking.id, booking.service_status])));
      setServiceAdjustmentsState(Object.fromEntries(staffBookings.map((booking: any) => [booking.id, normalizeAdjustmentState(booking)])));

      const adminBookings = [
        ...(response.admin?.bookings?.groupedBookings ?? []).flatMap((group: any) =>
          group.types.flatMap((typeGroup: any) => typeGroup.bookings),
        ),
        ...(response.admin?.confirmedEvents?.confirmedEvents ?? []),
      ];

      setAdminStatusState(Object.fromEntries(adminBookings.map((booking: any) => [booking.id, booking.status])));
      setAdminCrewState(Object.fromEntries(adminBookings.map((booking: any) => [booking.id, String(booking.assigned_staff_id ?? '')])));
      setAdminAdjustmentState(Object.fromEntries(adminBookings.map((booking: any) => [booking.id, normalizeAdjustmentState(booking)])));

      const users = response.admin?.accounts?.users ?? [];
      setAccountEdits(
        Object.fromEntries(
          users.map((account: any) => [
            account.id,
            {
              name: account.name ?? '',
              email: account.email ?? '',
              phone: account.phone ?? '',
              role: account.role ?? 'customer',
              password: '',
              password_confirmation: '',
            },
          ]),
        ),
      );

      const branches = response.admin?.branches?.branches ?? [];
      setBranchEdits(
        Object.fromEntries(
          branches.map((branch: any) => [
            branch.id,
            {
              name: branch.name ?? '',
              city: branch.city ?? '',
              map_url: branch.map_url ?? '',
              concurrent_limit: String(branch.concurrent_limit ?? 2),
              max_guests: String(branch.max_guests ?? 40),
              supports: Array.isArray(branch.supports) ? [...branch.supports] : [],
              is_active: Boolean(branch.is_active),
            },
          ]),
        ),
      );

      const bookingSettings = response.admin?.catalog?.bookingSettings;
      if (bookingSettings) {
        setBookingSettingsForm({
          opening_hour: String(bookingSettings.opening_hour ?? 7),
          closing_hour: String(bookingSettings.closing_hour ?? 23),
          default_duration_hours: String(bookingSettings.default_duration_hours ?? 4),
        });
      }

      const roomOptions = response.admin?.catalog?.roomOptions ?? [];
      setRoomOptionState(
        Object.fromEntries(
          roomOptions.map((item: any) => [
            item.id,
            {
              label: item.label ?? '',
              description: item.description ?? '',
              preferred_event_type: item.preferred_event_type ?? '',
              is_active: Boolean(item.is_active),
            },
          ]),
        ),
      );

      const eventTypes = response.admin?.catalog?.eventTypes ?? [];
      setEventTypeState(
        Object.fromEntries(
          eventTypes.map((eventType: any) => [
            eventType.id,
            {
              label: eventType.label ?? '',
              description: eventType.description ?? '',
              icon: eventType.icon ?? '',
              is_active: Boolean(eventType.is_active),
            },
          ]),
        ),
      );

      setPackageState(
        Object.fromEntries(
          eventTypes.flatMap((eventType: any) =>
            eventType.packages.map((item: any) => [
              item.id,
              {
                name: item.name ?? '',
                price: String(item.price ?? 0),
                guest_range: item.guest_range ?? '',
                features: Array.isArray(item.features) ? item.features.join('\n') : '',
                is_active: Boolean(item.is_active),
              },
            ]),
          ),
        ),
      );

      const inventory = response.admin?.reports?.inventory ?? [];
      setInventoryForms(
        Object.fromEntries(
          inventory.flatMap((branch: any) =>
            (branch.alerts ?? [])
              .filter((item: any) => item.id)
              .map((item: any) => [
                item.id,
                {
                  item: item.item ?? '',
                  stock: String(item.stock ?? 0),
                  threshold: String(item.threshold ?? 0),
                },
              ]),
          ),
        ),
      );
      setNewInventoryForms(
        Object.fromEntries(
          inventory
            .filter((branch: any) => branch.branch_id)
            .map((branch: any) => [
              branch.branch_id,
              {
                item: '',
                stock: '0',
                threshold: '0',
              },
            ]),
        ),
      );

      const initialBranch = response.admin?.availability?.initialBranch ?? response.admin?.availability?.availability?.branches?.[0]?.code ?? '';
      const initialDate = response.admin?.availability?.availability?.branches?.find((branch: any) => branch.code === initialBranch)?.dates?.[0]?.date ?? '';
      setSelectedAvailability({ branchCode: initialBranch, date: initialDate });
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to load role-based operations right now.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useEffect(() => {
    if (token) {
      void loadOperations();
    } else {
      setLoading(false);
    }
  }, [loadOperations, token]);

  useEffect(() => {
    if (!token || !selectedAvailability.branchCode || !selectedAvailability.date) {
      return;
    }

    if (!['admin', 'manager'].includes(payload?.role ?? '')) {
      return;
    }

    void (async () => {
      try {
        const response = await fetchAvailabilityDay(token, selectedAvailability.branchCode, selectedAvailability.date);
        setDayAvailability(response);
      } catch {
        setDayAvailability(null);
      }
    })();
  }, [payload?.role, selectedAvailability.branchCode, selectedAvailability.date, token]);

  function setAdjustmentState(
    setter: Dispatch<SetStateAction<Record<number, AdjustmentState>>>,
    bookingId: number,
    next: Partial<AdjustmentState>,
  ) {
    setter((current) => ({
      ...current,
      [bookingId]: {
        ...(current[bookingId] ?? { duration_hours: '4', extra_menu_bundles: [], extra_add_ons: [] }),
        ...next,
      },
    }));
  }

  function toggleArrayValue(values: string[], value: string) {
    return values.includes(value) ? values.filter((item) => item !== value) : [...values, value];
  }

  function getErrorMessage(error: unknown) {
    return error instanceof ApiError ? Object.values(error.errors ?? {})[0]?.[0] ?? error.message : 'Something went wrong.';
  }

  async function runAction(action: () => Promise<{ message: string } | void>, successTitle = 'Saved') {
    try {
      const response = await action();
      Alert.alert(successTitle, response?.message ?? 'Saved successfully.');
      await loadOperations();
    } catch (error) {
      Alert.alert('Action failed', getErrorMessage(error));
    }
  }

  if (booting) {
    return (
      <AppScreen eyebrow="Operations" title="Loading tools" subtitle="Checking role access and restoring your session.">
        <Panel>
          <Text style={styles.helper}>Preparing role-based operations...</Text>
        </Panel>
      </AppScreen>
    );
  }

  if (!user || !token) {
    return (
      <AppScreen
        eyebrow="Operations"
        title="Sign in for staff or admin tools"
        subtitle="This tab unlocks staff floor controls and admin management only after token-based mobile sign-in.">
        <Panel>
          <View style={styles.buttonStack}>
            <AppButton label="Sign in" onPress={() => router.push('/login')} />
            <AppButton label="Create account" onPress={() => router.push('/register')} tone="secondary" />
          </View>
        </Panel>
      </AppScreen>
    );
  }

  const staff = payload?.staff;
  const admin = payload?.admin;
  const adminStaffUsers = admin?.bookings?.staffUsers ?? admin?.confirmedEvents?.staffUsers ?? [];
  const adminMenuBundles = admin?.bookings?.menuBundles ?? [];
  const adminAddOns = admin?.bookings?.addOns ?? [];
  const branches = admin?.availability?.availability?.branches ?? [];
  const selectedBranch = branches.find((branch: any) => branch.code === selectedAvailability.branchCode) ?? branches[0];
  const selectedBranchDates = selectedBranch?.dates ?? [];

  return (
    <AppScreen
      eyebrow="Operations"
      title={`${user.role} tools`}
      subtitle="Unified mobile access to the same staff and admin capabilities already present in your Laravel system."
      scroll={false}
      rightSlot={<AppButton label="Refresh" onPress={() => void loadOperations()} tone="secondary" />}>
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadOperations(true)} tintColor={palette.brandRed} />}
        contentContainerStyle={{ gap: 20, paddingBottom: 32 }}>
        {errorMessage ? (
          <Panel style={styles.errorPanel}>
            <SectionHeading label="Connection issue" title="Operations data is unavailable right now" />
            <Text style={styles.errorText}>{errorMessage}</Text>
            <AppButton label="Reload operations" onPress={() => void loadOperations()} tone="secondary" />
          </Panel>
        ) : null}

        {loading && !payload ? (
          <Panel>
            <Text style={styles.helper}>Loading operations...</Text>
          </Panel>
        ) : null}

        {staff ? (
          <>
            <Panel>
              <SectionHeading label="Staff" title="Check-in and floor control" />
              <Field label="Booking reference or check-in code" value={checkInCode} onChangeText={setCheckInCode} />
              <AppButton
                label="Check in guest"
                onPress={() =>
                  void runAction(
                    () => checkInGuest(token, checkInCode),
                    'Guest checked in',
                  )
                }
              />
            </Panel>

            <Panel>
              <SectionHeading label="Staff" title="Prep list and alerts" />
              <View style={styles.stack}>
                {(staff.notifications ?? []).map((item: any) => (
                  <View key={`alert-${item.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{item.booking_reference}</Text>
                    <Text style={styles.cardMeta}>{item.package_name} · {item.branch}</Text>
                    <Text style={styles.cardMeta}>{item.event_date} · {item.event_time}</Text>
                    <Text style={styles.helper}>{item.message}</Text>
                  </View>
                ))}
                {(staff.prepList ?? []).map((item: any) => (
                  <View key={`prep-${item.booking_reference}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{item.booking_reference}</Text>
                    <Text style={styles.cardMeta}>{item.time} · {item.branch}</Text>
                    <Text style={styles.helper}>{item.reminder}</Text>
                    {(item.items ?? []).map((prep: string) => (
                      <Text key={`${item.booking_reference}-${prep}`} style={styles.helper}>• {prep}</Text>
                    ))}
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Staff" title="Live floor management" />
              <View style={styles.stack}>
                {(staff.todayBookings ?? []).map((booking: any) => (
                  <View key={`staff-${booking.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{booking.booking_reference}</Text>
                    <Text style={styles.cardMeta}>{booking.package_name} · {booking.branch}</Text>
                    <Text style={styles.cardMeta}>{booking.event_date} · {booking.event_time}</Text>
                    <Text style={styles.helper}>Customer: {booking.customer_name}</Text>
                    <View style={styles.tagWrap}>
                      {(staff.statusOptions ?? []).map((status: string) => (
                        <Tag
                          key={`${booking.id}-${status}`}
                          label={status.replace('_', ' ')}
                          active={serviceStatusState[booking.id] === status}
                          onPress={() => setServiceStatusState((current) => ({ ...current, [booking.id]: status }))}
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save floor status"
                      onPress={() =>
                        void runAction(
                          () => updateFloorStatus(token, booking.id, serviceStatusState[booking.id]),
                          'Floor status updated',
                        )
                      }
                      tone="secondary"
                    />
                    <Field
                      label="Duration hours"
                      value={serviceAdjustmentsState[booking.id]?.duration_hours ?? '4'}
                      onChangeText={(value) => setAdjustmentState(setServiceAdjustmentsState, booking.id, { duration_hours: value })}
                      keyboardType="numeric"
                    />
                    <Text style={styles.helperStrong}>Extra food</Text>
                    <View style={styles.tagWrap}>
                      {(staff.menuBundles ?? []).map((bundle: any) => (
                        <Tag
                          key={`${booking.id}-${bundle.code}`}
                          label={bundle.name}
                          active={serviceAdjustmentsState[booking.id]?.extra_menu_bundles?.includes(bundle.code)}
                          onPress={() =>
                            setAdjustmentState(setServiceAdjustmentsState, booking.id, {
                              extra_menu_bundles: toggleArrayValue(serviceAdjustmentsState[booking.id]?.extra_menu_bundles ?? [], bundle.code),
                            })
                          }
                        />
                      ))}
                    </View>
                    <Text style={styles.helperStrong}>Extra services</Text>
                    <View style={styles.tagWrap}>
                      {(staff.addOns ?? []).map((item: any) => (
                        <Tag
                          key={`${booking.id}-${item.code}`}
                          label={item.name}
                          active={serviceAdjustmentsState[booking.id]?.extra_add_ons?.includes(item.code)}
                          onPress={() =>
                            setAdjustmentState(setServiceAdjustmentsState, booking.id, {
                              extra_add_ons: toggleArrayValue(serviceAdjustmentsState[booking.id]?.extra_add_ons ?? [], item.code),
                            })
                          }
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save event edits"
                      onPress={() =>
                        void runAction(
                          () =>
                            updateServiceAdjustments(token, booking.id, {
                              duration_hours: Number(serviceAdjustmentsState[booking.id]?.duration_hours ?? 4),
                              extra_menu_bundles: serviceAdjustmentsState[booking.id]?.extra_menu_bundles ?? [],
                              extra_add_ons: serviceAdjustmentsState[booking.id]?.extra_add_ons ?? [],
                            }),
                          'Live event updates saved',
                        )
                      }
                      tone="ghost"
                    />
                  </View>
                ))}
              </View>
            </Panel>
          </>
        ) : null}

        {admin ? (
          <>
            <Panel>
              <SectionHeading label="Admin" title="Hub overview" />
              <View style={styles.metricGrid}>
                {(admin.dashboard?.stats ?? []).map((stat: any) => (
                  <View key={stat.label} style={styles.metricCard}>
                    <Text style={styles.metricLabel}>{stat.label}</Text>
                    <Text style={styles.metricValue}>{stat.value}</Text>
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Pending bookings" />
              <View style={styles.stack}>
                {(admin.bookings?.groupedBookings ?? []).flatMap((group: any) =>
                  group.types.flatMap((typeGroup: any) => typeGroup.bookings),
                ).map((booking: any) => (
                  <View key={`pending-${booking.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{booking.booking_reference}</Text>
                    <Text style={styles.cardMeta}>{booking.customer_name} · {booking.package_name}</Text>
                    <Text style={styles.cardMeta}>{booking.branch} · {booking.event_date} · {booking.event_time}</Text>
                    <Text style={styles.helper}>Contact: {booking.customer_email} · {booking.customer_phone}</Text>
                    <Text style={styles.helper}>{booking.notes || 'No notes'}</Text>
                    <Text style={styles.helperStrong}>Status</Text>
                    <View style={styles.tagWrap}>
                      {['pending_review', 'confirmed', 'cancelled'].map((status) => (
                        <Tag
                          key={`${booking.id}-${status}`}
                          label={status.replace('_', ' ')}
                          active={adminStatusState[booking.id] === status}
                          onPress={() => setAdminStatusState((current) => ({ ...current, [booking.id]: status }))}
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save booking status"
                      onPress={() =>
                        void runAction(
                          () => updateAdminBookingStatus(token, booking.id, adminStatusState[booking.id]),
                          'Booking status updated',
                        )
                      }
                      tone="secondary"
                    />
                    <Text style={styles.helperStrong}>Crew assignment</Text>
                    <View style={styles.tagWrap}>
                      <Tag label="Unassigned" active={adminCrewState[booking.id] === ''} onPress={() => setAdminCrewState((current) => ({ ...current, [booking.id]: '' }))} />
                      {adminStaffUsers.map((staffUser: any) => (
                        <Tag
                          key={`${booking.id}-crew-${staffUser.id}`}
                          label={`${staffUser.name} (${staffUser.role})`}
                          active={adminCrewState[booking.id] === String(staffUser.id)}
                          onPress={() => setAdminCrewState((current) => ({ ...current, [booking.id]: String(staffUser.id) }))}
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save crew"
                      onPress={() =>
                        void runAction(
                          () => updateAdminCrew(token, booking.id, adminCrewState[booking.id] ? Number(adminCrewState[booking.id]) : null),
                          'Crew assignment updated',
                        )
                      }
                      tone="ghost"
                    />
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Confirmed events" />
              <View style={styles.stack}>
                {(admin.confirmedEvents?.confirmedEvents ?? []).map((booking: any) => (
                  <View key={`confirmed-${booking.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{booking.booking_reference}</Text>
                    <Text style={styles.cardMeta}>{booking.customer_name} · {booking.package_name}</Text>
                    <Text style={styles.cardMeta}>{booking.branch} · {booking.event_date} · {booking.event_time}</Text>
                    <Text style={styles.helper}>Assigned crew: {booking.assigned_staff_name || 'Unassigned'}</Text>
                    <Text style={styles.helperStrong}>Status</Text>
                    <View style={styles.tagWrap}>
                      {['confirmed', 'rescheduled', 'checked_in', 'completed', 'cancelled'].map((status) => (
                        <Tag
                          key={`${booking.id}-${status}`}
                          label={status.replace('_', ' ')}
                          active={adminStatusState[booking.id] === status}
                          onPress={() => setAdminStatusState((current) => ({ ...current, [booking.id]: status }))}
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save event status"
                      onPress={() =>
                        void runAction(
                          () => updateAdminBookingStatus(token, booking.id, adminStatusState[booking.id]),
                          'Event status updated',
                        )
                      }
                      tone="secondary"
                    />
                    <Field
                      label="Duration hours"
                      value={adminAdjustmentState[booking.id]?.duration_hours ?? '4'}
                      onChangeText={(value) => setAdjustmentState(setAdminAdjustmentState, booking.id, { duration_hours: value })}
                      keyboardType="numeric"
                    />
                    <Text style={styles.helperStrong}>Extra food</Text>
                    <View style={styles.tagWrap}>
                      {adminMenuBundles.map((bundle: any) => (
                        <Tag
                          key={`${booking.id}-${bundle.code}`}
                          label={bundle.name}
                          active={adminAdjustmentState[booking.id]?.extra_menu_bundles?.includes(bundle.code)}
                          onPress={() =>
                            setAdjustmentState(setAdminAdjustmentState, booking.id, {
                              extra_menu_bundles: toggleArrayValue(adminAdjustmentState[booking.id]?.extra_menu_bundles ?? [], bundle.code),
                            })
                          }
                        />
                      ))}
                    </View>
                    <Text style={styles.helperStrong}>Extra services</Text>
                    <View style={styles.tagWrap}>
                      {adminAddOns.map((item: any) => (
                        <Tag
                          key={`${booking.id}-${item.code}`}
                          label={item.name}
                          active={adminAdjustmentState[booking.id]?.extra_add_ons?.includes(item.code)}
                          onPress={() =>
                            setAdjustmentState(setAdminAdjustmentState, booking.id, {
                              extra_add_ons: toggleArrayValue(adminAdjustmentState[booking.id]?.extra_add_ons ?? [], item.code),
                            })
                          }
                        />
                      ))}
                    </View>
                    <AppButton
                      label="Save event edits"
                      onPress={() =>
                        void runAction(
                          () =>
                            updateServiceAdjustments(token, booking.id, {
                              duration_hours: Number(adminAdjustmentState[booking.id]?.duration_hours ?? 4),
                              extra_menu_bundles: adminAdjustmentState[booking.id]?.extra_menu_bundles ?? [],
                              extra_add_ons: adminAdjustmentState[booking.id]?.extra_add_ons ?? [],
                            }),
                          'Live event updates saved',
                        )
                      }
                      tone="ghost"
                    />
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Availability" />
              <Text style={styles.helperStrong}>Branches</Text>
              <View style={styles.tagWrap}>
                {branches.map((branch: any) => (
                  <Tag
                    key={branch.code}
                    label={branch.name}
                    active={selectedAvailability.branchCode === branch.code}
                    onPress={() =>
                      setSelectedAvailability({
                        branchCode: branch.code,
                        date: branch.dates?.[0]?.date ?? '',
                      })
                    }
                  />
                ))}
              </View>
              <Text style={styles.helperStrong}>Dates</Text>
              <View style={styles.tagWrap}>
                {selectedBranchDates.slice(0, 12).map((item: any) => (
                  <Tag
                    key={item.date}
                    label={`${item.date} (${item.available_slots})`}
                    active={selectedAvailability.date === item.date}
                    onPress={() => setSelectedAvailability((current) => ({ ...current, date: item.date }))}
                  />
                ))}
              </View>
              {dayAvailability ? (
                <View style={styles.stack}>
                  <Text style={styles.cardTitle}>{dayAvailability.branch?.name} · {dayAvailability.date}</Text>
                  {(dayAvailability.time_slots ?? []).slice(0, 10).map((slot: any) => (
                    <View key={`${dayAvailability.date}-${slot.time}`} style={styles.cardSoft}>
                      <Text style={styles.cardTitle}>{slot.label}</Text>
                      <Text style={styles.cardMeta}>{slot.status} · {slot.remaining_capacity} remaining</Text>
                      <Text style={styles.helper}>Rooms: {(slot.available_rooms ?? []).join(', ') || 'None'}</Text>
                    </View>
                  ))}
                </View>
              ) : null}
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Accounts" />
              {admin.accounts?.canManageAccounts ? (
                <View style={styles.stack}>
                  <Field label="Name" value={userCreateForm.name} onChangeText={(value) => setUserCreateForm((current) => ({ ...current, name: value }))} />
                  <Field label="Email" value={userCreateForm.email} onChangeText={(value) => setUserCreateForm((current) => ({ ...current, email: value }))} keyboardType="email-address" />
                  <Field label="Phone" value={userCreateForm.phone} onChangeText={(value) => setUserCreateForm((current) => ({ ...current, phone: value }))} />
                  <Text style={styles.helperStrong}>Role</Text>
                  <View style={styles.tagWrap}>
                    {['customer', 'staff', 'manager', 'admin'].map((role) => (
                      <Tag key={role} label={role} active={userCreateForm.role === role} onPress={() => setUserCreateForm((current) => ({ ...current, role }))} />
                    ))}
                  </View>
                  <Field label="Password" value={userCreateForm.password} onChangeText={(value) => setUserCreateForm((current) => ({ ...current, password: value }))} secureTextEntry />
                  <Field label="Confirm password" value={userCreateForm.password_confirmation} onChangeText={(value) => setUserCreateForm((current) => ({ ...current, password_confirmation: value }))} secureTextEntry />
                  <AppButton
                    label="Create account"
                    onPress={() =>
                      void runAction(
                        async () => {
                          const response = await createAdminUser(token, userCreateForm);
                          setUserCreateForm(defaultUserForm());
                          return response;
                        },
                        'Account created',
                      )
                    }
                  />
                </View>
              ) : null}

              <View style={styles.stack}>
                {(admin.accounts?.users ?? []).map((account: any) => (
                  <View key={`account-${account.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{account.name}</Text>
                    <Text style={styles.cardMeta}>{account.email} · {account.role}</Text>
                    <Field label="Name" value={accountEdits[account.id]?.name ?? ''} onChangeText={(value) => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], name: value } }))} />
                    <Field label="Email" value={accountEdits[account.id]?.email ?? ''} onChangeText={(value) => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], email: value } }))} keyboardType="email-address" />
                    <Field label="Phone" value={accountEdits[account.id]?.phone ?? ''} onChangeText={(value) => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], phone: value } }))} />
                    {admin.accounts?.canManageAccounts ? (
                      <>
                        <View style={styles.tagWrap}>
                          {['customer', 'staff', 'manager', 'admin'].map((role) => (
                            <Tag key={`${account.id}-${role}`} label={role} active={accountEdits[account.id]?.role === role} onPress={() => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], role } }))} />
                          ))}
                        </View>
                        <Field label="New password" value={accountEdits[account.id]?.password ?? ''} onChangeText={(value) => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], password: value } }))} secureTextEntry />
                        <Field label="Confirm password" value={accountEdits[account.id]?.password_confirmation ?? ''} onChangeText={(value) => setAccountEdits((current) => ({ ...current, [account.id]: { ...current[account.id], password_confirmation: value } }))} secureTextEntry />
                        <View style={styles.buttonStack}>
                          <AppButton label="Save account" onPress={() => void runAction(() => updateAdminUser(token, account.id, accountEdits[account.id]), 'Account updated')} tone="secondary" />
                          <AppButton label="Delete account" onPress={() => void runAction(() => deleteAdminUser(token, account.id), 'Account deleted')} tone="ghost" />
                        </View>
                      </>
                    ) : null}
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Branches and inventory" />
              <View style={styles.stack}>
                <Field label="Branch name" value={branchCreateForm.name} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, name: value }))} />
                <Field label="City" value={branchCreateForm.city} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, city: value }))} />
                <Field label="Code" value={branchCreateForm.code} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, code: value }))} />
                <Field label="Map URL" value={branchCreateForm.map_url} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, map_url: value }))} />
                <Field label="Concurrent limit" value={branchCreateForm.concurrent_limit} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, concurrent_limit: value }))} keyboardType="numeric" />
                <Field label="Max guests" value={branchCreateForm.max_guests} onChangeText={(value) => setBranchCreateForm((current) => ({ ...current, max_guests: value }))} keyboardType="numeric" />
                <View style={styles.tagWrap}>
                  {Object.keys(admin.catalog?.eventTypes?.reduce((carry: Record<string, any>, item: any) => ({ ...carry, [item.code]: true }), {}) ?? { birthday: true, business: true, table: true }).map((typeCode) => (
                    <Tag
                      key={`new-branch-${typeCode}`}
                      label={typeCode}
                      active={branchCreateForm.supports.includes(typeCode)}
                      onPress={() => setBranchCreateForm((current) => ({ ...current, supports: toggleArrayValue(current.supports, typeCode) }))}
                    />
                  ))}
                </View>
                <AppButton
                  label="Add branch"
                  onPress={() =>
                    void runAction(
                      async () => {
                        const response = await createBranch(token, {
                          ...branchCreateForm,
                          concurrent_limit: Number(branchCreateForm.concurrent_limit),
                          max_guests: Number(branchCreateForm.max_guests),
                        });
                        setBranchCreateForm(defaultBranchForm());
                        return response;
                      },
                      'Branch created',
                    )
                  }
                />
              </View>

              <View style={styles.stack}>
                {(admin.branches?.branches ?? []).map((branch: any) => (
                  <View key={`branch-${branch.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{branch.name}</Text>
                    <Text style={styles.cardMeta}>{branch.code} · {branch.city}</Text>
                    <Field label="Branch name" value={branchEdits[branch.id]?.name ?? ''} onChangeText={(value) => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], name: value } }))} />
                    <Field label="City" value={branchEdits[branch.id]?.city ?? ''} onChangeText={(value) => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], city: value } }))} />
                    <Field label="Map URL" value={branchEdits[branch.id]?.map_url ?? ''} onChangeText={(value) => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], map_url: value } }))} />
                    <Field label="Concurrent limit" value={branchEdits[branch.id]?.concurrent_limit ?? '2'} onChangeText={(value) => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], concurrent_limit: value } }))} keyboardType="numeric" />
                    <Field label="Max guests" value={branchEdits[branch.id]?.max_guests ?? '40'} onChangeText={(value) => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], max_guests: value } }))} keyboardType="numeric" />
                    <View style={styles.tagWrap}>
                      {Object.keys(admin.catalog?.eventTypes?.reduce((carry: Record<string, any>, item: any) => ({ ...carry, [item.code]: true }), {}) ?? { birthday: true, business: true, table: true }).map((typeCode) => (
                        <Tag
                          key={`${branch.id}-${typeCode}`}
                          label={typeCode}
                          active={branchEdits[branch.id]?.supports?.includes(typeCode)}
                          onPress={() => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], supports: toggleArrayValue(current[branch.id]?.supports ?? [], typeCode) } }))}
                        />
                      ))}
                    </View>
                    <View style={styles.tagWrap}>
                      <Tag label="Active" active={branchEdits[branch.id]?.is_active} onPress={() => setBranchEdits((current) => ({ ...current, [branch.id]: { ...current[branch.id], is_active: !current[branch.id]?.is_active } }))} />
                    </View>
                    <View style={styles.buttonStack}>
                      <AppButton
                        label="Save branch"
                        onPress={() =>
                          void runAction(
                            () =>
                              updateBranch(token, branch.id, {
                                ...branchEdits[branch.id],
                                concurrent_limit: Number(branchEdits[branch.id]?.concurrent_limit ?? 2),
                                max_guests: Number(branchEdits[branch.id]?.max_guests ?? 40),
                              }),
                            'Branch updated',
                          )
                        }
                        tone="secondary"
                      />
                      <AppButton label="Delete branch" onPress={() => void runAction(() => deleteBranch(token, branch.id), 'Branch deleted')} tone="ghost" />
                    </View>
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Catalog" />
              <Field label="Opening hour" value={bookingSettingsForm.opening_hour} onChangeText={(value) => setBookingSettingsForm((current) => ({ ...current, opening_hour: value }))} keyboardType="numeric" />
              <Field label="Closing hour" value={bookingSettingsForm.closing_hour} onChangeText={(value) => setBookingSettingsForm((current) => ({ ...current, closing_hour: value }))} keyboardType="numeric" />
              <Field label="Default duration hours" value={bookingSettingsForm.default_duration_hours} onChangeText={(value) => setBookingSettingsForm((current) => ({ ...current, default_duration_hours: value }))} keyboardType="numeric" />
              <AppButton
                label="Save booking hours"
                onPress={() =>
                  void runAction(
                    () =>
                      updateBookingSettings(token, {
                        opening_hour: Number(bookingSettingsForm.opening_hour),
                        closing_hour: Number(bookingSettingsForm.closing_hour),
                        default_duration_hours: Number(bookingSettingsForm.default_duration_hours),
                      }),
                    'Booking settings updated',
                  )
                }
                tone="secondary"
              />

              <Field label="New room label" value={roomOptionForm.label} onChangeText={(value) => setRoomOptionForm((current) => ({ ...current, label: value }))} />
              <Field label="New room description" value={roomOptionForm.description} onChangeText={(value) => setRoomOptionForm((current) => ({ ...current, description: value }))} multiline />
              <View style={styles.tagWrap}>
                <Tag label="No preferred type" active={roomOptionForm.preferred_event_type === ''} onPress={() => setRoomOptionForm((current) => ({ ...current, preferred_event_type: '' }))} />
                {(admin.catalog?.eventTypes ?? []).map((eventType: any) => (
                  <Tag
                    key={`new-room-${eventType.id}`}
                    label={eventType.label}
                    active={roomOptionForm.preferred_event_type === eventType.code}
                    onPress={() => setRoomOptionForm((current) => ({ ...current, preferred_event_type: eventType.code }))}
                  />
                ))}
              </View>
              <AppButton
                label="Add room option"
                onPress={() =>
                  void runAction(
                    async () => {
                      const response = await createRoomOption(token, roomOptionForm);
                      setRoomOptionForm({ label: '', description: '', preferred_event_type: '' });
                      return response;
                    },
                    'Room option created',
                  )
                }
              />

              <View style={styles.stack}>
                {(admin.catalog?.eventTypes ?? []).map((eventType: any) => (
                  <View key={`event-type-${eventType.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{eventType.code}</Text>
                    <Field label="Label" value={eventTypeState[eventType.id]?.label ?? ''} onChangeText={(value) => setEventTypeState((current) => ({ ...current, [eventType.id]: { ...current[eventType.id], label: value } }))} />
                    <Field label="Icon" value={eventTypeState[eventType.id]?.icon ?? ''} onChangeText={(value) => setEventTypeState((current) => ({ ...current, [eventType.id]: { ...current[eventType.id], icon: value } }))} />
                    <Field label="Description" value={eventTypeState[eventType.id]?.description ?? ''} onChangeText={(value) => setEventTypeState((current) => ({ ...current, [eventType.id]: { ...current[eventType.id], description: value } }))} multiline />
                    <View style={styles.tagWrap}>
                      <Tag label="Available" active={eventTypeState[eventType.id]?.is_active} onPress={() => setEventTypeState((current) => ({ ...current, [eventType.id]: { ...current[eventType.id], is_active: true } }))} />
                      <Tag label="Unavailable" active={!eventTypeState[eventType.id]?.is_active} onPress={() => setEventTypeState((current) => ({ ...current, [eventType.id]: { ...current[eventType.id], is_active: false } }))} />
                    </View>
                    <AppButton label="Save event type" onPress={() => void runAction(() => updateEventType(token, eventType.id, eventTypeState[eventType.id]), 'Event type updated')} tone="secondary" />
                    {(eventType.packages ?? []).map((item: any) => (
                      <View key={`package-${item.id}`} style={styles.innerCard}>
                        <Text style={styles.cardTitle}>{item.code}</Text>
                        <Field label="Package name" value={packageState[item.id]?.name ?? ''} onChangeText={(value) => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], name: value } }))} />
                        <Field label="Price" value={packageState[item.id]?.price ?? '0'} onChangeText={(value) => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], price: value } }))} keyboardType="numeric" />
                        <Field label="Guest range" value={packageState[item.id]?.guest_range ?? ''} onChangeText={(value) => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], guest_range: value } }))} />
                        <Field label="Features (one per line)" value={packageState[item.id]?.features ?? ''} onChangeText={(value) => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], features: value } }))} multiline />
                        <View style={styles.tagWrap}>
                          <Tag label="Available" active={packageState[item.id]?.is_active} onPress={() => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], is_active: true } }))} />
                          <Tag label="Unavailable" active={!packageState[item.id]?.is_active} onPress={() => setPackageState((current) => ({ ...current, [item.id]: { ...current[item.id], is_active: false } }))} />
                        </View>
                        <AppButton
                          label="Save package"
                          onPress={() =>
                            void runAction(
                              () =>
                                updatePackage(token, item.id, {
                                  ...packageState[item.id],
                                  price: Number(packageState[item.id]?.price ?? 0),
                                }),
                              'Package updated',
                            )
                          }
                          tone="ghost"
                        />
                      </View>
                    ))}
                  </View>
                ))}

                {(admin.catalog?.roomOptions ?? []).map((roomOption: any) => (
                  <View key={`room-${roomOption.id}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{roomOption.code}</Text>
                    <Field label="Room label" value={roomOptionState[roomOption.id]?.label ?? ''} onChangeText={(value) => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], label: value } }))} />
                    <Field label="Description" value={roomOptionState[roomOption.id]?.description ?? ''} onChangeText={(value) => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], description: value } }))} multiline />
                    <View style={styles.tagWrap}>
                      <Tag label="No preferred type" active={(roomOptionState[roomOption.id]?.preferred_event_type ?? '') === ''} onPress={() => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], preferred_event_type: '' } }))} />
                      {(admin.catalog?.eventTypes ?? []).map((eventType: any) => (
                        <Tag
                          key={`${roomOption.id}-${eventType.code}`}
                          label={eventType.label}
                          active={roomOptionState[roomOption.id]?.preferred_event_type === eventType.code}
                          onPress={() => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], preferred_event_type: eventType.code } }))}
                        />
                      ))}
                    </View>
                    <View style={styles.tagWrap}>
                      <Tag label="Available" active={roomOptionState[roomOption.id]?.is_active} onPress={() => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], is_active: true } }))} />
                      <Tag label="Unavailable" active={!roomOptionState[roomOption.id]?.is_active} onPress={() => setRoomOptionState((current) => ({ ...current, [roomOption.id]: { ...current[roomOption.id], is_active: false } }))} />
                    </View>
                    <AppButton label="Save room option" onPress={() => void runAction(() => updateRoomOption(token, roomOption.id, roomOptionState[roomOption.id]), 'Room option updated')} tone="secondary" />
                  </View>
                ))}
              </View>
            </Panel>

            <Panel>
              <SectionHeading label="Admin" title="Reports and timeline" />
              <Text style={styles.helperStrong}>Analytics</Text>
              {(admin.reports?.report?.top_event_types ?? []).map((item: any) => (
                <Text key={`top-${item.type}`} style={styles.helper}>{item.type}: {item.count}</Text>
              ))}
              {(admin.reports?.report?.branch_mix ?? []).map((item: any) => (
                <Text key={`branch-mix-${item.branch}`} style={styles.helper}>{item.branch}: {item.count}</Text>
              ))}

              <Text style={styles.helperStrong}>Inventory</Text>
              <View style={styles.stack}>
                {(admin.reports?.inventory ?? []).map((branch: any) => (
                  <View key={`inventory-${branch.branch}`} style={styles.cardSoft}>
                    <Text style={styles.cardTitle}>{branch.branch}</Text>
                    {(branch.alerts ?? []).map((item: any) => (
                      <View key={`inventory-item-${item.id ?? item.item}`} style={styles.innerCard}>
                        <Field label="Item" value={inventoryForms[item.id]?.item ?? item.item ?? ''} onChangeText={(value) => setInventoryForms((current) => ({ ...current, [item.id]: { ...current[item.id], item: value } }))} />
                        <Field label="Stock" value={inventoryForms[item.id]?.stock ?? String(item.stock ?? 0)} onChangeText={(value) => setInventoryForms((current) => ({ ...current, [item.id]: { ...current[item.id], stock: value } }))} keyboardType="numeric" />
                        <Field label="Threshold" value={inventoryForms[item.id]?.threshold ?? String(item.threshold ?? 0)} onChangeText={(value) => setInventoryForms((current) => ({ ...current, [item.id]: { ...current[item.id], threshold: value } }))} keyboardType="numeric" />
                        {item.id ? (
                          <AppButton
                            label="Save inventory item"
                            onPress={() =>
                              void runAction(
                                () =>
                                  updateInventoryItem(token, item.id, {
                                    item: inventoryForms[item.id]?.item ?? item.item,
                                    stock: Number(inventoryForms[item.id]?.stock ?? item.stock ?? 0),
                                    threshold: Number(inventoryForms[item.id]?.threshold ?? item.threshold ?? 0),
                                  }),
                                'Inventory item updated',
                              )
                            }
                            tone="ghost"
                          />
                        ) : null}
                      </View>
                    ))}
                    {branch.branch_id ? (
                      <View style={styles.innerCard}>
                        <Field label="New inventory item" value={newInventoryForms[branch.branch_id]?.item ?? ''} onChangeText={(value) => setNewInventoryForms((current) => ({ ...current, [branch.branch_id]: { ...current[branch.branch_id], item: value } }))} />
                        <Field label="Stock" value={newInventoryForms[branch.branch_id]?.stock ?? '0'} onChangeText={(value) => setNewInventoryForms((current) => ({ ...current, [branch.branch_id]: { ...current[branch.branch_id], stock: value } }))} keyboardType="numeric" />
                        <Field label="Threshold" value={newInventoryForms[branch.branch_id]?.threshold ?? '0'} onChangeText={(value) => setNewInventoryForms((current) => ({ ...current, [branch.branch_id]: { ...current[branch.branch_id], threshold: value } }))} keyboardType="numeric" />
                        <AppButton
                          label="Add inventory item"
                          onPress={() =>
                            void runAction(
                              async () => {
                                const response = await createInventoryItem(token, branch.branch_id, {
                                  item: newInventoryForms[branch.branch_id]?.item ?? '',
                                  stock: Number(newInventoryForms[branch.branch_id]?.stock ?? 0),
                                  threshold: Number(newInventoryForms[branch.branch_id]?.threshold ?? 0),
                                });
                                setNewInventoryForms((current) => ({ ...current, [branch.branch_id]: { item: '', stock: '0', threshold: '0' } }));
                                return response;
                              },
                              'Inventory item added',
                            )
                          }
                          tone="secondary"
                        />
                      </View>
                    ) : null}
                  </View>
                ))}
              </View>

              <Text style={styles.helperStrong}>Staff assignments</Text>
              {(admin.reports?.staffAssignments ?? []).map((item: any) => (
                <Text key={`assignment-${item.booking_reference}`} style={styles.helper}>{item.booking_reference}: {item.host} · {item.branch} · {item.slot}</Text>
              ))}

              <Text style={styles.helperStrong}>Timeline</Text>
              {(admin.timeline?.notifications ?? []).slice(0, 4).map((item: any) => (
                <Text key={`timeline-notification-${item.id}`} style={styles.helper}>{item.booking_reference}: {item.message}</Text>
              ))}
              {(admin.timeline?.history ?? []).slice(0, 4).map((item: any) => (
                <Text key={`timeline-history-${item.id}`} style={styles.helper}>{item.booking_reference}: {item.status} · {item.service_status}</Text>
              ))}
              {(admin.timeline?.cancelledEvents ?? []).slice(0, 4).map((item: any) => (
                <Text key={`timeline-cancelled-${item.id}`} style={styles.helper}>{item.booking_reference}: {item.cancelled_note || 'No cancellation note'}</Text>
              ))}
            </Panel>
          </>
        ) : null}
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
  helperStrong: {
    color: palette.ink,
    fontWeight: '900',
    fontSize: 14,
  },
  buttonStack: {
    gap: 12,
  },
  stack: {
    gap: 12,
  },
  cardSoft: {
    backgroundColor: '#FFF8EA',
    borderRadius: 20,
    padding: 14,
    gap: 10,
  },
  innerCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 12,
    gap: 10,
  },
  cardTitle: {
    color: palette.ink,
    fontWeight: '900',
    fontSize: 17,
  },
  cardMeta: {
    color: palette.inkMuted,
    lineHeight: 19,
  },
  tagWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  metricGrid: {
    gap: 12,
  },
  metricCard: {
    backgroundColor: '#FFF8EA',
    borderRadius: 18,
    padding: 14,
  },
  metricLabel: {
    color: palette.inkMuted,
    fontSize: 13,
    fontWeight: '700',
  },
  metricValue: {
    color: palette.ink,
    fontSize: 26,
    fontWeight: '900',
  },
});
