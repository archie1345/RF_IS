# Development database backup and recovery

The development MySQL database uses the persistent `rf_is_dev_db-data` named volume. A Docker volume is not a backup. Do not use `docker compose down -v`, `migrate:fresh`, `db:wipe`, or a destructive seeder against it.

## Create a consistent logical backup

Run this from the repository with the development database container running. The MySQL password stays inside the container environment and is not printed by the command.

```bash
set -euo pipefail
umask 077
backup_dir="${XDG_STATE_HOME:-$HOME/.local/state}/rf-is/mysql"
install -d -m 700 "$backup_dir"
backup_stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="$backup_dir/dev-$backup_stamp.sql"

docker exec -i rf-db-dev sh -s > "$backup_file" <<'MYSQL'
set -eu
umask 077
client_config="$(mktemp)"
trap 'rm -f "$client_config"' EXIT
printf '[client]\nuser=root\npassword=%s\n' "$MYSQL_ROOT_PASSWORD" > "$client_config"
mysqldump --defaults-extra-file="$client_config" --single-transaction --routines --triggers --events --hex-blob --set-gtid-purged=OFF --databases "$MYSQL_DATABASE"
MYSQL
sha256sum "$backup_file" > "$backup_file.sha256"
chmod 600 "$backup_file" "$backup_file.sha256"
```

`--single-transaction` gives a consistent point-in-time dump for InnoDB tables without blocking normal writes. Check table engines first if the application adds non-InnoDB tables; arrange a maintenance window and an appropriate locking backup for those tables. Confirm the dump completed, record its checksum and capture time, and copy backups to storage outside this WSL installation. A local copy on the same disk does not protect against disk loss.

The guarded development reset helper `scripts/dev-reset-seed.sh` now performs a restore check before it runs `migrate:fresh`: it loads the dump into a temporary MySQL 8.4 container with no network and tmpfs-only storage, then compares table count, user count, migration count, and latest migration batch to the active pre-reset summary. A mismatch aborts the reset.

## Verify recovery without touching the active database

Restore a backup into a disposable MySQL instance with the same major version, using temporary storage and an isolated network with no host port. Never attach the active named volume to the verifier. Check the restored migration history, table counts, foreign-key relationships, and application-specific invariants. Record the source checksum, restore result, and validation output. Keep the verified backup and its recovery logs until a newer backup has also been verified.

### Verification record — 2026-10-27

The pre-reset development snapshot `/home/arch13/.local/state/rf-is/mysql/pre-reset-20261027T120815Z.3kz5Oz.sql` passed its SHA-256 check (`bb8b2889513015b79bcbcb369ba46d7462793f0ded21d6db6e343258412423e2`) and restored into a separate MySQL 8.4 container with `--network none` and tmpfs-only data storage. The restored schema contained 42 tables, 13 users, and 79 migration records in batch 1; those counts matched the active pre-reset summary. The verifier container was removed after validation; the active development and production databases were not used for the restore.

Binary logs only support point-in-time recovery when the complete contiguous stream from the backup position through the target time is available. Record `SHOW BINARY LOG STATUS` with each backup and preserve every required log. A gap in files or a different server UUID means the chain is not proven continuous.

## Restore boundary

The project owner has authorized reset, migration, and reseeding of the positively identified RF_IS development database for testing. Before each such operation, keep the reset helper's consistent backup and restore check. This authorization does not permit targeting an unidentified or unrelated database. Production source data remains outside the development reset path. Prefer a tested logical restore into a newly created database or volume over modifying the only copy in place.

Automated application tests must use the guarded `composer test` command. It runs the suite in a network-disabled container with SQLite `:memory:`; do not bypass it with a direct Pest/PHPUnit invocation.

### Follow-up verification — 2026-10-27

The last guarded development acceptance cleanup retained `/home/arch13/.local/state/rf-is/mysql/pre-reset-20261027T160031Z.t6RNto.sql` (mode 0600, SHA-256 `5c7d4ae2031427af352e8e12d622079af99a0fb37afc81dc1e932fcb25dba679`). The reset helper verified the logical backup in a disposable MySQL instance before resetting, then finished with 42 tables, 13 synthetic users, 79 migrations in batch 1, five training groups, 17 sessions, 22 payments, and four events. The persistent `rf_is_dev_db-data` named volume was retained. A local development MySQL root credential rotation recreated only the database container and did not alter the schema or records.

The production audit ran against a separately named disposable schema and database user on the verified RF_IS MySQL service. Its before/after source summary remained 43 tables, 1 user, 77 migrations in batch 3; the temporary schema, user, app container, and TLS proxy were removed. The persistent production application services remained stopped.

### Verification — 2026-10-28

The final guarded headed development suite backed up and verified the positively identified `rf-is-dev` database before its reset. The retained pre-reset dump is `/home/arch13/.local/state/rf-is/mysql/pre-reset-20261027T183005Z.8roCBg.sql` (mode 0600; SHA-256 `85f70b88f32f7772c767a76bb6a2b5797931879d46ddc559bd710d1fb8b576c1`). After test cleanup, the wrapper reran the migrations and deterministic seeders, verified 42 tables, 13 synthetic users, 79 migrations in batch 1, five training groups, 18 sessions, 22 payments, and four events, then restarted the app and scheduler. The development named volume was retained.

