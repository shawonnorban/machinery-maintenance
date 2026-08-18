# 05-Gap-Analysis-and-Traceability.md
# Gap Analysis and Traceability
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Review date:** 2026-08-18 (first pass: specification completeness; second pass: buildability)
**Reviewed:** `README.md`, `01-SRS.md`, `02-Database-ERD.md`, `03-API-Specification.md`, `04-Architecture-Decision-Record.md` at v1.0
**Result:** 64 gaps found and closed in v1.1. A second, buildability-focused review (Section 10) found 14 more, also closed. The frontend stack was subsequently changed to Laravel Blade + CoreUI (Section 11, ADR-066). 7 business decisions remain open (Section 5), plus 4 frontend items (Section 11.5).

---

## 1. Summary

The v1.0 specification set was broad and internally consistent in tone, but it had three classes of problem:

1. **Requirements with no data model behind them.** The documents asked for labor cost, technician KPIs, assigned teams, approval-gated work orders, work order parts, and configurable behavior, but defined no table for any of them. These are not omissions of detail; a developer reading v1.0 could not have built them.

2. **Metrics defined in words rather than in arithmetic.** Availability was "a configurable formula". Downtime had no planned versus unplanned distinction and no working calendar. Two engineers implementing the same KPI from v1.0 would have produced different numbers, and neither would have been defensible to a factory manager.

3. **Cross-document contradictions.** Two competing location models, two competing breakdown-to-work-order links, reservation transaction types that contradicted the reservation table, and API endpoints with no backing tables.

Alongside these, the set was silent on several things a Bangladeshi garment-factory SaaS cannot ship without: language, retention, capacity assumptions, document numbering, rate limit values, and a test strategy.

---

## 2. Severity Classification

- **P1 — Blocks implementation.** A developer cannot build the feature from the specification.
- **P2 — Produces wrong behavior.** Buildable, but the result would be incorrect, unsafe, or misleading.
- **P3 — Incomplete.** Buildable and correct, but under-specified in a way that causes rework.

---

## 3. Gaps Found and Closed

### 3.1 Missing Data Model (P1)

| # | Gap | Evidence in v1.0 | Closed by |
|---|---|---|---|
| 1 | No labor time or rate table | SRS 13 required `estimated_labor_cost` and `actual_cost`; SRS 25 required "average repair time" per technician. No table recorded who worked, how long, at what rate. | ERD `work_order_labor_entries`; `technicians.hourly_rate`; SRS 13.2; API 11; ADR-050 |
| 2 | No work order parts table | API 11 exposed `GET /work-orders/{id}/parts`. Nothing backed it. Parts cost had no source. | ERD `work_order_parts`; SRS 13.3; API 11; ADR-050 |
| 3 | No teams table | SRS 10 and 13 referenced "assigned team" repeatedly. No `teams`, no `team_members`, no `assigned_team_id`. | ERD `teams`, `team_members`; API 4.1 |
| 4 | No working calendar | SRS 17 required downtime "configurable by factory and timezone"; SRS 31 defined availability against "scheduled production time". No shift, break, or holiday model existed. | ERD Section 23; SRS 47; API 5.1; ADR-048 |
| 5 | No settings table | SRS used "configurable" for downtime rules, negative stock, approval thresholds, grace periods, escalation. No storage was defined. | ERD Section 24; SRS 53; API 19.2; ADR-054 |
| 6 | No number sequence table | `work_order_number`, `invoice_number`, `claim_number`, `contract_number` were all required with no generation strategy. | ERD Section 25; SRS 52; ADR-055 |
| 7 | No idempotency key storage | SRS 39 and API 32 mandated idempotency keys with nowhere to store them and no conflict semantics. | ERD Section 26; API 32; ADR-056 |
| 8 | No attachment path for checklists, work orders, breakdowns | SRS 12 listed "Attachment/photo" as a checklist outcome. `work_order_checklist_results` had no file column. | ERD `work_order_attachments`, `breakdown_attachments`, `attachments`; `checklist_results.file_id` |
| 9 | No inventory transfer line items | `inventory_transfers` had one `from_bin_id` and one `to_bin_id`, so a transfer could move exactly one part. | ERD `inventory_transfer_items` |
| 10 | No invoice line items | `subscription_invoices` had a total but no lines. A per-factory or per-asset contract could not be itemized. | ERD `subscription_invoice_lines` |
| 11 | No report job table | API 22 exposed `POST /report-jobs`. Only `export_jobs` existed, which is a different concern. | ERD `report_jobs` |
| 12 | No API client credentials | SRS 43 promised "API keys/OAuth as appropriate" for ERP and IoT integration. No table, no endpoint. | ERD `api_clients`; API 4.2 |
| 13 | No MFA, session, or login attempt storage | Security sections required rate limiting and audit of failed logins with no supporting tables, and never mentioned MFA. | ERD `user_mfa_methods`, `user_recovery_codes`, `user_sessions`, `login_attempts`; SRS 50 |
| 14 | No breakdown status history | Every other major workflow had a status history table. The breakdown lifecycle did not. | ERD `breakdown_status_histories` |

