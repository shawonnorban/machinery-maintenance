# Production Deployment — Shared Hosting (cPanel), Git-based

**What this covers**: deploying the *current* architecture — a Laravel 13 API
(`app/`) and a separate Next.js 16 frontend (`frontend/`) living in one Git
repository — to a cPanel shared hosting account, using cPanel's Git Version
Control feature or plain `git`/SSH, with MySQL as the database.

**What this assumes** (confirmed for this deployment):

- The host has **"Setup Node.js App"** in cPanel (Cloudlinux/Phusion Passenger
  Node.js Selector). Without this, the Next.js half cannot run at all — there
  is no static-export fallback for this app, since every page reads live data
  through server-side, cookie-authenticated requests.
- The host provides **MySQL/MariaDB**, not PostgreSQL. `DB_CONNECTION=pgsql`
  is this project's current default, but MySQL is a fully supported, CI-tested
  alternative (`.github/workflows/tests.yml` runs the whole suite against
  both engines on every push) — nothing here is a downgrade.
- PHP **8.4.1+** is available (see `docs/11-Deployment.md` §2 for exactly why
  the floor is 8.4.1 and not the `^8.3` `composer.json` suggests).

This file is the practical, step-by-step runbook for *this* deployment.
`docs/11-Deployment.md` is the deeper reference (backups, scaling, health
checks, alerting, the Postgres-migration runbook) — its own §13 predates the
Next.js split and describes the older, single-app Blade deployment; this file
supersedes it for anything involving the frontend.

---

## 0. Architecture on this host

Two subdomains, one repository, one MySQL database:

```
https://api.yourdomain.com   →  Laravel API           (public/ as document root, PHP)
https://app.yourdomain.com   →  Next.js frontend       (cPanel "Node.js App", frontend/)
```

The browser **never calls the Laravel API directly** — every `apiFetch()` call
happens server-side, inside the Next.js Node process, using a bearer token
kept in an httpOnly cookie the browser can't read (`frontend/src/lib/
session.js`). This matters for hosting because it means:

- `api.yourdomain.com` only needs to be reachable by the Next.js server
  process and by browsers loading images/files (asset documents, the company
  logo, exported reports) — it does **not** need CORS configured for
  cross-origin `fetch`/XHR from the browser.
- If anything goes wrong with the frontend, the API is still a complete,
  independently testable surface (`curl https://api.yourdomain.com/api/v1/health`).

One repository, two deployment targets inside it — cPanel's Node.js Selector
lets you point an "Application Root" at a subfolder (`frontend/`) of a
normally-cloned repo, so a single `git pull` updates both halves at once.

---

## 1. One-time cPanel setup

### 1.1 PHP version

```bash
php -v                        # the shell's default — often older than the web PHP
ls -d /opt/cpanel/ea-php*      # what's actually installed
```

If the shell shows < 8.4, use the versioned binary for every command below:

```bash
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
```

Set the **web** PHP version separately in cPanel → **MultiPHP Manager** for
the `api.yourdomain.com` subdomain — it does not follow the shell.

### 1.2 MySQL database

cPanel → **MySQL Databases**:

1. Create a database (e.g. `cpaneluser_machinery`).
2. Create a database user with a strong password.
3. Add the user to the database with **all privileges**.

Write these three values down — they go in Laravel's `.env` in §3.

### 1.3 Subdomains

cPanel → **Domains** → **Create A New Domain**:

