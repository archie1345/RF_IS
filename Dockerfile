# syntax=docker/dockerfile:1.5

############################
# Node stage (New)
############################
FROM node:20 as node-deps
WORKDIR /app
RUN apt-get update && apt-get install -y php-cli
COPY package*.json vite.config.ts ./
RUN npm install
COPY resources/ resources/
COPY routes/ routes/
COPY vite.config.ts .
COPY bootstrap/ bootstrap/
# RUN npm run build

############################
# Dependencies stage
############################
FROM php:8.4 as deps

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
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer


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

RUN docker-php-ext-install opcache

# Copy Composer from deps stage
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Additional runtime tools
RUN apt-get update && apt-get install -y git curl unzip \
 && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && docker-php-ext-install pdo pdo_mysql

# Set npm cache permissions
RUN mkdir -p /var/www/.npm && chown -R www-data:www-data /var/www

# Use production php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure Apache to serve Laravel from public directory
RUN echo '<VirtualHost *:80>' > /etc/apache2/sites-available/000-default.conf && \
    echo '    ServerName localhost' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    DocumentRoot /var/www/html/public' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html/public>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        <IfModule mod_rewrite.c>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '            RewriteEngine On' >> /etc/apache2/sites-available/000-default.conf && \
    echo '            RewriteCond %{REQUEST_FILENAME} !-d' >> /etc/apache2/sites-available/000-default.conf && \
    echo '            RewriteCond %{REQUEST_FILENAME} !-f' >> /etc/apache2/sites-available/000-default.conf && \
    echo '            RewriteRule ^ index.php [QSA,L]' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        </IfModule>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '</VirtualHost>' >> /etc/apache2/sites-available/000-default.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

    RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.fast_shutdown=1'; \
        } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Copy vendor from deps stage
COPY --from=deps /app/vendor ./vendor

# # Copy Node dependencies
# COPY --from=node-deps /app/public/build ./public/build

# Copy application code
COPY . .

# Ensure permissions are set as ROOT before switching user
USER root
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Now switch to the limited user
# USER www-data