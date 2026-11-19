#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$repo_root"

die() {
    printf 'Refusing development database reset: %s\n' "$1" >&2
    exit 1
}

command -v docker >/dev/null || die 'Docker is required.'
command -v jq >/dev/null || die 'jq is required.'
command -v openssl >/dev/null || die 'OpenSSL is required.'
[[ -f .env ]] || die 'The project .env file is missing.'

compose_file=compose.dev.yaml
compose_config="$(docker compose -f "$compose_file" config --format json)"
project="$(jq -r '.name' <<< "$compose_config")"
[[ "$project" == rf-is-dev ]] || die "unexpected Compose project: $project"

compose_env() {
    jq -r --arg key "$2" --arg service "$1" '.services[$service].environment[$key] // ""' <<< "$compose_config"
}

[[ "$(compose_env server APP_ENV)" == local ]] || die 'Compose Laravel APP_ENV is not local.'
[[ "$(compose_env server DB_CONNECTION)" == mysql ]] || die 'Compose Laravel DB_CONNECTION is not mysql.'
[[ "$(compose_env server DB_HOST)" == db ]] || die 'Compose Laravel DB_HOST is not the development service.'
[[ "$(compose_env server DB_DATABASE)" == laravel ]] || die 'Compose Laravel DB_DATABASE is not laravel.'
[[ -z "$(compose_env server DB_URL)" ]] || die 'Compose Laravel DB_URL overrides the verified development connection.'
[[ "$(compose_env db MYSQL_DATABASE)" == laravel ]] || die 'Compose MySQL database is not laravel.'
compose_volume_key="$(jq -r '.services.db.volumes[] | select(.target == "/var/lib/mysql") | .source' <<< "$compose_config")"
compose_volume_name="$(jq -r --arg volume "$compose_volume_key" '.volumes[$volume].name // ""' <<< "$compose_config")"
[[ "$compose_volume_name" == rf_is_dev_db-data ]] || die "Compose database volume is not the verified development volume: $compose_volume_name"

db_container="$(jq -r '.services.db.container_name' <<< "$compose_config")"
server_container="$(jq -r '.services.server.container_name' <<< "$compose_config")"
scheduler_container="$(jq -r '.services.scheduler.container_name' <<< "$compose_config")"
[[ "$db_container" == rf-db-dev ]] || die "unexpected database container: $db_container"
[[ "$server_container" == rf-server-dev ]] || die "unexpected app container: $server_container"
[[ "$scheduler_container" == rf-scheduler-dev ]] || die "unexpected scheduler container: $scheduler_container"

compose_identity() {
    docker inspect "$1" --format '{{index .Config.Labels "com.docker.compose.project"}} {{index .Config.Labels "com.docker.compose.service"}}'
}

[[ "$(compose_identity "$db_container")" == 'rf-is-dev db' ]] || die 'database container does not belong to rf-is-dev/db.'
[[ "$(compose_identity "$server_container")" == 'rf-is-dev server' ]] || die 'app container does not belong to rf-is-dev/server.'
[[ "$(compose_identity "$scheduler_container")" == 'rf-is-dev scheduler' ]] || die 'scheduler container does not belong to rf-is-dev/scheduler.'

database_volume="$(docker inspect "$db_container" --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}')"
[[ "$database_volume" == rf_is_dev_db-data ]] || die "unexpected MySQL volume: $database_volume"

container_env() {
    docker inspect "$1" --format '{{range .Config.Env}}{{println .}}{{end}}' \
        | awk -F= -v key="$2" '$1 == key { sub(/^[^=]*=/, ""); print; exit }'
}

[[ "$(container_env "$server_container" APP_ENV)" == local ]] || die 'Laravel APP_ENV is not local.'
[[ "$(container_env "$server_container" DB_CONNECTION)" == mysql ]] || die 'Laravel is not configured for MySQL.'
[[ "$(container_env "$server_container" DB_HOST)" == db ]] || die 'Laravel DB_HOST is not the development Compose service.'
[[ "$(container_env "$server_container" DB_DATABASE)" == laravel ]] || die 'Laravel DB_DATABASE is not laravel.'
[[ "$(container_env "$db_container" MYSQL_DATABASE)" == laravel ]] || die 'MySQL MYSQL_DATABASE is not laravel.'
[[ "$(container_env "$db_container" MYSQL_ROOT_PASSWORD)" != '' ]] || die 'MySQL root credentials are not configured.'
[[ "$(docker inspect "$db_container" --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}')" == 'running healthy' ]] || die 'the verified development MySQL container is not healthy.'
[[ "$(docker inspect "$db_container" --format '{{.Config.Image}}')" == rf-is:mysql-8.4.13 ]] || die 'the development database image is not the guarded RF_IS MySQL 8.4.13 image.'

