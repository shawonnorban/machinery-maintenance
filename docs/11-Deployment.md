# 11-Deployment.md
# Production Deployment and Operations

Build order step 35. This is the runbook: what has to be running, in what
order, and what breaks quietly if it is not.

The quiet failures are the point of this document. A missing queue worker does
not produce an error page — notifications simply stop arriving. A missing
scheduler does not either — maintenance stops being generated, and the first
symptom is a machine that was never serviced.

---

## 1. What has to be running

Five processes. The application alone is one of them.

| Process | What stops without it | Visible? |
|---|---|---|
| PHP-FPM / web server | Everything | Immediately |
| `queue:work` | Notifications, webhooks, exports, imports, escalation delivery | **No** |
| `schedule:run` (every minute) | Maintenance generation, escalation, KPI snapshots, subscription advance, expiry alerts | **No** |
| `reverb:start` | Live updates; the connection indicator shows Offline | Partly |
| MySQL 8.4+ | Everything | Immediately |

Redis is optional but strongly recommended for cache, queue and session at the
target volumes in SRS 51. The application runs on the database driver for all
three; it simply asks more of MySQL.

---

## 2. Requirements

- PHP 8.4+ with `bcmath`, `pdo_mysql`, `mbstring`, `openssl`, `zip`, `intl`
  - `bcmath` is not optional. Money is DECIMAL(18,4) and every arithmetic
    operation on it goes through bcmath (ADR-063). Without the extension the
    application will not boot.
- MySQL 8.4+ (`utf8mb4`, `utf8mb4_unicode_ci`)
- Node 20+ **at build time only**. Nothing on the server needs Node at runtime.
- A writable `storage/` and `bootstrap/cache/`

---

## 3. First deployment

```bash
git clone <repo> && cd machinery-maintenance

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # produces public/build; Node is not needed again

cp .env.example .env
php artisan key:generate          # do this once, then keep it — see §4
php artisan storage:link

php artisan migrate --force
php artisan db:seed --force       # platform roles, permissions, reference data

php artisan optimize              # config, routes, views, events
```

Then create the first company and its owner. There is no registration screen by
design — this is sold, not signed up for — so the first account is made with
the platform seeder or by an existing platform administrator.

---

## 4. `APP_KEY` is not rotatable on a whim

`mfa_secret` is encrypted with it (SRS 50.3). Rotating `APP_KEY` without
re-encrypting makes every enrolled second factor undecryptable, which locks out
exactly the accounts that were most careful. Keep it in a secret manager, back
it up separately from the database, and if it must be rotated, re-encrypt first
and expect to re-enrol anybody whose secret cannot be recovered.

Nothing else in the schema is encrypted at rest; passwords, client secrets,
API tokens and recovery codes are hashed, and hashes survive a key change.

---

## 5. Process supervision

`systemd` units, one per process. Supervisor works equally well.

```ini
# /etc/systemd/system/mm-queue.service
[Unit]
Description=Machinery Maintenance queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=5
# --max-time recycles the worker hourly. A long-lived PHP process accumulates
# memory and, worse, keeps a stale copy of any code deployed since it started.
ExecStart=/usr/bin/php /var/www/mm/artisan queue:work \
          --queue=default,broadcasts --tries=3 --max-time=3600 --sleep=1

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/mm-scheduler.timer  (with a matching .service)
[Timer]
OnCalendar=*:0/1
AccuracySec=1s
```

The queue order matters and is not alphabetical. `default` carries
notifications, webhooks, exports and imports; `broadcasts` carries live
updates, which are advisory — the thing each one announces is already recorded
and durable. Naming `default` first means a websocket server that is down or
slow can never delay work that matters. Broadcasts are also given a single
attempt: a retried live update arrives stale, lands after the screen has been
refreshed, and contradicts what is on it.

If Reverb is not being run at all, set `BROADCAST_CONNECTION=log` rather than
leaving `reverb` pointed at nothing. Otherwise every stock movement, status
change and assignment leaves a row in `failed_jobs`, which buries the failures
somebody actually needs to see.

The scheduler must run **every minute**, not every five. Escalation is
evaluated on its tick, and a rule that says "tell the manager after thirty
minutes" is worthless if the tick is coarser than the promise.

