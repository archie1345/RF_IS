# #0011 Mobile login helper page.goto to locale redirect aborts dashboard navigation and triggers false failed-request diagnostics

- 2026-10-28T14:51:05Z `issue`: Mobile login helper page.goto to locale redirect aborts dashboard navigation and triggers false failed-request diagnostics [e2e/tests/mobile-workflows.test.ts:43]
- 2026-10-28T14:55:05Z `attempt`: Set mobile test locale through context API request and reload authenticated page; schedule and admin-flow focused tests each passed (1/1). [e2e/tests/mobile-workflows.test.ts:43] (worked)
- 2026-10-28T14:55:05Z `fix`: Mobile setup no longer races page navigation with locale redirect; both focused production tests passed. [e2e/tests/mobile-workflows.test.ts:43]
