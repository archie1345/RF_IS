# RF IS - Training & Operations Management

RF IS is a Laravel 12 + Inertia + Vue application for managing athlete and guardian contact records, coach sessions and attendance, manual bill/invoice proof review, championships, and administrative data exports.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel plus `pdo_mysql`, `gd`, `zip`, and `bcmath`.
- Composer 2.
- Node.js 20+ and npm.
- MySQL/MariaDB for local development, or SQLite for tests.
- A writable `storage/` directory and `bootstrap/cache/` directory.

## Fresh Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` before migrating:

- Set `APP_URL` to your local URL, for example `http://127.0.0.1:8000`.
- Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for your local database.
- Keep `APP_DEBUG=true` only for local development.
- Use `QUEUE_CONNECTION=database` locally unless you are running a separate queue backend.
- Use `MAIL_MAILER=log` locally so invitation/reset email content is written to logs instead of sent.
- Use database-backed session/cache drivers locally when the migrations have been run.

Then run:

```bash
php artisan migrate
php artisan storage:link
npm ci
npm run build
php artisan serve
```

Run migrations only after confirming `.env` points to the intended local database and a verified backup exists. App and dependency setup commands do not migrate automatically.

`DatabaseSeeder` invokes `FullSystemSimulationSeeder`, which creates synthetic local accounts and workflow fixtures. Demo seeders are blocked in production and require an explicit local opt-in. For this repository's verified development Compose project, use `bash scripts/dev-reset-seed.sh`; it checks the project, container, schema, and named volume, saves a consistent backup outside the repository, then runs fresh migrations and seeders. Do not point this script at another Compose project or database.

Open the app at `http://127.0.0.1:8000` unless your `.env` uses a different `APP_URL`.

For active frontend development, run Vite separately:

```bash
npm run dev
```

Or run the combined Laravel helper from `composer.json`:

```bash
composer run dev
```

## Demo Accounts

The default seeder creates local/demo-only accounts. Do not use these credentials in production.

| Roles                      | Email                  | Demo purpose                                      |
| -------------------------- | ---------------------- | ------------------------------------------------- |
| Admin                      | `admin@rfis.test`      | Admin dashboard, users, finance, attendance       |
| Coach                      | `coach@rfis.test`      | Assigned sessions and attendance                  |
| Athlete                    | `athlete@rfis.test`    | Own profile, payments, attendance, championships  |
| Coach + Athlete            | `multirole@rfis.test`  | Combined coach and athlete access                 |
| Admin + Coach              | `admincoach@rfis.test` | Combined admin and coach access                   |
| Admin + Athlete            | `adminathlete@rfis.test` | Combined admin and athlete access                |
| Admin + Coach + Athlete    | `allroles@rfis.test`   | Full role-union access                            |

All demo users use the development-only password configured in the ignored `.env` setting `RFIS_DEMO_PASSWORD`. The reset script creates a random value if the setting is missing, does not print it, and keeps it out of version control. Do not use it outside this local application.

Seed data is synthetic and local/demo-only. It includes one branch, one group/class, athlete guardian contact details, coach and athlete profiles, attendance/session data, championship data, and manual bill/invoice examples. The parent login role and parent portal have been removed; guardian details are stored on the athlete profile.

## MVP Demo Flow

### Admin

1. Log in as `admin@rfis.test`.
2. Open `Users`; search athletes and filter by branch/group/status.
3. Open athlete and coach accounts; verify guardian contact details on the athlete profile.
4. Open `Payments`; filter bills by proof queue/status/kind/category.
5. Issue or open a bill, upload proof from a member account, approve a partial amount, then approve a second proof to complete it.
6. Confirm paid/remaining amounts and Transaction History.
7. Open a session attendance sheet and generate a QR window inside the session start/end time.
8. Try an invalid QR window and confirm inline field errors.
9. Check CSV export/template/import from the admin surfaces.
10. Open Championships and confirm registration/payment context is understandable.

### Athlete

1. Log in as `athlete@rfis.test`.
2. View own profile, payment/bill information, attendance, and championship information where available.
3. Confirm admin-only actions are not visible.

### Coach

1. Log in as `coach@rfis.test`.
2. View assigned sessions/attendance.
3. Confirm admin-only actions and sensitive identifiers are hidden.

