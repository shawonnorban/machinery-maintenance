# 02-Database-ERD.md
# Database Design and ERD
## Garment Industry Machinery Asset & Maintenance Management SaaS

**Version:** 1.1  
**Database:** MySQL 8+  
**Primary Key:** ULID recommended  
**Timestamps:** UTC  
**Tenant Key:** `company_id` on tenant-owned tables  

---

## 1. Core Database Rules

1. All tenant-owned tables contain `company_id`.
2. Foreign keys must be tenant-safe. Application validation must ensure related records belong to the same company.
3. Use ULIDs for externally exposed identifiers.
4. Internal numeric surrogate keys may be used only where justified, but public API identifiers should be non-sequential.
5. Use `created_at`, `updated_at`; use `deleted_at` only for entities where soft delete is appropriate.
6. Financial and audit records are immutable or append-only.
7. Inventory balances are derived from a transaction ledger; do not rely solely on mutable stock fields.
8. Money uses decimal values, never floating point.
9. Store transaction currency and base-currency conversion.
10. Store timestamps in UTC; calculate schedules in factory timezone.
11. Critical status transitions must be auditable.
12. All tables use `utf8mb4` with `utf8mb4_unicode_ci` collation and the InnoDB engine. Bengali and English text must sort, search, and truncate correctly.
13. Money columns are `DECIMAL(18,4)`. Quantity columns are `DECIMAL(18,4)`. Exchange rates are `DECIMAL(18,8)`. Percentages are `DECIMAL(9,4)`.
14. Enumerated values are stored as `VARCHAR` with an application-level enum plus a `CHECK` constraint, not as MySQL `ENUM`. Adding a value must not require an `ALTER TABLE` on a large table.
15. Timestamps are `DATETIME(3)` in UTC. Date-only columns are `DATE`. No column stores a local time without an accompanying timezone identifier.
16. Foreign keys are declared with explicit referential actions (Section 27). The default is `ON DELETE RESTRICT ON UPDATE CASCADE`.
17. Every tenant-scoped foreign key must be covered by a composite index beginning with `company_id`.
18. Polymorphic references store `{name}_type` and `{name}_id` and are always paired with `company_id` in the index; they cannot carry a database-level foreign key, so tenant and type validation is mandatory in the application layer.
19. Append-only tables (`inventory_transactions`, `cost_entries`, `audit_logs`, `meter_readings`, all `*_histories`) have no `updated_at` and no `deleted_at`. Corrections are new rows that reference the original.
20. Any table expected to exceed the Section 51 volumes in the SRS declares an archival or partitioning strategy (Section 28).

---

## 1.1 Location Model Decision

The v1.0 draft described two competing location models: a polymorphic `assets.current_location_type` / `current_location_id` pair, and an `asset_locations` table holding a denormalized hierarchy. This was ambiguous and is now resolved.

**Decision:** `asset_locations` is the single addressable location entity. An asset points at exactly one `asset_location_id`.

Rationale:

1. A location has a stable identity that transfer history can reference for the life of the record; a polymorphic pointer to a workstation that is later deleted leaves history dangling.
2. Locations need their own code, QR label, and reporting rollup.
3. A single foreign key is enforceable at the database level; a polymorphic pair is not.

`asset_locations` therefore carries the resolved hierarchy (factory, building, floor, department, section, production line, workstation) with all levels above factory nullable, so a factory that does not model floors is not forced to invent them.

The polymorphic `current_location_type` / `current_location_id` columns are removed from `assets`, and the equivalent columns are removed from `asset_transfer_history`.

---

## 2. Organization Tables

### organizations
- id
- name
- code
- status
- created_at
- updated_at

### companies
- id
- organization_id nullable
- name
- code
- legal_name
- base_currency
- timezone
- default_locale
- status
- created_at
- updated_at
- deleted_at

### business_units
- id
- company_id
- name
- code
- status

### factories
- id
- company_id
- business_unit_id nullable
- name
- code
- address
- timezone
- status

### buildings
- id
- company_id
- factory_id
- name
- code

### floors
- id
- company_id
- building_id
- name
- code

### departments
- id
- company_id
- factory_id
- name
- code

### sections
- id
- company_id
- department_id
- name
- code

### production_lines
- id
- company_id
- department_id
- section_id nullable
- name
- code

### workstations
- id
- company_id
- production_line_id
- name
- code

---

## 3. Users and Authorization

### users
- id
- name
- email
- phone
- password_hash
- status
- last_login_at
- timezone
- locale
- created_at
- updated_at

### company_users
- id
- company_id
- user_id
- status

### roles
- id
- company_id nullable (null = platform role)
- name
- code

### permissions
- id
- name
- code

### role_permissions
- role_id
- permission_id

### user_roles
- id
- company_id
- user_id
- role_id
- factory_id nullable

Roles may be company-wide or factory-scoped.

### user_mfa_methods
- id
- user_id
- type (`TOTP`)
- secret_encrypted
- confirmed_at nullable
- last_used_at nullable
- created_at

### user_recovery_codes
- id
- user_id
- code_hash
- used_at nullable
- created_at

### user_sessions
- id
- user_id
- personal_access_token_id
- device_label nullable
- ip_address
- user_agent
- last_active_at
- expires_at
- revoked_at nullable
- created_at

Supports the "list and revoke my devices" requirement in SRS 50.2.

### login_attempts
- id
- email
- user_id nullable
- ip_address
- successful
- failure_reason nullable
- attempted_at

Retained 90 days. Feeds lockout and security reporting.

### api_clients
- id
- company_id
- name
- client_id
- secret_hash
- scopes_json
- status
- last_used_at
- expires_at nullable
- created_by
- created_at
- revoked_at nullable

Machine-to-machine integration credentials. Scoped to a company and to an explicit permission subset. Never issued to browser sessions.

### support_access_grants
- id
- company_id
- platform_user_id
- reason
- granted_by (tenant user)
- starts_at
- ends_at
- revoked_at nullable
- created_at

Implements SRS 5.4. No platform user may read tenant data without an active grant.

### teams
- id
- company_id
- factory_id nullable
- name
- code
- team_lead_technician_id nullable
- specialization nullable
- status
- created_at
- updated_at

Referenced by maintenance plans, work orders, and escalation rules. The v1.0 draft referred to an "assigned team" with no table behind it.

### team_members
- id
- company_id
- team_id
- technician_id
- role_in_team nullable
- joined_at
- left_at nullable

Unique on `(team_id, technician_id)` where `left_at is null`.

---

## 4. Asset Tables

### asset_types
- id
- company_id nullable
- name
- code

### asset_categories
- id
- company_id
- asset_type_id
- name
- code

### manufacturers
- id
- company_id nullable
- name
- code

### asset_models
- id
- company_id
- manufacturer_id
- asset_type_id
- model
- code

