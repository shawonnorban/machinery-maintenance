# Request Inbox

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টা স্ক্রিন; New Request, বিস্তারিত আর সিদ্ধান্তসহ)
> **মডিউল:** [Approval Center](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `04/01`

**⬅ ফিরে যান:** [Approval Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | All Requests | `/m/approval-center/all-requests` | 1 | 2 | 1 |
| 2 | Pending Requests | `/m/approval-center/pending-requests` | 1 | 2 | 0 |
| 3 | My Submitted Requests | `/m/approval-center/my-submitted-requests` | 1 | 2 | 1 |
| 4 | Assigned to Me | `/m/approval-center/assigned-to-me` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### All Requests

`/m/approval-center/all-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: New Request, Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status

**ডায়ালগ: New Request** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Priority* | select | ✔ | Select…, Low, Medium, High, Urgent |
| Title | text |  |  |
| Reference type | text |  |  |
| Reference No.* | text | ✔ |  |
| PO No. | text |  |  |
| Quantity | number |  |  |
| Unit | select |  | Select…, KG, PCS, YDS, MTR, DZN, SET, LBS, CONE |
| Amount | number |  |  |
| Currency | select |  | Select…, USD, BDT, EUR, GBP |
| Due date | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Choose a request type.

#### Pending Requests

`/m/approval-center/pending-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status

#### My Submitted Requests

`/m/approval-center/my-submitted-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: New Request, Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status

**ডায়ালগ: New Request** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Priority* | select | ✔ | Select…, Low, Medium, High, Urgent |
| Title | text |  |  |
| Reference type | text |  |  |
| Reference No.* | text | ✔ |  |
| PO No. | text |  |  |
| Quantity | number |  |  |
| Unit | select |  | Select…, KG, PCS, YDS, MTR, DZN, SET, LBS, CONE |
| Amount | number |  |  |
| Currency | select |  | Select…, USD, BDT, EUR, GBP |
| Due date | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Choose a request type.

#### Assigned to Me

`/m/approval-center/assigned-to-me`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Request To, Date, Status
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `app/Modules/Approval`:
  - `RequestApproval` (chain তৈরি, প্রথম ধাপকে নোটিফিকেশন)।
  - `DecideApproval` (approve/reject/cancel/expire, নিজের request নিজে অনুমোদন নিষেধ, ধাপ-যোগ্যতা যাচাই)।
  - `ApprovalEntities` (entity_type → মডেল), `Approvable` contract।
- `approval_requests.context_json`-এ amount আর currency জমে থাকে (`cost`)। পরে amount বদলালেও আগের অনুমোদন বদলায় না।
- নোটিফিকেশন ইভেন্ট `APPROVAL_REQUESTED` আর `APPROVAL_DECIDED` ইতিমধ্যে আছে।
- Numbering ইঞ্জিন (`NumberSequenceGenerator`): request নম্বর `APR-…`।

## ৪. ডাটা মডেল (Entity ও Field)

**নতুন `approval_tickets`** (হাতে-লেখা request, entity type `MANUAL_REQUEST`):

| ফিল্ড | নোট |
|---|---|
| request_no | numbering থেকে, Submit করার সময় (Draft-এ খালি) |
| request_type_id | বাধ্যতামূলক, শুধু active type |
| priority | LOW / MEDIUM / HIGH / URGENT; ডিফল্ট type থেকে |
| title | খালি থাকলে "type নাম · reference no" |
| reference_type, reference_no* | |
| buyer_name, order_no, style_no, po_no | আপাতত লেখা (দেখুন নোট) |
| quantity, unit | unit: KG, PCS, YDS, MTR, DZN, SET, LBS, CONE |
| amount, currency | currency: USD, BDT, EUR, GBP; amount দিয়ে chain-এর band ঠিক হয় |
| request_to_user_id* | লেভেল ১-এর approver; এই কোম্পানির সক্রিয় সদস্য, নিজে নয় |
| due_date | খালি থাকলে type-এর due days থেকে (০ = কোনো সময়সীমা নেই) |
| remarks | |
| status | DRAFT / PENDING / APPROVED / REJECTED / RETURNED / CANCELLED |
| approval_status, approval_request_id | Approvable-এর নিয়মিত ঘর |
| requested_by, submitted_at | |

**`approval_requests`-এ নতুন কলাম:**
- `request_type_id`, `priority`, `due_at`, `request_to_user_id` (Request To লেভেল)।
- `workflow_id` nullable: "None — Request To only" type-এর কোনো chain নেই।
- status-এ নতুন মান `RETURNED`।

**`approval_actions`-এ নতুন কলাম:** `previous_status`, `new_status`, `level`। action-এ নতুন মান `SUBMITTED`, `RETURNED`, `RESUBMITTED`।

## ৫. Workflow / Status ও Business Rules

- **Save draft:** কোনো যাচাই নেই শুধু type ছাড়া। নম্বর বসে না, কাউকে জানানো হয় না, শুধু নিজে দেখে।
- **Submit:**
  - সব বাধ্যতামূলক ঘর লাগে।
  - নম্বর বসে।
  - chain তৈরি হয়: লেভেল ১ = Request To, তারপর type-এর chain-এর যেসব approval ধাপ (level ≥ ১, active) amount-এর band-এ পড়ে।
  - `approval_request` তৈরি হয় (`context_json`: amount, currency, priority)।
  - লেভেল ১-কে `APPROVAL_REQUESTED` নোটিফিকেশন যায়।
- **কে কাজ করতে পারে:**
  - Request To লেভেলে শুধু ওই ইউজার।
  - chain-এর ধাপে: ধাপে নির্দিষ্ট ইউজার বসানো থাকলে শুধু তারাই। না থাকলে আগের মতো role, user বা team।
  - নিজের request কেউ অনুমোদন করতে পারে না (আগের নিয়ম)।
- **Approve:** শেষ লেভেল হলে APPROVED, না হলে পরের লেভেলে যায় আর তাকে নোটিফিকেশন যায়।
- **Reject:** কারণ বাধ্যতামূলক। পুরো request REJECTED হয়, শেষ।
- **Return for revision:**
  - কারণ বাধ্যতামূলক। এই approval_request RETURNED হয়, ticket RETURNED হয়, requester-কে জানানো হয়।
  - requester বদলে আবার Submit করলে **নতুন** approval_request তৈরি হয়, chain আবার লেভেল ১ থেকে শুরু। পুরনোটা ইতিহাসে থাকে (`RESUBMITTED`)।
- **Cancel:** requester নিজের PENDING বা RETURNED request বাতিল করতে পারে।
- **Draft মোছা:** শুধু DRAFT, শুধু নিজের।
- **Overdue:** `due_at` পেরিয়েছে আর এখনো PENDING।
- **Escalation (ধাপ C):** type-এ escalate_to আর escalate_after_hours থাকলে, due-র পর তত ঘণ্টা গেলে escalate_to-কে নোটিফিকেশন যায় (একবার, `escalated_at`)।

**তালিকাগুলো কী দেখায় (দেখার অধিকার):**
- `approval.request.see_all` থাকলে (Owner/Admin) সব request।
- না থাকলে শুধু যেগুলো সে করেছে, যেগুলোয় এখন তার পালা, অথবা যেগুলোয় সে আগে কাজ করেছে।

| স্ক্রিন | ফিল্টার |
|---|---|
| All Requests | দেখার অধিকারের সব (নিজের Draft সহ) |
| Pending Requests | status PENDING |
| My Submitted Requests | requested_by = আমি (Draft সহ) |
| Assigned to Me | PENDING আর এখন আমার পালা |

ওপরের কার্ড: In this list, Pending, Overdue, Urgent & open। সার্চ: Request ID, buyer, order, style, PO, reference, requester। Filters: type, status, priority, তারিখ।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET | `/api/v1/approval-center/requests?scope=all\|pending\|mine\|assigned\|approved\|rejected\|returned&q=&type=&status=&priority=&from=&to=` | লগইন করা (দেখার নিয়মে ছাঁকা) |
| GET | `/api/v1/approval-center/requests/{id}` | একই |
| POST | `/api/v1/approval-center/tickets` (`submit: true\|false`) · PATCH `/tickets/{id}` · DELETE `/tickets/{id}` | `approval.ticket.create` |
| POST | `/tickets/{id}/submit` · `/tickets/{id}/cancel` | নিজের |
| POST | `/approval-center/requests/{id}/approve` · `/reject` · `/return` | ধাপের যোগ্যতা (আগের নিয়ম) |
| GET | `/approval-center/form-options` (type, ইউজার, unit, currency) | `approval.ticket.create` |

নতুন পারমিশন: `approval.ticket.create` (সবাই, read-only ছাড়া), `approval.request.see_all` (Owner/Admin), `approval.type.manage` (Owner/Admin)।

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Approval/Database/Migrations/2026_10_25_000100_create_approval_center_tables.php`
- `app/Modules/Approval/Models/ApprovalTicket.php`
- `app/Modules/Approval/Models/ApprovalRequestType.php`
- `app/Modules/Approval/Models/ApprovalRuleUser.php`
- `app/Modules/Approval/Models/ApprovalRequest.php`
- `app/Modules/Approval/Models/ApprovalAction.php`
- `app/Modules/Approval/Models/ApprovalRule.php`
- `app/Modules/Approval/Models/ApprovalWorkflow.php`
- `app/Modules/Approval/Actions/ManageApprovalTicket.php`
- `app/Modules/Approval/Actions/RequestApproval.php`
- `app/Modules/Approval/Actions/DecideApproval.php`
- `app/Modules/Approval/Services/ApprovalInbox.php`
- `app/Modules/Approval/Support/ApprovalEntities.php`
- `app/Modules/Approval/Http/Controllers/Api/ApprovalCenterApiController.php`
- `app/Modules/Approval/Http/Controllers/Api/ApprovalTicketApiController.php`
- `app/Modules/Approval/Routes/api.php`
- `app/Modules/Settings/Services/NumberSequenceGenerator.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/app/(app)/approval-center/[screen]/page.js`
- `frontend/src/app/(app)/approval-center/[screen]/screens.js`
- `frontend/src/app/(app)/approval-center/[screen]/actions.js`
- `frontend/src/components/approval/request-list.jsx`
- `frontend/src/components/approval/request-detail.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/ui/modal.jsx`
- `lang/en/approval.php`
- `lang/bn/approval.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Approval/ApprovalCenterTest.php`
- `tests/Feature/Identity/RoleMatrixTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব তৈরি হয়ে গেছে, ওপরের As-built দেখুন।_

## ৯. টেস্ট ও যাচাই

- `ApprovalCenterTest` ৭/৭, এর মধ্যে এই অংশের যাচাই:
  - **Request To আর chain:** Draft-এ নম্বর বসে না, শুধু লেখক দেখে। Submit-এ Request To লাগে, নিজেকে দেওয়া যায় না, আর নম্বর `APR-…` বসে। লেভেল ১ Request To, তারপর amount-এর band-এ পড়া chain-এর ধাপ। লেভেল ০-র কাজের ধাপ গোনা হয় না। নির্দিষ্ট ইউজার বসানো ধাপে শুধু তারাই কাজ করতে পারে।
  - **ছোট অঙ্ক আর return:** band-এর নিচের অঙ্ক chain এড়িয়ে যায়। return-এ কারণ লাগে। আবার জমা দিলে নতুন রাউন্ড হয়, নম্বর একই থাকে, তালিকায় একবারই দেখায়, আর ইতিহাসে SUBMITTED → RETURNED → RESUBMITTED থাকে।
  - **দেখার অধিকার:** যার সাথে সম্পর্ক নেই সে কিছুই দেখে না (404)। Owner সব দেখে। প্রত্যাহারের পরে আর সিদ্ধান্ত দেওয়া যায় না (409)।
- ব্রাউজার (dev), দুজন ইউজার দিয়ে:
  - Owner New Request ভরে জমা দিলেন, Request To = ম্যানেজার।
  - ম্যানেজার Assigned to Me থেকে খুলে কারণসহ Return দিলেন।
  - Owner Edit & resubmit করলেন, ম্যানেজার Approve করলেন।
  - Approved, History আর Dashboard-এ ফল এসেছে। কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **একটাই ইনবক্স:** হাতে-লেখা request আর মডিউলের document (WO, PR, PO …) একই তালিকায় আসে। `ApprovalInbox` দেখার নিয়ম মেনে সারি বানায়।
  - কে দেখে: view_any থাকলে সব। না থাকলে যা সে করেছে, যা তাকে পাঠানো (Request To), যা এখন তার পালা, আর যাতে সে আগে কাজ করেছে।
- **Request To = request-এর নিজস্ব একটা লেভেল-১ ধাপ** (DB-তে কোনো সারি নেই)। `ApprovalRequest::applicableRules()` একে chain-এর আগে বসায়, তাই বিদ্যমান `DecideApproval`-ই সব যাচাই করে।
- **ইঞ্জিনে বদল:**
  - ধাপে নির্দিষ্ট ইউজার বসানো থাকলে (Action Assignment) শুধু তারাই, না থাকলে আগের মতো role, user বা team।
  - পরের লেভেলকে নোটিফিকেশন যায় (আগে শুধু প্রথম লেভেল পেত)।
  - প্রতিটা action-এ পূর্ব → নতুন অবস্থা আর লেভেল লেখা হয়।
  - level ০ আর inactive ধাপ অনুমোদন চায় না।
- **ফর্ম:** EntryForm-এ দ্বিতীয় সাবমিট বাটন (`secondaryLabel`, Save draft)। আর একটা সাধারণ বাগ ঠিক হয়েছে: লম্বা Modal স্ক্রিন থেকে বেরিয়ে যেত, Submit-এ পৌঁছানো যেত না। এখন Modal নিজে স্ক্রল হয় (সব ডায়ালগে প্রযোজ্য)।
- **তালিকা:** Brickland-এর মতো একটাই সার্চ (`RecordsGrid searchable={false}`) আর Filters প্যানেল। ফিল্টার url-এ থাকে।
- **পথে ঠিক করা:**
  - dev-এ lazy loading বন্ধ, টেস্টে চালু, তাই কয়েকটা relation-এর লোড টেস্টে ধরা পড়েনি। explicit query দিয়ে ঠিক করা হয়েছে।
  - একই মিলিসেকেন্ডের দুটো সিদ্ধান্তে "শেষ জন" এলোমেলো আসছিল। এখন id দিয়ে tie ভাঙা হয়।
  - role-এর কাঁচা কোডের বদলে বিবরণ দেখানো হয়।
- **বাকি:**
  - Buyer, Order আর Style আপাতত লেখার ঘর। Merchandise মাস্টার এলে select হবে।
  - মডিউলের document-এ Return আসবে নিজ নিজ মডিউল নতুন করে তৈরির সময়।
  - পুরনো `/approvals` পাতা সাইডবার থেকে লুকানো। work order-এর নোটিফিকেশন লিংক এখনো সেখানে যায়।