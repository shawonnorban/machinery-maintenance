# 01-SRS.md
# Software Requirements Specification (SRS)
## Textile & Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.1  
**Status:** Final Draft (Revision 1 — gap closure)  
**Backend:** Laravel 13 / PHP 8.3+  
**Frontend:** Laravel Blade + Bootstrap 5 + CoreUI 5 Free  
**Database:** MySQL  
**Realtime:** Laravel Reverb / WebSocket  
**Cache & Queue:** Redis  
**Queue Monitoring:** Laravel Horizon  
**Authentication:** Laravel Sanctum  
**Deployment:** Docker + Nginx  

---

## 1. Purpose

This document defines the functional and non-functional requirements for a multi-tenant SaaS platform for garment, textile, and manufacturing organizations to manage machinery, assets, preventive maintenance, breakdowns, work orders, spare parts, costs, downtime, vendors, warranties, service contracts, notifications, reporting, and subscriptions.

The system is designed as an Asset and Maintenance Management platform rather than a machine-only registry. A machine is treated as an asset subtype, allowing future management of utility equipment, electrical equipment, safety equipment, calibration assets, and other factory assets.

---

## 2. Product Objectives

1. Centralize all factory machinery and asset records.
2. Maintain complete asset lifecycle history.
3. Schedule and execute preventive maintenance.
4. Record and resolve breakdowns and corrective maintenance.
5. Track maintenance work orders and technician activity.
6. Manage spare parts inventory and consumption.
7. Calculate maintenance, repair, downtime, and lifecycle costs.
8. Provide real-time operational notifications.
9. Provide management dashboards, analytics, and reports.
10. Support multiple companies, factories, locations, and users in one SaaS platform.
11. Support custom subscription contracts without mandatory fixed packages.
12. Provide API-first architecture for future ERP, HRM, production, accounting, and IoT integrations.

---

## 3. Scope

### 3.1 In Scope

- SaaS tenant management
- Company and factory hierarchy
- Locations and production lines
- Asset and machine management
- Parent/child assets
- QR/barcode identification
- Asset transfer history
- Asset lifecycle
- Asset documents
- Preventive maintenance
- Corrective maintenance
- Breakdown management
- Emergency maintenance
- Inspection and calibration-ready architecture
- Maintenance templates and checklist versioning
- Date, meter, usage, condition, and combined maintenance triggers
- Meter readings
- Work orders
- Approval workflows
- Technician management
- Spare parts and inventory
- Reservations, issues, consumption, returns, transfers
- Weighted-average inventory costing for MVP
- Vendors
- Warranty
- AMC/service contracts
- Maintenance and lifecycle costs
- Downtime and production impact
- Failure and root-cause analysis
- Notifications and escalation
- Real-time WebSocket updates
- Dashboards
- Reports
- Import/export
- Audit logs
- Subscription and billing
- Data retention and archival
- API and webhook integration

### 3.2 Out of Scope for MVP

- Full accounting ledger
- Full procurement/Purchase Order module
- Full production planning
- IoT sensor ingestion
- AI predictive maintenance
- Native mobile applications
- WhatsApp/SMS delivery unless separately integrated
- Full calibration workflow
- Full EAM financial depreciation ledger

The data model must remain extensible for these capabilities.

### 3.3 What This System Is Not

The platform is a **maintenance and machine tracking system**. Several adjacent domains are deliberately excluded, and the exclusions are binding on the data model, not merely on the MVP roadmap.

| Not this | Why | What the system does instead |
|---|---|---|
| HR or payroll system | Salary is HR data with its own access controls and legal handling. A maintenance system that stores it becomes an unintended payroll data store. | Maintenance labour has no cost at all: a work order records hours, never money (Section 25.1). No salary, wage, rate, or compensation field exists anywhere in the schema. |
| Attendance or time-and-attendance system | Labor entries record time spent on a work order, not presence at work. | A technician's total logged hours in the system is not, and must not be presented as, an attendance record. |
| Production planning or MES | Production quantity, efficiency, and output belong to the production system. | Production loss is recorded as an optional impact figure on a breakdown (Section 18), entered manually or via future integration. |
| Accounting or ERP ledger | Maintenance costing and financial accounting are distinct domains. | Cost entries feed maintenance and lifecycle cost analysis, with integration points for ERP export (Section 43). |
| Employee performance appraisal tool | Technician KPIs exist to balance workload and identify training needs. | Metrics in Section 25 are reported at team and factory level by default; individual figures require an explicit permission. |
| Procurement or purchasing system | Full PR/PO workflow is out of MVP scope. | Inventory receipts record what arrived; the procurement chain that preceded it is future scope (Section 22). |

Anything that would require storing an employee's pay, attendance, discipline, or appraisal record is out of scope by design. A request to add one should be answered with an integration, not a column.

---

## 4. Organization and Tenant Hierarchy

Recommended hierarchy:

Platform
→ Organization/Group
→ Company
→ Business Unit (optional)
→ Factory/Site
→ Building
→ Floor
→ Department
→ Section
→ Production Line
→ Workstation
→ Asset/Machine

A company may have multiple factories. A factory may have multiple locations. Assets can move between locations, but transfer history must remain immutable.

### Tenant Isolation

The MVP uses a shared database with logical tenant isolation.

Every tenant-owned record must carry `company_id` or an equivalent tenant identifier. Laravel middleware establishes the tenant context. Policies, query scopes, repositories/services, and authorization checks must enforce tenant boundaries.

