# McDonald's Reservations Mobile

Expo mobile app for the Laravel reservation backend in the repository root.

## Setup

1. Copy `.env.example` to `.env`.
2. Set `EXPO_PUBLIC_API_BASE_URL`.
3. Start Laravel from the repo root.
4. Start Expo from the `mobile` folder.

## Local URLs

- Android emulator: `http://10.0.2.2:8000`
- iOS simulator: `http://127.0.0.1:8000`
- Real device: `http://YOUR_COMPUTER_LAN_IP:8000`

## Commands

```bash
# repo root
php artisan serve

# mobile folder
npm install
npm run start
```

## Mobile API

The Expo app uses these Laravel endpoints:

- `GET /api/mobile/home`
- `GET /api/mobile/booking-options`
- `GET /api/mobile/availability`
- `POST /api/mobile/login`
- `POST /api/mobile/register`
- `GET /api/mobile/me`
- `POST /api/mobile/logout`
- `GET /api/mobile/dashboard`
- `POST /api/mobile/reservations`