### assets
- id
- company_id
- asset_type_id
- asset_category_id
- manufacturer_id nullable
- asset_model_id nullable
- parent_asset_id nullable
- asset_code
- serial_number nullable
- qr_code
- barcode
- name
- description
- criticality
- status
- purchase_date
- installation_date
- commissioning_date
- acquisition_cost
- installation_cost
- current_value nullable
- currency
- warranty_start nullable
- warranty_end nullable
- supplier_id nullable
- current_factory_id
- asset_location_id
- country_of_origin nullable
- version
- capitalized_cost nullable
- salvage_value nullable
- useful_life_months nullable
- depreciation_method nullable
- expected_life_cycles nullable
- default_meter_type_id nullable
- is_imported (legacy migration flag)
- imported_batch_id nullable
- retired_at nullable
- scrapped_at nullable
- disposal_value nullable
- disposal_reference nullable
- notes nullable
- created_by
- updated_by nullable
- created_at
- updated_at
- deleted_at

Unique constraints:
- `(company_id, asset_code)`
- `(company_id, serial_number)` where applicable
- `(company_id, qr_code)`
- `(company_id, barcode)`

### asset_locations
- id
- company_id
- factory_id
- building_id nullable
- floor_id nullable
- department_id nullable
- section_id nullable
- production_line_id nullable
- workstation_id nullable
- name
- code
- qr_code nullable
- full_path (denormalized display path, rebuilt on hierarchy change)
- status
- created_at
- updated_at

Unique: `(company_id, code)`.

### asset_transfer_history
- id
- company_id
- asset_id
- transfer_number
- from_factory_id
- from_location_id nullable
- to_factory_id
- to_location_id
- status
- reason
- notes
- requested_by
- requested_at
- approved_by nullable
- approved_at nullable
- received_by nullable
- received_at nullable
- rejected_by nullable
- rejected_at nullable
- rejection_reason nullable
- transfer_at
- reverses_transfer_id nullable
- created_at

Status: `REQUESTED`, `APPROVED`, `REJECTED`, `IN_TRANSIT`, `RECEIVED`, `CANCELLED`, `REVERSED`.

Rules:
1. `to_factory_id` must belong to the same company. Cross-tenant transfer is impossible by construction.
2. Rows are immutable once status reaches `RECEIVED`. A correction is a new row with `reverses_transfer_id` set.
3. `assets.asset_location_id` and `assets.current_factory_id` are updated only when a transfer reaches `RECEIVED`.

### asset_status_histories
- id
- company_id
- asset_id
- from_status
- to_status
- changed_by
- changed_at
- reason

### asset_documents
- id
- company_id
- asset_id
- document_type
- file_id
- version
- is_current
- uploaded_by

---

## 5. File Tables

### files
- id
- company_id
- storage_disk
- storage_path
- original_name
- mime_type
- size_bytes
- checksum
- uploaded_by
- created_at

### file_versions
- id
- company_id
- file_id
- version
- storage_path
- checksum
- uploaded_by

---

## 6. Maintenance Master Data

### maintenance_types
- id
- company_id
- name
- code

### maintenance_templates
- id
- company_id
- asset_type_id nullable
- name
- code
- status

### maintenance_template_versions
- id
- company_id
- template_id
- version_number
- effective_from
- effective_to nullable
- status

### checklist_items
- id
- company_id
- template_version_id
- sequence
- label
- input_type
- expected_value nullable
- tolerance_min nullable
- tolerance_max nullable
- required
- unit nullable
- options_json nullable (for CHOICE inputs)
- help_text nullable
- allows_attachment
- requires_attachment_on_fail
- requires_note_on_fail
- fail_creates_followup_work_order
- created_at

`input_type`: `PASS_FAIL`, `NUMERIC`, `TEXT`, `CHOICE`, `PHOTO`, `SIGNATURE`.

Checklist items belong to a template *version* and are immutable once that version has been used by any work order.

---

## 7. Maintenance Plans

### maintenance_plans
- id
- company_id
- asset_id nullable
- asset_type_id nullable
- maintenance_type_id
- template_version_id
- name
- trigger_type
- schedule_mode
- interval_value nullable
- interval_unit nullable
- meter_type_id nullable
- meter_threshold nullable
- usage_threshold nullable
- grace_period_minutes
- priority
- active
- timezone
- rule_logic (`OR` | `AND`, explicit; no implicit default)
- assigned_team_id nullable
- default_technician_id nullable
- escalation_rule_id nullable
- estimated_duration_minutes nullable
- estimated_labor_cost nullable
- lead_time_days (how far ahead a schedule row is generated)
- non_working_day_policy (`NONE` | `NEXT_WORKING_DAY` | `PREVIOUS_WORKING_DAY`)
- requires_shutdown (boolean; drives planned downtime)
- last_generated_at nullable
- last_completed_at nullable
- next_due_at nullable (denormalized for dashboard queries)
- start_date
- end_date nullable
- created_by
- created_at
- updated_at

A plan may target a specific asset or an asset model/type template.

### maintenance_plan_rules
- id
- company_id
- maintenance_plan_id
- rule_type
- operator
- value
- unit

Supports combined rules such as:
30 days OR 500 running hours.

### maintenance_schedules
- id
- company_id
- maintenance_plan_id
- asset_id
- due_at
- due_meter nullable
- status
- generated_from_schedule_id nullable
- completed_at nullable
- timezone
- generated_at
- work_order_id nullable
- skipped_reason nullable
- skipped_by nullable
- rescheduled_from_due_at nullable
- rescheduled_reason nullable
- grace_until nullable
- is_overdue (derived, maintained by the scheduler)
- created_at
- updated_at

Status: `PLANNED`, `DUE`, `OVERDUE`, `IN_PROGRESS`, `COMPLETED`, `SKIPPED`, `CANCELLED`.

Unique: `(maintenance_plan_id, asset_id, due_at)` prevents the scheduler from generating the same occurrence twice when it runs concurrently or is retried.

Scheduler rules:

1. Schedules are generated forward only to `maintenance_plans.lead_time_days`, bounded by the company setting `maintenance.schedule_generation_horizon_days`.
2. Generation is idempotent. Re-running the generator for the same period must not create duplicates.
3. Rolling mode computes the next `due_at` from `completed_at`. Fixed mode computes it from the plan calendar anchor, independent of when the previous occurrence was completed.
4. For a `COMBINED` plan with `rule_logic = OR`, `due_at` and `due_meter` are both populated and whichever is reached first triggers the occurrence. With `AND`, both must be satisfied.
5. Meter-triggered occurrences are evaluated when a reading is posted, not only on the scheduler tick, so a machine that reaches 500 hours at 02:00 does not wait for the next daily run.

---

## 8. Metering

### meter_types
- id
- company_id
- name
- code
- unit

### asset_meters
- id
- company_id
- asset_id
- meter_type_id
- current_value
- status

### meter_readings
- id
- company_id
- asset_id
- meter_id
- value
- reading_at
- source
- recorded_by
- previous_value nullable
- delta nullable
- is_reset_baseline
- notes nullable
- source_reference nullable (import batch, API client, or device id)
- created_at

`source`: `MANUAL`, `IMPORT`, `API`, `IOT`.

Unique: `(company_id, meter_id, reading_at, source)` rejects a duplicate submission of the same reading on retry.

Rules:
1. A reading below `asset_meters.current_value` is rejected unless it follows a `meter_reset_events` row.
2. `meter_readings` is append-only. A wrong reading is corrected by a compensating reading flagged in `notes`, not by an update.
3. Posting a reading updates `asset_meters.current_value` in the same transaction and evaluates meter-triggered maintenance plans.

