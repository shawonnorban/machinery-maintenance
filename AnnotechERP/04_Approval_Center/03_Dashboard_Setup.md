# Dashboard & Setup

> **স্ট্যাটাস:** ✅ সম্পন্ন (২টা স্ক্রিন; escalation কমান্ডসহ)
> **মডিউল:** [Approval Center](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `04/03`

**⬅ ফিরে যান:** [Approval Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Request Dashboard | `/m/approval-center/request-dashboard` | 1 | 0 | 0 |
| 2 | Request Types & Routing | `/m/approval-center/request-types` | 1 | 2 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Request Dashboard

`/m/approval-center/request-dashboard`

**ভিউ**
- সেকশন: Requests by module, Requests by status, Requests by priority, Requests by buyer, Requests by department, Requests by requester, Requests by approver, Rejected request analysis, Revision request analysis, Approval turnaround time, Overdue request analysis

#### Request Types & Routing

`/m/approval-center/request-types`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Request types | search |  | Search request types… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request type, Module, Default priority, Due days, Approval chain, Escalation, Requests, Status
- সেকশন: Request types
- বাটন/অ্যাকশন: New type, Request type, Module, Default priority, Due days, Approval chain, Escalation, Requests, Status

**ডায়ালগ: New type** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Name* | text | ✔ |  |
| Module* | text | ✔ |  |
| Default priority | select |  | Select…, Low, Medium, High, Urgent |
| Due days | number |  |  |
| Sort order | number |  |  |
| Approval chain | select |  | None — Request To only, AT-001 — Dyes/Chem Requisition Workflow (Transfer & Loan), AT-002 — Yarn Requisition for Bulk (e |
| Escalate after (hours past due) | number |  |  |
| Description | textarea |  |  |
- খালি Save-এ মেসেজ: Code is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

`approval_workflows` আর `approval_rules` (chain)। Request type-এর "Approval chain" select-এ এগুলোই, Approval Workflow Designer-এ বানানো।

## ৪. ডাটা মডেল (Entity ও Field)

**নতুন `approval_request_types`:**

| ফিল্ড | নোট |
|---|---|
| code* | কোম্পানিতে unique, বড় হাতে (FABRIC-BOOKING) |
| name* | |
| module* | কোন মডিউলের (Material Planning, Purchase …), লেখা |
| default_priority | ডিফল্ট MEDIUM |
| due_days | ০ = কোনো সময়সীমা নেই |
| sort_order | |
| workflow_id | null = "None — Request To only" |
| escalate_to_user_id | null = "No escalation" |
| escalate_after_hours | ডিফল্ট ২৪ |
| description, active | inactive type New Request-এ দেখা যায় না |

**শুরুর ডাটা (সিডার):** Brickland-এর মতো ১৪টা type। FABRIC-BOOKING, ACCESSORIES-BOOKING, PURCHASE-REQUISITION, PURCHASE-ORDER, SAMPLE-APPROVAL, COSTING-APPROVAL, ORDER-CONFIRMATION, BUDGET-APPROVAL, LC-APPROVAL, PAYMENT-APPROVAL, PRODUCTION-APPROVAL, SHIPMENT-APPROVAL, MATERIAL-ISSUE, আর একটা GENERAL।

## ৫. Workflow / Status ও Business Rules

- **Request Types & Routing:**
  - grid: Request type (name + code), Module, Default priority, Due days, Approval chain, Escalation ("নাম after N h"), Requests (সংখ্যা), Status।
  - কোনো request থাকলে type মোছা যায় না, শুধু inactive করা যায়।
- **Request Dashboard:**
  - **টাইল:** Total requests, Pending, Pending on me, Approved, Rejected, Returned / revision, Overdue, Today's requests, This month, Avg. turnaround।
  - **বিশ্লেষণ:** module, status, priority, buyer, department, requester আর approver অনুযায়ী সংখ্যা; Rejected analysis (কারণ, সবচেয়ে বেশি আগে); Revision analysis; Approval turnaround (type অনুযায়ী, সবচেয়ে ধীর আগে); Overdue analysis (সবচেয়ে পুরনো due আগে)।
  - দেখার নিয়ম ইনবক্সের মতো।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET/POST/PATCH/DELETE | `/api/v1/erp/approval-request-types[/{id}]` (স্ক্রিন-কিট resource) | দেখা: লগইন করা · লেখা: `approval.type.manage` |
| GET | `/api/v1/approval-center/dashboard` | লগইন করা |

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Approval/Models/ApprovalRequestType.php`
- `app/Modules/Approval/Resources/ApprovalRequestTypeResource.php`
- `app/Modules/Approval/Support/RequestTypeDefaults.php`
- `app/Modules/Approval/Services/ApprovalDashboard.php`
- `app/Modules/Approval/Console/EscalateOverdueRequests.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Notification/Models/Notification.php`
- `routes/console.php`

**Frontend**
- `frontend/src/components/approval/type-manager.jsx`
- `frontend/src/components/approval/request-dashboard.jsx`
- `frontend/src/app/(app)/approval-center/[screen]/screens.js`
- `lang/en/approval.php`
- `lang/bn/approval.php`
- `lang/en/notification.php`
- `lang/bn/notification.php`

**Tests**
- `tests/Feature/Approval/ApprovalCenterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব তৈরি হয়ে গেছে, ওপরের As-built দেখুন।_

## ৯. টেস্ট ও যাচাই

- `ApprovalCenterTest`:
  - **Request types:** প্রথমবারে ১৪টা ডিফল্ট তৈরি হয়। code ছোট-বড় হাতের অক্ষর নির্বিশেষে unique। store manager type বদলাতে পারে না।
  - **Dashboard:** শুধু যা দেখার অধিকার আছে তা গোনে। কারণ-বিশ্লেষণ কাজ করে। সম্পর্কহীন ইউজারের মোট ০।
  - **Escalation:** সময় পেরোনো request escalate_to ব্যক্তিকে একবারই জানানো হয়, দুবার চালালেও।
  - **Chain-এর গঠন:** অপেক্ষমাণ request থাকলে ধাপ যোগ করা যায় না, নাম বদলানো যায়। অন্য কোম্পানির ইউজার বসানো যায় না।
- ব্রাউজার: Request Types (১৪টা) আর Dashboard (১০টা টাইল, বিশ্লেষণ, turnaround) দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **Request Types:** স্ক্রিন-কিটের resource (`/erp/approval-request-types`)। Brickland-এর ১৪টা ডিফল্ট প্রতিটা কোম্পানির জন্য প্রথম ব্যবহারে তৈরি হয় (`RequestTypeDefaults::ensure`), তারপর সেগুলো কোম্পানির নিজের।
- **Dashboard (`ApprovalDashboard`):** ইনবক্সের সারির ওপরেই হিসাব করে, তাই তালিকায় যা দেখা যায় না তা ড্যাশবোর্ডেও আসে না। এক ঘণ্টার কম সময় মিনিটে দেখায়।
- **Escalation:** `approval:escalate-overdue` চলে প্রতি ১৫ মিনিটে। নতুন নোটিফিকেশন ইভেন্ট `APPROVAL_OVERDUE`, `escalated_at` দিয়ে একবারই পাঠানো হয়।
- **Approval Workflow Designer** (Access Control 02/03) একসাথে তৈরি হয়েছে। বিস্তারিত [03_Configuration](../02_Access_Control/03_Configuration.md)-এ।