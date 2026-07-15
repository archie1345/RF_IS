# RF_IS Developer Guide

This guide documents the current Laravel + Vue 3 + TypeScript + Inertia architecture after the role, parent-child, visibility, action, presenter, and naming refactors. It is based on the files currently in this repository and is intended to help future contributors make targeted changes without guessing where behavior lives.

## 1. Architecture overview

RF_IS is organized around thin HTTP controllers, feature-specific request validation, policy/visibility authorization, transactional actions for writes, presenters for Inertia row payloads, Eloquent models for persistence, and Vue pages/components for rendering.

Typical request flow:

`routes/web.php` → controller in `app/Http/Controllers` → Form Request in `app/Http/Requests` → Policy in `app/Policies` or visibility service in `app/Services` → Action in `app/Actions` for writes → Eloquent model in `app/Models` → Presenter in `app/Presenters` for row data → Inertia page in `resources/js/pages`.

Core cross-cutting rules:

- Role resolution is centralized in `app/Support/RoleResolver.php` and accessed through `App\Models\User` methods.
- Parent-child context is centralized in `app/Services/ParentChildContextService.php` and shared to Inertia by `app/Http/Middleware/HandleInertiaRequests.php`.
- Attendance, payment, and session visibility are centralized in `app/Services/AttendanceVisibilityService.php`, `app/Services/PaymentVisibilityService.php`, and `app/Services/SessionVisibilityService.php`.
- Status labels and tones are centralized in `app/Support/Domain/AttendanceStatus.php`, `app/Support/Domain/PaymentStatus.php`, and `app/Support/Domain/SessionStatus.php`.
- Frontend internal URLs are centralized in `resources/js/data/routes.ts`.

## 2. Directory responsibility map

### `app/Actions`

Belongs here: single-purpose write operations that perform state changes, transactions, file handling, or multi-model mutations.

Examples:

- `app/Actions/Attendance/CreateAttendanceRecord.php`
- `app/Actions/Attendance/UpdateAttendanceStatus.php`
- `app/Actions/Attendance/BulkUpdateAttendanceStatus.php`
- `app/Actions/Payments/CreatePayment.php`
- `app/Actions/Payments/SubmitPaymentProof.php`
- `app/Actions/Payments/ReviewPaymentProof.php`
- `app/Actions/Sessions/CreateSession.php`
- `app/Actions/ParentChild/SwitchActiveChild.php`
- `app/Actions/Profiles/UpdateParentProfile.php`

Do not put redirects, Inertia responses, or request validation here. Add a new Action when a write operation has business rules, multiple writes, transactions, or reuse value.

### `app/Http/Controllers`

Belongs here: request orchestration only. Controllers should receive validated input, authorize, call services/actions/presenters, and return redirects or Inertia responses.

Examples:

- `AttendanceController.php`
- `PaymentController.php`
- `SessionController.php`
- `DashboardController.php`
- `ParentChildContextController.php`
- `UserDirectoryController.php`
- `ProfileAccessController.php`
- `ChampionshipController.php`
- `AnnouncementController.php`
- `UserAchievementController.php`

Do not put large validation arrays, cross-feature business rules, or reusable visibility logic here.

### `app/Http/Requests`

Belongs here: validation and simple request-level authorization.

Examples:

- `Attendance/StoreAttendanceRequest.php`
- `Attendance/UpdateAttendanceRequest.php`
- `Payments/SubmitPaymentProofRequest.php`
- `Payments/ReviewPaymentProofRequest.php`
- `Sessions/StoreSessionRequest.php`
- `ParentChild/SwitchActiveChildRequest.php`
- `Profiles/UpdateParentProfileRequest.php`

Do not perform writes in Form Requests. Prefer feature subdirectories.

### `app/Policies`

Belongs here: model-level authorization decisions.

Examples:

- `AttendancePolicy.php`
- `PaymentPolicy.php`
- `SessionPolicy.php`

Policies may delegate to visibility services. Do not duplicate complex visibility queries in multiple controllers.

### `app/Services`

Belongs here: read scoping, context resolution, and visibility rules that are reused across controllers/policies.

Examples:

- `ParentChildContextService.php`
- `AttendanceVisibilityService.php`
- `PaymentVisibilityService.php`
- `SessionVisibilityService.php`

Do not return HTTP responses from services.

### `app/Presenters`

Belongs here: mapping Eloquent models and relationships into frontend row contracts.

Examples:

- `AttendanceRowPresenter.php`
- `PaymentRowPresenter.php`
- `SessionRowPresenter.php`
- `Concerns/FormatsPresenterData.php`

Change presenter output only with matching TypeScript updates.

### `app/Support`

Belongs here: cross-cutting support classes and fixed domain definitions.

Examples:

- `RoleResolver.php`
- `ActivityLogger.php`
- `Domain/AttendanceStatus.php`
- `Domain/PaymentStatus.php`
- `Domain/SessionStatus.php`
- `Profile/ProfilePageData.php`
- `Profile/ProfileFormRules.php`
- `Profile/ProfileMedia.php`

### `app/Models`

Belongs here: Eloquent table mapping, keys, casts, fillable fields, relationships, and small model-local domain helpers.

Important models:

