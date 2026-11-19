# #0020 Legacy public file disks may expose historical uploads by static path

- 2026-10-28T20:31:13Z `issue`: Legacy public file disks may expose historical uploads by static path [database/migrations/2026_07_24_000002_add_disk_to_user_files_table.php:11]
- 2026-10-28T20:51:35Z `attempt`: Confirmed legacy exposure path: public storage symlink serves user-files paths directly; Apache rewrite lacks a deny rule. Added a failing config regression test; production rows remain unverified. [public/.htaccess] (partial)
- 2026-10-28T20:51:57Z `attempt`: Initial legacy-file test expected HTTP 401, but web middleware correctly redirects unauthenticated browser users to login (302). Update assertion to route contract. [tests/Feature/UserFilePrivacyTest.php:74] (failed)
- 2026-10-28T20:54:34Z `fix`: Deny direct Apache requests to /storage/user-files while authenticated UserFileController downloads remain available; config regression passes. Production file inventory remains unverified. [public/.htaccess]
