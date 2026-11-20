#!/usr/bin/env bash
set -euo pipefail

# Ensure PHP CLI is available for Wayfinder route/action generation during `vite build`.
if ! command -v php >/dev/null 2>&1; then
    if command -v dnf >/dev/null 2>&1; then
        dnf install -y php-cli php-mbstring php-xml php-pdo php-mysqlnd php-zip php-gd php-intl
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update && apt-get install -y --no-install-recommends php-cli php-mbstring php-xml php-pdo php-mysql php-zip php-gd php-intl
    else
        echo "Error: PHP CLI is required to run Wayfinder during Vite build." >&2
        exit 1
    fi
fi

# Ensure Composer dependencies are installed so `php artisan wayfinder:generate` can boot Laravel.
if [ ! -f vendor/autoload.php ]; then
    if ! command -v composer >/dev/null 2>&1; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer
        COMPOSER_BIN="php /tmp/composer"
    else
        COMPOSER_BIN="composer"
    fi

    $COMPOSER_BIN install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=php
fi

# Provide a minimal .env for Artisan CLI during build if none is present.
if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

npm run build
