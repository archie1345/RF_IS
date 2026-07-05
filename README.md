# RF IS - Training & Operations Management

RF IS is a Laravel 12 + Inertia + Vue application for managing athlete records, parent-child visibility, coach sessions and attendance, manual bill/invoice proof review, championships, and admin CSV transfer workflows.

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
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

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

| Role    | Email               | Password   | Demo purpose                                               |
| ------- | ------------------- | ---------- | ---------------------------------------------------------- |
| Admin   | `admin@rfis.test`   | `12345678` | Admin dashboard, users, bills, attendance, CSV transfer    |
| Coach   | `coach@rfis.test`   | `12345678` | Session and attendance workflows                           |
| Parent  | `parent@rfis.test`  | `12345678` | Linked-child view and bill proof upload                    |
| Athlete | `athlete@rfis.test` | `12345678` | Own profile, payments, attendance, championship visibility |

Seed data is synthetic and local/demo-only. It includes one branch/ranting, one group/class, a linked parent-child relationship, a coach profile, an athlete profile, attendance/session data, championship data, and manual bill/invoice examples.

## MVP Demo Flow

### Admin

1. Log in as `admin@rfis.test`.
2. Open `Users`; search athletes and filter by branch/group/status.
3. Open parent and coach tables; verify parent-child linking from the Link Children modal.
4. Open `Payments`; filter bills by proof queue/status/kind/category.
5. Issue or open a bill, upload proof from a member account, approve a partial amount, then approve a second proof to complete it.
6. Confirm paid/remaining amounts and Transaction History.
7. Open a session attendance sheet and generate a QR window inside the session start/end time.
8. Try an invalid QR window and confirm inline field errors.
9. Check CSV export/template/import from the admin surfaces.
10. Open Championships and confirm registration/payment context is understandable.

### Parent

1. Log in as `parent@rfis.test`.
2. Confirm only the linked child is visible.
3. View bill/invoice status, paid amount, remaining amount, and approved installments.
4. Upload payment proof for an open bill when available.

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

This repository includes a production-oriented `Dockerfile` that builds PHP dependencies and Vite assets, then serves Laravel through Apache. No Docker Compose file is currently included in the repository. If you deploy with Docker, provide runtime environment variables externally and run database migrations/seeding as a separate release step.

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
php artisan migrate:fresh --seed --env=testing
php artisan test
npm run lint
npm run build
git status --short --branch
```

`npm run test` and `npm run typecheck` are not defined in `package.json` at this time.
