#!/bin/sh

set -eu

APP_PORT="${PORT:-8080}"

# PHP's Apache image requires prefork. Force a single MPM at startup so
# package updates or cached image layers cannot enable a conflicting worker.
a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1

# Railway mounts persistent volumes after the image is built. Make the upload
# volume writable every time the container starts so payment proofs can be
# created by Apache's www-data worker.
mkdir -p /var/www/html/storage/app/public/payment-proofs
chown -R www-data:www-data /var/www/html/storage/app/public
chmod -R ug+rwX /var/www/html/storage/app/public

# Keep the hosted database schema synchronized with every deployed release.
# Laravel migration locks make this safe to rerun when Railway restarts the
# container, while --force allows the command in the production environment.
php artisan migrate --force

sed -ri "s/^Listen [0-9]+$/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/*.conf

# Run the database-backed invitation email queue beside Apache. Keeping both
# processes in this container avoids a second Railway service while still
# allowing automatic guest invitations to leave the queue immediately.
php artisan queue:work sms \
    --queue=email \
    --tries=3 \
    --timeout=30 \
    --sleep=1 &
SMS_WORKER_PID=$!

apache2-foreground &
APACHE_PID=$!

shutdown() {
    trap - TERM INT
    kill -TERM "$APACHE_PID" "$SMS_WORKER_PID" 2>/dev/null || true
    wait "$APACHE_PID" 2>/dev/null || true
    wait "$SMS_WORKER_PID" 2>/dev/null || true
}

trap 'shutdown; exit 0' TERM INT

# If Apache or the queue worker exits unexpectedly, stop the other process and
# let Railway restart the container instead of leaving a half-working service.
while kill -0 "$APACHE_PID" 2>/dev/null && kill -0 "$SMS_WORKER_PID" 2>/dev/null; do
    sleep 2
done

shutdown
exit 1