- `User.php`: numeric `id`, role helpers, profile relationships.
- `ParentProfile.php`: table `parents`, string ULID primary key `parent_id`.
- `Athlete.php`: string ULID primary key `athlete_id`.
- `Coach.php`: string ULID primary key `coach_id`.
- `Attendance.php`: attendance records.
- `Payment.php`: bill/payment rows and relation to `PaymentTransaction`.
- `PaymentTransaction.php`: table `payment_transactions`, numeric `ptid`.
- `Session.php`: coach sessions.

Do not add session/request/Inertia behavior to models.

### `resources/js/pages`

Belongs here: Inertia pages. Pages coordinate props, forms, and feature components.

Examples:

- `Dashboard.vue`
- `AttendancePage.vue`
- `PaymentsPage.vue`
- `SessionsPage.vue`
- `SessionAttendancePage.vue`
- `AthletesPage.vue`
- `AdminPage.vue`
- `ChampionshipsPage.vue`
- `AnnouncementsPage.vue`
- `profiles/ProfileDetailsPage.vue`

Do not put reusable business rules or backend authorization assumptions only in Vue pages.

### `resources/js/components/shared` and `resources/js/components/ui`

`shared` contains reusable app-level components such as `DataTable.vue`, `ResourceTablePanel.vue`, `PageSection.vue`, `StatusBadge.vue`, and `FormModal.vue`.

`ui` contains lower-level UI primitives such as buttons, dialogs, alerts, inputs, labels, and sidebar primitives.

### `resources/js/components/admin`, `dashboard`, and feature components

Admin-specific UI lives in `resources/js/components/admin`, including `AdminUserAccountPanel.vue`, `BranchAdministrationPanel.vue`, and `GroupAdministrationPanel.vue`.

Dashboard sections live in `resources/js/components/dashboard`, including `DashboardHeroSection.vue`, `DashboardOverviewSections.vue`, and `ParentSettingsCard.vue`.

### `resources/js/composables`

Belongs here: reusable Vue composition logic.

Examples:

- `useActiveChild.ts`
- `useRole.ts`
- `useLiveReload.ts`
- `useProfilePictureCropper.ts`

### `resources/js/types`

Belongs here: TypeScript contracts.

Examples:

- `resource-table.ts`: shared table, role, metric, select, and status-tone types.
- `domain.ts`: domain rows such as attendance/payment/session/parent-child types.
- `auth.ts`, `profile.ts`, `dashboard.ts`, `navigation.ts`, `ui.ts`.

### `resources/js/data`

Belongs here: static frontend config and route maps.

Examples:

- `routes.ts`: internal URL constants.
- `dashboard.ts`: dashboard table column definitions.

### `routes`

`routes/web.php` contains authenticated Inertia routes and feature route groups. `routes/settings.php` contains settings/profile routes. `routes/api.php` contains API resource endpoints.

### `database/migrations` and `database/seeders`

Migrations define identifier types and table semantics. Do not rename tables/columns casually. `database/seeders/ApplicationDataSeeder.php` creates representative users, role assignments, parent/athlete links, attendance, payments, sessions, championships, announcements, and activity data.

### `tests`

Feature tests live in `tests/Feature`. Current high-value tests include:

- `ParentChildContextTest.php`
- `ParentChildProfileAccessTest.php`
- `AttendancePaymentAchievementFixTest.php`
- `PaymentAndAnnouncementFlowTest.php`
- `RoleResolutionTest.php`
- `ChampionshipManagementTest.php`
- `AdminAccountSecurityTest.php`

## 3. Feature ownership map

### Feature: Authentication and roles

Purpose: Authenticate users and determine role behavior for admin, coach, parent, and athlete.

Backend entry point: Fortify controllers/providers, `app/Support/RoleResolver.php`, `app/Models/User.php`.

Read flow: `User::assignedRoles()`, `primaryRole()`, and `hasRole()` delegate to `RoleResolver`.

Write flow: role assignments are stored in `user_role_assignments`; `users.role` remains fallback.

Frontend entry point: `resources/js/composables/useRole.ts`, `resources/js/components/AppSidebar.vue`, `resources/js/components/AppHeader.vue`.

Authorization: policies and controllers should call user helper methods rather than reading `users.role` directly.

Database: `users.id` is numeric; `user_role_assignments.user_id` references `users.id`.

Where to modify: role priority in `RoleResolver`; sidebar visibility in `AppSidebar.vue`; role types in `resources/js/types/resource-table.ts` and `resources/js/types/domain.ts`.

Tests: `tests/Feature/RoleResolutionTest.php`.

### Feature: Parent-child context

Purpose: Load linked children, resolve active child, switch or clear `active_child_id`, and scope parent-visible records.

Backend entry point: `routes/web.php` parent children routes, `ParentChildContextController.php`, `ParentChildContextService.php`.

Read flow: service retrieves children and active child; middleware shares `auth.children` and `auth.activeChild`.

Write flow: `SwitchActiveChildRequest` validates, `SwitchActiveChild` stores `active_child_id`, `ClearActiveChild` clears it.

Frontend entry point: `ParentChildSwitcherPage.vue`, `useActiveChild.ts`, header/sidebar child selector usage.

Authorization: parents may select only linked children.

Database: `parents.parent_id` and `athletes.parent_id` are ULID strings; `users.id` remains numeric.

Where to modify: parent filtering in `ParentChildContextService`; route handling in `ParentChildContextController`; frontend URL in `resources/js/data/routes.ts`.

