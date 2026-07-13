# ---- assets build ----
FROM node:22-bookworm-slim AS assets
WORKDIR /src
COPY . .
# Upstream's lockfile pins @babel/core 8 + gulp-babel 8.1, which are incompatible — gulp-babel throws a
# TypeError per file (swallowed by gulp's plumber), so NO assets/js/*.min.js are produced and the booking
# UI 404s its scripts. Pin a compatible Babel 7 toolchain at BUILD TIME only (--no-save) so we never edit
# the upstream-tracked package.json / package-lock.json (keeps the fork additive-only, ADR 0008).
RUN npm ci --no-audit --no-fund \
 && npm install --no-save @babel/core@^7.28.0 @babel/preset-env@^7.28.0 \
 && npx gulp compile

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
# The whole compiled assets tree from the build stage: gulp's minified assets/js/*.min.js +
# assets/css/*.css + assets/vendor. These are git-ignored (build output), so `COPY . .` never brings
# them — copying only assets/vendor left the page referencing 404'd *.min.js/css (unstyled booking page).
COPY --from=assets /src/assets ./assets
# Ship no Easy!Appointments (or author) branding in the image at all. These four assets are the only
# ones that reach a user without passing through the de-brand filter:
#   - logo.png is EMBEDDED INTO EVERY OUTGOING EMAIL as a cid: attachment, with the path hard-coded in
#     an upstream-tracked file (application/libraries/Email_messages.php:301) — so the only additive way
#     to keep EA's logo out of a customer's inbox is to make sure the file itself is not EA's.
#   - favicon.ico is what the browser tab shows on the booking page.
# All four are replaced with neutral, fully transparent images. The repo files are untouched (ADR 0008);
# only the built image differs. A business's OWN logo is a data: URL in ea_settings and is unaffected.
COPY deploy/booking-runtime/neutral/logo.png    ./assets/img/logo.png
COPY deploy/booking-runtime/neutral/logo.png    ./assets/img/logo-16x16.png
COPY deploy/booking-runtime/neutral/logo.png    ./assets/img/alextselegidis-logo-16x16.png
COPY deploy/booking-runtime/neutral/favicon.ico ./assets/img/favicon.ico
COPY deploy/booking-runtime/config.stub.php ./config.php
COPY deploy/booking-runtime/nginx-app.conf /etc/nginx/sites-available/default
RUN mkdir -p storage/sessions storage/cache storage/logs storage/uploads && chown -R www-data:www-data storage \
    && printf '#!/bin/sh\nset -e\nphp-fpm -D\nexec nginx -g "daemon off;"\n' > /start && chmod +x /start
EXPOSE 80
CMD ["/start"]
