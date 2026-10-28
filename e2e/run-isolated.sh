#!/bin/sh

set -eu

if [ -n "${HOME:-}" ] && [ -d "$HOME/.local/bin" ]; then
    PATH="$HOME/.local/bin:$PATH"
    export PATH
fi

project_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$project_root"

port=9205
image=rf-is:e2e
container="rfis-e2e-$$"
network="rfis-e2e-net-$$"
artifact_dir="$project_root/e2e/test-results"
app_log="$artifact_dir/isolated-app.log"
app_url="http://127.0.0.1:$port"

if [ ! -f .env ]; then
    printf '%s\n' 'The local .env file is required for the isolated demo account password.' >&2
    exit 1
fi

demo_password=$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { print substr($0, index($0, "=") + 1); exit }' .env)
if [ "${#demo_password}" -lt 16 ]; then
    printf '%s\n' 'RFIS_DEMO_PASSWORD must be configured with at least 16 characters.' >&2
    exit 1
fi

if [ -z "${DISPLAY:-}" ] && [ -z "${WAYLAND_DISPLAY:-}" ]; then
    printf '%s\n' 'A visible browser requires WSLg or an X server (DISPLAY or WAYLAND_DISPLAY).' >&2
    exit 1
fi

if ! python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1", 9205)); s.close()' 2>/dev/null; then
    printf '%s\n' 'Port 9205 is already in use; stop its owner before running isolated E2E tests.' >&2
    exit 1
fi

mkdir -p "$artifact_dir"

printf '%s\n' 'Building the isolated Laravel E2E image (no services or database are started).'
docker build --target final --file Dockerfile.dev --tag "$image" .

docker network create \
    --driver bridge \
    --opt com.docker.network.bridge.enable_ip_masquerade=false \
    "$network" >/dev/null

cleanup() {
    exit_code=$?
    trap - EXIT HUP INT TERM
    unset demo_password

    if docker container inspect "$container" >/dev/null 2>&1; then
        docker logs "$container" > "$app_log" 2>&1 || true
        docker rm --force "$container" >/dev/null 2>&1 || true
    fi

    docker network rm "$network" >/dev/null 2>&1 || true
    exit "$exit_code"
}
trap cleanup EXIT HUP INT TERM

docker run --detach \
    --name "$container" \
    --network "$network" \
    --publish "127.0.0.1:$port:8000" \
    --read-only \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777,size=64m \
    --tmpfs /var/www/html/storage:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=64m \
    --tmpfs /var/www/html/bootstrap/cache:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=16m \
    --user 33:33 \
    --workdir /var/www/html \
    --env APP_ENV=local \
    --env APP_DEBUG=false \
    --env APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    --env APP_URL="$app_url" \
    --env APP_MAINTENANCE_DRIVER=file \
    --env DB_CONNECTION=sqlite \
    --env DB_DATABASE=/tmp/rfis-e2e.sqlite \
    --env DB_URL= \
    --env SESSION_DRIVER=file \
    --env CACHE_STORE=file \
    --env QUEUE_CONNECTION=sync \
    --env MAIL_MAILER=array \
    --env BROADCAST_CONNECTION=null \
    --env FILESYSTEM_DISK=local \
    --env LOG_CHANNEL=stderr \
    --env RFIS_ALLOW_DEMO_SEEDING=true \
    --env RFIS_DEMO_PASSWORD="$demo_password" \
    "$image" \
    sh -ec 'mkdir -p /tmp/laravel storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache; php artisan config:clear; php artisan migrate:fresh --seed --force --no-interaction; exec php artisan serve --host=0.0.0.0 --port=8000' >/dev/null

ready=false
for attempt in $(seq 1 60); do
    if curl --silent --show-error --fail "$app_url/up" >/dev/null 2>&1; then
        ready=true
        break
    fi

    if ! docker container inspect "$container" --format '{{.State.Running}}' 2>/dev/null | grep -q true; then
        break
    fi

    sleep 2
done

if [ "$ready" != true ]; then
    printf '%s\n' 'The isolated Laravel app did not become ready. Container logs follow:' >&2
    docker logs "$container" >&2 || true
    exit 1
fi

printf 'Running headed Playwright against %s with SQLite and uploads restricted to disposable tmpfs storage.\n' "$app_url"
APP_URL="$app_url" RFIS_DEMO_PASSWORD="$demo_password" npm --prefix "$project_root/e2e" run test:headed -- "$@"
