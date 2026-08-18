# 03-API-Specification.md
# REST API Specification
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.1  
**Base URL:** `/api/v1`  
**Authentication:** Laravel Sanctum  
**Format:** JSON  
**Realtime:** Laravel Reverb / WebSocket  

---

## 1. API Standards

### Request Headers

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
X-Company-Id: {company_id}
Idempotency-Key: {uuid}
X-Request-Id: {uuid}
Accept-Language: en | bn
If-Match: {version}
```

**`X-Company-Id`** selects among the authenticated user's company memberships. It is required only when the user belongs to more than one company. It can never grant access to a company the user is not a member of; such a value returns `403 TENANT_ACCESS_DENIED`. Tenant context is always derived and re-validated server-side from the authenticated membership, never taken on trust from this header.

**`X-Request-Id`** is echoed on every response and written to `audit_logs.request_id`. A support ticket referencing one request id must be traceable to the exact database changes it caused. The server generates one when the client omits it.

**`Accept-Language`** selects the locale for human-readable strings. Machine-readable `code` values never vary by locale (SRS 48).

**`If-Match`** carries the resource `version` for optimistic locking on endpoints that support it (Section 33).

### Standard Success

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

### Standard Error

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."]
  },
  "code": "VALIDATION_ERROR"
}
```

The error envelope is extended so a failure is diagnosable:

```json
{
  "success": false,
  "message": "Insufficient stock for part SP-1042 in bin A-03-11",
  "code": "INSUFFICIENT_STOCK",
  "errors": {
    "quantity": ["Requested 12, available 4."]
  },
  "meta": {
    "request_id": "01HX7Q...",
    "documentation_url": "https://docs.example.com/errors/INSUFFICIENT_STOCK"
  }
}
```

Rules:

1. `code` is stable and machine-readable. Clients branch on `code`, never on `message`.
2. `message` is localized per `Accept-Language`. `code` is not.
3. `errors` is keyed by request field and is present only for `422`.
4. `meta.request_id` is always present.
5. A `500` never leaks a stack trace, SQL, or file path to the client. The `request_id` is the link to the server-side detail.

---

## 2. HTTP Status Codes

- 200 OK
- 201 Created
- 202 Accepted
- 204 No Content
- 400 Bad Request
- 401 Unauthenticated
- 403 Forbidden
- 404 Not Found
- 409 Conflict
- 422 Validation Error
- 429 Rate Limited
- 500 Server Error
- 503 Service Unavailable (a required dependency such as the queue is down; retryable)

Usage rules:

- `403` is returned when the caller is authenticated but not permitted. The reason phrase in the specification header is corrected: `403` is *Forbidden*, not "Unauthorized".
- `404` is returned for a resource that does not exist **and** for a resource that exists in another tenant. Cross-tenant probing must not be able to distinguish the two. The one exception is an explicit `X-Company-Id` mismatch, which returns `403 TENANT_ACCESS_DENIED` because the caller has already named the tenant.
- `409` covers optimistic lock conflicts, idempotency conflicts, and invalid state transitions, distinguished by `code`.
- `422` is used for validation failures. `400` is reserved for malformed requests such as invalid JSON.
- `202` is returned by endpoints that queue work (report jobs, imports, exports, bulk operations) together with a job resource to poll.
- Write endpoints return `403 SUBSCRIPTION_READ_ONLY` when the tenant is in the read-only lifecycle state. Read and export endpoints stay available.

---

## 3. Authentication

### POST `/auth/login`

