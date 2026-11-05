import type {
  BookingOptionsPayload,
  DashboardPayload,
  EventInvitation,
  HomePayload,
  MobileUser,
  OperationsPayload,
  ProfilePayload,
  ReservationRecord,
} from '@/lib/types';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

const apiBaseUrlStorageKey = 'mcdonalds-reservations:api-base-url';

function apiBaseUrlFromHost(hostUri?: string | null) {
  if (!hostUri) {
    return null;
  }

  const host = hostUri
    .replace(/^https?:\/\//, '')
    .split('/')[0]
    ?.split(':')[0];

  if (!host || ['localhost', '127.0.0.1', '0.0.0.0'].includes(host)) {
    return null;
  }

  return `http://${host}:8000`;
}

function getExpoDevServerApiBaseUrl() {
  const constants = Constants as any;
  const hostCandidates = [
    Constants.expoConfig?.hostUri,
    constants.expoGoConfig?.debuggerHost,
    constants.manifest?.debuggerHost,
    constants.manifest2?.extra?.expoGo?.debuggerHost,
  ];

  for (const candidate of hostCandidates) {
    const baseUrl = apiBaseUrlFromHost(candidate);

    if (baseUrl) {
      return baseUrl;
    }
  }

  return null;
}

function isLocalDevelopmentApiBaseUrl(apiBaseUrl?: string | null) {
  if (!apiBaseUrl) {
    return false;
  }

  try {
    const hostname = new URL(apiBaseUrl).hostname;

    if (['localhost', '127.0.0.1', '0.0.0.0', '10.0.2.2'].includes(hostname)) {
      return true;
    }

    if (hostname.startsWith('10.') || hostname.startsWith('192.168.')) {
      return true;
    }

    const private172Range = hostname.match(/^172\.(\d{1,2})\./);

    if (private172Range) {
      const secondOctet = Number(private172Range[1]);
      return secondOctet >= 16 && secondOctet <= 31;
    }
  } catch {
    return false;
  }

  return false;
}

function getFallbackApiBaseUrl() {
  const expoDevServerBaseUrl = getExpoDevServerApiBaseUrl();

  if (expoDevServerBaseUrl) {
    return expoDevServerBaseUrl;
  }

  if (Platform.OS === 'android') {
    return 'http://10.0.2.2:8000';
  }

  return 'http://127.0.0.1:8000';
}

function resolveApiBaseUrl() {
  const configuredApiBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || '';
  const expoDevServerBaseUrl = getExpoDevServerApiBaseUrl();

  if (expoDevServerBaseUrl && (!configuredApiBaseUrl || isLocalDevelopmentApiBaseUrl(configuredApiBaseUrl))) {
    return expoDevServerBaseUrl;
  }

  return configuredApiBaseUrl || getFallbackApiBaseUrl();
}

function normalizeApiBaseUrl(value: string) {
  const trimmedValue = value.trim();

  if (!trimmedValue) {
    throw new Error('Enter the server address shown on the computer.');
  }

  const candidate = /^[a-z][a-z\d+.-]*:\/\//i.test(trimmedValue)
    ? trimmedValue
    : `http://${trimmedValue}`;
  const parsed = new URL(candidate);

  if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname) {
    throw new Error('Use a valid HTTP or HTTPS server address.');
  }

  parsed.search = '';
  parsed.hash = '';

  return parsed.toString().replace(/\/$/, '');
}

let apiBaseUrl = normalizeApiBaseUrl(resolveApiBaseUrl());
let apiBaseUrlHydration: Promise<string> | null = null;

async function hydrateApiBaseUrl() {
  if (!apiBaseUrlHydration) {
    apiBaseUrlHydration = AsyncStorage.getItem(apiBaseUrlStorageKey)
      .then(async (storedApiBaseUrl) => {
        const configuredApiBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || '';

        if (
          storedApiBaseUrl
          && configuredApiBaseUrl
          && isLocalDevelopmentApiBaseUrl(storedApiBaseUrl)
          && !isLocalDevelopmentApiBaseUrl(configuredApiBaseUrl)
        ) {
          apiBaseUrl = normalizeApiBaseUrl(configuredApiBaseUrl);
          await AsyncStorage.setItem(apiBaseUrlStorageKey, apiBaseUrl);

          return apiBaseUrl;
        }

        if (storedApiBaseUrl) {
          apiBaseUrl = normalizeApiBaseUrl(storedApiBaseUrl);
        }

        return apiBaseUrl;
      })
      .catch(() => apiBaseUrl);
  }

  return apiBaseUrlHydration;
}

