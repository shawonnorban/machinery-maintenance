# Fabric Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `18/05`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Finished Fabric Store Receipt | `/m/dyeing-finishing/finish-fabric-store-receive` | 3 | 26 | 2 |
| 2 | Roll-Wise Racking & Barcode | `/m/dyeing-finishing/roll-wise-racking-barcode` | 1 | 0 | 0 |
| 3 | Fabric Issue to Cutting Floor | `/m/dyeing-finishing/fabric-issue-to-cutting` | 3 | 10 | 2 |
| 4 | Fabric Store Return & Transfer | `/m/dyeing-finishing/fabric-store-return-transfer` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Finished Fabric Store Receipt

`/m/dyeing-finishing/finish-fabric-store-receive`

**ট্যাব: Delivery List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Shipment Month | text |  | 2026-08 |
| Date | date |  |  |
| Batch # | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Delivery List 0, Delivery to Finish Fabric Store, New delivery, --Style --, -- Select Store --, Show, Add New, Excel, Reset

**ট্যাব: Delivery to Finish Fabric Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing delivery | select |  |  |
| Date of Receive* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Batch No | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Fabric Type | select |  | 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Fabric Details | text |  |  |
| Item Code | text |  |  |
| Lot No | text |  |  |
| Batch No (tag) | text |  |  |
| Shade No | text |  |  |
| Dia Type | select |  | Select…, Open, Tube |
| Fin. GSM | number |  |  |
| Fin. Dia | number |  |  |
| Grey Qty. | number |  |  |
| Finish Qty | number |  |  |
| No of Roll | number |  |  |
| Rate | number |  |  |
| AOP | checkbox |  |  |
| Remarks | text |  |  |
- সেকশন: Delivery to Finish Fabric Store, Master Data, Remarks, Batch Lot Data
- বাটন/অ্যাকশন: Delivery List 0, Delivery to Finish Fabric Store, New delivery, -- Select Store --, Add Lot, Cancel, -- Color --, Back To List, Back, Save

**ট্যাব: New delivery**
- সেকশন: New delivery
- বাটন/অ্যাকশন: Delivery List 0, Delivery to Finish Fabric Store, New delivery, Reset, Save delivery

**ডায়ালগ: New delivery**
- খালি Save-এ মেসেজ: A delivery is written on the entry tab, where the batch lots are picked. Add New opens it.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing delivery | select |  |  |
| Date of Receive* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Batch No | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Fabric Type | select |  | 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Fabric Details | text |  |  |
| Item Code | text |  |  |
| Lot No | text |  |  |
| Batch No (tag) | text |  |  |
| Shade No | text |  |  |
| Dia Type | select |  | Select…, Open, Tube |
| Fin. GSM | number |  |  |
| Fin. Dia | number |  |  |
| Grey Qty. | number |  |  |
| Finish Qty | number |  |  |
| No of Roll | number |  |  |
| Rate | number |  |  |
| AOP | checkbox |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A hand-over with no lots on it hands over nothing.

#### Roll-Wise Racking & Barcode

`/m/dyeing-finishing/roll-wise-racking-barcode`

**ভিউ**
> 19 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Fabric Issue to Cutting Floor

`/m/dyeing-finishing/fabric-issue-to-cutting`

**ট্যাব: Finish Fabric Issue to Cutting List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer A/C | select |  |  |
| Batch No | text |  |  |
| Status | select |  | Draft, Partial, Complete, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Finish Fabric Issue to Cutting List 0, Finish Fabric Issue to Cutting, New requisition, --Style --, -- Select --, -- Select Store --, Search, Add New, Excel, Reset

**ট্যাব: Finish Fabric Issue to Cutting**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Buyer A/C | select |  |  |
| Batch No | text |  |  |
| Status | select |  | Draft, Partial, Complete, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Finish Fabric Issue to Cutting List 0, Finish Fabric Issue to Cutting, New requisition, --Style --, -- Select --, -- Select Store --, Search, Add New, Excel, Reset