Request:
```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

### POST `/auth/logout`

### GET `/auth/me`

### POST `/auth/forgot-password`

### POST `/auth/reset-password`

### POST `/auth/verify-email`

### POST `/auth/mfa/challenge`

Returned when login requires a second factor. The login response carries `mfa_required: true` and a short-lived challenge token instead of an access token.

### POST `/auth/mfa/verify`

### POST `/auth/mfa/enable`

### POST `/auth/mfa/disable`

Requires password re-authentication.

### GET `/auth/mfa/recovery-codes`

### POST `/auth/mfa/recovery-codes/regenerate`

### POST `/auth/switch-company`

Switches the active company for a multi-company user and returns the refreshed permission set. v1.0 described `X-Company-Id` but provided no way to enumerate or switch context explicitly.

### GET `/auth/sessions`

Lists the authenticated user's active sessions and devices.

### DELETE `/auth/sessions/{session}`

### POST `/auth/sessions/revoke-all`

Revokes every session except the current one.

### POST `/auth/change-password`

Invalidates all other sessions on success.

### GET `/auth/permissions`

Returns the effective permission codes for the active company and factory scope, so the frontend can render authorization without guessing.

### Authentication Notes

1. Login is rate limited per IP and per account (Section 35.1).
2. A failed login returns a generic message and never reveals whether the email exists.
3. Password reset tokens are single-use and expire in 60 minutes.
4. Token expiry follows SRS 50.2: absolute 30 days, idle 12 hours for browser sessions.

---

## 4. Company Context

### GET `/companies`

Returns companies accessible to the authenticated user.

### GET `/companies/{company}`

### POST `/companies`

Platform-authorized users only.

### PATCH `/companies/{company}`

### GET `/companies/{company}/users`

### POST `/companies/{company}/users`

Tenant membership must be validated.

### DELETE `/companies/{company}/users/{user}`

Revokes membership. The user record is not deleted; their history stays intact.

### GET `/companies/{company}/settings`

### PATCH `/companies/{company}/settings`

Accepts only keys present in `setting_definitions`. Changes are audited with old and new values.

---

## 4.1 User, Role and Permission APIs

Absent from v1.0, which specified a full RBAC model with no way to administer it.

### GET `/users`

Users within the active company. Filters: `status`, `role_id`, `factory_id`, `search`.

### POST `/users`

Creates a user and the company membership, and sends an invitation.

### GET `/users/{user}`

### PATCH `/users/{user}`

### POST `/users/{user}/deactivate`

Deactivation revokes tokens immediately and reassigns nothing; open work orders must be reassigned explicitly.

### POST `/users/{user}/activate`

### GET `/users/{user}/roles`

### POST `/users/{user}/roles`

Body carries `role_id` and an optional `factory_id` for a factory-scoped assignment.

### DELETE `/users/{user}/roles/{assignment}`

### GET `/roles`

### POST `/roles`

Creates a company role. Seeded platform roles are not editable and may only be cloned.

### GET `/roles/{role}`

### PATCH `/roles/{role}`

### DELETE `/roles/{role}`

Rejected with `409 CONFLICT` while the role is assigned to any user.

### GET `/permissions`

The full permission catalog, grouped by module.

### GET `/teams`

### POST `/teams`

### GET `/teams/{team}`

### PATCH `/teams/{team}`

### POST `/teams/{team}/members`

### DELETE `/teams/{team}/members/{member}`

---

## 4.2 API Client APIs

### GET `/api-clients`

### POST `/api-clients`

Returns the client secret exactly once. It is stored hashed and cannot be retrieved again.

### PATCH `/api-clients/{client}`

### POST `/api-clients/{client}/rotate-secret`

### DELETE `/api-clients/{client}`

API client tokens carry an explicit scope subset and are rejected on any endpoint outside that scope.

---

## 5. Factory and Location APIs

### GET `/factories`
Filters:
- status
- search

### POST `/factories`

### GET `/factories/{factory}`

### PATCH `/factories/{factory}`

### DELETE `/factories/{factory}`

### GET `/factories/{factory}/locations`

### POST `/locations`

### PATCH `/locations/{location}`

### GET `/departments`

### GET `/production-lines`

### GET `/workstations`

### GET `/locations`

### GET `/locations/{location}`

### DELETE `/locations/{location}`

Rejected with `409 CONFLICT` while any asset points at the location.

### GET `/buildings` / `POST` / `PATCH` / `DELETE`

### GET `/floors` / `POST` / `PATCH` / `DELETE`

### POST `/departments` / `PATCH` / `DELETE`

### GET `/sections` / `POST` / `PATCH` / `DELETE`

### POST `/production-lines` / `PATCH` / `DELETE`

### POST `/workstations` / `PATCH` / `DELETE`

Every location-hierarchy endpoint follows the same shape: list, show, create, update, delete, with delete rejected while dependents exist.

---

## 5.1 Working Calendar APIs

Required by SRS 47. Absent from v1.0.

### GET `/factories/{factory}/calendar`

Returns the operating mode, weekly off-days, and the effective shift set.

### PUT `/factories/{factory}/calendar`

### GET `/factories/{factory}/shifts`

### POST `/factories/{factory}/shifts`

### PATCH `/shifts/{shift}`

Editing creates a new effective-dated version rather than mutating history.

### DELETE `/shifts/{shift}`

End-dates the shift; it is never removed from closed periods.

### GET `/factories/{factory}/holidays`

### POST `/factories/{factory}/holidays`

### DELETE `/holidays/{holiday}`

### GET `/factories/{factory}/working-time`

Query: `from`, `to`. Returns scheduled operating minutes for the window. This is the endpoint every availability figure in the product resolves against, so it must be exposed for verification rather than hidden inside report code.

---

## 5.2 Master Data APIs

v1.0 defined master data tables and referenced them throughout, but exposed no endpoints to manage them. Each resource below supports list, show, create, update, and delete, with delete rejected while the record is referenced.

### Asset master data
```text
/asset-types
/asset-categories
/manufacturers
/asset-models
```

### Maintenance master data
```text
/maintenance-types
/maintenance-templates
/maintenance-templates/{template}/versions
/maintenance-templates/{template}/versions/{version}/checklist-items
/maintenance-templates/{template}/versions/{version}/publish
```

Publishing a template version freezes it. A published version that has been used by any work order is immutable; editing it creates a new version.

### Failure taxonomy
```text
/failure-categories
/failure-codes
/root-causes
/downtime-reason-codes
```

### Inventory structure
```text
/warehouses
/stores
/bins
/spare-part-categories
```

### Common query parameters

All master data lists accept `search`, `active`, `page`, `per_page`, `sort`, and `direction`.

---

## 6. Asset APIs

### GET `/assets`

Query:
```text
search
asset_type_id
category_id
factory_id
department_id
status
criticality
page
per_page
sort
direction
```

### POST `/assets`

Supports `Idempotency-Key`.

### GET `/assets/{asset}`

### PATCH `/assets/{asset}`

Uses optimistic versioning.

### DELETE `/assets/{asset}`

Soft-delete/archive according to policy.

### POST `/assets/{asset}/status`

### POST `/assets/{asset}/transfer`

### GET `/assets/{asset}/transfer-history`

### GET `/assets/{asset}/status-history`

### GET `/assets/{asset}/documents`

### POST `/assets/{asset}/documents`

### GET `/assets/{asset}/maintenance-history`

### GET `/assets/{asset}/qr`

### GET `/assets/{asset}/barcode`

---

## 7. Asset Scan

### GET `/scan/qr/{code}`

Returns authorized asset summary.

### POST `/scan/qr/{code}/breakdown`

Creates a breakdown using the scanned asset.

The server derives the asset's company and factory context.

---

## 8. Maintenance Plan APIs

### GET `/maintenance-plans`

### POST `/maintenance-plans`

Request example:
```json
{
  "asset_id": "01HX...",
  "maintenance_type_id": "01HY...",
  "trigger_type": "COMBINED",
  "schedule_mode": "ROLLING",
  "rules": [
    {
      "type": "TIME",
      "operator": "EVERY",
      "value": 30,
      "unit": "DAY"
    },
    {
      "type": "METER",
      "operator": "AFTER",
      "value": 500,
      "unit": "HOUR"
    }
  ]
}
```

### GET `/maintenance-plans/{plan}`

### PATCH `/maintenance-plans/{plan}`

### POST `/maintenance-plans/{plan}/activate`

### POST `/maintenance-plans/{plan}/deactivate`

---

## 9. Maintenance Schedule APIs

### GET `/maintenance-schedules`

Filters:
- due_from
- due_to
- status
- factory_id
- asset_id

### GET `/maintenance-schedules/{schedule}`

### POST `/maintenance-schedules/{schedule}/reschedule`

### POST `/maintenance-schedules/{schedule}/skip`

### POST `/maintenance-schedules/{schedule}/complete`

Schedule completion must update the recurrence according to the plan's schedule mode.

---

## 10. Meter APIs

### GET `/assets/{asset}/meters`

### POST `/assets/{asset}/meters`

### POST `/meters/{meter}/readings`

### GET `/meters/{meter}/readings`

### POST `/meters/{meter}/reset`

Meter reset requires elevated permission and reason.

---

## 11. Work Order APIs

### GET `/work-orders`

Filters:
- status
- priority
- asset_id
- factory_id
- technician_id
- maintenance_type
- date range

### POST `/work-orders`

### GET `/work-orders/{workOrder}`

### PATCH `/work-orders/{workOrder}`

### POST `/work-orders/{workOrder}/assign`

### POST `/work-orders/{workOrder}/start`

### POST `/work-orders/{workOrder}/pause` — replaced by `/hold`

### POST `/work-orders/{workOrder}/complete`

### POST `/work-orders/{workOrder}/verify`

### POST `/work-orders/{workOrder}/close`

### POST `/work-orders/{workOrder}/cancel`

### GET `/work-orders/{workOrder}/checklist`

### POST `/work-orders/{workOrder}/checklist/results`

### GET `/work-orders/{workOrder}/costs`

### GET `/work-orders/{workOrder}/parts`

Work order completion validates required checklist items.

### POST `/work-orders/{workOrder}/hold`

Body: `reason_code`, `notes`. Starts a hold and stops the repair clock. Replaces the ambiguous `pause` endpoint.

### POST `/work-orders/{workOrder}/resume`

Ends the current hold and accumulates `hold_minutes`.

### POST `/work-orders/{workOrder}/submit-for-approval`

### POST `/work-orders/{workOrder}/reopen`

Requires elevated permission and a reason. Increments `reopened_count` and writes a status history row.

### GET `/work-orders/{workOrder}/labor`

### POST `/work-orders/{workOrder}/labor`

Body: `technician_id`, `started_at`, `ended_at`, `labor_category`. The rate is resolved server-side from the technician grade rate effective on `started_at`; a client-supplied rate is ignored. For `EXTERNAL` labor the body carries `vendor_id` and the vendor charge.

### PATCH `/work-orders/{workOrder}/labor/{entry}`

### DELETE `/work-orders/{workOrder}/labor/{entry}`

Rejected once the work order is `CLOSED`.

### POST `/work-orders/{workOrder}/parts`

Requests a part on the work order.

### PATCH `/work-orders/{workOrder}/parts/{line}`

### POST `/work-orders/{workOrder}/parts/{line}/issue`

Issues from a bin, writing an `ISSUE` ledger transaction in the same database transaction.

### POST `/work-orders/{workOrder}/parts/{line}/consume`

### POST `/work-orders/{workOrder}/parts/{line}/return`

### GET `/work-orders/{workOrder}/history`

Status transitions with actor, timestamp, and reason.

### GET `/work-orders/{workOrder}/attachments`

### POST `/work-orders/{workOrder}/attachments`

### POST `/work-orders/bulk-assign`

Assigns many work orders to a technician or team in one call. Returns `202` with a job resource when the batch exceeds 50 items.

### Work Order Rules Enforced by the API

1. `complete` validates that every required checklist item has a result, and that items with `requires_attachment_on_fail` carry a file when failed.
2. `complete` is rejected while any part line has issued quantity that is neither consumed nor returned.
3. `verify` is rejected when `requires_verification` is false, and is rejected when the verifier is the same user who completed the work.
4. `close` is rejected while `approval_status = PENDING` or verification is outstanding.
5. Every transition validates against the state machine in SRS 13.1 and returns `409 INVALID_STATUS_TRANSITION` otherwise.

---

## 12. Breakdown APIs

### GET `/breakdowns`

### POST `/breakdowns`

Supports idempotency.

### GET `/breakdowns/{breakdown}`

### PATCH `/breakdowns/{breakdown}`

### POST `/breakdowns/{breakdown}/acknowledge`

### POST `/breakdowns/{breakdown}/assign`

### POST `/breakdowns/{breakdown}/start-repair`

### POST `/breakdowns/{breakdown}/complete-repair`

### POST `/breakdowns/{breakdown}/resume-production`

### POST `/breakdowns/{breakdown}/close`

### GET `/breakdowns/{breakdown}/downtime`

### GET `/breakdowns/{breakdown}/root-cause`

---

## 13. Spare Part APIs

### GET `/spare-parts`

### POST `/spare-parts`

### GET `/spare-parts/{part}`

### PATCH `/spare-parts/{part}`

### GET `/spare-parts/{part}/stock`

### GET `/spare-parts/{part}/transactions`

### POST `/spare-parts/{part}/reserve`

### POST `/spare-parts/{part}/release`

### POST `/spare-parts/{part}/issue`

### POST `/spare-parts/{part}/return`

### POST `/spare-parts/{part}/adjust`

All inventory writes create immutable ledger transactions.

---

## 14. Inventory Transfer APIs

### POST `/inventory-transfers`

### POST `/inventory-transfers/{transfer}/approve`

### POST `/inventory-transfers/{transfer}/dispatch`

### POST `/inventory-transfers/{transfer}/receive`

---

## 15. Cost APIs

### GET `/costs`

### POST `/costs`

### GET `/assets/{asset}/lifecycle-cost`

### GET `/reports/maintenance-cost`

Posted costs cannot be edited directly. Corrections use adjustment entries.

---

## 16. Technician APIs

### GET `/technicians`

### POST `/technicians`

### GET `/technicians/{technician}`

### PATCH `/technicians/{technician}`

### GET `/technicians/{technician}/workload`

### GET `/technicians/{technician}/performance`

Requires `technician.performance.view`. Without it, only team-level and factory-level aggregates are returned (SRS 25.2).

### GET `/labor-rate-grades`

### POST `/labor-rate-grades`

### PATCH `/labor-rate-grades/{grade}`

A rate change creates a new effective period rather than editing the current one, so recorded work keeps its original cost.

### DELETE `/labor-rate-grades/{grade}`

End-dates the grade. Rejected while any technician is assigned to it.

The system exposes no endpoint that accepts or returns an individual salary, wage, or payroll identifier. Labor cost is always grade-derived (SRS 25.1).

---

## 17. Vendor APIs

### GET `/vendors`

### POST `/vendors`

### GET `/vendors/{vendor}`

### PATCH `/vendors/{vendor}`

---

## 18. Warranty and Contract APIs

### GET `/warranties`

### POST `/warranties`

### GET `/service-contracts`

### POST `/service-contracts`

### POST `/service-contracts/{contract}/renew`

---

## 19. Notification APIs

### GET `/notifications`

### POST `/notifications/{notification}/read`

### POST `/notifications/read-all`

### GET `/notification-preferences`

### PATCH `/notification-preferences`

---

## 19.1 File APIs

v1.0 required private storage and signed URLs but exposed no upload or download endpoint.

### POST `/files`

Multipart upload. Returns a file id.

Validation:
1. Allowed MIME types are an explicit allowlist, checked by content sniffing, not by the client-supplied `Content-Type` or the file extension.
2. Maximum size is 25 MB per file by default, configurable per company.
3. Uploads are virus-scanned before the file is marked usable; an unscanned file returns `409` on download.
4. The stored path is server-generated. A client-supplied filename is never used as a path segment.

### POST `/files/presign`

Returns a short-lived direct-to-storage upload URL for large files, plus the id to reference afterwards.

### GET `/files/{file}`

Returns metadata, never the bytes.

### GET `/files/{file}/download`

Returns `302` to a signed URL valid for 5 minutes. The signed URL is bound to the file and does not grant listing or directory access.

### DELETE `/files/{file}`

Rejected while the file is referenced by an asset document, attachment, or invoice.

### GET `/files/{file}/versions`

### POST `/files/{file}/versions`

Document versioning per SRS 37. The previous version stays retrievable so a historical work order still resolves the manual revision that was current when it ran.

---

## 19.2 Settings APIs

### GET `/settings`

Returns effective settings for the active scope, each with the level that defined it.

### GET `/settings/definitions`

The catalog of settable keys with types and defaults.

### PUT `/settings/{key}`

Body carries `value` and an optional `factory_id` or `production_line_id` to set an override.

### DELETE `/settings/{key}`

Removes an override so the value falls back to the next level up.

---

## 19.3 Health and Operations

### GET `/health`

Unauthenticated liveness probe. Returns `200` with no tenant data.

### GET `/health/ready`

Readiness probe checking database, Redis, queue, and storage connectivity. Used by the load balancer and deployment pipeline.

### GET `/version`

Returns the API version and build identifier, so a bug report can name the deployed build.

---

## 20. Real-Time WebSocket Events

### Private Channels

```text
private-company.{companyId}
private-factory.{factoryId}
private-user.{userId}
```

### Events

```text
AssetStatusChanged
BreakdownCreated
BreakdownAssigned
WorkOrderCreated
WorkOrderAssigned
WorkOrderUpdated
WorkOrderCompleted
MaintenanceDue
MaintenanceOverdue
SparePartLowStock
WarrantyExpiring
ContractExpiring
NotificationCreated
```

### Security

Every channel subscription is authorized server-side.

A user must:
1. Be authenticated.
2. Belong to the company.
3. Have access to the factory if factory-scoped.
4. Be the target user for user-specific channels.

---

## 21. Dashboard APIs

### GET `/dashboard/management`

### GET `/dashboard/maintenance`

### GET `/dashboard/store`

### GET `/dashboard/kpis`

Dashboard results may be cached.

---

## 22. Report APIs

### GET `/reports/assets`

### GET `/reports/maintenance`

### GET `/reports/breakdowns`

### GET `/reports/downtime`

### GET `/reports/costs`

### GET `/reports/inventory`

### GET `/reports/technicians`

### GET `/reports/vendors`

### GET `/reports/mtbf-mttr`

Large reports:

### POST `/report-jobs`

### GET `/report-jobs/{job}`

### GET `/report-jobs/{job}/download`

---

## 23. Import APIs

### POST `/imports/assets`

### POST `/imports/spare-parts`

### POST `/imports/vendors`

### POST `/imports/maintenance-history`

### GET `/imports/{job}`

### GET `/imports/{job}/errors`

Import flow:
Upload → Validate → Preview → Confirm.

---

## 24. Export APIs

### POST `/exports`

### GET `/exports/{job}`

### GET `/exports/{job}/download`

Exports must enforce permissions and tenant scope.

---

## 25. Subscription APIs

### GET `/subscription`

### POST `/subscription`

### PATCH `/subscription`

### POST `/subscription/cancel`

### POST `/subscription/renew`

### GET `/subscription/invoices`

### GET `/subscription/payments`

### POST `/subscription/payments`

### POST `/subscription/refunds`

Subscription financial actions require billing permissions.

---

## 26. Audit APIs

### GET `/audit-logs`

Filters:
- user
- action
- entity
- date range

Audit logs are read-only.

---

## 27. Approval APIs

### GET `/approval-requests`

### POST `/approval-requests/{request}/approve`

### POST `/approval-requests/{request}/reject`

### GET `/approval-requests/{request}`

---

## 28. Webhook APIs

### GET `/webhooks`

### POST `/webhooks`

### PATCH `/webhooks/{webhook}`

### DELETE `/webhooks/{webhook}`

Events are delivered asynchronously.

---

## 29. Pagination

Default:
`per_page=25`

Maximum:
`per_page=100`

Response:
```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 240
  }
}
```

### Cursor Pagination

Endpoints over append-only, high-volume tables (`/audit-logs`, `/meters/{meter}/readings`, `/spare-parts/{part}/transactions`, `/notifications`, `/webhooks/{webhook}/deliveries`) support cursor pagination and should be preferred there. Offset pagination degrades badly past a few thousand pages and can skip or repeat rows when new data arrives mid-scan.

Request:
```text
/audit-logs?cursor=eyJpZCI6IjAxSFgifQ&per_page=100
```

Response:
```json
{
  "data": [],
  "meta": {
    "per_page": 100,
    "next_cursor": "eyJpZCI6IjAxSFkifQ",
    "has_more": true
  }
}
```

Cursor responses omit `total`. Counting a large table on every page is the exact cost cursor pagination exists to avoid; a client that needs a count calls the dedicated count or report endpoint.

---

## 30. Filtering

Use query parameters.

Example:
```text
/assets?factory_id=01HX&status=BREAKDOWN&criticality=CRITICAL
```

Filtering must never bypass tenant scope.

---

## 31. Sorting

Example:
```text
/assets?sort=created_at&direction=desc
```

Only allowlisted sortable fields are accepted.

---

## 32. Idempotency

Critical create endpoints:
- POST `/assets`
- POST `/breakdowns`
- POST `/work-orders`
- POST `/inventory-transfers`
- POST `/subscription/payments`

Require or support `Idempotency-Key`.

Duplicate requests with the same key return the original result.

### Semantics

1. Keys are scoped to `(company, endpoint, key)`. A key from one tenant can never collide with another's.
2. The server stores a hash of the request body. A replay with the same key and the same body returns the original status code and body, with header `Idempotent-Replay: true`.
3. A replay with the same key and a **different** body returns `409 IDEMPOTENCY_CONFLICT`. It does not silently execute.
4. A replay arriving while the first request is still in flight returns `409 IDEMPOTENCY_CONFLICT` rather than executing twice.
5. Keys expire 24 hours after creation. A replay after expiry is a new request.
6. A key is required, not merely accepted, on `POST /subscription/payments`. On the other endpoints in the list it is accepted and strongly recommended.
7. Idempotency covers the whole operation, including its side effects. An idempotent breakdown creation must not send the notification twice.

### Additional Idempotent Endpoints

```text
POST /work-orders/{workOrder}/parts/{line}/issue
POST /spare-parts/{part}/issue
POST /spare-parts/{part}/adjust
POST /meters/{meter}/readings
POST /costs
POST /imports/*
```

Every endpoint that moves stock or money is idempotent. A retry on a flaky factory-floor connection must never double-issue a part.

---

## 33. Optimistic Locking

Mutable high-conflict resources expose `version`.

Client sends:
```json
{
  "version": 3
}
```

If the stored version differs, return `409 CONFLICT`.

---

## 34. Authorization Rules

Every request passes:

Authentication
→ Company membership
→ Factory scope
→ Role permission
→ Resource policy
→ Business rule

Do not rely only on route model binding.

---

## 35. API Security

- Sanctum
- HTTPS
- Rate limits
- Validation
- Authorization policies
- Tenant scope
- Signed file URLs
- Audit logs
- Secret rotation
- Webhook signing

### 35.1 Rate Limits

v1.0 required rate limiting without specifying any limit, which is not implementable. Defaults, per authenticated user unless stated:

| Scope | Limit | Window |
|---|---|---|
| `POST /auth/login` | 5 per account, 20 per IP | 1 minute |
| `POST /auth/forgot-password` | 3 per account, 10 per IP | 15 minutes |
| `POST /auth/mfa/verify` | 5 per challenge | 5 minutes |
| General authenticated read | 300 | 1 minute |
| General authenticated write | 120 | 1 minute |
| Report and export creation | 10 | 1 minute |
| Import creation | 5 | 1 hour |
| File upload | 60 | 1 minute |
| Meter reading ingestion (API client) | 1,000 | 1 minute |
| Scan endpoints | 60 | 1 minute |
| Per company, all endpoints | 3,000 | 1 minute |

Every response carries `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset`. A `429` carries `Retry-After` in seconds.

Limits are configurable per contract. Exceeding the per-company limit throttles that tenant only; one tenant can never exhaust another tenant's budget.

### 35.2 Webhook Signing

Each delivery carries:

```http
X-Webhook-Id: {event_id}
X-Webhook-Timestamp: {unix_seconds}
X-Webhook-Signature: v1={hex_hmac_sha256}
```

The signature is `HMAC_SHA256(secret, "{timestamp}.{raw_body}")`, hex-encoded.

Receiver requirements:

1. Reject a delivery whose timestamp is more than 5 minutes old, to prevent replay.
2. Compare signatures in constant time.
3. Deduplicate on `X-Webhook-Id`; at-least-once delivery means a duplicate is possible and is not an error.
4. Respond `2xx` within 10 seconds. Anything else is a failure and is retried.

During secret rotation both the current and previous secret are accepted for 24 hours, so a receiver can roll over without dropping events.

### 35.3 Additional Security Requirements

1. CORS allows only the configured frontend origins. Wildcard origins are prohibited.
2. Security headers on all responses: `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and a restrictive `Content-Security-Policy` on any server-rendered page.
3. Request body size is capped; oversized bodies return `413` before parsing.
4. Query complexity is bounded: `per_page` is capped at 100, filter arrays are capped, and nested includes are limited to one level.
5. Sort and filter fields are allowlisted per endpoint. Arbitrary column names are never interpolated into SQL.
6. Signed file URLs expire in 5 minutes and are single-purpose.
7. Every write endpoint is covered by an authorization policy test. An endpoint with no policy test does not ship.

---

## 36. Standard API Error Codes

- `UNAUTHENTICATED`
- `FORBIDDEN`
- `TENANT_ACCESS_DENIED`
- `VALIDATION_ERROR`
- `RESOURCE_NOT_FOUND`
- `CONFLICT`
- `INSUFFICIENT_STOCK`
- `INVALID_STATUS_TRANSITION`
- `APPROVAL_REQUIRED`
- `IDEMPOTENCY_CONFLICT`
- `RATE_LIMITED`
- `TENANT_CONTEXT_REQUIRED`
- `SUBSCRIPTION_READ_ONLY`
- `SUBSCRIPTION_EXPIRED`
- `PLAN_LIMIT_EXCEEDED`
- `MFA_REQUIRED`
- `ACCOUNT_LOCKED`
- `PASSWORD_POLICY_VIOLATION`
- `RESERVATION_EXPIRED`
- `NEGATIVE_STOCK_NOT_ALLOWED`
- `METER_VALUE_DECREASED`
- `CHECKLIST_INCOMPLETE`
- `PARTS_NOT_RECONCILED`
- `VERIFICATION_REQUIRED`
- `SELF_APPROVAL_NOT_ALLOWED`
- `IMMUTABLE_RECORD`
- `DEPENDENT_RECORDS_EXIST`
- `FILE_TOO_LARGE`
- `UNSUPPORTED_FILE_TYPE`
- `FILE_SCAN_PENDING`
- `CALENDAR_NOT_CONFIGURED`
- `SETTING_KEY_UNKNOWN`
- `SEQUENCE_EXHAUSTED`
- `DEPENDENCY_UNAVAILABLE`
- `PAYLOAD_TOO_LARGE`

Every code maps to exactly one HTTP status. The mapping is part of the API contract and must not change within a major version.

---

## 37. Business Rule Examples

### Maintenance Completion
Cannot complete if required checklist items are missing.

### Work Order Close
Cannot close if required verification is pending.

### Inventory Issue
Cannot issue more than available stock.

### Breakdown Close
Requires repair completion and required root cause fields.

### Critical Asset
Requires verification before closure.

### Subscription Expiry
Tenant transitions to read-only after grace period.

---

## 38. API Versioning

Current:
`/api/v1`

Breaking changes require `/api/v2`.

Non-breaking additions remain within the current version.

Deprecation policy should provide a documented migration window.

### Deprecation Policy

1. A deprecated endpoint or field returns the `Deprecation` header with the deprecation date and a `Sunset` header with the removal date.
2. The minimum window between deprecation and removal is 6 months.
3. Deprecations are announced through the changelog and through a `warnings` array in the response `meta`.
4. Adding an optional request field, adding a response field, adding an endpoint, adding an enum value to a field the client only reads, or relaxing a validation rule are all non-breaking and stay within the current version.
5. Removing or renaming a field, changing a type, adding a required request field, tightening validation, changing an error code mapping, or changing default behavior are breaking and require `/api/v2`.
6. Both `/api/v1` and `/api/v2` run concurrently for the full deprecation window.

### API Documentation

The specification is published as OpenAPI 3.1, generated from the route and request classes so it cannot drift from the implementation. A Postman collection and typed clients are generated from the same document. The web UI is server-rendered Blade (ADR-066); the API exists for ERP, HRM, production, accounting, and IoT integration and for any future mobile client.

---

## 39. Bulk Operations

Factory administrators routinely act on hundreds of assets at once.

```text
POST /assets/bulk-update
POST /assets/bulk-transfer
POST /work-orders/bulk-assign
POST /work-orders/bulk-close
POST /maintenance-plans/bulk-activate
POST /notifications/bulk-read
```

Rules:

1. A batch of 50 items or fewer executes synchronously and returns a per-item result array.
2. A larger batch returns `202` with a job resource to poll.
3. Batches are transactional per item, not per batch: one invalid asset must not roll back 499 valid ones. The response reports each item's outcome.
4. Every bulk endpoint enforces the same policy checks per item as its single-resource equivalent. Bulk is never an authorization shortcut.
5. Bulk operations are audited per item, with a shared `request_id` linking them.

---

## 40. Real-Time Event Contract

Broadcast payloads are deliberately thin.

```json
{
  "event": "WorkOrderAssigned",
  "event_id": "01HX7Q...",
  "occurred_at": "2026-08-18T09:14:02.451Z",
  "company_id": "01HW...",
  "factory_id": "01HW...",
  "resource": {
    "type": "work_order",
    "id": "01HX..."
  },
  "summary": {
    "work_order_number": "WO-DHK-202608-00417",
    "status": "ASSIGNED",
    "priority": "HIGH"
  }
}
```

Rules:

1. A payload carries identifiers and a small summary sufficient to update a list row or raise a toast. It never carries the full resource, and never carries cost or personal data.
2. The client refetches from REST for anything beyond the summary. REST remains the source of truth (ADR-008).
3. Events are best-effort. A client that reconnects after a gap refetches rather than assuming it saw every event.
4. `event_id` allows clients to discard duplicates.
5. A user subscribed to both a company channel and a factory channel may receive the same event twice; deduplication by `event_id` is the client's responsibility.
6. Channel authorization is re-evaluated on every subscription, not cached from login. A revoked role takes effect on the next subscribe.


---

## 41. KPI and Analytics APIs

Dashboards and reports must resolve KPIs through one shared endpoint family, so a number never differs between two screens (SRS 31.2).

### GET `/kpis`

Query:
```text
scope_type   COMPANY | FACTORY | LINE | ASSET
scope_id
period_type  DAY | WEEK | MONTH | CUSTOM
from
to
metrics      mtbf,mttr,availability,pm_compliance,downtime_minutes
```

Response includes, for each metric, the value, the unit, the calculation version, and the scheduled operating minutes it was computed against. A metric with a zero denominator returns `null` with a `reason`, never `0`.

### GET `/assets/{asset}/kpis`

### GET `/assets/{asset}/downtime`

### GET `/factories/{factory}/kpis`

### Dashboard Parameters

`/dashboard/management`, `/dashboard/maintenance`, and `/dashboard/store` accept `factory_id`, `from`, and `to`. Results are cached for 60 seconds per scope, and the response carries `meta.computed_at` so a user can see how fresh the figure is. A cached dashboard must never cross tenant or factory scope; the cache key includes both.

---

## 42. Endpoint Coverage

Every table in the data model must be reachable through the API, and every endpoint must map to a table. Endpoint groups added in v1.1 to close that gap:

| Area | Endpoints | Closes |
|---|---|---|
| Users, roles, permissions, teams | Section 4.1 | RBAC model with no administration surface |
| API clients | Section 4.2 | SRS 43 integration credentials |
| Location hierarchy CRUD | Section 5 | Tables with no write path |
| Working calendar and shifts | Section 5.1 | SRS 47 |
| Master data | Section 5.2 | Asset, maintenance, failure, and inventory master tables |
| Work order labor, parts, holds | Section 11 | SRS 13.2, 13.3 |
| Files and document versions | Section 19.1 | SRS 37 |
| Settings | Section 19.2 | SRS 53 |
| Health and version | Section 19.3 | ADR-041 observability |
| MFA, sessions, company switching | Section 3 | SRS 50 |
| Bulk operations | Section 39 | Operational reality at 20,000 assets |
| KPIs | Section 41 | SRS 31 |

Endpoints deliberately **not** provided:

- No endpoint updates `work_orders.actual_cost`, `inventory_balances`, or any derived total directly. Those are computed server-side from their source records.
- No endpoint deletes an audit log, a posted inventory transaction, or a posted cost entry.
- No endpoint accepts `company_id` in a request body.
