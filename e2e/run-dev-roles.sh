#!/usr/bin/env bash
set -Eeuo pipefail

if [[ -d "${HOME:-}/.local/bin" ]]; then
    export PATH="$HOME/.local/bin:$PATH"
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$repo_root"

die() {
    printf 'Refusing role E2E run: %s\n' "$1" >&2
    exit 1
}

if [[ -z "${DISPLAY:-}" && -z "${WAYLAND_DISPLAY:-}" ]]; then
    die 'headed Chromium requires WSLg or an X server (DISPLAY or WAYLAND_DISPLAY).'
fi

command -v docker >/dev/null || die 'Docker is required.'
command -v jq >/dev/null || die 'jq is required.'
command -v curl >/dev/null || die 'curl is required.'
command -v flock >/dev/null || die 'flock is required to prevent overlapping database resets and artifact writes.'
[[ -f .env ]] || die 'the local .env file is missing.'

artifacts=e2e/test-results
mkdir -p "$artifacts"
exec {e2e_lock_fd}>"$artifacts/acceptance.lock"
flock -n "$e2e_lock_fd" || die 'another browser acceptance run is active; wait for it to finish before resetting the development database.'

compose_file=compose.dev.yaml
compose_config="$(docker compose -f "$compose_file" config --format json)"
[[ "$(jq -r '.name' <<< "$compose_config")" == rf-is-dev ]] || die 'unexpected Compose project.'

compose_env() {
    jq -r --arg key "$2" --arg service "$1" '.services[$service].environment[$key] // ""' <<< "$compose_config"
}

[[ "$(compose_env server APP_ENV)" == local ]] || die 'Laravel must use the local environment.'
[[ "$(compose_env server DB_CONNECTION)" == mysql ]] || die 'the development app must use MySQL.'
[[ "$(compose_env server DB_HOST)" == db ]] || die 'the development app must connect to the Compose db service.'
[[ "$(compose_env server DB_DATABASE)" == laravel ]] || die 'the development app database must be laravel.'
[[ -z "$(compose_env server DB_URL)" ]] || die 'DB_URL overrides the verified development connection.'
[[ "$(compose_env server MAIL_MAILER)" == smtp ]] || die 'development browser tests require the SMTP-only Mailpit transport.'
[[ "$(compose_env server MAIL_HOST)" == mailpit && "$(compose_env server MAIL_PORT)" == 1025 ]] || die 'development browser tests must deliver mail only to the Compose Mailpit service.'
[[ "$(compose_env server MAIL_URL)" == null && -z "$(compose_env server MAIL_USERNAME)" && -z "$(compose_env server MAIL_PASSWORD)" ]] || die 'development SMTP URL and credentials must not override the local Mailpit transport.'
[[ "$(compose_env db MYSQL_DATABASE)" == laravel ]] || die 'the Compose database name is not laravel.'

db_container="$(jq -r '.services.db.container_name' <<< "$compose_config")"
server_container="$(jq -r '.services.server.container_name' <<< "$compose_config")"
[[ "$db_container" == rf-db-dev ]] || die 'unexpected database container name.'
[[ "$server_container" == rf-server-dev ]] || die 'unexpected application container name.'

compose_identity() {
    docker inspect "$1" --format '{{index .Config.Labels "com.docker.compose.project"}} {{index .Config.Labels "com.docker.compose.service"}}'
}

container_env() {
    docker inspect "$1" --format '{{range .Config.Env}}{{println .}}{{end}}' |
        awk -F= -v key="$2" '$1 == key { sub(/^[^=]*=/, ""); print; exit }'
}

