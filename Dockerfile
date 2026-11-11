# =============================================================================
# AgileTracker — Docker Development Image
# Multi-stage: dependencies → frontend → runtime
# =============================================================================

# ─── Stage 1: Dependencies (Composer) ──────────────────────────────────────
FROM php:8.3-cli AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        libicu-dev \
        libzip-dev \
        git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ─── Stage 2: Frontend Build ────────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

# ─── Stage 3: Runtime ───────────────────────────────────────────────────────
FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    zip \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libsqlite3-dev \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    intl \
    pdo \
    pdo_sqlite \
    mbstring \
    bcmath \
    opcache \
    zip

# Composer
COPY --from=vendor /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy Composer dependencies
COPY --from=vendor /app/vendor /app/vendor

# Copy frontend assets
COPY --from=frontend /app/public/build /app/public/build

# Copy application source
COPY . .

# Create storage structure & set permissions
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        database \
    && chmod -R 775 storage bootstrap/cache

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# OPcache settings
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]