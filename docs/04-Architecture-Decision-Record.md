# 04-Architecture-Decision-Record.md
# Architecture Decision Record (ADR)
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.1  
**Status:** Accepted  
**Date:** 2026-07-26 (revised 2026-08-18)  

---

## ADR-001: Product Architecture

### Decision
Build the platform as a multi-tenant SaaS application.

### Rationale
The product must support multiple garment companies and factories while maintaining strict data isolation.

### Consequence
Tenant context is a first-class application concern.

---

## ADR-002: Frontend — SUPERSEDED by ADR-066

### Decision
~~Use Next.js with TypeScript.~~ Superseded 2026-08-18. The frontend is server-rendered Laravel Blade with Bootstrap 5 and CoreUI 5 Free. See ADR-066 for the decision and its rationale. The original entry is retained below because the API-first consequence it records still stands.

### Rationale
Provides strong frontend architecture, routing, server/client rendering options, TypeScript safety, and scalable dashboard development.

### Consequence
Frontend communicates with Laravel through versioned REST APIs and subscribes to Reverb WebSocket channels for real-time updates.

---

## ADR-003: Backend

### Decision
Use Laravel 13.

### Rationale
Laravel provides mature authentication, authorization, queues, notifications, events, scheduling, validation, filesystem abstraction, and ecosystem support.

### Consequence
Business logic must be structured into Services/Actions/Policies rather than concentrated in controllers.

---

## ADR-004: Database

### Decision
Use MySQL 8+.

### Rationale
Strong transactional support, mature tooling, broad hosting availability, and suitability for relational maintenance/inventory workloads.

### Consequence
Financial and inventory operations require transactions and carefully designed indexes.

---

## ADR-005: Multi-Tenancy

### Decision
Use shared database with logical tenant isolation for MVP.

### Rationale
Simplifies operations and reduces infrastructure overhead.

### Enforcement
- Tenant middleware
- Company membership
- Policies
- Query scopes
- Tenant-aware services
- Tenant-safe relationships
- WebSocket authorization

### Future
Database-per-tenant may be introduced for selected enterprise customers if required.

---

## ADR-006: Asset-Centric Model

### Decision
Use `assets` as the core entity. Machines are an asset type.

### Rationale
The platform must eventually manage generators, boilers, compressors, electrical equipment, safety assets, and calibration equipment.

### Consequence
Machine-specific features are implemented as asset capabilities, not as an isolated machine table.

---

## ADR-007: Parent/Child Assets

### Decision
Support recursive asset hierarchy using `parent_asset_id`.

### Rationale
Complex equipment contains sub-assets/components.

### Constraint
Prevent circular parent-child relationships.

---

## ADR-008: Real-Time Communication

### Decision
Use Laravel Reverb and WebSockets.

### Rationale
Keeps real-time infrastructure within the Laravel ecosystem and avoids mandatory dependency on a third-party real-time provider.

### Consequence
REST remains the source of truth. WebSockets deliver events and UI updates.

---

## ADR-009: Redis

### Decision
Use Redis for cache, queue backend, rate limiting where appropriate, and real-time support.

### Rationale
High performance and mature Laravel integration.

### Consequence
Redis is not the permanent source of business data.

---

## ADR-010: Queue Processing

### Decision
Use Laravel Queue with Redis and monitor through Horizon.

### Jobs
- Notifications
- Escalations
- Large report generation
- Import processing
- Export generation
- Webhook delivery
- Email delivery

---

## ADR-011: Scheduled Tasks

### Decision
Use Laravel Scheduler.

### Tasks
- Generate maintenance schedules
- Detect due/overdue maintenance
- Run escalation rules
- Warranty expiry alerts
- AMC expiry alerts
- Subscription state transitions
- Usage aggregation

---

## ADR-012: Maintenance Scheduling

### Decision
Support both rolling and fixed schedules.

### Rationale
Factories use different maintenance policies.

### Combined Trigger
Support OR/AND rules. The plan stores `rule_logic` explicitly; there is no implicit default. Semantics:
- "Whichever occurs first" = OR
- "Both conditions required" = AND

---

## ADR-013: Meter Data

### Decision
Store meter readings as append-only measurements.

### Rationale
Historical meter data is required for audit and maintenance calculations.

