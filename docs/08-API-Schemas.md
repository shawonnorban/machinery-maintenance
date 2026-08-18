# 08-API-Schemas.md
# API Request and Response Schemas
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.0
**Status:** Accepted
**Companion to:** `03-API-Specification.md`, `openapi.yaml`

---

## 1. Purpose

`03-API-Specification.md` lists 263 endpoints as paths and behavior. It does not say what a request body contains or what a response looks like. A frontend developer cannot write a client from it and a backend developer cannot write validation from it.

This document defines the conventions that apply to every endpoint, then the representations and write schemas for the core resources. `openapi.yaml` is the machine-readable form and is generated from the Laravel request and resource classes (Handbook 6.4), which keeps it from drifting.

Where an endpoint is not detailed here, it follows the conventions in Section 2 and the resource representation for its entity.

---

## 2. Universal Conventions

### 2.1 Identifiers and Types

| Type | Wire format | Example |
|---|---|---|
| Resource id | ULID string, 26 chars | `"01HX7QF3M2K8VN4RTZ9WPYB6CD"` |
| Timestamp | ISO 8601 UTC with milliseconds | `"2026-08-18T09:14:02.451Z"` |
| Date | ISO 8601 date | `"2026-08-18"` |
| Money | String decimal, never a float | `"1250.7500"` |
| Quantity | String decimal | `"12.0000"` |
| Duration | Integer minutes | `142` |
| Enum | Uppercase string from Data Dictionary | `"IN_PROGRESS"` |
| Boolean | JSON boolean | `true` |

Money and quantity are strings because IEEE 754 cannot represent `0.1` exactly, and a maintenance cost that drifts by fractions across a year of aggregation is not defensible to a customer.

### 2.2 Nested Resource Shape

A response embeds a compact reference for related entities, never a full nested object and never a bare id:

```json
{
  "asset": {
    "id": "01HX7QF3M2K8VN4RTZ9WPYB6CD",
    "asset_code": "SEW-DHK-00412",
    "name": "Juki DDL-9000C"
  }
}
```

The reference always carries `id`, the human code, and the display name. That is enough to render a table row or a link without a second request, and small enough that embedding it everywhere costs nothing.

Full related objects are returned only when explicitly requested with `?include=`, limited to one level (API 35.3 rule 4).

### 2.3 Request Rules

1. `company_id` is never accepted in a body or a query. It is derived from the authenticated context (ADR-064).
2. Derived values are never accepted: `actual_cost`, `total_downtime_minutes`, `balance_after`, `minutes`, `amount` on labor entries.
3. Unknown fields are rejected with `422`, not silently ignored. A typo in a field name must fail loudly.
4. `PATCH` applies only the fields present. Sending `null` sets a nullable field to null; omitting it leaves it unchanged.
5. Write endpoints on versioned resources require `version` in the body or `If-Match` in the header.

