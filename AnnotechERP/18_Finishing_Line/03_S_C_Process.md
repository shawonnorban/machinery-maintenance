# S/C Process

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `18/03`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sub-Contract Delivery Challan | `/m/dyeing-finishing/sc-delivery-challan-gatepass` | 3 | 12 | 2 |
| 2 | Sub-Contract Finished Receipt | `/m/dyeing-finishing/sc-receive-finished` | 3 | 8 | 2 |
| 3 | Sub-Contract Process Bill | `/m/dyeing-finishing/sc-process-bill-entry` | 3 | 8 | 2 |
| 4 | Sub-Contract Party Balance | `/m/dyeing-finishing/sc-party-wise-balance` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sub-Contract Delivery Challan

`/m/dyeing-finishing/sc-delivery-challan-gatepass`

**ট্যাব: S/C Program Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Status | select |  | Draft, Posted, Cancelled |
| Challan No | text |  |  |
| Gate Pass No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: S/C Program Challan List 0, S/C Program Challan, New challan, -- Select Supplier --, -- Select Store --, Search, Add New, Excel, Reset

**ট্যাব: S/C Program Challan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Status | select |  | Draft, Posted, Cancelled |
| Challan No | text |  |  |
| Gate Pass No | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: S/C Program Challan List 0, S/C Program Challan, New challan, -- Select Supplier --, -- Select Store --, Search, Add New, Excel, Reset

**ট্যাব: New challan**
- সেকশন: New challan
- বাটন/অ্যাকশন: S/C Program Challan List 0, S/C Program Challan, New challan, Reset, Save challan

**ডায়ালগ: New challan**
- খালি Save-এ মেসেজ: A challan is raised on the entry tab, where its shades are picked. Add New opens it.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
| Batch | select |  |  |
| Buyer A/C | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Description of Goods | text |  |  |
| Lot No | text |  |  |
| Dia Type | select |  | Select…, Open, Tube |
| Qty (Kg) | number |  |  |
| Rolls | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A challan with no shades on it sends nothing to the processor.

#### Sub-Contract Finished Receipt

`/m/dyeing-finishing/sc-receive-finished`

**ট্যাব: Receive S/C Finish Fabric at Store 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No | text |  |  |
| Receive Date | date |  |  |
| Challan No | text |  |  |
| Batch No | text |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Receive S/C Finish Fabric at Store 0, Receive S/C Finish Fabric at Store, New receipt, -- Party --, -- Store --, Search, Add New, Excel, Reset

**ট্যাব: Receive S/C Finish Fabric at Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No | text |  |  |
| Receive Date | date |  |  |
| Challan No | text |  |  |
| Batch No | text |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Receive S/C Finish Fabric at Store 0, Receive S/C Finish Fabric at Store, New receipt, -- Party --, -- Store --, Search, Add New, Excel, Reset

**ট্যাব: New receipt**
- সেকশন: New receipt
- বাটন/অ্যাকশন: Receive S/C Finish Fabric at Store 0, Receive S/C Finish Fabric at Store, New receipt, Reset, Save receipt

