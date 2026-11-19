# 0023 Physical camera hardware unavailable for end-to-end test

- Blocker: No webcam/Android device available in local environment
- Workaround: Mocked camera (`e2e/tests/mobile-workflows.test.ts:576`) passes E2E but does not verify physical access
- Decision needed: Proceed without physical verification or wait for hardware access

## Current Status
- `COMPLETE_FUNCTIONAL_TEST_MATRIX.md` → BLOCKED for MOB-03, MOB-04 (physical camera)
- AT-06 still FIXED but browser tests use mocked media
- No automated physical camera test is possible without actual hardware

## Required Actions
1. Acquire webcam or Android device with camera access
2. Re-run Playwright camera workflows with real hardware (`e2e/tests/mobile-workflows.test.ts:576`)
3. Verify physical camera input capture and submission (not mocked)
4. Test camera permission flow: accept/deny/recover
5. If hardware unavailable, mark all camera-related rows as BLOCKED indefinitely

## Evidence Required
- Screenshot or video of desktop webcam capture workflow
- Physical device QR scan attendance test
- Browser `navigator.mediaDevices.getUserMedia()` API behavior on real hardware
- Permission denial flow test with actual denial (not mocked)

