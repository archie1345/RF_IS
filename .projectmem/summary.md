# projectmem - RF_IS

_Last updated: 2026-11-20_

## Project purpose
RF IS is a Laravel 12 + Inertia + Vue application for managing athlete and guardian contact records, coach sessions and attendance, manual bill/invoice proof review, championships, and administrative data exports.

## Recent issues
- [OPEN] #0009 Interrupted production audit left identifiable disposable schema/user, app/proxy container names, and proxy Caddyfile; Docker engine is stopped so cleanup and source counts cannot be checked. [/tmp/rfis-production-audit-proxy.8vrmkm/Caddyfile] (open)
  - Partial attempt: Decoded the retained Playwright reports and residual proxy config. The full report is 120 tests with 113 pass, 2 fail, 5 skip; the alleged 43-test interrupted report is not retained. Temp schema/resource identities are derivable, but DB existence and source counts need Docker. [docs/DATABASE_BACKUP_AND_RECOVERY.md:75]
  - Partial attempt: Docker restored; read-only checks confirm 43 source tables, 1 user, 77 migrations batch 3. Exact interrupted run schema/user, two exited containers, and Caddyfile remain. [docs/DATABASE_BACKUP_AND_RECOVERY.md]
  - Partial attempt: Final production audit removed its own temporary schema, user, containers, and proxy directory; source counts remain 43/1/77/batch3. Earlier uniquely named orphan resources remain untouched under the no-destructive-database instruction. [docs/DATABASE_BACKUP_AND_RECOVERY.md]
- [DONE] #0021 Parent-role migration rollback drops guardian contact fields [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php:64] -> Keep guardian columns during rollback because parent schema cannot restore their values; isolated SQLite regression and full backend suite pass. [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php] (fixed)
  - Failed attempt: Isolated SQLite rollback regression failed: migration down() drops guardian_phone and the contact value. No persistent database was touched. [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php:63]
- [DONE] #0020 Legacy public file disks may expose historical uploads by static path [database/migrations/2026_07_24_000002_add_disk_to_user_files_table.php:11] -> Deny direct Apache requests to /storage/user-files while authenticated UserFileController downloads remain available; config regression passes. Production file inventory remains unverified. [public/.htaccess] (fixed)
  - Partial attempt: Confirmed legacy exposure path: public storage symlink serves user-files paths directly; Apache rewrite lacks a deny rule. Added a failing config regression test; production rows remain unverified. [public/.htaccess]
  - Failed attempt: Initial legacy-file test expected HTTP 401, but web middleware correctly redirects unauthenticated browser users to login (302). Update assertion to route contract. [tests/Feature/UserFilePrivacyTest.php:74]
- [DONE] #0019 Combined Excel export bypasses formula-safe cell normalization [app/Services/AdminDataExportService.php:403] -> Normalize formula-like combined-export cell values before workbook serialization; regression and full isolated backend suite pass. [app/Services/AdminDataExportService.php] (fixed)
- [DONE] #0018 Manual coach attendance does not enforce session athlete eligibility [app/Actions/Attendance/CreateAttendanceRecord.php:30] -> Apply session-athlete eligibility to coach manual attendance; allowed, denied, persistence, and full isolated backend tests pass. [app/Actions/Attendance/CreateAttendanceRecord.php] (fixed)
- [DONE] #0017 Coach session creation does not scope submitted group to assignments [app/Actions/Sessions/CreateSession.php:21] -> Reject coach-created sessions for groups outside coach assignments; assigned-group and denial regression tests plus full isolated backend suite pass. [app/Actions/Sessions/CreateSession.php] (fixed)
- [DONE] #0016 Coach class page returns unscoped group rosters and athlete details [app/Http/Controllers/Training/TrainingClassController.php:25] -> Scope coach class listing, rosters, and athlete options to assigned groups; regression and full isolated backend suite pass. [app/Http/Controllers/Training/TrainingClassController.php] (fixed)
- [DONE] #0015 Coach self-assignment grants event management without prior event access [routes/web.php:157] -> Require existing event-manager assignment before a coach can add their own event coach registration; authorization regression and full isolated backend suite pass. [app/Http/Controllers/ChampionshipController.php] (fixed)
- [DONE] #0014 Composer audit reports Symfony Mime CVE-2026-45070 in locked dependency tree [composer.lock] -> composer.lock now resolves symfony/mime 7.4.22 and symfony/polyfill-mbstring 1.43.3; current composer audit --locked reports no advisories. (fixed)
  - Partial attempt: Composer update for symfony/mime CVE remains blocked: host PHP lacks ext-gd; local apt package download returned 404, and Docker is unavailable. [composer.lock]
- [DONE] #0013 Unverified PHP 8.5 runtime bump conflicts potentially with locked Composer package constraints [Dockerfile.prod:19] -> Confirmed PHP container and CI versions are consistently 8.4.27; no PHP 8.5 image tag remains in Dockerfiles or workflows. (fixed)
  - Partial attempt: Changed all dirty PHP 8.5.12 image and CI targets to PHP 8.4.27 because locked PhpSpreadsheet 1.30.7 requires PHP <8.5; Composer confirms 8.4.27 is allowed. Docker build remains unverified because Docker engine is unavailable.
- [DONE] #0012 CI import/order misclassifies @/routes when Wayfinder files are absent [.github/workflows/lint.yml] -> Both workflows generate Wayfinder aliases before lint/type-check; final lint reports 0 errors and both type-checks/build pass. [.github/workflows/lint.yml] (fixed)
  - Failed attempt: ESLint autofix without generated Wayfinder routes reordered 65 imports, but generated-route lint then rejected them. [resources/js]
- ...and 10 older fixed issues not shown (`pjm search`, or `get_issue(issue_id)` via MCP)

## Decisions
- No decisions logged yet.

## Notes
- Stage (#36)
- Merge branch 'attendance' into stage
- Merge branch 'stage'
- initializing production environment and testing (#52)
- 202608022124 (#54)
- Stage (#56)
- 202607091444
- gotcha: RP-04 legacy instructor summary has no profile-link action in AdminPeopleFeatureController; member rows do link by UUID. Do not infer an instructor-link feature without a requirement.
- New feature: add prefix [.github/workflows/tests.yml]
- Merge: Merge branch 'main' into staging-preparation

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