**ডায়ালগ: New receipt**
- খালি Save-এ মেসেজ: A receipt is written on the entry tab, against the challan it answers. Add New opens it.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receipt | select |  |  |
| Receive Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Challan No | select |  |  |
| Gate Pass No | text |  |  |
| Remarks | textarea |  |  |
| Batch | select |  |  |
| Description | text |  |  |
| Lot No | text |  |  |
| Received (Kg) | number |  |  |
| Rolls | number |  |  |
| Short (Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: S/C Party is required.

#### Sub-Contract Process Bill

`/m/dyeing-finishing/sc-process-bill-entry`

**ট্যাব: S/C Program Bill List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Completed, Cancelled |
| Bill# | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Party
- বাটন/অ্যাকশন: S/C Program Bill List 0, S/C Program Bill, New bill, -- Select Party --, Show, Add New, Excel, Reset

**ট্যাব: S/C Program Bill**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Completed, Cancelled |
| Bill# | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Party
- বাটন/অ্যাকশন: S/C Program Bill List 0, S/C Program Bill, New bill, -- Select Party --, Show, Add New, Excel, Reset

**ট্যাব: New bill**
- সেকশন: New bill
- বাটন/অ্যাকশন: S/C Program Bill List 0, S/C Program Bill, New bill, Reset, Save bill

**ডায়ালগ: New bill**
- খালি Save-এ মেসেজ: A bill is written on the entry tab, against the challans it charges for. Add New opens it.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing bill | select |  |  |
| Bill Date* | date | ✔ |  |
| Bill #* | text | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Ref. Bill # | text |  |  |
| Rev # | text |  |  |
| Rev. Description | text |  |  |
| Remarks | textarea |  |  |
| Challan | select |  |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Description | text |  |  |
| Qty (Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Supplier is required.

#### Sub-Contract Party Balance

`/m/dyeing-finishing/sc-party-wise-balance`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  |  |
| Open lines only | checkbox |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: -- Party --, Show, Excel, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **18/02-এর ফিনিশিং কার্ড:** `fin_batch_receipts` আর `FinFloor::move`। চালানে যে ব্যাচ যায় তা ফ্লোরের কার্ড, আর তার স্টেজ সরানো 18/02-এর ট্রেইলেই লেখা হয়।
- **18/01-এর প্রসেস টাইপ:** প্রসেসের রেট (বিলের ডিফল্ট রেট) আর WIP স্টেজ (ফেরত এলে ব্যাচ যে স্টেজে ফেরে)।
- **মাস্টার:** সাপ্লায়ার (`mp_suppliers`), স্টোর, বায়ার, অর্ডার।
- **শেয়ার করা এডিটর:** chem-store-এর `LinesCard`/`EditorFoot`/`usePaper`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/Finishing/Database/Migrations/2027_02_04_000100_create_fin_sc_process.php`:
- **`fin_sc_challans`:** challan_no (FSC-CH-YYYYMM####), challan_date, supplier, store, gate_pass_no, vehicle_no, remarks, status (DRAFT/POSTED/CANCELLED), posted_at, posted_by।
- **`fin_sc_challan_lines`:** challan, receipt (ফিনিশিং কার্ড, ঐচ্ছিক), buyer, order, style, colour, process, description, lot_no, dia_type (OPEN/TUBE), qty_kg, rolls, rate, remarks।
- **`fin_sc_receipts`:** mrr_no (FSC-MRR-YYYYMM####), receive_date, challan (ঐচ্ছিক), supplier, store, gate_pass_no, remarks, status (DRAFT/POSTED)।
- **`fin_sc_receipt_lines`:** receipt, challan_line (ঐচ্ছিক), description, lot_no, recv_kg, rolls, short_kg, remarks।
- **`fin_sc_bills`:** bill_no (পার্টির নিজের, প্রতি পার্টিতে ইউনিক), bill_date, supplier, ref_bill_no, rev_no, rev_description, remarks, status (DRAFT/SUBMITTED/COMPLETED/CANCELLED)।
- **`fin_sc_bill_lines`:** bill, challan, process, description, qty_kg, rate, amount (= qty × rate, সার্ভারে হিসাব), remarks।

## ৫. Workflow / Status ও Business Rules

- **Delivery Challan:**
  - তারিখ, সাপ্লায়ার আর স্টোর বাধ্যতামূলক। অন্তত একটা লাইন লাগবে।
  - প্রতিটা লাইনে ফ্লোরের একটা ব্যাচ থাকে, অথবা লট বা মালের বিবরণ।
  - ব্যাচ শুধু ফ্লোরে থাকা কার্ড থেকে। এর বায়ার, অর্ডার, স্টাইল, রং, dia, কেজি আর রোল নিজে বসে।
  - এক চালানে একটা ব্যাচের রিসিভ হওয়া কেজির বেশি পাঠানো যায় না।
  - Save Challan চালানকে খসড়া রাখে। রেজিস্টার থেকে Post করলে প্রতিটা ব্যাচ At Sub-Contract-এ যায় (ট্রেইলে SC_OUT)। পোস্ট বা বাতিল চালান শুধু পড়া যায়।
  - কিছু ফেরত না এলে বা বিল না হলে চালান Cancel করা যায়। পোস্ট করা চালানের ব্যাচ তখন যে স্টেজ থেকে গিয়েছিল সেখানে ফেরে (SC_BACK)।
- **Finished Receipt:**
  - তারিখ, পার্টি আর স্টোর বাধ্যতামূলক।
  - চালান বাছলে সেটা পোস্ট করা হতে হবে, পার্টি হয় চালানেরটাই, আর তারিখ চালানের আগে হতে পারে না।
  - প্রতিটা লাইন চালানের একটা লাইনের জবাব দেয়। চালান ফাঁকা রাখলে মিল-ছাড়া রিটার্ন বুক হয়, যা কিছুই কাটে না।
  - রিসিভ + শর্ট কখনো লাইনের বাকির বেশি হয় না। বাকি = পরিমাণ − পোস্ট করা রিসিপ্টের রিসিভ আর শর্ট। এটা সেভে আর আবার পোস্টে যাচাই হয়।
  - Post করলে চালান-লাইনে জমা হয়। যে ব্যাচের সব পোস্ট করা চালান-লাইনের জবাব হয়ে গেছে, সেটা ফ্লোরে ফেরে শেষ লাইনের প্রসেসের WIP স্টেজে (SC_IN)।
- **Process Bill:**
  - পার্টির বিল নম্বর বড় হাতের অক্ষরে রাখা হয়, প্রতি পার্টিতে একবার।
  - প্রতিটা লাইন এই পার্টির একটা পোস্ট করা চালানের বিপরীতে, এমন প্রসেসের জন্য যা ওই চালানে পাঠানো হয়েছিল।
  - একটা চালান আর প্রসেসের জন্য যা ফেরত এসেছে তার বেশি কেজি বিল করা যায় না। বাতিল নয় এমন অন্য বিলের কেজিও এখানে গোনা হয়।
  - রেট ফাঁকা রাখলে প্রসেসের রেট বসে। টাকা = কেজি × রেট, সার্ভারে আবার হিসাব হয়।
  - Draft → Submit → Complete। Cancel করা যায় শুধু Draft বা Submitted থেকে। Draft বা Submitted থাকা পর্যন্ত বিল বদলানো যায়, Completed হলে আর না।
- **Party Balance:**
  - পোস্ট করা চালানের প্রতিটা লাইন দেখায়: পাঠানো, রিসিভ, শর্ট, বাকি, আর কত দিন বাইরে।
  - পার্টি, চালান নং আর "Open lines only" দিয়ে খোঁজা যায়। এখানে কিছুই রাখা হয় না, দেখার মুহূর্তে গোনা হয়। Excel একই সারি লেখে।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `finishing.sc.view` / `finishing.sc.manage`
  - FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER দুটোই পায়। LINE_CHIEF শুধু view পায়।
- **API (`FinScProcessApiController`):**
  - `GET /finishing/sc/options`
  - চালান: `GET|POST /finishing/sc-challans`, `GET|PUT /finishing/sc-challans/{no}`, `POST …/{no}/post`, `POST …/{no}/cancel`
  - রিসিপ্ট: `GET|POST /finishing/sc-receipts`, `GET|PUT /finishing/sc-receipts/{no}`, `POST …/{no}/post`
  - বিল: `GET|POST /finishing/sc-bills`, `GET|PUT /finishing/sc-bills/{billNo}?party=CODE`, `POST …/{billNo}/status`
  - ব্যালান্স: `GET /finishing/sc-balance`, `GET /finishing/sc-balance/export`
- **মেনু:** Finishing Line-এর নতুন ফোল্ডার S/C PROCESS, ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `finishing-line/[screen]`-এ, `sc-screens.js` থেকে। কম্পোনেন্ট `components/finishing/sc-process.jsx`-এ: `ScChallanEditor`, `ScReceiptEditor`, `ScBillEditor`, `ScRegister`, `ScPartyBalance`।
- **তিন ট্যাব:** Brickland-এর মতো তালিকা, এন্ট্রি আর "New …" ট্যাব।
  - তালিকায় Searching Data কার্ড, Add New আর রেজিস্টার। রেজিস্টারে Edit, Post / Cancel (চালান), Post (রিসিপ্ট), আর Submit / Complete / Cancel (বিল)।
  - Brickland-এর তৃতীয় ট্যাবে কোনো ফর্ম নেই, শুধু এন্ট্রি ট্যাবে পাঠায়। আমাদের তৃতীয় ট্যাবও একই কথা বলে, আর তার Add New বোতাম এন্ট্রি ট্যাব খোলে।
- **এডিটর:** Existing …, হেডার, Challan / Received / Bill Detail (Add Shade বা Add Line, আর Cancel), এবং Back To List / Clear / Save।
  - রিসিপ্টে ব্যাচের তালিকা আসে বাছাই করা চালানের লাইন থেকে, প্রতিটার পাশে কত বাকি তা লেখা।
  - বিলে টাকার কলাম নিজে হিসাব হয়।
- **লিংকে নম্বর থাকে, ULID নয়:** `?doc=FSC-CH-…`, আর বিলের জন্য `?doc=BILLNO&party=CODE`।
- **Process-Wise Delivery (18/02):** এই স্ক্রিন মেনুতে আসায় S/C Prod. বাটন এখন নিজে থেকেই দেখা যায়।
- **Excel:** `frontend/src/app/api/finishing/sc-balance/route.js`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Finishing/Database/Migrations/2027_02_04_000100_create_fin_sc_process.php`
- `app/Modules/Finishing/Models/`: `FinScChallan`, `FinScChallanLine`, `FinScReceipt`, `FinScReceiptLine`, `FinScBill`, `FinScBillLine`
- `app/Modules/Finishing/Support/FinSc.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinScProcessApiController.php`
- `app/Modules/Finishing/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/finishing.php`, `lang/bn/finishing.php` (বদলানো: `sc` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/sc-screens.js` (নতুন), `page.js` আর `actions.js` (বদলানো)
- `frontend/src/components/finishing/sc-process.jsx`
- `frontend/src/app/api/finishing/sc-balance/route.js`

**Test**
- `tests/Feature/Finishing/FinishingScProcessTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Finishing/FinishingScProcessTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ১১৭টা assertion।
- `test_a_challan_sends_its_batches_out_and_a_cancelled_one_brings_them_back`:
  - লাইন ছাড়া চালান, ব্যাচ/লট ছাড়া সারি আর কার্ডের কেজির বেশি পাঠানো আটকায়।
  - FSC-CH-2026110001: রং ব্যাচ থেকে আসে।
  - পোস্টের পরে ব্যাচ At Sub-Contract-এ। চালান এডিট আটকায়।
  - বাতিল করলে ব্যাচ Awaiting Load-এ ফেরে, আর ট্রেইল RECEIPT → SC_OUT → SC_BACK।
- `test_the_finished_receipt_answers_challan_lines_and_brings_the_batch_back`:
  - খসড়া চালান, অন্য পার্টি, আগের তারিখ আর বাকির বেশি আটকায়।
  - ৫৮ + ০.৫ পোস্ট হলে ব্যাচ বাইরেই থাকে, আর ব্যালান্স ৪১.৫। চালান বাতিলও আটকায়।
  - বাকিটা আসলে ব্যাচ Stentering-এ ফ্লোরে ফেরে, আর খোলা লাইন ০। Excel আসে।
  - চালান ছাড়া রিটার্ন বুক হয়, কিন্তু চালান ছাড়া চালান-লাইন দেওয়া আটকায়।
- `test_the_process_bill_is_held_to_what_came_back`:
  - অন্য পার্টির চালান, না পাঠানো প্রসেস আর ফেরত আসার বেশি কেজি আটকায়।
  - বিল HM-INV-77: রেট প্রসেস থেকে ৮, টাকা ৪০০, মোট ৫৯০। একই পার্টির একই বিল নম্বর আবার দেওয়া আটকায়।
  - Draft থেকে সরাসরি Complete আটকায়। Submit-এর পরে এডিট (রিভিশন R1, ৬৪০) চলে। Complete-এর পরে এডিট আর Cancel আটকায়।
- `test_who_may_send_fabric_out`: STORE_MANAGER চালান তুলতে পারে। LINE_CHIEF দেখতে পারে, পোস্ট করতে পারে না। TECHNICIAN কিছুই পায় না।

**Regression:** `FinishingProductionTest` (৬টা, ২০৬টা), `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/fsc01`), dev অ্যাপে (Delta Apparels):**
- **Delivery Challan:**
  - FSC-CH-2026110001: Square Textiles, Fabric store (DHK-FAB), গাড়ি DM-TA-11-2233।
  - একটা শেড: B-26110011 (বায়ার LPP, স্টাইল, অর্ডার, রং Black, ৯৯.২ kg নিজে বসেছে), Calendering (CAL), লট LOT-B11।
  - রেজিস্টার থেকে Post করা হয়েছে।
- **Process-Wise Delivery (18/02):** B-26110011 এখন At Sub-Contract। S/C Prod. বাটন নিজে থেকেই এসেছে।
- **Finished Receipt:**
  - FSC-MRR-2026110001: চালান বাছতেই পার্টি বসেছে। গেট পাস IN-501।
  - লাইনে বাকি ৯৯.২ দেখিয়েছে। রিসিভ ৯৮.৫, শর্ট ০.৭। Post করা হয়েছে।
  - পোস্টের পরে ব্যাচ Calendering-এ ফ্লোরে ফিরেছে। ট্রেইল: RECEIPT → SHEET → SHEET → BOARD → SC_OUT → SC_IN।
- **Process Bill:** SQ-INV-101 (বড় হাতের অক্ষরে)। চালান FSC-CH-2026110001, Calendering, ৯৮.৫ kg × ৬ (প্রসেসের রেট) = ৫৯১। Submit করা হয়েছে।
- **Party Balance:** চালানের লাইন: ৯৯.২ পাঠানো, ৯৮.৫ রিসিভ, ০.৭ শর্ট, ব্যালান্স ০।
- **তৃতীয় ট্যাব ("New receipt"):** Brickland-এর মতো এন্ট্রি ট্যাবে পাঠানোর কথা আর Add New বাটন।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।
- **পরিবেশের সমস্যা:** backend রিস্টার্টের পরে nginx পুরোনো ঠিকানায় যাচ্ছিল, তাই লগইনে 502 আসছিল। `docker compose restart nginx` দিয়ে ঠিক হয়েছে।

**প্রিন্ট:** Brickland-এর এই চারটা স্ক্রিনে Print বাটন নেই, শুধু Excel।

## ১০. খোলা প্রশ্ন

১. **ফেরত আসা ফেব্রিক কোথায় যায়:** রিসিপ্টে স্টোর বাছতে হয়, কিন্তু এখনো কোনো স্টক লেজারে কিছু ঢোকে না। ফিনিশড ফেব্রিক স্টোর (18/05) তৈরি হলে রিসিপ্ট সেখানে স্টক তুলবে কিনা জানাবেন।

২. **ব্যাচ কোন স্টেজে ফেরে:** সব লাইনের জবাব হলে ব্যাচ শেষ লাইনের প্রসেসের WIP স্টেজে ফেরে (যেমন Calendering → Calendering)। প্রসেস শেষ হয়েছে বলে পরের ধাপে (যেমন QC Inspection) পাঠানো উচিত কিনা জানাবেন।

৩. **শর্ট ওজন:** শর্ট ওজন লাইনের বাকি থেকে কাটা হয়, কিন্তু বিল হয় শুধু রিসিভ কেজির ওপর। পার্টির কাছ থেকে শর্টের দাম কাটার (ডেবিট নোট) কোনো ধাপ লাগবে কিনা জানাবেন।

৪. **বিলের অনুমোদন:** Submit থেকে Complete শুধু একটা ধাপ, আলাদা কোনো অনুমোদন নেই। Approval Center-এ পাঠাতে হবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/fs01`–`fs02`):**
  - তিনটা কাগজের এন্ট্রি খোলে তালিকার Add New দিয়ে। তৃতীয় ট্যাবটা ("New …") খালি, শুধু এন্ট্রির দিকে দেখায়।
  - ডেমোতে কোনো সারি ছিল না, তাই রেজিস্টার আর ব্যালান্সের কলাম বানানো হয়েছে হিন্ট আর ফিল্ড দেখে ("Received column is recounted from the receipts", "Balance is the challan's quantity less every posted receipt")।
- **কিছুই লেখা হয় না:** চালানের রিসিভ আর বাকি সবসময় পোস্ট করা রিসিপ্ট থেকে গোনা হয়, কোথাও সংখ্যা রাখা হয় না।
- **একই কার্ড:** S/C-তে যাওয়া আর ফেরা 18/02-এর একই কার্ডে `fin_process_deliveries`-এ লেখা হয় (SC_OUT / SC_BACK / SC_IN)।
