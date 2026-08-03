# Cleanup candidates and evidence log

No file was deleted during this pass. Search-only absence is insufficient proof; each candidate below records framework and compatibility considerations.

| Candidate | Why suspicious | Evidence checked | Classification / next step |
|---|---|---|---|
| `Http/Controllers/Api/*` and explicit API CRUD routes | Parallel implementations exist beside browser controllers. | `routes/api.php` references every controller; `bootstrap/app.php` loads API routes. | **Active/uncertain consumers; retain.** Inventory clients and add authentication before consolidation. |
| `Models/Session.php` and `Models/TrainingSession.php` | Two names suggest a pre-/post-rename domain. | PHP references, relationships, migration `2026_07_02_000001_rename_attendance_training_session_domain.php`, controllers/actions/tests. | **Compatibility risk; retain.** Establish canonical model and deprecation plan before removal. |
| `Models/Group.php` and `Models/TrainingGroup.php` | Overlapping group terminology. | Admin routes/controllers, training-class payloads, model relations, migrations, frontend pages. | **Both active domains; retain.** Document legacy ClassGroup versus training grouping semantics. |
| Root `UserAchievementController` and profile controller alias | Similar controller names. | `routes/web.php` uses root controller for achievement page and aliased profile controller for user-scoped mutations. | **Distinct active responsibilities; retain.** Namespacing is intentional. |
| `ParentChildProfileController` versus `ProfileAccessController` | Overlapping profile responsibilities. | User password route, user show/profile routes, parent-child tests. | **Active; responsibility could be clearer.** Move password mutation to a dedicated controller only with route/test parity. |
| `AdminFeaturePage.vue` | Generic page can conceal unfinished features. | `BaseAdminFeatureController` dynamically renders it; admin feature controllers extend/use the base. | **Active shared read-model page; retain.** |
| `ErrorPage.vue` | No literal `Inertia::render` reference. | Inertia exception handling/bootstrap dynamic resolution and recursive page resolver. | **Framework-discovered; retain.** |
| `resources/js/types/wayfinder-route-stub.d.ts` | Declares broad `unknown` parameters and many generic names, weakening generated helper contracts. | TypeScript config/module resolution, imports from generated route modules, Wayfinder generation workflow. | **Active technical-debt candidate; retain for now.** Remove only after generated declarations pass `vue-tsc`; prefer exact route parameter types and normalized IDs. |
| Duplicated row-ID prefix parsing in attendance/session/championship pages | Repeated `ATT-`, `SCA-`, `SES-`, `ATHREG-` string replacement can drift. | Page call sites and generated helper parameter expectations. | **Safe future extraction.** Introduce a typed `normalizeDisplayId(value, prefix)` returning validated string/number, page by page with type checks. |
| Repeated date formatting in admin pages/dashboard | Similar names found in several files. | Implementations and call-site data shapes. | **Not proven equivalent.** Dashboard formats day numbers while admin pages format `Date`; do not merge blindly. |
| Compatibility redirects (`admin.payments`, finance, schedules, events schedule) | Routes render no unique page. | Named routes, sidebar/bookmark compatibility, `LegacyFinanceRedirectTest`. | **Deprecated but used contract; retain.** |
| Duplicate invitation POST URI with `invitations.store` and `invitations.accept` names | Two identical method/URI registrations. | Route file, generated route names, invitation tests and potential external links. | **Compatibility alias; retain.** Confirm Wayfinder and route-cache behavior before restructuring. |
| Storage/bootstrap cache `.gitignore` files | Located inside excluded runtime folders. | `git ls-files`; only placeholder `.gitignore` files are tracked, no runtime/build output. | **Expected Laravel scaffolding; retain.** |

## Proof required before deletion

For a proposed file, record: routes and route-cache behavior; Inertia literal and dynamic resolution; Vue imports/globs; PHP references and container bindings; model relations/casts/events; policy/provider registration; console scheduling; tests; generated Wayfinder dependencies; and external/public compatibility. A deletion should include a focused test and an entry in `CLEANUP_REPORT.md`.

## Safe follow-up sequence

1. Capture a clean baseline for all required checks and database-backed tests.
2. Replace the Wayfinder stub with generated exact declarations without editing generated output by hand.
3. Extract prefixed display-ID normalization and migrate one feature at a time.
4. Add characterization tests for API consumers and legacy model aliases.
5. Only then consider deprecations, redirects, or file removals.