[[ "$(compose_identity "$db_container")" == 'rf-is-dev db' ]] || die 'database container identity does not match development Compose.'
[[ "$(compose_identity "$server_container")" == 'rf-is-dev server' ]] || die 'app container identity does not match development Compose.'
[[ "$(container_env "$server_container" MAIL_MAILER)" == smtp ]] || die 'the running development app is not configured for local SMTP.'
[[ "$(container_env "$server_container" MAIL_HOST)" == mailpit && "$(container_env "$server_container" MAIL_PORT)" == 1025 ]] || die 'the running development app must send mail only to Mailpit.'
[[ "$(container_env "$server_container" MAIL_URL)" == null && -z "$(container_env "$server_container" MAIL_USERNAME)" && -z "$(container_env "$server_container" MAIL_PASSWORD)" ]] || die 'the running development app has an SMTP URL or credentials that could bypass Mailpit.'
[[ "$(docker inspect "$db_container" --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}')" == 'running healthy' ]] || die 'development MySQL is not healthy.'
[[ "$(docker inspect "$db_container" --format '{{.Config.Image}}')" == rf-is:mysql-8.4.13 ]] || die 'development MySQL image is not the guarded RF_IS MySQL 8.4.13 image.'
[[ "$(docker inspect "$db_container" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}')" == rf_is_dev_db-data ]] || die 'unexpected development MySQL volume.'
[[ "$(docker inspect "$db_container" --format '{{range .Config.Env}}{{println .}}{{end}}' | awk -F= '$1 == "MYSQL_DATABASE" { print $2; exit }')" == laravel ]] || die 'active MySQL container database is not laravel.'
[[ "$(docker inspect "$server_container" --format '{{.State.Status}}')" == running ]] || die 'the actual development app is not running.'
app_gateway="$(docker inspect "$server_container" --format '{{range .NetworkSettings.Networks}}{{.Gateway}}{{end}}')"
[[ -n "$app_gateway" && "$(compose_env server TRUSTED_PROXIES)" == "$app_gateway" ]] || die 'the app must trust only the verified development Docker gateway for forwarded headers.'

jq -e '.services.server.ports[] | select(.target == 80 and .published == "9200" and .host_ip == "127.0.0.1")' <<< "$compose_config" >/dev/null || die 'the development app is not loopback-bound on port 9200.'
jq -e '.services.mailpit.ports[] | select(.target == 8025 and .published == "9204" and .host_ip == "127.0.0.1")' <<< "$compose_config" >/dev/null || die 'Mailpit must remain loopback-bound on port 9204.'
jq -e '.services.mailpit.ports[] | select(.target == 1025 and .published == "1025" and .host_ip == "127.0.0.1")' <<< "$compose_config" >/dev/null || die 'Mailpit SMTP must remain loopback-bound on port 1025.'

demo_password="$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { print substr($0, index($0, "=") + 1); exit }' .env)"
[[ "${#demo_password}" -ge 16 ]] || die 'RFIS_DEMO_PASSWORD is missing or too short.'

app_url=http://localhost:9200
restore_required=0

app_ready=false
for attempt in $(seq 1 30); do
    state="$(docker inspect "$server_container" --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}')"
    if [[ "$state" == 'running healthy' ]] && curl --silent --fail "$app_url/up" >/dev/null 2>&1; then
        app_ready=true
        break
    fi
    [[ "$state" != 'exited '* && "$state" != 'dead '* && "$state" != 'running unhealthy' ]] || die "the development app failed its health check ($state)."
    sleep 1
done
[[ "$app_ready" == true ]] || die 'the actual development app did not become healthy before the role E2E run.'

cleanup() {
    result=$?
    trap - EXIT HUP INT TERM
    unset demo_password

    if [[ "$restore_required" == 1 ]]; then
        printf '%s\n' 'Restoring the deterministic demo database after browser acceptance tests.'
        if ! bash scripts/dev-reset-seed.sh; then
            printf '%s\n' 'The post-test development reset failed; inspect the guarded reset output.' >&2
            result=1
        elif ! docker compose -f "$compose_file" up -d --no-deps server scheduler; then
            printf '%s\n' 'The app and scheduler containers could not be restarted after the post-test reset.' >&2
            result=1
        fi
    fi

    exit "$result"
}
trap cleanup EXIT HUP INT TERM

restore_required=1
bash scripts/dev-reset-seed.sh
docker compose -f "$compose_file" up -d --no-deps server

ready=false
for attempt in $(seq 1 60); do
    if curl --silent --fail "$app_url/up" >/dev/null 2>&1 \
        && [[ "$(docker inspect "$server_container" --format '{{.State.Health.Status}}')" == healthy ]]; then
        ready=true
        break
    fi

    sleep 2
done
[[ "$ready" == true ]] || die 'the actual dev app did not become healthy at port 9200.'

printf 'Running headed role workflows against %s with guarded development fixtures.\n' "$app_url"
PW_HEADED=1 \
APP_URL="$app_url" \
    RFIS_E2E_DEV_TARGET=rf-is-dev \
    RFIS_E2E_PERSISTENT_STORAGE=1 \
    RFIS_DEMO_PASSWORD="$demo_password" \
    npm --prefix e2e run test:roles -- "$@"
