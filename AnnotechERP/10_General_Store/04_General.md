# General

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/04`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | New Requisition | `/m/general-store/gs-req-new` | 1 | 5 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### New Requisition

`/m/general-store/gs-req-new`

**ভিউ**
> 16 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reason | textarea |  |  |
| Required Qty* | number | ✔ |  |
| Remarks | text |  |  |
- গ্রিড কলাম: REQ. NO., DATE, DEPARTMENT, STORE, ITEMS, REQUESTED, APPROVED, ISSUED, REQUESTED BY, STATUS
- সেকশন: New Requisition, My Requisitions (0)
- বাটন/অ্যাকশন: Select…, Add Item, Save Draft, Submit

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reason | textarea |  |  |
| Required Qty* | number | ✔ |  |
| Remarks | text |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- Requisition-এর পুরো ব্যবস্থা 10/05-এর সাথে একই (`GsRequisitionApiController`, `requisition.jsx`); বিস্তারিত নিয়ম ও টেবিল [Requisition](05_Requisition.md)-এ।

## ৪. ডাটা মডেল (Entity ও Field)

`gs_requisitions` আর `gs_requisition_lines` ([Requisition](05_Requisition.md) দেখুন)।

## ৫. Workflow / Status ও Business Rules

- **ফর্ম:** "New Requisition", নিচে "Department → Item → Required Quantity → Reason → Submit"।
  - Date, Department (বাধ্যতামূলক), Store (বাধ্যতামূলক, ডিফল্ট স্টোর আগে থেকে), Reason।
  - লাইনে Item n (বাধ্যতামূলক), Required Qty (বাধ্যতামূলক, ঘরে একক লেখা), Remarks, আর মুছে ফেলার আইকন। আইটেম বাছলে নিচে "ITM-022 · BOX · in store 0 BOX"।
  - Add Item, Save Draft, Submit।
- **বার্তা:** আইটেম না দিলে "Pick at least one item."; পরিমাণ না দিলে "Line 1: Required Qty must be a number greater than zero."; Department না দিলে "Department is required."।
- **খসড়া:** Save Draft-এ DRAFT। "My Requisitions"-এ খসড়ার সারিতে View, Edit, Submit আর Cancel (আইকন)।
  - Edit চাপলে ফর্মটাই "Edit Draft Requisition" হয়ে যায়, ডানে "Stop editing"; আবার Save Draft বা Submit।
  - শুধু নিজের খসড়া বদলানো যায়।
- **Submit:** PENDING, Store Manager-এর অনুমোদনের অপেক্ষায়। Settings-এ অনুমোদন বন্ধ থাকলে সঙ্গে সঙ্গে চাওয়া পরিমাণে APPROVED হয় (ফর্মে নীল লেখায় তা জানানো থাকে)।
- **My Requisitions (n):** যিনি দেখছেন তাঁর নিজের সব requisition। কলাম: Req. No., Date, Department, Store, Items, Requested, Approved, Issued, Requested By, Status। অবস্থা অনুযায়ী কাজ: Pending-এ View, Print, Cancel; Approved / Partially Issued-এ View, Print, Issue (যাঁর Issue করার অনুমতি আছে)।

## ৬. API ও পারমিশন

- `GET /general-store/requisitions/options`, `GET /requisitions?list=mine`, `POST /requisitions`, `PUT /requisitions/{no}`, `POST /{no}/submit`, `POST /{no}/cancel`।
- পারমিশন `store.requisition.create`।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/general-store/gs-req-new`, সাইডবারে General Store → GENERAL → New Requisition।
- ফর্ম আর তালিকা একই পাতায়; Edit, Submit, Cancel কোনোটাতে পাতা রিলোড হয় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Http/Controllers/Api/GsRequisitionApiController.php`
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (GENERAL_STORE.GENERAL, REQ_NEW)

**Frontend**
- `frontend/src/components/generalstore/requisition.jsx` (`NewRequisition`)
- `frontend/src/app/(app)/general-store/[screen]/screens.js`
- `lang/en/store.php`, `lang/bn/store.php` (`req.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreRequisitionTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- [Requisition](05_Requisition.md)-এর টেস্ট আর ব্রাউজার যাচাই দেখুন (ফর্মের বার্তা, খসড়া, Edit, Submit, অনুমোদন বন্ধ থাকলে নিজে অনুমোদন)।
- ব্রাউজারে ফাঁকা Submit-এ "Pick at least one item." আর Edit-এর রূপ দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs08.json`, `gs11.json`, `gs12.json`।
