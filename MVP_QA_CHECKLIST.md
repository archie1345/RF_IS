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
