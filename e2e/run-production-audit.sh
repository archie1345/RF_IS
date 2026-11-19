#!/usr/bin/env bash
set -Eeuo pipefail

if [[ -d "${HOME:-}/.local/bin" ]]; then
    export PATH="$HOME/.local/bin:$PATH"
fi

cd "$(dirname "$0")/.."

die() {
    printf 'Refusing production-mode audit run: %s\n' "$1" >&2
    exit 1
}

env | grep -Eq '^(DISPLAY|WAYLAND_DISPLAY)=' || die 'headed Chromium requires WSLg or an X server.'
command -v docker >/dev/null || die 'Docker is required.'
command -v jq >/dev/null || die 'jq is required.'
command -v curl >/dev/null || die 'curl is required.'
command -v openssl >/dev/null || die 'OpenSSL is required.'
command -v flock >/dev/null || die 'flock is required to prevent overlapping browser suites and artifact writes.'
[[ -f .env && -f .env.production ]] || die 'the local demo-password file or production Compose environment file is missing.'

artifact_dir=e2e/test-results
mkdir -p "$artifact_dir"
exec {e2e_lock_fd}>"$artifact_dir/acceptance.lock"
flock -n "$e2e_lock_fd" || die 'another browser acceptance run is active; wait for it to finish before creating a disposable schema.'

demo_password="$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { print substr($0, index($0, "=") + 1); exit }' .env)"
[[ "$(printf %s "$demo_password" | wc -c)" -ge 16 ]] || die 'RFIS_DEMO_PASSWORD must be configured with at least 16 characters.'

compose_config="$(docker compose -p rf-is -f compose.prod.yaml --env-file .env.production config --format json)"
[[ "$(jq -r '.name' <<< "$compose_config")" == rf-is ]] || die 'unexpected production Compose project.'
[[ "$(jq -r '.services.db.container_name' <<< "$compose_config")" == rf-db ]] || die 'unexpected production database container.'
[[ "$(jq -r '.services.db.image' <<< "$compose_config")" == rf-is:mysql-8.4.13 ]] || die 'production audit expects the guarded RF_IS MySQL 8.4.13 image.'
[[ "$(jq -r '.services.db.volumes[] | select(.target == "/var/lib/mysql") | .source' <<< "$compose_config")" == db_data ]] || die 'unexpected production database mount.'

prod_env() {
    python3 - "$1" <<'PY'
from pathlib import Path
import sys

key = sys.argv[1]
for line in Path('.env.production').read_text().splitlines():
    if line.strip().startswith(f'{key}='):
        print(line.split('=', 1)[1].strip().strip('"\''))
        break
PY
}

[[ "$(prod_env DB_CONNECTION)" == mysql ]] || die 'the production DB_CONNECTION is not MySQL.'
[[ "$(prod_env DB_HOST)" == db ]] || die 'the production DB_HOST is not the Compose db service.'
[[ "$(prod_env DB_DATABASE)" == rf_is ]] || die 'the production schema is not rf_is.'

compose_identity() {
    docker inspect "$1" --format '{{index .Config.Labels "com.docker.compose.project"}} {{index .Config.Labels "com.docker.compose.service"}}'
}

[[ "$(compose_identity rf-db)" == 'rf-is db' ]] || die 'database container identity does not match production Compose.'
[[ "$(docker inspect rf-db --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}')" == 'running healthy' ]] || die 'production MySQL is not healthy.'
[[ "$(docker inspect rf-db --format '{{.Config.Image}}')" == rf-is:mysql-8.4.13 ]] || die 'the active production database image is unexpected.'
[[ "$(docker inspect rf-db --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}')" == rf_is_prod_db-data ]] || die 'the active production database volume is unexpected.'
[[ "$(docker inspect rf-db --format '{{range .Config.Env}}{{println .}}{{end}}' | awk -F= '$1 == "MYSQL_DATABASE" { print $2; exit }')" == rf_is ]] || die 'the active MySQL container is not initialized for rf_is.'
port_bindings="$(docker inspect rf-db --format '{{json .HostConfig.PortBindings}}')"
[[ "$port_bindings" == '{}' || "$port_bindings" == null ]] || die 'the production database has a host port binding.'

for container in rf-server rf-caddy rf-worker rf-scheduler; do
    if docker inspect "$container" --format '{{.State.Running}}' 2>/dev/null | grep -q true; then
        die "$container is running; persistent production services must remain stopped for this isolated run."
    fi
done

network_gateway="$(docker inspect rf-db --format '{{(index .NetworkSettings.Networks "rf-is_backend").Gateway}}')"
[[ "$network_gateway" == 172.18.0.1 ]] || die 'the production backend gateway changed; review trusted-proxy isolation.'
proxy_ip=172.18.0.10
if docker network inspect rf-is_backend --format '{{range .Containers}}{{.IPv4Address}}{{println}}{{end}}' | grep -q "^$proxy_ip/"; then
    die 'the isolated TLS proxy address is already in use on the production backend network.'