A user must never access another company's data through direct IDs, filters, exports, reports, WebSockets, or APIs.

---

## 5. User Roles

- Platform Super Admin
- Company Owner
- Company Admin
- Factory Admin
- Factory Manager
- Maintenance Manager
- Maintenance Engineer
- Technician
- Store Manager
- Storekeeper
- Viewer
- Auditor

Permissions are granular and role-based.

Examples:
- `asset.view`
- `asset.create`
- `asset.update`
- `asset.transfer`
- `maintenance.plan.create`
- `work_order.assign`
- `work_order.complete`
- `work_order.verify`
- `breakdown.create`
- `breakdown.close`
- `spare_part.issue`
- `report.export`
- `subscription.manage`

### 5.1 Role Scope

Every role assignment carries a scope. A role is either company-wide or bound to one or more factories.

| Role | Scope | Notes |
|---|---|---|
| Platform Super Admin | Platform | No tenant data access by default; access requires an audited impersonation/support grant |
| Company Owner | Company | Full company access including billing |
| Company Admin | Company | Full company access excluding billing unless granted |
| Factory Admin | Factory | Full access within assigned factories |
| Factory Manager | Factory | Operational access, approvals above threshold |
| Maintenance Manager | Factory | Plans, work orders, approvals below threshold |
| Maintenance Engineer | Factory | Plans, work orders, root cause, verification |
| Technician | Factory | Assigned work orders, checklists, part requests |
| Store Manager | Factory | Inventory, transfers, adjustments |
| Storekeeper | Factory / Store | Issue, receive, return |
| Viewer | Company or Factory | Read-only |
| Auditor | Company | Read-only including audit logs and financial records |

### 5.2 Permission Naming Convention

Permissions use `{module}.{resource}.{action}`:

```text
asset.asset.view
asset.asset.create
asset.transfer.approve
maintenance.plan.create
maintenance.schedule.reschedule
work_order.work_order.assign
work_order.work_order.verify
breakdown.breakdown.close
inventory.stock.issue
inventory.adjustment.create
cost.entry.create
billing.subscription.manage
audit.log.view
settings.company.manage
```

Actions are limited to: `view`, `view_any`, `create`, `update`, `delete`, `restore`, `approve`, `reject`, `assign`, `verify`, `close`, `cancel`, `export`, `import`, `manage`.

### 5.3 Role Permission Matrix

The seeded default matrix is a deliverable of the User/Role module. Rules:

1. Every permission must be granted to at least one seeded role, or it is dead code.
2. `Viewer` and `Auditor` must never hold a write permission.
3. `Technician` write permissions are limited to work orders where the technician is assigned.
4. Permission changes are audited (see Section 34).
5. Customers may clone a seeded role and edit the clone; seeded roles are not editable.

### 5.4 Platform Support Access

Platform staff access to tenant data requires:

- An explicit, time-boxed impersonation grant.
- A recorded reason.
- An audit log entry on start and end.
- Tenant-visible notification that support access occurred.

Silent platform access to tenant data is prohibited.

---

## 6. Asset Management Requirements

Each asset must support:

- Internal asset ID
- Asset code
- Asset type
- Category
- Brand
- Model
- Serial number
- Manufacturer
- Country of origin
- QR code
- Barcode
- Criticality
- Status
- Purchase date
- Installation date
- Commissioning date
- Acquisition cost
- Installation cost
- Current book/operational value where applicable
- Warranty
- Supplier/vendor
- Current location
- Parent asset
- Documents
- Images

### Asset Status

- Draft
- Purchased
- Installed
- Commissioned
- Running
- Idle
- Under Maintenance
- Breakdown
- Under Repair
- Retired
- Scrapped
- Lost

All material status changes must be auditable.

### Parent/Child Asset

Assets may have parent-child relationships.

Example:
Generator → Engine → Alternator → Battery.

A child asset can have its own maintenance and cost records while remaining linked to the parent.

---

## 7. Asset Location and Transfer

An asset has one current operational location.

Transfers require:
- From location
- To location
- Transfer date/time
- Reason
- Requested by
- Approved by where required
- Received by
- Notes

Transfer history is immutable after posting except through authorized correction workflows.

---

## 8. Asset Identification

Each asset may have:
- Asset code
- QR code
- Barcode
- Serial number

QR scanning must provide role-aware actions:
- View asset
- View current status
- View maintenance history
- Report breakdown
- Create work order
- Start checklist where permitted

---

## 9. Maintenance Types

Supported maintenance strategies:

- Preventive
- Corrective
- Breakdown
- Emergency
- Predictive-ready
- Condition-based
- Inspection
- Calibration-ready

---

## 10. Preventive Maintenance

Maintenance plans may be triggered by:

- Calendar date
- Running hours
- Meter reading
- Production cycles
- Usage count
- Stitch count
- Condition
- Combined rules

Example:
Every 30 days OR 500 running hours, whichever occurs first.

### Schedule Modes

1. Rolling schedule: next due date is calculated from completion.
2. Fixed calendar schedule: recurrence follows the defined calendar anchor.

Each plan must define:
- Trigger
- Frequency
- Grace period
- Due calculation
- Schedule mode
- Priority
- Checklist template
- Assigned team
- Escalation rule

---

## 11. Meter Readings

Meter readings support:
- Manual entry
- API input
- IoT future integration
- Import

