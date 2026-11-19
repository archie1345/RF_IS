# #0004 Mobile connectivity E2E marks late logo, avatar, and remote-font requests as failures after toggling offline.

- 2026-10-28T09:58:59Z `issue`: Mobile connectivity E2E marks late logo, avatar, and remote-font requests as failures after toggling offline. [e2e/tests/mobile-workflows.test.ts:499]
- 2026-10-28T09:59:05Z `attempt`: Production mobile-large connection-toast test failed because image and Bunny Fonts requests remained in flight when the test toggled offline. [e2e/tests/mobile-workflows.test.ts:499] (failed)
- 2026-10-28T10:54:05Z `attempt`: Full production mobile suite passed the offline connection-toast workflow on both 360×800 and 412×915 after asset decoding waits. (worked)
- 2026-10-28T10:54:10Z `fix`: Waited for document fonts and visible images before switching browser offline; both mobile connection-toast tests pass. [e2e/tests/mobile-workflows.test.ts:502]