fi
python3 -c 'import socket; s=socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1); s.bind(("127.0.0.1", 9208)); s.close()' 2>/dev/null || die 'loopback port 9208 is already in use.'

root_password="$(docker inspect rf-db --format '{{range .Config.Env}}{{println .}}{{end}}' | awk -F= '$1 == "MYSQL_ROOT_PASSWORD" { sub(/^[^=]*=/, ""); print; exit }')"
[[ -n "$root_password" ]] || die 'the verified MySQL container has no root password configured.'

mysql_value() {
    docker exec -e MYSQL_PWD="$root_password" rf-db mysql --protocol=socket -uroot --batch --skip-column-names -e "$1"
}

source_counts() {
    mysql_value "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='rf_is' AND table_type='BASE TABLE'), ':', (SELECT COUNT(*) FROM rf_is.users), ':', (SELECT COUNT(*) FROM rf_is.migrations), ':', (SELECT COALESCE(MAX(batch), 0) FROM rf_is.migrations));"
}

original_counts="$(source_counts)"
[[ -n "$original_counts" ]] || die 'the production source-schema integrity summary could not be read.'

suffix="$(date -u +%Y%m%d%H%M%S)_$$"
test_database="rfis_e2e_$suffix"
test_user="rfis_e2e_$suffix"
test_password="$(openssl rand -hex 24)"
app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
app_container="rfis-production-audit-$suffix"
app_url=https://localhost:9208
app_log="$artifact_dir/production-audit-app.log"
proxy_log="$artifact_dir/production-audit-proxy.log"
proxy_container="rfis-production-audit-proxy-$suffix"
proxy_dir="$(mktemp -d /tmp/rfis-production-audit-proxy.XXXXXX)"
database_created=0
cleanup_errors=0
mkdir -p "$artifact_dir"

cleanup() {
    result=$?
    trap - EXIT HUP INT TERM

    if docker container inspect "$app_container" >/dev/null 2>&1; then
        docker logs "$app_container" > "$app_log" 2>&1 || true
        docker rm --force "$app_container" >/dev/null 2>&1 || cleanup_errors=1
    fi
    if docker container inspect "$proxy_container" >/dev/null 2>&1; then
        docker logs "$proxy_container" > "$proxy_log" 2>&1 || true
        docker rm --force "$proxy_container" >/dev/null 2>&1 || cleanup_errors=1
    fi

    if [[ "$database_created" == 1 ]]; then
        if ! mysql_value "DROP DATABASE IF EXISTS $test_database; DROP USER IF EXISTS '$test_user'@'%';" >/dev/null; then
            printf '%s\n' 'Could not remove the uniquely named E2E schema and user; inspect cleanup before further use.' >&2
            cleanup_errors=1
        fi
        after_counts="$(source_counts 2>/dev/null || true)"
        if [[ -z "$after_counts" || "$after_counts" != "$original_counts" ]]; then
            printf '%s\n' 'The production source-schema summary changed during the isolated run; investigate before further use.' >&2
            cleanup_errors=1
        fi
    fi

    unset demo_password root_password test_password app_key
    rm -f "$proxy_dir/Caddyfile"
    rmdir "$proxy_dir" 2>/dev/null || cleanup_errors=1

    if [[ "$result" -ne 0 || "$cleanup_errors" -ne 0 ]]; then
        printf 'Production audit app log: %s\n' "$app_log" >&2
    fi
    [[ "$cleanup_errors" == 0 ]] || result=1
    exit "$result"
}
trap cleanup EXIT HUP INT TERM

[[ -z "$(mysql_value "SELECT SCHEMA_NAME FROM information_schema.schemata WHERE schema_name='$test_database';")" ]] || die 'generated disposable schema already exists.'
[[ -z "$(mysql_value "SELECT User FROM mysql.user WHERE User='$test_user' LIMIT 1;")" ]] || die 'generated disposable database user already exists.'
database_created=1
mysql_value "CREATE DATABASE $test_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$test_user'@'%' IDENTIFIED BY '$test_password'; GRANT ALL PRIVILEGES ON $test_database.* TO '$test_user'@'%';" >/dev/null

printf '%s\n' 'Preparing a disposable schema with the production image; the existing rf_is schema remains read-only.'
docker run --rm \
    --network rf-is_backend \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777,size=64m \
    --tmpfs /var/www/html/storage:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=64m \
    --tmpfs /var/www/html/bootstrap/cache:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=16m \
    --env APP_ENV=local \
    --env APP_DEBUG=false \
    --env "APP_KEY=$app_key" \
    --env "APP_URL=$app_url" \
    --env "TRUSTED_PROXIES=$proxy_ip" \
    --env SANCTUM_STATEFUL_DOMAINS=localhost:9208,localhost,127.0.0.1,::1 \
    --env DB_CONNECTION=mysql \
    --env DB_HOST=db \
    --env DB_PORT=3306 \
    --env "DB_DATABASE=$test_database" \
    --env "DB_USERNAME=$test_user" \
    --env "DB_PASSWORD=$test_password" \
    --env DB_URL= \
    --env SESSION_DRIVER=file \
    --env CACHE_STORE=file \
    --env QUEUE_CONNECTION=sync \
    --env MAIL_MAILER=array \
    --env BROADCAST_CONNECTION=log \
    --env FILESYSTEM_DISK=local \
    --env LOG_CHANNEL=stderr \
    --env RFIS_ALLOW_DEMO_SEEDING=true \
    --env "RFIS_DEMO_PASSWORD=$demo_password" \
    rf-is:production-audit \
    sh -ec 'php artisan config:clear; php artisan migrate:fresh --seed --force --no-interaction'

