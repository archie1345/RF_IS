# UI simplification changelog

This file records the interface reductions introduced with the payroll, family, multi-role, schedule, attendance, and championship workflow update. It exists so removed or condensed presentation can be restored without guessing.

## Global tables

**Changed file:** `resources/js/components/shared/DataTable.vue`

- Pagination is enabled by default for every shared table.
- Added Previous and Next controls, current-page indicator, displayed-row range, and row-count selector.
- Added automatic single-select filtering when a table contains multiple `athlete`, `child`, or `coach` values.
- Added consistent status tones for common attendance, payment, event, and account statuses.
- Reduced header spacing and card density.

**Restore approach:** revert the DataTable commit that introduced the pagination and automatic filter behavior, then restore page-specific pagination where required.

## Parent experience

**Changed files:**

- `resources/js/pages/ParentChildSwitcherPage.vue`
- `app/Http/Controllers/ParentChildContextController.php`
- `app/Services/ParentChildContextService.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/components/dashboard/DashboardHeroSection.vue`

- Removed the persistent “displayed child” selector from the dashboard.
- The child page is now only a profile picker.
- Parent-facing data queries return every linked child.
- Shared tables provide child/athlete filtering instead of changing global child context.
- Schedule cards explicitly show which child or children a schedule belongs to.

**Restore approach:** restore active-child session scoping in `ParentChildContextService`, re-enable `activeChild` in Inertia shared data, and restore the child selector in `DashboardHeroSection` and `ParentChildSwitcherPage`.

## Sidebar

**Changed file:** `resources/js/components/AppSidebar.vue`

- Added Payroll Pelatih.
- Renamed the WhatsApp item to include contact configuration.
- Removed the separate QRIS sidebar entry because QRIS remains reachable from the payment workflow.
- Reduced logo/header height.
- Dashboard logo always opens `/dashboard` so multi-role users reach the combined dashboard.

**Restore approach:** add the QRIS item back to the Finance section and restore the previous header/logo classes.

## Dashboard

**Changed files:**

- `app/Http/Controllers/DashboardController.php`
- `resources/js/pages/Dashboard.vue`
- `resources/js/components/dashboard/DashboardHeroSection.vue`

- Multi-role users now receive one dashboard containing sections and key metrics from all assigned roles.
- Removed role-specific dashboard switching and the child-context control.
- Admin payroll reminder is included in dashboard metrics.

**Restore approach:** return only active-role payloads from `DashboardController`, remove the role loop from `Dashboard.vue`, and restore role-specific hero copy/actions.

## Schedule board

**Changed files:**

- `app/Http/Controllers/Training/WeeklySchedulePageController.php`
- `resources/js/pages/WeeklySchedulePage.vue`
- `resources/js/features/training/components/WeeklyScheduleBoard.vue`
- `resources/js/types/training.ts`

- The original weekly schedule board design has been restored, including the seven-day desktop layout, mobile day cards, colored type badges, gradients, and original detail modal styling.
- The added `Atlet / Anak` row remains visible on schedule cards and in the detail modal.
- Parent users retain child filtering.
- Private and dedicated schedules retain athlete names.

**Restore approach:** to remove only the added participant information, remove `participantLabel()` and the `Atlet / Anak` rows from `WeeklyScheduleBoard.vue`; the original visual structure can remain unchanged.

## Public page and WhatsApp settings

**Changed files:**

- `resources/js/pages/Welcome.vue`
- `resources/js/pages/admin/WhatsAppTemplatePage.vue`
- `app/Http/Controllers/Admin/WhatsAppTemplateController.php`

- Condensed the public landing page.
- Public registration now opens WhatsApp to an admin-configured number.
- Combined payment reminder template and public contact number in one admin settings page.

**Restore approach:** restore the prior Welcome layout and keep or remove the `public_admin_whatsapp` setting independently.

## Championship details

**Changed files:**

- `app/Http/Controllers/ChampionshipController.php`
- `resources/js/pages/ChampionshipDetailPage.vue`

- Consolidated event metadata into one compact row.
- Added one participant modal for coach/admin entry.
- Preserved registration editing, result entry, profile access, exports, and coach assignment while reducing separate page sections.

**Restore approach:** restore the previous detail page template; the staff participant endpoint may remain available without being displayed.

## Attendance reports

**Changed file:** `resources/js/pages/AdminAttendanceReportPage.vue`

- Reduced metric card decoration.
- Added explicit colors for athlete and coach attendance categories.

**Restore approach:** restore the previous report page while retaining the `StatusBadge` slot if status colors are still desired.

## Payroll pages

**New files:**

- `resources/js/pages/admin/AdminPayrollPage.vue`
- `app/Http/Controllers/Admin/AdminPayrollController.php`

These are additions rather than reductions. Removing them requires removing the routes and payroll navigation item, but existing payroll ledger rows and migrations should not be deleted after production data exists.
