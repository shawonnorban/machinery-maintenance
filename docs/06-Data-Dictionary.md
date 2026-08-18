# 06-Data-Dictionary.md
# Data Dictionary, Enums and State Machines
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.0
**Status:** Accepted
**Companion to:** `02-Database-ERD.md`

---

## 1. Purpose

Every enumerated value, state transition, generated identifier, and currency rule in one place. The ERD names columns; this document says what may go in them.

Rules that apply throughout:

1. Enum values are stored as uppercase `VARCHAR` with an underscore separator, never as integers and never as MySQL `ENUM` (ERD rule 14).
2. The stored value is the contract. Display labels are localized through `translations`; the code never changes with locale.
3. Adding a value is a non-breaking API change. Removing or renaming one is breaking (API 38).
4. A value not listed here is invalid. Validation rejects it rather than storing it.

---

## 2. Shared Enums

### 2.1 Criticality

Applies to `assets.criticality`. Drives verification requirements, escalation, and PM priority.

| Value | Meaning | Typical garment example |
|---|---|---|
| `CRITICAL` | Stops a whole line or the factory; no standby exists | Boiler, generator, compressor, main cutting machine |
| `HIGH` | Stops a line section; limited standby | Bartack, buttonhole, embroidery head |
| `MEDIUM` | Reduces output; work can be rerouted | One of many plain sewing machines |
| `LOW` | No production impact | Office equipment, spare unit in store |

`work_order.require_verification_for_criticality` defaults to `CRITICAL,HIGH` (SRS 53.2).

### 2.2 Priority

Applies to `work_orders.priority`, `breakdowns.priority`, `maintenance_plans.priority`.

| Value | Target response | Target resolution |
|---|---|---|
| `CRITICAL` | 15 minutes | 4 hours |
| `HIGH` | 1 hour | 8 hours |
| `MEDIUM` | 4 hours | 2 working days |
| `LOW` | 1 working day | 5 working days |

Targets are measured against the factory working calendar (SRS 47) and are configurable per company.

### 2.3 Severity

Applies to `breakdowns.severity`. Severity describes the failure; priority describes the response. They are set independently: a severe failure on a spare machine may be low priority.

| Value | Meaning |
|---|---|
| `TOTAL_FAILURE` | Asset cannot run at all |
| `PARTIAL_FAILURE` | Runs at reduced capacity or quality |
| `INTERMITTENT` | Fault appears and clears |
| `DEGRADED` | Runs, but outside specification |
| `SAFETY` | Runs, but is unsafe; must be stopped regardless of output |

`SAFETY` always escalates immediately regardless of priority.

### 2.4 Status Enums by Entity

