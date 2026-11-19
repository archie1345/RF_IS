#!/bin/sh

set -eu

export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE=:memory:
export DB_URL=
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array

php artisan config:clear --ansi
exec php artisan test "$@"
