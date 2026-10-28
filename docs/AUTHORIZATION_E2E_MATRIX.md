# RFIS role and browser coverage

The authorization source is `resources/js/lib/navigation.ts`, the authenticated routes in `routes/web.php`, `RoleResolver`, the model policies, and the resource-scoping services. The application has three actual roles: `admin`, `coach`, and `athlete`. Navigation is additive: an item appears when at least one assigned role is allowed to see it. Role assignments are stored independently from the legacy primary `users.role` value.

## Navigation access matrix

Each account combination below is represented by a separate development account and a separate Playwright test. The count is the number of entries in `navigationSections` that the test visits and checks for an HTTP 200 response.

| Assigned roles          | Visible navigation pages                                                                                | Pages |
| ----------------------- | ------------------------------------------------------------------------------------------------------- | ----: |
| Admin                   | All admin pages, dashboard, schedule, finance, championships, announcements                             |    19 |
| Coach                   | Dashboard, schedule, sessions, attendance, classes, finance, championships, achievements, announcements |     9 |
| Athlete                 | Dashboard, schedule, attendance, finance, championships, achievements, announcements                    |     7 |
| Coach + athlete         | Coach and athlete union                                                                                 |     9 |
| Admin + coach           | Admin, coach, and athlete-shared pages                                                                  |    21 |
| Admin + athlete         | Admin, coach, and athlete-shared pages                                                                  |    21 |
| Admin + coach + athlete | Full navigation union                                                                                   |    21 |

The 21 navigation paths are `/dashboard`, `/training-schedule`, `/sessions`, `/attendance`, `/admin/attendance`, `/admin/instructor-attendance`, `/admin/classes`, `/admin/groups`, `/admin/locations`, `/payments`, `/admin/payroll`, `/admin/billing-settings`, `/admin`, `/users`, `/championships`, `/achievements`, `/admin/events/history`, `/announcements`, `/admin/whatsapp-template`, `/admin/data-export`, and `/admin/activity-logs`.

## Workflow and authorization matrix

| Role or combination         | Permission and scope                                         | Workflow and positive assertion                                                                                                               | Negative assertion                                                                                                         | Result |
| --------------------------- | ------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ------ |
| Admin                       | Manage all admin navigation, locations, groups, and classes  | Create, edit, and delete groups, locations, and classes; manage class participants (`Kelola Peserta`) and weekly/daily schedules              | Blank group/location names, invalid coordinates, and private classes without dedicated athletes are rejected               | PASS   |
| Admin                       | Publish role-targeted announcements and WhatsApp templates   | Create, edit, and delete an athlete-only announcement; configure WhatsApp payment reminder template and public contact number                  | Other roles receive 403 for direct announcement POST or WhatsApp template writes; coach is excluded from athlete target    | PASS   |
| Admin / authorized coach    | Manage session attendance sheets, QR lifecycle, and reports  | Update individual and bulk athlete attendance, update coach teaching status via UUID key, open/rotate/close session QR, and export CSVs       | Unrelated coach gets 403 on attendance sheet; rotated/closed QR tokens and invalid bulk `attendance_ids` are rejected      | PASS   |
| Coach                       | View assigned sessions, check in, and view scoped payroll    | Check in to an assigned session, view assigned athlete profiles and group rosters (`/api/groups/*/athletes`), and download own payroll slip   | Unassigned athlete profile (403), unassigned group roster (403), another user's payment export (403), and payroll estimate | PASS   |
| Athlete                     | Register for championships, scan/sync QR, and upload proofs  | Register for an open championship, scan live QR, queue and sync offline QR scan, manage 2FA/settings/achievements, and upload payment proof   | Duplicate championship registration, invalid proof MIME, and cross-user file/proof/invoice downloads (403) are rejected    | PASS   |
| Coach + athlete             | Keep each role's attendance and settings workflows available | Switch between coach and athlete attendance contexts and verify multi-role settings overview and role-specific profile fields                 | Athlete context does not expose coach check-in controls                                                                    | PASS   |
| Admin                       | Manage multirole accounts, invitations, and safety guards    | Create Coach+Athlete account, activate invited account once via Mailpit, soft delete/restore/permanently delete a disposable non-admin account | Duplicate email, mismatched password, replayed/expired invitation token, and self-demotion/self-suspension/self-deletion   | PASS   |
| Admin                       | Manage finance invoices, ledger, billing rules, and exports  | Create/edit/delete invoices, settle partial/final payments, review proofs, configure QRIS/templates, issue billing rules, and export `.xlsx`  | Ledger-backed invoice locks identity fields; overpayment, unscoped monthly rule, and non-admin data export (403) fail      | PASS   |
| All seven role combinations | Access the union of assigned navigation permissions          | Visit every permitted navigation page across desktop (`1440×1000`) and both mobile viewports (`360×800` and `412×915`), then log out          | Check forbidden admin routes, direct unauthorized mutations, and guest access to `/dashboard` and `/register` (404)        | PASS   |