Each reading records:
- Asset
- Meter type
- Value
- Unit
- Timestamp
- Source
- Recorded by

Readings normally cannot decrease. Meter replacement/reset requires an explicit meter reset event with reason and audit trail.

---

## 12. Maintenance Templates and Checklists

Maintenance templates are reusable.

Structure:

Maintenance Template
→ Version
→ Checklist Items
→ Maintenance Plan
→ Work Order

Checklist versions are immutable after use. Historical work orders retain the exact checklist version used at execution time.

Checklist item outcomes:
- Pass
- Fail
- N/A
- Numeric value where applicable
- Text observation
- Attachment/photo

---

## 13. Work Orders

Work order fields include:
- Work order number
- Tenant
- Factory
- Asset
- Maintenance type
- Priority
- Schedule
- Assigned team
- Assigned technician
- Checklist
- Instructions
- Estimated parts cost
- Actual cost
- Start/end timestamps
- Downtime
- Status

### Status

Draft → Pending Approval → Scheduled → Assigned → In Progress → On Hold → Completed → Verified → Closed

Cancelled is allowed from eligible pre-close states.

Technician completion does not necessarily close the work order. Critical assets and configured workflows require verification.

### 13.1 Status Definitions

| Status | Meaning | Entry condition |
|---|---|---|
| Draft | Created, not yet committed to a schedule | — |
| Pending Approval | Waiting on an approval matrix decision | An approval rule matched (Section 14) |
| Scheduled | Committed to a time window | Approved or no approval required |
| Assigned | At least one technician assigned | Active assignment exists |
| In Progress | Work physically started | `actual_start` set |
| On Hold | Paused, clock stopped | A hold reason is required |
| Completed | Technician finished, checklist satisfied | All required checklist items answered |
| Verified | Reviewed by an authorized verifier | Verification required and performed |
| Closed | Terminal; costs posted, no further edits | Verified, or verification not required |
| Cancelled | Terminal; abandoned before close | Cancellation reason required |
| Rejected | Approval denied | Returns to Draft or terminates |

`On Hold` replaces the previously ambiguous `Pending` state. Time spent in `On Hold` is excluded from repair time and is recorded with a hold reason so that MTTR is not inflated by waiting for parts or approvals.

Allowed transitions are explicit; any other transition returns `INVALID_STATUS_TRANSITION`.

```text
Draft            → Pending Approval | Scheduled | Cancelled
Pending Approval → Scheduled | Rejected | Cancelled
Rejected         → Draft | Cancelled
Scheduled        → Assigned | Cancelled
Assigned         → In Progress | Scheduled | Cancelled
In Progress      → On Hold | Completed | Cancelled
On Hold          → In Progress | Cancelled
Completed        → Verified | In Progress (reopen, audited) | Closed
Verified         → Closed
Closed           → (terminal)
Cancelled        → (terminal)
```

### 13.2 Labor Logging

Work orders must record time as discrete entries rather than a single free-text duration. Each entry records:

- Technician
- Start time and end time (UTC)
- Computed minutes
- Notes

Requirements:

1. Time entries may not overlap for the same technician: one person cannot be in two places at once, and an overlap is almost always a mistyped date.
2. A time entry carries no money. Technicians are salaried employees, so their hours are already paid for whether they are spent on a machine or not, and charging them against a work order would invent a figure no ledger in the business agrees with (Section 3.3, ADR-065).
3. A work order's actual cost is parts consumed plus posted costs. A contractor's charge is a cost entry against the asset, recorded where the invoice is.
4. Technician KPIs (Section 25) are computed from time entries and work order timestamps, not from estimates.

### 13.3 Parts Consumption

Parts consumed on a work order are recorded as work order part lines. Each line records the spare part, requested quantity, issued quantity, consumed quantity, returned quantity, the issuing bin, the unit cost captured at issue time, and any substitute part used.

Actual parts cost is the sum of consumed quantity × issue-time unit cost. A work order cannot be closed while it holds unreturned issued parts that are neither consumed nor returned.

### 13.4 Attachments

Work orders, checklist results, and breakdowns support file attachments (photos, reports, vendor documents). Attachments follow the private storage and signed URL rules in Section 37.

---

## 14. Approval Workflow

Approval matrices are configurable.

Example:
- Low-cost maintenance → Maintenance Manager
- High-cost maintenance → Factory Manager
- Critical asset → Engineer + Manager

Approval rules may depend on:
- Cost threshold
- Asset criticality
- Maintenance type
- Factory
- Department

Approval actions must be audited.

---

## 15. Breakdown Management

A breakdown report must capture:
- Asset
- Location
- Reporter
- Failure time
- Report time
- Problem description
- Severity
- Priority
- Production line
- Production impact
- Assigned technician
- Root cause
- Failure code
- Corrective action
- Preventive action
- Parts used
- Cost
- Downtime

### Breakdown Priority

- Critical
- High
- Medium
- Low

Breakdown and preventive work orders may coexist. If a breakdown repair is active, conflicting preventive maintenance must be blocked or rescheduled according to configured rules.

---

## 16. Failure and Root Cause Analysis

Failure taxonomy supports:
- Failure category
- Failure code
- Root cause
- Corrective action
- Preventive action

Repeated failures must be detectable and reportable.

The system should identify assets with recurring failures and optionally generate a replacement/review recommendation.

---

## 17. Downtime

The system must record:
- Failure time
- Reported time
- Acknowledged time
- Technician arrival
- Repair start
- Repair end
- Production resume