Tests: `ParentChildContextTest.php`.

### Feature: Dashboard

Purpose: Show role-specific metrics, activity, attendance, payments, achievements, announcements, and parent context.

Backend entry point: `DashboardController.php` and `routes/web.php` dashboard route.

Read flow: controller gathers role-scoped data and presenter rows for Inertia.

Write flow: no dashboard-specific writes; child switching uses parent-child routes.

Frontend entry point: `Dashboard.vue`, `DashboardHeroSection.vue`, `DashboardOverviewSections.vue`, `ParentSettingsCard.vue`.

Types and contracts: metrics and rows use `resources/js/types/resource-table.ts`; domain rows use `resources/js/types/domain.ts`.

Authorization: backend role checks use `User` role helpers and visibility services.

Where to modify: metrics in `DashboardController`; dashboard columns in `resources/js/data/dashboard.ts`; UI panels in dashboard components.

Tests: `DashboardTest.php` plus parent-child/visibility tests.

### Feature: Attendance

Purpose: Record, display, and update athlete attendance with role-based visibility.

Backend entry point: `AttendanceController.php`, routes under `/attendance` in `routes/web.php`.

Read flow: `AttendanceVisibilityService` scopes query; `AttendanceRowPresenter` maps rows.

Write flow: `StoreAttendanceRequest`, `UpdateAttendanceRequest`, `BulkUpdateAttendanceRequest`; `AttendancePolicy`; attendance Actions update records.

Frontend entry point: `AttendancePage.vue`, `SessionAttendancePage.vue`.

Types and contracts: `AttendanceRow` in `resources/js/types/domain.ts`; shared table types in `resource-table.ts`.

Authorization: admins manage permitted records; coaches are scoped by session access; parents see linked children; athletes see self.

Database: attendance references string `athlete_id`; session IDs follow schema.

Where to modify: visibility in `AttendanceVisibilityService`; validation in attendance requests; write behavior in attendance actions; row output in `AttendanceRowPresenter`; labels in `AttendanceStatus`.

Tests: `ParentChildContextTest.php`, `AttendancePaymentAchievementFixTest.php`.

### Feature: Payments

Purpose: Manage bills, payroll-style rows, proof upload/review, payment status, invoices, and history.

Backend entry point: `PaymentController.php`, routes under `/payments`, `Admin/InvoiceTemplateController.php` for invoice settings.

Read flow: `PaymentVisibilityService` scopes query; `PaymentRowPresenter` maps rows and history.

Write flow: payment Form Requests and Actions create/update payments, submit proof, review proof, and update status.

Frontend entry point: `PaymentsPage.vue`.

Types and contracts: payment row contracts in `resources/js/types/domain.ts`; route helpers in `resources/js/data/routes.ts`.

Authorization: `PaymentPolicy` delegates visibility decisions; parents/athletes can submit proof for visible payments; admins review.

Database: `payments.payment_id` numeric; `payment_transactions.ptid` numeric; `athlete_id` remains ULID string.

Where to modify: visibility in `PaymentVisibilityService`; proof rules in `SubmitPaymentProofRequest`; transaction behavior in payment Actions; row output in `PaymentRowPresenter`; labels in `PaymentStatus`.

Tests: `PaymentAndAnnouncementFlowTest.php`, `AttendancePaymentAchievementFixTest.php`.

### Feature: Sessions and session attendance

Purpose: Create sessions, join sessions, manage coach session attendance, and view session attendance sheets.

Backend entry point: `SessionController.php`, routes under `/sessions`.

Read flow: `SessionVisibilityService` scopes session access; `SessionRowPresenter` maps rows.

Write flow: `StoreSessionRequest`, `UpdateSessionRequest`, `CreateSession`, `UpdateSession`; coach attendance methods remain in `SessionController`.

Frontend entry point: `SessionsPage.vue`, `SessionAttendancePage.vue`.

Authorization: `SessionPolicy` and visibility service scope coach/admin/athlete access.

Database: `sessions.training_session_id` is numeric by schema; `coach_id` is a ULID string; session pivot tables preserve their schema types.

Where to modify: session visibility in `SessionVisibilityService`; creation/update validation in session requests; row output in `SessionRowPresenter`; labels in `SessionStatus`.

Tests: session coverage is partial; add tests before changing join/coach-attendance behavior.

### Feature: Users and profiles

Purpose: List users, view profiles, update account details, update role-specific profiles, manage certifications/achievements, and manage parent-child assignments.

Backend entry point: `ProfileAccessController.php`, `ParentChildProfileController.php`, `UserDirectoryController.php`, and `app/Http/Controllers/Profiles/*`.

Read flow: profile controllers build `ProfileDetailsPage` props using profile support classes and model relationships.

Write flow: profile Form Requests and Actions update account/profile/athlete/coach/parent/certification/achievement data.

Frontend entry point: `AthletesPage.vue`, `profiles/ProfileDetailsPage.vue`, profile components.

Authorization: profile concerns and policy-style checks ensure users can only edit permitted profiles.

Database: user `id` is numeric; role profile IDs use their migrated schema (`athlete_id`, `coach_id`, `parent_id` are strings).

Where to modify: profile data construction in `app/Support/Profile`; role-profile validation in `app/Http/Requests/Profiles`; profile UI sections in `resources/js/pages/profiles/components`.

