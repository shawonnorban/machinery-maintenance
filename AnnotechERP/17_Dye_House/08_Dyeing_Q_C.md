# Dyeing Q.C

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/08`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Check Roll Quality Inspection | `/m/dyeing/check-roll-inspection` | 1 | 38 | 0 |
| 2 | Batch Programme Completion | `/m/dyeing/complete-batch-program` | 1 | 9 | 0 |
| 3 | Rib & Collar Shade Approval | `/m/dyeing/rib-collar-shade-approval` | 2 | 23 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Check Roll Quality Inspection

`/m/dyeing/check-roll-inspection`

**ট্যাব: Check Roll Inspection**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Batch Details | search |  | Scan Batch Card Barcode… |
| Existing inspection | select |  |  |
| Req No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Batch No | text |  | Scan Batch Card Barcode |
| Roll No* | text | ✔ | Scan Fabric Roll Barcode |
| Weights | text |  |  |
| M/C No | text |  |  |
| Model/Combo | text |  |  |
| Color | text |  |  |
| Color Group | text |  |  |
| Buyer | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Lab-dip # | text |  |  |
| D Method | text |  |  |
| Liqur Ratio | text |  |  |
| Crease Mark | checkbox |  |  |
| Softenar Spot | checkbox |  |  |
| Shade Not Ok | checkbox |  |  |
| Uneven Dyeing | checkbox |  |  |
| Running Shade | checkbox |  |  |
| Wrinkle Mark | checkbox |  |  |
| Color Spot | checkbox |  |  |
| Patches | checkbox |  |  |
| Dia Mark | checkbox |  |  |
| Shade Not Ok (YD/MELL) | checkbox |  |  |
| PPP Contamination | checkbox |  |  |
| Thick and Thin | checkbox |  |  |
| Roll To Roll Shade Variation | checkbox |  |  |
| Bad Smell | checkbox |  |  |
| Sinker Mark | checkbox |  |  |
| Dead Cotton | checkbox |  |  |
| Neps | checkbox |  |  |
| Hairiness | checkbox |  |  |
| Slub | checkbox |  |  |
| Yarn Contra | checkbox |  |  |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Batch No, Lot, Color
- সেকশন: Batch Details, Check Roll Inspection, Search Data, Mark Defects, Assessment
- বাটন/অ্যাকশন: Check Roll Inspection, -- Select Department --, Clear, Save inspection

#### Batch Programme Completion

`/m/dyeing/complete-batch-program`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Color Name | select |  | All |
| Process Type | select |  | All |
| Req. Store | select |  | All |
| Batch No | search |  | B-26070008 |
| Back From DF | select |  | All, Sent back, Not sent back |
| Search Batch programmes and where each has got to, with the ones sent back from  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Req Date, Color Name, Process Type, Req. Store, Batch No, ACTION
- বাটন/অ্যাকশন: Clear, Export, Requisition No, Req Date, Color Name, Process Type, Req. Store, Batch No

#### Rib & Collar Shade Approval

`/m/dyeing/rib-collar-shade-approval`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | search |  |  |
| Color | select |  |  |
| Batch # | search |  | B-26061108 |
| QC Status | select |  | Pending, Passed, Hold Dyeing, Rejected |
| Rcv Date from | date |  |  |
| Rcv Date to | date |  |  |
| Search Rib and collar shade checked against the body fabric, batch by batch and  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Batch No, Lot, SL#, B Qty, RP Qty, Rcv Date, Aprv Date, Buyer Name, Style/Order, Color, G. Qty, G. Roll, Fin Qty, Fin Roll, Created By, ACTION
- বাটন/অ্যাকশন: New shade approval, Clear, Export, Batch No, Lot, SL#, B Qty, RP Qty, Rcv Date, Aprv Date, Buyer Name, Style/Order, Color, G. Qty, G. Roll, Fin Qty, Fin Roll, Created By

**ট্যাব: New shade approval**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch No* | select | ✔ |  |
| Lot | text |  | 1-A |
| SL#* | number | ✔ |  |
| B Qty | number |  |  |
| RP Qty | number |  |  |
| Rcv Date | date |  |  |
| Aprv Date | date |  |  |
| Buyer Name | select |  | Select…, LPP (LPP) |
| G. Qty | number |  |  |
| G. Roll | number |  |  |
| Fin Qty | number |  |  |
| Fin Roll | number |  |  |
| QC Status* | select | ✔ | Select…, Pending, Passed, Hold Dyeing, Rejected |
| Remarks | textarea |  |  |
- সেকশন: New shade approval
- বাটন/অ্যাকশন: New shade approval, Select…, Reset, Save shade approval

**ডায়ালগ: New shade approval**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch No* | select | ✔ |  |
| Lot | text |  |  |
| SL#* | number | ✔ |  |
| B Qty | number |  |  |
| RP Qty | number |  |  |
| Rcv Date | date |  |  |
| Aprv Date | date |  |  |
| Buyer Name | select |  | Select…, LPP (LPP) |
| G. Qty | number |  |  |
| G. Roll | number |  |  |
| Fin Qty | number |  |  |
| Fin Roll | number |  |  |
| QC Status* | select | ✔ | Select…, Pending, Passed, Hold Dyeing, Rejected |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch No is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/03–17/07-এর ব্যাচ কার্ড আর প্রোডাকশন:** কোন ব্যাচ মেশিনে উঠেছে, আর তার শেষ প্রোডাকশনের M/C, Model/Combo, Lab-dip, লিকার রেশিও আর By-Pass Check Roll।
- **17/05-এর রিকুইজিশন:** Batch Programme Completion-এর সারি হলো BATCH_PROGRAM আর REPROCESS রিকুইজিশন। Dye Batch Re-Process-এর "Back" থেকে আসে "Back From DF"।
- **17/06-এর editor অংশ:** `Field`, `Group`, `ChemRegister`।
- **মাস্টার:** `departments` (দায়ী বিভাগ), বায়ার, অর্ডার, কালার।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_31_000100_create_dye_quality_control.php`:
- **`dye_check_roll_inspections`:** inspection_no (CRI-YYYYMM####), inspection_date, batch, roll_no (প্রতি ব্যাচে ইউনিক), defects (JSON, ২০টা কোড থেকে), department (দায়ী বিভাগ), result PASSED/HELD, remarks।
- **`dye_batch_completions`:** requisition (ইউনিক), remarks, completed_by।
- **`dye_shade_approvals`:** batch, lot_no, sl_no (প্রতি ব্যাচে ইউনিক), b_qty, rp_qty, rcv_date, aprv_date, buyer, order, colour, g_qty, g_roll, fin_qty, fin_roll, status PENDING/PASSED/HOLD_DYEING/REJECTED, remarks, created_by।

## ৫. Workflow / Status ও Business Rules

- **Check Roll Inspection:**
  - বাঁ দিকে যেসব খোলা কার্ড মেশিনে উঠেছে। স্ক্যান বক্সে নম্বর লিখে Enter দিলে খোলে।
  - কার্ড আর শেষ প্রোডাকশন থেকে বিবরণ নিজে বসে।
  - রোল নম্বর লাগবে, বড় হাতের অক্ষরে রাখা হয়। প্রতি ব্যাচে একটা রোল একবারই পরীক্ষা হয়।
  - কিছু টিক না দিলে PASSED। কিছু টিক দিলে HELD, আর তখন দায়ী বিভাগ বাধ্যতামূলক।
  - আগের পরীক্ষা খুলে আবার দেওয়া যায়। ত্রুটি সরালে পাস হয়, আর বিভাগ মুছে যায়।
  - যে ব্যাচ মেশিনে ওঠেনি সেটা আটকায়।
- **Rib & Collar Shade Approval:**
  - শুধু মেশিনে ওঠা ব্যাচ। প্রতি ব্যাচে SL# একবার।
  - লট, বায়ার, স্টাইল আর কালার ফাঁকা রাখলে কার্ড থেকে বসে।
  - Passed হলে অনুমোদনের তারিখ না দিলে আজকের তারিখ বসে। অনুমোদনের তারিখ রিসিভের আগে হতে পারে না।
- **Batch Programme Completion:**
  - BATCH_PROGRAM আর REPROCESS রিকুইজিশন (বাতিল ছাড়া) দেখায়: QC ধাপের অবস্থা, Back From DF আর Status।
  - Complete-এর আগে এক এক করে ধাপ দেখা হয়:
    1. প্রোডাকশন হয়েছে।
    2. অন্তত একটা চেক রোল পরীক্ষা হয়েছে। প্রোডাকশন By-Pass Check Roll হলে এটা লাগে না।
    3. কোনো রোল hold-এ নেই।
    4. কোনো রিব বা কলার শেড Passed ছাড়া অন্য অবস্থায় নেই।
  - কোনো ধাপ বাকি থাকলে আটকায় আর সেই ধাপের নাম বলে।
  - ব্যাচ ছাড়া রিকুইজিশন আর একই রিকুইজিশন দুবার Complete করা যায় না।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:** `dyehouse.qc.view` আর `dyehouse.qc.manage`, দুটোই FACTORY_ADMIN আর FACTORY_MANAGER পায়।
- **Screen-kit resource:** `/api/v1/erp/dye-shade-approvals`
- **API:**
  - `GET /dyehouse/qc/options`
  - `GET|POST /dyehouse/check-roll-inspections`, `PUT /dyehouse/check-roll-inspections/{no}`
  - `GET /dyehouse/batches/{no}/check-roll`
  - `GET /dyehouse/batch-completions`, `POST /dyehouse/batch-completions/{reqNo}`
- **মেনু:** Dye House-এর নতুন ফোল্ডার DYEING Q.C, ৩টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `dye-house/[screen]`-এ, `qc-screens.js` থেকে। কম্পোনেন্ট `components/dyehouse/check-roll-inspection.jsx`।
- **Check Roll Inspection:**
  - Records আর Check Roll Inspection ট্যাব। বাঁ দিকে ব্যাচের তালিকা আর স্ক্যান বক্স।
  - ডান দিকে অংশগুলো: Search Data, Batch Details, Mark Defects (২০টা টিক, চার কলামে), Assessment।
  - নিচে পাস বা hold-এর পূর্বাভাস, সবুজ বা হলুদ রঙে।
- **Batch Programme Completion:** ফিল্টার (তারিখ, Color, Process Type, Req. Store, Batch, Back From DF) আর গ্রিড। প্রতিটা সারিতে QC ধাপ, আর Complete বাটন নিশ্চিতকরণসহ।
- **Rib & Collar Shade Approval:** Records আর New shade approval ট্যাব। Edit আর Delete একই পাতায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_31_000100_create_dye_quality_control.php`
- `app/Modules/DyeHouse/Models/`: `DyeCheckRollInspection`, `DyeBatchCompletion`, `DyeShadeApproval`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeQualityApiController.php`
- `app/Modules/DyeHouse/Resources/DyeShadeApprovalResource.php`
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `qc` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/qc-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/check-roll-inspection.jsx` (নতুন)

**Test**
- `tests/Feature/DyeHouse/DyeHouseQualityTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseQualityTest.php`):** test2 DB-তে পাস, ৫টা টেস্ট, ১০৭টা assertion।
- `test_a_roll_passes_with_nothing_ticked_and_is_held_against_a_department_otherwise`:
  - মেশিনে না ওঠা ব্যাচ আটকায়। প্রোডাকশনের পরে ব্যাচটা তালিকায় আসে, আর শিটে বিবরণ দেখায়।
  - R-01 পাস হয় (CRI-…0001)। একই রোল দুবার দিলে আটকায়।
  - ত্রুটি দিয়ে বিভাগ না দিলে আটকায়, অজানা ত্রুটির কোড আটকায়।
  - HELD হলে দুটো ত্রুটি আর বিভাগ থাকে। আবার পরীক্ষায় ত্রুটি সরালে পাস হয়।
- `test_rib_and_collar_shade_is_approved_per_position_in_the_lot`:
  - মেশিনে না ওঠা ব্যাচ আর SL# ছাড়া আটকায়।
  - লট, বায়ার, স্টাইল আর কালার কার্ড থেকে বসে। একই SL# দুবার আটকায়। অনুমোদন রিসিভের আগে হলে আটকায়।
  - Passed হলে আজকের তারিখ বসে।
- `test_a_batch_programme_is_completed_only_once_every_qc_gate_has_passed`:
  - M/C Wash রিকুইজিশন আর ব্যাচ ছাড়া রিকুইজিশন আটকায়।
  - চারটা ধাপের বার্তা পরপর আসে, শেষে Complete হয়। দুবার Complete আটকায়।
  - CRR Back-এর পরে Back From DF হয় Yes।
- `test_a_run_that_by_passed_check_roll_needs_no_inspection`: By-Pass প্রোডাকশন হলে চেক রোল পরীক্ষা ছাড়াই Complete হয়।
- `test_who_may_inspect_and_complete`: FACTORY_MANAGER পারে। STORE_MANAGER আর TECHNICIAN পায় না।

**Regression:** `DyeHouseProductionBoardTest` (৬টা টেস্ট, ১৩৭টা assertion) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/qc01`), dev অ্যাপে:**
- **Check Roll:** B-26110011-এ R-01 কিছু টিক না দিয়ে পাস (CRI-2026110001)। R-02-এ Crease Mark আর Shade Not Ok টিক দেওয়া হয়েছে। বিভাগ ছাড়া সেভ আটকেছে, Dyeing Department দেওয়ার পরে HELD হয়েছে (CRI-2026110002, মন্তব্য "Body two shades dark")।
- **Shade Approval:** B-26110011, SL# 1, গ্রে ১০০ kg / ৫ রোল, ফিনিশড ৯৪.৫ kg / ৫ রোল, Passed। লট, বায়ার, স্টাইল আর কালার কার্ড থেকে বসেছে, আর অনুমোদনের তারিখ আজকের।
- **Completion:** IR-DC-2026110001 আর 0002 দুটোরই অবস্থা "Not produced yet"। IR-DC-2026110001-এ নিশ্চিত করে Complete চাপা হলে আটকেছে, DB-তে কোনো completion হয়নি। Back From DF Yes দেখায়, কারণ 17/05-এর CRR-2026110001 ফেরত পাঠানো হয়েছিল।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।

