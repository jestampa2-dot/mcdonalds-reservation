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

sed -ri "s/^Listen [0-9]+$/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/*.conf

exec apache2-foreground