### meter_reset_events
- id
- company_id
- meter_id
- old_value
- new_value
- reason
- reset_at
- reset_by

---

## 9. Work Orders

### work_orders
- id
- company_id
- factory_id
- asset_id
- maintenance_schedule_id nullable
- breakdown_id nullable
- maintenance_type_id
- work_order_number
- priority
- status
- scheduled_start
- scheduled_end
- actual_start
- actual_end
- estimated_labor_cost
- estimated_parts_cost
- actual_cost
- currency
- created_by
- completed_by nullable
- verified_by nullable
- verified_at nullable
- version
- assigned_team_id nullable
- asset_location_id nullable
- title
- description
- approval_status (`NOT_REQUIRED` | `PENDING` | `APPROVED` | `REJECTED`)
- approval_request_id nullable
- requires_verification (resolved at creation from criticality and settings)
- requires_shutdown
- downtime_class (`PLANNED` | `UNPLANNED` | `NONE`)
- hold_minutes (accumulated time spent in On Hold)
- hold_reason nullable
- on_hold_since nullable
- actual_labor_cost
- actual_parts_cost
- actual_other_cost
- base_currency_actual_cost
- exchange_rate
- closed_by nullable
- closed_at nullable
- cancelled_by nullable
- cancelled_at nullable
- cancellation_reason nullable
- reopened_count
- source (`PLAN` | `BREAKDOWN` | `MANUAL` | `CHECKLIST_FAILURE` | `IMPORT`)
- is_imported
- created_at
- updated_at

Status: `DRAFT`, `PENDING_APPROVAL`, `SCHEDULED`, `ASSIGNED`, `IN_PROGRESS`, `ON_HOLD`, `COMPLETED`, `VERIFIED`, `CLOSED`, `CANCELLED`, `REJECTED`.

Unique: `(company_id, work_order_number)`.

`actual_cost` is derived: `actual_labor_cost + actual_parts_cost + actual_other_cost`. It is recalculated on labor, part, and cost entry changes and is never accepted directly from the client.

### work_order_labor_entries
- id
- company_id
- work_order_id
- technician_id
- labor_category (`REGULAR` | `OVERTIME` | `EXTERNAL`)
- started_at
- ended_at
- minutes (derived)
- labor_grade_id nullable (null for EXTERNAL)
- hourly_rate (resolved from the grade, or the vendor rate for EXTERNAL)
- currency
- exchange_rate
- amount
- base_amount
- vendor_id nullable (for EXTERNAL labor)
- notes nullable
- recorded_by
- created_at

Rules:
1. Entries for the same technician may not overlap in time.
2. `hourly_rate` is resolved from the technician grade rate effective on `started_at` and copied onto the entry, so historical cost stays reproducible when grade rates change. It is never a per-person wage (Section 16.1).
3. Labor entries are append-only once the work order is `CLOSED`.

This table was absent in v1.0, which left `actual_cost` and every technician KPI without a source.

### work_order_parts
- id
- company_id
- work_order_id
- spare_part_id
- substitute_for_spare_part_id nullable
- bin_id nullable
- quantity_requested
- quantity_reserved
- quantity_issued
- quantity_consumed
- quantity_returned
- unit_cost (captured at issue time)
- currency
- total_cost
- base_total_cost
- reservation_id nullable
- status (`REQUESTED` | `RESERVED` | `ISSUED` | `PARTIALLY_CONSUMED` | `CONSUMED` | `RETURNED` | `CANCELLED`)
- created_at
- updated_at

Rules:
1. `quantity_consumed + quantity_returned` may not exceed `quantity_issued`.
2. A work order cannot reach `CLOSED` while any line has `quantity_issued > quantity_consumed + quantity_returned`.
3. Every quantity movement writes a matching `inventory_transactions` row in the same database transaction.

The API in v1.0 exposed `GET /work-orders/{id}/parts` with no table behind it.

### work_order_attachments
- id
- company_id
- work_order_id
- checklist_result_id nullable
- file_id
- attachment_type (`BEFORE` | `AFTER` | `EVIDENCE` | `DOCUMENT` | `SIGNATURE`)
- caption nullable
- uploaded_by
- created_at

### work_order_holds
- id
- company_id
- work_order_id
- reason_code (`AWAITING_PARTS` | `AWAITING_APPROVAL` | `AWAITING_VENDOR` | `PRODUCTION_RUNNING` | `SHIFT_END` | `OTHER`)
- notes nullable
- started_at
- ended_at nullable
- minutes nullable
- created_by

Hold time is excluded from MTTR (SRS 31.1) and is a primary input for identifying spare-part shortages that inflate downtime.

### work_order_assignments
- id
- company_id
- work_order_id
- technician_id
- assigned_by
- assigned_at
- unassigned_at nullable

### work_order_checklists
- id
- company_id
- work_order_id
- template_version_id

### work_order_checklist_results
- id
- company_id
- work_order_id
- checklist_item_id
- result
- numeric_value nullable
- text_value nullable
- observation nullable
- completed_by
- completed_at
- file_id nullable (evidence photo)
- is_within_tolerance nullable (derived for NUMERIC items)
- followup_work_order_id nullable
- created_at

Rules:
1. A `FAIL` result on an item with `requires_note_on_fail` must carry `observation`.
2. A `FAIL` result on an item with `fail_creates_followup_work_order` creates a corrective work order linked through `followup_work_order_id`.
3. A `NUMERIC` result outside `tolerance_min`/`tolerance_max` is treated as a fail.
4. Results reference `checklist_item_id` on the immutable template version captured in `work_order_checklists`, so historical work orders always reproduce the exact checklist executed.

### work_order_status_histories
- id
- company_id
- work_order_id
- from_status
- to_status
- changed_by
- changed_at
- reason

---

## 10. Breakdown

### failure_categories
- id
- company_id
- name
- code

### failure_codes
- id
- company_id
- failure_category_id
- name
- code

### root_causes
- id
- company_id
- name
- code

### breakdowns
- id
- company_id
- factory_id
- asset_id
- reported_by
- failure_at
- reported_at
- acknowledged_at nullable
- technician_arrival_at nullable
- repair_started_at nullable
- repair_completed_at nullable
- production_resumed_at nullable
- priority
- severity
- status
- problem_description
- failure_category_id nullable
- failure_code_id nullable
- root_cause_id nullable
- corrective_action nullable
- preventive_action nullable
- production_line_id nullable
- production_order_reference nullable
- (production loss quantities live in `production_impacts`; see Section 11)
- breakdown_number
- asset_location_id nullable
- assigned_technician_id nullable
- assigned_team_id nullable
- assigned_at nullable
- acknowledged_by nullable
- downtime_class (`UNPLANNED` by default)
- downtime_reason_code_id nullable
- is_recurrence_of_breakdown_id nullable
- closed_by nullable
- closed_at nullable
- closure_notes nullable
- created_at
- updated_at

Status: `REPORTED`, `ACKNOWLEDGED`, `ASSIGNED`, `IN_REPAIR`, `ON_HOLD`, `REPAIRED`, `PRODUCTION_RESUMED`, `CLOSED`, `CANCELLED`.

Unique: `(company_id, breakdown_number)`.