## Manual Payment Scope

Payments are currently an internal/manual bill proof review flow:

- Admins issue bills/invoices inside the app.
- Members upload payment proof.
- Admins approve or reject proof.
- Admins may approve a partial amount.
- Approved installments appear in Transaction History.
- Remaining amount is recalculated by the backend.
- Bills become complete only when the approved total reaches the total bill amount.

The following integrations are intentionally postponed:

- Full Midtrans gateway integration.
- Midtrans webhook integration.
- WhatsApp notification/API/template/scheduling.

## CSV Import/Export

Admin-only CSV transfer controls are available from the admin/payment surfaces for supported entities. Keep template headers unchanged, use UTF-8 CSV, and verify foreign-key IDs exist before importing. Sensitive identifiers should be treated as controlled data and should not be added to demo CSV files unless explicitly required for an admin-only test.

## File Uploads and Storage

Uploaded payment proofs, certifications, achievements, and profile images use Laravel storage. For local public access, run:

```bash
php artisan storage:link
```

Production deployments must ensure:

- `storage/` and `bootstrap/cache/` are writable by the web server user.
- The public storage symlink exists or equivalent shared storage is configured.
- Uploaded files are backed up according to deployment policy.

## Frontend Build and Wayfinder

Production assets are built with:

```bash
npm run build
```

The Vite Wayfinder plugin generates frontend action/route files during builds. Generated Wayfinder output is ignored by ESLint in this project; regenerate it with the normal build/dev commands rather than hand-editing generated files.

## Docker & Container Environment

The project provides isolated Docker environments for development, testing, and production. Configuration is split into `compose.dev.yaml` and `compose.prod.yaml`.

### Development Stack (`compose.dev.yaml`)

The development Compose project (`rf-is-dev`) provisions the complete stack with loopback-bound ports:

| Service | Container Name | Port Mapping | Description |
|---|---|---|---|
| `server` | `rf-server-dev` | `127.0.0.1:9200:80` | Apache + PHP 8.4 runtime with development INI & hot-reload mounts |
| `vite` | `rf-vite-dev` | `127.0.0.1:9201:9201` | Vite frontend dev server with Hot Module Replacement (HMR) |
| `phpmyadmin` | `rf-phpmyadmin-dev` | `127.0.0.1:9202:80` | Web database management for the local MySQL database |
| `db` | `rf-db-dev` | `127.0.0.1:9203:3306` | MySQL 8.4 database with persistent named volume (`rf_is_dev_db-data`) |
| `mailpit` | `rf-mailpit-dev` | `127.0.0.1:9204:8025` (Web)<br>`127.0.0.1:1025:1025` (SMTP) | Local email trap for invitations, password resets, and verification |
| `scheduler` | `rf-scheduler-dev` | _internal_ | Runs `php artisan schedule:work` background scheduler |
| `test` | _ephemeral_ | _none (isolated network)_ | Offline, read-only container for `composer test` (Pest / SQLite) |

#### Common Development Docker Commands

```bash
# Start all development services in background (build if images missing)
docker compose -f compose.dev.yaml up -d --build

# View real-time status of development services
docker compose -f compose.dev.yaml ps

# Inspect logs of all services (or specify service e.g. server, vite, db)
docker compose -f compose.dev.yaml logs -f
docker compose -f compose.dev.yaml logs -f server

# Execute commands inside the app container (as www-data or default user)
docker compose -f compose.dev.yaml exec server php artisan route:list
docker compose -f compose.dev.yaml exec server php artisan migrate --force
docker compose -f compose.dev.yaml exec server php artisan cache:clear

# Execute MySQL queries directly inside db container
docker compose -f compose.dev.yaml exec db mysql -u laravel -p laravel

# Stop all development containers without losing database volume
docker compose -f compose.dev.yaml down

# WARNING: Routine cleanup should NEVER remove volumes (-v), as it destroys local development data!
```

### Production Stack & Image Builds (`compose.prod.yaml` & `Dockerfile.prod`)