chmod 600 .env
demo_password_count="$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { count++ } END { print count + 0 }' .env)"
[[ "$demo_password_count" -le 1 ]] || die 'RFIS_DEMO_PASSWORD appears more than once in .env.'
demo_password_length="$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { value = substr($0, index($0, "=") + 1); print length(value); exit }' .env)"
if [[ "$demo_password_count" == 0 || "${demo_password_length:-0}" -lt 16 ]]; then
    demo_password="$(openssl rand -hex 24)"
    if [[ "$demo_password_count" == 0 ]]; then
        printf '\nRFIS_DEMO_PASSWORD=%s\n' "$demo_password" >> .env
    else
        temporary_env="$(mktemp .env.XXXXXX)"
        awk -F= -v password="$demo_password" '
            $1 == "RFIS_DEMO_PASSWORD" { print "RFIS_DEMO_PASSWORD=" password; next }
            { print }
        ' .env > "$temporary_env"
        chmod 600 "$temporary_env"
        mv "$temporary_env" .env
    fi
    unset demo_password
fi
chmod 600 .env

demo_password_length="$(awk -F= '$1 == "RFIS_DEMO_PASSWORD" { value = substr($0, index($0, "=") + 1); print length(value); exit }' .env)"
[[ "${demo_password_length:-0}" -ge 16 ]] || die 'RFIS_DEMO_PASSWORD must contain at least 16 characters.'

mysql_version="$(docker exec -i "$db_container" sh -s <<'MYSQL'
set -eu
client_config="$(mktemp)"
trap 'rm -f "$client_config"' EXIT
umask 077
printf '[client]\nuser=root\npassword=%s\n' "$MYSQL_ROOT_PASSWORD" > "$client_config"
mysql --defaults-extra-file="$client_config" --batch --skip-column-names --execute='SELECT CONCAT(VERSION(), "|", DATABASE())' laravel </dev/null
MYSQL
)"
[[ "$mysql_version" == 8.4.*'|laravel' ]] || die 'the active MySQL server did not confirm version 8.4 and schema laravel.'

backup_dir="${XDG_STATE_HOME:-$HOME/.local/state}/rf-is/mysql"
install -d -m 700 "$backup_dir"
backup_dir="$(realpath "$backup_dir")"
case "$backup_dir/" in
    "$repo_root/"*) die 'backup directory must be outside the repository.' ;;
esac
backup_stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="$(mktemp --suffix=.sql "$backup_dir/pre-reset-$backup_stamp.XXXXXX")"
umask 077

docker exec -i "$db_container" sh -s > "$backup_file" <<'MYSQL'
set -eu
umask 077
client_config="$(mktemp)"
trap 'rm -f "$client_config"' EXIT
printf '[client]\nuser=root\npassword=%s\n' "$MYSQL_ROOT_PASSWORD" > "$client_config"
mysqldump --defaults-extra-file="$client_config" --single-transaction --routines --triggers --events --hex-blob --set-gtid-purged=OFF --databases "$MYSQL_DATABASE" </dev/null
MYSQL

[[ -s "$backup_file" ]] || die 'consistent pre-reset dump is empty.'
chmod 600 "$backup_file"
sha256sum "$backup_file" > "$backup_file.sha256"
chmod 600 "$backup_file.sha256"

verify_container="rf-is-dev-backup-verify-$$"
cleanup_backup_verifier() {
    docker rm --force "$verify_container" >/dev/null 2>&1 || true
}
trap cleanup_backup_verifier EXIT

source_summary="$(docker exec -i "$db_container" sh -s <<'MYSQL'
set -eu
client_config="$(mktemp)"
trap 'rm -f "$client_config"' EXIT
umask 077
printf '[client]\nuser=root\npassword=%s\n' "$MYSQL_ROOT_PASSWORD" > "$client_config"
mysql --defaults-extra-file="$client_config" --batch --skip-column-names --execute="
SELECT CONCAT(
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='laravel' AND table_type='BASE TABLE'), ':',
    (SELECT COUNT(*) FROM laravel.users), ':',
    (SELECT COUNT(*) FROM laravel.migrations), ':',
    (SELECT COALESCE(MAX(batch), 0) FROM laravel.migrations)
)
" laravel </dev/null
MYSQL
)"
[[ -n "$source_summary" ]] || die 'could not capture the pre-reset integrity summary.'