The final production-mode browser run used only a unique disposable schema and user, plus tmpfs application storage. Its source-schema comparison stayed at 43 tables, 1 user, 77 migrations, batch 3. A read-only post-run query found zero `rfis_e2e_*` schemas and zero corresponding database users. The persistent production schema and volume were not modified.

The expanded 70-case production-mode rerun completed with 68 passed and two Mailpit-only skips. The post-run source query still returned `rf_is:43 tables:1 user:77 migrations:batch 3`; no temporary E2E schema or user remained. The final guarded development full run completed with 66 passed and four persistent-storage cases skipped, restored the 42-table/13-user/79-migration seed state, and retained the named volume. Subsequent focused billing desktop/mobile and account-lifecycle runs also completed their guarded restore. The latest verified pre-reset dump is `/home/arch13/.local/state/rf-is/mysql/pre-reset-20261027T201548Z.VYg1Vg.sql` (mode 0600, SHA-256 `64a34aa71ff831f7432689cf0d2c8e9a2254bbcf94351f762540d2005bd1f3ae`). The focused production-mode billing and account-lifecycle runs used disposable schemas; the source schema remained `43 tables / 1 user / 77 migrations / batch 3` and the temporary app, proxy, schema, and user were removed. The development lifecycle regression created and permanently deleted only a uniquely named synthetic account during the guarded browser run; post-test reset restored 42 tables, 13 users, 79 migrations in batch 1, and the named volume remained intact.


### Final guarded E2E backup — 2026-10-28

The final development browser runner created and restore-verified /home/arch13/.local/state/rf-is/mysql/pre-reset-20261027T234258Z.ktAyRM.sql (SHA-256 b783358a777a7fbbcb6757d0711cd351da2fa494bdf715688696f1ba50012d34). The test cleanup restored the deterministic development seed state: 42 tables, 13 synthetic users, 79 migrations in batch 1, five groups, 18 sessions, 22 payments, and four events. The existing named volume was retained. The final production-mode browser suite used only a temporary schema/user and tmpfs storage; its source-schema checks remained unchanged and cleanup removed those temporary database objects.


### Final full-suite restore verification — 2026-10-28

Before the final guarded development suite, the runner created and restored a consistent snapshot at /home/arch13/.local/state/rf-is/mysql/pre-reset-20261028T003028Z.8YHI63.sql (SHA-256 9c3b13be4e6fdd10b3875d7114a8a1e36a74231baa5aea3646e2c840a642231a). After the browser suite, it also created /home/arch13/.local/state/rf-is/mysql/pre-reset-20261028T004622Z.8PioUy.sql (SHA-256 358e9d75fa4925c42b78493643a6896d02803c54c35007ab1350aa20d01c6315), verified a restore in temporary MySQL, and returned the development database to 42 tables, 13 users, 79 migrations batch 1, five groups, 18 sessions, 22 payments, and four events. Both snapshots are outside the repository and mode 0600. The named volume was retained.

### Interrupted production audit — recovery state 2026-10-28

At the preceding handoff, read-only artifact inspection found `/tmp/rfis-production-audit-proxy.8vrmkm/Caddyfile`, still present after the production run. It identifies app container `rfis-production-audit-20261028114219_66972`; the runner derives proxy container, disposable database, and database user as `rfis-production-audit-proxy-20261028114219_66972` and `rfis_e2e_20261028114219_66972`. Their existence and cleanup state could not be checked while Docker Desktop and its WSL distro were stopped. Do not delete or drop these resources until identities are verified against the guarded MySQL container.

At that handoff, no post-run source-schema query existed for this interrupted run. The last completed guarded audit confirmed `43 tables / 1 user / 77 migrations / batch 3` and removed its temporary schema/user. The production Compose DB uses `restart: unless-stopped`; Docker startup could restart persistent `rf-db`, so startup awaited instruction under the no-restart rule. The development synthetic database was untouched during that continuation.

### Resume verification — 2026-10-28

Docker Engine 29.9.2 is reachable. `rf-db` was already running healthy after Docker startup, despite the hold-stopped startup plan; this continuation did not start or restart any persistent production service. Read-only source queries before and after the final guarded audit returned `43 tables / 1 user / 77 migrations / batch 3`.

The interrupted run's disposable schema/user `rfis_e2e_20261028114219_66972`, exited app/proxy containers `rfis-production-audit-20261028114219_66972` and `rfis-production-audit-proxy-20261028114219_66972`, and `/tmp/rfis-production-audit-proxy.8vrmkm/Caddyfile` remain. The later guarded audits removed their own temporary schema/user, app/proxy containers, and proxy directory. The interrupted resources were left untouched under the explicit no-destructive-database instruction.

The current development schema was queried read-only and remains at `42 tables / 13 users / 79 migrations in batch 1 / 5 training groups / 18 sessions / 22 payments / 4 events`. The development-role runner was not run: its verified backup helper then performs `migrate:fresh --seed` against persistent `laravel`, which does not restore the original row set. Use a disposable clone for Mailpit-backed development workflows.

