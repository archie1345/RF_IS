# #0017 Coach session creation does not scope submitted group to assignments

- 2026-10-28T20:31:13Z `issue`: Coach session creation does not scope submitted group to assignments [app/Actions/Sessions/CreateSession.php:21]
- 2026-10-28T20:54:23Z `fix`: Reject coach-created sessions for groups outside coach assignments; assigned-group and denial regression tests plus full isolated backend suite pass. [app/Actions/Sessions/CreateSession.php]