Rules:
1. `failure_at` may precede `reported_at` but never follows it.
2. The timestamp chain must be non-decreasing: `failure_at` <= `reported_at` <= `acknowledged_at` <= `technician_arrival_at` <= `repair_started_at` <= `repair_completed_at` <= `production_resumed_at`.
3. Closing requires `repair_completed_at`, a `root_cause_id`, and a `failure_code_id`.
4. A breakdown reported on an asset that already has an open breakdown is linked through `is_recurrence_of_breakdown_id` rather than counted as an independent failure for MTBF.

### breakdown_status_histories
- id
- company_id
- breakdown_id
- from_status
- to_status
- changed_by
- changed_at
- reason

Absent in v1.0, which made the breakdown lifecycle the only major workflow with no state audit trail.

### breakdown_attachments
- id
- company_id
- breakdown_id
- file_id
- caption nullable
- uploaded_by
- created_at

### breakdown_work_orders — removed

v1.0 defined both `work_orders.breakdown_id` and a `breakdown_work_orders` pivot. Two ways to express the same link guarantee they will disagree.

**Decision:** the pivot is removed. One breakdown may generate many work orders; a work order belongs to at most one breakdown. `work_orders.breakdown_id` is the single link.

---

## 11. Production Impact

### production_impacts
- id
- company_id
- breakdown_id
- production_line_id
- production_order_reference
- estimated_loss
- actual_loss
- unit
- notes

---

## 12. Downtime

### downtime_records
- id
- company_id
- asset_id
- breakdown_id nullable
- work_order_id nullable
- failure_at
- reported_at
- acknowledged_at
- technician_arrival_at
- repair_started_at
- repair_completed_at
- production_resumed_at
- response_minutes
- repair_minutes
- total_downtime_minutes
- calculation_version
- downtime_class (`UNPLANNED` | `PLANNED` | `NON_OPERATING` | `EXTERNAL`)
- downtime_reason_code_id nullable
- hold_minutes (excluded from repair time)
- calendar_aware (whether the shift calendar was applied)
- scheduled_operating_minutes_in_window
- counts_against_availability
- factory_id
- production_line_id nullable
- calculated_at
- created_at

Rules:
1. Every downtime row is classified. An unclassified row defaults to `UNPLANNED` and is flagged for review, never silently excluded.
2. `total_downtime_minutes` is computed against the factory working calendar when `metrics.downtime_uses_shift_calendar` is enabled (SRS 47).
3. `repair_minutes` excludes `hold_minutes`.
4. Rows are recalculated only by an explicit, audited backfill that writes a new `calculation_version`. Changing a downtime rule must never silently rewrite closed-period KPIs.

### downtime_reason_codes
- id
- company_id
- code
- name
- downtime_class
- counts_against_availability
- active

Tenant-configurable master data. Examples: `NEEDLE_BREAK`, `MOTOR_FAILURE`, `POWER_OUTAGE`, `NO_OPERATOR`, `MATERIAL_SHORTAGE`, `PLANNED_PM`.

---

## 13. Spare Parts and Inventory

### spare_part_categories
- id
- company_id
- name
- code

### spare_parts
- id
- company_id
- part_number
- name
- category_id
- brand
- manufacturer
- unit
- minimum_stock
- reorder_level
- unit_cost (last purchase price, informational only)
- lead_time_days nullable
- default_vendor_id nullable
- is_critical_spare
- shelf_life_days nullable
- hazardous
- notes nullable
- created_at
- updated_at

Unique: `(company_id, part_number)`.

Stock levels are never stored here. `minimum_stock` and `reorder_level` are policy thresholds; actual quantities live in `inventory_balances` and are derived from the ledger.

### spare_part_compatibilities
- id
- company_id
- spare_part_id
- asset_model_id nullable
- asset_id nullable
- compatibility_type
- substitute_for_part_id nullable

### warehouses
- id
- company_id
- factory_id
- name
- code

### stores
- id
- company_id
- warehouse_id
- name
- code

### bins
- id
- company_id
- store_id
- name
- code

### inventory_balances
- id
- company_id
- spare_part_id
- bin_id
- quantity_on_hand
- quantity_reserved
- weighted_average_cost
- currency
- version

### inventory_transactions
- id
- company_id
- spare_part_id
- bin_id
- transaction_type
- quantity
- unit_cost
- total_cost
- currency
- reference_type
- reference_id
- performed_by
- transaction_at
- balance_after (quantity on hand in the bin after this row)
- wac_after (weighted average cost after this row)
- reservation_id nullable
- inventory_transfer_id nullable
- work_order_id nullable
- reverses_transaction_id nullable
- exchange_rate
- base_total_cost
- notes nullable
- idempotency_key nullable
- created_at

Transaction types: `OPENING_BALANCE`, `RECEIPT`, `ISSUE`, `CONSUME`, `RETURN`, `ADJUSTMENT_IN`, `ADJUSTMENT_OUT`, `TRANSFER_OUT`, `TRANSFER_IN`, `SCRAP`, `REVERSAL`.

v1.0 listed `RESERVATION` and `RELEASE` as ledger transaction types. That was wrong: a reservation does not move stock, it only encumbers it. Reservations live in `spare_part_reservations` and adjust `inventory_balances.quantity_reserved`; they never write to the ledger. `ADJUSTMENT` is split into `ADJUSTMENT_IN` and `ADJUSTMENT_OUT` so sign is explicit rather than inferred from the quantity.

Rules:
1. Append-only. A posted transaction is never updated or deleted. A correction is a `REVERSAL` row referencing `reverses_transaction_id`.
2. Every row is written inside the same database transaction as the `inventory_balances` update, under a row lock on the balance.
3. `balance_after` and `wac_after` make the ledger self-auditing: replaying the ledger must reproduce the current balance exactly.
4. Weighted average cost changes only on `RECEIPT`, `TRANSFER_IN`, `RETURN`, and `ADJUSTMENT_IN`. Issues consume at the current WAC and do not change it.
5. `ISSUE` is blocked when `quantity_on_hand - quantity_reserved` is insufficient, unless `inventory.allow_negative_stock` is enabled for the company or factory.


### spare_part_reservations
- id
- company_id
- spare_part_id
- work_order_id
- quantity
- status
- reserved_by
- reserved_at
- bin_id
- work_order_part_id nullable
- quantity_released
- quantity_issued
- expires_at nullable
- released_by nullable
- released_at nullable
- created_at
- updated_at

Status: `ACTIVE`, `PARTIALLY_ISSUED`, `ISSUED`, `RELEASED`, `EXPIRED`, `CANCELLED`.

`bin_id` was missing in v1.0. Because `inventory_balances` is per bin, a reservation without a bin cannot be enforced against any balance.

Rules:
1. Creating a reservation increments `inventory_balances.quantity_reserved` under a row lock.
2. Available stock is `quantity_on_hand - quantity_reserved`.
3. Expired reservations are released by a scheduled job; the release is audited.
4. Reservations never write to `inventory_transactions`.

### inventory_transfers
- id
- company_id
- (bin-level detail moved to `inventory_transfer_items`)
- status
- requested_by
- approved_by
- dispatched_at
- received_at
- transfer_number
- from_factory_id
- to_factory_id
- dispatched_by nullable
- received_by nullable
- rejected_by nullable
- rejected_at nullable
- notes nullable
- in_transit_bin_id (system bin holding dispatched stock)
- created_at
- updated_at