### 3.2 Contradictions and Modelling Errors (P2)

| # | Gap | Problem | Resolution |
|---|---|---|---|
| 15 | Two competing location models | `assets.current_location_type` / `current_location_id` (polymorphic) alongside an `asset_locations` table. Both were described as authoritative. | `asset_locations` is the single location entity. Polymorphic columns removed. ADR-052 |
| 16 | Two competing breakdown-to-work-order links | `work_orders.breakdown_id` and a `breakdown_work_orders` pivot. Two representations of one fact will diverge. | Pivot removed; the foreign key is the single link. |
| 17 | Reservations as ledger transactions | `RESERVATION` and `RELEASE` were listed as inventory transaction types, but a reservation moves no stock. Posting them to an append-only ledger corrupts the balance replay. | Reservations live only in `spare_part_reservations` and adjust `quantity_reserved`. Removed from the transaction type list. |
| 18 | Reservations had no bin | `inventory_balances` is per bin. A reservation without a bin cannot be enforced against any balance. | `spare_part_reservations.bin_id` added. |
| 19 | Production loss stored twice | `breakdowns` carried `estimated_production_loss` and `actual_production_loss`, and a `production_impacts` table carried the same fields. | Quantities live in `production_impacts` only. |
| 20 | `ADJUSTMENT` with no direction | A single adjustment type forced sign to be inferred from the quantity, which is ambiguous in an audited ledger. | Split into `ADJUSTMENT_IN` and `ADJUSTMENT_OUT`. |
| 21 | Ledger could not be verified | No `balance_after`, so a divergence between the ledger and `inventory_balances` was undetectable. | `balance_after` and `wac_after` added; replay must reproduce the balance exactly. |
| 22 | Derived values were plain columns | `actual_cost`, `inventory_balances`, `total_downtime_minutes` were writable like any other field. | ADR-064; API 42 states no endpoint writes them. |
| 23 | `403` labelled "Unauthorized" | The status code list said `403 Unauthorized`, which is `401`. Small, but it propagates into client error handling. | Corrected to `403 Forbidden`; usage rules added in API 2. |
| 24 | Undefined `Pending` work order state | The state existed in the flow with no definition and no entry condition. | Renamed `On Hold`, requires a reason code, excluded from MTTR. SRS 13.1; ADR-051 |
| 25 | Combined trigger had an implicit default | ADR-012 said OR/AND with an "MVP default", which invites two implementations. | `maintenance_plans.rule_logic` is explicit and required. |

### 3.3 Metrics That Could Not Be Computed (P2)

