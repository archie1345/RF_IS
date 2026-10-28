# #0001 Production audit E2E fails at TR-04 schedule update (404), ID-07 localized crop button selector timeout, FI-09 expects HTML despite PDF response, and RP-04 assumes English/Indonesian card label.

- 2026-10-28T08:27:50Z `issue`: Production audit E2E fails at TR-04 schedule update (404), ID-07 localized crop button selector timeout, FI-09 expects HTML despite PDF response, and RP-04 assumes English/Indonesian card label. [e2e/tests/role-workflows.test.ts:2713]
- 2026-10-28T08:30:41Z `attempt`: Exposed weekly schedule UUID for route binding; updated E2E selectors/assertions for Indonesian crop label, PDF bytes, locale card label, and mobile search pagination. (partial)
- 2026-10-28T08:42:07Z `attempt`: PDF byte assertion exposed invalid 1x1 PNG fixture: Dompdf omitted it from PDF output. Production image can render a normal GD-generated PNG. (failed)
- 2026-10-28T08:53:25Z `attempt`: Rebuilt rf-is:production-audit from current worktree; reran TR-04, ID-07, FI-09, RP-04 individually and selected desktop/mobile regression group. (worked)
- 2026-10-28T09:00:21Z `attempt`: Mobile ID-04 invitation acceptance test failed because it calls undefined logout(page) helper. [e2e/tests/mobile-workflows.test.ts:775] (failed)
- 2026-10-28T09:04:27Z `fix`: Added mobile logout helper used by ID-04 invitation acceptance flow; both phone viewport tests passed. [e2e/tests/mobile-workflows.test.ts:40]
- 2026-10-28T10:31:08Z `attempt`: Full production E2E exposed ID-07 asserting English crop-ready text on an Indonesian profile; browser rendered Foto 3×4 siap disimpan. (failed)
- 2026-10-28T10:58:11Z `attempt`: Fresh production audit passed TR-04, FI-09, RP-04, and the admin ID-07 avatar workflow after the Indonesian status assertion was corrected. (worked)
- 2026-10-28T10:58:16Z `fix`: TR-04, ID-07, FI-09, and RP-04 production E2E flows pass with UUID schedule keys, Indonesian labels, PDF byte checks, and locale-aware card assertions. [e2e/tests/role-workflows.test.ts:2631]