docker run --detach \
    --name "$verify_container" \
    --network none \
    --tmpfs /var/lib/mysql:rw,nosuid,nodev,size=512m \
    --tmpfs /var/run/mysqld:rw,nosuid,nodev,size=16m \
    --env MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    mysql:8.4.13 >/dev/null

backup_ready=0
for attempt in $(seq 1 60); do
    if docker logs "$verify_container" 2>&1 | grep -q 'MySQL init process done. Ready for start up.' \
        && docker exec "$verify_container" mysql --protocol=socket -uroot --batch --skip-column-names --execute='SELECT 1' >/dev/null 2>&1; then
        backup_ready=1
        break
    fi

    if [[ "$(docker inspect "$verify_container" --format '{{.State.Running}}')" != true ]]; then
        docker logs "$verify_container" >&2
        die 'the isolated MySQL backup verifier exited during initialization.'
    fi

    sleep 1
done
[[ "$backup_ready" == 1 ]] || die 'the isolated MySQL backup verifier did not become ready.'

docker exec -i "$verify_container" mysql --protocol=socket -uroot < "$backup_file"
restored_summary="$(docker exec "$verify_container" mysql --protocol=socket -uroot --batch --skip-column-names --execute="
SELECT CONCAT(
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='laravel' AND table_type='BASE TABLE'), ':',
    (SELECT COUNT(*) FROM laravel.users), ':',
    (SELECT COUNT(*) FROM laravel.migrations), ':',
    (SELECT COALESCE(MAX(batch), 0) FROM laravel.migrations)
)
")"
[[ "$restored_summary" == "$source_summary" ]] || die 'the restored backup integrity summary differs from the active pre-reset database.'
cleanup_backup_verifier
trap - EXIT
printf 'Backup restore verification passed: tables:users:migrations:max_batch=%s\n' "$restored_summary"

for container in "$server_container" "$scheduler_container"; do
    if [[ "$(docker inspect "$container" --format '{{.State.Running}}')" == true ]]; then
        docker stop "$container" >/dev/null
    fi
done

docker compose -f "$compose_file" run --rm --no-deps \
    -e RFIS_ALLOW_DEMO_SEEDING=true \
    --entrypoint sh server -ec 'php artisan config:clear && php artisan migrate:fresh --seed --force --no-interaction'

printf 'Development schema reset and seeded. Backup: %s\n' "$backup_file"
printf 'Backup SHA-256: %s\n' "$(sha256sum "$backup_file" | cut -d' ' -f1)"

docker exec -i "$db_container" sh -s <<'MYSQL'
set -eu
client_config="$(mktemp)"
trap 'rm -f "$client_config"' EXIT
umask 077
printf '[client]\nuser=root\npassword=%s\n' "$MYSQL_ROOT_PASSWORD" > "$client_config"
mysql --defaults-extra-file="$client_config" --batch --skip-column-names --database=laravel <<'SQL'
SELECT CONCAT('tables=', COUNT(*)) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
SELECT CONCAT('users=', COUNT(*)) FROM users;
SELECT CONCAT('migrations=', COUNT(*), ',max_batch=', MAX(batch)) FROM migrations;
SELECT CONCAT('training_groups=', COUNT(*)) FROM training_groups;
SELECT CONCAT('sessions=', COUNT(*)) FROM training_sessions;
SELECT CONCAT('payments=', COUNT(*)) FROM payments;
SELECT CONCAT('events=', COUNT(*)) FROM events;
SELECT GROUP_CONCAT(CONCAT(role_combination, '=', user_count) ORDER BY role_combination SEPARATOR ',') FROM (SELECT role_combination, COUNT(*) AS user_count FROM (SELECT GROUP_CONCAT(ura.role ORDER BY FIELD(ura.role, 'admin', 'coach', 'athlete') SEPARATOR '+') AS role_combination FROM user_role_assignments ura GROUP BY ura.user_id) AS assignments GROUP BY role_combination) AS role_counts;
SQL
MYSQL