Tests: `ParentChildProfileAccessTest.php`, `AdminAccountSecurityTest.php`.

### Feature: Championships

Purpose: List events, view details, register athletes/coaches, record results, and settle linked payments.

Backend entry point: `ChampionshipController.php`, routes under `/championships`.

Frontend entry point: `ChampionshipsPage.vue`, `ChampionshipDetailPage.vue`.

Database: `events`, `event_registrations`, `event_coach_registrations`, linked `payments`.

Where to modify: event/register/result behavior in `ChampionshipController`; UI in championship pages; route helpers in `resources/js/data/routes.ts`.

Tests: `ChampionshipManagementTest.php`.

### Feature: Achievements

Purpose: Show and create achievements for users/athletes/coaches and integrate with profile pages.

Backend entry point: `UserAchievementController.php` and profile achievement controller.

Frontend entry point: `AchievementsPage.vue`, `ProfileAchievementsSection.vue`.

Where to modify: achievement rows in profile table utilities; creation/update validation in profile requests; UI in achievements components.

Tests: `AttendancePaymentAchievementFixTest.php` and profile access tests.

### Feature: Announcements

Purpose: Show role-filtered announcements and allow admins to create messages.

Backend entry point: `AnnouncementController.php`, routes under `/announcements`.

Frontend entry point: `AnnouncementsPage.vue`, dashboard announcement rows.

Where to modify: announcement visibility in `AnnouncementController`; UI in `AnnouncementsPage.vue`.

Tests: `PaymentAndAnnouncementFlowTest.php`.

## 4. Change-location index

| I want to change... | Primary files | Supporting files | Risks | Tests/checks |
| --- | --- | --- | --- | --- |
| Role priority | `app/Support/RoleResolver.php` | `app/Models/User.php`, `tests/Feature/RoleResolutionTest.php` | Backend/frontend role mismatch | `php artisan test --filter=RoleResolutionTest` |
| Add a role | `RoleResolver.php`, `resources/js/types/resource-table.ts` | policies, sidebar/header, seeders | Missing authorization branches | role tests, manual navigation |
| Parent active-child behavior | `ParentChildContextService.php` | `SwitchActiveChild.php`, `ClearActiveChild.php`, middleware | Breaking `active_child_id` | `ParentChildContextTest` |
| Which child records a parent sees | `ParentChildContextService.php` | attendance/payment visibility services | Data leak across children | parent-child feature tests |
| Attendance status | `AttendanceStatus.php` | attendance requests, presenters, Vue types | DB/status mismatch | attendance tests, `vue-tsc` |
| Attendance row output | `AttendanceRowPresenter.php` | `resources/js/types/domain.ts`, `AttendancePage.vue` | Inertia contract break | `vue-tsc`, attendance page manual test |
| Attendance calendar UI | `AttendancePage.vue` or future attendance components | `AttendanceRowPresenter.php` | Putting authorization in UI | frontend build/type checks |
| Payment visibility | `PaymentVisibilityService.php` | `PaymentPolicy.php`, `PaymentController.php` | Parent/athlete payment data leak | payment feature tests |
| Payment proof validation | `SubmitPaymentProofRequest.php` | `SubmitPaymentProof.php`, `PaymentPolicy.php` | Unsafe files, unauthorized proof | payment proof tests |
| Invoice output | `PaymentController::exportInvoice` | `InvoiceTemplate`, `PaymentsPage.vue` | PDF/render changes | manual invoice export |
| Session creation behavior | `CreateSession.php` | `StoreSessionRequest.php`, `SessionController.php` | bad session dates/coach IDs | session tests/manual create |
| Coach session access | `SessionVisibilityService.php` | `SessionPolicy.php` | coach sees wrong sessions | coach manual tests |
| Dashboard metrics | `DashboardController.php` | `DashboardOverviewSections.vue`, `resources/js/data/dashboard.ts` | missing props | dashboard manual test |
| Add dashboard panel | dashboard Vue components | `DashboardController.php` | role-specific missing data | `vue-tsc` |
| Sidebar by role | `AppSidebar.vue` | `useRole.ts`, `resource-table.ts` | frontend-only auth assumptions | manual role navigation |
| Header child selector | `AppHeader.vue` | `useActiveChild.ts`, parent routes | wrong active child | parent manual test |
| New Inertia prop | controller/middleware | page `defineProps`, TS types | contract drift | `vue-tsc` |
| New route | `routes/web.php` | controller, `resources/js/data/routes.ts` | URL/name conflicts | `route:list` when vendor exists |
| New DB field | migration | model fillable/casts, requests, presenters, tests | schema/model drift | migration + feature tests |
| New model relationship | model | presenters/services/tests | N+1 or wrong key | targeted feature tests |
| New Form Request | `app/Http/Requests/<Feature>` | controller method signature | write path unvalidated | request/feature test |
| New Policy | `app/Policies` | controller `$this->authorize`, tests | bypassed checks | authorization tests |
| New Action | `app/Actions/<Feature>` | controller and tests | returning HTTP response from action | feature/unit test |
| New status | `app/Support/Domain` | requests, presenters, TS types | repeated magic strings | `vue-tsc`, feature tests |
| Test data | `ApplicationDataSeeder.php`, tests | model factories | invalid relationships | seeder smoke test |
| New frontend route string | `resources/js/data/routes.ts` | Vue consumers | stale hard-coded URL | `rg` + manual click |
| Frontend domain type | `resources/js/types/domain.ts` | presenters/pages | TS contract mismatch | `npx vue-tsc --noEmit` |
| User-facing status labels | status definition classes | presenters and shared badges | inconsistent tones | UI manual test |
| Activity logging | `ActivityLogger.php`, relevant Actions/controllers | tests | logging secrets | security review |

