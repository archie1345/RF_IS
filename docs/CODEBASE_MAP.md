# Codebase map

This map describes the source tree as audited on 2026-07-23. Generated dependencies and runtime output (`vendor`, `node_modules`, `public/build`, `storage`, and `bootstrap/cache`) are outside the source architecture.

## Request and rendering entry points

- `routes/web.php` is the authenticated browser application and preserves the public route names used by Wayfinder.
- `routes/settings.php` and `routes/auth.php` contain account settings and invitation/authentication entry points.
- `routes/api.php` contains the legacy JSON CRUD surface. It is active because Laravel loads it from `bootstrap/app.php`; it should be authenticated and consolidated in a separate, reviewed change.
- `routes/console.php` schedules activity-log pruning, monthly tuition generation, and training-session generation.
- `resources/js/app.ts` resolves Inertia page names under `resources/js/pages`; `resources/js/ssr.ts` is the server-rendering entry point.

## A. Identity and access

**Routes:** `/`, `/dashboard`, `/users`, `/athlete`, `/parents`, `/parent/children`, `/settings/*`, and `/invitations/{token}`.

**Backend:** `AdminController`, `UserDirectoryController`, `ProfileAccessController`, `ParentChildContextController`, profile/settings/auth controllers; `Actions/Users`, `Actions/Profiles`, `Actions/ParentChild`; profile and invitation requests; `ParentChildContextService`, `RoleResolver`, and `ActivityLogger`.

**Models:** `User`, `UserProfile`, `UserRoleAssignment`, `UserInvitation`, `ParentProfile`, `Athlete`, `Coach`, `UserFile`, `UserCertification`, and `UserAchievement`.

**Frontend:** `AdminPage.vue`, `AthletesPage.vue`, `ParentChildSwitcherPage.vue`, `pages/profiles/*`, `pages/settings/*`, and `pages/auth/*`; shared context is in `useRole.ts` and `useActiveChild.ts`.

**Shared dependencies:** Fortify, account-active middleware, policies, profile media/rules/page-data support, and the Inertia shared user payload.

## B. Athlete management

**Routes:** named `users.*`, `athletes.*`, `parents.children.sync`, and `parent.children.*` routes in `routes/web.php`.

**Backend:** `UserDirectoryController`, `ProfileAccessController`, `ParentChildProfileController`, profile actions/requests, and `ParentChildContextService`.

**Models:** `Athlete`, `ParentProfile`, `User`, `UserProfile`, achievements, certifications, and files.

**Frontend:** `AthletesPage.vue`, `profiles/ProfileRosterPage.vue`, `profiles/ProfileDetailsPage.vue`, their colocated components/composable/config/type modules.

## C. Training setup

**Routes:** `training-schedule.*`, `training-schedules.*`, `admin.locations`, `admin.classes`, `admin.groups`, `admin.training-groups.*`, `admin.branches.*`, and `admin.schedules.*`.

**Backend:** controllers under `Http/Controllers/Training` and `Http/Controllers/Admin` (`BranchController`, `GroupController`, `TrainingGroupController`), plus `GenerateWeeklyTrainingSessions` and its console command.

**Models:** `Branch`, legacy `Group`, `TrainingGroup`, `WeeklyTrainingSchedule`, `TrainingSession`, and `Coach`.

**Frontend:** `AdminLocationsPage.vue`, `AdminClassesPage.vue`, `AdminGroupsPage.vue`, `WeeklySchedulePage.vue`, and `features/training/components/WeeklyScheduleBoard.vue`.

**Legacy terminology:** database and route compatibility retain `Group`/class-group naming. New code should use Training Group or Training Class in identifiers and document mappings to legacy tables.

## D. Sessions

**Routes:** `sessions.index/store/update/destroy/join/attendance`, attendance-QR routes, and coach-attendance routes.

**Backend:** `SessionController`, `SessionAttendanceQrController`, actions under `Actions/Sessions` and `Actions/Attendance`, session requests, `SessionVisibilityService`, `AttendanceQrTokenService`, `SessionPolicy`, and `SessionRowPresenter`.

**Models:** canonical `TrainingSession`, compatibility `Session`, `WeeklyTrainingSchedule`, `CoachAttendance`, `Coach`, and `Attendance`.

**Frontend:** `SessionsPage.vue` and types for session browsing/editing; `SessionAttendancePage.vue`, its types, and `SessionAttendanceQrPanel.vue` for a single attendance sheet.

## E. Attendance

**Routes:** `attendance.*`, `attendance.scan.*`, `sessions.attendance`, `sessions.attendance-qr.*`, admin athlete/coach attendance reports, and coach-attendance mutations.

