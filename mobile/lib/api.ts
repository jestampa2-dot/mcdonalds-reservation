import type {
  BookingOptionsPayload,
  DashboardPayload,
  HomePayload,
  MobileUser,
  OperationsPayload,
  ProfilePayload,
  ReservationRecord,
} from '@/lib/types';
import { Platform } from 'react-native';

function getDefaultApiBaseUrl() {
  if (Platform.OS === 'android') {
    return 'http://10.0.2.2:8000';
  }

  return 'http://127.0.0.1:8000';
}

const apiBaseUrl = (process.env.EXPO_PUBLIC_API_BASE_URL?.trim() || getDefaultApiBaseUrl()).replace(/\/$/, '');

type ApiRequestOptions = RequestInit & {
  token?: string | null;
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
  const { token, headers, body, ...rest } = options;
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

  try {
    response = await fetch(`${apiBaseUrl}${path}`, {
      ...rest,
      headers: requestHeaders,
      body,
    });
  } catch {
    throw new ApiError(
      `Unable to reach the reservation server at ${apiBaseUrl}. Start Laravel with "php artisan serve" and set EXPO_PUBLIC_API_BASE_URL in mobile/.env when testing on a real device.`,
      0,
    );
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
  });
}

export function register(payload: Record<string, string>) {
  return requestJson<{ message: string; token: string; user: MobileUser }>('/api/mobile/register', {
    method: 'POST',
    body: JSON.stringify(payload),
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

export function updateProfile(token: string, payload: Record<string, string>) {
  return requestJson<{ message: string; profile: ProfilePayload }>('/api/mobile/profile', {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
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