### Consequence
Meter reset is a separate auditable event.

---

## ADR-014: Inventory Accounting

### Decision
Use immutable inventory transaction ledger with weighted-average cost for MVP.

### Rationale
Provides traceability without implementing a full accounting system.

### Future
FIFO or other costing methods may be added.

---

## ADR-015: Financial Costing

### Decision
Use append-only cost entries.

### Rationale
Financial history should not be silently overwritten.

### Corrections
Use adjustment/reversal entries.

---

## ADR-016: Accounting Depreciation

### Decision
Do not implement a full accounting depreciation ledger in MVP.

### Rationale
Maintenance management and accounting are distinct domains.

### Future
Provide integration points for accounting/ERP systems.

---

## ADR-017: Notifications

### Decision
Persist notifications in database and broadcast real-time events.

### Rationale
Users must see notification history even if they were offline.

### Channels
- In-app
- WebSocket
- Email
- Future SMS/WhatsApp

---

## ADR-018: WebSocket Isolation

### Decision
Use private company, factory, and user channels.

### Security
Server-side authorization is mandatory.

### Consequence
Cross-tenant event leakage is prevented.

---

## ADR-019: File Storage

### Decision
Use private S3-compatible object storage.

### Rationale
Scalable and secure.

### Access
Temporary signed URLs.

---

## ADR-020: Document Versioning

### Decision
Version important documents.

### Rationale
Manuals, contracts, and compliance documents may change over time while historical records must remain reproducible.

---

## ADR-021: Authentication

### Decision
Use Laravel Sanctum for API authentication.

### Rationale
Suitable for first-party session-based web UI plus token-based API clients. Since ADR-066 the web UI uses Laravel session and CSRF; Sanctum tokens serve API clients and any future mobile app.

---

## ADR-022: Authorization

### Decision
Use RBAC plus policy/resource-level authorization.

### Rationale
Factory users require more granular control than simple role checks.

---

## ADR-023: API Versioning

### Decision
Use `/api/v1`.

### Rationale
Allows backward-compatible evolution.

---

## ADR-024: Idempotency

### Decision
Support idempotency keys on critical create/payment operations.

### Rationale
Mobile/browser retries can otherwise create duplicate breakdowns, work orders, inventory transactions, or payments.

---

## ADR-025: Optimistic Locking

### Decision
Use version fields on high-conflict resources.

### Rationale
Prevents silent overwriting of concurrent updates.

---

## ADR-026: Timezone

### Decision
Store timestamps in UTC; schedule using factory timezone.

### Rationale
Factories may operate across regions.

---

## ADR-027: Currency

### Decision
Store transaction currency and base-currency equivalent.

### Rationale
A multinational organization may purchase assets in one currency and maintain them in another.

---

## ADR-028: Subscription Model

### Decision
Use custom contract-based subscriptions without mandatory fixed plans.

### Rationale
Garment groups may negotiate pricing based on factories, assets, users, or contract terms.

### Consequence
Usage metrics are tracked independently from pricing.

---

## ADR-029: Subscription Lifecycle

### Decision

Active
→ Grace Period
→ Read Only
→ Archived

### Rationale
Customer data should not be immediately deleted after payment failure or cancellation.

---

## ADR-030: Data Ownership

### Decision
Customer owns tenant data.

### Consequence
Data export and retention policies are required.

---

## ADR-031: Import/Export

### Decision
Provide validated bulk import and permission-controlled export.

### Rationale
Factories often have legacy spreadsheets and large installed-asset lists.

---

## ADR-032: Reports

### Decision
Small reports may execute synchronously; large reports run as background jobs.

### Rationale
Prevents long-running HTTP requests and improves reliability.

---

## ADR-033: Search

### Decision
Start with indexed MySQL search.

### Future
Introduce Meilisearch/Elasticsearch if scale or fuzzy search requirements justify it.

---

## ADR-034: Offline Readiness

### Decision
Design APIs for retry-safe operations and PWA compatibility.

### MVP
No full offline synchronization.

### Future
Offline work order/checklist execution can be added.

---

## ADR-035: Integration Strategy

### Decision
API-first architecture with webhooks.

### Future Integrations
- ERP
- HRM
- Production
- Accounting
- IoT

---

## ADR-036: IoT