## 5. Request lifecycle examples

### Parent switches active child

`POST /parent/children/switch` → `auth`/`verified` middleware → `ParentChildContextController::switch` → `SwitchActiveChildRequest` → `SwitchActiveChild` action → `ParentChildContextService::ensureChildBelongsToParent` and session key `active_child_id` → redirect/back → `HandleInertiaRequests` refreshes `auth.children` and `auth.activeChild` → `AppHeader`/parent pages use shared props.

### Parent views attendance

`GET /attendance` → `AttendanceController::index` → `AttendanceVisibilityService` and `ParentChildContextService` determine linked/active child scope → `Attendance` model query with relationships → `AttendanceRowPresenter` outputs rows with raw `athlete_id`, date/status/session/coach fields → Inertia `AttendancePage.vue` → shared `DataTable.vue`/page controls.

### Parent submits payment proof

`POST /payments/{payment}/proof` → `PaymentController::submitProof` → `SubmitPaymentProofRequest` validates file → `PaymentPolicy::submitProof` delegates visibility → `SubmitPaymentProof` action stores proof safely → `Payment` model updated → redirect to payments index → `PaymentVisibilityService` + `PaymentRowPresenter` refresh rows → `PaymentsPage.vue`.

### Coach updates attendance

`PUT /attendance/{attendance}` or session attendance route actions → `AttendanceController::update` → `UpdateAttendanceRequest` → `AttendancePolicy::update` and `AttendanceVisibilityService` check allowed session/record → `UpdateAttendanceStatus` action → `Attendance` model update → redirect back/index → `AttendanceRowPresenter`/`SessionAttendancePage.vue` refresh.

### Admin creates or updates a session

`POST /sessions` or `PUT /sessions/{session}` → `SessionController::store/update` → `StoreSessionRequest` or `UpdateSessionRequest` → `SessionPolicy` → `CreateSession` or `UpdateSession` action → `Session` model and related coach/attendance data as needed → redirect to sessions index → `SessionVisibilityService` + `SessionRowPresenter` → `SessionsPage.vue`.

## 6. Role and authorization overview

- `user_role_assignments` is the primary role source.
- `users.role` is a compatibility fallback.
- `User::assignedRoles()`, `primaryRole()`, `hasRole()`, `isAdmin()`, `isCoach()`, `isParent()`, and `isAthlete()` are the backend role entry points.
- Frontend role display/navigation uses `useRole.ts` and `AppSidebar.vue`; it is not a substitute for backend authorization.
- Attendance, payment, and session policies delegate to visibility services where needed.

## 7. Identifier and ULID rules

- `users.id` and user-related `user_id` columns are numeric.
- `athletes.athlete_id`, `parents.parent_id`, and `coaches.coach_id` are string ULIDs.
- `payments.payment_id`, `payment_transactions.ptid`, sessions, events, registrations, and other IDs follow their migrations.
- Never use `(int)`, `intval()`, `Number()`, or `parseInt()` on ULID-backed identifiers.
- Numeric casts found in user/profile checks and payment/session IDs should remain tied to numeric schema fields.

## 8. Inertia contract overview

Shared props from `HandleInertiaRequests` include:

- `auth.user`
- `auth.children`
- `auth.activeChild`

Feature controllers provide page-specific props. When changing a prop:

1. update the controller/presenter;
2. update the page `defineProps`;
3. update `resources/js/types`;
4. run `npx vue-tsc --noEmit` after generated Wayfinder files are available.

## 9. Status-definition overview

- Attendance statuses: `app/Support/Domain/AttendanceStatus.php`.
- Payment statuses and proof tones: `app/Support/Domain/PaymentStatus.php`.
- Session statuses: `app/Support/Domain/SessionStatus.php`.

Do not add new magic status strings in controllers or Vue pages without updating these definitions and matching TS types.

## 10. Testing guide

Suggested targeted tests:

- Roles: `php artisan test --filter=RoleResolutionTest`
- Parent-child: `php artisan test --filter=ParentChildContextTest`
- Profile access: `php artisan test --filter=ParentChildProfileAccessTest`
- Payments/announcements: `php artisan test --filter=PaymentAndAnnouncementFlowTest`
- Attendance/payment/achievement regression: `php artisan test --filter=AttendancePaymentAchievementFixTest`
- Championships: `php artisan test --filter=ChampionshipManagementTest`

Full checks when dependencies are installed:

```bash
composer validate --no-check-publish
php artisan optimize:clear
php artisan route:list
php artisan test
npm run lint
npx vue-tsc --noEmit
npm run build
```

In this workspace, `vendor/autoload.php` is absent, so Laravel runtime commands and Wayfinder-backed builds are blocked until Composer dependencies are installed.

## 11. Common modification recipes

### Add a field to a profile

1. Add a migration.
2. Update the corresponding model fillable/casts.
3. Update `app/Http/Requests/Profiles/*` validation.
4. Update profile Action.
5. Update `ProfileDetailsPage.vue` or a profile component.
6. Update `resources/js/pages/profiles/types.ts`.
7. Add/adjust feature tests.

