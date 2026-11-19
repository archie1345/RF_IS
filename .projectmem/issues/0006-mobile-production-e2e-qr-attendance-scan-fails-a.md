# #0006 Mobile production E2E QR attendance scan fails after the browser workflow reaches the live scan scenario; failure context requires investigation.

- 2026-10-28T10:37:55Z `issue`: Mobile production E2E QR attendance scan fails after the browser workflow reaches the live scan scenario; failure context requires investigation. [e2e/tests/mobile-workflows.test.ts:523]
- 2026-10-28T10:52:22Z `attempt`: Full production E2E: large phone decodes the mocked camera QR and passes; small phone stays on /attendance until 30-second wait expires. (failed)
- 2026-10-28T10:58:28Z `attempt`: Production focused QR scan regression passed at 360×800 and 412×915 after the mocked canvas camera began publishing continuous frames. (worked)
- 2026-10-28T10:58:33Z `fix`: Mock camera stream now emits repeated deterministic QR frames; small and large mobile scan E2E tests pass. [e2e/tests/mobile-workflows.test.ts:576]
