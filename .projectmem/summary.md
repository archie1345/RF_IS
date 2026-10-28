# projectmem - RF_IS

_Last updated: 2026-10-28_

## Project purpose
RF IS is a Laravel 12 + Inertia + Vue application for managing athlete and guardian contact records, coach sessions and attendance, manual bill/invoice proof review, championships, and administrative data exports.

## Recent issues
- [OPEN] #0009 Interrupted production audit left identifiable disposable schema/user, app/proxy container names, and proxy Caddyfile; Docker engine is stopped so cleanup and source counts cannot be checked. [/tmp/rfis-production-audit-proxy.8vrmkm/Caddyfile] (open)
  - Partial attempt: Decoded the retained Playwright reports and residual proxy config. The full report is 120 tests with 113 pass, 2 fail, 5 skip; the alleged 43-test interrupted report is not retained. Temp schema/resource identities are derivable, but DB existence and source counts need Docker. [docs/DATABASE_BACKUP_AND_RECOVERY.md:75]
  - Partial attempt: Docker restored; read-only checks confirm 43 source tables, 1 user, 77 migrations batch 3. Exact interrupted run schema/user, two exited containers, and Caddyfile remain. [docs/DATABASE_BACKUP_AND_RECOVERY.md]
  - Partial attempt: Final production audit removed its own temporary schema, user, containers, and proxy directory; source counts remain 43/1/77/batch3. Earlier uniquely named orphan resources remain untouched under the no-destructive-database instruction. [docs/DATABASE_BACKUP_AND_RECOVERY.md]
- [DONE] #0012 CI import/order misclassifies @/routes when Wayfinder files are absent [.github/workflows/lint.yml] -> Both workflows generate Wayfinder aliases before lint/type-check; final lint reports 0 errors and both type-checks/build pass. [.github/workflows/lint.yml] (fixed)
  - Failed attempt: ESLint autofix without generated Wayfinder routes reordered 65 imports, but generated-route lint then rejected them. [resources/js]
- [DONE] #0011 Mobile login helper page.goto to locale redirect aborts dashboard navigation and triggers false failed-request diagnostics [e2e/tests/mobile-workflows.test.ts:43] -> Mobile setup no longer races page navigation with locale redirect; both focused production tests passed. [e2e/tests/mobile-workflows.test.ts:43] (fixed)
- [DONE] #0010 Mobile connection-toast test races exit and enter transitions; getByRole status sees two toasts [e2e/tests/mobile-workflows.test.ts:524] -> Toast test now waits for exit transition; focused production test passed. [e2e/tests/mobile-workflows.test.ts:524] (fixed)
- [DONE] #0008 Docker engine unavailable in WSL blocks fresh guarded E2E verification and cleanup/schema checks for interrupted production audit. [docs/AUTHORIZATION_E2E_MATRIX.md:45] -> Docker Engine 29.9.2 is reachable; guarded production E2E and source-schema verification completed. [docs/REMAINING_WORK_AND_SECURITY_AUDIT.md] (fixed)
  - Partial attempt: Retried guarded production and development E2E runners. Both exited before tests because WSL cannot reach the Docker Linux engine; no services or databases changed. [e2e/run-production-audit.sh:1]
  - Partial attempt: Read-only WSL diagnostics found Docker Desktop service stopped and docker-desktop distro stopped. Starting engine risks auto-restarting production rf-db (restart: unless-stopped); held pending user direction. [compose.prod.yaml:136]
- [DONE] #0007 Full production E2E selected Excel workbook export fails after admin export request; failure context requires investigation. [e2e/tests/role-workflows.test.ts:1263] -> Excel workbook download and non-admin denial passed in the final complete production E2E run. [e2e/tests/role-workflows.test.ts:1263] (fixed)
  - Failed attempt: Full production E2E exposed admin Excel-export login mismatch and a 30-second timeout in the admin profile-scope workflow; captured trace and screenshots, investigating shared seeded state.
  - Partial attempt: Excel export login redirect did not reproduce: focused production rerun and subsequent full-run case passed. Root cause remains unknown; later full suite was interrupted. [e2e/tests/role-workflows.test.ts:1263]