type ApiRequestOptions = RequestInit & {
  token?: string | null;
  timeoutMs?: number;
};

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

async function requestJson<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  await hydrateApiBaseUrl();

  const { token, headers, body, timeoutMs = 20000, ...rest } = options;
  const requestHeaders: Record<string, string> = {
    Accept: 'application/json',
    ...(headers as Record<string, string> | undefined),
  };

  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  if (!(body instanceof FormData)) {
    requestHeaders['Content-Type'] = requestHeaders['Content-Type'] ?? 'application/json';
  }

  let response: Response;
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    response = await fetch(`${apiBaseUrl}${path}`, {
      ...rest,
      headers: requestHeaders,
      body,
      signal: controller.signal,
    });
  } catch (error) {
    const connectionHelp = isLocalDevelopmentApiBaseUrl(apiBaseUrl)
      ? 'Check that Laravel is running and that your phone is on the same Wi-Fi.'
      : 'Check your internet connection and try again.';

    if (error instanceof Error && error.name === 'AbortError') {
      throw new ApiError(
        `The reservation server at ${apiBaseUrl} is taking too long to respond. ${connectionHelp}`,
        0,
      );
    }

    throw new ApiError(
      `Unable to reach the reservation server at ${apiBaseUrl}. ${connectionHelp}`,
      0,
    );
  } finally {
    clearTimeout(timeoutId);
  }

  const raw = await response.text();
  let data: any = null;

  if (raw) {
    try {
      data = JSON.parse(raw);
    } catch {
      data = { message: raw };
    }
  }

  if (!response.ok) {
    throw new ApiError(
      data?.message ?? 'Something went wrong while talking to the reservation server.',
      response.status,
      data?.errors,
    );
  }

  return data as T;
}

export function getApiBaseUrl() {
  return apiBaseUrl;
}

export async function setApiBaseUrl(value: string) {
  const normalizedApiBaseUrl = normalizeApiBaseUrl(value);
  apiBaseUrl = normalizedApiBaseUrl;
  apiBaseUrlHydration = Promise.resolve(normalizedApiBaseUrl);
  await AsyncStorage.setItem(apiBaseUrlStorageKey, normalizedApiBaseUrl);

  return normalizedApiBaseUrl;
}

export async function testApiBaseUrl(value: string, timeoutMs = 6000) {
  const normalizedApiBaseUrl = normalizeApiBaseUrl(value);
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(`${normalizedApiBaseUrl}/api/mobile/home`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    });

    if (!response.ok) {
      throw new Error(`The server responded with HTTP ${response.status}.`);
    }

    const data = await response.json();

    if (!data || !Array.isArray(data.branches) || !Array.isArray(data.eventTypes)) {
      throw new Error('This address is not the McDonald\'s Reservations server.');
    }

    return normalizedApiBaseUrl;
  } catch (error) {
    if (error instanceof Error && error.message.startsWith('This address')) {
      throw error;
    }

    if (error instanceof Error && error.message.startsWith('The server responded')) {
      throw error;
    }

    throw new Error(`Cannot reach ${normalizedApiBaseUrl}. Start Laravel and keep the phone on the same Wi-Fi.`);
  } finally {
    clearTimeout(timeoutId);
  }
}

export function resolveApiUrl(value?: string | null) {
  if (!value) {
    return null;
  }

  try {
    const parsed = new URL(value, `${apiBaseUrl}/`);
    const configured = new URL(apiBaseUrl);

    if (['localhost', '127.0.0.1', '0.0.0.0'].includes(parsed.hostname)) {
      parsed.protocol = configured.protocol;
      parsed.hostname = configured.hostname;
      parsed.port = configured.port;
    }

    return parsed.toString();
  } catch {
    return `${apiBaseUrl}/${value.replace(/^\//, '')}`;
  }
}

