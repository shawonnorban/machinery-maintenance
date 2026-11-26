# Accessories Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন; Brickland-এ Scaffold থাকা Return Register-ও পুরো বানানো)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/08`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Trims & Accessories Receipt | `/m/garments/trims-accessories-receive` | 2 | 21 | 1 |
| 2 | Trims & Accessories Issue | `/m/garments/trims-accessories-issue` | 2 | 16 | 1 |
| 3 | Trims Receipt Return Register | `/m/garments/trims-accessories-receive-return-list` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Trims & Accessories Receipt

`/m/garments/trims-accessories-receive`

**ট্যাব: MRR List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | text |  | MRR-… |
| MRR From Date | date |  |  |
| MRR To Date | date |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Payment Method | select |  | Cash, Lc, Cheque, Tt, Consignment |
| PR | text |  |  |
| PO | text |  |  |
| Status | select |  | Draft, Completed, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: MRR List 0, Daily Trims Receive, -- Select Store --, -- Select Supplier --, -- Select Req Type --, Search, Add New, Refresh

**ট্যাব: Daily Trims Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing trims receive | select |  |  |
| MRR No | text |  | [Auto Gen] |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| PR | text |  |  |
| PO | text |  |  |
| Remarks | textarea |  |  |
| Description | text |  |  |
| UoM | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| Line Remarks | text |  |  |
- সেকশন: Daily Trims Receive, Key Information, Against, Notes, Receive Detail
- বাটন/অ্যাকশন: MRR List 0, Daily Trims Receive, -- Select Store --, -- Select Supplier --, -- Select Req Type --, Add to Grid, Cancel, -- Select Item --, Back To List, Clear, Save MRR

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing trims receive | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| PR | text |  |  |
| PO | text |  |  |
| Remarks | textarea |  |  |
| Description | text |  |  |
| UoM | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| Line Remarks | text |  |  |
- খালি Save-এ মেসেজ: MRR Date is required.

#### Trims & Accessories Issue

`/m/garments/trims-accessories-issue`

**ট্যাব: Issue List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Issue From | date |  |  |
| Issue To | date |  |  |
| Status | select |  | Draft, Submitted, Approve, Rejected |
- সেকশন: Search
- বাটন/অ্যাকশন: Issue List 0, Trims/Accessories Issue, -- Store --, Show, Add New, Refresh

