# Code style and structure

These rules are the cleanup target for new work and incremental refactors. They favor analyzability, modularity, testability, and traceable change impact without requiring a rewrite.

## Backend conventions

- Follow PSR-12, Laravel naming, typed signatures, constructor injection, and Pint formatting.
- Keep controllers thin: authorize, delegate validation, invoke an action/service, and return a response. Resource endpoints use `index`, `show`, `store`, `update`, and `destroy`.
- Put reusable or complex validation and authorization in a `FormRequest`; never trust UI-only constraints.
- Put one transactional mutation/use case in an Action with `handle()`. Use a Service for reusable query rules or coordinated domain behavior without a single command shape.
- Put display transformation in a Presenter or resource-like helper. Presenters expose `row()` for one record and `rows()` for collections.
- Put dropdown/query providers behind `options()` or `selectOptions()` and return a documented stable shape.
- Put authorization in Policies. An explicit role check is acceptable at a boundary only when a policy cannot express it cleanly; reuse `User`/`RoleResolver` helpers rather than duplicating role strings.
- Centralize audit writes in `ActivityLogger`; log actor, action, subject, meaningful metadata, and outcome consistently.
- Keep visibility in dedicated query services. `PaymentVisibilityService`, `AttendanceVisibilityService`, `SessionVisibilityService`, and `ParentChildContextService` own payment visibility, attendance eligibility, session visibility, and linked-child access respectively. Do not reproduce these filters in controllers.
- Enforce private-session coach selection on the backend as well as the UI. Regular groups use assigned class/session coaches; private groups may expose the applicable selection flow.
- Wrap multi-record mutations in transactions within the action. Eager-load presenter inputs and avoid hidden N+1 queries.
- Preserve route names/URLs and migration history. Compatibility redirects are contracts until evidence and a deprecation plan permit removal.

### Choosing an abstraction

| Need | Create |
|---|---|
| Request parsing, validation, request authorization | FormRequest |
| One state-changing use case | Action with `handle()` |
| Reused domain/query/visibility capability | Service |
| Authorization decision for a model/resource | Policy |
| Stable view/table/API display shape | Presenter with `row()`/`rows()` |
| Shared constants and label/color rules | Typed domain support value/helper |

## Frontend conventions

- Inertia pages live in `resources/js/pages`. A page coordinates props, feature components, and navigation; it should not become a library of unrelated helpers.
- Shared reusable components live in `resources/js/components/shared`; form wrappers live in `resources/js/components/forms`; feature-only components live in `resources/js/features/<feature>/components`.
- Put `PageName.types.ts` beside a page. Put component-specific contracts in `ComponentName.types.ts`; cross-feature contracts belong in `resources/js/types`.
- Put page-only composables beside the page or inside its feature folder. Promote a composable to `resources/js/composables` only when independent features share it.
- Use Vue 3 Composition API with `<script setup lang="ts">`, typed props/emits, computed state instead of synchronized copies, and narrowly typed refs.
- Split large pages by coherent responsibility: reusable presentation to components, stateful reusable behavior to composables, and pure formatting/parsing to typed utilities.
- Use `DataTable`, `ResourceTablePanel`, `FormModal`, `FormInputField`, `FormSelectField`, and other form wrappers where their contracts fit. DataTable search, filters, pagination, and rows-per-page remain off unless explicitly enabled. Preserve multi-filter/multi-select and row-action behavior.
- Use shared alert/modal components instead of raw `alert()` or `confirm()`.
- Preserve custom date, time, and date-range controls; do not silently replace their parsing/UX with browser-native inputs.
- Do not use `any`, `@ts-ignore`, broad casts, or unused declarations to suppress errors. Narrow `unknown` at boundaries with validation/type guards.

## Import ordering

Separate groups with one blank line and sort consistently within a group:

1. Vue and Inertia framework imports.
2. Third-party packages.
3. Generated Wayfinder route/action helpers.
4. Layouts, feature/shared components, and composables.
5. Shared types and utilities.
6. Relative page-local modules.

Use `import type` for type-only imports. Prefer configured `@/` aliases across feature boundaries and relative imports for colocated files. Do not edit generated Wayfinder output to improve imports.

## Route helpers and ID normalization

- Use generated Wayfinder helpers rather than handwritten URLs. Fix the route/controller source and regenerate helpers; never patch `public/build`.
- Resolve `@/routes` directly to Wayfinder's generated TypeScript. Do not shadow it with ambient or path-mapped route declarations.
- Load Vite's official `vite/client` types through `tsconfig.json`; project declarations may augment environment keys but must not recreate `ImportMeta` or `import.meta.glob`.
- Pass the exact generated parameter object/primitive. Avoid a global permissive route shim.
- Rows may carry presentation IDs. Normalize only the expected prefix before calling a helper: `ATT-123` to `123`, `SCA-123` to `123`, `SES-123` to `123`, and `ATHREG-123` to `123`.
- A normalizer must accept `string | number`, verify the exact prefix, reject an empty or nonnumeric result when a numeric route key is required, and return the generated helper's expected type. It must not strip arbitrary letters or silently return `NaN`.
- Keep a one-off normalizer near its page. Promote it to a shared typed utility only after at least two equivalent call sites exist and tests/type checks cover both.
- Normalize at the navigation/mutation boundary, not when receiving props, so display IDs remain stable in tables and selection state.
- Use `routeId()` from `resources/js/lib/routeIds.ts` when a shared table display ID reaches a route boundary. It accepts only positive integer IDs and the documented `ATT`, `SCA`, `SES`, and `ATHREG` prefixes, returning `null` for invalid input.

## Naming

- Code identifiers are English; existing Indonesian UI labels may remain Indonesian.
- Prefer `TrainingClass` or `ClassGroup` for the class domain, `TrainingSession`, `AthleteAttendance`, `CoachAttendance`, and `Payment`/`Tuition`/`Invoice` for finance concepts.
- Avoid new `kelas` identifiers. Existing database tables, columns, model names, and route names are legacy compatibility contracts until a separately planned migration/deprecation.
- Vue files use PascalCase; composables use `useX`; booleans use `is`/`has`/`can`; event handlers describe the event/outcome (`handleProofSubmitted`).

## Documentation and review discipline

Every meaningful refactor records its module, entry points, behavior preserved, files moved/deleted, evidence for deletion, checks run, and remaining risks in `CLEANUP_REPORT.md`. Update the map/inventory when routes or ownership move. Prefer small commits organized by module, with characterization tests before security-sensitive access changes.