| Entity | Values |
|---|---|
| `assets.status` | `DRAFT`, `PURCHASED`, `INSTALLED`, `COMMISSIONED`, `RUNNING`, `IDLE`, `UNDER_MAINTENANCE`, `BREAKDOWN`, `UNDER_REPAIR`, `RETIRED`, `SCRAPPED`, `LOST` |
| `work_orders.status` | `DRAFT`, `PENDING_APPROVAL`, `SCHEDULED`, `ASSIGNED`, `IN_PROGRESS`, `ON_HOLD`, `COMPLETED`, `VERIFIED`, `CLOSED`, `CANCELLED`, `REJECTED` |
| `breakdowns.status` | `REPORTED`, `ACKNOWLEDGED`, `ASSIGNED`, `IN_REPAIR`, `ON_HOLD`, `REPAIRED`, `PRODUCTION_RESUMED`, `CLOSED`, `CANCELLED` |
| `maintenance_schedules.status` | `PLANNED`, `DUE`, `OVERDUE`, `IN_PROGRESS`, `COMPLETED`, `SKIPPED`, `CANCELLED` |
| `asset_transfer_history.status` | `REQUESTED`, `APPROVED`, `REJECTED`, `IN_TRANSIT`, `RECEIVED`, `CANCELLED`, `REVERSED` |
| `inventory_transfers.status` | `REQUESTED`, `APPROVED`, `REJECTED`, `DISPATCHED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `CANCELLED` |
| `spare_part_reservations.status` | `ACTIVE`, `PARTIALLY_ISSUED`, `ISSUED`, `RELEASED`, `EXPIRED`, `CANCELLED` |
| `work_order_parts.status` | `REQUESTED`, `RESERVED`, `ISSUED`, `PARTIALLY_CONSUMED`, `CONSUMED`, `RETURNED`, `CANCELLED` |
| `approval_requests.status` | `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`, `EXPIRED` |
| `subscription_contracts.status` | `DRAFT`, `TRIAL`, `ACTIVE`, `PAST_DUE`, `GRACE`, `READ_ONLY`, `ARCHIVED`, `CANCELLED` |
| `subscription_invoices.status` | `DRAFT`, `ISSUED`, `PARTIALLY_PAID`, `PAID`, `OVERDUE`, `VOID`, `WRITTEN_OFF` |
| `import_jobs.status`, `export_jobs.status`, `report_jobs.status` | `QUEUED`, `RUNNING`, `COMPLETED`, `FAILED`, `EXPIRED` |
| `webhook_deliveries.status` | `PENDING`, `DELIVERED`, `FAILED`, `EXHAUSTED` |
| Generic master data `status` / `active` | `ACTIVE`, `INACTIVE`, `ARCHIVED` |

### 2.5 Type and Category Enums

| Column | Values |
|---|---|
| `maintenance_plans.trigger_type` | `TIME`, `METER`, `USAGE`, `CONDITION`, `COMBINED` |
| `maintenance_plans.schedule_mode` | `ROLLING`, `FIXED` |
| `maintenance_plans.rule_logic` | `OR`, `AND` |
| `maintenance_plan_rules.rule_type` | `TIME`, `METER`, `USAGE`, `CONDITION` |
| `maintenance_plan_rules.operator` | `EVERY`, `AFTER`, `AT`, `BETWEEN` |
| `maintenance_plans.interval_unit` | `HOUR`, `DAY`, `WEEK`, `MONTH`, `QUARTER`, `YEAR` |
| `maintenance_plans.non_working_day_policy` | `NONE`, `NEXT_WORKING_DAY`, `PREVIOUS_WORKING_DAY` |
| `checklist_items.input_type` | `PASS_FAIL`, `NUMERIC`, `TEXT`, `CHOICE`, `PHOTO`, `SIGNATURE` |
| `work_order_checklist_results.result` | `PASS`, `FAIL`, `NA` |
| `work_order_labor_entries.labor_category` | `REGULAR`, `OVERTIME`, `EXTERNAL` |
| `work_order_holds.reason_code` | `AWAITING_PARTS`, `AWAITING_APPROVAL`, `AWAITING_VENDOR`, `PRODUCTION_RUNNING`, `SHIFT_END`, `OTHER` |
| `work_orders.source` | `PLAN`, `BREAKDOWN`, `MANUAL`, `CHECKLIST_FAILURE`, `IMPORT` |
| `work_orders.approval_status` | `NOT_REQUIRED`, `PENDING`, `APPROVED`, `REJECTED` |
| `downtime_records.downtime_class` | `UNPLANNED`, `PLANNED`, `NON_OPERATING`, `EXTERNAL` |
| `inventory_transactions.transaction_type` | `OPENING_BALANCE`, `RECEIPT`, `ISSUE`, `CONSUME`, `RETURN`, `ADJUSTMENT_IN`, `ADJUSTMENT_OUT`, `TRANSFER_OUT`, `TRANSFER_IN`, `SCRAP`, `REVERSAL` |
| `cost_entries.source_type` | `LABOR`, `PARTS`, `EXTERNAL_SERVICE`, `VENDOR`, `TRANSPORT`, `MANUAL`, `REVERSAL` |
| `meter_readings.source` | `MANUAL`, `IMPORT`, `API`, `IOT` |
| `notifications.severity` | `INFO`, `WARNING`, `CRITICAL` |
| `notification_deliveries.channel` | `IN_APP`, `EMAIL`, `SMS`, `WHATSAPP`, `WEBSOCKET` |
| `audit_logs.context` | `API`, `UI`, `JOB`, `CONSOLE`, `IMPORT`, `WEBHOOK` |
| `factory_calendars.operating_mode` | `CONTINUOUS`, `SHIFT_BASED` |
| `spare_part_compatibilities.compatibility_type` | `EXACT`, `SUBSTITUTE`, `UPGRADE`, `SUPERSEDED_BY` |
| `asset_documents.document_type` | `MANUAL`, `INVOICE`, `WARRANTY`, `CERTIFICATE`, `DRAWING`, `PHOTO`, `INSPECTION_REPORT`, `OTHER` |

### 2.6 Units of Measure

| Group | Values |
|---|---|
| Meter units | `HOUR`, `CYCLE`, `STITCH`, `PIECE`, `KM`, `KWH`, `LITRE`, `CUBIC_METRE` |
| Inventory units | `PCS`, `SET`, `PAIR`, `BOX`, `METRE`, `LITRE`, `KG`, `GRAM`, `ROLL`, `PACKET` |
| Duration | Always stored in minutes as an integer; never as a formatted string |

Inventory unit conversion is out of MVP scope. A spare part has one unit; receipt, issue, and consumption all use it.

---

## 3. State Machines

A transition not listed is rejected with `409 INVALID_STATUS_TRANSITION`. Every transition writes a status history row with actor, timestamp, and reason where required.

### 3.1 Work Order

Defined in SRS 13.1. Reproduced here as the authoritative transition table.

| From | To | Guard |
|---|---|---|
| `DRAFT` | `PENDING_APPROVAL` | An approval rule matched |
| `DRAFT` | `SCHEDULED` | No approval required |
| `DRAFT` | `CANCELLED` | Reason required |
| `PENDING_APPROVAL` | `SCHEDULED` | All approval steps approved |
| `PENDING_APPROVAL` | `REJECTED` | Any step rejected |
| `PENDING_APPROVAL` | `CANCELLED` | Requester or manager, reason required |
| `REJECTED` | `DRAFT` | Edit and resubmit |
| `REJECTED` | `CANCELLED` | — |
| `SCHEDULED` | `ASSIGNED` | At least one active assignment |
| `SCHEDULED` | `CANCELLED` | Reason required |
| `ASSIGNED` | `IN_PROGRESS` | Sets `actual_start` |
| `ASSIGNED` | `SCHEDULED` | All assignments removed |
| `ASSIGNED` | `CANCELLED` | Reason required |
| `IN_PROGRESS` | `ON_HOLD` | Hold reason code required |
| `IN_PROGRESS` | `COMPLETED` | Required checklist complete; parts reconciled |
| `IN_PROGRESS` | `CANCELLED` | Elevated permission; reason required |
| `ON_HOLD` | `IN_PROGRESS` | Accumulates `hold_minutes` |
| `ON_HOLD` | `CANCELLED` | Elevated permission; reason required |
| `COMPLETED` | `VERIFIED` | `requires_verification`; verifier is not the completer |
| `COMPLETED` | `CLOSED` | Verification not required |
| `COMPLETED` | `IN_PROGRESS` | Reopen; elevated permission, reason, increments `reopened_count` |
| `VERIFIED` | `CLOSED` | Costs posted |
| `CLOSED` | — | Terminal |
| `CANCELLED` | — | Terminal |

### 3.2 Breakdown

Absent from v1.0 and from v1.1 until now; the statuses existed without transitions.

| From | To | Guard |
|---|---|---|
| `REPORTED` | `ACKNOWLEDGED` | Sets `acknowledged_at`, `acknowledged_by` |
| `REPORTED` | `CANCELLED` | Duplicate or false report; reason required |
| `ACKNOWLEDGED` | `ASSIGNED` | Technician or team assigned |
| `ACKNOWLEDGED` | `CANCELLED` | Reason required |
| `ASSIGNED` | `IN_REPAIR` | Sets `repair_started_at`; asset status moves to `UNDER_REPAIR` |
| `ASSIGNED` | `ACKNOWLEDGED` | Assignment withdrawn |
| `IN_REPAIR` | `ON_HOLD` | Hold reason required; excluded from repair time |
| `IN_REPAIR` | `REPAIRED` | Sets `repair_completed_at` |
| `ON_HOLD` | `IN_REPAIR` | Accumulates hold minutes |
| `REPAIRED` | `PRODUCTION_RESUMED` | Sets `production_resumed_at`; asset returns to `RUNNING` |
| `REPAIRED` | `CLOSED` | Only when the asset is not production-linked |
| `PRODUCTION_RESUMED` | `CLOSED` | `root_cause_id` and `failure_code_id` required |
| `CLOSED` | — | Terminal; downtime record finalized |
| `CANCELLED` | — | Terminal; no downtime recorded |

Closing a breakdown finalizes its `downtime_records` row. Reopening is not supported; a recurrence is a new breakdown linked through `is_recurrence_of_breakdown_id`.

### 3.3 Asset Status

| From | To |
|---|---|
| `DRAFT` | `PURCHASED`, `CANCELLED` via delete |
| `PURCHASED` | `INSTALLED` |
| `INSTALLED` | `COMMISSIONED` |
| `COMMISSIONED` | `RUNNING`, `IDLE` |
| `RUNNING` | `IDLE`, `UNDER_MAINTENANCE`, `BREAKDOWN`, `RETIRED` |
| `IDLE` | `RUNNING`, `UNDER_MAINTENANCE`, `BREAKDOWN`, `RETIRED` |
| `UNDER_MAINTENANCE` | `RUNNING`, `IDLE`, `BREAKDOWN` |
| `BREAKDOWN` | `UNDER_REPAIR` |
| `UNDER_REPAIR` | `RUNNING`, `IDLE`, `RETIRED`, `SCRAPPED` |
| `RETIRED` | `SCRAPPED`, `RUNNING` (recommissioned, elevated permission) |
| `SCRAPPED` | — Terminal |
| `LOST` | `RUNNING` (found, elevated permission), `SCRAPPED` |

System-driven transitions: creating a breakdown moves the asset to `BREAKDOWN`; starting repair moves it to `UNDER_REPAIR`; starting a shutdown work order moves it to `UNDER_MAINTENANCE`. These are not manually settable while the driving record is open.

### 3.4 Inventory Transfer

| From | To | Ledger effect |
|---|---|---|
| `REQUESTED` | `APPROVED`, `REJECTED`, `CANCELLED` | None |
| `APPROVED` | `DISPATCHED`, `CANCELLED` | None |
| `DISPATCHED` | `PARTIALLY_RECEIVED`, `RECEIVED` | `TRANSFER_OUT` from source bin to in-transit bin |
| `PARTIALLY_RECEIVED` | `RECEIVED` | `TRANSFER_IN` for received quantity |
| `RECEIVED` | — | Terminal; variance requires an adjustment with a reason |

### 3.5 Subscription

| From | To | Trigger |
|---|---|---|
| `DRAFT` | `TRIAL`, `ACTIVE` | Contract signed |
| `TRIAL` | `ACTIVE`, `CANCELLED` | Trial end |
| `ACTIVE` | `PAST_DUE`, `CANCELLED` | Invoice overdue |
| `PAST_DUE` | `ACTIVE`, `GRACE` | Payment received, or grace begins |
| `GRACE` | `ACTIVE`, `READ_ONLY` | Payment received, or grace expires |
| `READ_ONLY` | `ACTIVE`, `ARCHIVED` | Payment received, or archival period reached |
| `ARCHIVED` | `ACTIVE` | Reactivation, elevated permission |
| `CANCELLED` | — | Terminal |

`READ_ONLY` and `ARCHIVED` reject every write endpoint with `403 SUBSCRIPTION_READ_ONLY`. Read and export endpoints stay available at every state, so a customer can always retrieve their own data (SRS 49.3).

---

## 4. Currency and Exchange Rates

The ERD carries `exchange_rate` and `base_amount` on four tables. Neither the rate source nor the selection rule was specified. Both are defined here.

### exchange_rates
- id
- company_id nullable (null = platform-wide rate)
- from_currency
- to_currency
- rate
- effective_date
- source (`MANUAL`, `PROVIDER`, `CONTRACT`)
- provider_reference nullable
- created_by nullable
- created_at

Unique: `(company_id, from_currency, to_currency, effective_date)`.

### Rules

1. A company defines exactly one `base_currency`. Every cross-currency amount stores the transaction currency, the amount, the rate applied, and the base-currency equivalent.
2. The rate used is the one whose `effective_date` is the latest date **on or before** the transaction's business date. Never the rate at the time the row was inserted, which differs for backdated entries.
3. The applied rate is copied onto the transaction and frozen. A later rate correction never rewrites posted history (ERD 14 rule 2).
4. Same-currency transactions store rate `1.0` explicitly rather than null, so summing never depends on a null check.
5. A missing rate for a required pair and date blocks the posting with `422`. The system never silently substitutes `1.0`.
6. Rates are entered manually by default. A provider integration may populate them, but a manual override always wins for the same date and is audited.
7. Reports aggregate `base_amount` across currencies and `amount` only within a single currency. A report mixing currencies without conversion is a defect.

---

## 5. QR Code and Barcode Content

The QR module was specified behaviorally (SRS 8) but never said what a code contains. That determines whether scanning works offline, whether codes survive a domain change, and whether a printed label leaks data.

### 5.1 Asset QR Content

A scanned asset QR contains a URL, not a bare identifier:

```text
https://{app_domain}/s/{code}
```

Where `{code}` is a 12-character opaque, non-sequential token stored in `assets.qr_code`, unique per company.

Rationale:

1. A URL works in any phone camera without the app installed, which matters when a line supervisor scans a machine for the first time.
2. An opaque token means a printed label on the factory floor reveals no asset id, company id, or count of assets. Sequential codes would let anyone enumerate the fleet.
3. The token is not a credential. Resolving `/s/{code}` requires an authenticated session; an unauthenticated scan lands on the login page and returns to the asset afterwards.

### 5.2 Resolution

`GET /scan/qr/{code}` resolves the token to an asset summary plus the actions the caller is permitted to take (SRS 8). The server derives company and factory context from the token, never from the request.

A token that does not resolve returns `404`, whether it never existed or belongs to another tenant.

### 5.3 Location QR

`asset_locations.qr_code` uses the same format under `/s/l/{code}`. Scanning a location lists the assets currently at it, which is how a stock-take or an audit walk is performed.

### 5.4 Barcode

`assets.barcode` holds an existing manufacturer or customer barcode where one exists. It is Code 128 for printing, is not generated by the platform, and is not used for authentication or resolution. It exists so a factory that already labels machines can search by its own code.

### 5.5 Label Printing

- Minimum printed QR size 20 mm square, error correction level `M`, for readability on an oily machine frame.
- Label carries the QR, the asset code, and the asset name; nothing else.
- Regenerating a token invalidates the old label and is audited. It is used when a label is compromised or when an asset changes tenant ownership, which requires elevated permission.

---

## 6. Document Number Formats

Default formats per SRS 52. Configurable per company through `number_sequences.format`.

| Document | Default format | Example | Reset |
|---|---|---|---|
| Work order | `WO-{FACTORY}-{YYYY}{MM}-{SEQ:5}` | `WO-DHK-202608-00417` | Monthly |
| Breakdown | `BD-{FACTORY}-{YYYY}{MM}-{SEQ:5}` | `BD-DHK-202608-00092` | Monthly |
| Asset transfer | `AT-{FACTORY}-{YYYY}-{SEQ:5}` | `AT-DHK-2026-00031` | Yearly |
| Inventory transfer | `IT-{FACTORY}-{YYYY}-{SEQ:5}` | `IT-DHK-2026-00114` | Yearly |
| Subscription invoice | `INV-{YYYY}-{SEQ:6}` | `INV-2026-000238` | Yearly |
| Warranty claim | `WC-{YYYY}-{SEQ:5}` | `WC-2026-00017` | Yearly |
| Service contract | `AMC-{YYYY}-{SEQ:4}` | `AMC-2026-0009` | Yearly |
| Purchase receipt | `GRN-{FACTORY}-{YYYY}{MM}-{SEQ:5}` | `GRN-DHK-202608-00204` | Monthly |

`{FACTORY}` is `factories.code`, uppercase, 2 to 5 characters. `{SEQ:n}` is zero-padded to `n` digits. Sequences may gap; they never duplicate and never reuse a number after cancellation.
