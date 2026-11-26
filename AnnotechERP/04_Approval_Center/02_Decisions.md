# Decisions

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টা স্ক্রিন)
> **মডিউল:** [Approval Center](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `04/02`

**⬅ ফিরে যান:** [Approval Center](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Approved Requests | `/m/approval-center/approved-requests` | 1 | 2 | 0 |
| 2 | Rejected Requests | `/m/approval-center/rejected-requests` | 1 | 2 | 0 |
| 3 | Returned / Revision Requests | `/m/approval-center/returned-requests` | 1 | 2 | 0 |
| 4 | Approval History | `/m/approval-center/approval-history` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Approved Requests

`/m/approval-center/approved-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Approved by, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Approved by, Date, Status

#### Rejected Requests

`/m/approval-center/rejected-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Rejected by, Rejection reason, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Rejected by, Rejection reason, Date, Status

#### Returned / Revision Requests

`/m/approval-center/returned-requests`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requests | text |  | Request ID, buyer, order, style, PO, reference, requester… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Returned by, Revision reason, Date, Status, ACTION
- সেকশন: Requests
- বাটন/অ্যাকশন: Search, Filters, Request ID, Type, Requested By, Buyer · Order · Style, Qty / Amount, Returned by, Revision reason, Date, Status

#### Approval History

`/m/approval-center/approval-history`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Request ID, person or comment |
| From | date |  |  |
| To | date |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date & time, Request ID, Action, Action by, Previous → New, Level, COMMENT
- সেকশন: Approval history
- বাটন/অ্যাকশন: All actions, Search, Date & time, Request ID, Action, Action by, Previous → New, Level
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

`approval_actions` প্রতিটা সিদ্ধান্ত সময়সহ রাখে, কখনো বদলায় না। Approval History এরই তালিকা।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই। `approval_actions`-এ `previous_status`, `new_status`, `level` (দেখুন [01](01_Request_Inbox.md))।

## ৫. Workflow / Status ও Business Rules

| স্ক্রিন | কী দেখায় | বিশেষ কলাম |
|---|---|---|
| Approved Requests | APPROVED | Approved by (শেষ অনুমোদনকারী) |
| Rejected Requests | REJECTED | Rejected by, Rejection reason |
| Returned / Revision Requests | RETURNED | Returned by, Revision reason |
| Approval History | প্রতিটা action, নতুনগুলো আগে | Date & time, Request ID, Action, Action by, Previous → New, Level, Comment |

- দেখার নিয়ম [01](01_Request_Inbox.md)-এর মতোই। History-তে শুধু সেইসব request-এর action, যেগুলো সে দেখতে পারে।
- History বদলানো বা মোছা যায় না (কোনো API নেই)।
- Approval History-র ফিল্টার: সার্চ (Request ID, ব্যক্তি, মন্তব্য), Action, From, To।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET | `/api/v1/approval-center/requests?scope=approved\|rejected\|returned` | লগইন করা |
| GET | `/api/v1/approval-center/history?q=&action=&from=&to=` | লগইন করা (দেখার নিয়মে ছাঁকা) |

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Approval/Services/ApprovalInbox.php`
- `app/Modules/Approval/Http/Controllers/Api/ApprovalCenterApiController.php`
- `app/Modules/Approval/Models/ApprovalAction.php`

**Frontend**
- `frontend/src/app/(app)/approval-center/[screen]/screens.js`
- `frontend/src/components/approval/request-list.jsx`
- `frontend/src/components/erp/filter-bar.jsx`
- `lang/en/approval.php`
- `lang/bn/approval.php`

**Tests**
- `tests/Feature/Approval/ApprovalCenterTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_সব তৈরি হয়ে গেছে, ওপরের As-built দেখুন।_

## ৯. টেস্ট ও যাচাই

- `ApprovalCenterTest`: Approved তালিকায় শেষ অনুমোদনকারী, Returned তালিকায় কারণ, History-তে action ফিল্টার (REJECTED, মন্তব্যসহ), আর একাধিক রাউন্ডের ইতিহাস।
- ব্রাউজার: Approval History-তে Submitted → Returned (মন্তব্যসহ) → Resubmitted → Approved, পূর্ব → নতুন অবস্থা আর লেভেলসহ। একই তালিকায় আগের একটা work order-এর অনুমোদনও এসেছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-১০):**
- **তিনটা সিদ্ধান্ত-তালিকা** ইনবক্সের একই কম্পোনেন্ট (`RequestList`) ব্যবহার করে, শুধু scope আলাদা। কলাম বদলায়: Approved by / Rejected by + কারণ / Returned by + কারণ।
- **Approval History:**
  - একাধিক রাউন্ডের হাতে-লেখা request-এর আগের রাউন্ডের action-ও ইতিহাসে থাকে, একই request নম্বরে।
  - বদলানো বা মোছার কোনো API নেই।