**ট্যাব: New requisition**
- সেকশন: New requisition
- বাটন/অ্যাকশন: Finish Fabric Issue to Cutting List 0, Finish Fabric Issue to Cutting, New requisition, Reset, Save requisition

**ডায়ালগ: New requisition**
- খালি Save-এ মেসেজ: A requisition is written on the entry tab, where the lots are picked off the store's stock. Add New opens it.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Req Date* | date | ✔ |  |
| Req No* | text | ✔ |  |
| Unit* | select | ✔ | Brickland Composite Ltd. |
| CutProg Ref# | text |  |  |
| Buyer A/C | select |  |  |
| Batch No | text |  |  |
| Action | text |  |  |
| Status* | select | ✔ | Select…, Draft, Partial, Complete, Cancelled |
| Remarks | textarea |  |  |
| Lot | select |  |  |
| Description | text |  |  |
| Lot No | text |  |  |
| Req Qty | number |  |  |
| Issue Qty | number |  |  |
| Rolls | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Store is required.

#### Fabric Store Return & Transfer

`/m/dyeing-finishing/fabric-store-return-transfer`

**ভিউ**
> 21 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **18/02–18/04-এর ফিনিশিং কার্ড:** ইন্সপেকশনে Pass হওয়া ব্যাচ Ready for Delivery-তে থাকে। স্টোরে পাঠালে একই কার্ড Delivered হয় (ট্রেইলে STORE), তাই 18/02-এর বোর্ডে BT Trans. বাটন এখন এই স্ক্রিনে যায়।
- **শেয়ার করা অংশ:** `DyeBatches::nextNumber`, `ScRegister` (18/03), chem-store-এর `LinesCard` (এখন `check` কলামও আছে, AOP-এর জন্য)।
- **নিজের লেজার:** Store & Material-এর সাধারণ লেজার রোল/লট-ভিত্তিক ফিনিশড ফেব্রিকের জন্য বানানো নয়। তাই এখানে আলাদা `fin_store_lots` আর `fin_store_moves` রাখা হয়েছে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/Finishing/Database/Migrations/2027_02_06_000100_create_fin_fabric_store.php`:
- **`fin_store_deliveries`:** delivery_no (FFD-YYYYMM####), receive_date, store, order (লাইন থেকে আসে), remarks, status (DRAFT/SENT/RECEIVED), sent_at, received_at, received_by।
- **`fin_store_delivery_lines`:** receipt (কার্ড), order, fabric_type, colour, fabric_details, item_code, lot_no, tag_batch_no, shade_no, dia_type, fin_gsm, fin_dia, grey_qty, finish_qty, rolls, rate, aop, remarks।
- **`fin_store_lots`:** lot_code (FSL-…), store, delivery_line (একটাই), receipt, order, buyer, style, colour, fabric type/details, lot_no, shade_no, dia, GSM, received_kg, rolls, rate, received_on।
- **`fin_store_moves`:** lot, kind (RECEIPT/ISSUE/RETURN/TRANSFER), qty_kg আর rolls (চিহ্নসহ), move_date, doc_type, doc_id, doc_no। ব্যালান্স = এগুলোর যোগফল।
- **`fin_store_rolls`:** lot, roll_no (প্রতি লটে ইউনিক), barcode (FR+YYMM+৬ অঙ্ক, ইউনিক), weight_kg, rack, bin, status।
- **`fin_cut_requisitions`:** req_no (নিজে দেওয়া, ইউনিক), req_date, cut_prog_ref, store, buyer, style, colour, batch_no, action, status (DRAFT/PARTIAL/COMPLETE/CANCELLED), remarks।
- **`fin_cut_requisition_lines`:** lot, description, lot_no, batch_no, req_qty, issue_qty, rolls, remarks।
- **`fin_store_returns`:** return_no (FSR-…), return_date, kind (RETURN/TRANSFER), lot, requisition, qty_kg, rolls, to_unit, reason, remarks।

## ৫. Workflow / Status ও Business Rules

- **Finished Fabric Store Receipt:**
  - লাইনে শুধু Ready for Delivery ব্যাচ (ইন্সপেকশনে পাস)। এক নোটে একটা ব্যাচ একবারই, আর অন্য খসড়া বা পাঠানো নোটে থাকা ব্যাচ নেওয়া যায় না।
  - এক নোটের সব লট একই অর্ডারের হতে হবে, আর নোটের অর্ডার লাইন থেকে বসে।
  - ফিনিশ পরিমাণ গ্রের বেশি হতে পারে না। ব্যাচ বাছলে অর্ডার, রং, লট, ব্যাচ নং, ডায়া, কেজি আর রোল নিজে বসে।
  - রেজিস্টারে Send (খসড়া থেকে) দিলে প্রতিটা ব্যাচ বোর্ড থেকে Delivered হয়।
  - Receive (Sent থেকে) দিলে প্রতিটা লাইন স্টোরে একটা লট হয়, আর তখনই স্টক তৈরি হয় (RECEIPT move)। পাঠানো নোট শুধু পড়া যায়।
- **Roll-Wise Racking & Barcode** (Brickland-এ scaffold, পুরোটা বানানো হয়েছে):
  - রোল নম্বর বড় হাতের অক্ষরে রাখা হয়, প্রতি লটে একবার। র‍্যাক আর বিনও বড় হাতের অক্ষরে।
  - বারকোড সার্ভার দেয় (FR…), কেউ লেখে না।
  - একটা লটের রোলগুলোর ওজন লটে রিসিভ হওয়া ওজনের বেশি হয় না।
- **Fabric Issue to Cutting Floor:**
  - Req No নিজে দিতে হয়, বড় হাতের অক্ষরে রাখা হয়, আর ইউনিক।
  - প্রতিটা লাইনের লট বাছাই করা স্টোরে থাকতে হয়। Issue Qty লটের ব্যালান্সের বেশি হয় না, আর ব্যালান্স প্রতিবার আবার পড়া হয় (এই রিকুইজিশনের নিজের move বাদে)।
  - Partial বা Complete হলে ইস্যু করা কেজি লট থেকে বের হয় (ISSUE move)। Draft বা Cancelled হলে কিছুই বের হয় না।
  - আবার সেভ করলে move নতুন করে বুক হয়, কখনো দুবার নয়।
  - Complete হতে প্রতিটা লাইন পুরো দিতে হয়। Partial-এ কিছু না কিছু দিতে হয়। Complete বা Cancelled রিকুইজিশন আর বদলায় না।
- **Fabric Store Return & Transfer** (Brickland-এ scaffold):
  - **রিটার্ন:** লাগে সেই রিকুইজিশন, যাতে লট থেকে দেওয়া হয়েছিল। ওই রিকুইজিশন যত দিয়েছিল, তার বেশি ফেরত নেওয়া যায় না (আগের রিটার্ন বাদ দিয়ে)।
  - **ট্রান্সফার:** লাগে যে ইউনিটে যাবে তার নাম, আর লটের ব্যালান্সের বেশি পাঠানো যায় না।
  - তারিখ লট রিসিভের আগে হতে পারে না।
  - এডিট করলে move নতুন করে বুক হয়, আর মুছলে move ফিরে আসে। কোনো রিটার্নের ফেব্রিক আবার বের হয়ে গেলে সেই রিটার্ন মোছা যায় না।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `finishing.store.view` / `finishing.store.manage`
  - FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER দুটোই পায়। LINE_CHIEF শুধু view পায়।
- **Screen-kit resource:** `/api/v1/erp/fin-store-rolls`, `/api/v1/erp/fin-store-returns`
- **API (`FinStoreApiController`):**
  - `GET /finishing/store/options`, `GET /finishing/store/lots`
  - ডেলিভারি: `GET|POST /finishing/store-deliveries`, `GET|PUT /finishing/store-deliveries/{no}`, `POST …/{no}/send`, `POST …/{no}/receive`
  - রিকুইজিশন: `GET|POST /finishing/cut-requisitions`, `GET|PUT /finishing/cut-requisitions/{no}`
- **মেনু:** Finishing Line-এর নতুন ফোল্ডার FABRIC STORE, ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `finishing-line/[screen]`-এ, `store-screens.js` থেকে। এডিটর `components/finishing/store-process.jsx`-এ: `FinStoreDeliveryEditor`, `FinCutRequisitionEditor`।
- **Store Receipt আর Issue to Cutting:** Brickland-এর মতো তিন ট্যাব (তালিকা, এন্ট্রি, আর "New …" যা এন্ট্রিতে পাঠায়)।
  - তালিকায় Search কার্ড, Add New আর রেজিস্টার। ডেলিভারির রেজিস্টারে Edit / Send / Receive।
  - ডেলিভারির এন্ট্রিতে Master Data, Remarks আর Batch Lot Data (Add Lot)।
  - রিকুইজিশনের এন্ট্রিতে হেডার আর Requisition Detail (লটের তালিকা বাছাই করা স্টোর থেকে, প্রতিটার পাশে ব্যালান্স)।
- **Racking আর Return & Transfer:** Records আর Add New।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Finishing/Database/Migrations/2027_02_06_000100_create_fin_fabric_store.php`
- `app/Modules/Finishing/Models/`: `FinStoreDelivery`, `FinStoreDeliveryLine`, `FinStoreLot`, `FinStoreMove`, `FinStoreRoll`, `FinCutRequisition`, `FinCutRequisitionLine`, `FinStoreReturn`
- `app/Modules/Finishing/Support/FinStore.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinStoreApiController.php`
- `app/Modules/Finishing/Resources/FinStoreRollResource.php`, `FinStoreReturnResource.php`
- `app/Modules/Finishing/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/finishing.php`, `lang/bn/finishing.php` (বদলানো: `store` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/store-screens.js` (নতুন), `page.js` আর `actions.js` (বদলানো)
- `frontend/src/components/finishing/store-process.jsx`
- `frontend/src/components/dyehouse/chem-store.jsx` (বদলানো: `LinesCard`-এ `check` কলাম)

**Test**
- `tests/Feature/Finishing/FinishingFabricStoreTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Finishing/FinishingFabricStoreTest.php`):** test2 DB-তে পাস, ৫টা টেস্ট, ১১৫টা assertion।
- `test_passed_batches_are_sent_to_the_store_and_received_into_lots`:
  - লাইন ছাড়া নোট, পাস না হওয়া ব্যাচ, গ্রের বেশি ফিনিশ আর দুই অর্ডার এক নোটে আটকায়।
  - FFD-…0001-এর অর্ডার লাইন থেকে আসে। একই ব্যাচ আরেক নোটে দেওয়া আটকায়।
  - Send-এর আগে Receive আটকায়। Send করলে ব্যাচ Delivered হয়, আর পাঠানোর পরে এডিট আটকায়। Receive-এর আগে কোনো লট নেই।
  - Receive করলে FSL-…0001 তৈরি হয়: ৯৫.৫ কেজি, ব্যালান্স ৯৫.৫, ৬ রোল।
- `test_cutting_requisitions_release_from_the_lots_balance`:
  - অন্য স্টোরের লট, ব্যালান্সের বেশি, অসম্পূর্ণ Complete আর কিছু না দিয়ে Partial আটকায়।
  - CR-101 (বড় হাতের অক্ষরে): ৩০ দিলে ব্যালান্স ৬৫.৫, আবার সেভে ৪০ দিলে ৫৫.৫, Draft-এ ৯৫.৫, Complete ৬০-এ ৩৫.৫।
  - Complete-এর পরে এডিট আর একই Req No আবার দেওয়া আটকায়।
- `test_rolls_are_racked_and_barcoded`: R-1 আর R-A1 বড় হাতের অক্ষরে রাখা হয়। বারকোড FR…000001 আর FR…000002। একই রোল দুবার আর লটের ওজনের বেশি ("only 77 kg is left") আটকায়।
- `test_returns_and_transfers_move_the_lots_balance`:
  - রিকুইজিশন ছাড়া রিটার্ন, দেওয়ার বেশি রিটার্ন, আগের তারিখ, ইউনিট ছাড়া ট্রান্সফার আর ব্যালান্সের বেশি ট্রান্সফার আটকায়।
  - রিটার্ন ১২ কেজি → ৬৭.৫, ট্রান্সফার ২০ → ৪৭.৫, এডিট করে ২৫ → ৪২.৫, রিটার্ন মুছলে → ৩০.৫।
- `test_who_may_keep_the_store`: STORE_MANAGER নোট তুলতে পারে। LINE_CHIEF দেখতে পারে, রোল তুলতে পারে না। TECHNICIAN কিছুই পায় না।

**Regression:** `FinishingQcProcessTest` (৫টা, ১২০টা), `FinishingProductionTest` (৬টা, ২০৬টা), `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/fst01`), dev অ্যাপে (Delta Apparels):**
- **Store Receipt:**
  - FFD-2026110001: যে স্টোরে যাবে Fabric store (DHK-FAB)।
  - ব্যাচ B-26110011 বাছতেই অর্ডার, রং Black, লট, ব্যাচ নং, ডায়া, গ্রে ৯৯.২ আর ৬ রোল বসেছে।
  - হাতে দেওয়া: ফেব্রিকের বিবরণ "30/1 combed, 180 GSM", শেড SH-2, GSM ১৮০, ডায়া ৭২, ফিনিশ ৯৭.৯, রেট ১২।
  - রেজিস্টার থেকে Send আর তারপর Receive করা হয়েছে, এখন অবস্থা Received।
- **Process-Wise Delivery (18/02):** WIP=Delivered দিয়ে খুঁজলে B-26110011 দেখায়।
- **Racking:** FSL-2026110001-এ রোল r-01 সেভ হয়েছে R-01 হিসেবে: ১৬.৫ কেজি, র‍্যাক R-A1, বিন B-03। বারকোড নিজে বসেছে FR2611000001।
- **Issue to Cutting:**
  - CR-101 (বড় হাতের অক্ষরে), স্টোর Fabric store, Work Style SS27-TEE01, Partial।
  - লট বাছতেই চাহিদা = ব্যালান্স ৯৭.৯ বসেছে। ইস্যু ৪০ কেজি, ২ রোল।
- **Return & Transfer:** FSR-2026110001: CR-101 থেকে ৫ কেজি ফেরত, কারণ "Cut plan reduced"। লটের ব্যালান্স হয়েছে ৯৭.৯ − ৪০ + ৫ = ৬২.৯।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।
- **প্ল্যানে যা খেয়াল করা হয়েছে:**
  - Fabric Type বাছার সময় "Single Jersey" লেখা দিয়ে প্রথমে "Lycra Single Jersey" মিলেছে, তাই dev-এর লাইনে সেটাই আছে।
  - নতুন টেস্টে কিছু পরীক্ষা ক্ষণে ক্ষণে ফেল হচ্ছিল। কারণ: একই সেকেন্ডে দুটো move হলে "শেষটা কোনটা" ঠিক বোঝা যেত না। id দিয়ে ক্রম ঠিক করা হয়েছে (`FinReprocessAdviceResource`, `FinSc::callBack`), আর 18/03 ও 18/04-এর টেস্ট আবার পাস করেছে।

**প্রিন্ট:** Brickland-এর এই চারটা স্ক্রিনে Print বাটন নেই, শুধু Excel।

## ১০. খোলা প্রশ্ন

১. **সাধারণ স্টোর লেজারের সাথে যোগ:** ফিনিশড ফেব্রিকের লট আর ব্যালান্স এখন Finishing মডিউলের নিজের লেজারে থাকে। Store & Material-এর স্টক রিপোর্টেও এগুলো দেখাতে হবে কিনা জানাবেন।

২. **রোল আর ইস্যু:** কাটিংয়ে ইস্যু লট আর কেজি ধরে হয়, রোলের বারকোড ধরে নয়, আর রোলের অবস্থা নিজে থেকে Issued হয় না। ইস্যুর সময় বারকোড স্ক্যান করে রোল ধরে দেওয়া লাগবে কিনা জানাবেন।

৩. **রিকুইজিশনের অবস্থা:** Brickland-এর মতো অবস্থা হাতে বাছা হয় (Draft/Partial/Complete/Cancelled), আর নিয়ম দিয়ে যাচাই হয়। ইস্যু থেকে অবস্থা নিজে বসবে কিনা জানাবেন।

৪. **নোট রিসিভ না হলে:** পাঠানো নোট স্টোর রিসিভ না করা পর্যন্ত ব্যাচ বোর্ডে Delivered থাকে, আর স্টকে কিছু থাকে না। পাঠানো নোট বাতিল বা ফেরতের ধাপ লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/fst01`):**
  - Store Receipt আর Issue to Cutting-এর এন্ট্রি খোলে তালিকার Add New দিয়ে।
  - Brickland-এর কথা: "Sending a delivery raises a draft receipt in the receiving store. The store posts it, and only then does the stock exist." তাই এখানে Send আর Receive দুটো আলাদা ধাপ।
  - Racking আর Return & Transfer scaffold, তাই তাদের এক লাইনের বিবরণ থেকে পুরোটা বানানো হয়েছে।