| # | Gap | Problem | Resolution |
|---|---|---|---|
| 26 | Availability had no formula | "Configurable formula based on scheduled production/operational time and downtime." Nothing said what scheduled time was, or where it came from. | SRS 31.1 gives the arithmetic; SRS 47 gives the calendar it resolves against. |
| 27 | No planned versus unplanned downtime | All downtime counted the same, so doing preventive maintenance lowered a factory's availability score. | `downtime_class` and `downtime_reason_codes`; SRS 17.1; ADR-049 |
| 28 | Hold time inflated MTTR | Waiting for a part counted as repair time, hiding the actual constraint. | `work_order_holds`; MTTR excludes hold time. ADR-051 |
| 29 | No zero-denominator rule | An asset with no failures divides by zero in MTBF. Silent `0` would read as "fails constantly". | SRS 31.2 rule 2: return `null`, render "N/A", never average `null` into an aggregate. |
| 30 | No rollup rule | Averaging factory availability percentages gives a different answer than weighting by operating time, and v1.0 did not say which. | SRS 31.2 rule 6: weighted by scheduled operating time. |
| 31 | Dashboards could not meet the latency target | p95 under 500 ms while scanning millions of downtime rows per load. | `kpi_snapshots`; ADR-058 |
| 32 | Dashboard and report could disagree | Two code paths, one metric. | SRS 31.2 rule 7 and API 41: one shared KPI service. |

### 3.4 Missing API Surface (P1)

v1.0 defined tables and RBAC with no way to administer either.

| # | Missing | Closed by |
|---|---|---|
| 33 | User, role, permission, and team administration | API 4.1 |
| 34 | All master data CRUD (asset types, categories, manufacturers, models, maintenance types, templates, checklist items, failure codes, root causes, warehouses, stores, bins) | API 5.2 |
| 35 | File upload, download, signed URL, and versioning | API 19.1 |
| 36 | Working calendar and shift management | API 5.1 |
| 37 | MFA, session listing and revocation, company switching, effective permissions | API 3 |
| 38 | Health and readiness probes | API 19.3 |

### 3.5 Under-Specified Cross-Cutting Concerns (P3)

| # | Gap | Resolution |
|---|---|---|
| 39 | Rate limiting required with no numbers | API 35.1: a full limit table with headers and `Retry-After` |
| 40 | Webhook signing named but not specified | API 35.2: HMAC-SHA256 construction, replay window, rotation, retry schedule |
| 41 | No request correlation | `X-Request-Id` echoed on every response and written to `audit_logs.request_id`; ADR-061 |
| 42 | Offset-only pagination on append-only tables | API 29: cursor pagination for audit logs, meter readings, ledger, notifications |
| 43 | No soft delete boundary | ADR-057 and ERD 31.2 name exactly which tables carry `deleted_at` |
| 44 | No referential action policy | ERD 31.2 |
| 45 | No archival or partitioning strategy | ERD 31.3 |
| 46 | No charset or collation | ERD rule 12: `utf8mb4_unicode_ci` throughout |
| 47 | Index list covered a fraction of the query patterns | ERD 31 expanded, plus index discipline rules |
| 48 | No deprecation mechanics | API 38: `Deprecation` and `Sunset` headers, 6-month minimum window, breaking-change definition |
| 49 | No bulk operations | API 39 |
| 50 | WebSocket payload contract undefined | API 40 |

### 3.6 Entirely Absent Topics (P1/P2)

| # | Gap | Why it matters here | Resolution |
|---|---|---|---|
| 51 | No language requirement | The primary market is Bangladesh. Technicians and storekeepers are the highest-volume users and largely do not work in English. Retrofitting localization after the UI, notification templates, and PDF pipeline exist is expensive and usually done badly. | SRS 48; ERD 27; ADR-053 |
| 52 | No retention periods | "Data retention and archival" was in scope with no period for any data class. | SRS 49.1 |
| 53 | No tenant data export or offboarding path | ADR-030 said the customer owns their data, with no mechanism to give it back. | SRS 49.3 |
| 54 | No password, session, or MFA policy | A multi-tenant industrial system with no stated account security policy. | SRS 50 |
| 55 | No capacity assumptions | A p95 target under 500 ms is unfalsifiable without a load figure. | SRS 51 |
| 56 | No degraded-operation behavior | Nothing said what happens when Redis, storage, or the queue is unavailable. | SRS 54 |
| 57 | No test strategy | "Write automated tests for critical business rules" named none of them. In a multi-tenant system the rule that matters most is tenant isolation, and it fails silently. | SRS 55; ADR-059 |
| 58 | No migration or cutover plan | Factories arrive with spreadsheets and opening stock balances. | SRS 56 |
| 59 | No deployment pipeline or environments | ADR-040 gave a topology, not a process. | ADR-060 |
| 60 | No observability implementation | ADR-041 listed what to monitor, not how or with what thresholds. | ADR-061 |
| 61 | RPO of 24 hours | A failure at end of shift would discard a full day of floor-captured records that exist nowhere else. | ADR-062: RPO tightened to 15 minutes |
| 62 | Platform Super Admin had unbounded tenant access | A platform role with implicit access to every tenant's data, unlogged. | SRS 5.4 and `support_access_grants`: time-boxed, reasoned, audited, tenant-visible |
| 63 | No permission naming convention or role matrix | 12 roles and a dozen example permissions, with no scheme and no mapping. | SRS 5.1 to 5.3 |
| 64 | Scope boundary never stated | The set never said what the system is *not*. Labor cost was specified with a per-person rate, which reads as salary and would have made a maintenance tool an unintended payroll store. | SRS 3.3, 25.1, 25.2; ERD 16.1 `labor_rate_grades`; ADR-065 |

