### Development stack

Start the development Compose project with:

```bash
docker compose -f compose.dev.yaml up -d --build
```

The web app is available at `http://localhost:9200`; Vite is on port `9201`, phpMyAdmin on `9202`, MySQL on `9203`, and Mailpit on `9204` (SMTP `1025`). The app and Vite ports bind to loopback by default; database and development tools are loopback-only. Vite installs from `package-lock.json` with `npm ci` so startup does not rewrite the lockfile.

Starting or recreating the app container does not apply migrations. After reviewing the pending changes and confirming a verified backup, apply migrations explicitly with `docker compose -f compose.dev.yaml exec server php artisan migrate --force`. This project uses a named MySQL volume; do not use `docker compose down -v` for routine cleanup.

See [Development database backup and recovery](docs/DATABASE_BACKUP_AND_RECOVERY.md) for a consistent logical backup procedure and isolated restore verification steps.

### Browser end-to-end tests

Playwright is isolated under `e2e/`; it does not add dependencies to Laravel or the root frontend. Install the locked E2E dependencies and the headed Chromium binary, then run the browser suite from the repository root:

```bash
npm ci --prefix e2e
npm --prefix e2e run install:browsers
npm run test:e2e
```

The command builds an isolated app image and starts it on `127.0.0.1:9205` on a dedicated Docker bridge with no MySQL or other service attached and outbound masquerading disabled. It uses SQLite `:memory:` and array sessions. The runner removes only its temporary container and network. The Playwright URL guard rejects other ports and non-loopback destinations. WSL2 needs WSLg or an X server for the visible browser. Screenshots and the HTML report are written on a passing run; traces and videos are retained on failure. Artifacts live under `e2e/test-results/` and `e2e/playwright-report/`. Console errors, failed requests, HTTP errors, and unexpected non-GET requests are attached as browser diagnostics.

Laravel backend behavior remains covered by Pest. `composer test` uses the non-started `test` profile service. It has no network, mounts the worktree read-only, stores temporary state in tmpfs, and uses SQLite `:memory:`. It does not start app dependencies or invoke migrations:

```bash
composer test
```

The guarded runner clears cached Laravel configuration, then checks that PHPUnit is targeting SQLite `:memory:` with no database URL before tests can execute. Direct Pest and PHPUnit invocations also pass through this bootstrap guard. Do not point tests at MySQL.

### PHP extensions

If your application requires specific PHP extensions to run, they will need to be added to the Dockerfile. Follow the instructions and example in the Dockerfile to add them.

### Deploying your application to the cloud

First, build your image, e.g.: `docker build -t myapp .`.
If your cloud uses a different CPU architecture than your development
machine (e.g., you are on a Mac M1 and your cloud provider is amd64),
you'll want to build the image for that platform, e.g.:
`docker build --platform=linux/amd64 -t myapp .`.

Then, push it to your registry, e.g. `docker push myregistry.com/myapp`.

Consult Docker's [getting started](https://docs.docker.com/go/get-started-sharing/)
docs for more detail on building and pushing.