The test fixture assigns a unique synthetic client address per independent browser scenario to avoid exhausting Fortify's five-attempt login throttle. The development app trusts forwarded headers only from its verified Docker gateway. Production Compose pins Caddy to a private address and trusts only that proxy. Requests directly from untrusted peers ignore `X-Forwarded-For`; this is covered by `RouteSecurityTest.php`.

Every browser test records page errors, browser console errors, failed requests, and HTTP responses with status 400 or greater. Screenshots, traces, and videos are retained for failed tests under the ignored `e2e/test-results/` directory.

## E2E verification status — 2026-10-28

The retained completed production report has **120 total tests: 113 passed, 2 failed, 5 skipped; status failed (exit 1)**. Excel export timed out inside `loginAs` after the browser navigated back to `/login`; the workbook-download assertion never ran. Admin directory/profile workflow also hit its 30-second timeout. A later retained focused profile report has **1/1 passed in 24.9 seconds**. Previous handoff notes say the Excel test and a later partial full run passed, but no retained artifact verifies those outcomes.

The archived report folder named `rfis-audit-interrupted-full-20261028` contains a single passing profile test, not the interrupted full suite. The prior handoff reported **43/120 (41 passed, 2 skipped)** and admin navigation passing, but no full-run summary or exit code remains. Treat that count as unverified. The admin role-navigation test passed in the retained 120-test report. It now allows 120 seconds for its route walk; the separate directory/profile workflow has a 60-second timeout.

The mobile invitation-token test passed in both phone projects during an earlier guarded development rerun. Production skips Mailpit-only tests. Desktop Mailpit creation/resend and verification flows still need a guarded development run. The current authorization row for account lifecycle is `PARTIAL` until those flows pass.

Four feature rows moved to complete from actual retained Playwright results: `ID-07`, `ID-10`, `TR-05`, and `FI-09`. Strict coverage is **46/57 (80.7%)**, with 11 partial rows: `ID-04`, `ID-05`, `ID-09`, `UI-01`, `TR-04`, `FI-07`, `RP-03`, `RP-04`, `API-01`, `BG-01`, and `INT-01`. See [`COMPLETE_FUNCTIONAL_TEST_MATRIX.md`](COMPLETE_FUNCTIONAL_TEST_MATRIX.md).

At the preceding handoff, Docker Desktop's Windows service and `docker-desktop` WSL distro were stopped. The prior attempts exited before database work, so cleanup and source-schema comparison were unconfirmed then. See the resume verification below for current state.

### Resume verification — 2026-10-28

Docker Engine became reachable. The latest guarded production run completed **120 tests: 115 passed, 5 skipped, 0 failed** (exit 0). Desktop and both mobile project role-navigation workflows passed for the seven seeded role combinations. The admin directory/profile scope checks and Excel export authorization case also passed. The five skips were Mailpit-only flows, including desktop invitation/verification and mobile invitation acceptance; those authorization workflows remain `PARTIAL` until rerun against a safe Mailpit-backed target.

The persistent production source schema stayed at **43 tables, 1 user, 77 migrations, batch 3**. The interrupted run's uniquely named disposable schema/user, two exited containers, and temporary Caddyfile remain present. The resumed guarded runs removed their own temporary resources. No persistent production service was started or restarted by this continuation. The development-role runner was not invoked because it runs `migrate:fresh --seed` against the persistent development database, conflicting with the current no-destructive-database instruction.
