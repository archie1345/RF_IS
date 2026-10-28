# #0010 Mobile connection-toast test races exit and enter transitions; getByRole status sees two toasts

- 2026-10-28T14:51:03Z `issue`: Mobile connection-toast test races exit and enter transitions; getByRole status sees two toasts [e2e/tests/mobile-workflows.test.ts:524]
- 2026-10-28T14:55:05Z `attempt`: Wait for dismissed offline status to leave before toggling online; focused production mobile-small toast test passed (1/1). [e2e/tests/mobile-workflows.test.ts:524] (worked)
- 2026-10-28T14:55:05Z `fix`: Toast test now waits for exit transition; focused production test passed. [e2e/tests/mobile-workflows.test.ts:524]
