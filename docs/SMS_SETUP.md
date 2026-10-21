# Booking SMS Alerts

Laravel handles SMS for both the web system and the Expo app. Customer booking
submissions and admin status updates use the same reservation observer. Messages
are saved in the booking database and sent by a separate worker, so SMS delays
do not hold up booking, login, or registration.

## Free service and Android setup

TextBee's Free plan currently allows one Android device, 50 messages per day,
and 300 per month, counting sent and received messages. The platform is free;
your SIM's SMS allowance or carrier charges still apply.

- Pricing: https://textbee.dev/pricing
- Setup: https://textbee.dev/docs/getting-started
- Android app: https://textbee.dev/download
- API: https://textbee.dev/docs/sending-sms/sending-sms

1. Create a TextBee account and verify its email.
2. Install TextBee on an Android phone with a SIM that can send SMS.
3. Register the phone and allow its SMS/background permissions.
4. Create an API key in TextBee. Optionally copy the device ID.
5. Keep the sending phone charged, online, and able to send ordinary SMS.

Customers do not need TextBee installed; only the sending phone needs it.

## Configure Laravel

From the folder containing artisan, apply the migration:

    php artisan migrate

Add these settings to the Laravel root .env, using your own credentials:

    SMS_ENABLED=true
    TEXTBEE_API_KEY=your_private_textbee_api_key
    TEXTBEE_DEVICE_ID=your_registered_device_id

The device ID is optional if TextBee already has a default phone.
Keep the key on the server. Never put it in mobile/.env, Expo public variables,
Vue code, or Git.

Reload configuration, then start the worker in a separate terminal:

    php artisan config:clear
    php artisan queue:work sms --queue=sms --tries=1 --timeout=20 --sleep=1

Leave the worker running during testing. Stop and restart it after changing
SMS configuration. In production, use your hosting service's process manager
to keep it running. Existing queues keep their current configuration.

## Test

Use your own real number. This queues one actual SMS:

    php artisan sms:test 09XXXXXXXXX

Sign in as a customer in the app or website. In My Account, enter a Philippine
mobile number, enable "Receive booking updates by SMS", and save. The setting
is shared through the database.

Create a booking, then confirm it from admin. Receipt and confirmation alerts
should arrive. Rescheduling, cancellation, check-in, and completion also trigger
alerts. Saving an unchanged status, assigning staff, or changing floor/service
status does not. Existing bookings are not sent retroactively.

Valid formats include 09171234567, 9171234567, 639171234567, and +639171234567,
including spaces and hyphens. Customers can opt out from either profile.

## Troubleshooting

The sms_messages table records each alert:

| Status | Meaning |
| --- | --- |
| queued | Waiting for the Laravel worker |
| sending | Worker started the provider request |
| accepted | TextBee accepted it; delivery is not yet confirmed |
| skipped | Disabled SMS, customer opt-out, deleted account, or changed phone |
| failed | Provider rejected the request or configuration is invalid |
| unknown | Connection/worker failed; TextBee may already have accepted it |

Accepted does not mean delivered. Look up provider_batch_id in TextBee to
check sending and delivery. No automatic retry is made after an ambiguous
timeout. Check TextBee before manually sending another message.

HTTP 401 usually means a bad key; HTTP 429 means a plan limit was reached.
If an accepted message does not arrive, check the sending phone's internet,
SMS permissions, cellular signal, and SIM balance.
A failed SMS does not undo a saved booking.

To disable SMS, set SMS_ENABLED=false, run php artisan config:clear, and restart
the worker. The worker also checks each customer's preference before sending.

## Automated checks

    php artisan test --filter=BookingSmsTest

These tests use mocked TextBee responses and isolated SQLite data. They do not
send real texts or change the development database.