---

## 4. Traceability Matrix

Every requirement resolves to tables, endpoints, and an acceptance criterion. A requirement missing any column is not implementable.

| SRS | Requirement | Tables | API | ADR | Acceptance |
|---|---|---|---|---|---|
| 4 | Tenant hierarchy and isolation | `organizations`, `companies`, `company_users`, `factories` | 4, 4.1 | 001, 005 | AC 2 |
| 5 | Roles, permissions, scope | `roles`, `permissions`, `user_roles`, `teams` | 4.1 | 022 | AC 2 |
| 5.4 | Platform support access | `support_access_grants` | 4 | 042 | AC 13 |
| 6 | Asset management | `assets`, `asset_types`, `asset_models` | 6 | 006 | AC 3 |
| 7 | Location and transfer | `asset_locations`, `asset_transfer_history` | 6 | 052 | AC 3 |
| 8 | QR and barcode | `assets.qr_code`, `asset_locations.qr_code` | 7 | 006 | AC 3 |
| 10 | Preventive maintenance | `maintenance_plans`, `maintenance_plan_rules`, `maintenance_schedules` | 8, 9 | 012 | AC 4, 5 |
| 11 | Meter readings | `asset_meters`, `meter_readings`, `meter_reset_events` | 10 | 013 | AC 5 |
| 12 | Templates and checklists | `maintenance_templates`, `maintenance_template_versions`, `checklist_items` | 5.2 | 020 | AC 6 |
| 13 | Work orders | `work_orders`, `work_order_status_histories` | 11 | 051 | AC 6 |
| 13.2 | Labor logging | `work_order_labor_entries`, `labor_rate_grades` | 11, 16 | 050, 065 | AC 16 |
| 13.3 | Parts consumption | `work_order_parts` | 11 | 050 | AC 16 |
| 14 | Approval workflow | `approval_workflows`, `approval_rules`, `approval_requests`, `approval_actions` | 27 | — | AC 17 |
| 15 | Breakdown | `breakdowns`, `breakdown_status_histories` | 12 | — | AC 7 |
| 16 | Failure and root cause | `failure_categories`, `failure_codes`, `root_causes` | 5.2 | — | AC 7 |
| 17 | Downtime | `downtime_records`, `downtime_reason_codes` | 12, 41 | 049 | AC 7, 18 |
| 18 | Production impact | `production_impacts` | 12 | — | AC 7 |
| 19 | Spare parts and inventory | `spare_parts`, `inventory_balances`, `inventory_transactions` | 13 | 014 | AC 8 |
| 21 | Inventory transfer | `inventory_transfers`, `inventory_transfer_items` | 14 | — | AC 8 |
| 23 | Cost management | `cost_entries`, `cost_categories` | 15 | 015 | AC 9, 16 |
| 24 | Multi-currency | `cost_entries.exchange_rate`, `base_amount` | 15 | 027 | AC 9 |
| 25 | Technicians | `technicians`, `technician_skills`, `labor_rate_grades` | 16 | 050, 065 | AC 16 |
| 3.3 | Scope boundary (no HR, payroll, MES, or appraisal data) | `labor_rate_grades` | 16 | 065 | AC 16 |
| 26 | Vendor, warranty, AMC | `vendors`, `warranties`, `service_contracts` | 17, 18 | — | AC 3 |
| 27, 28 | Notifications and escalation | `notifications`, `notification_preferences`, `escalation_rules` | 19 | 017 | AC 10 |
| 29 | Real-time | broadcast only | 20, 40 | 008, 018 | AC 10, 14 |
| 30, 31 | Dashboards and KPIs | `kpi_snapshots`, `downtime_records` | 21, 41 | 058 | AC 18 |
| 32 | Reports | `report_jobs` | 22 | 032 | AC 11 |
| 33 | Import and export | `import_jobs`, `import_errors`, `export_jobs` | 23, 24 | 031 | AC 15, 20 |
| 34 | Audit | `audit_logs` | 26 | 061 | AC 13 |
| 37 | File storage | `files`, `file_versions`, `attachments` | 19.1 | 019, 020 | AC 3 |
| 39 | Idempotency and locking | `idempotency_keys`, `version` columns | 32, 33 | 024, 025, 056 | AC 14 |
| 40 | Subscription and billing | `subscription_contracts`, `subscription_invoices`, `subscription_invoice_lines` | 25 | 028, 029 | AC 12 |
| 47 | Working calendar | `shifts`, `factory_calendars`, `factory_holidays` | 5.1 | 048 | AC 18 |
| 48 | Localization | `translations`, `locales`, `users.locale` | 1 | 053 | AC 19 |
| 49 | Retention and export | archival policy, `export_jobs` | 24 | 030 | AC 20 |
| 50 | Account security | `user_mfa_methods`, `user_sessions`, `login_attempts` | 3 | 021 | AC 2 |
| 52 | Numbering | `number_sequences` | — | 055 | AC 17 |
| 53 | Settings | `settings`, `setting_definitions` | 19.2 | 054 | AC 17 |

