# Stage 1: Install PHP dependencies
FROM php:8.4-cli-alpine AS composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
# Copy full source so we can generate an optimized autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Stage 2: Build frontend assets
FROM node:20-alpine AS node
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
# Vite bakes VITE_* env vars into the JS bundle at build time
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
RUN npm run build

# Stage 3: Production image
FROM php:8.4-fpm-alpine AS runner

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    gd \
    zip \
    intl \
    pcntl \
    bcmath

WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy composer dependencies (with optimized autoloader)
COPY --from=composer /app/vendor ./vendor

# Copy built frontend assets
COPY --from=node /app/public/build ./public/build

# Create .env placeholder (real env comes from Docker runtime)
RUN php -r "file_exists('.env') || copy('.env.example', '.env');"

# Clear bootstrap cache (may contain dev-only providers from local dev)
# and re-discover packages in no-dev mode
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi

# Copy Docker config files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Set permissions — ensure all files are readable (host may have restrictive umask)
RUN chmod -R a+rX /var/www/html \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /entrypoint.sh

# Create nginx pid directory
RUN mkdir -p /run/nginx

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
