# 07-Permissions-and-Module-Structure.md
# Permission Catalog, Module Structure and Developer Setup
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.0
**Status:** Accepted
**Companion to:** `01-SRS.md` Section 5, `04-Architecture-Decision-Record.md` ADR-045

---

## 1. Purpose

Three things a developer needs on day one that the v1.1 set still lacked: which permission guards which endpoint, where code goes, and how to run the project locally.

---

## 2. Permission Catalog

Naming follows SRS 5.2: `{module}.{resource}.{action}`.

### 2.1 Asset Module

| Permission | Guards |
|---|---|
| `asset.asset.view_any` | `GET /assets`, `GET /scan/qr/{code}` |
| `asset.asset.view` | `GET /assets/{asset}` and its sub-resources |
| `asset.asset.create` | `POST /assets`, `POST /imports/assets` |
| `asset.asset.update` | `PATCH /assets/{asset}` |
| `asset.asset.delete` | `DELETE /assets/{asset}` |
| `asset.status.update` | `POST /assets/{asset}/status` |
| `asset.transfer.create` | `POST /assets/{asset}/transfer` |
| `asset.transfer.approve` | Transfer approval step |
| `asset.transfer.receive` | Transfer receipt at destination |
| `asset.document.manage` | `POST`/`DELETE /assets/{asset}/documents` |
| `asset.qr.regenerate` | QR token regeneration (Data Dictionary 5.5) |
| `asset.financial.view` | Acquisition cost, book value, lifecycle cost fields |

`asset.financial.view` is separate because a technician needs the asset record but has no business seeing its purchase price.

### 2.2 Maintenance Module

| Permission | Guards |
|---|---|
| `maintenance.plan.view_any` / `.view` | `GET /maintenance-plans` |
| `maintenance.plan.create` / `.update` / `.delete` | Plan CRUD |
| `maintenance.plan.activate` | Activate and deactivate |
| `maintenance.template.view_any` / `.create` / `.update` | Templates and checklist items |
| `maintenance.template.publish` | Freezing a template version |
| `maintenance.schedule.view_any` | `GET /maintenance-schedules` |
| `maintenance.schedule.reschedule` | `POST .../reschedule` |
| `maintenance.schedule.skip` | `POST .../skip` — requires a reason, reported as a compliance exception |
| `meter.reading.create` | `POST /meters/{meter}/readings` |
| `meter.reading.view_any` | Reading history |
| `meter.meter.reset` | `POST /meters/{meter}/reset` — elevated |

### 2.3 Work Order Module

| Permission | Guards |
|---|---|
| `work_order.work_order.view_any` | `GET /work-orders` |
| `work_order.work_order.view` | Single work order |
| `work_order.work_order.create` / `.update` | Creation and editing |
| `work_order.work_order.assign` | `POST .../assign`, `POST /work-orders/bulk-assign` |
| `work_order.work_order.start` | `POST .../start`, `/hold`, `/resume` |
| `work_order.work_order.complete` | `POST .../complete` |
| `work_order.work_order.verify` | `POST .../verify` |
| `work_order.work_order.close` | `POST .../close` |
| `work_order.work_order.cancel` | `POST .../cancel` |
| `work_order.work_order.reopen` | `POST .../reopen` — elevated |
| `work_order.labor.manage` | Labor entry CRUD |
| `work_order.part.request` | Requesting a part on a work order |
| `work_order.cost.view` | Cost breakdown on a work order |

**Technician scoping.** A user holding only `Technician` role permissions may act on a work order **only where they hold an active assignment**. This is a policy check, not a permission check: the permission grants the ability, the policy restricts the instance (SRS 5.3 rule 3).

### 2.4 Breakdown, Inventory, Cost, Vendor

