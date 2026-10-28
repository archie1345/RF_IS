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

## Docker Notes

Development and production use separate Compose files. Start development with `docker compose -f compose.dev.yaml up -d --build`. App startup does not apply database migrations; review pending migrations and verify a backup before applying them explicitly. The production stack is defined in `compose.prod.yaml`; it was not verified as a deployed system by this repository guide.

Example image build:

```bash
docker build -t rf-is:local .
```

Example container run, assuming an external database is reachable from the container network:

```bash
docker run --rm -p 8080:80 --env-file .env rf-is:local
```

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

## Verification Commands

```bash
php artisan route:list
composer run test:lint
npm run lint:check
npm run typecheck
npm run build
git status --short --branch
```

Use the isolated backend and headed browser commands in [README.Docker.md](README.Docker.md). Do not use `migrate:fresh` as a test setup command; the feature suite uses the in-memory SQLite database forced by `phpunit.xml`.