### Decision
Do not require IoT for MVP.

### Future
IoT devices can publish meter/status data through a dedicated ingestion service or gateway.

---

## ADR-037: Predictive Maintenance

### Decision
Keep data model ready for predictive analytics but do not implement AI in MVP.

### Future Inputs
- Failure history
- Meter readings
- Downtime
- Condition readings
- Maintenance history

---

## ADR-038: Compliance and Inspection

### Decision
Keep asset and checklist architecture extensible for inspection, calibration, safety, boiler, and fire compliance.

---

## ADR-039: Backup and Disaster Recovery

### Decision
Implement:
- Daily database backups
- File backups
- Retention policy
- Periodic restore testing

### Target
RPO/RTO must be defined by the production infrastructure plan.

Superseded by ADR-062. The original recommendation was:
- RPO: 24 hours maximum
- RTO: 4 hours maximum

Enterprise contracts may require stronger targets.

---

## ADR-040: Deployment

Recommended topology:

```text
Internet
  ↓
Cloudflare
  ↓
Nginx / Load Balancer
  └── Laravel (Blade UI + /api/v1)
       ├── MySQL
       ├── Redis
       ├── Reverb
       ├── Queue Workers
       └── Scheduler
```

Docker is the standard deployment unit.

---

## ADR-041: Observability

Required:
- Application logs
- Error monitoring
- Queue monitoring
- Audit logs
- Health checks
- Database monitoring
- Infrastructure monitoring

---

## ADR-042: Security Principles

1. Never trust client tenant IDs.
2. Never expose private files directly.
3. Never allow cross-tenant WebSocket subscriptions.
4. Never hard-delete financial/audit history.
5. Never mutate posted inventory transactions.
6. Never bypass policies in exports or reports.
7. Validate all uploaded files.
8. Rate-limit authentication and sensitive endpoints.
9. Keep secrets outside source control.
10. Use least-privilege access.

---

## ADR-043: Recommended Project Boundaries

### Blade + CoreUI (amended by ADR-066)
- Server-rendered UI
- Client state limited to technician offline drafts
- Single Axios client for AJAX
- WebSocket client
- Route protection via Laravel middleware and `@can`
- Form rendering; validation defined once in FormRequest classes

### Laravel
- Authentication
- Authorization
- Domain rules
- Persistence
- Events
- Notifications
- Queues
- Scheduling
- Reports
- Imports/exports
- Subscription lifecycle

### MySQL
- Durable relational data

### Redis
- Cache
- Queues
- Temporary state

### Reverb
- Real-time transport

### Object Storage
- Documents and media

---

## ADR-044: Development Principles

1. API-first.
2. Tenant-first.
3. Asset-centric.
4. Event-driven where useful.
5. Transactional inventory.
6. Auditable business operations.
7. Immutable financial history.
8. Secure-by-default.
9. Extensible for integrations.
10. Avoid premature microservices.

---

## ADR-045: Initial Deployment Strategy

Start as a modular monolith:

Laravel modular monolith (Blade + CoreUI UI, and /api/v1)
+
MySQL
+
Redis
+
Reverb

Do not split into microservices at MVP stage.

### Rationale
The domain is complex but does not initially require independent service deployment. A modular monolith reduces operational complexity while preserving clean boundaries.

---

## ADR-046: Future Scaling Path

If scale requires:
- Horizontal Laravel API instances
- Separate queue workers
- Dedicated Reverb nodes
- Read replicas
- Dedicated reporting database
- Search service
- IoT ingestion service

The current architecture should allow these without changing the public API contract.

---

## ADR-047: Final Architecture Summary

```text
                    SaaS Platform
                         |
                  Tenant Context
                         |
              Company / Factory Scope
                         |
       +-----------------+----------------+
       |                 |                |
     Assets         Maintenance       Inventory
       |                 |                |
   Lifecycle        Work Orders       Spare Parts
       |                 |                |
   Transfers        Breakdown          Costing
       |                 |                |
   Documents       Downtime          Transactions
       +-----------------+----------------+
                         |
                  Events / Queues
                         |
              Redis + Laravel Horizon
                         |
                 Laravel Reverb
                         |
                 Blade + CoreUI UI
                         |
                    MySQL Data
```

---