**ট্যাব: Trims/Accessories Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing trims issue | select |  |  |
| Iss Ref# | text |  | [Auto Gen] |
| Issue Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Requisition# | text |  |  |
| Order/Style | select |  | 529 — LPP-2026-000008 [529], 1111 — LPP-2026-000007 [1111], 529 — LPP-2026-000006 [529], 234234 — LPP-2026-000005 [23423 |
| Remarks | textarea |  |  |
| Description | text |  |  |
| UoM | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| Line Remarks | text |  |  |
- সেকশন: Trims/Accessories Issue, Key Information, Against, Notes, Issue Detail
- বাটন/অ্যাকশন: Issue List 0, Trims/Accessories Issue, -- Store --, -- Select --, Add to Grid, Cancel, -- Select Item --, Back To List, Clear, Save Issue

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing trims issue | select |  |  |
| Iss Ref# | text |  |  |
| Issue Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Requisition# | text |  |  |
| Order/Style | select |  | 529 — LPP-2026-000008 [529], 1111 — LPP-2026-000007 [1111], 529 — LPP-2026-000006 [529], 234234 — LPP-2026-000005 [23423 |
| Remarks | textarea |  |  |
| Description | text |  |  |
| UoM | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| Line Remarks | text |  |  |
- খালি Save-এ মেসেজ: Issue Date is required.

#### Trims Receipt Return Register

`/m/garments/trims-accessories-receive-return-list`

**ভিউ**
> 35 of 54 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Company Management-এর `stores`:** Store বাছাই (dev-এ "Trims & accessories store")।
- **Material Procurement-এর `mp_items` (kind TRIMS) আর `mp_suppliers`:** আইটেম আর সাপ্লায়ার। 09 Store Material-এর Trims Warehouse-ও এই আইটেম মাস্টার ব্যবহার করে।
- **Merchandising-এর `merch_orders`:** Order/Style ("স্টাইল — অর্ডার [স্টাইল]", Brickland-এর মতো)। **Planning-এর `planning_lines`:** সুইং লাইন।
- **17/06 Dye House Chemical Store:** একই ধরনের MRR / Issue কাগজ। তার editor-এর অংশ (`Field`, `Group`, `EditorHead`, `LinesCard`, `usePaper`) আর `RecordsGrid` এখানেও ব্যবহার করা হয়েছে। `UomCodes` দিয়ে একক মাস্টার থেকে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_14_000100_create_asm_trims_store.php`।

- **`asm_trims_receipts`** (MRR-YYYYMM####): `mrr_date`, `store_id`, `supplier_id`, `req_type`, `pay_method` (CASH / LC / CHEQUE / TT / CONSIGNMENT), `pr_no`, `po_no`, `remarks`, `status` (DRAFT → COMPLETED, বা CANCELLED), `completed_at/by`।
- **`asm_trims_issues`** (ISS-YYYYMM####): `issue_date`, `store_id`, `requisition_no`, `order_id`, `line_id`, `remarks`, `status` (DRAFT → SUBMITTED → APPROVED বা REJECTED), `submitted_at/by`, `decided_at/by`, `reject_reason`।
- **`asm_trims_returns`** (RTN-YYYYMM####): `return_date`, `receipt_id`, `reason` (SHORT / DAMAGED / EXCESS), `remarks`, `status` (DRAFT → COMPLETED, বা CANCELLED)।
- **লাইন (তিনটারই):** `item_id` (mp_items, TRIMS), `description`, `uom`, `qty`, `rate`, `remarks`, `sort_order`। রিটার্নের লাইনে `receipt_line_id`।
- **`asm_trims_moves`** (স্টক): store + item + তারিখ + kind (RECEIPT / ISSUE / RETURN) + doc_no, `qty` (+ ইন, − আউট), `value`। স্টোরের স্টক = এগুলোর যোগফল।

## ৫. Workflow / Status ও Business Rules

- **প্রতিটা স্ক্রিন দুই ট্যাবে (পেজ রিলোড ছাড়াই বদলায়):** তালিকা (Searching Data ফিল্টার, Search, Refresh, Excel, সারির কাজ) আর এন্ট্রি (আগেরটা খোলার ড্রপডাউন, Key Information, Against, Notes, আইটেমের গ্রিড Add to Grid দিয়ে, Back To List, Clear, Save)।
- **নম্বর** প্রথম সেভে দেওয়া হয় ("[Auto Gen]"), কাগজের মাসের ক্রমিক। ড্রাফট যতবার খুশি বদলানো যায়; ড্রাফট না থাকলে শুধু পড়া যায়।
- **Trims & Accessories Receipt:** MRR Date, Store, Payment Method বাধ্যতামূলক; Supplier, Requisition Type, PR, PO ঐচ্ছিক। শুধু TRIMS আইটেম; পরিমাণ > ০। তালিকা থেকে Complete করলে স্টকে যোগ হয়; ড্রাফট Cancel করা যায়।
- **Trims & Accessories Issue:** Issue Date আর Store বাধ্যতামূলক; Requisition#, Order/Style, Line ঐচ্ছিক। আইটেম বাছলে পাশে সেই স্টোরের স্টক দেখায়। Submit করলে আর বদলায় না। অনুমোদনকারী Approve করলে স্টক থেকে কমে (স্টোরে যা আছে তার বেশি হলে আটকায়, বার্তায় কত আছে আর কত লাগবে), বা কারণসহ Reject।
- **Trims Receipt Return:** শুধু সম্পন্ন MRR-এর বিপরীতে। তারিখ MRR-এর আগে হতে পারে না। MRR বাছলে তার আইটেম: এসেছে, ফেরত গেছে, ফেরতযোগ্য; প্রতিটায় ফেরতের পরিমাণ (ফেরতযোগ্যের বেশি নয়) আর মন্তব্য। কারণ বাধ্যতামূলক। Complete করলে স্টক থেকে কমে।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `assembly.trims.view`
  - `assembly.trims.manage` (কাগজ লেখা, MRR / রিটার্ন সম্পন্ন বা বাতিল, ইস্যু Submit)
  - `assembly.trims.approve` (ইস্যু Approve / Reject)
  - FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER তিনটাই পায়। STOREKEEPER আর LINE_CHIEF পায় view আর manage।
- **API (`TrimsApiController`):**
  - `GET /assembly/trims/options` · `GET /assembly/trims/stock?store_id=`
  - `GET|POST /assembly/trims/receipts` · `GET|POST /assembly/trims/receipts/{no}` · `POST /assembly/trims/receipts/{no}/complete|cancel`
  - `GET|POST /assembly/trims/issues` · `GET|POST /assembly/trims/issues/{no}` · `POST /assembly/trims/issues/{no}/submit|approve|reject`
  - `GET|POST /assembly/trims/returns` · `GET /assembly/trims/returns/source/{mrr}?except=` · `GET|POST /assembly/trims/returns/{no}` · `POST /assembly/trims/returns/{no}/complete|cancel`
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার ACCESSORIES STORE, ৩টা স্ক্রিন: `/assembly-floor/trims-accessories-receive`, `/trims-accessories-issue`, `/trims-accessories-receive-return-list`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/trims.jsx` (`TrimsReceiptDesk`, `TrimsIssueDesk`, `TrimsReturnDesk`, সাথে `DeskTabs`, `Filters`, `Register`, `Foot`)।
- **তালিকা:** Brickland-এর "MRR List n" / "Issue List n" / "Return List n" ট্যাব, ফিল্টার (MRR নং, তারিখের সীমা, স্টোর, সাপ্লায়ার, পেমেন্ট, রিক. ধরন, PR, PO, অবস্থা ইত্যাদি), RecordsGrid (সাজানো, খোঁজা, Excel), সারির বাটন: Edit / Complete / Cancel, বা Edit / Submit / Approve / Reject (নিশ্চিত হতে জিজ্ঞেস করে; Reject-এ কারণ)।
- **এন্ট্রি:** Dye House-এর মতো কার্ড; একক মাস্টার থেকে (UomSelect); ইস্যুতে প্রতিটা লাইনের পাশে "In Store"।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_14_000100_create_asm_trims_store.php`
- `app/Modules/Assembly/Models/AsmTrimsReceipt.php`, `AsmTrimsReceiptLine.php`, `AsmTrimsIssue.php`, `AsmTrimsIssueLine.php`, `AsmTrimsReturn.php`, `AsmTrimsReturnLine.php`, `AsmTrimsMove.php`
- `app/Modules/Assembly/Support/TrimsStore.php` (নম্বর, নাম, স্টক, স্টক চলাচল)
- `app/Modules/Assembly/Http/Controllers/Api/TrimsApiController.php`
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/trims.jsx` (নতুন)
- `frontend/src/app/(app)/assembly-floor/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyTrimsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyTrimsTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ১০১টা assertion। Assembly-র ২২টা টেস্টই একসাথে পাস (১,১৯৬টা assertion); MenuAccessTest + RoleMatrixTest (১৩টা, ৮৪টা assertion) পাস।
- `test_a_receipt_is_drafted_rewritten_and_completed_into_stock`: options (স্টোর, শুধু TRIMS আইটেম, ৫টা পেমেন্ট, অর্ডার, লাইন); MRR Date / পেমেন্ট / আইটেম ছাড়া, FABRIC আইটেম, পরিমাণ ০ আটকায়; MRR-2026110001 ড্রাফট (২টা আইটেম, ৬০০, মূল্য); ড্রাফট বদলানো; Complete-এর আগে স্টক ফাঁকা, পরে বোতাম ৪৮০ আর সুতা ১০০; সম্পন্ন MRR বদলানো / আবার Complete / Cancel আটকায়; দ্বিতীয় MRR বাতিল, স্টক বদলায় না।
- `test_an_issue_is_submitted_then_approved_off_stock_never_more_than_held`: ড্রাফটে "In Store" ৫০০; Submit-এর আগে Approve আটকায়; Submit-এর পর বদলানো আটকায়; LINE_CHIEF Approve পারে না; ৬০০ Approve আটকায় ("৫০০ in stock, ৬০০ needed"), অবস্থা SUBMITTED থাকে; কারণ ছাড়া Reject আটকায়, কারণসহ REJECTED; LINE_CHIEF-এর ইস্যু STORE_MANAGER অনুমোদন করে → বোতাম ৩০০, সুতা ৭০।
- `test_a_receipt_return_and_who_may`: ড্রাফট MRR-এর বিপরীতে রিটার্ন আটকায়; ফেরতযোগ্য ৫০০ / ১০০; ৬০০ আটকায়; MRR-এর আগের তারিখ আটকায়; কারণ বাধ্যতামূলক; ৫০ পিস (Damaged) RTN-2026110001; অন্য রিটার্নের জন্য ফেরতযোগ্য ৪৫০, নিজেরটা এডিটে ৫০০ (`except`); ৬০-তে বদলে Complete → বোতাম ৪৪০; ৪৪১ আটকায়; দ্বিতীয় রিটার্ন বাতিল; STOREKEEPER লিখতে / Submit করতে পারে, Approve পারে না; TECHNICIAN আর VIEWER পায় না।

**UI চেক (`AnnotechERP/tools/app_check/plans/ta01`), dev অ্যাপে (ডাটা রেখে দেওয়া হয়েছে):**
- Receipt: "Daily Trims Receive" ট্যাব → Trims & accessories store, Coats Bangladesh Ltd, Store Requisition, LC, PR-2611-07, PO-2611-12; Sewing Thread 40/2 ১২০ কোন (১৪৫ টাকা) আর Button 18L ২,০০০ পিস (০.৯) Add to Grid → Save MRR → MRR-2026110001 (মোট ২,১২০, মূল্য ১৯,২০০) → তালিকায় Complete → Completed।
- Issue: Trims & accessories store, REQ-LINE02-07, SS27-TEE01 — LPP-2026-000001, LINE02; সুতা বাছতেই "In Store" ১২০; ৪০ কোন → Save Issue → ISS-2026110001 → Submit → Approve → Approved।
- Return: MRR-2026110001 বাছলে দুটো আইটেম (এসেছে / ফেরত / ফেরতযোগ্য); Button ৫০ পিস, Damaged, "Broken holes" → Save Return → RTN-2026110001 → Complete → Completed।
- ট্যাবগুলো পেজ রিলোড ছাড়াই বদলায়। **error:** ০ (বাগ ঠিক করার পরের রানে)।

## ১০. খোলা প্রশ্ন

১. **Requisition Type:** Brickland-এ এটা একটা তালিকা থেকে বাছাই, কিন্তু ডেমোতে কোনো মান নেই। এখন Dye House-এর মতো লিখে দেওয়ার ঘর (ডিফল্ট লেখা "Store Requisition")। নির্দিষ্ট তালিকা লাগলে মানগুলো জানাবেন।

২. **ইস্যু আর রিকুইজিশন:** "Requisition#" এখন শুধু লেখা নম্বর; কোনো রিকুইজিশন কাগজের সাথে যুক্ত নয়, আর ইস্যু অর্ডারের ট্রিমস বুকিং / প্রয়োজনের সাথে মেলানো হয় না। অর্ডারের প্রয়োজনের বেশি ইস্যু আটকাতে হবে কিনা জানাবেন।

৩. **স্টোর আর 09 Store Material:** এই ফ্লোর স্টোরের স্টক আলাদা লেজারে (`asm_trims_moves`), 09 Store Material-এর Trims Warehouse-এর সাথে যুক্ত নয়। মূল গুদাম থেকে ফ্লোর স্টোরে ট্রান্সফার লাগবে কিনা, নাকি দুটো একই স্টক হবে, জানাবেন।

৪. **Receipt-এর অনুমোদন:** Brickland-এর MRR-এর অবস্থা শুধু Draft / Completed / Cancelled; Issue-তে Submitted / Approve / Rejected। তাই MRR-এ কোনো অনুমোদন ধাপ নেই। MRR-এও অনুমোদন লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/ta01` আর JS চাঙ্ক):** এই তিনটা স্ক্রিন Brickland-এর সাধারণ resource-spec ফর্ম (`createRecord` / `loadRecords`) দিয়ে চলে; ঘর, ট্যাব, সেকশন (Key Information, Against, Notes, Receive / Issue Detail), বাটন আর অবস্থা লাইভ পেজ থেকে নেওয়া। Store / Supplier / Item / Order / Line-এর তালিকা আমাদের মাস্টার থেকে।
- **Trims Receipt Return Register:** Brickland-এ "Scaffold" (ডেটা নেই)। আমাদের নিয়মে পুরো বানানো হয়েছে: সম্পন্ন MRR-এর বিপরীতে রিটার্ন, কারণ (Short / Damaged / Over-supplied), ফেরতযোগ্যের হিসাব, Complete-এ স্টক থেকে কমে।
- **UI চেকে ধরা পড়া বাগ:** প্রথমবার স্ক্রিনগুলো খোলেনি ("Functions cannot be passed directly to Client Components"), কারণ `screens.js`-এ action-গুলো arrow function দিয়ে মোড়ানো ছিল। প্রতিটার জন্য আলাদা server action বানিয়ে ঠিক করা হয়েছে; দ্বিতীয় UI চেক তার পরে।
