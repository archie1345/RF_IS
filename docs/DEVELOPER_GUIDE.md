# RF_IS Developer Guide

This guide describes the current Laravel 12, Vue 3, Inertia, and Vite application. Development and production run as separate Compose projects.

## Request and data flow

Typical Inertia workflow:

`routes/web.php` → controller → Form Request and authorization → action/service → Eloquent model → presenter/page props → Vue page in `resources/js/pages`.

Controllers coordinate requests and responses. Form Requests validate inputs. Actions own state changes and transactions. Services centralize reusable authorization, visibility, or business rules. Presenters define frontend row contracts. Policies and middleware enforce access on the server.

## Main modules

| Workflow | Backend entry points | Frontend entry points |
| --- | --- | --- |
| Authentication and invitations | Fortify provider/actions; `routes/auth.php`; account lifecycle controllers | `resources/js/pages/auth` |
| Dashboard and navigation | `DashboardController`; `HandleInertiaRequests` | `Dashboard.vue`; `AppHeader.vue`; `AppSidebar.vue` |
| People, profiles, and accounts | `UserDirectoryController`; `ProfileAccessController`; `Settings/ProfileController`; profile requests/actions | `AthletesPage.vue`; `profiles/`; settings pages |
| Training schedules and sessions | `WeeklyScheduleController`; `SessionController`; `GenerateWeeklyTrainingSessions` | `WeeklySchedulePage.vue`; `SessionsPage.vue`; session components |
| Attendance and QR | `AttendanceController`; `AttendanceScanController`; `SessionAttendanceQrController`; `AttendanceVisibilityService`; `SessionEligibilityService` | `AttendancePage.vue`; `AttendanceScanPage.vue`; `SessionAttendancePage.vue` |
| Billing, payments, and payroll | `PaymentController`; `BillingSettingsController`; `PaymentVisibilityService`; `BillingInvoiceGenerator`; admin feature controllers | `PaymentsPage.vue`; billing/admin feature pages |
| Championships and results | `ChampionshipController`; championship page/export/payment controllers | `ChampionshipsPage.vue`; `ChampionshipDetailPage.vue`; `EventHistoryPage.vue` |
| Files and privacy | `UserFileController`; `PaymentProofFileController`; `UserFile` and `Payment` storage helpers | profile, achievement, payment, and file-upload components |
| Announcements and activity | `AnnouncementController`; `ActivityLogController` | `AnnouncementsPage.vue`; activity-log admin page |

API routes are in `routes/api.php`. Settings and profile routes are in `routes/settings.php`. Console commands and scheduled tasks are in `routes/console.php`.

## Roles and authorization

The supported roles are `admin`, `coach`, and `athlete`. A user may have multiple assignments. `RoleResolver` is the source of truth; authorization checks use the union of assigned roles. `auth.user.role` is a presentation default, not an active permission context. Attendance and payments accept a stateless `?mode=` only for pages that explicitly support role-specific views. See [Roles and View Modes](ACTIVE_ROLE_CONTEXT.md).

Public registration is disabled. Administrators create accounts and send invitations. Fortify provides login, password reset, email verification, and two-factor authentication. Account-active, verified, and role middleware protect the authenticated workflows.

Guardian name, phone, and relation are fields on the athlete record. The parent portal and parent login role are not current features.

## Data and infrastructure

- Development uses MySQL through `compose.dev.yaml`, with data on the named `db_data` volume. Do not remove that volume as part of routine development or testing.
- Tests use PHPUnit/Pest with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` forced by `phpunit.xml`. The Compose test command uses `--no-deps` and overrides the normal server startup command.
- The development server does not apply migrations during startup. Review pending migrations and verify a backup before applying them explicitly.
- Production has a separate static configuration in `compose.prod.yaml`; this guide does not start or validate a deployed environment.
- Production includes queue-worker and scheduler services. The development stack includes a scheduler but no queue worker; there are currently no application job classes under `app/Jobs`.

Scheduled commands are declared in `routes/console.php`: activity-log pruning, monthly tuition generation, and generating sessions from weekly schedules. Inspect these commands and their effects before changing cadence or running them manually.

## Files and uploads

Private user files and payment proofs use private storage disks and authorized download controllers. Do not expose a private disk through a public symlink. Upload validation belongs in Form Requests; disk/path ownership checks belong in controllers or domain services. Tests that exercise file writes should use `Storage::fake()`.

## Frontend conventions

- Inertia pages live in `resources/js/pages`.
- Shared app components live in `resources/js/components`; low-level primitives are under `components/ui`.
- Reusable Vue state and behavior belong in `resources/js/composables`.
- Shared browser/backend contracts belong in `resources/js/types`.
- Wayfinder route/action files under `resources/js/routes` and `resources/js/actions` are generated and ignored; do not edit them by hand.
- Vite is configured in `vite.config.ts`. Its watcher polls for WSL2 bind-mount updates while ignoring backend-only directories.

## Verification

Backend tests run through a networkless Compose test service:

```bash
composer test
```

Run the visible browser suite after the development app is available:

```bash
npm ci --prefix e2e
npm --prefix e2e run install:browsers
npm run test:e2e
```

The Playwright suite is isolated under `e2e/`; it builds the app into a dedicated Docker bridge with an in-memory SQLite database and array sessions, separate from the development MySQL network. It stores diagnostic artifacts under ignored `e2e/test-results/` and `e2e/playwright-report/` directories. See [Docker and test workflow](../README.Docker.md) for exact commands and artifact behavior.

Other checks include `composer run test:lint`, `npm run format:check`, `npm run lint:check`, `npm run typecheck`, and `npm run build`. `npm run build` invokes PHP Wayfinder generation, so run it in an environment with the application PHP dependencies and required PDO extensions installed.

## Data recovery

Database backup and restore commands are deployment-specific and are not documented here. Before applying schema changes or running data imports, take a verified backup and document the tested restore path for the target environment.