| Subdomain | Document root |
|---|---|
| `api.yourdomain.com` | `machinery-maintenance/public` |
| `app.yourdomain.com` | *(leave as cPanel's default — the Node.js App step in §1.5 sets the real serving path)* |

**The Laravel document root must be `public/`, never the repository root** —
anything else serves `.env` to the internet. Verify this the moment DNS
resolves:

```bash
curl -I https://api.yourdomain.com/.env      # must be 403 or 404, never 200
```

### 1.4 Clone the repository

Two ways — use whichever the host's cPanel offers; both end up in the same
place.

**A. cPanel → Git™ Version Control (the "git app")**

1. **Create**, paste the repository's clone URL, set the repository path to
   `~/machinery-maintenance`.
2. If the repo is private, add the deploy key cPanel shows you as a
   **read-only deploy key** on the Git host (GitHub → repo → Settings → Deploy
   keys) — never reuse a personal SSH key here.
3. cPanel clones the branch you specify (usually `main`/`master`) immediately.
   Its own **"Pull or Deploy"** tab is what you use for every deployment
   *after* the first one (§5) — one click re-runs `git pull`.

**B. SSH terminal**

```bash
cd ~
git clone git@github.com:you/machinery-maintenance.git
# or, if the host only allows HTTPS:
git clone https://github.com/you/machinery-maintenance.git
```

Either way you should now have `~/machinery-maintenance/` containing both
`app/` (Laravel) and `frontend/` (Next.js) side by side.

### 1.5 Node.js App (the Next.js half)

cPanel → **Setup Node.js App** → **Create Application**:

| Field | Value |
|---|---|
| Node.js version | **20 LTS or 22 LTS** — Next.js 16 needs a current Node; pick whichever the dropdown offers that's newest |
| Application mode | Production |
| Application root | `machinery-maintenance/frontend` |
| Application URL | `app.yourdomain.com` |
| Application startup file | `server.js` — see below |

Next.js's own `next start` isn't a file cPanel's Node Selector can point at
directly (it wants a single JS file to `require`), so add this one file to
the repo:

```js
// frontend/server.js
const { createServer } = require("http");
const next = require("next");

const app = next({ dev: false });
const handle = app.getRequestHandler();

app.prepare().then(() => {
  createServer((req, res) => handle(req, res)).listen(process.env.PORT || 3000, () => {
    console.log(`Next.js listening on ${process.env.PORT || 3000}`);
  });
});
```

Commit this file once (`git add frontend/server.js`) — it's server
infrastructure, not build output, so it belongs in the repo. cPanel's Node
Selector sets `process.env.PORT` itself; nothing else to configure there.

After creating the app, cPanel shows a **"Run NPM Install"** button — use it
after every deployment that changes `frontend/package.json` (§5), not just
the first time.

---

## 2. Laravel `.env`

Create `~/machinery-maintenance/.env` on the server directly (it's
git-ignored — never committed, never pulled):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_KEY=                              # filled by `artisan key:generate` below

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpaneluser_machinery
DB_USERNAME=cpaneluser_machinery
DB_PASSWORD=…

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# The Next.js origin — everywhere the API needs to redirect or link back
# into the frontend (QR-scan labels, login redirects, notification action
# URLs) reads this, not a hardcoded host.
FRONTEND_URL=https://app.yourdomain.com

# No supervised `reverb:start` on shared hosting (§6) — see docs/11-
# Deployment.md §13.5 for exactly what this trades away and why it's safe.
BROADCAST_CONNECTION=log

# No ClamAV on shared hosting — uploads are recorded SKIPPED and stay
# usable rather than stuck PENDING forever.
VIRUS_SCAN_ENABLED=false

# `log` here means "nobody can reset a forgotten password" — see docs/11-
# Deployment.md §13.4. Use a real mailbox from cPanel → Email Accounts.
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=…
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
```

## 3. Next.js `.env.production`

Create `~/machinery-maintenance/frontend/.env.production` (also git-ignored —
`NEXT_PUBLIC_*` values below get baked into the browser bundle at *build*
time, so this file must exist and be correct **before** `npm run build`,
not just before starting the app):

```dotenv
LARAVEL_API_URL=https://api.yourdomain.com/api/v1

# Reverb is off on shared hosting (no supervised process to run it — same
# reasoning as BROADCAST_CONNECTION=log above). Leaving these unset means
# the sync indicator just shows "Reconnecting" instead of live updates;
# nothing else depends on them. Fill them in only if you later move the
# API host to somewhere that can run `reverb:start` permanently.
# NEXT_PUBLIC_REVERB_APP_KEY=
# NEXT_PUBLIC_REVERB_HOST=
# NEXT_PUBLIC_REVERB_PORT=443
# NEXT_PUBLIC_REVERB_SCHEME=https
```

---

## 4. First deployment

```bash
cd ~/machinery-maintenance

# --- Laravel ---
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader
$PHP artisan key:generate
$PHP artisan storage:link
$PHP artisan migrate --force
$PHP artisan db:seed --force          # roles, permissions, taxonomy — not optional, see §7
$PHP artisan platform:admin you@yourdomain.com --name="Your Name"

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

# --- Next.js ---
cd frontend
# Use cPanel's own "Run NPM Install" button instead if the shell's Node
# doesn't match the version selected in the Node.js App (§1.5) — its
# terminal button runs npm inside the app's own virtual environment.
npm ci
npm run build
```

Then, in cPanel → **Setup Node.js App**, click **Restart** on the app so it
picks up the fresh `.next/` build.

Verify both halves independently before touching DNS for real users:

```bash
curl -s https://api.yourdomain.com/api/v1/health          # {"status":"ok",...}
curl -s -o /dev/null -w "%{http_code}\n" https://app.yourdomain.com/login   # 200
```

---

## 5. Every deployment after the first

**Via cPanel's Git Version Control** (if you used method A in §1.4):
click **Pull or Deploy** → **Update from Remote**, then continue from the
`composer install` line below in an SSH terminal (cPanel's Git UI only pulls
code; it doesn't run Composer, migrations, or the frontend build).

**Via SSH** (either method):

```bash
cd ~/machinery-maintenance
$PHP artisan down --retry=60

git pull                                  # skip this line if you deployed via cPanel's Git UI above

$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader
$PHP artisan migrate --force
$PHP artisan db:seed --force              # every deploy, not just the first — see §7
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

cd frontend
npm ci                                    # or cPanel's "Run NPM Install" button
npm run build

$PHP artisan up
```

Then restart the Node.js App from cPanel → **Setup Node.js App** → **Restart**
— it holds the old build in memory until restarted, the same reason
`docs/11-Deployment.md` §6 restarts the queue worker after every deploy.

---

## 6. Cron jobs

cPanel → **Cron Jobs**, two entries, both **every minute**:

```
* * * * * cd ~/machinery-maintenance && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >/dev/null 2>&1
* * * * * cd ~/machinery-maintenance && /opt/cpanel/ea-php84/root/usr/bin/php artisan queue:work --stop-when-empty --max-time=55 >/dev/null 2>&1
```

Use the **full path** to PHP in both — cron's own `php` is whatever the
system default is, usually the version too old to have run `composer
install` in the first place, and a cron entry that fails this way leaves no
error and no obvious symptom.

The first line is maintenance-schedule generation, KPI snapshots, escalation
delivery, subscription billing — without it, the product looks fine and
quietly does nothing on a timer. The second is the queue: notifications,
webhooks, exports; picked up within a minute rather than instantly, which is
the best a shared host allows without a supervised `queue:work` daemon.

---

## 7. Why `db:seed --force` runs on every deployment, not just the first

Reference data — permissions, roles, setting definitions, the asset/failure
taxonomy — ships with the *code*, not the schema. A release that adds a new
one and skips the seeder leaves the application asking the database for a row
that only exists in the new code. For a setting definition specifically, this
isn't a quiet degradation: the resolver refuses an unknown key outright
(ADR-054), which takes down *every* page — login included — until the seeder
runs. Every seeder here is safe to re-run (each uses `updateOrCreate` keyed on
a natural key), so there's no cost to doing it every time.

This includes `App\Modules\Platform\Database\Seeders\PlatformAdminSeeder`,
which keeps `admin@noirban.com` at a fixed, known password across every
`migrate:fresh`/reseed on purpose (see that seeder's own docblock) — if this
account should **not** exist in production with that password, remove or
change that seeder before the first production seed, not after.

---

## 8. What doesn't work here, and why that's fine

Same trade-off `docs/11-Deployment.md` §13.5 already documents for the
Blade app, carried over to the Next.js half:

- **No live updates.** `reverb:start` needs a permanently-running process on
  an open port; shared hosting has neither. The sync indicator in the topbar
  shows "Reconnecting" (truthfully — it's never faked as live), and
  notification badges/breakdown lists/work-order boards update on page
  load or refresh instead of pushing to the screen. Nothing is lost; a
  technician has to refresh. Move to a VPS when this stops being acceptable.
- **Queue latency up to ~1 minute**, not instant — the cron-driven
  `queue:work --stop-when-empty` in §6, not a supervised daemon.
- **`max_execution_time`** (often 30s on shared hosting) can cut off a large
  import/export run in the browser — both already run on the queue for this
  reason; keep individual imports modest.

---

## 9. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `composer install` refuses with "does not satisfy" errors | PHP < 8.4.1 — see §1.1 |
| `.env` loads with 200 at `/api/v1/.env` or similar | Document root points at the repo root, not `public/` — see §1.3 |
| Every page 500s after a deploy | Forgot `db:seed --force` and a new setting definition is missing — see §7 |
| Frontend shows old content after a deploy | Node.js App wasn't restarted — see §5 |
| Login works but every page after redirects to `/login` in a loop | `FRONTEND_URL` in Laravel's `.env` doesn't match the real `app.yourdomain.com` origin, or `LARAVEL_API_URL` in `frontend/.env.production` doesn't match `api.yourdomain.com` |
| `git pull` fails on the second deployment with no obvious cause | Repo was cloned without a trailing `.` in some other setup and `.git` ended up nested — not applicable here since §1.4 clones directly into `~/machinery-maintenance`, but see `docs/11-Deployment.md` §13.2 if this surfaces anyway |
| Password reset says sent but nothing arrives | `MAIL_MAILER` still `log` — see §2 |

For anything not covered here — backups, scaling past this host, alerting,
the eventual PostgreSQL cutover — see `docs/11-Deployment.md`.