Status: `REQUESTED`, `APPROVED`, `REJECTED`, `DISPATCHED`, `PARTIALLY_RECEIVED`, `RECEIVED`, `CANCELLED`.

### inventory_transfer_items
- id
- company_id
- inventory_transfer_id
- spare_part_id
- from_bin_id
- to_bin_id nullable
- quantity_requested
- quantity_dispatched
- quantity_received
- quantity_variance (derived; drives a discrepancy investigation)
- unit_cost_at_dispatch
- currency
- created_at
- updated_at

v1.0 modelled a transfer as a single `from_bin_id` / `to_bin_id` pair with no line items, so a transfer could only ever move one part. Real inter-factory transfers move many.

Rules:
1. `DISPATCH` writes `TRANSFER_OUT` from the source bin into `in_transit_bin_id`.
2. `RECEIVE` writes `TRANSFER_IN` from the in-transit bin to the destination bin.
3. Stock is never in two places and never nowhere; in-transit quantity is always visible.
4. A receive quantity below the dispatched quantity leaves the balance in the in-transit bin until a variance adjustment is posted with a reason.

---

## 14. Costing

### cost_categories
- id
- company_id
- name
- code

### cost_entries
- id
- company_id
- asset_id
- work_order_id nullable
- breakdown_id nullable
- cost_category_id
- amount
- currency
- exchange_rate
- base_amount
- occurred_at
- description
- source_type (`LABOR` | `PARTS` | `EXTERNAL_SERVICE` | `VENDOR` | `TRANSPORT` | `MANUAL` | `REVERSAL`)
- source_reference_type nullable
- source_reference_id nullable
- vendor_id nullable
- invoice_reference nullable
- posted_at
- posted_by
- reverses_cost_entry_id nullable
- is_reversal
- created_at

Rules:
1. Append-only after `posted_at`. A correction is a `REVERSAL` row referencing `reverses_cost_entry_id`, plus a new corrected entry. The original is never edited or deleted.
2. `base_amount = amount * exchange_rate`, computed at post time and frozen. A later exchange-rate change never rewrites history.
3. Entries derived from labor entries and work order parts are written by the system, not by users, so a work order's cost cannot drift from its underlying records.
4. Reports must sum `base_amount` for cross-currency aggregates and `amount` only within a single currency.

Cost records are append-only after posting.

---

## 15. Vendors and Contracts

### vendors
- id
- company_id
- name
- code
- contact_name
- phone
- email
- address
- status

### warranties
- id
- company_id
- asset_id
- vendor_id
- start_date
- end_date
- coverage
- status

### warranty_claims
- id
- company_id
- warranty_id
- asset_id
- claim_number
- claim_date
- status
- resolution

### service_contracts
- id
- company_id
- asset_id nullable
- vendor_id
- contract_number
- start_date
- end_date
- value
- currency
- coverage
- renewal_date
- status

---

## 16. Technicians

### technicians
- id
- company_id
- user_id nullable
- employee_id
- name
- department_id nullable
- status
- factory_id
- (user_id stays nullable: a technician may exist without a login account)
- phone nullable
- email nullable
- specialization nullable
- labor_grade_id
- (no salary, wage, or payroll field: see Section 16.1)
- joining_date nullable
- shift_id nullable
- max_concurrent_work_orders nullable
- created_at
- updated_at

Unique: `(company_id, employee_id)`.

`factory_id` was missing in v1.0, which left technician workload unscopeable. Costing is attached through `labor_grade_id` rather than a per-person wage; see Section 16.1.

### technician_skills
- id
- company_id
- technician_id
- skill_name
- proficiency

### 16.1 Labor Rate Grades

The platform is a maintenance and machine tracking system, not an HR or payroll system. It stores no salary, wage, or individual compensation data.

Labor cost is therefore computed from a standard rate per grade, not from what any person is actually paid.

### labor_rate_grades
- id
- company_id
- factory_id nullable
- name (for example `Junior Technician`, `Senior Technician`, `Electrician`, `Maintenance Engineer`)
- code
- standard_hourly_rate
- overtime_multiplier (default 2.0)
- currency
- effective_from
- effective_to nullable
- active
- created_at
- updated_at

Unique: `(company_id, code, effective_from)`.

Rules:

1. A technician is assigned to a grade. Two technicians on the same grade cost the same, by design.
2. Grades are effective-dated. Changing a rate creates a new effective period; it never rewrites the cost of work already recorded.
3. Overtime cost is `standard_hourly_rate * overtime_multiplier`, not a separately entered wage.
4. External contractor labor does not use a grade. Its cost is the vendor's charge, recorded on the labor entry with `vendor_id`, because that is an invoiced amount rather than employee compensation.

Consequences:

- Maintenance cost per machine remains computable, which is the point: repair-versus-replace decisions need it.
- No maintenance user can infer a colleague's salary from the system.
- The maintenance department does not become an unintended HR data store, and the system stays outside payroll's compliance surface.

---

## 17. Notifications

### notifications
- id
- company_id
- user_id
- event_type
- title
- body
- data_json
- read_at
- entity_type nullable
- entity_id nullable
- factory_id nullable
- severity (`INFO` | `WARNING` | `CRITICAL`)
- action_url nullable
- locale
- escalation_level (0 = original recipient)
- source_notification_id nullable (set when this is an escalation of another)
- expires_at nullable

Rules:
1. Notifications are persisted first and broadcast second. A failed broadcast never loses a notification.
2. `title` and `body` are rendered in the recipient locale at creation time (SRS 48).
3. Escalations create new notification rows linked through `source_notification_id`; the original is not mutated, so the escalation chain is auditable.
- created_at

### notification_preferences
- id
- company_id
- user_id
- event_type
- in_app
- email
- sms
- whatsapp

### escalation_rules
- id
- company_id
- event_type
- severity
- delay_minutes
- escalation_role_id
- factory_id nullable
- escalation_level
- escalation_team_id nullable
- escalation_user_id nullable
- channel_overrides_json nullable
- max_escalations
- stop_on_acknowledge
- active

Escalation is evaluated by a scheduled job against unacknowledged notifications. Each level has its own `delay_minutes` measured from the original event, not from the previous escalation, so a stalled chain cannot drift.

### notification_deliveries
- id
- company_id
- notification_id
- channel
- status
- sent_at
- failure_reason

---

## 18. Audit

### audit_logs
- id
- company_id nullable
- user_id nullable
- action
- entity_type
- entity_id
- old_values_json
- new_values_json
- ip_address
- user_agent
- created_at
- request_id (correlates every row written by one HTTP request or job)
- api_client_id nullable
- impersonated_by nullable (platform support access, SRS 5.4)
- changed_fields_json (the diff, so a wide update does not require comparing two full snapshots)
- context (`API` | `UI` | `JOB` | `CONSOLE` | `IMPORT` | `WEBHOOK`)

