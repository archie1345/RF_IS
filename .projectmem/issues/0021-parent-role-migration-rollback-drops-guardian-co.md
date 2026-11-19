# #0021 Parent-role migration rollback drops guardian contact fields

- 2026-10-28T20:31:13Z `issue`: Parent-role migration rollback drops guardian contact fields [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php:64]
- 2026-10-28T20:53:06Z `attempt`: Isolated SQLite rollback regression failed: migration down() drops guardian_phone and the contact value. No persistent database was touched. [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php:63] (failed)
- 2026-10-28T20:54:37Z `fix`: Keep guardian columns during rollback because parent schema cannot restore their values; isolated SQLite regression and full backend suite pass. [database/migrations/2026_10_20_000001_remove_parent_role_add_athlete_guardian_fields.php]