### 2.4 List Response Envelope

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 12,
    "per_page": 25,
    "total": 287,
    "request_id": "01HX7QF3M2K8VN4RTZ9WPYB6CD"
  },
  "links": {
    "first": "/api/v1/assets?page=1",
    "prev": null,
    "next": "/api/v1/assets?page=2",
    "last": "/api/v1/assets?page=12"
  }
}
```

### 2.5 Common Query Parameters

| Parameter | Applies to | Notes |
|---|---|---|
| `page`, `per_page` | All lists | `per_page` max 100 |
| `cursor` | Append-only lists | Mutually exclusive with `page` |
| `sort`, `direction` | All lists | Allowlisted fields only; `direction` is `asc` or `desc` |
| `search` | Most lists | Prefix match on code and name |
| `include` | Detail endpoints | Comma-separated, one level deep |
| `factory_id` | Tenant-scoped lists | Filters within the user's accessible factories; never widens scope |
| `created_from`, `created_to` | Most lists | ISO dates, inclusive |

---

## 3. Authentication

### POST `/auth/login`

Request:
```json
{
  "email": "supervisor@example.com",
  "password": "correct-horse-battery",
  "device_name": "Chrome on Windows"
}
```

Response `200`, no MFA:
```json
{
  "success": true,
  "data": {
    "token": "12|xLq...",
    "expires_at": "2026-09-17T09:14:02.451Z",
    "user": {
      "id": "01HX...",
      "name": "Rahim Uddin",
      "email": "supervisor@example.com",
      "locale": "bn",
      "timezone": "Asia/Dhaka",
      "mfa_enabled": true
    },
    "companies": [
      {
        "id": "01HW...",
        "name": "Delta Apparels Ltd",
        "code": "DAL",
        "base_currency": "BDT",
        "default_locale": "bn"
      }
    ],
    "active_company_id": "01HW..."
  }
}
```

Response `200`, MFA required:
```json
{
  "success": true,
  "data": {
    "mfa_required": true,
    "challenge_token": "chg_01HX...",
    "expires_at": "2026-08-18T09:19:02.451Z",
    "methods": ["TOTP", "RECOVERY_CODE"]
  }
}
```

No access token is issued until `/auth/mfa/verify` succeeds. Errors: `422 VALIDATION_ERROR`, `401 UNAUTHENTICATED` (generic message, never revealing whether the email exists), `429 RATE_LIMITED`, `403 ACCOUNT_LOCKED`.

### GET `/auth/permissions`

```json
{
  "success": true,
  "data": {
    "company_id": "01HW...",
    "roles": [{"code": "MAINTENANCE_MANAGER", "factory_id": null}],
    "permissions": ["asset.asset.view_any", "work_order.work_order.verify"],
    "accessible_factory_ids": ["01HW...", "01HW..."]
  }
}
```

The frontend renders authorization from this. It is never the enforcement point; the server re-checks on every request.

---

## 4. Asset

### 4.1 Asset Representation

```json
{
  "id": "01HX7QF3M2K8VN4RTZ9WPYB6CD",
  "asset_code": "SEW-DHK-00412",
  "name": "Juki DDL-9000C Single Needle Lockstitch",
  "serial_number": "JK9000C-2023-88421",
  "status": "RUNNING",
  "criticality": "MEDIUM",
  "asset_type": {"id": "01HW...", "code": "SEWING", "name": "Sewing Machine"},
  "asset_category": {"id": "01HW...", "code": "LOCKSTITCH", "name": "Lockstitch"},
  "manufacturer": {"id": "01HW...", "code": "JUKI", "name": "Juki"},
  "asset_model": {"id": "01HW...", "code": "DDL-9000C", "name": "DDL-9000C"},
  "parent_asset": null,
  "factory": {"id": "01HW...", "code": "DHK", "name": "Dhaka Unit 1"},
  "location": {
    "id": "01HW...",
    "code": "DHK-A-2-SEW-L03-W12",
    "name": "Line 3 / Workstation 12",
    "full_path": "Dhaka Unit 1 › Building A › Floor 2 › Sewing › Line 3 › WS 12"
  },
  "qr_code": "K7M2VN4RTZ9W",
  "barcode": "8801234567890",
  "country_of_origin": "JP",
  "purchase_date": "2023-04-11",
  "installation_date": "2023-05-02",
  "commissioning_date": "2023-05-06",
  "warranty": {"start": "2023-05-06", "end": "2025-05-05", "is_active": false},
  "open_breakdown_id": null,
  "next_maintenance_due_at": "2026-09-04T02:00:00.000Z",
  "meters": [
    {"id": "01HW...", "meter_type": "RUNNING_HOURS", "unit": "HOUR", "current_value": "4187.5000"}
  ],
  "financial": {
    "acquisition_cost": "285000.0000",
    "installation_cost": "12000.0000",
    "currency": "BDT",
    "lifetime_maintenance_cost": "41750.0000"
  },
  "version": 7,
  "created_at": "2023-04-11T04:22:10.000Z",
  "updated_at": "2026-08-14T11:02:44.318Z"
}
```

The `financial` object is omitted entirely for callers without `asset.financial.view`. It is not returned as nulls, because a null is indistinguishable from "no cost recorded".

### 4.2 POST `/assets`

```json
{
  "asset_code": "SEW-DHK-00412",
  "name": "Juki DDL-9000C Single Needle Lockstitch",
  "asset_type_id": "01HW...",
  "asset_category_id": "01HW...",
  "manufacturer_id": "01HW...",
  "asset_model_id": "01HW...",
  "parent_asset_id": null,
  "serial_number": "JK9000C-2023-88421",
  "barcode": "8801234567890",
  "criticality": "MEDIUM",
  "status": "DRAFT",
  "current_factory_id": "01HW...",
  "asset_location_id": "01HW...",
  "country_of_origin": "JP",
  "purchase_date": "2023-04-11",
  "installation_date": "2023-05-02",
  "commissioning_date": "2023-05-06",
  "acquisition_cost": "285000.0000",
  "installation_cost": "12000.0000",
  "currency": "BDT",
  "supplier_id": "01HW...",
  "warranty_start": "2023-05-06",
  "warranty_end": "2025-05-05",
  "meters": [{"meter_type_id": "01HW...", "initial_value": "0.0000"}]
}
```

| Field | Rules |
|---|---|
| `asset_code` | Required, 1-64 chars, unique per company, immutable after creation |
| `name` | Required, 1-255 chars |
| `asset_type_id` | Required, must belong to the company or be a platform type |
| `asset_category_id` | Required, must belong to `asset_type_id` |
| `parent_asset_id` | Optional; must be same company; must not create a cycle |
| `serial_number` | Optional; unique per company when present |
| `criticality` | Required, Data Dictionary 2.1 |
| `status` | Optional, defaults `DRAFT`; only `DRAFT`, `PURCHASED`, `INSTALLED` accepted at creation |
| `asset_location_id` | Required; its factory must equal `current_factory_id` (ERD 32 rule 23) |
| `acquisition_cost` | Optional decimal >= 0; requires `asset.financial.view` |
| `currency` | ISO 4217; required when any cost is present |
| `commissioning_date` | Must be on or after `installation_date`, which must be on or after `purchase_date` |
| `warranty_end` | Must be after `warranty_start` |

`qr_code` is server-generated and never accepted from the client. Supports `Idempotency-Key`.

Errors: `422 VALIDATION_ERROR`, `409 CONFLICT` (duplicate `asset_code`), `403 FORBIDDEN`.

### 4.3 POST `/assets/{asset}/transfer`

```json
{
  "to_factory_id": "01HW...",
  "to_location_id": "01HW...",
  "reason": "Line rebalancing for winter order",
  "transfer_at": "2026-08-20T03:00:00.000Z",
  "notes": null,
  "version": 7
}
```

Returns `201` with the transfer record in `REQUESTED` or, when no approval is configured, `RECEIVED` with the asset already moved. `to_factory_id` outside the caller's company returns `404`, not `403`, so cross-tenant probing gains nothing.

### 4.4 PATCH `/assets/{asset}`

Accepts any creatable field except `asset_code`, plus a required `version`. A stale version returns:

```json
{
  "success": false,
  "message": "This asset was modified by someone else.",
  "code": "CONFLICT",
  "meta": {
    "current_version": 9,
    "submitted_version": 7,
    "request_id": "01HX..."
  }
}
```

Returning the current version lets the client offer a reload rather than a dead end.

---

## 5. Work Order

### 5.1 Work Order Representation

```json
{
  "id": "01HX...",
  "work_order_number": "WO-DHK-202608-00417",
  "title": "Monthly PM - Lockstitch Line 3",
  "status": "IN_PROGRESS",
  "priority": "MEDIUM",
  "approval_status": "NOT_REQUIRED",
  "source": "PLAN",
  "maintenance_type": {"id": "01HW...", "code": "PREVENTIVE", "name": "Preventive"},
  "asset": {"id": "01HX...", "asset_code": "SEW-DHK-00412", "name": "Juki DDL-9000C"},
  "factory": {"id": "01HW...", "code": "DHK", "name": "Dhaka Unit 1"},
  "maintenance_schedule_id": "01HX...",
  "breakdown_id": null,
  "assigned_team": {"id": "01HW...", "code": "SEW-MAINT", "name": "Sewing Maintenance"},
  "assignments": [
    {"technician": {"id": "01HW...", "employee_id": "T-1042", "name": "Karim Mia"},
     "assigned_at": "2026-08-18T02:10:00.000Z"}
  ],
  "requires_verification": false,
  "requires_shutdown": true,
  "downtime_class": "PLANNED",
  "scheduled_start": "2026-08-18T02:00:00.000Z",
  "scheduled_end": "2026-08-18T04:00:00.000Z",
  "actual_start": "2026-08-18T02:14:33.000Z",
  "actual_end": null,
  "hold_minutes": 25,
  "current_hold": {"reason_code": "AWAITING_PARTS", "started_at": "2026-08-18T03:01:00.000Z"},
  "cost": {
    "estimated_labor_cost": "600.0000",
    "estimated_parts_cost": "1200.0000",
    "actual_labor_cost": "412.5000",
    "actual_parts_cost": "980.0000",
    "actual_other_cost": "0.0000",
    "actual_cost": "1392.5000",
    "currency": "BDT"
  },
  "checklist_progress": {"total": 14, "completed": 9, "required_remaining": 3, "failed": 1},
  "parts_reconciled": false,
  "reopened_count": 0,
  "version": 4,
  "created_at": "2026-08-15T06:00:00.000Z"
}
```

`checklist_progress` and `parts_reconciled` are included because they are exactly the two conditions that block completion. Without them the UI has to guess why a Complete button is disabled.

### 5.2 POST `/work-orders`

```json
{
  "asset_id": "01HX...",
  "maintenance_type_id": "01HW...",
  "title": "Replace worn feed dog",
  "description": "Operator reports uneven feed on heavy fabric.",
  "priority": "MEDIUM",
  "maintenance_schedule_id": null,
  "breakdown_id": null,
  "template_version_id": "01HW...",
  "assigned_team_id": "01HW...",
  "technician_ids": ["01HW..."],
  "scheduled_start": "2026-08-19T02:00:00.000Z",
  "scheduled_end": "2026-08-19T04:00:00.000Z",
  "requires_shutdown": true,
  "estimated_labor_cost": "600.0000",
  "estimated_parts_cost": "1200.0000",
  "currency": "BDT",
  "parts": [{"spare_part_id": "01HW...", "quantity_requested": "2.0000"}]
}
```

| Field | Rules |
|---|---|
| `asset_id` | Required, same company, not `SCRAPPED` or `LOST` |
| `maintenance_type_id` | Required |
| `priority` | Required, Data Dictionary 2.2 |
| `scheduled_end` | Must be after `scheduled_start` |
| `template_version_id` | Optional; must be a published version; snapshotted onto the work order |
| `technician_ids` | Optional; each must belong to the asset's factory |
| `parts[].quantity_requested` | Decimal > 0 |

Server-set on creation: `work_order_number` (Data Dictionary 6), `status`, `requires_verification` (from criticality and settings), `approval_status` (from the approval matrix), `downtime_class`.

Response `201`. Errors: `422`, `403 SUBSCRIPTION_READ_ONLY`, `409 IDEMPOTENCY_CONFLICT`.

### 5.3 Work Order Transition Endpoints

All follow one shape. Request:

```json
{
  "reason": "Waiting for feed dog from central store",
  "reason_code": "AWAITING_PARTS",
  "notes": null,
  "version": 4
}
```

| Endpoint | Required fields | Notes |
|---|---|---|
| `/assign` | `technician_ids` or `team_id` | Replaces the assignment set |
| `/start` | — | Sets `actual_start` |
| `/hold` | `reason_code` | Data Dictionary 2.5; stops the repair clock |
| `/resume` | — | Accumulates `hold_minutes` |
| `/complete` | — | Rejected if required checklist incomplete or parts unreconciled |
| `/verify` | — | Rejected if verifier completed the work |
| `/close` | — | Rejected while approval or verification pending |
| `/cancel` | `reason` | Terminal |
| `/reopen` | `reason` | Elevated permission |

Every one returns the full updated work order. Failures return the specific code, so the UI can explain rather than showing a generic error:

```json
{
  "success": false,
  "message": "3 required checklist items are not answered.",
  "code": "CHECKLIST_INCOMPLETE",
  "meta": {
    "incomplete_item_ids": ["01HW...", "01HW...", "01HW..."],
    "request_id": "01HX..."
  }
}
```

### 5.4 POST `/work-orders/{workOrder}/labor`

```json
{
  "technician_id": "01HW...",
  "labor_category": "REGULAR",
  "started_at": "2026-08-18T02:14:00.000Z",
  "ended_at": "2026-08-18T03:44:00.000Z",
  "notes": "Feed dog replacement and timing adjustment"
}
```

For `EXTERNAL`, `vendor_id` and `hourly_rate` are required and `technician_id` is optional. For `REGULAR` and `OVERTIME`, `hourly_rate` is rejected if supplied: the rate comes from the grade effective on `started_at` (ADR-065).

Response `201`:
```json
{
  "id": "01HX...",
  "technician": {"id": "01HW...", "employee_id": "T-1042", "name": "Karim Mia"},
  "labor_category": "REGULAR",
  "labor_grade": {"id": "01HW...", "code": "SR_TECH", "name": "Senior Technician"},
  "started_at": "2026-08-18T02:14:00.000Z",
  "ended_at": "2026-08-18T03:44:00.000Z",
  "minutes": 90,
  "hourly_rate": "275.0000",
  "currency": "BDT",
  "amount": "412.5000",
  "base_amount": "412.5000"
}
```

Validation: `ended_at` after `started_at`; duration at most 24 hours; no overlap with another entry for the same technician, which returns `409 CONFLICT` naming the conflicting entry.

### 5.5 POST `/work-orders/{workOrder}/checklist/results`

Batch submission, because a technician answers many items then syncs once:

```json
{
  "results": [
    {"checklist_item_id": "01HW...", "result": "PASS"},
    {"checklist_item_id": "01HW...", "result": "FAIL",
     "observation": "Bobbin case shows wear", "file_id": "01HX..."},
    {"checklist_item_id": "01HW...", "result": "PASS", "numeric_value": "0.4500"},
    {"checklist_item_id": "01HW...", "result": "NA", "observation": "Not fitted on this model"}
  ]
}
```

Rules: `numeric_value` required for `NUMERIC` items and evaluated against tolerance; `observation` required when the item sets `requires_note_on_fail` and the result is `FAIL`; `file_id` required when `requires_attachment_on_fail` and the result is `FAIL`. A `FAIL` on an item with `fail_creates_followup_work_order` returns the created work order id in `meta.created_work_order_ids`.

The batch is atomic per item: valid results are stored and invalid ones are reported, so a technician does not lose an hour of answers to one bad field.

---

## 6. Breakdown

### 6.1 POST `/breakdowns`

```json
{
  "asset_id": "01HX...",
  "failure_at": "2026-08-18T05:40:00.000Z",
  "problem_description": "Machine stops mid-seam, motor humming",
  "severity": "TOTAL_FAILURE",
  "priority": "HIGH",
  "production_line_id": "01HW...",
  "downtime_reason_code_id": "01HW...",
  "failure_category_id": null,
  "attachments": ["01HX..."]
}
```

| Field | Rules |
|---|---|
| `asset_id` | Required; must not be `SCRAPPED`, `RETIRED`, or `LOST` |
| `failure_at` | Required; may not be in the future; may not precede commissioning |
| `problem_description` | Required, 5-2000 chars |
| `severity` | Required, Data Dictionary 2.3 |
| `priority` | Optional; defaults from asset criticality |

`reported_at` is server-set to now, never client-supplied, because response-time metrics depend on it. Creating a breakdown moves the asset to `BREAKDOWN` and opens a `downtime_records` row.

If the asset already has an open breakdown, the response is `200` with the existing breakdown and `meta.linked_as_recurrence: true`, rather than creating a duplicate. Three operators reporting the same stopped machine must not become three failures in the MTBF denominator.

Supports `Idempotency-Key`, which matters most here: this endpoint is hit from phones on factory wifi.

### 6.2 POST `/breakdowns/{breakdown}/close`

```json
{
  "root_cause_id": "01HW...",
  "failure_code_id": "01HW...",
  "corrective_action": "Replaced motor capacitor and re-tensioned belt",
  "preventive_action": "Add capacitor check to quarterly PM checklist",
  "closure_notes": null,
  "version": 6
}
```

Rejected with `422` when `repair_completed_at` is unset, or when `root_cause_id` or `failure_code_id` is missing. Closing finalizes the downtime record.

### 6.3 GET `/breakdowns/{breakdown}/downtime`

```json
{
  "success": true,
  "data": {
    "downtime_class": "UNPLANNED",
    "counts_against_availability": true,
    "calendar_aware": true,
    "timestamps": {
      "failure_at": "2026-08-18T05:40:00.000Z",
      "reported_at": "2026-08-18T05:47:12.000Z",
      "acknowledged_at": "2026-08-18T05:52:40.000Z",
      "technician_arrival_at": "2026-08-18T06:05:00.000Z",
      "repair_started_at": "2026-08-18T06:09:30.000Z",
      "repair_completed_at": "2026-08-18T07:22:10.000Z",
      "production_resumed_at": "2026-08-18T07:31:00.000Z"
    },
    "response_minutes": 5,
    "arrival_minutes": 18,
    "repair_minutes": 73,
    "hold_minutes": 0,
    "total_downtime_minutes": 111,
    "wall_clock_minutes": 111,
    "scheduled_operating_minutes_in_window": 111,
    "calculation_version": 3
  }
}
```

`wall_clock_minutes` is returned alongside the calendar-aware figure so a manager can see the difference rather than doubting the number. When a breakdown spans a shift break they diverge, and that divergence is the calendar doing its job.

---

## 7. Inventory

### 7.1 POST `/spare-parts/{part}/issue`

```json
{
  "bin_id": "01HW...",
  "quantity": "2.0000",
  "work_order_id": "01HX...",
  "reservation_id": "01HX...",
  "substitute_for_spare_part_id": null,
  "notes": null
}
```

Response `201`:
```json
{
  "transaction": {
    "id": "01HX...",
    "transaction_type": "ISSUE",
    "quantity": "2.0000",
    "unit_cost": "490.0000",
    "total_cost": "980.0000",
    "currency": "BDT",
    "balance_after": "14.0000",
    "wac_after": "490.0000",
    "transaction_at": "2026-08-18T06:30:00.000Z"
  },
  "balance": {
    "bin_id": "01HW...",
    "quantity_on_hand": "14.0000",
    "quantity_reserved": "3.0000",
    "quantity_available": "11.0000"
  }
}
```

Returning the resulting balance avoids a second request that would race with another storekeeper.

Insufficient stock returns `409`:
```json
{
  "success": false,
  "message": "Requested 2.0000, available 1.0000 in bin A-03-11.",
  "code": "INSUFFICIENT_STOCK",
  "meta": {
    "requested": "2.0000",
    "quantity_on_hand": "4.0000",
    "quantity_reserved": "3.0000",
    "quantity_available": "1.0000",
    "alternative_bins": [{"bin_id": "01HW...", "code": "B-01-04", "available": "9.0000"}],
    "request_id": "01HX..."
  }
}
```

Naming alternative bins turns a dead end into the next action, which is the difference between a storekeeper solving it and a storekeeper calling someone.

---

## 8. Maintenance Plan and Meter

### 8.1 POST `/maintenance-plans`

```json
{
  "name": "Lockstitch 30-day / 500-hour service",
  "asset_id": null,
  "asset_type_id": "01HW...",
  "maintenance_type_id": "01HW...",
  "template_version_id": "01HW...",
  "trigger_type": "COMBINED",
  "schedule_mode": "ROLLING",
  "rule_logic": "OR",
  "rules": [
    {"rule_type": "TIME", "operator": "EVERY", "value": "30.0000", "unit": "DAY"},
    {"rule_type": "METER", "operator": "EVERY", "value": "500.0000", "unit": "HOUR",
     "meter_type_id": "01HW..."}
  ],
  "priority": "MEDIUM",
  "grace_period_minutes": 2880,
  "lead_time_days": 14,
  "non_working_day_policy": "NEXT_WORKING_DAY",
  "requires_shutdown": true,
  "assigned_team_id": "01HW...",
  "estimated_duration_minutes": 120,
  "start_date": "2026-09-01",
  "end_date": null,
  "active": true
}
```

| Field | Rules |
|---|---|
| `asset_id` / `asset_type_id` | Exactly one required |
| `trigger_type` | `COMBINED` requires at least two rules; others exactly one |
| `rule_logic` | Required when `COMBINED`; rejected otherwise. No default (ADR-012) |
| `rules[].meter_type_id` | Required for `METER` and `USAGE` rules |
| `template_version_id` | Must be a published version |
| `grace_period_minutes` | 0 to 43200 |
| `lead_time_days` | 1 to the company generation horizon |

Activating a plan generates schedules immediately up to `lead_time_days`; it does not wait for the nightly scheduler.

### 8.2 POST `/meters/{meter}/readings`

```json
{
  "value": "4212.5000",
  "reading_at": "2026-08-18T06:00:00.000Z",
  "source": "MANUAL",
  "notes": null
}
```

Response `201`:
```json
{
  "id": "01HX...",
  "value": "4212.5000",
  "previous_value": "4187.5000",
  "delta": "25.0000",
  "reading_at": "2026-08-18T06:00:00.000Z",
  "triggered_schedules": [
    {"id": "01HX...", "maintenance_plan_id": "01HW...", "due_at": "2026-08-18T06:00:00.000Z",
     "reason": "METER_THRESHOLD_REACHED"}
  ]
}
```

Returning `triggered_schedules` tells the operator immediately that their reading just created maintenance work, instead of it appearing silently overnight.

A decreasing value returns `422 METER_VALUE_DECREASED` with the current value in `meta`, and points at `POST /meters/{meter}/reset` for a genuine meter replacement.

---

## 9. Error Catalog Mapping

| Code | HTTP | Typical trigger |
|---|---|---|
| `VALIDATION_ERROR` | 422 | Field validation |
| `UNAUTHENTICATED` | 401 | Missing or expired token |
| `MFA_REQUIRED` | 401 | MFA not yet satisfied |
| `ACCOUNT_LOCKED` | 403 | Brute force lockout |
| `FORBIDDEN` | 403 | Permission or policy denial |
| `TENANT_ACCESS_DENIED` | 403 | `X-Company-Id` names a non-membership |
| `SUBSCRIPTION_READ_ONLY` | 403 | Tenant in read-only lifecycle state |
| `RESOURCE_NOT_FOUND` | 404 | Missing, or belongs to another tenant |
| `CONFLICT` | 409 | Optimistic lock, duplicate unique value, overlapping labor |
| `INVALID_STATUS_TRANSITION` | 409 | Not in the Data Dictionary transition table |
| `IDEMPOTENCY_CONFLICT` | 409 | Same key, different body, or in-flight replay |
| `INSUFFICIENT_STOCK` | 409 | Issue exceeds available |
| `CHECKLIST_INCOMPLETE` | 422 | Complete with required items unanswered |
| `PARTS_NOT_RECONCILED` | 422 | Close with issued parts outstanding |
| `VERIFICATION_REQUIRED` | 422 | Close before verify |
| `SELF_APPROVAL_NOT_ALLOWED` | 403 | Approver is the requester |
| `METER_VALUE_DECREASED` | 422 | Reading below current without a reset |
| `IMMUTABLE_RECORD` | 409 | Edit of a posted ledger, cost, or audit row |
| `DEPENDENT_RECORDS_EXIST` | 409 | Delete of referenced master data |
| `PLAN_LIMIT_EXCEEDED` | 403 | Subscription limit with `BLOCK` overage policy |
| `RATE_LIMITED` | 429 | Rate limit; carries `Retry-After` |
| `DEPENDENCY_UNAVAILABLE` | 503 | Queue or storage down |

Every code maps to exactly one status, fixed for the life of the major version.
