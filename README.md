# Garment Industry Machinery Asset & Maintenance Management SaaS

A multi-tenant SaaS platform for garment, textile, and manufacturing organizations to manage machinery and assets, preventive maintenance, breakdowns, corrective maintenance, work orders, spare parts, inventory, maintenance costs, downtime, vendors, warranties, service contracts, notifications, reports, and subscriptions.

## Scope

This is a **maintenance and machine tracking system**. It is deliberately not an HR, payroll, attendance, production planning, or accounting system.

Labor cost is computed from standard rates per skill grade. No salary, wage, attendance, or appraisal data is stored anywhere in the schema. Where a factory needs payroll-accurate costing or production output data, that is an integration, not a column here. See `01-SRS.md` Section 3.3 for the full boundary.

## Technology Stack

- **Frontend:** Laravel Blade (server-rendered) + Bootstrap 5 + CoreUI 5 Free (MIT)
- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** MySQL 8+
- **Authentication:** Laravel Sanctum
- **Real-Time:** Laravel Reverb / WebSocket
- **Cache & Queue:** Redis
- **Queue Monitoring:** Laravel Horizon
- **File Storage:** Private S3-compatible object storage
- **Localization:** English + Bengali (`utf8mb4_unicode_ci`)
- **Deployment:** Docker + Nginx
- **API:** REST API `/api/v1`

## Core Modules

1. Multi-Tenant SaaS
2. Company, Factory & Location Management
3. User, Role & Permission Management
4. Asset & Machinery Management
5. Asset Lifecycle & Transfer Management
6. QR Code & Barcode
7. Preventive Maintenance
8. Meter-Based & Usage-Based Maintenance
9. Maintenance Templates & Checklists
10. Work Order Management
11. Breakdown & Corrective Maintenance
12. Downtime & Production Impact
13. Failure & Root Cause Analysis
14. Spare Parts & Inventory
15. Inventory Transfer & Weighted Average Costing
16. Maintenance & Lifecycle Cost Management
17. Technician Management
18. Vendor, Warranty & AMC Management
19. Real-Time Notifications & Escalation
20. Dashboards & KPI Analytics
21. Reports & Data Export
22. Bulk Import & Export
23. Audit Logging
24. Subscription & Contract-Based Billing
25. REST API & Webhooks
26. Approval Workflows
27. Working Calendar, Shifts & Holidays
28. Configuration & Settings Management
29. Localization (English & Bengali)
30. KPI Snapshots & Analytics Engine

## Asset-Centric Architecture

Machines are modeled as assets, allowing future management of sewing machines, cutting machines, embroidery machines, washing and dyeing machines, finishing machines, compressors, generators, boilers, HVAC equipment, electrical and safety equipment, and calibration assets.

Assets support codes, serial numbers, QR/barcodes, manufacturer/model, criticality, location, lifecycle status, documents, warranty, vendor, parent/child relationships, and cost data.

## Maintenance

The system supports:

- Preventive
- Corrective
- Breakdown
- Emergency
- Condition-based
- Inspection
- Calibration-ready workflows

Maintenance can be triggered by calendar dates, running hours, meter readings, production cycles, usage counts, conditions, or combined rules.

Example:

```text
Every 30 days OR every 500 running hours
Whichever occurs first
```

Maintenance schedules support rolling and fixed-calendar modes.

## Work Orders

Typical workflow:

```text
Draft
→ Pending Approval
→ Scheduled
→ Assigned
→ In Progress
→ On Hold
→ Completed
→ Verified
→ Closed
```

Work orders can include technicians, teams, checklists, parts, labor, costs, downtime, attachments, and verification.

## Breakdown & Downtime

Breakdown records capture failure time, report time, acknowledgement, technician arrival, repair start, repair completion, production resume, root cause, failure code, corrective action, preventive action, and production impact.

Metrics include:

- Response Time
- Repair Time
- Total Downtime
- MTBF
- MTTR
- Availability

## Spare Parts & Inventory

Inventory hierarchy:

```text
Factory
→ Warehouse
→ Store
→ Bin / Rack
```

Inventory lifecycle:

```text
Required
→ Reserved
→ Issued
→ Consumed
→ Returned / Scrapped
```

The MVP uses an immutable inventory transaction ledger and Weighted Average Cost.


## Metrics

Availability, MTBF, MTTR, and PM compliance resolve through one shared KPI service, so a figure is identical on a dashboard and in a report.

```text
Operating Time = Scheduled Operating Time (factory shift calendar) - Total Downtime
Availability   = Operating Time / Scheduled Operating Time
MTBF           = Operating Time / Failure Count
MTTR           = Sum(repair time - hold time) / Completed Repairs
```

Downtime is classified as unplanned, planned, non-operating, or external. Planned maintenance does not count against availability by default, so a factory is never penalized for maintaining its machines.

## Localization

The platform ships bilingual: English and Bengali. Locale is a user attribute defaulting to a company setting. Database collation is `utf8mb4_unicode_ci`, and PDF and Excel exports embed a font with full Bengali glyph coverage.

## Real-Time Notifications

Laravel events are broadcast through Laravel Reverb.

Private channels:

```text
private-company.{companyId}
private-factory.{factoryId}
private-user.{userId}
```

REST API remains the source of truth; WebSockets provide real-time delivery.

