# #0005 Mobile production E2E billing-rule and invoice-dialog workflow fails at phone viewport; failure context requires investigation.

- 2026-10-28T10:36:00Z `issue`: Mobile production E2E billing-rule and invoice-dialog workflow fails at phone viewport; failure context requires investigation. [e2e/tests/mobile-workflows.test.ts:188]
- 2026-10-28T10:52:18Z `attempt`: Full production E2E: small viewport one-time billing submission redirects but dialog remains; large viewport passes. Investigate response handling and generation timing. (failed)
- 2026-10-28T10:58:20Z `attempt`: Production focused rerun passed the billing dialog workflow on 360×800 and 412×915 after synchronizing with the Inertia GET response. (worked)
- 2026-10-28T10:58:24Z `fix`: Mobile billing workflow now waits for the server Inertia response before asserting the invoice dialog closes; both phone sizes pass. [e2e/tests/mobile-workflows.test.ts:231]