| Permission | Guards |
|---|---|
| `breakdown.breakdown.view_any` / `.view` | Breakdown lists and detail |
| `breakdown.breakdown.create` | `POST /breakdowns`, `POST /scan/qr/{code}/breakdown` |
| `breakdown.breakdown.acknowledge` / `.assign` / `.repair` | Lifecycle actions |
| `breakdown.breakdown.close` | Requires root cause and failure code |
| `inventory.part.view_any` / `.create` / `.update` | Spare part master |
| `inventory.stock.view` | Balances and valuation |
| `inventory.stock.receive` | `RECEIPT` transactions |
| `inventory.stock.issue` | `ISSUE` and `CONSUME` |
| `inventory.stock.return` | `RETURN` |
| `inventory.reservation.manage` | Reserve and release |
| `inventory.adjustment.create` | `ADJUSTMENT_IN` / `ADJUSTMENT_OUT` — elevated, always audited |
| `inventory.transfer.create` / `.approve` / `.dispatch` / `.receive` | Transfer lifecycle |
| `cost.entry.view` / `.create` | Cost entries |
| `cost.entry.reverse` | Reversal entries — elevated |
| `vendor.vendor.view_any` / `.create` / `.update` | Vendor master |
| `vendor.warranty.manage` / `vendor.contract.manage` | Warranty and AMC |
| `technician.technician.manage` | Technician records |
| `technician.performance.view` | Individual KPI figures (SRS 25.2) |
| `technician.grade.manage` | Labor rate grades — elevated |

### 2.5 Platform, Admin and Cross-Cutting

| Permission | Guards |
|---|---|
| `admin.user.manage` / `admin.role.manage` | Users, roles, assignments |
| `admin.team.manage` | Teams |
| `admin.api_client.manage` | API clients — elevated |
| `settings.company.manage` / `settings.factory.manage` | Settings by level |
| `settings.calendar.manage` | Shifts, holidays, calendars |
| `settings.numbering.manage` | Number sequence formats |
| `masterdata.manage` | Asset types, categories, failure codes, reason codes, locations |
| `report.report.view` / `report.report.export` | Reports and exports |
| `dashboard.management.view` / `.maintenance.view` / `.store.view` | Dashboards |
| `audit.log.view` | Audit log — read only, always |
| `billing.subscription.manage` / `billing.payment.manage` | Subscription and payments |
| `approval.request.approve` / `.reject` | Approval actions |
| `webhook.endpoint.manage` | Webhooks |
| `import.job.create` / `export.job.create` | Bulk import and export |

### 2.6 Enforcement Rules

1. Every endpoint declares exactly one required permission. An endpoint with none does not ship (API 35.3 rule 7).
2. Permission grants the ability; a policy restricts the instance. Both run on every request (API 34).
3. `view_any` returns a tenant-scoped and factory-scoped list. It never returns another company's rows, regardless of filters.
4. Export endpoints require both the resource permission and `report.report.export`. Export is the most common way authorization gets bypassed.
5. Permissions marked elevated additionally require the user's session to have passed MFA where company policy enables it.
6. `Viewer` and `Auditor` roles hold only `view`, `view_any`, and `audit.log.view`. A seed test asserts they hold no permission ending in `create`, `update`, `delete`, `approve`, `manage`, `issue`, or `close`.

---

## 3. Backend Module Structure

ADR-045 chose a modular monolith with "strict module boundaries" and never named the modules. Without that, boundaries do not exist.

```text
app/
├── Modules/
│   ├── Tenancy/          Company, factory, location hierarchy, tenant context
│   ├── Identity/         Users, roles, permissions, teams, MFA, sessions, API clients
│   ├── Settings/         Settings resolution, setting definitions, numbering sequences
│   ├── Calendar/         Shifts, holidays, working-time service
│   ├── Asset/            Assets, models, transfers, documents, QR
│   ├── Maintenance/      Plans, rules, schedules, templates, checklists, scheduler
│   ├── Metering/         Meters, readings, resets
│   ├── WorkOrder/        Work orders, labor, parts, holds, checklist execution
│   ├── Breakdown/        Breakdowns, failure taxonomy, downtime records
│   ├── Inventory/        Parts, bins, ledger, balances, reservations, transfers
│   ├── Costing/          Cost entries, exchange rates, lifecycle cost
│   ├── Vendor/           Vendors, warranties, service contracts, claims
│   ├── Analytics/        KPI service, snapshots, dashboards
│   ├── Reporting/        Report jobs, exports, imports
│   ├── Approval/         Workflows, rules, requests, actions
│   ├── Notification/     Notifications, preferences, escalation, delivery
│   ├── Billing/          Subscriptions, invoices, payments, usage metrics
│   └── Audit/            Audit log writer and reader
└── Shared/               Cross-module contracts, base classes, value objects
```

Each module holds:

```text
Modules/{Module}/
├── Actions/          Single-purpose write operations
├── Services/         Domain services, reused across actions
├── Models/           Eloquent models
├── Policies/         Instance-level authorization
├── Http/
│   ├── Controllers/  Thin; validate, authorize, delegate, respond
│   ├── Requests/     Validation and the source of the OpenAPI request schema
│   └── Resources/    Response shaping and the source of the response schema
├── Events/
├── Listeners/
├── Jobs/
├── Database/         Migrations, factories, seeders
└── Tests/
```