## ADR-048: Working Calendar as a First-Class Model

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 required configurable downtime rules and an availability formula "based on scheduled production time", but modelled no shift, break, or holiday data. Every availability and MTTR figure would therefore have been computed against wall-clock time. In a factory running one 10-hour shift, a breakdown reported near shift end would have recorded roughly 14 hours of phantom downtime overnight, making availability meaningless and the product untrustworthy to the exact managers it is sold to.

### Decision
Model shifts, breaks, weekly off-days, holidays, and per-line overrides as effective-dated tenant data. All duration-based metrics resolve against the factory calendar.

### Consequences
- Downtime, MTTR, response time, escalation timers, and PM due-date shifting all depend on one calendar service.
- Calendar edits are versioned; closed periods are never retroactively recomputed.
- A factory with no calendar falls back to continuous operation, and reports must say so rather than silently reporting a different basis.

---

## ADR-049: Planned Versus Unplanned Downtime

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 treated downtime as a single quantity. Preventive maintenance necessarily stops a machine. Counting planned stoppages against availability punishes factories for doing maintenance, which inverts the incentive the product exists to create.

### Decision
Every downtime record carries a class: `UNPLANNED`, `PLANNED`, `NON_OPERATING`, or `EXTERNAL`, plus an optional tenant-defined reason code. Planned downtime is excluded from availability by default, configurable per company and factory.

### Consequences
- Availability figures are defensible and comparable across factories.
- Reason codes make the leading causes of stoppage reportable, which is the analysis customers actually buy the system for.
- Every downtime row must be classified; an unclassified row defaults to `UNPLANNED` and is flagged, never silently dropped.

---

## ADR-050: Labor Entries as the Source of Cost and KPIs

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 defined `work_orders.actual_cost` and required technician KPIs including average repair time, but modelled no table recording who worked, for how long, at what rate. The cost field would have been a number someone typed, and every technician KPI would have been uncomputable.

### Decision
Introduce `work_order_labor_entries` and `work_order_parts`. `actual_cost` is derived from them and is never accepted from a client.

### Consequences
- Rates are frozen on each entry, so historical costs stay reproducible when rates change.
- Technician utilization, cost per work order, and cost per asset all become computable from one source.
- Overlapping labor entries for one technician are rejected, which makes utilization figures trustworthy.

---

## ADR-051: Hold Time Excluded From Repair Time

### Status
Accepted. Closes a gap in v1.0.

### Context
The v1.0 work order flow contained a state named `Pending` with no definition. In practice work stops for a specific reason: waiting for a part, an approval, a vendor, or the end of a shift. Counting that wait as repair time inflates MTTR and hides the actual constraint.

### Decision
Rename the state to `On Hold`, require a reason code, and record each hold in `work_order_holds`. MTTR excludes hold time. Total downtime still includes it, because the machine really was down.

### Consequences
- MTTR measures repair capability; hold time measures supply and process constraints. The two are reported separately.
- Hold reason analysis directly identifies spare-part shortages that drive downtime, which is a primary business case for the inventory module.

---

## ADR-052: Locations as Entities, Not Polymorphic Pointers

### Status
Accepted. Supersedes an ambiguity in v1.0.

### Context
v1.0 defined both `assets.current_location_type` / `current_location_id` and an `asset_locations` table. Two competing models would have been implemented inconsistently, and transfer history pointing at a deleted workstation would dangle.

### Decision
`asset_locations` is the single addressable location entity. An asset holds one `asset_location_id`. The polymorphic columns are removed.

### Consequences
- The relationship is enforceable by a real foreign key.
- Locations gain their own code and QR label, which factories need for floor-level scanning.
- Location deletion is blocked while assets reference it.

---
## ADR-053: Bilingual Operation From Day One

### Status
Accepted. Closes a gap in v1.0.

### Context
The target market is Bangladeshi garment factories. Technicians, storekeepers, and line supervisors, the highest-volume users of the system, largely do not work in English. v1.0 never mentioned language. Retrofitting localization after the UI, notification templates, and PDF pipeline are built is expensive and usually done badly.

### Decision
English and Bengali are both first-class from the first release. Locale is a user attribute defaulting to a company setting. Server-generated text is rendered in the recipient locale. `utf8mb4_unicode_ci` throughout, and export pipelines embed a font with Bengali coverage.