```bash
# Build the production target image locally
docker build -f Dockerfile.prod --target final -t rf-is:production .

# Run production audit image build (used by guarded audit script)
docker build -f Dockerfile.prod --target final -t rf-is:production-audit .

# Start production stack (uses .env.production, Caddy reverse proxy on port 80/443, and isolated backend network)
docker compose -f compose.prod.yaml up -d --build

# Run one-shot production migrations and cache optimization
docker compose -f compose.prod.yaml run --rm migrate

# Run production admin seeder (isolated profile)
docker compose -f compose.prod.yaml --profile tools run --rm seed-admin
```

See [README.Docker.md](README.Docker.md) and [Development database backup and recovery](docs/DATABASE_BACKUP_AND_RECOVERY.md) for deeper architecture details and safe backup/restore procedures.

## Deployment Checklist

Before production/demo deployment:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set a real `APP_KEY`; never deploy with the example key placeholder.
- Set `APP_URL` and any asset/CDN URL consistently.
- Configure database credentials and run `php artisan migrate --force`.
- Run `php artisan storage:link` or configure equivalent public file storage.
- Ensure `storage/` and `bootstrap/cache/` permissions are correct.
- Configure queue, cache, and session drivers for the hosting environment.
- Configure mail delivery for invitations/password resets, or keep `MAIL_MAILER=log` only for demos.
- Run `npm run build` and verify `public/build/manifest.json` exists.
- Keep production secrets out of Git, docs, exports, and logs.
- Back up the database before CSV imports or destructive admin operations.

## Verification and Quality Commands

```bash
# Code Style & Linting
composer run test:lint        # Check PHP syntax and code styling (Laravel Pint)
composer run lint             # Auto-fix PHP styling via Laravel Pint
npm run format:check          # Check formatting of resources/ via Prettier
npm run format                # Auto-fix formatting of resources/ via Prettier
npm run lint:check            # Check JS/Vue files via ESLint
npm run lint                  # Auto-fix JS/Vue files via ESLint

# Type Checking
npm run typecheck             # Check Vue and TypeScript components (vue-tsc)
npm run typecheck:e2e         # Check Playwright test files in e2e/ via tsc

# Comprehensive Quality Pipeline
npm run quality               # Runs format:check, lint:check, typecheck, typecheck:e2e, and build

# Dependency Security Audits
composer audit --locked       # Security audit for Composer PHP dependencies
npm audit --audit-level=low   # Security audit for root npm dependencies
npm --prefix e2e audit        # Security audit for E2E npm dependencies
```

## Testing Suites & Guarded Runners

> [!IMPORTANT]
> Never run destructive test commands directly against a persistent development database. Always use the guarded scripts.

```bash
# Backend Feature & Unit Tests (Pest PHP, Isolated SQLite in Container)
composer test                 # Runs Pint and isolated Pest tests inside compose.dev.yaml test runner

# Guarded Development E2E (Playwright)
# Backs up and reseeds the verified rf-is-dev database, runs desktop and mobile role suites, then restores dataset
bash e2e/run-dev-roles.sh

# Guarded Production-Mode Audit (Playwright)
# Builds disposable MySQL schema and tmpfs storage, runs audit suite, and cleans up completely
bash e2e/run-production-audit.sh

# Targeted E2E Runs
npm --prefix e2e run test:mobile                   # Runs mobile small (360x800) and large (412x915) Chromium viewports
npm --prefix e2e run test:headed                   # Runs Playwright in headed mode (requires WSLg / DISPLAY)
bash e2e/run-production-audit.sh -g "<pattern>"     # Runs only test titles matching regex pattern
```

## Database Management & Safe Reseeding

```bash
# Safe Development Reseed
# Verifies container, saves a timestamped backup, executes fresh migration, and populates synthetic simulation data
bash scripts/dev-reset-seed.sh

# Standard Artisan Migration & Storage (Manual)
php artisan migrate
php artisan storage:link
php artisan route:list
```

## Background Tasks & Scheduler

```bash
# Run Background Queue Listener
php artisan queue:listen --tries=1

# Run Scheduled Background Jobs
php artisan schedule:run

# Specific Scheduled Command Triggers
php artisan sessions:generate-from-weekly --days=14  # Generate upcoming sessions from weekly schedule
php artisan tuition:generate-monthly                # Generate monthly student tuition invoices
php artisan activity-logs:prune --days=90           # Prune activity logs older than retention period
```
