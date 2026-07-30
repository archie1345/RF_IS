# Active Role Context

## Purpose

An account may have one or several assigned roles. The application must not merge every role's page content, navigation, and data scope into one screen. Instead, each authenticated request runs under one explicit active role.

Supported roles:

- `admin`
- `coach`
- `parent`
- `athlete`

## Core invariants

1. `User::assignedRoles()` and `User::hasRole()` represent all roles assigned to the account.
2. `auth.user.roles` is the complete role list used by the role switcher.
3. `auth.user.activeRole` is the role currently used for page presentation and data scoping.
4. `auth.user.role` is a compatibility alias for `activeRole`; frontend code must not treat it as the legacy database column.
5. `auth.user.primaryRole` is the account's default/first assigned role and is not necessarily the active role.
6. `User::isAdmin()`, `isCoach()`, `isParent()`, and `isAthlete()` evaluate the active role for the current authenticated web request.
7. Outside the current authenticated web request, role membership checks fall back to the complete assignment list so background and administrative operations remain deterministic.
8. Switching to an unassigned role returns HTTP 403.
9. Changing active role never adds or removes a role assignment.
10. Role-specific authorization, navigation, page props, and query visibility must use the active role.

## Request flow

1. `ActiveRoleContextService` resolves assigned roles.
2. It reads `active_role` from the authenticated session.
3. Missing or invalid session values fall back to the first assigned role.
4. `HandleInertiaRequests` shares the active role and available roles with every authenticated Inertia page.
5. `RoleSwitcher.vue` and the shared user menu call `role-context.update`.
6. The switch endpoint validates membership, stores the role in the session, and redirects to the dashboard.
7. Controllers, policies, services, layouts, and pages evaluate the selected role on the next request.

## Frontend usage

Use the global composable when page-specific role props are unnecessary:

```ts
const { role, availableRoles, isMultiRole, isAdmin, isCoach, isParent, isAthlete } = useRole();
```

A page that already receives an explicit role may continue to pass it:

```ts
const { isCoach, isAthlete } = useRole(toRef(props, 'role'));
```

Do not combine navigation for every assigned role. Render `navByRole[activeRole]`.

## Backend usage

Use active-role helpers for page behavior and permissions:

```php
if ($request->user()->isCoach()) {
    // Coach-mode behavior only.
}
```

Use cumulative membership only when the business rule explicitly concerns assignment rather than the current operating context:

```php
if ($request->user()->hasRole('coach')) {
    // The account owns a coach assignment, regardless of active role.
}
```

Use an explicit mode argument in visibility services when a controller intentionally resolves a role before querying:

```php
$payments = $paymentVisibility->visiblePaymentsQuery($request, $activeRole);
$attendance = $attendanceVisibility->scopedAttendanceQuery($request, $activeRole);
```

## Multi-role examples

### Coach and athlete

- Coach mode: assigned coaching sessions, coach attendance, coach-specific navigation and payments.
- Athlete mode: personal QR attendance, athlete schedule, tuition, achievements, and registrations.
- Coach attendance and athlete attendance remain separate records.

### Parent and athlete

- Parent mode: linked children, child attendance, and child tuition.
- Athlete mode: only the account owner's athlete data.
- Child context is not shared outside parent mode.

### Admin and another role

- Admin mode: administration pages and system-wide records.
- Non-admin mode: admin routes and actions are unavailable even though the assignment still exists.
- The account can return to admin mode through the global role switcher.

## Adding a new page

Before adding a role-sensitive page:

1. Identify whether the rule depends on active role or cumulative assignment.
2. Scope backend queries before serializing Inertia props.
3. Avoid returning hidden-role data and merely hiding it in Vue.
4. Use `useRole()` or `auth.user.activeRole` for presentation.
5. Add tests for at least one single-role account and one relevant multi-role combination.