### Consequences
- No user-facing string is hard-coded in either language.
- API error `code` values stay locale-independent so clients branch on them safely.
- PDF and Excel export must be tested with Bengali content; boxes instead of glyphs is a release blocker.

---

## ADR-054: Configuration as Tenant Data

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 used the word "configurable" for downtime rules, availability formulas, approval thresholds, negative stock, escalation, and grace periods, without saying where any of it lives. Left unspecified, it becomes environment variables, which cannot vary per tenant, or scattered columns, which cannot be reasoned about.

### Decision
A `settings` table with resolution order platform, company, factory, line, and a seeded `setting_definitions` catalog constraining which keys exist and what types they hold.

### Consequences
- An unknown key is rejected rather than silently stored.
- The effective value and the level that defined it are both resolvable, so an administrator can answer "why is it behaving this way".
- Setting changes are audited, because several of them change how money and KPIs are computed.

---

## ADR-055: Race-Safe Document Numbering

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 required `work_order_number`, `invoice_number`, `claim_number`, and `contract_number` without specifying generation. The common implementations, `MAX(number) + 1` or a count query, both produce duplicates under concurrent creation, which is guaranteed on a shop floor where several people report breakdowns at once.

### Decision
A `number_sequences` table with atomic increment under a row lock, configurable format and reset period per company and document type.

### Consequences
- Gaps are accepted as the cost of correctness; duplicates are not.
- Numbers are display identifiers only. API identifiers stay ULIDs, so a sequential number never becomes an enumerable resource id.

---

## ADR-056: Idempotency Key Storage and Conflict Semantics

### Status
Accepted. Refines ADR-024.

### Context
ADR-024 mandated idempotency keys but defined no storage, no scope, no expiry, and no behavior when the same key arrives with a different body or while the first request is still running.

### Decision
An `idempotency_keys` table scoped to `(company, endpoint, key)` storing a request hash and the original response. Same key with same body replays the stored response; same key with a different body returns `409`; a concurrent replay returns `409`; keys expire after 24 hours.

### Consequences
- Every stock and money movement endpoint is safe to retry, which matters on unreliable factory connectivity.
- Side effects are covered by the key, so a retried breakdown report does not notify twice.

---

## ADR-057: Soft Delete Boundaries

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 said to use `deleted_at` "where appropriate" without saying where. Soft delete applied indiscriminately breaks unique constraints, hides rows from aggregates inconsistently, and creates the illusion that financial history is deletable.

### Decision
Soft delete applies only to `companies`, `users`, `assets`, `spare_parts`, `vendors`, `technicians`, and master data. Transactional, ledger, audit, and financial tables have no `deleted_at`; they are archived, never deleted. Unique constraints on soft-deletable tables include `deleted_at` so a code can be reused after archival.

### Consequences
- Every query on a soft-deletable table must be explicit about whether it includes archived rows; reports state which basis they use.
- Deleting master data referenced by history is rejected rather than cascaded.

---

## ADR-058: Precomputed KPI Snapshots

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 set a p95 target under 500 ms and noted dashboards "should use caching where necessary". At the stated volumes, computing availability or MTBF for a factory over a year means scanning millions of downtime and work order rows per dashboard load. Caching the result of a slow query does not fix the first request, nor the cold cache after every write.

### Decision
A scheduled job writes `kpi_snapshots` per scope and period. Closed periods are computed once; the current period is refreshed on a short interval. Dashboards and reports read snapshots, and both resolve through one shared KPI service so a number is identical wherever it appears.

### Consequences
- Recalculation after a rule change is an explicit, audited backfill that writes a new calculation version.
- Ad-hoc custom date ranges still compute live and are routed to the report job queue when they exceed a size threshold.

---

## ADR-059: Testing and CI Strategy

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 asked for "automated tests for critical business rules" without naming them or defining a gate. In a multi-tenant system, the untested rule that matters most is tenant isolation, and it fails silently.

### Decision
The mandatory coverage list in SRS 55.1, enforced in CI. No merge with a failing tenant-isolation or authorization test. Migrations must be reversible and must run against a seeded database in CI. Static analysis and style checks are gates, not suggestions.