**প্রিন্ট:** Brickland-এর এই তিনটা স্ক্রিনে কোনো Print বাটন নেই।

## ১০. খোলা প্রশ্ন

১. **চেক রোলে GSM আর প্রস্থ:** Brickland-এর বর্ণনায় "shade, GSM, width and defects recorded" আছে, কিন্তু ফর্মে GSM বা প্রস্থের ঘর নেই। আমরা শুধু ত্রুটির টিক রেখেছি। মাপা GSM আর প্রস্থ লিখে Check Roll Parameter (17/07)-এর সহনসীমার সাথে মেলানো লাগবে কিনা জানাবেন।

২. **ইন্সপেকশনের নম্বর:** Brickland-এ "[Auto Gen]" লেখা থাকে, ফরম্যাট দেখা যায়নি। আমরা CRI-YYYYMM#### রেখেছি।

৩. **Batch Programme Completion-এর ধাপ:** Brickland বলে "once every QC gate has passed"। আমরা চারটা ধাপ ধরেছি: প্রোডাকশন, চেক রোল পরীক্ষা, কোনো রোল hold-এ নেই, কোনো শেড খোলা নেই। আর কোনো ধাপ (যেমন ফিনিশিং QC) লাগলে জানাবেন। Complete ফেরানোর (undo) কোনো উপায় রাখা হয়নি।

৪. **Sample কার্ড:** চেক রোলের তালিকায় স্যাম্পল কার্ডও আসে (S-…), যদি সেটা মেশিনে উঠে থাকে। শুধু বাল্ক রাখতে হলে জানাবেন।

৫. **Hold হওয়া রোলের পরে কী হয়:** এখন hold হলে শুধু রেকর্ড থাকে। রি-প্রসেস রিকুইজিশন বা check-roll return নিজে থেকে তৈরি হয় না। দরকার হলে জানাবেন।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/qc01`):** তিনটা স্ক্রিনের ফর্ম, হিন্ট, ২০টা ত্রুটির নাম আর বিভাগের তালিকা দেখা হয়েছে। demo-তে কোনো রেকর্ড নেই।
- **Records-এর কলাম:** Check Roll-এর Records কলাম Brickland থেকে নেওয়া (Req No, Req Date, Batch No, Roll No, Defects, Marked, Responsible Department), সঙ্গে Result।
