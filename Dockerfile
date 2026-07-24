# syntax=docker/dockerfile:1.5

FROM composer:2 AS composer-bin

FROM php:8.4-cli AS php-deps

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring gd zip pdo pdo_mysql bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.4-cli AS frontend-build

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring gd zip pdo pdo_mysql bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=php-deps /app/vendor ./vendor
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN test -f .env || cp .env.example .env
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && LOG_CHANNEL=stderr VIEW_COMPILED_PATH=/tmp/laravel-views npm run build

FROM php:8.4-apache AS final

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring gd zip pdo pdo_mysql bcmath opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
      } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && { \
        echo 'sys_temp_dir=/tmp/laravel'; \
        echo 'upload_tmp_dir=/tmp/laravel'; \
      } > /usr/local/etc/php/conf.d/laravel-temp.ini

RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

COPY . .
COPY --from=php-deps /app/vendor ./vendor
COPY --from=frontend-build /app/public/build ./public/build

RUN set -eux; \
    dev_gid=1000; \
    group_name="$(getent group "$dev_gid" | cut -d: -f1 || true)"; \
    if [ -z "$group_name" ]; then groupadd --gid "$dev_gid" appdev; group_name=appdev; fi; \
    usermod -a -G "$group_name" www-data; \
    mkdir -p \
        /tmp/laravel \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public/build; \
    chown -R www-data:"$group_name" /tmp/laravel storage bootstrap/cache public/build; \
    find /tmp/laravel storage bootstrap/cache public/build -type d -exec chmod 2775 {} +; \
    find /tmp/laravel storage bootstrap/cache public/build -type f -exec chmod ug+rw {} +

EXPOSE 80

CMD ["apache2-foreground"]
