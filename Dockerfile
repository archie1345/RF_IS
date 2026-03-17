# syntax=docker/dockerfile:1

############################
# Dependencies stage
############################
FROM php:8.4-cli as deps

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only composer files first (better caching)
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

############################
# Application stage
############################
FROM php:8.4-apache as final

WORKDIR /var/www/html

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql bcmath \
    && a2enmod rewrite

# Use production php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy vendor from deps stage
COPY --from=deps /app/vendor ./vendor

# Copy application
COPY . .

# Set permissions (important for Laravel)
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

USER www-data