Derived metrics:
- Response time
- Repair time
- Total downtime

Downtime calculation rules must be configurable by factory and timezone.

### 17.1 Downtime Classification

Every downtime record must be classified. Availability and OEE-style metrics are meaningless without this split.

| Class | Examples | Counts against availability |
|---|---|---|
| Unplanned | Breakdown, emergency repair, unplanned stoppage | Yes |
| Planned | Scheduled preventive maintenance, planned overhaul | Configurable, default no |
| Non-operating | Outside shift, holiday, no production scheduled | No |
| External | Power outage, utility failure, material shortage | Configurable, default no |

Downtime records store `downtime_class` and an optional `downtime_reason_code`. Reason codes are tenant-configurable master data.

### 17.2 Shift Calendar Dependency

Downtime, availability, response time, and repair time are computed against the factory working calendar, not wall-clock elapsed time, unless the factory is configured as continuous operation. See Section 47.

Example: a breakdown reported at 21:50 in a factory whose shift ends at 22:00 and resumes at 06:00 accrues 10 minutes of downtime on that day, not 8 hours and 10 minutes, when calendar-aware calculation is enabled.

### 17.3 Calculation Versioning

Downtime records store the `calculation_version` used to derive them. Changing downtime rules must not silently rewrite historical metrics; recalculation is an explicit, audited, backfill operation that writes a new calculation version.

---

## 18. Production Impact

Breakdowns may optionally record:
- Production line
- Production order reference
- Estimated production loss
- Actual production loss
- Affected quantity

Production system integration remains future scope.

---

## 19. Spare Parts

Spare part master data:
- Part number
- Name
- Category
- Brand
- Manufacturer
- Unit
- Minimum stock
- Reorder level
- Unit cost
- Supplier

Inventory hierarchy:
Factory → Warehouse → Store → Bin/Rack

### Inventory Lifecycle

Required → Reserved → Issued → Consumed → Returned / Scrapped

Transactions:
- Purchase receipt
- Receive
- Issue
- Consume
- Return
- Adjustment
- Transfer
- Scrap

MVP costing method: Weighted Average Cost.

Maintenance cost uses the issue-time cost captured in the inventory ledger.

---

## 20. Spare Part Compatibility

Parts may be linked to:
- Asset model
- Asset type
- Compatible machine
- Substitute part

Substitution must be recorded in the work order or issue transaction.

---

## 21. Inventory Transfer

Inter-factory transfer:

Transfer Requested → Approved → Dispatched → Received

Inventory quantities update only at appropriate transaction stages.

---

## 22. Procurement-Ready Architecture

MVP does not implement full procurement but supports future:

Purchase Requisition
→ Approval
→ Purchase Order
→ Goods Receive
→ Inventory

---

## 23. Cost Management

Cost categories:
- Labor
- Spare parts
- External service
- Vendor
- Transportation
- Emergency
- Other

Machine lifecycle cost:

Acquisition + Installation + Upgrade + Maintenance + Repair + Parts + External Service.

Accounting depreciation is separate from maintenance costing.

Optional asset financial fields:
- Acquisition cost
- Capitalized cost
- Salvage value
- Useful life
- Depreciation method

---

## 24. Multi-Currency

Each transaction stores:
- Transaction currency
- Amount
- Exchange rate
- Base currency amount

Organization/company has a base currency.

---

## 25. Technician Management

Technician profile:
- Employee ID
- Name
- Department
- Skills
- Specialization
- Contact
- Joining date
- Status

KPIs:
- Assigned work orders
- Completion rate
- Average repair time
- Breakdown resolution
- Preventive maintenance compliance

### 25.1 Area of Responsibility

Technician profiles carry the part of the mill a person looks after, and no money of any kind.

A technician always belongs to a factory. Where a factory is divided into departments, the department is named: the dyeing technicians cover the dye house, the sewing mechanics cover the sewing floor. Where people are assigned line by line, the production line is named as well.

Rules:

1. Area decides who is offered first when work is assigned. It never decides who *may* be assigned — at two in the morning a manager sends whoever is awake, and a system that refuses is a system that gets worked around.
2. A technician with no department named covers the whole factory, which is how a small factory works.
3. No salary, wage, rate, bonus, deduction, or payroll identifier is stored anywhere in the system (Section 3.3). Maintenance labour has no cost: technicians are salaried, and their hours are recorded as time, not money.

If a factory needs true payroll-accurate maintenance costing, that is an integration with the HR system, not a field in this one.

### 25.2 KPI Scope and Privacy

Technician KPIs exist to balance workload, size the maintenance team, and identify training needs. They are not an appraisal instrument.

1. Dashboards default to team and factory level. Per-individual figures require the `technician.performance.view` permission.
2. KPIs report on work: completion rate, average repair time, first-time fix rate, PM compliance, open workload.
3. KPIs never report on the person: no attendance, punctuality, idle time, or ranking of employees against one another.
4. Average repair time is normalized by asset criticality and maintenance type. Comparing a technician who handles boiler failures with one who changes needles is a meaningless comparison presented as a fair one.
5. Total hours logged against work orders is not an attendance figure and must not be labelled or exported as one.

---

## 26. Vendor, Warranty and AMC

Vendor records support supplier/service providers.

Warranty:
- Start
- End
- Coverage
- Claims

AMC/service contract:
- Contract number
- Vendor
- Start/end
- Value
- Coverage
- Renewal date