### Consequences
- Every new tenant-scoped endpoint ships with a cross-tenant access test, or it does not ship.
- A representative seed dataset is maintained as a build artifact for load testing and demos.

---

## ADR-060: Deployment Pipeline and Environments

### Status
Accepted. Closes a gap in v1.0.

### Context
ADR-040 described a deployment topology but no pipeline, environments, migration strategy, or rollback path.

### Decision
Three environments: development, staging, and production. Staging mirrors production configuration and runs against anonymized data. Deployment is containerized and immutable: a build is promoted, never rebuilt per environment.

Migration policy is expand, then migrate, then contract. A schema change is deployed in a backward-compatible form first, so the previous application version keeps running during rollout and rollback does not require a down migration on production data. Destructive changes are a separate, later deployment.

### Consequences
- Rollback is redeploying the previous image, never reversing a data migration under pressure.
- Long-running backfills run as queued, resumable jobs rather than inside a migration.
- Queue workers and the scheduler are deployed and scaled independently from the web tier, and workers are drained before a deploy so a job is never killed mid-execution.

---

## ADR-061: Observability Implementation

### Status
Accepted. Refines ADR-041.

### Context
ADR-041 listed what to monitor but named no mechanism, no correlation strategy, and no alert thresholds.

### Decision
- Structured JSON logs carrying `request_id`, `company_id`, and `user_id` on every line.
- One `request_id` correlates the HTTP request, the jobs it dispatched, and the audit rows it wrote.
- Error tracking with release tagging, so a regression is attributable to a deploy.
- Alerts on p95 latency breach, error rate, queue depth and job age, failed job count, webhook failure rate, scheduler heartbeat miss, disk and connection saturation, and any cross-tenant authorization failure.

### Consequences
- A support ticket citing one request id resolves to the full causal chain.
- Logs never contain passwords, tokens, secrets, or full personal records.
- A missed scheduler run is alerted, because silent scheduler failure means maintenance stops being generated and nobody notices until an audit.

---

## ADR-062: Backup Verification and Recovery Objectives

### Status
Accepted. Refines ADR-039.

### Context
ADR-039 set an RPO of 24 hours and an RTO of 4 hours. A 24-hour RPO means a failure at end of shift discards a full day of breakdown, work order, and inventory records that were captured on the floor and exist nowhere else.

### Decision
Tighten to RPO 15 minutes using binary log shipping in addition to daily full backups, and keep RTO at 4 hours. Restore testing is automated monthly against a scratch environment, and a restore that fails is a production incident.

### Consequences
- Point-in-time recovery becomes possible, which also covers accidental bulk deletion by a tenant administrator.
- Object storage is versioned with a deletion hold, so a deleted document is recoverable within the retention window.
- Backups are encrypted, and restore credentials are held separately from application credentials.

---

## ADR-063: Search Strategy Boundary

### Status
Accepted. Refines ADR-033.

### Context
ADR-033 chose indexed MySQL search with a possible future move to Meilisearch or Elasticsearch, but set no trigger for that move.

### Decision
Stay on MySQL. Asset, part, and work order lookup are prefix and exact-match searches on indexed, tenant-scoped columns, which MySQL handles well at the target volumes.

Introduce a dedicated search service only when a concrete requirement appears that MySQL cannot serve: cross-entity fuzzy search, typo tolerance, or relevance ranking across more than 100,000 assets per tenant.

### Consequences
- No second datastore to keep synchronized, and no tenant-isolation surface on a system that does not enforce it natively.
- If a search service is added later, tenant scope must be a filter applied server-side, never a client-supplied parameter.

---

## ADR-064: No Client-Supplied Derived Values

### Status
Accepted. Closes a gap in v1.0.

### Context
v1.0 listed `actual_cost`, `inventory_balances`, `total_downtime_minutes`, and similar derived fields as ordinary columns. Anything a client can write, a client will eventually write wrongly, and a derived total that disagrees with its source records is worse than having no total.

### Decision
Derived values are computed server-side from their source records and are never accepted in a request body. The API exposes no endpoint that sets them directly. `company_id` is likewise never accepted from a client.

### Consequences
- A work order's cost always equals the sum of its labor and part lines by construction.
- Inventory balances always replay from the ledger; a mismatch is a bug with a defined detection query, not a data entry difference.
- Correcting a derived value means correcting its source records, which leaves an audit trail.

