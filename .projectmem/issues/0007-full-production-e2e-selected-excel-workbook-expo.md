# #0007 Full production E2E selected Excel workbook export fails after admin export request; failure context requires investigation.

- 2026-10-28T11:10:59Z `issue`: Full production E2E selected Excel workbook export fails after admin export request; failure context requires investigation. [e2e/tests/role-workflows.test.ts:1263]
- 2026-10-28T11:38:39Z `attempt`: Full production E2E exposed admin Excel-export login mismatch and a 30-second timeout in the admin profile-scope workflow; captured trace and screenshots, investigating shared seeded state. (failed)
- 2026-10-28T14:03:54Z `attempt`: Excel export login redirect did not reproduce: focused production rerun and subsequent full-run case passed. Root cause remains unknown; later full suite was interrupted. [e2e/tests/role-workflows.test.ts:1263] (partial)
- 2026-10-28T15:28:07Z `attempt`: Final guarded production run passed selected Excel workbook export and direct-download authorization (3.6s). [e2e/tests/role-workflows.test.ts:1263] (worked)
- 2026-10-28T15:28:07Z `fix`: Excel workbook download and non-admin denial passed in the final complete production E2E run. [e2e/tests/role-workflows.test.ts:1263]
