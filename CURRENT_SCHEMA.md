# Schema: Current Database Documentation

This document reflects the current Laravel migrations and Eloquent models in this repository. It is intentionally based on `database/migrations` and `app/Models`, not on older SQL dumps.

## Core accounts and roles

- `users`: login account data (`id`, `name`, `email`, password/session fields, `role`, phone/gender/date/profile basics, soft deletes, `account_status`). Current code treats `role` as a primary role and also supports multi-role assignments.
- `user_role_assignments`: normalized account roles keyed by `user_id` and `role`; `User::roleAssignments()` and `User::assignedRoles()` are the current role convention.
- `user_profiles`: normalized optional profile details such as bio/profile-picture metadata.
- `activity_logs`: audit rows for admin/account/payment/attendance actions when `ActivityLogger` is called.
- `user_invitations`: invitation tokens and expiry state for invited accounts.

## People profiles

- `athletes`: athlete profile rows keyed by `athlete_id`, linked to `users.id` through `id`. Important fields include `branch_id`, `group_id`, `parent_id`, gender, birth date, height/weight, address, geup, and sensitive identifier hashes/ciphertext for NIK/BPJS.
- `parents`: parent profile rows keyed by `parent_id`, linked to `users.id` through `id`. Athletes link to parents through `athletes.parent_id`.
- `coaches`: coach profile rows keyed by `coach_id`, linked to `users.id` through `id`, with status/license/specialization metadata.
- `branches`: branch/ranting records keyed by `branch_id`.
- `groups`: training group/class records keyed by `group_id`.

## Files and profile evidence

- `user_files`: reusable file metadata linked to users and optionally to certification/achievement records.
- `user_certifications`: certification records linked to users, with optional `user_file_id`.
- `user_achievements`: achievement records linked to users, with optional `user_file_id`.

## Bills, invoices, and transactions

- `payments`: current bill/invoice table keyed by `payment_id`. The app uses this table for issued bills/invoices and payout records, with `bill_kind`, `payment_type`, `total_amount`, `paid_amount`, `remaining_amount`, `status`, `proof_path`, `proof_status`, and `proof_notes`.
- `payment_transactions`: approved installment/transaction history keyed by `ptid`, linked by `payment_id`. Rows store `amount`, verifier (`verified_by`), transaction date, method, notes, and proof snapshot fields (`proof_path`, `proof_notes`). Current MVP payment behavior is internal/manual bill proof review: members upload proof, admins approve full or partial amounts, and approved installments are tracked here. Full Midtrans gateway and webhook integration are postponed.
- `invoice_templates`: admin-managed invoice header/footer/payment-instruction settings.

## Attendance and sessions

- `training_sessions`: session records keyed by `training_session_id`, including `session_date`, `start_time`, `end_time`, branch/group links, QR token/window columns, and lifecycle status.
- `attendances`: athlete attendance rows linked to sessions and athletes; uniqueness/indexing is used to prevent duplicate session/athlete attendance.
- `session_coach_attendances`: coach attendance rows for session attendance sheets.
- `coach_session_coaches`: pivot table linking coaches to training sessions.

## Championships and results

- `events`: championship/event definitions keyed by `event_id`, with dates, location, registration metadata, and optional map link.
- `event_registrations`: athlete championship registrations keyed by `registration_id`, linked to events/athletes and optionally to payment records through current controller workflow.
- `event_coach_registrations`: coach-event registration rows.
- `results`: result records for competition outcomes where present.
- `licenses` and `registrations`: legacy/supporting domain tables still represented by models and migrations.

## Import/export convention

CSV import/export is implemented in `AdminController` for current entities such as athletes, payments, sessions, attendance, events, and event registrations. Sensitive identifiers must remain admin-only and should be omitted or deliberately controlled in exported files.

## Known mismatch with older SQL

Older SQL snapshots may use different names or omit current fields. Current application code relies on Laravel migrations/model conventions such as `athlete_id`, `branch_id`, `group_id`, `roleAssignments`, `user_files`, `proof_path`, bill/invoice amount columns, and `payment_transactions` proof snapshots. WhatsApp notification/API/template/scheduling work is also postponed and is not represented as a production integration in the current schema.