**Backend:** `AttendanceController`, `AttendanceScanController`, `SessionController`, `SessionAttendanceQrController`, attendance actions and requests, `AttendanceVisibilityService`, `AttendanceQrTokenService`, `AttendancePolicy`, and `AttendanceRowPresenter`.

**Models:** `Attendance`, `CoachAttendance`, `TrainingSession`, `Athlete`, `Coach`, and parent-child relationships.

**Frontend:** `AttendancePage.vue` is the general attendance/parent-child view; `SessionAttendancePage.vue` is one session's sheet; `AttendanceScanPage.vue` is QR check-in; `AdminAttendanceReportPage.vue` is reporting/export. Feature components contain QR and attendance-window UI.

**Boundary:** eligibility and visibility belong in services/actions and policies, not page controllers. Parent QR authorization must continue through the linked-child context.

## F. Payments

**Routes:** `payments.*`, compatibility redirects under `admin`, monthly-dues settings/generation, and championship payment settlement.

**Backend:** `PaymentController`, `AdminFinanceFeatureController`, payment actions/requests, `PaymentVisibilityService`, `PaymentPolicy`, `PaymentRowPresenter`, and `GenerateMonthlyTuitionBills`.

**Models:** `Payment`, `PaymentTransaction`, `BillingSetting`, `InvoiceTemplate`, `User`, and registrations.

**Frontend:** `PaymentsPage.vue`, `PaymentsPage.types.ts`, and `features/payments/components/PaymentTransactionHistory.*`.

**Boundary:** tuition-only parent/athlete visibility is owned by `PaymentVisibilityService`; controller-specific copies should not be introduced.

## G. Championships, achievements, and communication

**Routes:** `championships.*`, `achievements.*`, `announcements.*`, and admin event routes.

**Backend:** championship/export, achievement, announcement, and admin event controllers; profile achievement actions/requests.

**Models:** `Event`, `EventRegistration`, `EventCoachRegistration`, `Registration`, `Result`, `Payment`, `UserAchievement`, and `Announcement`.

**Frontend:** `ChampionshipsPage.vue`, `ChampionshipDetailPage.vue`, `AchievementsPage.vue`, `AnnouncementsPage.vue`, and profile achievement components.

## H. Dashboard, reporting, and shared UI

**Backend:** `DashboardController`, `ActivityLogController`, `Admin/Features/*`, presenters, domain status value helpers, and `ActivityLogger`.

**Frontend:** `Dashboard.vue`, dashboard components, `AdminFeaturePage.vue`, `AdminActivityLogsPage.vue`; reusable components are in `components/shared`, form controls in `components/forms`, primitives in `components/ui`, layouts in `layouts`, and cross-feature types in `types`.

Key shared components are `DataTable`, `ResourceTablePanel`, `FormModal`, `AppAlert`, `StatusBadge`, form field wrappers, and `LeafletLocationMap`. DataTable capabilities remain opt-in at each call site.

## Active source inventory

| Kind | Location | Notes |
|---|---|---|
| Routes | `routes/{web,auth,settings,api,console}.php` | All five are loaded application entry points. |
| Controllers | `app/Http/Controllers` | Browser, API, admin, profile, training, settings, and auth adapters. |
| Requests | `app/Http/Requests` | Complex attendance, payment, session, profile, auth, and settings validation. |
| Actions | `app/Actions` | Mutations for attendance, sessions, payments, profiles, invitations, and parent context. |
| Services | `app/Services` | QR tokens and visibility/access query rules. |
| Presenters | `app/Presenters` | Attendance, payment, and session table rows. |
| Models | `app/Models` | Persistence and relationships for all business modules. |
| Policies | `app/Policies` | Attendance, payment, and session authorization. |
| Pages | `resources/js/pages` | Inertia pages resolved recursively by `app.ts`; `ErrorPage.vue` is resolved dynamically by the exception renderer. |
| Components | `resources/js/components`, `resources/js/features` | Shared/primitives plus attendance, payment, and training feature components. |
| Composables | `resources/js/composables`, page `composables` folders | Shared user/UI state and profile route behavior. |
| Types | `resources/js/types`, colocated `*.types.ts` | Shared contracts and page/component-local contracts. |
| Tests | `tests/Feature`, `tests/Unit` | Auth/settings, role/access, parent-child, attendance/QR, payment, championship, dashboard, schedules, and lifecycle coverage. |

## Change-impact checklist

Before changing a boundary, trace route name and URL, controller/request, policy/service/action, model relationships, presenter payload, Inertia page props, generated Wayfinder import, component imports, scheduled commands, and relevant feature tests. Preserve migrations and compatibility redirects.
