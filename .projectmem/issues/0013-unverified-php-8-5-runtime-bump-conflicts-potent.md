# #0013 Unverified PHP 8.5 runtime bump conflicts potentially with locked Composer package constraints

- 2026-10-28T20:19:35Z `issue`: Unverified PHP 8.5 runtime bump conflicts potentially with locked Composer package constraints [Dockerfile.prod:19]
- 2026-10-28T20:31:16Z `attempt`: Changed all dirty PHP 8.5.12 image and CI targets to PHP 8.4.27 because locked PhpSpreadsheet 1.30.7 requires PHP <8.5; Composer confirms 8.4.27 is allowed. Docker build remains unverified because Docker engine is unavailable. (partial)
- 2026-10-28T21:04:29Z `fix`: Confirmed PHP container and CI versions are consistently 8.4.27; no PHP 8.5 image tag remains in Dockerfiles or workflows.