```ini
# /etc/systemd/system/mm-reverb.service
ExecStart=/usr/bin/php /var/www/mm/artisan reverb:start --host=0.0.0.0 --port=8080
```

Put Reverb behind the same TLS termination as the application and proxy
`/app/` websocket upgrades to it. `connect-src 'self' ws: wss:` in the content
security policy already allows it.

---

## 6. Subsequent deployments

```bash
php artisan down --retry=60

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan db:seed --force          # not optional -- see below
php artisan optimize                 # replaces the previous caches

php artisan up

systemctl restart mm-queue mm-reverb # workers hold old code until restarted
```

Restarting the workers is not optional. A queue worker started before the
deploy keeps running the code it booted with, which means a job enqueued by new
code can be handled by an old handler — the class of bug that only appears in
production and only for a few minutes after each release.

### Seeding on every deploy, not only the first

Reference data ships with the code, not with the schema: permissions, roles,
setting definitions, the industry taxonomy. A release that adds one and is
deployed without `db:seed` leaves the application asking the database for a row
that only exists in the new code.

How that fails is the reason this line is in the runbook rather than left to
judgement. A missing setting definition is not a quiet degradation — the
resolver refuses an unknown key rather than storing it silently (ADR-054), so a
new definition read by anything on the request path takes down *every* page,
login included, until the seeder runs. That is a one-line omission producing a
total outage, and it has already happened once in development.

Every seeder here is safe to re-run: each writes with `updateOrCreate` keyed on
a natural key, and the two that remove anything — the asset taxonomy and the
checklist templates — delete only platform-owned rows that nothing references
(`whereDoesntHave('assets')`, `whereDoesntHave('results')`). A customer's own
data is never in scope, so running the seeder on each deploy changes nothing
when there is nothing new.

### Migrations and rollbacks

Every migration in this codebase guards its own steps with existence checks, so
re-running is safe. Rolling *back* a migration that dropped a column is not:
the column returns empty. Treat a bad deploy as a roll-forward.

---

## 7. Health checks

| Endpoint | Question | Use for |
|---|---|---|
| `GET /up` | Is the framework booting? | Container liveness |
| `GET /api/v1/health` | Is the process alive? | Load balancer liveness |
| `GET /api/v1/health/ready` | Should it be sent traffic? | Load balancer readiness |

`ready` checks the database and the cache and answers 503 when either is
unreachable. Keep them apart: a deploy mid-migration is alive and not ready,
and answering one question for both either restarts a healthy process or sends
traffic into a broken one.

Neither names what failed. They are unauthenticated, and an unauthenticated
endpoint that prints a database host is a gift.

---

## 8. What to alert on

Ordered by how quietly each one fails.

1. **Scheduler heartbeat.** If `schedule:run` has not completed in five
   minutes, alert. This is the failure nobody notices: no error, no page, just
   maintenance quietly not being generated.
2. **Queue depth and failed jobs.** A growing `jobs` table or any row in
   `failed_jobs`.
3. **Webhook endpoints disabled by consecutive failures.** The endpoint stops
   itself after repeated failures by design; somebody has to be told.
4. `/api/v1/health/ready` returning 503.
5. p95 latency above 500 ms (SRS 45).
6. `SECURITY_EVENT` audit rows with `TENANT_ACCESS_DENIED`. Either a bug in an
   integration or somebody trying doors; both need a person.

---

## 9. Backups

- **Database**: daily full dump, retained 30 days, plus binlogs for
  point-in-time recovery. Restore-test monthly against a scratch instance — an
  untested backup is a hypothesis.
- **`storage/app`**: the file store. Attachments, asset documents, generated
  exports. Same schedule.
- **`APP_KEY`**: separately, and not in the same place as the database dump.
  Together they are the second factor of every enrolled user.

---

## 10. Load testing before go-live

Scripts live in `load-tests/`. Run them against a staging instance seeded to
the **target** column of SRS 51 — 20,000 assets, not 40 — because every index
looks fast against a small table and the queries worth catching are the ones
that degrade with volume.

