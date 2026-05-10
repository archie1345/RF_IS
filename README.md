# RF IS - Training & Operations Management

RF IS is a Laravel + Inertia + Vue application for managing:
- Athlete records
- Parent-child links
- Coach sessions and attendance
- Payments/invoices
- Championship registrations
- Bulk data migration (CSV import/export)

## 1. Quick Start

### Requirements
- PHP 8.2+
- Composer
- Node.js 20+ and npm
- MySQL/MariaDB (or your configured DB)

### Install
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set database credentials in `.env`, then run:
```bash
php artisan migrate
npm install
npm run dev
php artisan serve
```

Open the app at `http://127.0.0.1:8000`.

## 2. Core Navigation

After login, use the sidebar to access:
- `Athletes`
- `Payments`
- `Attendance`
- `Championships`
- `Sessions`
- `Admin Panel`

Breadcrumbs at the top show your current location and allow quick back-navigation.

## 3. Daily Flow (End-to-End)

### A. Create athlete records
1. Open `Athletes`.
2. Click `New athlete`.
3. Fill identity, group, branch, geup, height, weight, and parent link fields.
4. Save.

### B. Parent-child context
1. Open `Parent > Switch Child`.
2. Pick a child profile.
3. Parent views will now be filtered by the selected child.

### C. Record payments
1. Open `Payments`.
2. Click `Create invoice`.
3. Select athlete, payment type, amounts, and date.
4. Submit to create the invoice row.

### D. Export invoice PDF
1. Open `Payments`.
2. In the payment row, click `PDF`.
3. File downloads as invoice PDF (or HTML fallback if PDF package is not installed).

## 4. CSV Import/Export (Legacy Data Migration)

All bulk transfer features are in `Admin Panel > Legacy Data Transfer`.

### Supported datasets
- `athletes`
- `payments`
- `sessions`
- `attendance`
- `events`
- `event_registrations`

### Export current DB data
1. Choose dataset.
2. Click `Export current data`.
3. Save the downloaded CSV.

### Download import template
1. Choose dataset.
2. Click `Download template`.
3. Fill the template in Excel/Google Sheets.

### Import CSV
1. Choose dataset.
2. Upload `.csv`.
3. Click `Import CSV`.
4. Review import summary (`Imported`, `Failed`, and row-level errors).

### Import notes
- Keep header names exactly the same as template.
- Use UTF-8 CSV.
- Date fields should be valid date format (`YYYY-MM-DD` preferred).
- IDs referencing other tables (ex: `athlete_id`, `branch_id`) must exist.

## 5. Invoice Template Customization

Invoice template is configurable in:
- `Admin Panel > Invoice Template Settings`

Editable fields:
- Company name
- Address
- Phone
- Email
- Logo URL
- Header text
- Footer text
- Default payment notes

These values are used by payment invoice exports.

## 6. PDF Engine Setup (Important)

Invoice export supports PDF using `barryvdh/laravel-dompdf`.

Install:
```bash
composer require barryvdh/laravel-dompdf
```

If not installed, invoice export returns an HTML fallback so operations can continue.

## 7. Roles

- `admin`: full management and data transfer
- `coach`: session and attendance operations
- `parent`: child-focused visibility and navigation
- `athlete`: personal attendance/event/payment visibility

## 8. Troubleshooting

- UI not updating: run `npm run dev` and hard refresh browser.
- Migration missing table: run `php artisan migrate`.
- PDF not downloading: ensure `barryvdh/laravel-dompdf` is installed.
- Import failures: verify CSV headers and foreign-key IDs.

## 9. Suggested Production Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`
- Configure queue, cache, and session drivers
- Use HTTPS and secure cookies
- Restrict admin routes by role middleware/policies
- Add regular DB backups before bulk imports
