# Roles and View Modes

RF_IS supports `admin`, `coach`, and `athlete` roles. A user may hold more than one role. The removed parent login/portal is not part of the current application; guardian name, phone, and relation are contact fields on an athlete profile.

## Authorization model

- `RoleResolver::rolesFor()` returns all assigned roles in privilege order. Legacy `users.role` is used only when there are no role-assignment rows.
- `User::hasRole()` and the `role:*` middleware check role membership, not a session-selected role.
- The shared Inertia `auth.user.role` is the highest-privilege assigned role for presentation; `auth.user.roles` lists all assigned roles.
- Permissions are cumulative across assigned roles. UI visibility is not an authorization boundary: controllers, policies, and scoped queries enforce access on the server.
- Public self-registration is disabled. Administrators create accounts and invite users.

## View modes

Some pages have meaningfully different layouts for users with multiple roles. Attendance and payments support a stateless `?mode=` query parameter. `RoleResolver::viewRoleFor()` accepts a requested mode only when the user has that role; otherwise it falls back to the highest-privilege assigned role. This changes page presentation and query scope only where the controller explicitly supports a mode. It never changes role assignments or grants permissions.

For example, a coach-athlete account may choose the coach or athlete view on attendance/payments pages. The user retains the union of permissions attached to both assigned roles on every request.

## Frontend and backend entry points

- Backend role ordering and membership: `app/Support/RoleResolver.php` and `app/Models/User.php`.
- Shared Inertia role props: `app/Http/Middleware/HandleInertiaRequests.php`.
- Frontend presentation helpers: `resources/js/composables/useRole.ts`.
- Attendance and payment view-mode parsing: `AttendanceController` and `PaymentPageController`.
- Route-level access: `routes/web.php`, `routes/settings.php`, and middleware in `app/Http/Middleware`.

Prefer `hasRole()` for permission checks. Use `viewRoleFor()` only on pages that explicitly support a presentation mode. Scope data in the backend before returning Inertia props; do not return hidden-role records and rely on Vue to hide them.

## Regression coverage

Use `tests/Feature/RoleResolutionTest.php`, `MultiRoleRouteAccessTest.php`, `CombinedMultiRoleDashboardTest.php`, `AttendancePaymentAchievementFixTest.php`, and `MultiRolePaymentContextTest.php` when changing role resolution or view-mode behavior. Add both single-role and multi-role cases for changes that affect scoping.
