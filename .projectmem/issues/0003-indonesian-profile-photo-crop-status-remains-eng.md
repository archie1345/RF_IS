# #0003 Indonesian profile photo crop status remains English because its source text lacks a translation entry.

- 2026-10-28T09:35:51Z `issue`: Indonesian profile photo crop status remains English because its source text lacks a translation entry. [resources/js/pages/profiles/ProfileDetailsPage.vue:562]
- 2026-10-28T09:36:02Z `attempt`: Production-mode mobile profile crop test expected the localized ready-to-save status, but rendered untranslated English. [e2e/tests/mobile-workflows.test.ts:660] (failed)
- 2026-10-28T10:53:57Z `attempt`: Fresh production-mode mobile crop regression passed at 360×800 and 412×915 with the Indonesian ready-to-save translation. (worked)
- 2026-10-28T10:54:01Z `fix`: Added missing Indonesian translation for the crop-ready status; mobile crop tests pass at both phone viewport sizes. [resources/js/composables/useI18n.ts:682]
