# RFIS full-system demo data

`DatabaseSeeder` creates synthetic development users and workflow records for the supported roles: `admin`, `coach`, and `athlete`. Role assignments combine additively, so the demo accounts cover every non-empty role combination.

## Safety and repeatable reset

Demo seeders reject production, require in-memory SQLite under PHPUnit, and require `RFIS_ALLOW_DEMO_SEEDING=true` for local MySQL. Direct calls to the application and multirole demo seeders use the same guard.

For the verified `rf-is-dev` Compose project only, run:

```bash
bash scripts/dev-reset-seed.sh
```

The script checks the Compose project, container identities, Laravel DB host/name/environment, and exact development volume. It makes a consistent owner-only backup outside the repository, stops the app and scheduler if running, then runs fresh migrations and `DatabaseSeeder`. It does not delete the MySQL volume. The development password is randomly generated into ignored `.env` as `RFIS_DEMO_PASSWORD` if missing; it is never printed or committed.

Do not point the reset script at another Compose project. Backend tests continue to use the isolated `composer test` command with SQLite `:memory:`.

## Accounts and role combinations

| Roles | Account |
|---|---|
| Admin | `admin@rfis.test` |
| Coach | `coach@rfis.test` |
| Athlete | `athlete@rfis.test` |
| Coach + Athlete | `multirole@rfis.test` |
| Admin + Coach | `admincoach@rfis.test` |
| Admin + Athlete | `adminathlete@rfis.test` |
| Admin + Coach + Athlete | `allroles@rfis.test` |

All accounts use the local-only value in `.env` under `RFIS_DEMO_PASSWORD`. Keep `.env` ignored and do not reuse this credential elsewhere.

## Synthetic workflow coverage

Seed records cover branches, training groups, classes, weekly schedules, sessions, coach assignments, attendance statuses, QR attendance initialization, payments, proof states, transactions, payroll, events, registrations, announcements, achievements, and certifications. Values are synthetic and intended for local development. The seeders use stable email/name/reference keys so repeated seed calls update the demo rows instead of multiplying them.

The database rebuild command is destructive to the configured development schema. Its checks and backup do not make it suitable for any other database.