export function getReservationPaymentProofUrl(reservationId: number) {
  return `${apiBaseUrl}/api/mobile/reservations/${reservationId}/payment-proof`;
}

export function getReservationPassUrl(reservationId: number) {
  return `${apiBaseUrl}/api/mobile/reservations/${reservationId}/pass`;
}

export function fetchHome() {
  return requestJson<HomePayload>('/api/mobile/home');
}

export function fetchBookingOptions() {
  return requestJson<BookingOptionsPayload>('/api/mobile/booking-options?days=60');
}

export function fetchDashboard(token: string) {
  return requestJson<DashboardPayload>('/api/mobile/dashboard', { token });
}

export function fetchOperations(token: string) {
  return requestJson<OperationsPayload>('/api/mobile/operations', { token });
}

export function fetchAvailabilityDay(token: string, branchCode: string, date: string) {
  return requestJson<Record<string, any>>(`/api/mobile/admin/availability-day/${branchCode}/${date}`, { token });
}

export function fetchCurrentUser(token: string) {
  return requestJson<{ user: MobileUser }>('/api/mobile/me', { token });
}

export function fetchProfile(token: string) {
  return requestJson<{ profile: ProfilePayload }>('/api/mobile/profile', { token });
}

export function login(payload: { email: string; password: string }) {
  return requestJson<{ message: string; token: string; user: MobileUser }>('/api/mobile/login', {
    method: 'POST',
    body: JSON.stringify(payload),
    timeoutMs: 8000,
  });
}

export function register(payload: Record<string, string>) {
  return requestJson<{ message: string; token: string; user: MobileUser }>('/api/mobile/register', {
    method: 'POST',
    body: JSON.stringify(payload),
    timeoutMs: 10000,
  });
}

export function googleLogin(idToken: string) {
  return requestJson<{
    message: string;
    token: string;
    user: MobileUser;
    needs_profile_completion: boolean;
  }>('/api/mobile/google', {
    method: 'POST',
    body: JSON.stringify({ id_token: idToken }),
    timeoutMs: 10000,
  });
}

export function logout(token: string) {
  return requestJson<{ message: string }>('/api/mobile/logout', {
    method: 'POST',
    token,
  });
}

export function createReservation(payload: FormData, token: string) {
  return requestJson<{ message: string; reservation: ReservationRecord }>('/api/mobile/reservations', {
    method: 'POST',
    body: payload,
    token,
    timeoutMs: 60000,
  });
}