Alerts are generated for upcoming expiry.

---

## 27. Notifications

Notification channels:
- In-app
- Real-time WebSocket
- Email
- SMS/WhatsApp future-ready

Events:
- Maintenance due
- Maintenance overdue
- Breakdown created
- Critical breakdown
- Work order assigned
- Work order completed
- Spare part low stock
- Warranty expiry
- AMC expiry

Notifications are persisted with read/unread state.

User notification preferences are event-specific.

---

## 28. Notification Escalation

Example:

Due → Technician  
Overdue → Maintenance Manager  
Critical overdue → Factory Manager  
Extended critical overdue → Company Admin

Escalation rules are configurable.

---

## 29. Real-Time Architecture

Laravel emits domain events.

Laravel Reverb broadcasts through private channels.

Channels:
- `private-company.{companyId}`
- `private-factory.{factoryId}`
- `private-user.{userId}`

Channel authorization must enforce tenant membership.

Real-time events:
- Asset status changed
- Breakdown created
- Work order assigned
- Work order updated
- Notification created
- Spare part stock changed

REST API remains the source of truth. WebSocket is an event/update transport, not the permanent data store.

---

## 30. Dashboard

Management dashboard:
- Total assets
- Running
- Idle
- Breakdown
- Under maintenance
- Overdue maintenance
- Maintenance cost
- Breakdown cost
- Downtime
- Availability
- MTBF
- MTTR

Maintenance dashboard:
- Today's tasks
- Due/overdue
- Open work orders
- Active breakdowns
- Technician workload

Store dashboard:
- Stock value
- Low stock
- Out of stock
- Reserved
- Issued
- Consumed

---

## 31. KPI Definitions

### MTBF
Mean operating time between failures. See 31.1 for the exact formula and counting rules.

### MTTR
Mean time from repair start to repair completion, with response time reported separately.

### Availability
Ratio of operating time to scheduled operating time, computed from the factory shift calendar. See 31.1.

Definitions must be consistent across reports.

### 31.1 Formulas

All KPIs are computed over an explicit period, for an explicit scope (asset, asset type, line, factory, or company), and against the factory working calendar.

```text
Operating Time      = Scheduled Operating Time − Total Downtime
Scheduled Operating Time
                    = working minutes from the factory shift calendar
                      for the period and scope (Section 47)

MTBF (minutes)      = Operating Time / Number of Failures
MTTR (minutes)      = Σ (repair_completed_at − repair_started_at − hold_minutes)
                      / Number of Completed Repairs
MTTA / Response     = Σ (acknowledged_at − reported_at) / Number of Breakdowns
Mean Time to Arrive = Σ (technician_arrival_at − reported_at) / Number of Breakdowns
Availability (%)    = Operating Time / Scheduled Operating Time × 100
Unplanned Downtime %= Unplanned Downtime / Scheduled Operating Time × 100
PM Compliance (%)   = PM completed within (due date + grace)
                      / PM due in period × 100
Schedule Attainment = Work orders closed in period
                      / Work orders scheduled in period × 100
Stock Turn          = Cost of parts consumed in period
                      / Average inventory value in period
```

### 31.2 Counting Rules

1. **Number of Failures** counts breakdowns whose `downtime_class` is `UNPLANNED`. Duplicate reports linked to an existing open breakdown are not counted separately.
2. **Denominator zero** returns `null`, never `0`. Reports must render `null` as "N/A" and must not average `null` into aggregates.
3. **Partial periods** clip to the period boundary; a breakdown spanning a period boundary contributes its downtime to each period proportionally.
4. **Retired and scrapped assets** are excluded from availability from their status-change date forward.
5. **Planned downtime** is excluded from availability by default. The setting `metrics.planned_downtime_counts_against_availability` (per company, overridable per factory) changes this.
6. **Asset-level rollup** to line/factory/company is a weighted average by scheduled operating time, never a plain average of percentages.
7. KPI definitions are shared code. A dashboard and a report showing the same KPI for the same scope and period must return identical values.

### 31.3 OEE Readiness

The MVP does not compute OEE, because performance and quality inputs come from production systems that are out of scope. Availability is stored in an OEE-compatible form so that `OEE = Availability × Performance × Quality` can be completed when production integration is added.

---

## 32. Reports

Reports:
- Asset register
- Asset status
- Asset transfer
- Maintenance history
- Preventive maintenance compliance
- Overdue maintenance
- Breakdown analysis
- Root cause
- Downtime
- MTBF/MTTR
- Maintenance cost
- Parts consumption
- Inventory valuation
- Technician performance
- Vendor performance
- Warranty/AMC expiry
- Lifecycle cost

Exports:
- CSV
- Excel
- PDF

Large reports use background jobs.

---

## 33. Import and Export

Import:
- Assets
- Locations
- Spare parts
- Vendors
- Historical maintenance

Process:
Upload → Validate → Preview → Error report → Confirm → Import.

Exports must respect tenant and permission boundaries.

---

## 34. Audit and Data Governance

Audit events:
- Login/logout
- Failed login
- Create/update/delete
- Status changes
- Permission changes
- Cost changes
- Subscription changes
- Approval actions
- API security events

Financial, audit, and historical records cannot be hard-deleted through normal CRUD.

---

## 35. Data Lifecycle

Entities use:
- Active
- Archived
- Soft deleted where appropriate
- Permanently deleted only under policy