### 3.1 Boundary Rules

1. A module may depend on `Shared` and on modules listed as its dependencies. Nothing else.
2. Cross-module reads go through the owning module's service interface, never through another module's Eloquent model directly.
3. Cross-module writes go through domain events, not direct calls, wherever the operation can be asynchronous.
4. `Tenancy`, `Identity`, and `Settings` are foundation modules: everything may depend on them, and they depend on nothing above them.
5. A circular dependency between modules is a build failure, enforced by a static analysis rule in CI.
6. `Analytics` reads from many modules and is read-only. It never writes to another module's tables.

### 3.2 Dependency Direction

```text
Tenancy ← Identity ← Settings ← Calendar
                                   ↑
        Asset → Metering → Maintenance → WorkOrder → Breakdown
                                              ↓          ↓
                                        Inventory → Costing
                                              ↓
                    Approval, Notification, Analytics, Reporting, Audit, Billing
```

Arrows point from dependency to dependent. `Audit` and `Notification` are subscribed through events and are depended on by nothing.

---

## 4. Frontend Structure

Server-rendered Blade inside the same Laravel application (ADR-066). There is no separate frontend project.

```text
resources/
├── views/
│   ├── layouts/          app.blade.php, auth.blade.php, mobile.blade.php
│   ├── components/       Shared x- components (see Frontend 4.3)
│   ├── auth/             login, mfa, forgot-password, select-company
│   ├── dashboard/
│   ├── assets/           index, show, create, edit, transfer, print-labels
│   ├── maintenance/      plans, schedule, templates
│   ├── work-orders/      index, show, create, execute (mobile)
│   ├── breakdowns/       index, show, create
│   ├── inventory/        parts, stock, issue, receipts, transfers
│   ├── technicians/  vendors/  costs/  reports/  settings/  billing/
│   ├── scan/             QR landing
│   └── partials/         AJAX-replaceable fragments
├── js/
│   ├── app.js            Shared entry: Bootstrap, CoreUI, Axios, Day.js
│   ├── mobile.js         Technician screens, IndexedDB queue, camera
│   ├── analytics.js      Chart.js, SmartTable extras
│   ├── http.js           The single configured Axios instance
│   ├── echo.js           Reverb subscription and reconnection
│   └── offline/          Draft store, retry queue, idempotency keys
├── sass/
│   ├── app.scss          Bootstrap + CoreUI imports, theme variables
│   └── _mobile.scss
└── views/vendor/         Published package views

lang/
├── en/                   assets.php, work_orders.php, enums.php, ...
└── bn/                   same files

public/
├── build/                Vite output, hashed
└── fonts/                CoreUI Icons subset, Bengali webfont subset
```

Rules:

1. A Blade view contains presentation only. No query building, no business rules, no `Model::where()` in a template.
2. Views used as AJAX targets live in `partials/` and render standalone, so a WebSocket event can replace one by re-fetching it.
3. `resources/js` holds no business logic. Due dates, costs, and downtime are computed server-side (Frontend 2.3).
4. Module-owned views may live under `app/Modules/{Module}/Resources/views` and be registered by the module service provider, which keeps a module self-contained. Either layout is acceptable; the project picks one and stays with it.

---

## 5. Environment Variables

### 5.1 Backend

```text
APP_ENV                 local | staging | production
APP_KEY
APP_URL
FRONTEND_URL            Used for CORS allowlist and QR links

DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

REDIS_HOST REDIS_PORT REDIS_PASSWORD
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET
REVERB_HOST REVERB_PORT REVERB_SCHEME

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION AWS_BUCKET AWS_ENDPOINT
AWS_USE_PATH_STYLE_ENDPOINT

MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS

SANCTUM_STATEFUL_DOMAINS
TOKEN_ABSOLUTE_EXPIRY_DAYS=30
TOKEN_IDLE_EXPIRY_HOURS=12

FILE_MAX_UPLOAD_MB=25
FILE_SIGNED_URL_TTL_MINUTES=5
VIRUS_SCAN_ENABLED=true

SENTRY_DSN
LOG_CHANNEL=stack
LOG_LEVEL
```

Tenant-varying behavior never goes here. It goes in `settings` (ADR-054). If a value needs to differ per company, it is a setting, not an env var.

