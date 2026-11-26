# DF Production

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `18/02`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Batch Receipt from Dye House | `/m/dyeing-finishing/batch-receive-from-dyeing` | 1 | 0 | 0 |
| 2 | Machine-Wise Production Entry | `/m/dyeing-finishing/df-production-entry-mc-wise` | 2 | 36 | 1 |
| 3 | Process-Wise Delivery | `/m/dyeing-finishing/df-process-wise-delivery` | 1 | 6 | 0 |
| 4 | Batch Re-Process Return | `/m/dyeing-finishing/batch-return-to-re-process` | 1 | 0 | 0 |
| 5 | Finishing Production Amendment | `/m/dyeing-finishing/df-production-amendment` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Batch Receipt from Dye House

`/m/dyeing-finishing/batch-receive-from-dyeing`

**ভিউ**
> 05 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Machine-Wise Production Entry

`/m/dyeing-finishing/df-production-entry-mc-wise`

**ট্যাব: Dyeing Finishing — Open Line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sheet | select |  |  |
| Production Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Prod. Floor | select |  | Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Shift* | select | ✔ | Select…, A, B, C, General |
| Line | text |  |  |
| Remarks | textarea |  |  |
| Batch | select |  |  |
| Machine | select |  |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Input (Kg) | number |  |  |
| Output (Kg) | number |  |  |
| Rolls | number |  |  |
| Fin. GSM | number |  |  |
| Fin. Dia | number |  |  |
| Load Time | datetime-local |  |  |
| Unload Time | datetime-local |  |  |
| Remarks | text |  |  |
- সেকশন: Dyeing Finishing — Open Line, Machine Wise Production
- বাটন/অ্যাকশন: Production Sheets 0, Dyeing Finishing — Open Line, Dyeing Finishing — Tube Line, Add Row, Cancel, Back To List, Clear, Save Sheet

**ট্যাব: Dyeing Finishing — Tube Line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sheet | select |  |  |
| Production Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Prod. Floor | select |  | Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Shift* | select | ✔ | Select…, A, B, C, General |
| Line | text |  |  |
| Remarks | textarea |  |  |
| Batch | select |  |  |
| Machine | select |  |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Input (Kg) | number |  |  |
| Output (Kg) | number |  |  |
| Rolls | number |  |  |
| Fin. GSM | number |  |  |
| Fin. Dia | number |  |  |
| Load Time | datetime-local |  |  |
| Unload Time | datetime-local |  |  |
| Remarks | text |  |  |
- সেকশন: Dyeing Finishing — Tube Line, Machine Wise Production
- বাটন/অ্যাকশন: Production Sheets 0, Dyeing Finishing — Open Line, Dyeing Finishing — Tube Line, Add Row, Cancel, Back To List, Clear, Save Sheet

**ডায়ালগ: Add Row**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing sheet | select |  |  |
| Production Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Prod. Floor | select |  | Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Shift* | select | ✔ | Select…, A, B, C, General |
| Line | text |  |  |
| Remarks | textarea |  |  |
| Batch | select |  |  |
| Machine | select |  |  |
| Process | select |  | Heat-Set (HS), Dewatering (DW), Drying (DRY), Stentering (ST), Open Compacting (OC), Calendering (CAL), Brush Only (BR), |
| Input (Kg) | number |  |  |
| Output (Kg) | number |  |  |
| Rolls | number |  |  |
| Fin. GSM | number |  |  |
| Fin. Dia | number |  |  |
| Load Time | datetime-local |  |  |
| Unload Time | datetime-local |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A production sheet with no rows on it records no production.

#### Process-Wise Delivery

`/m/dyeing-finishing/df-process-wise-delivery`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Dia Type | select |  | Open, Tube, Both |
| Booking Type | select |  | In House, Sub Contract, Sample |
| Buyer A/C | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Batch# | text |  | Search… |
- সেকশন: Search
- বাটন/অ্যাকশন: --Select WIP--, --Style --, -- Color --, Show, Excel, Reset

#### Batch Re-Process Return

`/m/dyeing-finishing/batch-return-to-re-process`

**ভিউ**
> 08 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Finishing Production Amendment

`/m/dyeing-finishing/df-production-amendment`