printf '%s\n' 'Starting the production-mode app with loopback binding and tmpfs storage.'
docker run --detach \
    --name "$app_container" \
    --network rf-is_backend \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777,size=64m \
    --tmpfs /var/www/html/storage:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=64m \
    --tmpfs /var/www/html/bootstrap/cache:rw,nosuid,nodev,uid=33,gid=33,mode=2775,size=16m \
    --env APP_ENV=production \
    --env APP_DEBUG=false \
    --env "APP_KEY=$app_key" \
    --env "APP_URL=$app_url" \
    --env "TRUSTED_PROXIES=$proxy_ip" \
    --env SANCTUM_STATEFUL_DOMAINS=localhost:9208,localhost,127.0.0.1,::1 \
    --env DB_CONNECTION=mysql \
    --env DB_HOST=db \
    --env DB_PORT=3306 \
    --env "DB_DATABASE=$test_database" \
    --env "DB_USERNAME=$test_user" \
    --env "DB_PASSWORD=$test_password" \
    --env DB_URL= \
    --env SESSION_DRIVER=file \
    --env CACHE_STORE=file \
    --env QUEUE_CONNECTION=sync \
    --env MAIL_MAILER=array \
    --env BROADCAST_CONNECTION=log \
    --env FILESYSTEM_DISK=local \
    --env LOG_CHANNEL=stderr \
    rf-is:production-audit \
    sh -ec 'mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache; chown -R www-data:www-data storage bootstrap/cache; php artisan storage:link; php artisan config:cache; php artisan route:clear; php artisan view:cache; exec apache2-foreground' >/dev/null

cat > "$proxy_dir/Caddyfile" <<EOF
{
    admin off
    servers {
        trusted_proxies static 172.18.0.1
        trusted_proxies_strict
    }
}

localhost:443 {
    tls internal
    reverse_proxy $app_container:80 {
        header_up X-Forwarded-For {http.request.header.X-Forwarded-For}
    }
}
EOF

docker run --detach \
    --name "$proxy_container" \
    --network rf-is_backend \
    --ip "$proxy_ip" \
    --publish 127.0.0.1:9208:443 \
    --tmpfs /data:rw,nosuid,nodev,size=16m \
    --tmpfs /config:rw,nosuid,nodev,size=4m \
    --mount "type=bind,src=$proxy_dir/Caddyfile,dst=/etc/caddy/Caddyfile,readonly" \
    rf-is:caddy caddy run --config /etc/caddy/Caddyfile >/dev/null

ready=false
for attempt in $(seq 1 60); do
    if curl --silent --insecure --fail "$app_url/up" >/dev/null 2>&1; then
        ready=true
        break
    fi
    if ! docker container inspect "$app_container" --format '{{.State.Running}}' 2>/dev/null | grep -q true; then
        break
    fi
    sleep 2
done
[[ "$ready" == true ]] || {
    docker logs "$app_container" >&2 || true
    die 'the loopback production-mode app did not become healthy.'
}

app_bindings="$(docker inspect "$app_container" --format '{{json .HostConfig.PortBindings}}')"
[[ "$app_bindings" == '{}' || "$app_bindings" == null ]] || die 'the app container must not publish a host port.'
[[ "$(docker inspect "$proxy_container" --format '{{json .HostConfig.PortBindings}}' | jq -r 'to_entries[0].value[0].HostIp')" == 127.0.0.1 ]] || die 'the TLS proxy is not bound to loopback.'
[[ "$(docker exec "$app_container" printenv APP_ENV)" == production ]] || die 'the browser target is not running production Laravel configuration.'
[[ "$(docker exec "$app_container" printenv DB_DATABASE)" == "$test_database" ]] || die 'the app is not using its unique disposable schema.'
[[ "$(docker exec "$app_container" printenv TRUSTED_PROXIES)" == "$proxy_ip" ]] || die 'the app does not trust only the ephemeral TLS proxy.'

printf 'Running headed role, mobile, and functional workflows against %s with a disposable MySQL schema.\n' "$app_url"
PW_HEADED=1 \
APP_URL="$app_url" \
    RFIS_E2E_PROD_TARGET=rf-is-prod \
    RFIS_DEMO_PASSWORD="$demo_password" \
    npm --prefix e2e run test:headed -- "$@"