```bash
k6 run -e BASE_URL=https://staging.example.com \
       -e EMAIL=... -e PASSWORD=... load-tests/browse.js

k6 run -e BASE_URL=https://staging.example.com \
       -e CLIENT_ID=cid_... -e CLIENT_SECRET=sk_... \
       -e METER_IDS=... load-tests/ingest.js
```

`browse.js` fails the run if p95 exceeds 500 ms at 100 concurrent users.
`ingest.js` fails if a single retried reading is ever executed twice rather
than replayed — a correctness property that only breaks under concurrency,
which is why it is asserted here and not in the unit suite.

The `QueryBudgetTest` in the ordinary suite covers the complementary case: it
renders each heavy screen with a few rows and with many and fails if the query
count follows the data. That catches an N+1 in review; the load test catches
what only appears under contention.

---

## 11. Scaling, in the order it will be needed

1. **Queue workers.** Cheapest and first. Add processes; they coordinate
   through the database or Redis.
2. **Redis** for cache, session and queue.
3. **Read replica** for reports and dashboards. KPI snapshots are already
   precomputed hourly (ADR-058), so the heavy aggregate work is off the request
   path before this becomes necessary.
4. **Archival** of the high-volume tables named in SRS 51 — `audit_logs`,
   `meter_readings`, `inventory_transactions`, `notifications`,
   `webhook_deliveries`. All five are append-only and partition cleanly by
   date. Do this before the ceiling column, not after.

Horizontal application scaling works today provided session and cache are
shared — meaning Redis, or the database driver, never the file driver.

---

## 12. Customer addresses

A customer can reach their system on three kinds of address. They cost very
different amounts of setup, and only the first needs nothing.

**1. The shared address.** `APP_URL`, the default. The tenant is resolved from
the signed-in user's membership. Nothing to configure, and every customer works
this way until told otherwise.

**2. A subdomain of ours** — `delta.example.com`. Issued from the customer's
page in the platform area and working the moment it is saved, because the host
is already ours. It needs two things once, for all customers ever:

```
; DNS — one wildcard record
*.example.com.   300   IN   A   <the application's address>
```

and a wildcard certificate for `*.example.com`. With Caddy that is one line;
with nginx it means a DNS-01 challenge, because HTTP-01 cannot issue wildcards.

Set `TENANCY_SUBDOMAIN_HOST=example.com` if customers should be issued
subdomains somewhere other than the platform's own host — for instance when the
platform area is served from `admin.example.com`.

**3. The customer's own domain** — `maintenance.deltaapparels.com`. Three
things must be true, and only the middle one happens in this application:

1. **They point it at us.** A CNAME from their address to ours. Their DNS,
   their decision, and nobody here can do it for them.
2. **They prove they own it.** A TXT record at `_mm-verify.<their address>`
   containing the token shown on their page in the platform area. Until this
   check passes, the address resolves to nobody — deliberately. An unverified
   row is a claim, and honouring a claim would let one customer put their name
   on an address they do not own and collect another company's sign-ins.
3. **The server has a certificate for it.** This is the part that is missed.
   The application will happily serve a verified custom domain and the browser
   will refuse to load it, which reads to the customer as the product being
   broken.

For (3), a proxy that issues certificates on demand is worth far more than a
per-customer certificate procedure. Caddy:

```caddyfile
{
    on_demand_tls {
        # Caddy asks before issuing, so a stranger pointing their domain at
        # this server cannot make us request certificates on their behalf.
        ask http://127.0.0.1:8000/internal/tls-check
    }
}

https:// {
    tls { on_demand }
    reverse_proxy 127.0.0.1:8000
}
```

The `ask` endpoint is not built yet. Until it is, add custom domains to the
proxy configuration by hand — which is fine at the scale where custom domains
are a handful of customers, and is the reason to leave it until they are not.

### What a support call about this usually is

In order of how often it is the answer:

1. The TXT record was added at the wrong name. Most DNS panels append the zone
   automatically, so a customer who pastes the full
   `_mm-verify.maintenance.deltaapparels.com` ends up with
   `_mm-verify.maintenance.deltaapparels.com.deltaapparels.com`.
2. DNS has not propagated. **Check now** says "not visible yet" rather than
   "wrong" for exactly this reason. Wait, then press it again.
3. The CNAME is there and the certificate is not — see (3) above.