Rules:
1. Append-only. No update, no delete, no soft delete.
2. `old_values_json` and `new_values_json` exclude password hashes, tokens, secrets, and encrypted MFA material.
3. Rows older than the Section 49 retention threshold are moved to cold storage, never dropped.
4. Audit writes never block the business transaction; they are dispatched to a queue but must be durable, so the job is retried until it succeeds and failures alert.

Audit logs are append-only.

---

## 19. Subscription and Billing

### subscription_contracts
- id
- company_id
- contract_number
- status
- start_date
- end_date
- billing_cycle
- amount
- currency
- trial_end
- grace_period_days
- auto_renew
- read_only_at nullable
- archived_at nullable
- cancelled_at nullable
- cancellation_reason nullable
- pricing_model_json (per-factory, per-asset, per-user, or flat terms)
- included_factories nullable
- included_assets nullable
- included_users nullable
- overage_policy (`BLOCK` | `ALLOW_AND_BILL` | `WARN_ONLY`)
- notes nullable
- created_at
- updated_at

Status: `DRAFT`, `TRIAL`, `ACTIVE`, `PAST_DUE`, `GRACE`, `READ_ONLY`, `ARCHIVED`, `CANCELLED`.

Lifecycle transitions are executed by a scheduled job and are audited. `READ_ONLY` is enforced by middleware that rejects every write endpoint with `SUBSCRIPTION_READ_ONLY`, while all read and export endpoints remain available so a customer can always retrieve their own data.

`overage_policy` and the `included_*` limits are the link between usage metrics and enforcement, which v1.0 described but never modelled.

### subscription_invoices
- id
- company_id
- subscription_contract_id
- invoice_number
- issue_date
- due_date
- subtotal
- tax
- total
- currency
- status
- tax_rate
- tax_reference nullable
- paid_amount
- balance_due
- paid_at nullable
- voided_at nullable
- void_reason nullable
- pdf_file_id nullable
- notes nullable
- created_at
- updated_at

Status: `DRAFT`, `ISSUED`, `PARTIALLY_PAID`, `PAID`, `OVERDUE`, `VOID`, `WRITTEN_OFF`.

Unique: `(company_id, invoice_number)`.

An issued invoice is immutable. It is corrected by a credit note or by voiding and reissuing, never by editing.

### subscription_invoice_lines
- id
- company_id
- subscription_invoice_id
- description
- metric nullable (`FACTORIES` | `ASSETS` | `USERS` | `FLAT`)
- quantity
- unit_price
- amount
- tax_rate
- tax_amount
- period_start nullable
- period_end nullable
- sort_order

v1.0 had an invoice header with no lines, so a contract priced per factory or per asset could not be itemized and the customer could not see what they were billed for.

### subscription_payments
- id
- company_id
- invoice_id
- payment_reference
- method
- amount
- currency
- paid_at
- status

### refunds
- id
- company_id
- payment_id
- amount
- currency
- reason
- status

### credit_notes
- id
- company_id
- invoice_id
- amount
- currency
- reason
- status

### usage_metrics
- id
- company_id
- metric
- value
- measured_at
- factory_id nullable
- period_start
- period_end
- limit_value nullable
- exceeded

Metrics: `ACTIVE_USERS`, `ACTIVE_FACTORIES`, `ACTIVE_ASSETS`, `WORK_ORDERS_CREATED`, `STORAGE_BYTES`, `API_CALLS`, `WEBHOOK_DELIVERIES`.

Usage is measured whether or not it is billed, so a contract renewal can be priced from evidence.

---

## 20. Approval

### approval_workflows
- id
- company_id
- name
- entity_type
- active

### approval_rules
- id
- company_id
- workflow_id
- condition_json
- sequence
- role_id

### approval_requests
- id
- company_id
- workflow_id
- entity_id
- status
- requested_by
- entity_type
- current_step
- total_steps
- requested_at
- completed_at nullable
- expires_at nullable
- context_json (the cost, criticality, and factory values the rules were evaluated against)

Status: `PENDING`, `APPROVED`, `REJECTED`, `CANCELLED`, `EXPIRED`.

Rules:
1. `context_json` freezes the values the decision was made on. A later cost change does not retroactively alter what an approver saw.
2. Approvals are sequential by `approval_rules.sequence`. A step may name a role, a specific user, or a team.
3. The requester may never approve their own request.
4. An approval decision writes an `approval_actions` row and an audit log entry.

### approval_actions
- id
- company_id
- approval_request_id
- approver_id
- action
- comment
- acted_at

---

## 21. Import/Export

### import_jobs
- id
- company_id
- type
- file_id
- status
- total_rows
- success_rows
- failed_rows
- started_at
- completed_at

### import_errors
- id
- import_job_id
- row_number
- field
- error

### export_jobs
- id
- company_id
- type
- filters_json
- status
- file_id
- requested_by

---

## 22. Webhooks

### webhook_endpoints
- id
- company_id
- url
- secret
- status
- description nullable
- signing_algorithm (`HMAC_SHA256`)
- secret_rotated_at nullable
- previous_secret nullable (accepted during a rotation window)
- consecutive_failure_count
- disabled_at nullable
- disabled_reason nullable
- created_by
- created_at
- updated_at

An endpoint is automatically disabled after a configured number of consecutive failures, and the tenant is notified.

### webhook_subscriptions
- id
- company_id
- webhook_endpoint_id
- event_type

### webhook_deliveries
- id
- company_id
- webhook_endpoint_id
- event_type
- payload_json
- status
- attempt_count
- delivered_at
- event_id (stable ULID sent to the receiver for their own deduplication)
- signature
- request_headers_json
- response_status nullable
- response_body_excerpt nullable
- next_retry_at nullable
- last_attempted_at nullable
- duration_ms nullable
- created_at

Retry policy: exponential backoff at 1m, 5m, 30m, 2h, 6h, 24h, then the endpoint is disabled. Payloads are purged after 30 days (SRS 49.1); delivery metadata is retained.

---

## 23. Working Calendar and Shifts

Required by SRS 47. Absent from v1.0, which left downtime, availability, and escalation timers without a definition of working time.

### shifts
- id
- company_id
- factory_id
- name
- code
- start_time
- end_time
- crosses_midnight
- days_of_week (bitmask or JSON array)
- effective_from
- effective_to nullable
- is_overtime
- status
- created_at
- updated_at

### shift_breaks
- id
- company_id
- shift_id
- name
- start_time
- end_time
- counts_as_operating_time

### factory_calendars
- id
- company_id
- factory_id
- operating_mode (`CONTINUOUS` | `SHIFT_BASED`)
- weekly_off_days (JSON array)
- effective_from
- effective_to nullable

### factory_holidays
- id
- company_id
- factory_id
- date
- name
- is_working_day (allows an override that makes a normal off-day a working day)

### production_line_shift_overrides
- id
- company_id
- production_line_id
- shift_id
- effective_from
- effective_to nullable

Rules:
1. Calendar rows are versioned by effective date. Editing a shift never rewrites a closed period.
2. Scheduled operating minutes for a period are derived from these tables and cached; the cache key includes the calendar version.
3. A factory with no calendar falls back to `CONTINUOUS`, and reports must surface that fallback.

---

## 24. Configuration and Settings

Required by SRS 53.

### settings
- id
- company_id nullable (null = platform default)
- factory_id nullable
- production_line_id nullable
- key
- value_json
- value_type
- updated_by
- created_at
- updated_at

