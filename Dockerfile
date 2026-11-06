FROM php:8.2-apache-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:20-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip \
    && (a2dismod -f mpm_event mpm_worker || true) \
    && a2enmod mpm_prefork rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/php-upload.ini /usr/local/etc/php/conf.d/99-upload.ini
COPY . .

RUN composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
    && npm ci \
    && npm run build \
    && npm cache clean --force \
    && rm -rf node_modules \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan storage:link --force \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