### 5.2 Frontend Build

The frontend is part of the same application, so it has no separate environment file. Vite reads the values it needs from the Laravel `.env` via the `VITE_` prefix:

```text
VITE_APP_NAME="${APP_NAME}"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Anything prefixed `VITE_` is compiled into the JavaScript bundle and is therefore public. No secret ever carries that prefix.

Per-request context (active company, locale, CSRF token, permissions) is passed from Blade into `window.App`, not through the build. It varies per user and cannot live in a bundle.

### 5.3 Third-Party Front-End Packages

All front-end dependencies are open source and install from the public npm registry. No licensed token or private registry is required, and `npm install` works from a fresh clone with no credentials.

| Package | Licence | Purpose |
|---|---|---|
| `@coreui/coreui` | MIT | Admin layout and components |
| `bootstrap` | MIT | CSS framework |
| `@coreui/icons` | CC BY 4.0 (icons), MIT (code) | Icon set |
| `axios` | MIT | Single HTTP client |
| `dayjs` | MIT | Dates and timezones |
| `tom-select` | Apache-2.0 | Selects, multi-select, remote autocomplete |
| `flatpickr` | MIT | Date range inputs |
| `fullcalendar` | MIT | Maintenance schedule view |
| `chart.js` | MIT | Dashboard and report charts |
| `idb-keyval` | Apache-2.0 | Offline draft store |
| `laravel-echo`, `pusher-js` | MIT | Reverb WebSocket client |

No CoreUI PRO package is added, and no commercial asset is copied into the repository. A build must never depend on a licensed file that a fresh clone cannot install. If a commercial component is judged necessary later, the licence is purchased first.

---

## 6. Local Development Setup

### 6.1 Services

`docker-compose.yml` provides: `mysql:8`, `redis:7`, `minio` (S3-compatible), `mailpit` (mail catcher), plus `app`, `queue`, `scheduler`, and `reverb` containers.

MinIO and Mailpit exist so no developer needs cloud credentials, and so no test email ever reaches a real address.

### 6.2 First Run

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
npm install && npm run dev            # Vite, same repository
```

`migrate --seed` loads the platform seed (permissions, roles, setting definitions, locales) plus a demo tenant (`09-Seed-Data-Catalog.md`). `npm install` needs no credentials (Section 5.3).

### 6.3 Standing Processes

| Process | Command | Note |
|---|---|---|
| API | `php artisan serve` (or nginx in container) | — |
| Queue | `php artisan horizon` | Nothing async works without it |
| Scheduler | `php artisan schedule:work` | Without it, no maintenance schedules are generated and nothing looks broken until someone checks |
| Reverb | `php artisan reverb:start` | Real-time only |
| Vite dev server | `npm run dev` | Hot reload for Blade, JS, and SCSS |

The scheduler being optional in development is a trap: a developer who never runs it will not notice that schedule generation is broken. It is included in `docker compose up` for that reason.

### 6.4 Quality Commands

```bash
php artisan test                    # full suite
php artisan test --group=tenancy    # tenant isolation only, run before every push
./vendor/bin/pint                   # code style
./vendor/bin/phpstan analyse        # static analysis
php artisan openapi:generate        # regenerate openapi.yaml from routes and requests
npm run build                       # production assets
php artisan dusk                    # browser tests
```

`openapi.yaml` is generated, never hand-edited. A pull request that changes a request or resource class and does not update the generated file fails CI. This holds even though the web UI is server-rendered: the API is a delivered product surface, not a by-product.

---

## 7. Definition of Done

A module is done when all of the following hold. Partial completion is not done.

1. Migrations written, reversible, and running in CI.
2. Models, policies, actions, and services in place with no business logic in controllers.
3. Every endpoint declares a permission and has both a positive and a negative authorization test.
4. A cross-tenant access test exists for every tenant-scoped endpoint.
5. Domain rules from the SRS are covered by tests, listed in SRS 55.1.
6. Request and resource classes are complete, so the generated OpenAPI schema is accurate.
7. Seed data exists for the module's master data.
8. Audit logging fires on every state change.
9. Both English and Bengali strings exist for anything user-facing.
10. Blade screens exist for the module, with `@can` guards, Bengali strings, and a Dusk test for the primary flow.
11. Web and API controllers both delegate to the same Action. A rule implemented in a controller instead of an Action is a defect, because it would apply to only one entry point.