---

## ADR-065: Grade-Based Labor Rates, No Payroll Data

### Status
Accepted. Refines ADR-050.

### Context
ADR-050 established labor entries as the source of maintenance cost, and specified a rate on the technician record. That left the rate's meaning open, and the obvious reading is an individual's actual pay.

The product is a maintenance and machine tracking system. Storing per-person compensation in it has three costs and no benefit the product needs:

1. Every maintenance manager who can read a work order's cost breakdown can then derive a colleague's pay.
2. The system inherits payroll's compliance and access-control obligations without being built for them.
3. It duplicates data that HR already owns authoritatively, so the two will drift.

The only thing maintenance genuinely needs from labor cost is comparability: which machine consumes the most repair effort, and is repair cheaper than replacement.

### Decision
Labor cost is computed from `labor_rate_grades`, an effective-dated standard rate per skill grade, per company and optionally per factory. Technicians reference a grade. No salary, wage, bonus, deduction, or payroll identifier exists anywhere in the schema or the API.

External contractor labor is exempt because it is a vendor's invoiced charge, not employee compensation.

### Consequences
- Cost per machine, per work order, and per asset lifecycle stay computable, which is what repair-versus-replace decisions require.
- Two technicians on the same grade cost the same. This is a deliberate modelling choice, not an approximation to be refined later.
- Grade rates are effective-dated, so a rate change never rewrites the cost of work already recorded.
- Payroll-accurate costing, if a customer ever requires it, is an HR system integration rather than a column here.
- Technician KPIs are correspondingly bounded: work metrics only, no attendance or appraisal data, individual figures behind a separate permission (SRS 25.2).

---

## ADR-066: Server-Rendered Blade Frontend with CoreUI

### Status
Accepted 2026-08-18. **Supersedes ADR-002.** Amends ADR-043 and ADR-034.

### Context
ADR-002 chose Next.js with TypeScript. The customer has an existing Laravel and Bootstrap codebase, an in-house team fluent in that stack, and a reference design already selected: the CoreUI 5 Bootstrap admin template.

Weighed against the Next.js option:

- Roughly 60% of this product is CRUD over tables and forms. Blade with a mature admin template delivers that materially faster than equivalent React screens.
- One codebase, one language, one deployment unit. No Node runtime, no CORS configuration, no SPA cookie handling, no second build pipeline.
- Laravel developers are abundant and affordable in the target market; TypeScript and React developers are neither.
- Server-rendered HTML gives a faster first paint on the mid-range Android phones technicians actually use, provided the page stays disciplined about plugins.
- Validation is defined once in FormRequest classes instead of twice, in Laravel and in Zod.

The costs are real and accepted:

- The technician mobile screens need genuine client-side state (offline drafts, a retry queue, per-answer autosave). A server-rendered template does not provide this; it is built deliberately, as its own workstream (Frontend 6).
- Imperative DOM manipulation is the known decay path for this stack. Mitigated by re-fetching server-rendered partials on change rather than patching individual nodes.
- The reference demo showcases CoreUI PRO components. The requirement is the *design*, not those components, so the build uses CoreUI Free (MIT) plus named MIT alternatives — Tom Select, Flatpickr, FullCalendar, Chart.js — and server-rendered tables. No licence is required and no commercial asset enters the repository.

### Decision
The frontend is server-rendered Laravel Blade with Bootstrap 5 and the CoreUI 5 Free admin template, built with Vite. There is no separate SPA and no commercial component dependency.

Three substitutions are made against the customer's prior stack, each for a specific reason rather than preference:

1. **One HTTP client (Axios), not Axios plus jQuery AJAX.** Two stacks means two interceptor chains for `X-Company-Id`, `X-Request-Id`, CSRF, and `Idempotency-Key`. One will eventually be missed, and a write that skips the idempotency header duplicates inventory or breakdown records under retry — the exact failure ADR-024 exists to prevent.
2. **Day.js replaces Moment.js.** Moment is in maintenance mode and recommends alternatives in its own documentation. This system's correctness rests on timezone handling: UTC storage, factory-timezone scheduling, shift calendars, DST boundaries (ADR-026, ADR-048). Building availability figures on an end-of-life date library is an avoidable risk.
3. **One icon set (CoreUI Icons).** Font Awesome, CoreUI Icons and Simple Line Icons overlap almost entirely and together add 150-300 KB of font files, competing directly with the Bengali webfont, which is not optional.

