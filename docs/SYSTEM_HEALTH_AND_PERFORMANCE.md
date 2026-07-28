# RF IS System Health and Performance Check

This document records the system-wide review performed before the next landing-page redesign. It covers the public entry flow, authentication, Laravel request handling, database access, frontend delivery, production containers, and automated validation.

## Changes applied during the review

### Public account flow

- Public self-registration is disabled in Fortify.
- New visitors are directed to WhatsApp so an administrator can create the account.
- The login page includes a back-to-home action and a WhatsApp contact action.
- The landing page includes an optional WhatsApp bubble in the bottom-right corner.
- The administrator controls the public WhatsApp number and bubble visibility from **Template & Kontak WhatsApp**.

### Backend request performance

- Public contact settings are resolved through `PublicContactSettings`.
- The WhatsApp number and bubble state are loaded in one database query.
- The result is cached for one hour and invalidated immediately whenever either setting changes.
- The service is a container singleton, preventing repeated cache reads during the same request.
- Inertia shared props reuse the cached service instead of querying `message_templates` on every page response.

### Admin account-page query

The admin account page still returns the complete account list because the current UI performs local search, filtering, and pagination. Its eager-loading payload has been reduced to only the columns required by the page:

- basic account identity and lifecycle fields;
- assigned role names;
- branch identifiers and branch names;
- the minimum coach and parent relationship identifiers.

This avoids loading sensitive athlete identifiers, profile biographies, and unrelated model columns for the account table.

For installations with thousands of users, server-side pagination and search should replace the current full-list response. That is a separate API and UI contract change and should be benchmarked against the expected production data volume.

### Frontend delivery

- Inertia pages are already loaded through a non-eager `import.meta.glob`, so page components remain code-split.
- The WhatsApp URL formatter is shared between the login and landing pages instead of duplicating normalization logic.
- The new floating action uses only the existing Lucide dependency and does not introduce another frontend package.
- The production frontend is still validated by formatting, ESLint, TypeScript, and the Vite production build.

### Production container

The production Apache/PHP image now enables:

- PHP OPcache;
- PHP realpath caching;
- HTTP keep-alive;
- gzip/deflate compression for HTML, CSS, JavaScript, JSON, XML, and SVG;
- one-year immutable caching for hashed Vite assets under `/build/assets/`;
- seven-day caching for uploaded public storage files;
- cross-origin access for webfont files.

HTML responses are not given long-lived cache headers, so authentication and Inertia page data are not cached by the browser as static content.

### Automated system checks

The GitHub Actions workflow now executes:

1. Composer installation with an optimized autoloader.
2. npm clean installation.
3. Laravel configuration caching.
4. Application route discovery.
5. Blade view compilation.
6. Frontend formatting checks.
7. ESLint checks.
8. Vue TypeScript checks.
9. Vite production build.
10. PHP formatting checks.
11. The complete Laravel test suite.

## Required local validation

Run these commands after pulling the branch:

```powershell
git pull origin feature/group-management

docker compose exec server php artisan optimize:clear
docker compose exec server php artisan migrate

docker compose exec server php artisan test tests/Feature/PublicWhatsAppContactTest.php
docker compose exec server php artisan test tests/Feature/Auth/RegistrationTest.php
docker compose exec server php artisan test tests/Feature/InvoiceTemplateBrandingTest.php
docker compose exec server php artisan test

docker compose exec vite npm run quality
```

Because the Dockerfile changed, rebuild the production-style server image before validating response compression and cache headers:

```powershell
docker compose build --no-cache server
docker compose up -d server
```

Then verify the production optimizations:

```powershell
docker compose exec server php -i | Select-String "opcache.enable|realpath_cache"
docker compose exec server apache2ctl -M
```

The Apache module output should include `deflate_module`, `headers_module`, `expires_module`, and `rewrite_module`.

## Production deployment sequence

After deploying tested code:

```powershell
docker compose exec server php artisan migrate --force
docker compose exec server php artisan storage:link
docker compose exec server php artisan optimize
```

Use `php artisan optimize:clear` before `php artisan optimize` when configuration or route behavior appears stale.

## Performance verification that still requires runtime data

Static review and automated checks cannot prove production latency under real traffic. Before final deployment, measure these with representative data:

- dashboard query count and response time for every role combination;
- admin account page with the expected maximum number of users;
- attendance tables with a full season of records;
- payment and ledger pages with multiple years of transactions;
- championship participant pages at maximum event capacity;
- invoice PDF generation time and memory consumption;
- Vite asset transfer size over the actual production network;
- database slow-query log during concurrent attendance and payment activity.

No performance claim should be treated as a benchmark result until these measurements are run against production-sized data.