---

## 5. Remaining Open Items

These are decisions, not omissions. Each needs an answer from the business before the affected module is built. They are tracked in SRS 58.

| # | Question | Blocks | Needed by |
|---|---|---|---|
| 1 | Statutory retention periods in the target jurisdiction | Confirmation of the SRS 49.1 table | Before production onboarding |
| 2 | Is external contractor labor invoiced through the platform, or only recorded as cost? | Scope of `work_order_labor_entries` with `labor_category = EXTERNAL`, and whether vendor billing is needed | Before the cost module |
| 7 | Seeded labor grade names and standard rates per factory | `labor_rate_grades` seed data | Before the cost module |
| 3 | Is production loss manually entered at MVP, or does it require production system integration? | Whether `production_impacts` is user-facing or integration-only | Before the downtime module |
| 4 | Payment gateway selection, and whether local gateways (bKash, Nagad, SSLCommerz) are required at MVP | Subscription payment implementation | Before the billing module |
| 5 | Tax treatment and invoice format requirements for Bangladesh | `subscription_invoices` tax fields and PDF template | Before the billing module |
| 6 | Does any committed customer require FIFO costing at go-live? | Whether ADR-014 needs revisiting before launch | Before the inventory module |

Items 1 and 5 overlap with the compliance review and should be answered together. Item 7 is the only one that blocks a v1.1 table from being seeded.

---

## 6. Build Order Implications

Three of the closed gaps change the recommended build order, because they are foundations other modules depend on rather than features that can be added later.

1. **Settings and configuration (SRS 53)** must exist before the maintenance, inventory, and work order modules, because each reads settings that determine its behavior. Building them first and retrofitting configuration means every rule starts life as a hard-coded constant.

2. **Working calendar (SRS 47)** must exist before downtime, KPI, and escalation work. Every duration metric resolves against it. Adding it afterwards means recomputing every historical metric.

3. **Document numbering (SRS 52)** must exist before the first module that creates a numbered document, which is work orders.

The README build order has been updated accordingly: settings and calendar move to positions 6 and 7, ahead of asset management.

---

## 7. What Was Not Changed

Deliberate non-changes, recorded so they are not re-litigated:

1. **Weighted average costing stays the MVP method.** FIFO is a real requirement in some factories, but no committed customer has been named. Open item 6 covers it.
2. **The modular monolith stands.** Nothing found in this review argues for splitting services; the gaps were modelling and specification gaps, not scaling ones.
3. **MySQL stays.** ADR-063 sets an explicit trigger for revisiting search, rather than adding a second datastore speculatively.
4. **IoT, predictive maintenance, and full procurement remain out of scope.** The data model extensions in v1.1 do not narrow the room left for them.
5. **Laravel Sanctum stays.** The MFA and session requirements added in SRS 50 are implementable on top of it; they do not require OAuth.

---

## 8. Document Change Log

| Document | v1.0 | v1.1 | Change |
|---|---|---|---|
| `README.md` | 274 lines | ~330 lines | 5 modules added, build order revised, metrics and localization sections added |
| `01-SRS.md` | 46 sections | 58 sections | 12 sections added, KPI formulas made explicit, work order state machine defined |
| `02-Database-ERD.md` | 25 sections | 33 sections | 8 sections added, roughly 30 new tables, expanded index list, referential and archival policy |
| `03-API-Specification.md` | 38 sections | 42 sections | 4 sections plus 8 subsections added, roughly 120 endpoints added |
| `04-Architecture-Decision-Record.md` | 47 ADRs | 65 ADRs | 18 ADRs added, 5 existing ADRs refined or superseded |
| `05-Gap-Analysis-and-Traceability.md` | — | new | This document |

---

## 9. Review Discipline

This document is maintained, not archived. Whenever a requirement, table, or endpoint is added or removed, the traceability matrix in Section 4 is updated in the same change. A row with an empty Tables or API column is a gap that has reappeared.

---

## 10. Second Review: Buildability (2026-08-18)

The first review asked whether the specification was internally complete. This one asked a different question: could a team start building on Monday? It found 14 further gaps, all now closed.

### 10.1 P1 — Blocked Implementation

| # | Gap | Evidence | Closed by |
|---|---|---|---|
| 65 | No request or response schemas | 263 endpoints, 9 JSON examples. No frontend client and no backend validation could be written from the spec. | `08-API-Schemas.md`, `openapi.yaml` |
| 66 | No machine-readable API contract | API 38 promised OpenAPI 3.1; no such file existed. | `openapi.yaml`, generated per Handbook 6.4 |
| 67 | No frontend specification | Next.js was chosen and nothing further defined. Roughly half the build effort had no spec. | `10-Frontend-Specification.md` |
| 68 | No module or folder structure | ADR-045 required "strict module boundaries" without naming a module. | Handbook 3 and 4 |
| 69 | No seed or master data | A maintenance system with no failure codes cannot log its first breakdown, and an empty demo sells nothing. | `09-Seed-Data-Catalog.md` |
| 70 | Enum values never enumerated | `criticality`, `priority`, `severity`, units, and input types were referenced throughout and defined nowhere. | Data Dictionary 2 |
| 71 | No exchange rate source or selection rule | Four tables carried `exchange_rate`; no table supplied it and no rule said which date's rate applied. | Data Dictionary 4, `exchange_rates` |

### 10.2 P2 — Would Have Caused Rework

| # | Gap | Closed by |
|---|---|---|
| 72 | Breakdown state transitions undefined (statuses existed, transitions did not) | Data Dictionary 3.2 |
| 73 | Asset, transfer, and subscription state transitions undefined | Data Dictionary 3.3 to 3.5 |
| 74 | No permission-to-endpoint mapping | Handbook 2 |
| 75 | QR code content never specified | Data Dictionary 5 |
| 76 | Document number formats never specified | Data Dictionary 6 |
| 77 | No environment variable list or local setup | Handbook 5 and 6 |
| 78 | No definition of done | Handbook 7 |

### 10.3 Corrections to v1.1

Two defects introduced during the v1.1 edits, found and fixed in this pass:

1. SRS Section 25.1 and 25.2 were duplicated into Section 32 (Reports) by a non-unique edit anchor. The duplicate was removed.
2. "Overdue maintenance" was dropped from the Section 32 report list during that removal. It was restored.