Bootstrap 5 and CoreUI 5 do not require jQuery. It may be included for developer familiarity, at roughly 30 KB gzipped, but nothing in the specification depends on it. `bootstrap-datepicker` is dropped: it targets Bootstrap 3 and 4 and is replaced by native date inputs, with Flatpickr where a range picker is needed.

### Consequences

- **The REST API is still built in full.** SRS 42-43 and ADR-001 are unchanged: ERP, HRM, production, accounting, and IoT integration and any future mobile client depend on it. It is not duplicated work, because business logic lives in Actions and Services (ADR-003) that both the web controller and the API controller call. A rule written in a controller instead of an Action is a defect, because it would apply to only one entry point.
- The API is tested independently. "The screen works" is not evidence the API works, and an untested API is the predictable failure mode of a Blade-first project.
- `openapi.yaml` continues to be generated and remains the integration contract.
- Authentication moves from Sanctum bearer tokens to Laravel session and CSRF for the web UI. Sanctum tokens remain for API clients and future mobile use.
- Localization moves server-side, which removes a client-side message bundle entirely.
- Testing moves to Pest and Laravel Dusk. `08-API-Schemas.md` and `openapi.yaml` are unaffected.
- ADR-034 is amended: PWA scope narrows to the technician screens, which is where offline drafts are actually needed.
- Frontend performance now depends on plugin discipline rather than on a bundler. Every new JS dependency requires a stated reason and a bundle-size check in CI (Frontend 10.3).

### Revisit If
A native mobile application is committed to, or a second consumer of the UI appears. Neither changes the API, which is why building it properly now keeps that door open.

---

## Final Architecture Decision

The approved MVP architecture is:

**Laravel Blade + Bootstrap 5 + CoreUI 5 Free**
for the server-rendered web UI and dashboards (ADR-066).

**Laravel 13**
for API, domain logic, authorization, scheduling, events, notifications, and billing lifecycle.

**MySQL**
for durable transactional data.

**Redis**
for caching and queues.

**Laravel Horizon**
for queue monitoring.

**Laravel Reverb**
for secure real-time WebSocket events.

**S3-compatible private storage**
for documents.

**Docker + Nginx**
for deployment.

The system starts as a modular monolith with strict module boundaries and is designed to scale horizontally before considering microservices.

---

## ADR Index

| ADR | Subject | Status |
|---|---|---|
| 001-047 | Original v1.0 decisions | Accepted |
| 048 | Working calendar as a first-class model | Accepted (new) |
| 049 | Planned versus unplanned downtime | Accepted (new) |
| 050 | Labor entries as the source of cost and KPIs | Accepted (new) |
| 051 | Hold time excluded from repair time | Accepted (new) |
| 052 | Locations as entities, not polymorphic pointers | Accepted (supersedes v1.0 ambiguity) |
| 053 | Bilingual operation from day one | Accepted (new) |
| 054 | Configuration as tenant data | Accepted (new) |
| 055 | Race-safe document numbering | Accepted (new) |
| 056 | Idempotency key storage and conflict semantics | Accepted (refines 024) |
| 057 | Soft delete boundaries | Accepted (new) |
| 058 | Precomputed KPI snapshots | Accepted (new) |
| 059 | Testing and CI strategy | Accepted (new) |
| 060 | Deployment pipeline and environments | Accepted (extends 040) |
| 061 | Observability implementation | Accepted (refines 041) |
| 062 | Backup verification and recovery objectives | Accepted (refines 039, tightens RPO) |
| 063 | Search strategy boundary | Accepted (refines 033) |
| 064 | No client-supplied derived values | Accepted (new) |
| 065 | Grade-based labor rates, no payroll data | Accepted (refines 050) |
| 066 | Server-rendered Blade frontend with CoreUI Free | Accepted (supersedes 002; amends 034, 043) |

Any future change to tenant isolation, maintenance scheduling, inventory costing, KPI definitions, the working calendar, subscription lifecycle, or core architecture requires a new ADR rather than an edit to an existing one.