- [DONE] #0006 Mobile production E2E QR attendance scan fails after the browser workflow reaches the live scan scenario; failure context requires investigation. [e2e/tests/mobile-workflows.test.ts:523] -> Mock camera stream now emits repeated deterministic QR frames; small and large mobile scan E2E tests pass. [e2e/tests/mobile-workflows.test.ts:576] (fixed)
  - Failed attempt: Full production E2E: large phone decodes the mocked camera QR and passes; small phone stays on /attendance until 30-second wait expires.
- [DONE] #0005 Mobile production E2E billing-rule and invoice-dialog workflow fails at phone viewport; failure context requires investigation. [e2e/tests/mobile-workflows.test.ts:188] -> Mobile billing workflow now waits for the server Inertia response before asserting the invoice dialog closes; both phone sizes pass. [e2e/tests/mobile-workflows.test.ts:231] (fixed)
  - Failed attempt: Full production E2E: small viewport one-time billing submission redirects but dialog remains; large viewport passes. Investigate response handling and generation timing.
- [DONE] #0004 Mobile connectivity E2E marks late logo, avatar, and remote-font requests as failures after toggling offline. [e2e/tests/mobile-workflows.test.ts:499] -> Waited for document fonts and visible images before switching browser offline; both mobile connection-toast tests pass. [e2e/tests/mobile-workflows.test.ts:502] (fixed)
  - Failed attempt: Production mobile-large connection-toast test failed because image and Bunny Fonts requests remained in flight when the test toggled offline. [e2e/tests/mobile-workflows.test.ts:499]
- [DONE] #0003 Indonesian profile photo crop status remains English because its source text lacks a translation entry. [resources/js/pages/profiles/ProfileDetailsPage.vue:562] -> Added missing Indonesian translation for the crop-ready status; mobile crop tests pass at both phone viewport sizes. [resources/js/composables/useI18n.ts:682] (fixed)
  - Failed attempt: Production-mode mobile profile crop test expected the localized ready-to-save status, but rendered untranslated English. [e2e/tests/mobile-workflows.test.ts:660]
- [DONE] #0002 Production audit role-navigation E2E times out after 30 seconds on admin's full authorized route walk. [e2e/tests/role-workflows.test.ts:152] -> The authorized route walk now passes in the complete production E2E suite. [e2e/tests/role-workflows.test.ts:152] (fixed)
  - Failed attempt: Full production-mode run reached Admin navigation test timeout while visiting authorized routes; failed at response wait for /announcements at 30s. [e2e/tests/role-workflows.test.ts:109]
- ...and 1 older fixed issue not shown (`pjm search`, or `get_issue(issue_id)` via MCP)

## Decisions
- No decisions logged yet.

## Notes
- Stage (#34)
- Attendance (#35)
- Stage (#36)
- Merge branch 'attendance' into stage
- Merge branch 'stage'
- initializing production environment and testing (#52)
- 202608022124 (#54)
- Stage (#56)
- 202607091444
- gotcha: RP-04 legacy instructor summary has no profile-link action in AdminPeopleFeatureController; member rows do link by UUID. Do not infer an instructor-link feature without a requirement.

## Key files
- `resources/js/pages/AdminFeaturePage.vue`
- `resources/js/pages/AdminAttendanceReportPage.vue`
- `app/Http/Controllers/Admin/Features/AdminAttendanceReportController.php`
- `routes/web.php`
- `app/Actions/Sessions/GenerateWeeklyTrainingSessions.php`
- `app/Http/Controllers/TrainingManagementController.php`
- `resources/js/pages/TrainingManagementPage.vue`
- `app/Http/Controllers/DashboardController.php`
- `.env.example`
- `.env.testing`
- `Dockerfile`
- `README.md`
- `app/Actions/Attendance/BulkUpdateAttendanceStatus.php`
- `app/Actions/Attendance/CreateAttendanceRecord.php`
- `app/Actions/Attendance/GenerateSessionAttendanceQr.php`
- `app/Actions/Attendance/InitializeSessionAttendance.php`
- `app/Actions/Attendance/RecordQrAttendance.php`
- `app/Actions/Attendance/RevokeSessionAttendanceQr.php`
- `app/Actions/Fortify/CreateNewUser.php`
- `app/Actions/Payments/ReviewPaymentProof.php`

## Open questions
- None logged yet.