Unique: `(company_id, factory_id, production_line_id, key)`.

Resolution is most-specific-wins: line, then factory, then company, then platform default. The effective value and its defining level must both be resolvable, so an administrator can see why a value is what it is.

### setting_definitions
- key
- scope_levels (which levels may define it)
- value_type
- default_value_json
- description
- is_sensitive

A seeded catalog. A key that is not in this table cannot be set, which prevents configuration from becoming an untyped free-for-all.

---

## 25. Document Numbering

Required by SRS 52.

### number_sequences
- id
- company_id
- factory_id nullable
- document_type
- format
- period_key (for example `2026` or `2026-08`, or a constant when the sequence never resets)
- reset_policy (`NEVER` | `YEARLY` | `MONTHLY`)
- current_value
- padding
- updated_at

Unique: `(company_id, factory_id, document_type, period_key)`.

Allocation uses an atomic increment under a row lock, or a dedicated transaction that commits before the parent record is written. Gaps are acceptable; duplicates are not. Numbers are never reused after a record is deleted or cancelled.

---

## 26. Idempotency and Concurrency

Required by SRS 39 and API Specification 32. v1.0 mandated idempotency keys without defining where they are stored.

### idempotency_keys
- id
- company_id
- user_id nullable
- api_client_id nullable
- key
- endpoint
- request_hash (hash of method, path, and body)
- status (`IN_PROGRESS` | `COMPLETED` | `FAILED`)
- response_status nullable
- response_body_json nullable
- resource_type nullable
- resource_id nullable
- locked_at
- expires_at
- created_at

Unique: `(company_id, key, endpoint)`.

Rules:
1. Keys are scoped to a company and endpoint. One tenant's key can never collide with another's.
2. A replay with the same key and the same `request_hash` returns the stored response with the original status code.
3. A replay with the same key but a different `request_hash` returns `409 IDEMPOTENCY_CONFLICT`.
4. A concurrent replay while `status = IN_PROGRESS` returns `409` rather than executing twice.
5. Keys expire after 24 hours and are purged by a scheduled job.

Optimistic locking uses a `version` integer on `assets`, `work_orders`, `inventory_balances`, `breakdowns`, `maintenance_plans`, and `subscription_contracts`. Every update increments it; a stale version returns `409 CONFLICT`.

---

## 27. Localization

Required by SRS 48.

### translations
- id
- company_id nullable (null = platform-seeded)
- translatable_type
- translatable_id
- locale
- field
- value

Unique: `(translatable_type, translatable_id, locale, field)`.

Used for seeded master data (maintenance types, failure codes, checklist labels, downtime reason codes) that ships in English and needs a Bengali label. Tenant-entered free text is stored as entered and is not translated.

### locales
- code
- name
- native_name
- date_format
- number_format
- active

---

## 28. Reporting and Job Tables

### report_jobs
- id
- company_id
- user_id
- report_type
- parameters_json
- filters_json
- format (`CSV` | `XLSX` | `PDF`)
- locale
- status (`QUEUED` | `RUNNING` | `COMPLETED` | `FAILED` | `EXPIRED`)
- file_id nullable
- row_count nullable
- error_message nullable
- started_at nullable
- completed_at nullable
- expires_at
- created_at

The API specification exposed `/report-jobs` with no backing table in v1.0; `export_jobs` covered raw data export only, which is a different concern.

### kpi_snapshots
- id
- company_id
- factory_id nullable
- asset_id nullable
- scope_type (`COMPANY` | `FACTORY` | `LINE` | `ASSET`)
- period_type (`DAY` | `WEEK` | `MONTH`)
- period_start
- period_end
- scheduled_operating_minutes
- downtime_minutes
- unplanned_downtime_minutes
- failure_count
- mtbf_minutes nullable
- mttr_minutes nullable
- availability_percent nullable
- pm_compliance_percent nullable
- calculation_version
- computed_at

Precomputed by a scheduled job so dashboards meet the SRS 45 latency target without scanning the transaction tables. Closed periods are computed once; the current period is refreshed on a short interval.

---

## 29. Attachments

### attachments
- id
- company_id
- attachable_type
- attachable_id
- file_id
- attachment_type
- caption nullable
- uploaded_by
- created_at

A generic attachment table for entities that do not warrant a dedicated one (vendors, service contracts, warranty claims, cost entries, import jobs). Asset documents, work order attachments, and breakdown attachments keep their dedicated tables because they carry entity-specific semantics such as document versioning and before/after evidence.

---

## 30. Recommended ERD Relationships

```text
Organization
  └── Company
       ├── Users/Roles
       ├── Factories
       │    └── Locations
       ├── Assets
       │    ├── Parent Asset
       │    ├── Maintenance Plans
       │    │    └── Schedules
       │    ├── Work Orders
       │    ├── Breakdowns
       │    ├── Costs
       │    └── Documents
       ├── Spare Parts
       │    └── Inventory Ledger
       ├── Vendors
       ├── Notifications
       ├── Audit Logs
       └── Subscription
```

---

## 31. Required Indexes

At minimum:
- `companies.code`
- `assets(company_id, asset_code)`
- `assets(company_id, serial_number)`
- `assets(company_id, status)`
- `assets(company_id, current_factory_id)`
- `maintenance_schedules(company_id, due_at, status)`
- `work_orders(company_id, status)`
- `work_orders(company_id, asset_id)`
- `breakdowns(company_id, status)`
- `breakdowns(company_id, asset_id)`
- `inventory_transactions(company_id, spare_part_id, transaction_at)`
- `notifications(company_id, user_id, read_at)`
- `audit_logs(company_id, entity_type, entity_id)`
- `subscription_contracts(company_id, status)`

Additional indexes required by the queries the SRS actually asks for:

**Tenant and hierarchy**
- `company_users(company_id, user_id)` unique
- `user_roles(company_id, user_id, factory_id)`
- `asset_locations(company_id, factory_id)`
- `settings(company_id, factory_id, production_line_id, key)` unique

**Assets**
- `assets(company_id, asset_location_id)`
- `assets(company_id, parent_asset_id)`
- `assets(company_id, criticality, status)`
- `asset_transfer_history(company_id, asset_id, transfer_at)`
- `asset_status_histories(company_id, asset_id, changed_at)`

**Maintenance**
- `maintenance_plans(company_id, active, next_due_at)`
- `maintenance_schedules(company_id, asset_id, status)`
- `maintenance_schedules(maintenance_plan_id, asset_id, due_at)` unique
- `maintenance_schedules(company_id, status, due_at)` for the overdue sweep

**Metering**
- `meter_readings(company_id, meter_id, reading_at)`
- `meter_readings(company_id, meter_id, reading_at, source)` unique
- `asset_meters(company_id, asset_id, meter_type_id)` unique

**Work orders**
- `work_orders(company_id, work_order_number)` unique
- `work_orders(company_id, factory_id, status, scheduled_start)`
- `work_orders(company_id, approval_status)` partial to `PENDING`
- `work_order_assignments(company_id, technician_id, unassigned_at)`
- `work_order_labor_entries(company_id, technician_id, started_at)`
- `work_order_parts(company_id, work_order_id)`
- `work_order_parts(company_id, spare_part_id)`