## Subscription Model

The platform uses a custom, contract-based subscription model with no mandatory fixed packages.

Billing entities:

```text
Subscription
→ Contract
→ Invoice
→ Payment
→ Refund / Credit Note
```

Subscription lifecycle:

```text
Active
→ Grace Period
→ Read Only
→ Archived
```

Customer data is not immediately deleted after subscription expiry.

## Security

The platform requires:

- HTTPS
- Laravel Sanctum
- RBAC
- Resource-level policies
- Strict tenant isolation
- Factory-level access control
- Rate limiting
- Input and file validation
- Private file storage
- Signed file URLs
- Audit logging
- Secure secrets
- Database backups

**Critical rule:** Never trust a client-supplied tenant/company ID. Tenant context must be derived from authenticated membership and validated server-side.

## Documentation Set

| File | Description |
|---|---|
| `README.md` | Project overview and implementation guide |
| `01-SRS.md` | Software Requirements Specification |
| `02-Database-ERD.md` | Database entities, relationships, indexes, and integrity rules |
| `03-API-Specification.md` | REST API, authentication, authorization, WebSocket events, and API standards |
| `04-Architecture-Decision-Record.md` | Architecture decisions, technology choices, scalability, security, and deployment |
| `05-Gap-Analysis-and-Traceability.md` | Gaps found in v1.0, how each was closed, requirements traceability, and remaining open items |
| `06-Data-Dictionary.md` | Every enum value, state machine, exchange-rate rule, QR content spec, and document number format |
| `07-Permissions-and-Module-Structure.md` | Permission catalog and endpoint mapping, backend and frontend module structure, environment variables, local setup, definition of done |
| `08-API-Schemas.md` | Request and response schemas, validation rules, and error mapping for the core resources |
| `openapi.yaml` | Machine-readable API contract, generated from route and request classes; the integration contract for ERP, IoT, and future mobile clients |
| `09-Seed-Data-Catalog.md` | Garment-industry master data: asset taxonomy, failure codes, downtime reasons, checklist templates, demo tenant |
| `10-Frontend-Specification.md` | Screen inventory, state management, design system, localization, offline behavior, performance budgets |

## Recommended Development Order

```text
1. Project Setup (Laravel + Vite + CoreUI Free)
2. Blade Layout, Component Library & Design System
3. Authentication (session + MFA)
4. Multi-Tenant Architecture
5. Company / Factory / Location
6. User / Role / Permission / Teams
7. Settings & Configuration Engine
8. Working Calendar / Shifts / Holidays
9. Asset Management
10. QR / Barcode
11. Maintenance Master Data
12. Maintenance Plans
13. Scheduling Engine
14. Meter Management
15. Work Orders (incl. labor & parts)
16. Checklist Engine
17. Breakdown Management
18. Downtime & KPI Engine
19. Spare Parts
20. Inventory Ledger
21. Cost Management
22. Vendor / Warranty / AMC
23. Approval Workflows
24. Notifications & Escalation
25. Reverb Real-Time Events
26. Dashboards & Reports
27. Import / Export
28. Audit Logs
29. Subscription / Billing
30. Webhooks / API Integration
31. Localization (en / bn)
32. Technician Mobile Screens (offline drafts)
33. Security Hardening
34. Performance & Load Testing
35. Production Deployment
```

## Development Principles

1. API-first architecture.
2. Tenant-first security.
3. Asset-centric domain model.
4. Modular backend architecture.
5. Thin controllers.
6. Business rules in service/action/domain layers.
7. Strict authorization policies.
8. Immutable audit history.
9. Transactional inventory operations.
10. Append-only financial corrections.
11. Secure private file storage.
12. Real-time events must never replace database persistence.
13. Use queues for long-running operations.
14. Use idempotency for critical write operations.
15. Use optimistic locking for high-conflict resources.
16. Avoid premature microservices.
17. Maintain backward-compatible APIs.
18. Write automated tests for critical business rules.
19. Never accept a derived value or a tenant ID from a client.
20. Classify every downtime record; an unclassified metric is a misleading metric.
21. Compute duration metrics against the factory working calendar, not wall-clock time.
22. Localize every user-facing string; keep API error codes locale-independent.

## Final Architecture

```text
Browser  ──  Blade + Bootstrap 5 + CoreUI 5 Free (server-rendered)
                        │
                        ▼
              Laravel 13  (one application)
              ├── Web controllers  ─┐
              │                     ├──→ Actions / Services  ──→ MySQL 8+
              └── API controllers  ─┘     (single source of business rules)
                        │
                        ├── Redis  (cache, queue, rate limit)
                        ├── Laravel Horizon  (queue monitoring)
                        ├── Laravel Reverb   (WebSocket)
                        └── Private S3-compatible storage

  /api/v1  is built in full and tested independently:
  ERP, HRM, production, accounting, IoT, and any future mobile client.
```

**Architecture:** Multi-Tenant Modular Monolith

The specification set is one unified document family. Any change to tenant isolation, maintenance scheduling, inventory costing, KPI definitions, the working calendar, subscription lifecycle, or core architecture should be documented through an ADR.

## Documentation Status

**Status:** Final Technical Specification Draft — Revision 1 (gap closure)  
**Version:** 1.1  
**Last reviewed:** 2026-08-18
