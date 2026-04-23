import type {
  BookingOptionsPayload,
  DashboardPayload,
  HomePayload,
  MobileUser,
  ReservationRecord,
} from '@/lib/types';

const apiBaseUrl = (process.env.EXPO_PUBLIC_API_BASE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');

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

  const response = await fetch(`${apiBaseUrl}${path}`, {
    ...rest,
    headers: requestHeaders,
    body,
  });

  const raw = await response.text();
  const data = raw ? JSON.parse(raw) : null;

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

export function fetchCurrentUser(token: string) {
  return requestJson<{ user: MobileUser }>('/api/mobile/me', { token });
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