Subscription lifecycle:

Active → Grace Period → Read Only → Archived

Customer data remains owned by the customer.

---

## 36. Security

Requirements:
- HTTPS
- Sanctum authentication
- RBAC
- Tenant isolation
- Authorization policies
- Rate limiting
- Input validation
- File validation
- Private object storage
- Signed file URLs
- Audit logging
- Secure secrets
- Database backups
- Encryption at rest where supported

---

## 37. File Storage

Private storage for:
- Manuals
- Invoices
- Warranty files
- AMC contracts
- Photos
- Checklist evidence

Files use signed temporary URLs.

Document versioning is required for important manuals/contracts.

---

## 38. Offline/Poor Connectivity Readiness

MVP should support:
- PWA-ready frontend architecture
- Draft form persistence where practical
- Retry-safe API operations
- Idempotency keys for critical create operations

Full offline synchronization is future scope.

---

## 39. Concurrency and Idempotency

Optimistic locking/version checks prevent overwriting concurrent edits.

Critical POST endpoints accept idempotency keys to prevent duplicate submissions.

---

## 40. Subscription and Billing

No mandatory fixed packages.

Each customer can have a custom subscription contract:
- Start/end
- Billing cycle
- Amount
- Currency
- Trial
- Grace period
- Status
- Renewal

Billing entities:
Subscription → Contract → Invoice → Payment → Refund/Credit Note

Payment methods:
- Manual
- Online gateway integration-ready

Subscription cancellation does not immediately delete data.

Usage metrics are tracked independently of billing.

---

## 41. Timezone

Store timestamps in UTC.

Display and schedule calculations use factory timezone, with user timezone available for presentation.

---

## 42. API Requirements

API:
`/api/v1`

Requirements:
- Pagination
- Filtering
- Sorting
- Search
- Consistent validation
- Consistent error format
- Tenant context
- Authorization
- Rate limiting
- Idempotency
- Versioning

---

## 43. Integration

Future integrations:
- ERP
- HRM
- Production
- Accounting
- IoT

Supported integration primitives:
- REST API
- API keys/OAuth as appropriate
- Webhooks
- Domain events

---

## 44. Inspection and Compliance Readiness

Future modules:
- Inspection plans
- Safety checklists
- Non-conformities
- Corrective actions
- Calibration
- Fire safety
- Boiler inspection

Asset architecture must support these without redesigning the tenant model.

---

## 45. Non-Functional Requirements

### Performance
- API p95 target under 500 ms for normal CRUD operations under expected load.
- Dashboard aggregates should use caching/precomputation where necessary.

### Availability
Target 99.5%+ for production, subject to infrastructure SLA.

### Scalability
Horizontal application scaling must be possible.

### Security
Tenant isolation is mandatory.

### Maintainability
Business logic must be placed in service/action/domain layers rather than fat controllers.

### Observability
Application logs, queue monitoring, error monitoring, and audit logs are required.

### Backup
Daily database backup, file backup, retention policy, and periodic restore testing.

---

## 46. Acceptance Criteria

The MVP is acceptable when:

1. A company can create multiple factories.
2. Users can only access authorized tenant data.
3. Assets can be registered, transferred, and retired.
4. Preventive plans generate due schedules.
5. Date and meter-based triggers work.
6. Technicians can execute checklists.
7. Breakdown workflows calculate downtime.
8. Spare parts can be reserved and issued.
9. Maintenance cost is calculated.
10. Real-time notifications work securely.
11. Reports respect tenant boundaries.
12. Subscription expiration can transition a tenant to read-only.
13. Audit logs record critical actions.
14. API and WebSocket authorization prevent cross-tenant access.
15. Data export and import work with validation.

16. Labor and parts are recorded per work order and roll up into actual cost.
17. Approval matrices block work orders above the configured threshold.
18. Availability and MTTR are computed from the factory shift calendar.
19. The UI is usable in both English and Bengali.
20. A tenant can export all of its data in a machine-readable archive.

---

## 47. Working Calendar, Shifts and Holidays

Garment factories operate on shifts with overtime and public holidays. Downtime, availability, response-time SLAs, and escalation timers are meaningless without this model.

### 47.1 Requirements

Each factory defines:

- Operating mode: `CONTINUOUS` (24x7) or `SHIFT_BASED`.
- A shift pattern: named shifts with start time, end time, and days of week.
- Break periods per shift, with a flag for whether breaks count as operating time.
- Overtime windows.
- A holiday calendar, including recurring weekly off-days and dated public holidays.
- Optional per-production-line overrides where lines run different shifts.

### 47.2 Rules

1. Shift definitions are versioned with an effective date range. Editing a shift must not retroactively change closed periods.
2. Shifts crossing midnight are supported and are attributed to their start date.
3. All calendar math is performed in the factory timezone, then persisted in UTC.
4. If no calendar is configured, the factory falls back to `CONTINUOUS` and reports must display that fallback so the numbers are not silently misread.
5. Holidays and non-operating time are excluded from scheduled operating time.

### 47.3 Interaction With Scheduling

Preventive maintenance due dates may optionally be shifted off non-working days using a configurable policy: `NONE`, `NEXT_WORKING_DAY`, or `PREVIOUS_WORKING_DAY`.

---

## 48. Localization and Internationalization

The primary deployment market is Bangladesh. Bilingual operation is a functional requirement, not a future enhancement.

### 48.1 Requirements