### 10.4 Still Open

Deliberately not specified, and not blocking:

1. **Per-report column definitions.** 18 reports are named with their purpose; columns are defined when each report is built, against the traceability matrix. Specifying them now would be guesswork that the first customer review would discard.
2. **Notification message templates.** The event catalog, channels, escalation, and locale handling are specified. The wording of each of the 13 templates is copy, written with the customer during UAT.
3. **Dashboard widget layout.** Content is specified per dashboard (Frontend 4.3); arrangement is a design task.
4. **Estimation and staffing.** A delivery-planning artifact, not a specification one.

---

## 11. Frontend Stack Change (2026-08-18)

The customer selected their existing Laravel and Bootstrap stack with the CoreUI 5 admin template, replacing the Next.js decision in ADR-002. Recorded as **ADR-066**.

### 11.1 Documents Revised

| Document | Change |
|---|---|
| `10-Frontend-Specification.md` | Rewritten as v2.0 for Blade + Bootstrap 5 + CoreUI 5 Free |
| `04-Architecture-Decision-Record.md` | ADR-066 added; ADR-002 superseded; ADR-021, ADR-034, ADR-040, ADR-043, ADR-045, ADR-047 amended |
| `07-Permissions-and-Module-Structure.md` | Frontend structure, environment variables, setup commands, definition of done |
| `README.md` | Stack, architecture diagram, build order |
| `01-SRS.md`, `03-API-Specification.md` | Stack header and client-generation note |

### 11.2 What Did Not Change

The REST API, the data model, tenant isolation, permissions, KPI definitions, seed data, and every domain rule are untouched. `08-API-Schemas.md` and `openapi.yaml` remain valid as written.

This is only true because business logic lives in Actions and Services (ADR-003). Web and API controllers call the same Action, so the frontend decision does not reach the domain layer. Had logic been written in controllers, this change would have been a rewrite.

### 11.3 New Risks Introduced

| Risk | Mitigation |
|---|---|
| The API becomes an untested afterthought, since the UI no longer consumes it | API tested independently; `openapi.yaml` generation gated in CI; Handbook 7 item 11 |
| Imperative DOM code decays, particularly on high-churn screens | WebSocket events re-fetch server-rendered partials rather than patching nodes (Frontend 8 rule 2) |
| Plugin accumulation erases the server-rendering performance advantage | Explicit bundle budgets, three separate entry points, CI enforcement (Frontend 10) |
| Technician offline requirements are met with hand-rolled client state | Treated as its own workstream, not a responsive afterthought (Frontend 6, build order step 32) |
| Free-component gaps (no SmartTable, no PRO pickers) slow table and form work | Named MIT replacements chosen up front: Tom Select, Flatpickr, FullCalendar, Chart.js, server-rendered tables (Frontend 2.1) |

### 11.4 Substitutions Against the Prior Stack

Three, each with a stated reason rather than preference:

1. **Axios only, not Axios plus jQuery AJAX.** Two HTTP stacks means two interceptor chains for `X-Company-Id`, `X-Request-Id`, CSRF, and `Idempotency-Key`. One will eventually be missed, and a write that skips the idempotency header duplicates inventory or breakdown records under retry.
2. **Day.js replaces Moment.js.** Moment is in maintenance mode and recommends alternatives itself. Availability, MTTR, and shift-calendar correctness rest on timezone handling (ADR-026, ADR-048).
3. **CoreUI Icons only.** Three overlapping icon fonts add 150-300 KB, competing with the Bengali webfont, which is not optional.

`bootstrap-datepicker` is dropped because it targets Bootstrap 3 and 4; native date inputs supply the replacement, with Flatpickr for ranges. Bootstrap 5 and CoreUI 5 do not require jQuery, which may still be included for familiarity at roughly 30 KB gzipped.

### 11.5 Open

1. Bengali webfont selection and licence.
2. Barcode scanner hardware model, which decides whether the issue screen reads keyboard-wedge input or needs a camera fallback.
3. Whether dark mode ships in v1.
4. Desktop date-input styling: native inputs throughout, or Flatpickr everywhere for visual consistency.