**ভিউ**
> 09 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **18/01-এর মাস্টার:** প্রসেস টাইপ (`fin_process_types`, WIP স্টেজসহ), মেশিন (`fin_machines`) আর Machine-Process Mapping (`fin_machine_processes`: কোন dia-তে কোন মেশিন কোন প্রসেস চালায়, আর ব্যাচ কোন স্টেজে ঢোকে ও বের হয়)।
- **Dye House-এর ব্যাচ:** `dye_batches` আর `dye_productions` (17/03, 17/04)। যে ব্যাচের অন্তত একটা ডাইং প্রোডাকশন আছে, শুধু সেটাই ডাই করা ধরা হয়।
- **শেয়ার করা অংশ:** `DyeBatches::nextNumber` (নম্বর), chem-store-এর `LinesCard`/`EditorFoot`/`usePaper` (এডিটর), `TablePdf` (PDF)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/Finishing/Database/Migrations/2027_02_03_000100_create_fin_production.php`:
- **`fin_batch_receipts`:** ব্যাচের ফিনিশিং কার্ড।
  - receipt_no (FBR-YYYYMM####), receive_date, batch (dye_batches), dia_type (OPEN/TUBE/BOTH), booking_type (IN_HOUSE/SUB_CONTRACT/SAMPLE)
  - challan_no, challan_qty_kg, challan_rolls, recv_qty_kg, recv_rolls, floor
  - stage (WIP স্টেজ), stage_at, last_load_at, last_machine, last_process
  - status (ON_FLOOR/RETURNED), remarks
- **`fin_production_sheets`:** sheet_no (DFP-YYYYMM####), line (OPEN/TUBE), production_date, floor, shift (A/B/C/GENERAL), remarks, status (DRAFT/POSTED), posted_at, posted_by।
- **`fin_production_lines`:** sheet, receipt (ব্যাচ), machine, process, input_kg, output_kg, rolls, fin_gsm, fin_dia, load_time, unload_time, remarks।
- **`fin_process_deliveries`:** ব্যাচের ট্রেইল। from_stage, to_stage, delivered_at, source (RECEIPT/SHEET/BOARD), sheet, remarks।
- **`fin_reprocess_returns`:** return_no (FRP-…), return_date, receipt (প্রতি কার্ডে একটা), reason (SHADE/GSM/WIDTH/OTHER), qty_kg, rolls, remarks।
- **`fin_production_amendments`:** amend_no (FPA-…), amend_date, line, input/output/machine/process-এর old আর new মান, reason।

## ৫. Workflow / Status ও Business Rules

- **Batch Receipt from Dye House** (Brickland-এ scaffold, পুরোটা বানানো হয়েছে):
  - শুধু সেই ব্যাচ নেওয়া যায় যা অন্তত একবার ডাই হয়েছে, OPEN (রিপ্লেস বা বন্ধ নয়) আর এখনো ফ্লোরে নেই।
  - রিসিভের তারিখ প্রথম ডাইংয়ের আগে হতে পারে না।
  - চালান নম্বর বড় হাতের অক্ষরে রাখা হয়। Booking Type ফাঁকা রাখলে স্যাম্পল ব্যাচ Sample হয়, বাকিগুলো In House।
  - কার্ড শুরু হয় Awaiting Load থেকে। পরে কোনো দিনের রিসিপ্ট এন্ট্রি করলে স্টেজের সময় হয় সেই দিনের শুরু, যাতে ট্রেইলের ক্রম ঠিক থাকে।
  - প্রোডাকশন এন্ট্রির পরে ব্যাচ বদলানো যায় না। প্রোডাকশন, বোর্ডে সরানো বা রিটার্ন থাকলে রিসিপ্ট মোছা যায় না। রিটার্ন হওয়া কার্ড শুধু পড়া যায়।
- **Machine-Wise Production Entry:**
  - প্রতিটা লাইনের (Open, Tube) নিজের ট্যাব। শিটের লাইন পরে বদলানো যায় না।
  - প্রতিটা সারিতে একটা ব্যাচ, একটা মেশিন, একটা প্রসেস।
  - ব্যাচ শুধু ফ্লোরে থাকা কার্ড থেকে, যার dia এই লাইনে চলে (Both দুটোতেই চলে)। কার্ডের রিসিভের তারিখ শিটের তারিখের আগে হতে হবে।
  - মেশিনকে এই লাইনে (বা Both-এ) প্রসেসে ম্যাপ করা থাকতে হবে।
  - আউটপুট ইনপুটের বেশি হতে পারে না, আর আনলোড লোডের আগে হতে পারে না। ভুল হলে "Row n: …" দেখায়।
  - Save Sheet শিটকে খসড়া রাখে, পুরো শিট আবার লেখা হয়। রেজিস্টার থেকে Post করলে প্রতিটা সারি, যে ক্রমে মেশিন থেকে নেমেছে (unload time) সেভাবে, তার ব্যাচকে ম্যাপিংয়ের next status-এ নিয়ে যায় আর ট্রেইলে লেখে।
  - পোস্ট করা শিট শুধু পড়া যায়। মোছা যায় শুধু খসড়া।
  - রেজিস্টার দেখায় ব্যাচ সংখ্যা, ইনপুট, আউটপুট, লস আর লস %।
- **Process-Wise Delivery:**
  - ফ্লোরে থাকা সব কার্ড দেখায় (ডেলিভারি হওয়াগুলো ছাড়া, যদি না WIP-এ Delivered বাছা হয়)।
  - Dia, WIP, Booking Type, বায়ার, স্টাইল, অর্ডার, রং আর ব্যাচ# দিয়ে খোঁজা যায়।
  - Spent Time সার্ভারে শেষ লোড থেকে গোনা হয়।
  - On Floor, On Hold আর Ready টাইলে চাপলে গ্রিড শুধু সেই ভাগ দেখায়।
  - Deliver বাটন ব্যাচকে অন্য যেকোনো স্টেজে নেয়, আর সেটা ট্রেইলে লেখে। Delivered আর At Sub-Contract শুধু নিজেদের ডেস্ক (18/05, 18/03) থেকে বসে।
  - Excel আর Pdf একই সারি লেখে।
  - S/C Prod. আর BT Trans. বাটন সেই স্ক্রিন মেনুতে আসলেই দেখা যাবে।
- **Batch Re-Process Return** (Brickland-এ scaffold):
  - শুধু ফ্লোরে থাকা কার্ড ফেরত যায়, যা ডেলিভারি হয়নি বা সাব-কন্ট্রাক্টে নেই।
  - রিসিভের বেশি কেজি বা রিসিপ্টের আগের তারিখ দেওয়া যায় না। কারণ Other হলে মন্তব্য লাগবে।
  - কার্ড RETURNED হয়, তাই ব্যাচ ফ্লোর ছাড়ে আর আবার ডাই হলে আবার রিসিভ করা যায়।
  - রিটার্নের কার্ড পরে বদলানো যায় না। ব্যাচ আবার রিসিভ না হওয়া পর্যন্তই রিটার্ন মোছা যায় (মুছলে কার্ড ফ্লোরে ফেরে)।
- **Finishing Production Amendment** (Brickland-এ scaffold):
  - শুধু পোস্ট করা শিটের লাইন সংশোধন হয়। খসড়া সরাসরি এডিট হয়।
  - নতুন ইনপুট আর আউটপুট দিতে হয়। মেশিন বা প্রসেস ফাঁকা রাখলে আগেরটাই থাকে, আর মেশিনকে লাইনে প্রসেসে ম্যাপ করা থাকতে হবে।
  - কিছু না বদলালে আটকায়। তারিখ শিটের আগে হতে পারে না।
  - লাইন নতুন মানে বদলায়, আর আগের মান সংশোধনে পাশাপাশি থাকে।
  - সংশোধন এডিট বা মোছা যায় না। আরও ঠিক করতে হলে আরেকটা সংশোধন।
  - সংশোধন ব্যাচের স্টেজ বদলায় না, কারণ বোর্ড আগেই কাজ করেছে।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `finishing.production.view` / `finishing.production.manage`
  - FACTORY_ADMIN, FACTORY_MANAGER আর LINE_CHIEF দুটোই পায়। STORE_MANAGER শুধু view পায়।
- **Screen-kit resource:** `/api/v1/erp/fin-batch-receipts`, `/api/v1/erp/fin-reprocess-returns`, `/api/v1/erp/fin-production-amendments` (amendment মোছা যায় না, 405)
- **API (`FinProductionApiController`):**
  - `GET /finishing/production/options`
  - `GET|POST /finishing/production-sheets`
  - `GET|PUT|DELETE /finishing/production-sheets/{no}`
  - `POST /finishing/production-sheets/{no}/post`
  - `GET /finishing/delivery-board`
  - `POST /finishing/delivery-board/{receiptNo}/deliver`
  - `GET /finishing/delivery-board/export/{pdf|xlsx}`
- **মেনু:** Finishing Line-এর নতুন ফোল্ডার DF PRODUCTION, ৫টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `finishing-line/[screen]`-এ, `prod-screens.js` থেকে। পাতা স্ক্রিন অনুযায়ী নিজের ভাষার গ্রুপ (`finishing.prod`) আর পারমিশন বাছে।
- **কম্পোনেন্ট:** `components/finishing/df-production.jsx`-এ `ProductionSheetEditor`, `SheetRegister`, `DeliveryBoard`।
- **Machine-Wise Production Entry:**
  - Brickland-এর মতো তিন ট্যাব: Production Sheets, Dyeing Finishing — Open Line, Dyeing Finishing — Tube Line। পাতা Open Line ট্যাবে খোলে।
  - এডিটরে Existing sheet, হেডার, Machine Wise Production (Add Row / Cancel), Back To List / Clear / Save Sheet।
  - প্রসেসের তালিকা বাছাই করা মেশিনের ম্যাপিং দেখে ছোট হয়। ব্যাচ বাছলে তার কেজি আর রোল বসে।
  - রেজিস্টারে Line / Shift / Status ফিল্টার, New Sheet আর প্রতিটা সারিতে Edit / Post / Delete।
- **Process-Wise Delivery:** Brickland-এর Search কার্ড (Show, Excel, Pdf, S/C Prod., BT Trans., Reset), On Floor / On Hold / Ready টাইল, আর গ্রিড যার প্রতিটা সারিতে Deliver ডায়ালগ।
- **বাকি তিনটা:** Records আর Add New ট্যাব, ফিল্টার, Edit/Remove (Amendment-এ নেই)।
- **শেয়ার করা অংশ:** `LinesCard` এখন `datetime` কলামও নেয় (Load/Unload Time)।
- **PDF:** `frontend/src/app/api/finishing/delivery-board/[format]/route.js` API-র PDF বা workbook সরাসরি পাঠায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend**
- `app/Modules/Finishing/Database/Migrations/2027_02_03_000100_create_fin_production.php`
- `app/Modules/Finishing/Models/`: `FinBatchReceipt`, `FinProductionSheet`, `FinProductionLine`, `FinProcessDelivery`, `FinReprocessReturn`, `FinProductionAmendment`
- `app/Modules/Finishing/Support/FinFloor.php`
- `app/Modules/Finishing/Resources/FinProductionResource.php`, `FinBatchReceiptResource.php`, `FinReprocessReturnResource.php`, `FinProductionAmendmentResource.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinProductionApiController.php`
- `app/Modules/Finishing/Routes/api.php` (বদলানো)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/finishing.php`, `lang/bn/finishing.php` (বদলানো: `prod` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/prod-screens.js` (নতুন), `page.js` আর `actions.js` (বদলানো)
- `frontend/src/components/finishing/df-production.jsx`
- `frontend/src/app/api/finishing/delivery-board/[format]/route.js`
- `frontend/src/components/dyehouse/chem-store.jsx` (বদলানো: `LinesCard`-এ datetime কলাম)

**Test**
- `tests/Feature/Finishing/FinishingProductionTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Finishing/FinishingProductionTest.php`):** test2 DB-তে পাস, ৬টা টেস্ট, ২০৬টা assertion।
- `test_a_dyed_batch_is_received_onto_the_floor_once`:
  - ডাই না হওয়া ব্যাচ, ডাইংয়ের আগের তারিখ আর শূন্য কেজি আটকায়।
  - FBR-2026110001 তৈরি হয়: Awaiting Load, In House, চালান DH-101, পার্থক্য −১.৫। দ্বিতীয়বার রিসিভ আটকায়।
  - স্যাম্পল ব্যাচ Sample হিসেবে বুক হয়।
  - কিছু না হওয়া কার্ড এডিট আর মোছা যায়, কিন্তু স্টেজ হাতে বদলানো যায় না।
- `test_a_sheet_is_saved_as_a_draft_and_posting_moves_each_batch`:
  - লাইন ছাড়া শিট, ভুল লাইনের ব্যাচ, ম্যাপ না করা মেশিন, ইনপুটের বেশি আউটপুট, আনলোড আগে আর রিসিপ্টের আগের তারিখ আটকায়।
  - DFP-2026110001 খসড়া। লাইন বদলানো আটকায়। রেজিস্টারে ১৯৮ → ১৯৩, লস ৫ (২.৫৩%)।
  - পোস্টের পরে কার্ড COMPACTING-এ। ট্রেইল RECEIPT → STENTERING → COMPACTING, unload-এর ক্রমে।
  - পোস্ট করা শিট এডিট, মোছা আর আবার পোস্ট আটকায়। রিসিপ্ট মোছা আর ব্যাচ বদলানোও আটকায়। খসড়া মোছা যায়।
- `test_the_floor_board_and_passing_a_batch_on`:
  - টাইল আর শেষ প্রসেস/মেশিন ঠিক দেখায়। Spent Time > ০। রঙের তালিকা আসে। Dia আর ব্যাচ/রং দিয়ে খোঁজা যায়।
  - Delivered আর একই স্টেজ আটকায়। Ready for Delivery-তে নেওয়ার পরে Ready টাইল ১ হয়। PDF আর xlsx আসে।
- `test_a_batch_returned_for_reprocessing_leaves_the_floor_and_comes_back`:
  - বেশি কেজি, আগের তারিখ আর মন্তব্য ছাড়া Other আটকায়।
  - রিটার্ন মুছলে কার্ড ফ্লোরে ফেরে। আবার রিটার্ন করলে কার্ড RETURNED হয়: এডিট আটকায়, বোর্ডে দেখায় না, শিটে নেওয়া যায় না।
  - ব্যাচ আবার রিসিভ (FBR-…0003) হওয়ার পরে রিটার্ন মোছা আটকায়।
- `test_a_posted_line_is_amended_with_its_old_figures_kept`:
  - খসড়ার লাইন, কিছু না বদলানো, বেশি আউটপুট, ম্যাপ না করা প্রসেস আর আগের তারিখ আটকায়।
  - FPA-2026110001 আগের আর নতুন মান রাখে, আর লাইন আর রেজিস্টারের লস বদলায়। এডিট আটকায়, মোছা 405।
- `test_who_may_run_the_floor`: STORE_MANAGER দেখতে পারে, শিট তুলতে পারে না। LINE_CHIEF রিসিভ করতে পারে। TECHNICIAN কিছুই পায় না।

**Regression:** `FinishingMasterTest` (৩টা, ৭৩টা), `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/fp01`–`fp03`), dev অ্যাপে (Delta Apparels):**
- **Batch Receipt:**
  - FBR-2026110001: S-26110001, Tube, ৩৯.৫ kg, ৩ রোল। স্যাম্পল ব্যাচ, তাই Booking নিজে Sample হয়েছে।
  - FBR-2026110002: B-26110011, Open, চালান DH-CH-501 (বড় হাতের অক্ষরে)। চালান ১০০, রিসিভ ৯৯.২, পার্থক্য −০.৮। ৬ রোল, 1st Floor।
- **Machine-Wise Production Entry (Open Line):**
  - DFP-2026110001: শিফট A, 1st Floor, দুটো সারি।
  - MFL-01-এ Stentering ৯৯.২ → ৯৮.৪ (GSM ১৮০, ডায়া ৭২, ০৮:০০–০৯:৪০)।
  - CP-01-এ Open Compacting ৯৮.৪ → ৯৬.৯ (১০:০০–১১:১৫)।
  - ব্যাচ বাছলে কেজি আর রোল বসেছে। প্রসেসের তালিকা মেশিনের ম্যাপিং অনুযায়ী ছোট হয়েছে।
  - রেজিস্টার: ১৯৭.৬ → ১৯৫.৩, লস ২.৩ (১.১৬%), Draft।
- **Post:** রেজিস্টার থেকে কনফার্ম করে পোস্ট করা হয়েছে। শিট Posted হয়ে শুধু পড়া যায়, আর ফুটারে Amendment-এর কথা দেখায়।
- **Process-Wise Delivery:**
  - B-26110011 Compacting-এ, শেষ প্রসেস Open Compacting, শেষ মেশিন CP-01, শেষ লোড ০৬ Nov ১০:০০, Spent Time ২ঘ ২৩মি।
  - Deliver ডায়ালগে QC Inspection-এ নেওয়া হয়েছে।
  - Dia=Tube খুঁজলে শুধু S-26110001 আসে।
  - Pdf ২০০, application/pdf: Process-Wise Delivery শিরোনাম, দুটো সারি, Total ১৩৮.৭ kg / ৯ রোল, landscape।
  - S/C Prod. আর BT Trans. লুকানো, কারণ 18/03 আর 18/05 এখনো মেনুতে নেই।
- **Batch Re-Process Return:** FRP-2026110001: S-26110001, Shade, ৩৯.৫ kg, ৩ রোল। রিসিপ্ট Returned হয়েছে।
- **Finishing Production Amendment:**
  - FPA-2026110001: DFP-2026110001-এর Stentering সারির আউটপুট ৯৮.৪ → ৯৭.৯, কারণসহ।
  - রেজিস্টারের আউটপুট ১৯৪.৮ আর লস ২.৮ (১.৪২%) হয়েছে।
- **প্ল্যানে ঠিক করা:**
  - Delta-র ফ্লোরের নাম "1st Floor", "Finishing" নয়।
  - CSS-এর text-matches-এ `\*` আর `\(` ব্যাকস্ল্যাশ হারায়, তাই "Shift*" আর "Output (Kg)" প্রথমে মেলেনি। `.?` দিয়ে ঠিক করা হয়েছে।
  - Amendment-এর লাইনে মেশিনের নাম নেই, তাই প্রসেসের নাম দিয়ে বাছা হয়েছে।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে (কোনো শিট বা মাস লক করা হয়নি)।

**প্রিন্ট:** Brickland-এর Pdf লিংক তার print register-এ যায়। আমাদেরটা সার্ভারের `TablePdf` (Process-Wise Delivery)। PDF ধরে এনে দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **ব্যাচ কখন হস্তান্তর হয়:** এখন যে ব্যাচের অন্তত একটা ডাইং প্রোডাকশন আছে, সেটাই রিসিভ করা যায়। Brickland-এর স্ক্রিন scaffold, তাই নিয়ম ধরে নেওয়া হয়েছে। Dyeing Q.C (17/08)-এর Batch Completion বা শেড অ্যাপ্রুভাল লাগবে কিনা জানাবেন।

২. **স্টেজের ক্রম:** পোস্ট করলে ব্যাচ ম্যাপিংয়ের next status-এ যায়, ব্যাচ এখন যে স্টেজে আছে তা ম্যাপিংয়ের batch status-এর সাথে না মিললেও। ক্রম কড়াভাবে মানাতে হবে কিনা (যেমন Stentering-এর আগে Compacting আটকানো) জানাবেন।

৩. **Sub Contract বুকিং:** Dye House ব্যাচের সাথে S/C In-House প্রোগ্রামের (17/10) এখনো কোনো যোগ নেই। তাই রিসিপ্টে Sub Contract হাতে বাছতে হয়। প্রোগ্রাম থেকে নিজে বসবে কিনা জানাবেন।

৪. **সংশোধনের পরে স্টেজ:** মেশিন বা প্রসেস সংশোধন করলে ব্যাচের স্টেজ বদলায় না। বদলানো উচিত কিনা জানাবেন।

৫. **Process-Wise Delivery-র কলাম:** Brickland-এর ডেমোতে বোর্ডে কোনো সারি ছিল না, তাই কলাম (ব্যাচ, রিসিপ্ট, বায়ার, স্টাইল, অর্ডার, রং, dia, বুকিং, কেজি, রোল, WIP, শেষ প্রসেস/মেশিন/লোড, Spent Time) বানানো হয়েছে হিন্ট আর ফিল্টার দেখে। আরও কোনো কলাম লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/fp01`–`fp03`):**
  - Machine-Wise Production Entry আর Process-Wise Delivery লাইভ দেখা হয়েছে।
  - Pdf যায় Brickland-এর print register-এ, S/C Prod. যায় `sc-delivery-challan-gatepass`-এ (18/03), BT Trans. যায় `finish-fabric-store-receive`-এ (18/05)।
  - বাকি তিনটা স্ক্রিন Brickland-এ scaffold, তাই তার এক লাইনের বিবরণ থেকে পুরোটা বানানো হয়েছে।
- **কার্ডেই স্টেজ:** ব্যাচের WIP স্টেজ রিসিপ্টে (ফিনিশিং কার্ড) থাকে। প্রতিটা সরানো `fin_process_deliveries`-এ লেখা হয়, তাই পরের ধাপের ডেস্কগুলো (18/03-এর S/C চালান, 18/05-এর স্টোর রিসিভ) একই কার্ড সরাবে।
- **সময়:** Load/Unload Time ঢাকার সময় হিসেবে নিয়ে UTC-তে রাখা হয়, আর ঢাকার সময়ে দেখানো হয়।
