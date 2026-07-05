# Docs: Runbook and QA Checklist

Use this checklist before demo/deploy. Task names below use domain names first; spreadsheet IDs are secondary references only.

## Admin

- Log in as an admin and confirm `/admin`, `/users`, `/payments`, `/attendance`, `/sessions`, and `/championships` load.
- Manage athletes: search the athlete table, filter by branch/ranting and group/class, open an athlete profile, and save safe profile edits.
- Manage parents: search parent records and link/unlink children using the Link Children modal.
- Manage coaches: search coach records and update coach profile metadata.
- Verify bills: open Payments, filter submitted proofs, review a receipt, approve a partial amount, and confirm remaining balance.
- Confirm Transaction History shows approved installments with amount, date, verifier, method, and notes/proof when available.
- Filter payments by bill status, proof queue, bill kind, and bill category.
- Export/import: download CSV templates/exports for supported entities and verify sensitive identifiers are not exposed unexpectedly.
- Attendance: create/update sessions, open attendance sheets, bulk update athlete attendance, and verify duplicate rows are not created.
- Attendance QR: generate QR only within session start/end, reject invalid windows, scan as an eligible user, and confirm no row is created on validation failure.
- Championships: register an athlete, confirm the related bill/invoice is identifiable, and settle/update payment consistently.

## Parent

- Log in as a parent and confirm only linked child records are visible.
- Confirm allowed sensitive child identifiers are shown only for linked children.
- Upload payment proof for an open bill where supported.
- Confirm bill status, paid amount, remaining amount, and approved transaction history are understandable.

## Athlete

- Log in as an athlete and view own profile.
- Review available attendance, payment/bill, and championship information.
- Confirm athlete cannot access admin-only routes or unrelated sensitive identifiers.

## Coach

- Log in as a coach and view assigned sessions/attendance where available.
- Confirm coach cannot access admin-only account/branch/group destructive routes.
- Confirm coach cannot view NIK/BPJS fields unless explicitly authorized by policy.

## Demo script

1. Admin logs in and opens the user directory.
2. Admin checks/searches an athlete and links a parent-child relationship.
3. Admin creates or opens a member bill.
4. Member uploads the first payment proof.
5. Admin partially approves the proof and confirms the remaining amount.
6. Member uploads the second proof.
7. Admin approves completion and shows transaction history.
8. Admin marks attendance and demonstrates QR validation rejecting invalid windows.
9. Admin checks CSV export/import templates where available.

## Postponed integrations

- Current payments are internal/manual bill proof review with partial approval/installment tracking.
- Full Midtrans gateway integration remains postponed.
- Midtrans webhook integration remains postponed.
- WhatsApp notification/API/template/scheduling remains postponed.

## Fresh setup smoke test

- From a clean checkout, run `composer install`, copy `.env.example` to `.env`, generate `APP_KEY`, configure the database, run `php artisan migrate --seed`, run `php artisan storage:link`, install npm packages, build assets, and start `php artisan serve`.
- Confirm seeded demo accounts can log in locally only; never use demo credentials in production.
- Confirm uploaded proof/certification/achievement files are visible through the public storage symlink.
- Confirm `APP_DEBUG=false`, production `APP_URL`, queue/cache/session/mail drivers, and storage permissions before deployment.

## Mobile QR Attendance Regression Checklist

1. Log in as an admin or authorized coach.
2. Open a training session and then open its attendance sheet.
3. Refresh the attendance sheet and confirm no duplicate attendance error appears.
4. Generate a QR window inside the session start/end time.
5. Scan the QR using a phone camera and open it in the phone browser.
6. Log in as the athlete if prompted.
7. Confirm the session summary is readable in portrait mode.
8. Tap **Check in now** and confirm the success message appears.
9. Confirm a **Done / Back to dashboard** safe-exit action is visible.
10. Scan or submit the same QR again and confirm the already-recorded message appears without duplicate rows.
11. Close/revoke the QR and scan again to confirm the invalid/closed state has a safe exit.
12. Try an invalid QR window outside the session time and confirm field-level validation errors appear.

## Business Flow QA Checklist

- **Admin:** sessions -> attendance sheet -> QR generation -> monitor attendance -> adjust manual exceptions -> return to sessions.
- **Coach:** assigned session -> attendance sheet -> QR/check-ins -> mark exceptions -> review session status.
- **Athlete:** scan QR -> log in if needed -> confirm session -> check in -> success or already-recorded state -> done/back.
- **Parent:** linked child -> bills/status -> upload proof if needed -> review paid/remaining amount and transaction history.
- **Payments:** bill -> proof upload -> admin review -> partial/full approval -> transaction history -> completion when remaining amount reaches zero.
- **CSV:** download template -> fill CSV -> import as admin -> review result -> export data if needed.

## Safe-Exit QA Checklist

- Forms provide **Cancel**, **Back**, or reset behavior before committing changes.
- Modals can close without saving.
- Filter-heavy pages provide reset/clear behavior where useful.
- Upload fields allow selected files to be replaced or cleared before submit.
- QR scan success/error states provide **Done / Back to dashboard**.
- Attendance sheet provides **Back to sessions**.
- QR generation provides **Reset window**, **Close QR** with confirmation, and return-to-attendance actions.
- Payment upload/review modals provide cancel/close actions.
- CSV import remains admin-only and should be canceled before upload if the operator is unsure.
- Destructive actions such as QR close, coach-row deletion, and bulk attendance changes require confirmation.