1. UI languages: English and Bengali (`bn`), with the architecture supporting further locales.
2. Language is resolved per user, defaulting to the company default language.
3. API responses carry machine-readable `code` values; the frontend renders localized text. Server-generated human text (emails, notifications, exported report headers) is localized using the recipient locale.
4. Tenant-entered master data (asset names, failure codes, checklist labels) is stored as entered. Optional translated labels may be added per locale for seeded master data.
5. Numbers, dates, and currency follow the user locale for display; storage remains ISO 8601 UTC and decimal.
6. All database text columns use `utf8mb4` with a `utf8mb4_unicode_ci` collation so Bengali text sorts, searches, and truncates correctly.
7. PDF and Excel exports must embed a font with full Bengali glyph coverage. A PDF export that renders Bengali as boxes is a defect.
8. The layout is left-to-right; no RTL support is required for MVP, but no hard-coded directional assumptions should block it.

---

## 49. Data Retention, Privacy and Deletion

### 49.1 Retention Periods

| Data class | Minimum retention | Policy |
|---|---|---|
| Audit logs | 7 years | Append-only, archived to cold storage after 2 years |
| Financial records (invoices, payments, cost entries) | 7 years | Immutable |
| Inventory ledger | 7 years | Immutable |
| Work orders, breakdowns, maintenance history | Life of asset + 3 years | Archivable |
| Meter readings | 3 years at full resolution | Downsampled after 3 years |
| Notifications | 12 months | Purgeable |
| Webhook delivery payloads | 30 days | Purgeable |
| Import/export job files | 30 days | Purgeable |
| Application logs | 90 days | Purgeable |

Retention periods are configurable upward per contract, never below statutory minimums.

### 49.2 Personal Data

Personal data in the system is limited to user and technician identity and contact details. The platform is a B2B workforce system; it stores no consumer personal data.

Requirements:

1. A tenant may request an export of all of its data (Section 49.3).
2. Personal data for a user may be pseudonymized on request: name and contact fields are replaced with a stable pseudonym while the audit and work history remain intact under the same identifier. Personal data is never hard-deleted out of audit or financial history.
3. Passwords are hashed. Contact details are not exposed cross-tenant.
4. A documented data processing agreement and sub-processor list are required before production onboarding.

### 49.3 Tenant Data Export and Offboarding

On request or on subscription termination, a tenant may obtain a complete export:

- Structured data as CSV or JSONL per entity.
- Stored files with a manifest mapping file IDs to paths.
- Generated asynchronously, delivered through a signed, expiring URL.

Deletion after offboarding follows: `Read Only` then `Archived`, with hard deletion only after a contractually defined period (default 180 days) and an explicit, audited authorization. Automatic hard deletion on payment failure is prohibited.

---

## 50. Authentication and Account Security Policy

### 50.1 Password Policy

- Minimum 10 characters.
- Checked against a known-breached password list.
- No forced periodic rotation; rotation is forced only on suspected compromise.
- Hashed with bcrypt or Argon2id.

### 50.2 Session and Token Policy

- Sanctum tokens carry an absolute expiry (default 30 days) and an idle expiry (default 12 hours) for browser sessions.
- Users can list and revoke their active sessions and devices.
- Password change, password reset, and role revocation invalidate existing tokens.
- Machine-to-machine API clients use separate, scoped API tokens that are never issued to browser sessions.

### 50.3 Multi-Factor Authentication

- TOTP-based MFA is supported for all users.
- MFA is enforceable per company policy, and is mandatory for Platform Super Admin and Company Owner roles.
- Recovery codes are issued once, stored hashed, and single-use.

### 50.4 Brute Force and Abuse Controls

- Login is rate limited per IP and per account.
- Progressive lockout after repeated failures, with a documented unlock path.
- Failed logins, lockouts, MFA failures, and password resets are audited (Section 34).

---

## 51. Capacity and Volume Assumptions

Performance targets in Section 45 are meaningless without a stated load. The MVP is sized for:

| Dimension | Target | Ceiling before re-architecture |
|---|---|---|
| Tenants (companies) | 200 | 1,000 |
| Factories per company | 10 | 50 |
| Assets per company | 20,000 | 100,000 |
| Concurrent users per company | 100 | 500 |
| Work orders created per day per company | 2,000 | 10,000 |
| Meter readings per day per company | 50,000 | 500,000 |
| Inventory transactions per day per company | 5,000 | 25,000 |
| Notifications per day per company | 20,000 | 100,000 |
| Audit rows per day per company | 50,000 | 250,000 |
| Concurrent WebSocket connections per node | 5,000 | 20,000 |

High-volume tables (`audit_logs`, `meter_readings`, `inventory_transactions`, `notifications`, `webhook_deliveries`) require an archival or partitioning strategy before the ceiling is reached.

Load testing must validate the Section 45 latency targets at the target column, not at the ceiling.

---

## 52. Document Numbering and Sequences

Human-readable numbers are required for work orders, breakdowns, transfers, invoices, warranty claims, and service contracts.

### 52.1 Requirements

1. Numbers are unique per company, and per factory where the format includes a factory segment.
2. Format is configurable per company and document type, for example `WO-{FACTORY}-{YYYY}{MM}-{SEQ:5}`.
3. Sequence allocation is race-safe under concurrent creation. Gaps are acceptable; duplicates are not.
4. Sequence counters reset according to a configured period: never, yearly, or monthly.
5. The generated number is stored on the record and is immutable.
6. Numbers are display identifiers. API resource identifiers remain ULIDs.

