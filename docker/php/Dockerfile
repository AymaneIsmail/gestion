FROM dunglas/frankenphp:1-php8.4 AS base

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy Caddyfile
COPY docker/php/Caddyfile /etc/caddy/Caddyfile

# ─── Development stage ────────────────────────────────────────────────────────
FROM base AS dev

ENV APP_ENV=dev
# Worker mode is OFF in dev: vendor/ is mounted as a volume and may not exist
# at container startup. FrankenPHP runs in regular CGI mode instead.
# Worker mode can be enabled later once vendor/ is bootstrapped if needed.

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php/conf.d/php.dev.ini /usr/local/etc/php/conf.d/app.ini

# Entrypoint: auto-runs composer install if vendor/ is missing
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ─── Production stage ─────────────────────────────────────────────────────────
FROM base AS prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime
# Valeurs factices uniquement pour le build — écrasées au runtime par compose.prod.yml
ENV APP_SECRET=build_secret_placeholder_32chars__
ENV DATABASE_URL="postgresql://build:build@localhost/build?serverVersion=16&charset=utf8"
ENV DEFAULT_URI=http://localhost
ENV MAILER_DSN=null://null
ENV MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

COPY docker/php/conf.d/php.prod.ini /usr/local/etc/php/conf.d/app.ini

COPY --chown=www-data:www-data . .

# --no-scripts évite que composer exécute cache:clear automatiquement
# (qui nécessiterait toutes les vars d'env au build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && php bin/console cache:clear \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup --no-optional-warmers

RUN mkdir -p public/uploads && chown -R www-data:www-data public/uploads

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