### Add a payment status

1. Update `PaymentStatus.php`.
2. Update payment Form Requests if user input accepts it.
3. Update `PaymentRowPresenter.php`.
4. Update frontend TS status unions and UI labels.
5. Add payment tests.

### Add an attendance row column

1. Add raw/presented value in `AttendanceRowPresenter.php`.
2. Update `resources/js/types/domain.ts`.
3. Update `AttendancePage.vue` or `SessionAttendancePage.vue` columns.
4. Run `vue-tsc`.

### Add a route

1. Add route in `routes/web.php` without changing existing public URLs.
2. Add or reuse a controller method.
3. Add a route constant in `resources/js/data/routes.ts` if used in Vue.
4. Add authorization and validation.
5. Add a route/feature test.

## 12. Common mistakes to avoid

- Adding authorization only in Vue.
- Adding validation arrays directly in controllers.
- Casting ULIDs with numeric casts.
- Changing Presenter output without updating TypeScript types.
- Changing public URLs to improve naming.
- Returning redirects or Inertia responses from Actions.
- Querying large unrelated datasets from Form Requests.
- Adding status labels in multiple places.
- Changing database columns without updating models, requests, presenters, tests, and seeders.

## 13. Manual verification checklist

### Admin

- Log in as admin.
- Open dashboard and confirm admin metrics/activity load.
- Open users and view/edit account and role profile sections.
- Create/update payment, upload/review proof, export invoice.
- Open attendance and update allowed records.
- Create/update sessions and view session attendance.
- Open championships and record result/payment settlement.
- Create announcement.
- Open activity logs.

### Coach

- Log in as coach.
- Confirm dashboard role panels.
- Confirm assigned sessions only.
- Open session attendance and update allowed attendance.
- Confirm achievements and announcements pages render.

### Parent

- Log in as parent.
- Confirm child selector appears for linked children.
- Switch active child and navigate away/back.
- Confirm dashboard, attendance, and payments filter by selected child.
- Attempt unrelated child switch URL and confirm rejection.
- Confirm championships, achievements, and announcements are visible as intended.

### Athlete

- Log in as athlete.
- Confirm dashboard loads.
- Confirm only self attendance/payments are visible.
- Confirm sessions/championships/achievements/announcements render.

## 14. Current verification notes

- PHP syntax checks pass for `app`, `routes`, `database`, and `tests`.
- `composer validate` passes with warnings about unbound dependency constraints.
- `php artisan optimize:clear`, `php artisan route:list --except-vendor`, `php artisan migrate --force`, and `php artisan test` pass after installing dependencies and generating Wayfinder files.
- `npm run lint`, `npx vue-tsc --noEmit`, and `npm run build` pass after `php artisan wayfinder:generate --with-form`.
- `vendor/bin/pint --test` is still a style-baseline warning because many pre-existing files need formatting; touched PHP files were formatted with Pint.

## Runtime Setup and Verification

This section records the runtime bootstrap and verification workflow that was exercised in the Codex workspace on 2026-06-30. It supplements the architecture notes above with the concrete commands needed to make a fresh checkout bootable before starting feature work.

### Runtime versions

- PHP: `composer.json` requires `^8.2`. The verification container used `PHP 8.5.7-dev`; this is newer than the locked `phpoffice/phpspreadsheet` platform constraint (`<8.5.0`), so Composer required the environment-only flag `--ignore-platform-req=php` in this container.
- Laravel: `php artisan --version` reported Laravel Framework `12.58.0` after dependencies were installed.
- Node/npm: the verification container used Node `v24.15.0` and npm `11.4.2`.
- Frontend build: Vite is configured with the Laravel plugin, Vue plugin, Tailwind plugin, and Wayfinder plugin. Wayfinder generates route/action modules used by TypeScript imports.

### Composer setup

Preferred command for normal local environments:

```bash
composer install --no-interaction --prefer-dist
```

If the local PHP version is newer than a locked package allows, Composer may fail before installing `vendor/autoload.php`. In this verification container only, the safe workaround was:

```bash
composer install --no-interaction --prefer-dist --ignore-platform-req=php
```

Do not run `composer update` just to make the environment install. Update dependency constraints only as a deliberate dependency-maintenance task.

### npm setup

Use the lockfile-preserving install command:

```bash
npm install
```

`npm install` may report audit findings. Do not run `npm audit fix` blindly because it can change dependency versions and should be handled as a separate dependency-maintenance task.

### `.env` setup

If `.env` is missing, create it from `.env.example` and generate an application key:

```bash
cp .env.example .env
php artisan key:generate --no-interaction
```

For isolated local verification, SQLite worked with these safe local-only settings:

```dotenv
APP_ENV=local
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Then create the database file:

```bash
touch database/database.sqlite
```

Do not overwrite a real `.env` containing developer-specific or deployment-specific values.

### Database setup

For a disposable local SQLite database:

```bash
php artisan migrate --force
```

The ULID conversion migration is written to skip MySQL-only foreign-key discovery when the connection driver is SQLite, so local SQLite migration verification can complete without querying `information_schema`.

Do not run `migrate:fresh` against an unknown or shared database. Use it only when the configured database is confirmed disposable.

### Wayfinder generation

Wayfinder-generated route and action modules are required for frontend typechecking and build. Generate them with:

```bash
php artisan wayfinder:generate --with-form
```

The generated directories are ignored by git in this repository (`resources/js/actions` and `resources/js/routes`), so they should be regenerated locally instead of hand-written or committed.

A common sign of stale or missing Wayfinder output is a TypeScript error for imports such as `@/routes`, `@/routes/dashboard`, or `@/actions/...`. Regenerate Wayfinder after route/controller changes and before `vue-tsc` or `vite build`.

### Verification commands

Run backend checks:

```bash
composer validate
php artisan optimize:clear
php artisan route:list --except-vendor
find app routes database tests -name "*.php" -print0 | xargs -0 -n1 php -l
php artisan test
```

Run frontend checks:

```bash
npm run lint
npx vue-tsc --noEmit
npm run build
```

Optional style check:

```bash
vendor/bin/pint --test
```

At the time of verification, the full Pint style check still reported pre-existing formatting issues across many files. Use Pint on touched files or address the full style baseline in a dedicated formatting pass.

### Common boot failures

- Missing `vendor/autoload.php`: run Composer install first. If Composer fails on PHP platform constraints, verify the local PHP version against `composer.lock` before changing dependency constraints.
- Missing Vite manifest during tests: feature tests should call Laravel's `withoutVite()` from the shared test base so Inertia page tests do not require a built `public/build/manifest.json`.
- Missing `@/routes` or `@/actions` imports: run `php artisan wayfinder:generate --with-form`.
- SQLite migration failure involving `information_schema`: check migrations for MySQL-only schema introspection and guard it by connection driver when SQLite is supported for tests/local verification.
- Duplicate or missing route names after route refactors: run `php artisan route:list --except-vendor` and verify route helpers with `route('...')` in tests or Tinker.

### Local development startup sequence

A fresh local setup should use this order:

```bash
composer install --no-interaction --prefer-dist
npm install
cp .env.example .env
php artisan key:generate --no-interaction
touch database/database.sqlite
php artisan migrate --force
php artisan wayfinder:generate --with-form
php artisan optimize:clear
npm run build
php artisan test
```

Adjust database settings before migration if using MySQL or another local database instead of SQLite.

### Verification status from this runtime pass

- Composer dependencies installed successfully in the Codex PHP 8.5 container with `--ignore-platform-req=php` because the lockfile contains a package constrained below PHP 8.5.
- Laravel booted, `php artisan about` completed, route listing completed, migrations completed on local SQLite, and all PHPUnit tests passed.
- Wayfinder generation completed and produced ignored local generated modules for frontend imports.
- Frontend lint, TypeScript checking, and production build completed successfully after Wayfinder generation.
- Confirmed fixes from this pass: canonical user route names were restored, local SQLite migration compatibility was fixed for the ULID conversion migration, shared tests now disable Vite asset lookup, and parent-child switch tests now post the same `athlete_id` payload used by the frontend.

## QR Attendance

### Purpose

QR attendance lets an authorized coach or admin open a time-boxed attendance window for a training session. The system generates a random scan token, stores only its SHA-256 hash on the `training_sessions` row, renders the plaintext token as a QR scan URL once, and lets authenticated athletes mark themselves `PRESENT` during the open window.

### Token lifecycle

- Generate/regenerate: `App\Actions\Attendance\GenerateSessionAttendanceQr` creates a 96-character random token through `App\Services\AttendanceQrTokenService`, stores `attendance_token_hash`, sets `attendance_opens_at`, `attendance_closes_at`, `attendance_qr_generated_at`, clears `attendance_qr_revoked_at`, and returns the plaintext token only in flash data for immediate QR rendering.
- Lookup: `AttendanceQrTokenService::findActiveSessionByToken()` hashes the scanned token and finds a session with the matching hash and no revocation timestamp.
- Revoke/close: `App\Actions\Attendance\RevokeSessionAttendanceQr` clears `attendance_token_hash` and sets `attendance_qr_revoked_at`, so old URLs stop resolving while existing attendance records remain intact.
- Record: `App\Actions\Attendance\RecordQrAttendance` validates the authenticated athlete, active token, time window, session status, branch/group eligibility, duplicate state, and attendance lock before writing `PRESENT` with `checked_in_at`.

### Database fields

The QR feature adds nullable fields to `training_sessions`:

- `attendance_token_hash` — unique SHA-256 token hash; plaintext tokens are not stored.
- `attendance_opens_at` — first valid scan time.
- `attendance_closes_at` — last valid scan time.
- `attendance_qr_generated_at` — current token generation time.
- `attendance_qr_revoked_at` — close/revocation time.

It also adds a unique constraint on `athlete_attendance(athlete_id, training_session_id)` to enforce one attendance row per athlete/session while preserving nullable general attendance rows.

### Routes and controllers

- `GET /attendance/scan/{token}` → `AttendanceScanController@show`, route name `attendance.scan.show`; displays the confirmation page and never records attendance.
- `POST /attendance/scan/{token}` → `AttendanceScanController@store`, route name `attendance.scan.store`; records attendance for authenticated athlete accounts only.
- `POST /sessions/{session}/attendance-qr` → `SessionAttendanceQrController@store`, route name `sessions.attendance-qr.store`; generate/regenerate for authorized coach/admin users.
- `DELETE /sessions/{session}/attendance-qr` → `SessionAttendanceQrController@destroy`, route name `sessions.attendance-qr.destroy`; closes/revokes the current QR token.

### Authorization and eligibility

- QR management uses `SessionPolicy::manageAttendanceQr()`, which delegates to existing session attendance management rules. Admins may manage permitted sessions; coaches may manage only sessions they can access.
- QR recording uses `RecordQrAttendanceRequest`; only authenticated users with an athlete role/profile may submit.
- Parents can view resulting attendance through the existing parent attendance page and active-child filtering, but cannot submit QR attendance for a child.
- Athlete eligibility requires the athlete branch to match the session branch. If the session has a group, the athlete group must match as well.
- Canceled sessions, revoked QR codes, scans before `attendance_opens_at`, and scans after `attendance_closes_at` are rejected.

### Attendance write behavior

QR check-in uses the existing `athlete_attendance` table. A valid scan creates or updates the athlete/session row to `PRESENT`, sets `date` from `training_sessions.session_date`, and records `checked_in_at`. Duplicate scans of an already-present row return idempotent success and do not create another row. Existing locked non-present rows are not overwritten.

### Frontend files

- `resources/js/pages/SessionAttendancePage.vue` renders the coach/admin attendance sheet and includes the QR panel.
- `resources/js/features/attendance/components/SessionAttendanceQrPanel.vue` handles open/close inputs, generate/regenerate, revoke, QR rendering, loading/error states, and copy URL.
- `resources/js/pages/AttendanceScanPage.vue` shows session details, athlete identity, current attendance status, errors, and the confirm button for scanned athletes.
- `resources/js/data/routes.ts` contains `sessionAttendanceQr()` and `attendanceScan()` helpers.

The QR panel uses the `qrcode` npm package to render the scan URL in the browser. Do not log or persist the plaintext token; it is intentionally available only immediately after generation.

### Where to modify QR behavior

- Change QR validity window defaults: `App\Actions\Attendance\GenerateSessionAttendanceQr`.
- Change QR token length or hashing: `App\Services\AttendanceQrTokenService`.
- Change coach/admin QR authorization: `App\Policies\SessionPolicy::manageAttendanceQr`.
- Change athlete eligibility rules: `App\Actions\Attendance\RecordQrAttendance::validateEligibility`.
- Change time-window or canceled-session rejection: `RecordQrAttendance::validateSession`.
- Change QR management UI: `SessionAttendanceQrPanel.vue`.
- Change scan confirmation UI: `AttendanceScanPage.vue`.
- Add rotating QR later: extend `AttendanceQrTokenService` and `GenerateSessionAttendanceQr`; preserve hashed-token storage and scan POST semantics.

### Tests

`tests/Feature/QrAttendanceTest.php` covers admin generation, coach generation, unrelated coach denial, unique hashed tokens, regeneration invalidation, revocation, unauthenticated scan redirect, parent denial, scan confirmation, successful athlete check-in, invalid/early/closed/canceled scans, branch/group eligibility, duplicate idempotency, locked attendance protection, parent read-only visibility, and unchanged existing route URLs.

### Manual test steps

1. Log in as admin or an assigned coach.
2. Open a session attendance sheet.
3. Set open/close times and generate a QR code.
4. Copy or scan the displayed URL with an athlete account from the same branch/group.
5. Confirm the scan page displays session and athlete details.
6. Submit attendance and verify the row becomes `PRESENT` with a check-in time.
7. Scan again and verify the idempotent “already recorded” result.
8. Regenerate the QR and verify the old URL no longer works.
9. Close the QR and verify the current URL no longer works.
10. Log in as parent and verify the active-child attendance page shows the resulting record but does not expose scan write behavior.

## Attendance domain naming

A training session is one scheduled training occurrence. The attendance domain uses explicit table, primary-key, and relationship names so it is clear which records belong to a scheduled training occurrence and which records belong to a user profile.

Text ER map:

```text
users
  ├── athletes
  └── coaches

