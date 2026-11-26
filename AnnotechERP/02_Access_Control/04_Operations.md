# Operations

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Access Control](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `02/04`

**⬅ ফিরে যান:** [Access Control](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Rule Violation Log | `/m/security/rules-violation` | 3 | 6 | 0 |
| 2 | TNA Task Status Setup | `/m/security/tna-task-status` | 4 | 12 | 1 |
| 3 | Global Notification Broadcast | `/m/security/global-notification` | 2 | 8 | 0 |
| 4 | Document Deletion Console | `/m/security/pr-po-mrr-etc-deletion` | 3 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Rule Violation Log

`/m/security/rules-violation`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Rules Violation, Record violation

**ট্যাব: Rules Violation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search scopes | search |  | Search… |
- সেকশন: Rules Scopes, Raised Violations
- বাটন/অ্যাকশন: Rules Violation, Record violation, Issue beyond available stock BR-002 0, Order confirmed without costing BR-003 0, Shade approved without lab dip BR-004 0, Overtime above statutory cap BR-005 0

**ট্যাব: Record violation**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Business Rule* | select | ✔ | Select…, BR-001 — Purchase order above requisition value, BR-002 — Issue beyond available stock, BR-003 — Order confirme |
| Document Reference* | text | ✔ | PO-261024 |
| Severity* | select | ✔ | Select…, Low, Medium, High, Critical |
| Status | select |  | Select…, Open, Acknowledged, Waived, Resolved |
| Detail* | textarea | ✔ | Purchase order raised above the approved requisition value… |
- সেকশন: New violation
- বাটন/অ্যাকশন: Rules Violation, Record violation, Select…, Reset

#### TNA Task Status Setup

`/m/security/tna-task-status`

**ট্যাব: Records 44**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Time-and-action events with their order, section, owner, repeat behaviour | search |  | Search 44 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Event Name, Task Message, Order, Parent, Section, Assign To, Offset, R B, Raised, Status, ACTION
- বাটন/অ্যাকশন: TNA Task Status, Mapped User List, New TNA event, Export, Code, Event Name, Task Message, Order, Parent, Section, Assign To, Offset, R B, Raised, Status, Edit

**ট্যাব: TNA Task Status**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search events | search |  | Search… |
- সেকশন: TNA Task List, Raised Instances
- বাটন/অ্যাকশন: TNA Task Status, Mapped User List, New TNA event, Issue PI/TT/Sign Sale Contract TNA-010 0, Rcv Order Confirm MER-ORDCFM 0, Yarn Booking TNA-020 0, Lab Dip Approval TNA-030 0, Knitting Start (Y/D) TNA-040 0, Rcv Export LC MER-EXPLC 0, 1st/Fit Sample Sending MER-FITSND 0, Knitting Start (AOP) TNA-050 0, 1st/Fit Sample Approval MER-FITAPP 0, Knitting (Y/D) TNA-060 0, Dyeing Start (Y/D) TNA-070 0, PP/Counter/IC Sample Sending MER-PPSND 0, Dyeing Finishing (RIB/CLCF) TNA-080 0, Bulk Fabric Booking MER-BULKFB 0, Fabric In-House TNA-090 0, Bulk Shade Approval TNA-100 0, Yarn Booking (Bulk) MER-YARNBK-BULK 0

**ট্যাব: Mapped User List**
- সেকশন: Mapped User List
- বাটন/অ্যাকশন: TNA Task Status, Mapped User List, New TNA event, -- Select TNA event --

**ট্যাব: New TNA event**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Event Code* | text | ✔ | TNA-018 |
| Event Name* | text | ✔ | Bulk Shade Approval |
| Task Message | text |  | Submit bulk shade for approval |
| Status Order* | number | ✔ |  |
| Task Status | select |  | Select…, Pending, In Progress, Completed, Delayed, Cancelled |
| Offset Days | number |  |  |
| Is Repeat Raised once per colour or delivery rather than once per order. | checkbox |  |  |
| Is Buyer Feedback | checkbox |  |  |
| Is Active | checkbox |  |  |
- সেকশন: New TNA event
- বাটন/অ্যাকশন: TNA Task Status, Mapped User List, New TNA event, Select…, Reset, Save TNA event

**ডায়ালগ: New TNA event**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Event Code* | text | ✔ |  |
| Event Name* | text | ✔ |  |
| Task Message | text |  |  |
| Status Order* | number | ✔ |  |
| Task Status | select |  | Select…, Pending, In Progress, Completed, Delayed, Cancelled |
| Offset Days | number |  |  |
| Is Repeat Raised once per colour or delivery rather than once per order. | checkbox |  |  |
| Is Buyer Feedback | checkbox |  |  |
| Is Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Event Code is required.

#### Global Notification Broadcast

`/m/security/global-notification`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Send to all

**ট্যাব: Send to all**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Notification Title* | text | ✔ | Planned maintenance window |
| Notification Type* | select | ✔ | Select…, Information, Warning, Alert, Maintenance |
| Notification Message* | textarea | ✔ | The ERP will be unavailable on Friday from 22:00 to 02:00… |
| Audience* | select | ✔ | Select…, All, Role, User |
| Severity | select |  | Select…, Low, Medium, High, Critical |
| Message Time* | date | ✔ |  |
| Off-line Time | date |  |  |
| Active | checkbox |  |  |
- সেকশন: New notification
- বাটন/অ্যাকশন: Send to all, Select…, Reset

#### Document Deletion Console

`/m/security/pr-po-mrr-etc-deletion`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Deletion Admin Panel, Raise deletion request

**ট্যাব: Deletion Admin Panel**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search document types | search |  | Search… |
- সেকশন: Deletion Type, Requests
- বাটন/অ্যাকশন: Deletion Admin Panel, Raise deletion request, General Pr 0, Trims And Acc Pr 0, Import Fabric Pr 0, General Po 0, Trims And Acc Po 0, Import Fabric Po 0, Material Receive Report 0, Goods Receive Note 0, Store Requisition 0, Sales Invoice 0, Delivery Challan 0

**ট্যাব: Raise deletion request**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Document No* | text | ✔ | PO-261024 |
| Reason* | textarea | ✔ | Raised in error against the wrong buyer; replaced by PO-261031. |
| Status | select |  | Select…, Draft, Pending, Approved, Rejected, Cancelled |
| Approval Remarks | textarea |  |  |
- সেকশন: New deletion request
- বাটন/অ্যাকশন: Deletion Admin Panel, Raise deletion request, Select…, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Notification মডিউল (Global Notification Broadcast)
- AuditLog (Rule Violation-এর ইতিহাস)

## ৪. ডাটা মডেল (Entity ও Field)

- **`business_rules` + `rule_violations` (নতুন):** BR-001…BR-008 (PO > requisition value, stock-এর বেশি ইস্যু, কস্টিং ছাড়া অর্ডার কনফার্ম, ল্যাব-ডিপ ছাড়া শেড অনুমোদন, আইনি সীমার বেশি ওভারটাইম, narration ছাড়া ভাউচার, বায়ারের সীমার বেশি ইন্সপেকশন পয়েন্ট, LC মেয়াদের পরে শিপমেন্ট); violation: rule, document_reference, severity, status (`OPEN`/`ACKNOWLEDGED`/`WAIVED`/`RESOLVED`), detail। নিয়মগুলো যার যার মডিউলে চেক হবে, যার override-অনুমতি আছে তিনি পার করতে পারবেন আর সেটা এখানে লগ হবে।
- **Global Notification Broadcast:** title, type (`INFORMATION`/`WARNING`/`ALERT`/`MAINTENANCE`), message, audience (role/user), severity, message_time, offline_time, active। বিদ্যমান Notification মডিউলের মাধ্যমে পাঠানো হবে।
- **Document Deletion Console:** deletion_requests (document_type: PR/PO/MRR/GRN/Store Requisition/Sales Invoice/Delivery Challan …, document_no, reason, status Draft→Pending→Approved/Rejected, approval_remarks)। Approval Engine দিয়ে অনুমোদনের পরে মোছা। ডকুমেন্টগুলোর নতুন মডিউল তৈরি হলে যুক্ত হবে।
- **TNA Task Status Setup:** TNA ইভেন্ট মাস্টার (code, name, message, status order, parent, section, assign to, offset days, is_repeat, is_buyer_feedback)। এটা Merchandise Desk-এর TNA Template Setup-এর সাথে একসাথে হবে।

## ৫. Workflow / Status ও Business Rules

- Violation: নিয়ম হতে হবে BUSINESS_RULE লুকআপের একটা মান; নতুন লঙ্ঘন OPEN, কে তুলেছেন সেটা নিজে থেকে লেখা হয়।
- Broadcast: সক্রিয় অবস্থায় সেভ করলে সাথে সাথে GLOBAL_BROADCAST নোটিফিকেশন যায় All/Role/User-কে (শুধু সক্রিয় সদস্য), প্রাপক-সংখ্যা রেকর্ড হয়; পরে এডিট করলে আবার পাঠায় না। Role/User হলে কাকে, সেটা বাধ্যতামূলক। গুরুত্ব LOW/MEDIUM→INFO, HIGH→WARNING, CRITICAL→CRITICAL।
- Deletion: নতুন অনুরোধ DRAFT, কে অনুরোধ করেছেন লেখা হয়; স্ট্যাটাস Draft/Pending/Approved/Rejected/Cancelled।

## ৬. API ও পারমিশন

`/erp/rule-violations` (দেখা `audit.log.view`, বদলানো `admin.user.manage`), `/erp/broadcasts`, `/erp/deletion-requests` (`settings.company.manage`); `?filter[column]=value` দিয়ে গ্রুপ-ট্যাবের তালিকা।

## ৭. Frontend পেজ (লাইট থিম)

Rule Violation Log: Records | Rules Violation (বাঁয়ে Rule Scopes সংখ্যাসহ, ডানে Raised Violations) | Record violation। Global Notification Broadcast: Records | Send to all। Document Deletion Console: Records | Deletion Admin Panel (বাঁয়ে Deletion Type) | Raise deletion request।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (চারটা স্ক্রিনই):

**Backend**
- `app/Modules/Erp/Support/ResourceDefinition.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Erp/Http/Controllers/Api/ResourceApiController.php`
- `app/Modules/Erp/Routes/api.php`
- `app/Modules/Identity/Database/Migrations/2026_10_22_000200_create_access_configuration_tables.php`
- `app/Modules/Identity/Models/RuleViolation.php`
- `app/Modules/Identity/Models/NotificationBroadcast.php`
- `app/Modules/Identity/Models/DeletionRequest.php`
- `app/Modules/Identity/Resources/RuleViolationResource.php`
- `app/Modules/Identity/Resources/NotificationBroadcastResource.php`
- `app/Modules/Identity/Resources/DeletionRequestResource.php`
- `app/Modules/Notification/Models/Notification.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Migrations/2026_12_26_000100_extend_access_mappings_and_tna_events.php` (TNA)
- `app/Modules/Identity/Http/Controllers/Api/TnaEventApiController.php`
- `app/Modules/Identity/Routes/api.php`
- `app/Modules/Merchandise/Models/TnaTask.php`, `TnaEventUser.php`, `TnaEventInstance.php`

**Frontend**
- `frontend/src/app/(app)/access-control/[tab]/tna-screen.js`
- `frontend/src/components/access/tna-event-tabs.jsx`
- `frontend/src/components/erp/grouped-records.jsx`
- `frontend/src/app/(app)/access-control/[tab]/config-screens.js`
- `frontend/src/app/(app)/access-control/[tab]/form-actions.js`
- `lang/en/access.php`
- `lang/bn/access.php`
- `lang/en/notification.php`
- `lang/bn/notification.php`

**Tests**
- `tests/Feature/Identity/AccessConfigurationResourcesTest.php`
- `tests/Feature/Identity/AccessBuyerMappingTest.php` (TNA)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

`tests/Feature/Identity/AccessConfigurationResourcesTest.php`: অজানা নিয়ম বাতিল আর কে তুলেছেন; broadcast একবারই সঠিক প্রাপকদের (All/Role), নিষ্ক্রিয় হলে কাউকে নয়; deletion-এর বাধ্যতামূলক মেসেজ আর অনুরোধকারী; গেট আর অজানা resource 404।

`AccessBuyerMappingTest::test_tna_events_their_instances_and_who_is_notified`:
- নতুন event-এর বার্তা ("Event Code is required." …), একই কোড আটকায়, নিজে নিজের parent হতে পারে না।
- নতুন instance event-এর শুরুর অবস্থা নেয় (In Progress); Completed করলে আজকের তারিখ actual date হয়; reference ছাড়া আটকায়।
- Mapped User List সেভ; Records-এ ক্রম, parent, Raised সংখ্যা, R/B; storekeeper লিখতে পারেন না।

ব্রাউজার (dev): TNA-030 "Lab Dip Approval (Dyeing)" তৈরি (parent Rcv Order Confirm, Jashim Uddin, In Progress, −95, R B); instance HM-1025 তোলা → Completed (2026-10-24); Mapped User Jashim Uddin; Records-এ Raised 1; `?tab=edit&id=TNA-030`-এ সব মান আসে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**আংশিক সম্পন্ন (২০২৬-১০-১০):** Rule Violation Log, Global Notification Broadcast, Document Deletion Console (স্ক্রিন-কিটের resource হিসেবে)।
- **Rule Violation Log:** লঙ্ঘন হাতে লেখা যায়। প্রতিটা মডিউলের নিজের নিয়ম-চেক (যেমন রিকুইজিশনের বেশি PO) ম্যাপ করা ইউজার উপেক্ষা করলে এখানে লিখবে। সেটা ওই মডিউলগুলো বানানোর সময় যুক্ত হবে (User Rule Mapping-এর `allows` দিয়ে)।
- **Global Notification Broadcast:** নতুন নোটিফিকেশন ইভেন্ট `GLOBAL_BROADCAST`; বেল-আইকনে আর নোটিফিকেশন তালিকায় আসে।
- **Document Deletion Console:** অনুরোধ আর সিদ্ধান্তের রেজিস্টার। আসল মোছা আর Approval Engine-এর অনুমোদন সংশ্লিষ্ট ডকুমেন্টের মডিউল (PR/PO/MRR …) তৈরি হলে যুক্ত হবে।
- **বাকি:** TNA Task Status Setup, Merchandise Desk-এর TNA Template-এর সাথে।

**TNA Task Status Setup সম্পন্ন (২০২৬-১০-২৪):**
- **একই event, আলাদা টেবিল নয়:** Brickland-এর তালিকায় Merchandise-এর TNA task (MER-…) আর TNA-0xx একসাথে আছে। তাই এটা Merchandise-এর `tna_tasks`-এর ওপরেই চলে।
  - বাড়তি কলাম: task_message, parent_id, section_id (departments), assign_to, execution_by, default_status, is_repeat, is_buyer_feedback।
  - Status Order = sort_order, Offset Days = offset_days, Is Active = active।
- **নতুন টেবিল:** `tna_event_users` (event × ইউজার, কাকে জানানো হবে), `tna_event_instances` (style reference, owner, plan / actual date, অবস্থা)।
- **ট্যাব:**
  - Records: Edit দিলে কোড দিয়ে "Edit TNA event" ট্যাব খোলে (`?tab=edit&id=TNA-030`), ULID নয়।
  - TNA Task Status: বাঁয়ে event আর Raised সংখ্যা; ডানে Raise instance (Style, Owner, Plan Date), অবস্থা বদল, Revert / Save।
  - Mapped User List: event বেছে ইউজারে টিক (Name, User ID, Department)।
  - New TNA event: Brickland-এর সব ঘর।
- **পারমিশন:** দেখতে `merchandise.tna.view`, লিখতে `merchandise.tna.manage`।
- **প্রোব:** `tools/erp_crawler/plans/ac01.json`–`ac04.json`।
- **বাকি:** কোনো event তোলা হলে Mapped User-দের নোটিফিকেশন পাঠানো (Notification-এর সাথে পরে)। Merchandise-এর TNA Follow-up এখনো instance তৈরি করে না।