export function cancelReservation(token: string, reservationId: number) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/reservations/${reservationId}/cancel`, {
    method: 'POST',
    token,
  });
}

export function rescheduleReservation(token: string, reservationId: number, payload: { event_date: string; event_time: string }) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/reservations/${reservationId}/reschedule`, {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function createReservationInvitation(
  token: string,
  reservationId: number,
  payload: { name: string; phone: string; email?: string },
) {
  return requestJson<{ message: string; sms_queued: boolean; invitation: EventInvitation }>(
    `/api/mobile/reservations/${reservationId}/invitations`,
    {
      method: 'POST',
      token,
      body: JSON.stringify(payload),
    },
  );
}

export function respondToReservationInvitation(token: string, invitationId: number, status: 'accepted' | 'declined') {
  return requestJson<{ message: string; invitation: EventInvitation }>(`/api/mobile/invitations/${invitationId}/respond`, {
    method: 'POST',
    token,
    body: JSON.stringify({ status }),
  });
}

export function cancelReservationInvitation(token: string, reservationId: number, invitationId: number) {
  return requestJson<{ message: string }>(`/api/mobile/reservations/${reservationId}/invitations/${invitationId}`, {
    method: 'DELETE',
    token,
  });
}

export function updateProfile(token: string, payload: Record<string, string | boolean> | FormData) {
  const isFormData = payload instanceof FormData;

  return requestJson<{ message: string; profile: ProfilePayload }>('/api/mobile/profile', {
    method: isFormData ? 'POST' : 'PUT',
    token,
    body: isFormData ? payload : JSON.stringify(payload),
    timeoutMs: isFormData ? 20000 : undefined,
  });
}

export function updateProfilePassword(token: string, payload: Record<string, string>) {
  return requestJson<{ message: string }>('/api/mobile/profile/password', {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function deleteProfile(token: string, password: string) {
  return requestJson<{ message: string }>('/api/mobile/profile', {
    method: 'DELETE',
    token,
    body: JSON.stringify({ password }),
  });
}

export function checkInGuest(token: string, code: string) {
  return requestJson<{ message: string; reservation: ReservationRecord }>('/api/mobile/staff/check-in', {
    method: 'POST',
    token,
    body: JSON.stringify({ code }),
  });
}

export function updateFloorStatus(token: string, reservationId: number, service_status: string) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/staff/reservations/${reservationId}/service-status`, {
    method: 'POST',
    token,
    body: JSON.stringify({ service_status }),
  });
}

export function updateServiceAdjustments(token: string, reservationId: number, payload: Record<string, any>) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/staff/reservations/${reservationId}/adjustments`, {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateAdminBookingStatus(token: string, reservationId: number, status: string) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/admin/reservations/${reservationId}/status`, {
    method: 'POST',
    token,
    body: JSON.stringify({ status }),
  });
}

export function updateAdminCrew(token: string, reservationId: number, assigned_staff_id: number | null) {
  return requestJson<{ message: string; reservation: ReservationRecord }>(`/api/mobile/admin/reservations/${reservationId}/crew`, {
    method: 'POST',
    token,
    body: JSON.stringify({ assigned_staff_id }),
  });
}

export function requestBranchAccess(token: string, branch_code: string, request_message: string) {
  return requestJson<{ message: string }>('/api/mobile/admin/branch-access-requests', {
    method: 'POST',
    token,
    body: JSON.stringify({ branch_code, request_message }),
  });
}

export function reviewBranchAccess(token: string, requestId: number, status: 'approved' | 'rejected') {
  return requestJson<{ message: string }>(`/api/mobile/admin/branch-access-requests/${requestId}/review`, {
    method: 'POST',
    token,
    body: JSON.stringify({ status }),
  });
}

export function createAdminUser(token: string, payload: Record<string, any>) {
  return requestJson<{ message: string; user: Record<string, any> }>('/api/mobile/admin/users', {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateAdminUser(token: string, userId: number, payload: Record<string, any>) {
  return requestJson<{ message: string; user: Record<string, any> }>(`/api/mobile/admin/users/${userId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function deleteAdminUser(token: string, userId: number) {
  return requestJson<{ message: string }>(`/api/mobile/admin/users/${userId}`, {
    method: 'DELETE',
    token,
  });
}

export function createBranch(token: string, payload: Record<string, any>) {
  return requestJson<{ message: string; branch: Record<string, any> }>('/api/mobile/admin/branches', {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateBranch(token: string, branchId: number, payload: Record<string, any>) {
  return requestJson<{ message: string; branch: Record<string, any> }>(`/api/mobile/admin/branches/${branchId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function deleteBranch(token: string, branchId: number) {
  return requestJson<{ message: string }>(`/api/mobile/admin/branches/${branchId}`, {
    method: 'DELETE',
    token,
  });
}

export function createInventoryItem(token: string, branchId: number, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/branches/${branchId}/inventory`, {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateInventoryItem(token: string, itemId: number, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/inventory-items/${itemId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateEventType(token: string, eventTypeId: number, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/event-types/${eventTypeId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function updatePackage(token: string, packageId: number, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/packages/${packageId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function createRoomOption(token: string, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/room-options`, {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateRoomOption(token: string, roomOptionId: number, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/room-options/${roomOptionId}`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}

export function updateBookingSettings(token: string, payload: Record<string, any>) {
  return requestJson<{ message: string }>(`/api/mobile/admin/booking-settings`, {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
}
