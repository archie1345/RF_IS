# Cleanup report

## Scope and outcome

The 2026-07-23 pass performed a repository-wide source inventory and dependency/reference audit, established target conventions, and documented module boundaries. It intentionally stopped short of speculative moves/deletions and broad formatting because the current tree already contains Actions, Services, Presenters, FormRequests, Policies, colocated page types, and feature components, while the baseline has unresolved test/type/style failures. No runtime behavior, UI, route, migration, database meaning, or generated source was changed.

## Files changed

- Added `CODEBASE_MAP.md`: request entry points, eight module maps, active source inventory, and change-impact checklist.
- Added `FEATURE_INVENTORY.md`: role, route, backend/frontend entry point, lifecycle status, and notes for each reachable feature.
- Added `CLEANUP_CANDIDATES.md`: suspicious/duplicate-looking files and the route, framework, reference, migration, compatibility, and test evidence preventing unsafe deletion.
- Added `CODE_STYLE_AND_STRUCTURE.md`: backend/frontend placement and naming rules, import order, abstraction criteria, ID normalization, and Wayfinder guidance.
- Added `LEARNING_GUIDE.md`: end-to-end request flow and guided reading paths for attendance, payments, parent access, and training/session domains.
- Added this report.

## Files moved or deleted

None. The audit did not establish deletion-grade proof for any candidate. In particular, API controllers are route-reachable, generic/error pages are dynamically resolved, duplicate-looking models/controllers have distinct route or compatibility responsibilities, and redirects preserve named/public links.

## Behavior preserved and intentionally unchanged

- All 169 discovered routes, names, URLs, redirects, middleware, and controller bindings.
- Parent-linked-child, tuition visibility, QR eligibility, coach/private-session, class history, and session visibility business rules.
- DataTable defaults/row actions, custom date/time controls, and all page markup.
- Migration history, legacy model/table terminology, generated Wayfinder source, and generated build artifacts.
- The broad Wayfinder declaration stub remains a documented candidate; removing it now would expand the existing TypeScript failure set without a generated declaration migration.

## Validation results

Dependencies were initially absent. `composer install` also rejected PHP 8.5.7-dev because locked PhpSpreadsheet supports PHP below 8.5; dependencies were installed locally with `--ignore-platform-req=php` only to execute diagnostics. Dependency/build folders and lock files were not committed.

| Check | Result | Classification |
|---|---|---|
| `php artisan optimize:clear` | Failed: MySQL `127.0.0.1:3306` connection refused while clearing database cache. | Environment/config issue. |
| `php artisan route:list --except-vendor` | Passed; 169 routes. | Pass. |
| `php artisan wayfinder:generate --with-form` | Passed; actions/routes regenerated with no tracked diff. | Pass. |
| `php artisan test` | 131 passed, 3 failed, 803 assertions. Failures: duplicate coach-attendance count; parent attendance group label (`Kelas Junior` vs `Junior`); QR unlinked-parent response expected 403 but got 302. | Pre-existing baseline/uncertain; docs-only cleanup cannot cause them. |
| `npm run build` | Passed. Vite emitted ignored `public/build` output only. | Pass. |
| `npx vue-tsc --noEmit` | Failed with 20 errors: missing generated route exports/form methods and missing Vite `ImportMeta.env/glob` types. | Pre-existing baseline; no TS source changed. |
| `./vendor/bin/pint --test` | Failed: 279 files checked, 76 style issues across existing controllers, migrations, seeders, routes, and tests. | Pre-existing baseline; broad autoformat was intentionally avoided. |

## Remaining risks and recommendations

1. Run the suite against the supported PHP version and a configured MySQL/MariaDB test database, then triage the three behavioral failures before refactoring access logic.
2. Repair Wayfinder generation/declaration integration and Vite ambient types; remove `wayfinder-route-stub.d.ts` only when `vue-tsc` passes without broad shims.
3. Introduce a validated display-ID normalizer for `ATT`, `SCA`, `SES`, and `ATHREG` one feature at a time, with exact generated route types.
4. Inventory consumers and secure the currently reachable legacy JSON CRUD API before consolidation.
5. Apply Pint in small module commits, excluding no source failures, so formatting diffs do not obscure behavior changes.
6. Add characterization tests around legacy `Session`/`TrainingSession`, `Group`/`TrainingGroup`, private coach selection, compatibility route aliases, and DataTable opt-in behavior before structural moves.

## Subsequent implementation

The coding-standards pass described in `CODING_STANDARDS_REFACTOR_REPORT.md` completed the TypeScript and Pint follow-ups: generated Wayfinder types now replace the broad declaration stub, Vite/Leaflet use official types, display route IDs share a validated helper, `vue-tsc` passes, and Pint passes all 279 files. A subsequent attendance correction made parent row visibility respect the active child and aligned two stale tests with documented private-session and linked-parent QR rules; all 134 tests now pass. The unavailable local MySQL cache remains an environment limitation for `optimize:clear`.
