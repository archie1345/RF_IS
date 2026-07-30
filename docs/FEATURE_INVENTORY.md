# Feature inventory

Status reflects reachable routes, framework registration, or tested behavior as of 2026-07-23. Roles are summarized from route middleware and controller/policy checks; authorization remains server-side truth.

| Feature | Roles | Main routes | Controller / backend entry | Page / component | Status and notes |
|---|---|---|---|---|---|
| Authentication and invitations | Guest; invited users | Fortify routes; `invitations.*` | `FortifyServiceProvider`, `Auth/InvitationController` | `pages/auth/*` | **Active**. Invitation has two route names on one POST URI for compatibility. |
| Account settings and security | Authenticated active user | `profile.*`, `user-password.*`, `two-factor.show`, `appearance.edit` | `Settings/*Controller` | `pages/settings/*`, `profiles/ProfileDetailsPage.vue` | **Active**. Verified middleware applies to destructive/security screens. |
| Account administration | Admin | `admin.accounts.*`, `admin.data-transfer.*` | `AdminController` | `AdminPage.vue` | **Active**. Includes lifecycle, invitations, import/export, and profile editing. |
| User/athlete roster | Admin/authorized staff; self where permitted | `users.*`, `athletes.*` | `UserDirectoryController`, `ProfileAccessController` | `AthletesPage.vue`, `profiles/ProfileRosterPage.vue` | **Active**. Sensitive identifiers remain backend-controlled. |
| Parent-child linking/context | Admin for linking; linked parent for context | `parents.children.sync`, `parent.children.*` | `ParentChildContextController`, `ParentChildContextService` | `ParentChildSwitcherPage.vue`, `useActiveChild.ts` | **Active**. Linked-child scope is security-sensitive and covered by feature tests. |
| Training locations/branches | Admin | `admin.locations`, `admin.branches.*` | `TrainingLocationController`, `Admin/BranchController` | `AdminLocationsPage.vue` | **Active**. |
| Training groups/classes | Admin; coaches where controller permits | `admin.groups*`, `admin.training-groups.*`, `admin.classes` | admin group controllers, `TrainingClassController` | `AdminGroupsPage.vue`, `AdminClassesPage.vue` | **Active**. Legacy Group/ClassGroup naming is retained for compatibility. |
| Weekly/one-day/private schedules | Authenticated users; mutations role-checked | `training-schedule.*`, `training-schedules.*`, `admin.schedules.*` | `WeeklyScheduleController`, generation action/command | `WeeklySchedulePage.vue`, `WeeklyScheduleBoard.vue` | **Active**. Private-athlete and coach semantics require regression review when changed. |
| Training sessions | Authenticated users with policy/visibility scope | `sessions.*` | `SessionController`, session actions/services/presenter | `SessionsPage.vue` | **Active**. Active/archive/all views are page-level presentation of scoped backend data. |
| Session attendance sheet | Authorized admin/coach | `sessions.attendance`, coach-attendance routes | `SessionController`, attendance actions | `SessionAttendancePage.vue` | **Active**. Separate from general reporting. |
| Athlete attendance | Authorized staff; scoped athlete/parent | `attendance.*` | `AttendanceController`, attendance actions/service/presenter | `AttendancePage.vue` | **Active**. Manual, single, and bulk mutations use dedicated requests/actions. |
| QR attendance | Authorized session staff; authenticated scanner | `attendance.scan.*`, `sessions.attendance-qr.*` | QR controller/action/token service | `AttendanceScanPage.vue`, `SessionAttendanceQrPanel.vue` | **Active**. Token throttling and linked-child checks must remain intact. |
| Coach attendance/reporting | Admin/authorized coach workflows | `admin.instructor-attendance*`, session coach-attendance routes | `AdminAttendanceReportController`, `SessionController` | `AdminAttendanceReportPage.vue`, `SessionAttendancePage.vue` | **Active**. Uses a distinct `CoachAttendance` model. |
| Payments and tuition | Admin/finance; scoped parents/athletes | `payments.*`, admin finance redirects/monthly dues | `PaymentController`, finance feature controller, actions/service/presenter | `PaymentsPage.vue`, `PaymentTransactionHistory.vue` | **Active**. Parent/athlete visibility is tuition-only and security-sensitive. |
| Championships/events/results | Authenticated roles; mutations authorized in controller | `championships.*`, `admin.events*` | championship/export and admin event controllers | `ChampionshipsPage.vue`, `ChampionshipDetailPage.vue` | **Active**. Includes registration, settlement, coach registration, results, export. |
| Achievements/certifications | Authenticated user; authorized profile editors | `achievements.*`, `users.*.achievements/certifications`, profile routes | achievement/profile controllers and actions | `AchievementsPage.vue`, profile components | **Active**. |
| Announcements | Authenticated readers; authorized writers | `announcements.*` | `AnnouncementController` | `AnnouncementsPage.vue` | **Active**. |
| Dashboard | Authenticated active verified users | `dashboard`, `admin.dashboard` | `DashboardController` | `Dashboard.vue` | **Active**. |
| Activity audit | Admin | `admin.activity-logs.index`; scheduled prune command | `ActivityLogController`, `ActivityLogger` | `AdminActivityLogsPage.vue` | **Active**. Logging should remain centralized. |
| Generic admin feature summaries | Admin | members, instructors, daily schedules, event routes | `Admin/Features/*` | `AdminFeaturePage.vue` | **Active**, some screens are summary/read models rather than full CRUD. |
| Legacy JSON CRUD API | Public route definitions (current state) | `/api/athletes`, branches, groups, coaches, payments, users | `Http/Controllers/Api/*` | None | **Partial / risk**. Reachable and therefore not dead; authentication/consumers need product review before changes. |
| Legacy admin finance/event/schedule URLs | Authenticated | named redirects under `/admin` | route redirects | Current destination pages | **Deprecated but active**. Retained to keep public links and sidebar/bookmarks stable. |

## Tests by capability

- Identity/access: auth, settings, role resolution/flows, account lifecycle/security, dashboard.
- Parent/athlete: parent-child context/profile access and manual regression coverage.
- Attendance/sessions: initialization, QR controllers/flows, schedule classification, attendance/payment/achievement fixes.
- Finance/communication/events: payment and announcement flow, legacy finance redirects, championships.

An entry is not a deletion candidate merely because it lacks a dedicated test; route reachability and framework discovery also count as use.
