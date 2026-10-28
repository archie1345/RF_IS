# #0012 CI import/order misclassifies @/routes when Wayfinder files are absent

- 2026-10-28T18:08:43Z `issue`: CI import/order misclassifies @/routes when Wayfinder files are absent [.github/workflows/lint.yml]
- 2026-10-28T18:08:49Z `attempt`: ESLint autofix without generated Wayfinder routes reordered 65 imports, but generated-route lint then rejected them. [resources/js] (failed)
- 2026-10-28T18:08:53Z `attempt`: Generate Wayfinder route types before frontend lint and type-check in both CI workflows. [.github/workflows/lint.yml] (worked)
- 2026-10-28T18:08:57Z `fix`: Both workflows generate Wayfinder aliases before lint/type-check; final lint reports 0 errors and both type-checks/build pass. [.github/workflows/lint.yml]