training_sessions
  ├── athlete_attendance
  ├── training_session_coaches
  └── coach_attendance
```

Primary relationships:

- `training_sessions.training_session_id` is the primary key for a scheduled training occurrence.
- `athlete_attendance.athlete_attendance_id` is the primary key for an athlete attendance row.
- `athlete_attendance.training_session_id` references `training_sessions.training_session_id`, so athlete attendance belongs to one athlete and one training session when it is session-scoped.
- `training_session_coaches.training_session_coach_id` is the primary key for a coach assignment row.
- `training_session_coaches.training_session_id` references `training_sessions.training_session_id`, and `training_session_coaches.coach_id` references `coaches.coach_id`; these rows represent assigned coaches.
- `coach_attendance.coach_attendance_id` is the primary key for a coach attendance row.
- `coach_attendance.training_session_id` references `training_sessions.training_session_id`, so coach attendance belongs to one coach and one training session.
- QR token fields (`attendance_token_hash`, `attendance_opens_at`, `attendance_closes_at`, `attendance_qr_generated_at`, and `attendance_qr_revoked_at`) live on `training_sessions`, because QR tokens belong to a training session.

Runtime model names mirror the schema: use `TrainingSession`, `Attendance::trainingSession()`, `TrainingSession::primaryCoach()`, `TrainingSession::assignedCoaches()`, and `CoachAttendance::trainingSession()` in new backend code.