**Breakdown and downtime**
- `breakdowns(company_id, breakdown_number)` unique
- `breakdowns(company_id, factory_id, status, reported_at)`
- `breakdowns(company_id, asset_id, failure_at)`
- `downtime_records(company_id, asset_id, failure_at)`
- `downtime_records(company_id, factory_id, downtime_class, failure_at)`
- `kpi_snapshots(company_id, scope_type, period_type, period_start)` unique with scope id

**Inventory**
- `spare_parts(company_id, part_number)` unique
- `inventory_balances(company_id, spare_part_id, bin_id)` unique
- `inventory_transactions(company_id, bin_id, transaction_at)`
- `inventory_transactions(company_id, reference_type, reference_id)`
- `spare_part_reservations(company_id, spare_part_id, bin_id, status)`
- `inventory_transfer_items(company_id, inventory_transfer_id)`

**Cost and billing**
- `cost_entries(company_id, asset_id, occurred_at)`
- `cost_entries(company_id, work_order_id)`
- `subscription_invoices(company_id, invoice_number)` unique
- `subscription_invoices(company_id, status, due_date)`
- `usage_metrics(company_id, metric, period_start)`

**Operational**
- `notifications(company_id, user_id, created_at)`
- `audit_logs(company_id, created_at)`
- `audit_logs(request_id)`
- `idempotency_keys(company_id, key, endpoint)` unique
- `idempotency_keys(expires_at)` for the purge job
- `number_sequences(company_id, factory_id, document_type, period_key)` unique
- `webhook_deliveries(company_id, status, next_retry_at)`
- `files(company_id, checksum)`
- `translations(translatable_type, translatable_id, locale, field)` unique

### 31.1 Index Discipline

1. Every tenant-scoped index begins with `company_id`. An index that does not is a cross-tenant table scan waiting to happen.
2. Every foreign key column has an index; MySQL creates one automatically only when no usable prefix exists.
3. Composite index column order follows equality columns first, then range, then sort.
4. Redundant left-prefix indexes are removed rather than accumulated.
5. New reports must not be shipped without checking their execution plan against the Section 51 target volumes in the SRS.

---

### 31.2 Referential Actions

Every foreign key declares its behavior explicitly.

| Relationship class | Action | Reason |
|---|---|---|
| Tenant root to owned record (`company_id`) | `RESTRICT` | A company is archived, never cascade-deleted |
| Master data to transaction (spare part, asset type, failure code) | `RESTRICT` | History must not lose its labels |
| Parent document to its own lines (invoice to invoice lines, transfer to transfer items) | `CASCADE` | Lines have no meaning without the header |
| Optional classification (`downtime_reason_code_id`, `root_cause_id`) | `SET NULL` | Retiring a code must not block deletion of nothing |
| Ledger and audit rows | No delete path | Append-only; the rows are never removed |
| Soft-deletable entity to its history | `RESTRICT` | Soft delete does not remove rows, so history stays intact |

Soft delete (`deleted_at`) applies only to `companies`, `assets`, `spare_parts`, `vendors`, `technicians`, `users`, and master data tables. Transactional and financial tables have no `deleted_at`.

---

### 31.3 Archival and Partitioning

Tables that grow without bound at the SRS Section 51 volumes:

| Table | Strategy | Trigger |
|---|---|---|
| `audit_logs` | Monthly range partition on `created_at`; detach and archive partitions older than 24 months | 100M rows |
| `meter_readings` | Monthly range partition on `reading_at`; downsample to daily aggregates after 36 months | 200M rows |
| `inventory_transactions` | Yearly partition; never purged, archived to cold storage after 7 years | 50M rows |
| `notifications` | Delete read notifications older than 12 months | 50M rows |
| `webhook_deliveries` | Purge payloads after 30 days, rows after 90 days | 20M rows |
| `login_attempts` | Purge after 90 days | 10M rows |
| `kpi_snapshots` | Retain daily for 2 years, monthly indefinitely | — |

Archival jobs run off-peak, are chunked, and are resumable. An archival job must never hold a long transaction on a live table.

---

## 32. ERD Integrity Rules

1. Never trust client-supplied `company_id`; derive it from authenticated tenant context.
2. Every cross-table relation must be tenant validated.
3. Prevent child asset cycles.
4. Prevent asset transfer to another tenant.
5. Prevent negative stock unless explicitly configured.
6. Inventory issue must not exceed available stock.
7. Work order closure requires required checklist completion.
8. Critical assets require verification.
9. Posted cost entries cannot be edited directly.
10. Posted inventory transactions cannot be deleted.
11. Audit records cannot be deleted.
12. Subscription financial records are immutable after posting.
13. Reservations encumber stock but never write to the inventory ledger.
14. A work order cannot close while it holds issued parts that are neither consumed nor returned.
15. Labor entries for one technician may not overlap in time.
16. Breakdown timestamps must form a non-decreasing chain.
17. Checklist template versions are immutable once referenced by any work order.
18. Maintenance schedule generation is idempotent; a unique constraint on `(plan, asset, due_at)` enforces it at the database level rather than trusting the job to behave.
19. A meter reading below the current value is rejected unless preceded by a reset event.
20. Document numbers are unique per company and are never reused.
21. Idempotency keys are scoped per company and endpoint.
22. Settings may only use keys present in `setting_definitions`.
23. `assets.asset_location_id` must resolve to a location whose `factory_id` equals `assets.current_factory_id`.
24. Every polymorphic reference is validated for both tenant ownership and allowed type before use.
25. Derived money columns (`work_orders.actual_cost`, `inventory_balances.*`) are written only by the application layer that owns them, never accepted from a client payload.

---

## 33. Model Coverage Check

Every requirement in the SRS must resolve to at least one table. Requirements added in SRS v1.1 map as follows:

| SRS section | Tables |
|---|---|
| 13.2 Labor logging | `work_order_labor_entries`, `labor_rate_grades` |
| 13.3 Parts consumption | `work_order_parts`, `inventory_transactions` |
| 13.4 Attachments | `work_order_attachments`, `breakdown_attachments`, `attachments` |
| 14 Approval workflow | `approval_workflows`, `approval_rules`, `approval_requests`, `approval_actions`, `work_orders.approval_status` |
| 17.1 Downtime classification | `downtime_records.downtime_class`, `downtime_reason_codes` |
| 31 KPI definitions | `kpi_snapshots`, `downtime_records` |
| 47 Working calendar | `shifts`, `shift_breaks`, `factory_calendars`, `factory_holidays`, `production_line_shift_overrides` |
| 48 Localization | `translations`, `locales`, `users.locale`, `companies.default_locale` |
| 49 Retention | Section 31.3 archival strategy |
| 50 Account security | `user_mfa_methods`, `user_recovery_codes`, `user_sessions`, `login_attempts` |
| 52 Numbering | `number_sequences` |
| 53 Settings | `settings`, `setting_definitions` |
| 5.4 Support access | `support_access_grants` |
| API idempotency | `idempotency_keys` |
| Teams and assignment | `teams`, `team_members` |
| Report jobs | `report_jobs` |
| API clients | `api_clients` |

Two columns are added to existing tables for localization: `users.locale` and `companies.default_locale`.