- **ব্যালান্স কোথাও রাখা হয় না:** প্রতিটা লটের ব্যালান্স সবসময় `fin_store_moves`-এর যোগফল থেকে গোনা হয়।
- **Finishing Line-এর ধারা পূর্ণ:** ডাই হাউস থেকে রিসিভ → প্রোডাকশন → S/C → QC → স্টোর → কাটিং, সবগুলো একই কার্ডের ট্রেইলে জোড়া। 18/06 Report বাকি, নির্ভরতা পূরণ হয়েছে।

**কাপড় দৈর্ঘ্যে (২০২৬-১১-১৭, ধাপ ১):** স্টোর এখন দৈর্ঘ্য (কোম্পানির একক, ডিফল্ট গজ) আর kg দুটোই রাখে; ব্যালান্স দুটোই `fin_store_moves`-এর যোগফল (`qty_kg`, `qty_m`)।
- **নতুন কলাম:** `fin_store_delivery_lines.finish_m`, `fin_store_lots.received_m`, `fin_store_rolls.length_m`, `fin_store_moves.qty_m`, `fin_cut_requisition_lines.req_m` / `issue_m`, `fin_store_returns.qty_m`।
- **ডেলিভারি:** লাইনে ফিনিশড দৈর্ঘ্য; না দিলে GSM/Dia দিয়ে kg থেকে। রিসিভে লটের `received_m`।
- **কাটিং রিকুইজিশন:** চাওয়া আর issue দৈর্ঘ্যে বা kg-তে; যেটা নেই সেটা লটের নিজের kg/মিটার অনুপাতে (না থাকলে GSM/চওড়া; তাও না থাকলে "kg দিন" ত্রুটি)। দৈর্ঘ্য রাখা লটে issue দৈর্ঘ্যের ব্যালান্স ছাড়ালে আটকায়; COMPLETE যাচাই দৈর্ঘ্যে।
- **Return & Transfer:** পরিমাণ দৈর্ঘ্যে দেওয়া যায়, kg নিজে আসে; ট্রান্সফার লটের দৈর্ঘ্যের ব্যালান্স ছাড়ালে আটকায়।
- **টেস্ট:** `FinishingFabricStoreTest::test_a_lot_kept_by_length_is_issued_and_returned_by_length` — ৪০০ গজের লট, ১০০ গজ issue → ২৩.৮৭৫ kg, ব্যালান্স ৩০০; ২০ গজ ফেরত → ৩২০; ৩৩০ গজ ট্রান্সফার আটকায়; কাটিংয়ের প্যানেলে issue ১০০ গজ। পুরোনো টেস্টের তারিখ আজকের সাপেক্ষে করা হয়েছে (আগে নির্দিষ্ট তারিখ ছিল, লট আজ রিসিভ হলে ফেল করত)। Cutting + এই দুই ফাইল — ১৭টা পাস।
