# MVP Release Notes

## Latest verified commit

- Latest verified commit: `17f085ec9827`.

## How to run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit DB_* values in .env
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

For frontend development, run `npm run dev`. The Vite Wayfinder plugin regenerates frontend action/route files during build/dev commands.

## Demo accounts

All demo credentials are local/demo-only and must not be used in production.

| Role    | Email               | Password   |
| ------- | ------------------- | ---------- |
| Admin   | `admin@rfis.test`   | `12345678` |
| Coach   | `coach@rfis.test`   | `12345678` |
| Parent  | `parent@rfis.test`  | `12345678` |
| Athlete | `athlete@rfis.test` | `12345678` |

Seed data is synthetic and includes a branch/ranting, group/class, linked parent-child relationship, athlete profile, coach profile, completed bill, partially paid bill with one approved installment, championship registration bill, and attendance/session data.

## Key ready features

- User directory with athlete search and branch/group/status filters.
- Parent-child linking and parent linked-child context.
- Manual/internal bill and invoice proof review.
- Partial payment approval with approved installments in Transaction History.
- Payment filters for proof queue, bill status, bill kind, and bill category.
- Attendance sheets, bulk attendance updates, coach attendance, and QR attendance windows constrained to session time.
- Profile certification/achievement file upload UI using reusable file fields.
- Admin CSV template/export/import controls with backend admin authorization.
- Championship registration creates/links a manual bill where supported by the current controller flow.

## Current payment scope

Payments are manual/internal bill proof review only:

- bills/invoices are created inside the app;
- members upload payment proof;
- admins approve or reject proof;
- admins may approve partial amounts;
- approved installments appear in Transaction History;
- remaining amount is recalculated by the backend;
- bills become paid only after the approved total reaches the bill total.

## Postponed integrations

- Full Midtrans gateway integration is postponed.
- Midtrans webhook integration is postponed.
- WhatsApp notification/API/template/scheduling is postponed.

## Verification commands

```bash
php artisan route:list
php artisan migrate:fresh --seed --env=testing
php artisan test
npm run lint
npm run build
git status --short --branch
```

`npm run test` and `npm run typecheck` are not currently defined in `package.json`.

## Manual QA checklist

Use [`MVP_QA_CHECKLIST.md`](MVP_QA_CHECKLIST.md) before demo/deploy.

## Known limitations and deployment risks

- Tables use client-side filtering/search over already-loaded rows for the MVP.
- Payment proof upload uses a simple file field and backend validation; no drag-and-drop uploader is included.
- QR codes show the plaintext scan URL only immediately after generation; regenerate if the display token is needed again.
- CSV import remains a basic admin-only upload flow without advanced column mapping.
- Production deployments must configure real secrets, database credentials, queue/cache/session drivers, mail delivery, writable storage/cache paths, and public upload storage.

## Final System Flow Readiness Notes

- Attendance sheet loading is now idempotent: opening or refreshing `/sessions/{session}/attendance` creates only missing rows, reuses existing rows, and preserves existing `PRESENT`, `ABSENT`, `LATE`, or `EXCUSED` statuses.
- QR attendance check-in is duplicate-safe: a pre-created default `ABSENT` row is updated to `PRESENT`, repeated scans return an already-recorded state, and no duplicate attendance rows are created.
- QR scanning is mobile-first: the scan landing page presents a compact session summary, clear status card, full-width check-in action, and **Done / Back to dashboard** safe exit.
- Attendance operations now emphasize the business flow: confirm session, generate QR, let athletes scan, monitor rows, and adjust exceptions.
- Midtrans gateway/webhooks and WhatsApp notification/API/template/scheduling remain postponed. Payments continue to use the manual/internal bill proof review flow.
