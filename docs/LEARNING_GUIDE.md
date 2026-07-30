# Learning guide

## Start here

Read in this order: `routes/web.php`; the controller for one route; its FormRequest, Action/Service, Policy, and Presenter; the rendered file in `resources/js/pages`; then its feature/shared components and generated route imports. Use `docs/CODEBASE_MAP.md` for ownership and `docs/FEATURE_INVENTORY.md` for role/route context.

## The normal request flow

1. Laravel matches a URL in `routes/web.php`, then applies authentication, active-account, verification, throttle, and controller-specific authorization.
2. A controller receives a typed model and/or FormRequest. Complex validation belongs in the request; access decisions belong in a policy or visibility/context service.
3. A read endpoint builds a visibility-scoped query and sends stable props through a presenter to `Inertia::render('PageName', ...)`. A write endpoint delegates its mutation to an Action and redirects with flash state.
4. `resources/js/app.ts` resolves `PageName` to `resources/js/pages/PageName.vue`. The page accepts typed props, composes layouts/components, and uses generated Wayfinder helpers for subsequent requests.
5. Shared Inertia props from `HandleInertiaRequests` carry authenticated-user/context data. The browser may hide unavailable actions, but the backend must enforce every rule again.

## Attendance

`AttendancePage.vue` is the general attendance and linked-child experience. `SessionAttendancePage.vue` is the roster and coach-attendance sheet for one training session. `AttendanceScanPage.vue` handles the QR confirmation flow; the token service, QR actions, visibility service, policy, and linked-child context enforce eligibility server-side. The `ATT-` and `SCA-` prefixes are display-row identifiers, not route keys.

Trace a manual update from the named `attendance.*` route to `AttendanceController`, an attendance FormRequest, the corresponding Action, the models, and `AttendanceRowPresenter`. Trace QR from the throttled scan route through `AttendanceScanController`, `AttendanceQrTokenService`, and `RecordQrAttendance`.

## Payments

`PaymentController` serves `PaymentsPage.vue` and delegates mutations to payment actions. `PaymentVisibilityService` scopes records by role/context, `PaymentPolicy` authorizes record operations, and `PaymentRowPresenter` creates the table shape. Proof submission/review and transaction history have dedicated requests/actions/components. Parents and athletes must remain restricted to tuition-related payments; never reproduce or loosen that rule only in Vue.

Monthly tuition generation is also a console/scheduled flow. Read `GenerateMonthlyTuitionBills` and the schedule in `routes/console.php` before altering billing behavior.

## Parents and linked children

An administrator links parent and athlete records. `ParentChildContextService` computes eligible children and the active child; `ParentChildContextController` switches/clears that context. `useActiveChild.ts` consumes the shared browser context. Profile, payment, attendance, and QR access must query the same linked-child boundary rather than trusting an athlete ID from a form.

The best characterization tests are `ParentChildContextTest`, `ParentChildProfileAccessTest`, and `QrAttendanceTest`.

## Classes, schedules, and sessions

Locations/branches, legacy class groups, training groups, and weekly/one-day definitions live in the Training Setup module. `WeeklyScheduleController` manages definitions, while `GenerateWeeklyTrainingSessions` creates concrete `TrainingSession` records. `SessionVisibilityService` determines which generated sessions a user may browse.

Private and regular sessions differ in coach/athlete selection. Preserve the backend rule when changing the UI: private group sessions may use applicable coach options, while regular group sessions derive their coach from class/session assignment. Class history and active/archive/all views depend on stable session relationships and public route names.

## Frontend landmarks

- `components/shared`: application-level reusable tables, modals, alerts, panels, badges, stats, and maps.
- `components/forms`: consistent field/file/select wrappers.
- `features/<feature>/components`: reusable only inside one business feature.
- `components/ui`: low-level UI primitives; do not put business rules here.
- `pages/*.types.ts`: contracts local to a page; `types/*`: cross-feature contracts.
- `routes` and `actions`: Wayfinder-generated helpers; regenerate them from PHP definitions rather than hand-editing.

## Safely making a change

Identify the feature and roles, add/adjust characterization tests, make the smallest change at the owning boundary, regenerate Wayfinder when routes/controllers change, run backend/frontend/style checks, inspect generated diffs, and update the cleanup/map docs when ownership changes. Never infer that a controller/page is dead from a single text search because Inertia, providers, scheduled commands, policies, and glob resolvers perform dynamic discovery.