---

## 53. Configuration and Settings

Several requirements refer to configurable behavior. Configuration is a first-class, tenant-scoped, audited feature rather than a set of environment constants.

### 53.1 Resolution Order

```text
Platform default -> Company setting -> Factory override -> Line override (where applicable)
```

The first defined value wins. Every effective value must be traceable to the level that defined it.

### 53.2 Minimum Configurable Settings

| Key | Level | Default |
|---|---|---|
| `metrics.planned_downtime_counts_against_availability` | Company / Factory | false |
| `metrics.downtime_uses_shift_calendar` | Company / Factory | true |
| `inventory.allow_negative_stock` | Company / Factory | false |
| `inventory.costing_method` | Company | WEIGHTED_AVERAGE |
| `maintenance.schedule_generation_horizon_days` | Company | 90 |
| `maintenance.non_working_day_policy` | Factory | NEXT_WORKING_DAY |
| `maintenance.block_pm_during_active_breakdown` | Company | true |
| `work_order.require_verification_for_criticality` | Company | CRITICAL,HIGH |
| `work_order.approval_cost_threshold` | Company / Factory | contract-defined |
| `notification.escalation_enabled` | Company | true |
| `subscription.grace_period_days` | Company | contract-defined |
| `locale.default` | Company | en |
| `numbering.{document_type}.format` | Company | see Section 52 |

Setting changes are audited with old and new values.

---

## 54. Error Handling and Degraded Operation

The system must behave predictably when a dependency fails.

| Dependency | Failure behavior |
|---|---|
| Redis (cache) | Fall back to direct database reads; log; do not fail the request |
| Redis (queue) | Reject operations that require queueing with `503` and a retryable error code; never silently drop a job |
| Reverb / WebSocket | UI falls back to polling; no data loss, because REST is the source of truth |
| Object storage | File upload and download fail explicitly; core workflows remain usable without attachments |
| Mail / SMS provider | Notification persists in-app and is retried; delivery failure is recorded in `notification_deliveries` |
| Webhook endpoint | Retry with exponential backoff, then disable the endpoint and notify the tenant |

Background jobs must be idempotent and safe to retry. Failed jobs must be visible in Horizon and must alert after a threshold.

---

## 55. Test Strategy and Quality Gates

### 55.1 Required Test Coverage

Automated tests are mandatory for:

1. Tenant isolation: every tenant-scoped endpoint has a cross-tenant access test that asserts `404` or `403`.
2. Authorization: each permission is exercised positively and negatively.
3. Maintenance schedule generation: rolling versus fixed, combined OR/AND rules, grace periods, non-working-day policy, timezone boundaries, DST.
4. Meter logic: monotonic enforcement, reset events, meter-triggered due calculation.
5. Work order state machine: every allowed transition, and rejection of every disallowed transition.
6. Inventory: concurrent issue against limited stock, weighted average recalculation, reservation lifecycle, negative stock prevention, transfer lifecycle.
7. Costing: labor and parts rollup, append-only correction via reversal.
8. Downtime and KPI formulas, including shift-calendar-aware cases and zero-denominator cases.
9. Idempotency: a duplicate key returns the original result and creates no second record.
10. Optimistic locking: a stale version returns `409`.
11. WebSocket channel authorization: cross-tenant subscription is rejected.
12. Subscription lifecycle transitions, including read-only enforcement on write endpoints.

### 55.2 Gates

- CI runs the full suite on every pull request.
- Static analysis (PHPStan/Larastan) and code style checks must pass.
- Database migrations must be reversible and must run against a seeded database in CI.
- No merge with a failing tenant-isolation or authorization test.
- A seed dataset representative of a mid-size factory is maintained for manual and load testing.

### 55.3 User Acceptance

Each module has UAT scenarios written from the role perspectives in Section 5, executed by the customer before production cutover.

---

## 56. Migration and Cutover

Factories arrive with legacy spreadsheets and, sometimes, an incumbent system.

1. Import supports assets, locations, spare parts, opening stock balances, vendors, and historical maintenance records (Section 33).
2. Opening inventory balances are posted as `OPENING_BALANCE` ledger transactions, never as direct balance writes.
3. Historical maintenance records may be imported as closed work orders flagged `imported`, so they feed history and MTBF without polluting compliance metrics for periods before go-live.
4. A dry-run mode validates a full import and produces an error report without writing.
5. Cutover requires a documented rollback plan and a verified backup taken immediately before import.

---

## 57. Requirements Traceability

Every requirement section maps to data model sections, API endpoints, and acceptance criteria. The traceability matrix is maintained in `05-Gap-Analysis-and-Traceability.md` and must be updated whenever a requirement, table, or endpoint is added or removed.

A requirement with no corresponding table and endpoint is not implementable. An endpoint with no corresponding requirement is scope creep.

---

## 58. Open Questions

Items that require a customer or business decision before the affected module is built:

1. Statutory retention periods for maintenance records in the target jurisdiction.
2. Whether external contractor labor is invoiced through the platform or only recorded as cost.
3. Whether production loss is entered manually or requires production system integration at MVP.
4. Tax treatment and invoice format requirements for subscription invoices in Bangladesh.
5. Whether spare part costing must support FIFO at go-live for any committed customer.
6. Payment gateway selection, and whether local gateways (bKash, Nagad, SSLCommerz) are required at MVP.
