# ---- assets build ----
FROM node:22-bookworm-slim AS assets
WORKDIR /src
COPY . .
RUN npm ci --no-audit --no-fund && npx gulp compile

# ---- php deps ----
FROM composer:2 AS vendor
WORKDIR /src
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --ignore-platform-reqs

# ---- runtime ----
FROM php:8.3-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends nginx default-mysql-client \
    && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions gd intl mbstring mysqli pdo_mysql zip exif bcmath \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /src/vendor ./vendor
COPY --from=assets /src/assets/vendor ./assets/vendor
COPY deploy/booking-runtime/config.stub.php ./config.php
COPY deploy/booking-runtime/nginx-app.conf /etc/nginx/sites-available/default
RUN mkdir -p storage/sessions storage/cache storage/logs storage/uploads && chown -R www-data:www-data storage \
    && printf '#!/bin/sh\nset -e\nphp-fpm -D\nexec nginx -g "daemon off;"\n' > /start && chmod +x /start
EXPOSE 80
CMD ["/start"]
