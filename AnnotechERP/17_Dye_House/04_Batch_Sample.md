# Batch (Sample)

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/04`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sample Grey Fabric Indent | `/m/dyeing/grey-fabric-requisition-sample` | 2 | 11 | 1 |
| 2 | Sample Grey Fabric Issue Register | `/m/dyeing/grey-fabric-requisition-issue-list-sample` | 1 | 8 | 0 |
| 3 | Sample Batch Production | `/m/dyeing/batch-production-sample` | 1 | 20 | 1 |
| 4 | Sample Finishing Quality Check | `/m/dyeing/dyeing-finishing-qc-sample` | 2 | 17 | 1 |
| 5 | Sample Batch from Bulk Lot | `/m/dyeing/sample-batch-from-bulk` | 2 | 14 | 2 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sample Grey Fabric Indent

`/m/dyeing/grey-fabric-requisition-sample`

**ট্যাব: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch#* | select | ✔ | search Batch#… |
| Schedule | select |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Dyeing Rate* | select | ✔ | White · water 0 / wash 0, Light · water 0 / wash 0, Medium · water 0 / wash 0, Dark · water 0 / wash 0, Extra Dark · wat |
| Proj Rate* | number | ✔ | 52.00 |
- সেকশন: New requisition
- বাটন/অ্যাকশন: Batch and Program Lists, Create Requisition, -- Select Store --, Reset

**ট্যাব: Batch and Program Lists**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch List | radio |  |  |
| Program List | radio |  |  |
| Pending Labdips Recipe | radio |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Batch and Program Lists, Create Requisition, Preview

**ডায়ালগ: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch#* | select | ✔ | search Batch#… |
| Schedule | select |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Dyeing Rate* | select | ✔ | White · water 0 / wash 0, Light · water 0 / wash 0, Medium · water 0 / wash 0, Dark · water 0 / wash 0, Extra Dark · wat |
| Proj Rate* | number | ✔ |  |
- খালি Save-এ মেসেজ: Batch# is required.

#### Sample Grey Fabric Issue Register

`/m/dyeing/grey-fabric-requisition-issue-list-sample`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Order/Style | select |  |  |
| Color | select |  |  |
| Requisition Store | select |  |  |
| Batch/Pre-Treatment No | search |  | B-26080001 |
| Requisition Type | select |  | All, Grey Fab Req for Sample Batch, Grey Fab Req for Bulk Batch, Grey Fab Req for Pre-Treatment |
| Search Every grey fabric requisition raised against a batch — what was asked for | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Req Date, Req(Kg), Issue(Kg), Batch No, Items, Requisition Type, Company, ACTION
- বাটন/অ্যাকশন: Clear, Export, Requisition No, Req Date, Req(Kg), Issue(Kg), Batch No, Items, Requisition Type, Company

#### Sample Batch Production

`/m/dyeing/batch-production-sample`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Sample Batches | search |  | Scan Barcode or search Batch#… |
| Existing production | select |  |  |
| Batch No | text |  | Scan or pick one on the left |
| Production Date* | date | ✔ |  |
| Req No | select |  |  |
| Req Date | text |  |  |
| Lot | text |  |  |
| Weights | number |  |  |
| M/C No | select |  |  |
| Model/Combo | text |  | AOP type: Pigment |
| Color | text |  |  |
| Color Group | text |  |  |
| Buyer | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Lab-dip # | text |  | LD-2608-014 |
| D Method | text |  |  |
| Liqur Ratio* | text | ✔ | 1:8 |
| Show Batch Recipe The approved lab dip recipe the shade is being matched to. | checkbox |  |  |
| Recipe Details | text |  |  |
- গ্রিড কলাম: Batch No, Color, Qty(Kg)
- সেকশন: Sample Batches, Sample Batch Production, Production, Batch Info, Recipe Details, Fabrics Details, Batch History
- বাটন/অ্যাকশন: Add Load, Clear, Save production

**ডায়ালগ: Add Load**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Sample Batches | search |  |  |
| Existing production | select |  |  |
| Batch No | text |  |  |
| Production Date* | date | ✔ |  |
| Req No | select |  |  |
| Req Date | text |  |  |
| Lot | text |  |  |
| Weights | number |  |  |
| M/C No | select |  |  |
| Model/Combo | text |  |  |
| Color | text |  |  |
| Color Group | text |  |  |
| Buyer | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Lab-dip # | text |  |  |
| D Method | text |  |  |
| Liqur Ratio* | text | ✔ |  |
| Show Batch Recipe The approved lab dip recipe the shade is being matched to. | checkbox |  |  |
| Recipe Details | text |  |  |
- খালি Save-এ মেসেজ: Find the batch on the left first.

#### Sample Finishing Quality Check

`/m/dyeing/dyeing-finishing-qc-sample`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order # | select |  |  |
| Color | select |  |  |
| Batch # | search |  | S-26080001 |
| Batch Status | select |  | All, Pending, Passed, Failed, Rework |
| Search Shade, hand-feel and GSM checks on finished sample batches, with the verd | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Batch, Lot, Batch Qty, Buyer, Style, Order, Color, Model/Combo, ACTION
- বাটন/অ্যাকশন: Add, Clear, Export, Batch, Lot, Batch Qty, Buyer, Style, Order, Color, Model/Combo

**ট্যাব: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch* | select | ✔ |  |
| Lot | text |  |  |
| Batch Qty | text |  |  |
| QC Date* | date | ✔ |  |
| Batch Status* | select | ✔ | Select…, Pending, Passed, Failed, Rework |
| Model/Combo | text |  | AOP type: Pigment |
| GSM | number |  |  |
| Width | number |  |  |
| Shade Note | text |  | Matches approved lab dip, 4-5 grey scale |
| Remarks | textarea |  |  |
- সেকশন: New QC check
- বাটন/অ্যাকশন: Add, -- Select Employee --, Reset

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch* | select | ✔ |  |
| Lot | text |  |  |
| Batch Qty | text |  |  |
| QC Date* | date | ✔ |  |
| Batch Status* | select | ✔ | Select…, Pending, Passed, Failed, Rework |
| Model/Combo | text |  |  |
| GSM | number |  |  |
| Width | number |  |  |
| Shade Note | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch is required.

#### Sample Batch from Bulk Lot

`/m/dyeing/sample-batch-from-bulk`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Transfer No | search |  | SBB/202608001 |
| Transfer Date from | date |  |  |
| Transfer Date to | date |  |  |
| Batch No | search |  | B-26080001 |
| Search Samples drawn out of bulk batches — the batch it came off, the number it  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Batch No, New Batch No, Lot No, Batch Date, Batch Qty, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Batch No, New Batch No, Lot No, Batch Date, Batch Qty

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Transfer No | text |  | Issued on save |
| Transfer Date* | date | ✔ |  |
| Batch No* | select | ✔ |  |
| New Batch No* | text | ✔ | S-26080692 |
| Lot No | text |  | SM |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- সেকশন: New sample transfer
- বাটন/অ্যাকশন: Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Transfer No | text |  |  |
| Transfer Date* | date | ✔ |  |
| Batch No* | select | ✔ |  |
| New Batch No* | text | ✔ |  |
| Lot No | text |  |  |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch Qty must be more than nothing.

**ডায়ালগ: New Batch No**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Transfer No | search |  |  |
| Transfer Date from | date |  |  |
| Transfer Date to | date |  |  |
| Batch No | search |  |  |
| Search Samples drawn out of bulk batches — the batch it came off, the number it  | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/03-এর batch card:** sample card হলো `dye_batches`-এর scope SAMPLE সারি (S-YYMM####)। আলাদা কোনো টেবিল নেই।
- **17/03 থেকে আবার ব্যবহার:**
  - grey indent resource, sample-এর জন্য type/scope বদলে (`DyeSampleIndentResource`)।
  - Issue Register-এর component (`GreyIssueRegister`)।
  - board plan, scope SAMPLE হলে sample মেশিন।
- **ল্যাব ডিপ রেসিপি (17/02):** production-এ "Show Batch Recipe" আর "Pending Labdips Recipe" তালিকা।
- **HR:** `hr_employees` থেকে QC-র Checked By।
- **ReportSet:** Batch and Program Lists-এর জন্য, এবার তাতে To Date যোগ হয়েছে (`withTo`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_27_000100_create_dye_sample_batches.php`:
- **`dye_grey_indents.party_id`:** sample requisition কোন কোম্পানির জন্য। req_type এখন SAMPLE-ও নিতে পারে।
- **`dye_productions`:** batch, production_date, requisition, weight_kg, machine, model_combo, lab_dip_ref, liquor_ratio, remarks।
  - এক batch-এ এক দিনে একটাই (ইউনিক)।
  - 17/07 Bulk Batch Production-ও এই টেবিলে লিখবে।
- **`dye_production_loads`:** machine, load_time, unload_time, load_type (ডিফল্ট Dyeing), status (LOADED / RUNNING / UNLOADED / ABORTED)।
- **`dye_finishing_qcs`:** scope, batch, qc_date, result (PENDING / PASSED / FAILED / REWORK), model_combo, gsm, width_inch, checked_by (hr_employees), shade_note, remarks। এক batch-এ এক দিনে একটাই।
- **`dye_sample_transfers`:** SBB/YYYYMM###, bulk batch, নতুন sample card, lot, তারিখ, qty।

## ৫. Workflow / Status ও Business Rules

- **Sample Batch from Bulk Lot:**
  - নতুন sample card পায় নিজের S-নম্বর (হাতে লেখা), lot, তারিখ আর qty, আর bulk card-এর item ভাগ ধরে।
  - একই ট্রানজ্যাকশনে bulk card থেকে ততটুকু কমে। bulk-এর পুরোটা নেওয়া যায় না।
  - ট্রান্সফার নম্বর দেয় সার্ভার।
  - পরে শুধু lot, তারিখ আর remarks বদলানো যায়। মোছা যায় না।
- **Sample Grey Fabric Indent:**
  - bulk indent-এর নিয়ম মেনে চলে: লাইন কপি, এক কার্ডে একটাই requisition।
  - Schedule (sample loading plan) বাছলে কার্ড সেই প্ল্যানে যায়। Company সংরক্ষিত হয়।
- **Issue:** from-bulk sample-এর grey আসলে এখনো bulk batch-এর নম্বরে রোলে থাকে। তাই sample requisition-এর Issue-তে bulk batch-এর lot-ও দেখায় আর সেখান থেকে কাটে।
- **Sample Grey Fabric Issue Register:** sample আর bulk দুই ধরনের requisition দেখায়। Requisition Type ফিল্টার আছে (Pre-Treatment ধরনের requisition এখনো তৈরি হয় না)।
- **Batch and Program Lists:**
  - From/To তারিখের মধ্যে: sample card-এর তালিকা (schedule, requisition, অবস্থাসহ), sample loading plan-এর তালিকা, আর যে sample card-এর বায়ার আর শেডের কোনো ল্যাব ডিপ রেসিপি নেই।
  - Export to Excel? টিক দিলে CSV নামে।
- **Sample Batch Production:**
  - বাঁ দিকে খোলা sample card। স্ক্যান বক্সে নম্বর লিখে Enter চাপলে কার্ডটা খোলে।
  - কার্ডের তথ্য নিজে ভরে, শুধু-পড়া।
  - ফ্লোর লেখে তারিখ, ওজন, মেশিন, model/combo, lab dip আর liquor ratio (1:N)।
  - Batch History-তে অন্তত একটা load লাগবে। Unload লোডের আগে হতে পারে না। Duration দুই সময় থেকে হিসাব হয়।
  - এক batch-এ এক দিনে একটাই production। আগেরটা "Existing production" থেকে খুলে বদলানো যায়।
- **Sample Finishing Quality Check:**
  - শুধু চলেছে এমন batch-এ, মানে যার production আছে।
  - এক batch-এ এক দিনে একটাই সিদ্ধান্ত। রিওয়ার্কের পরের চেক অন্য দিনে হয়, আর দুটো সারিই থাকে।
- **Board:** Fabric Batch Plan শুধু bulk প্ল্যানে আনা যায়। sample board-এ sample মেশিন থাকে, আর ক্ষমতার বেশি ওজনের কার্ড আটকায়।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন নেই, 17/03-এরগুলোই।
  - `dyehouse.batch.view`: দেখা।
  - `dyehouse.batch.manage`: লেখা, production আর QC।
  - `dyehouse.batch.issue`: ইস্যু।
- **API:**
  - `GET /dyehouse/production/batches?scope=SAMPLE`।
  - `GET|POST /dyehouse/batches/{no}/production`। আগেরটা বদলাতে `original_date` দিতে হয়।
  - `GET /dyehouse/sample/lists/{batch-list|program-list|pending-labdip}?from&to`।
- **Screen-kit resource:** `dye-sample-indents`, `dye-sample-transfers`, `dye-sample-qcs`।
- **options-এ নতুন:** `employees`, `run_batches`, আর plan-এর `id`।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Dye House → BATCH (SAMPLE), ৫টা লিফ।
- **Sample Grey Fabric Indent:** তিনটা ট্যাব।
  - Records: Batch#, Receiving Store আর Company ফিল্টার।
  - Batch and Program Lists: রেডিও বাছাই, From/To, Export to Excel?, Preview।
  - Create Requisition।
- **Issue Register:** Requisition Type সহ ফিল্টার, প্রতিটা সারিতে Issue।
- **Sample Batch Production:**
  - বাঁয়ে Sample Batches, স্ক্যান আর সার্চসহ।
  - ডানে Existing production, Production, Batch Info আর Recipe Details।
  - নিচে Fabrics Details আর Batch History (Add Load, Duration), তারপর Clear আর Save production।
- **QC আর Sample from Bulk:** Records আর Add / Add New ট্যাব। Edit একই পাতায় খোলে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_27_000100_create_dye_sample_batches.php`
- `app/Modules/DyeHouse/Models/` — `DyeProduction`, `DyeProductionLoad`, `DyeFinishingQc`, `DyeSampleTransfer` (নতুন); `DyeGreyIndent`, `DyeBatch` (বদলানো)
- `app/Modules/DyeHouse/Resources/` — `DyeSampleIndentResource`, `DyeSampleTransferResource`, `DyeSampleQcResource` (নতুন); `DyeGreyIndentResource` (type/scope দিয়ে সাধারণ করা)
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeProductionApiController.php` (নতুন), `DyeBatchApiController.php` (বদলানো: options, sample-এর issue, fp pull)
- `app/Modules/DyeHouse/Support/DyeBatches.php` (বদলানো: নম্বরের ফরম্যাট)
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/sample-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/batch-production.jsx` (নতুন)
- `frontend/src/components/erp/report-set.jsx` (বদলানো: `withTo`)

**Test**
- `tests/Feature/DyeHouse/DyeHouseSampleTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseSampleTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ৯৯টা assertion।
- `test_sample_off_a_bulk_batch_indent_issue_and_lists`:
  - from-bulk-এর বার্তা, পুরো bulk নেওয়া আটকায়।
  - SBB/202611001, S-কার্ড FROM_BULK, bulk ২৫০-এ নামে। পরে qty বদলানো আটকায়।
  - Fabric Batch Plan sample প্ল্যানে আনা আটকায়।
  - sample indent-এ Schedule আর Company বসে। bulk কার্ডে sample indent আটকায়।
  - bulk-এর lot থেকে ইস্যু হয়।
  - তিনটা তালিকাই ঠিক আসে। রেসিপি যোগ করলে pending তালিকা খালি হয়।
- `test_sample_production_and_finishing_check`:
  - production-এর তিন বার্তা (load নেই, রেশিও ভুল, আনলোড লোডের আগে)।
  - একই দিনে দ্বিতীয় production আটকায়। আগেরটা বদলে দুটো load হয়। রেসিপির লেখা ঠিক আসে।
  - QC: চলেনি এমন batch আটকায়, একই দিনে দ্বিতীয় সিদ্ধান্ত আটকায়, অন্য দিনে চলে, Edit হয়।
- `test_who_may_produce_and_check`: STORE_MANAGER শুধু দেখতে পারে, TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseBatchTest` (৫টা টেস্ট, ১৭৩টা assertion) আর `MenuAccessTest` (৬টা টেস্ট, ৩৪টা assertion) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/ds01`–`ds02`), dev অ্যাপে:**
- **Sample from Bulk:** B-26110010 থেকে S-26110001 (৪০ কেজি)। Batch Qty ০-এর বার্তা এসেছে।
- **Sample plan:** PLN-26110002 (Sample, 2Hr)।
- **Sample indent:** GFR-26110003, Schedule PLN-26110002 আর Company সহ। ফাঁকা সেভে চারটা বার্তা এসেছে।
- **Lists:** Batch List আর Program List-এ সারি আসে। Pending খালি, কারণ 17/02-এর রেসিপি এই শেডের।
- **Issue Register:** Requisition Type ফিল্টার কাজ করে। sample-এর Issue sheet খোলে।
- **Production:** S-26110001, SM-02, 1:8, লোড ০৮:০০–১১:৩০ (৩ঘ ৩০মি, Unloaded) সেভ হয়েছে। Existing production থেকে আবার খোলে।
- **QC:** চলা batch-এ Passed, GSM 160, Width 72।
- **Sample board:** S-26110001 strip-এ আসে। SM-02 (২৫ কেজি)-এ বসাতে চাইলে ক্ষমতার নিয়মে আটকায়, কারণ কার্ড ৪০ কেজি।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।
- **UI চেকে ধরা পড়া ছোট সমস্যা, ঠিক করা হয়েছে:** খালি তালিকায় "batch.lists.empty" key দেখাত, কারণ ReportSet-এর তিনটা key ছিল না।

**প্রিন্ট:** Brickland-এর এই ৫টা স্ক্রিনে Print নেই।

## ১০. খোলা প্রশ্ন

১. **Sample card কোথা থেকে আসে:** Brickland-এ sample batch তৈরির স্ক্রিন শুধু "Sample Batch from Bulk Lot"। আলাদাভাবে ডাই হওয়া sample batch (bulk থেকে নয়) কোথা থেকে আসবে জানাবেন। হাতে লেখা হবে (Manual Dye Batch Entry-তে S/B বেছে), নাকি Merchandise-এর lab dip বা sample program থেকে?

২. **Batch Card Split আর Colour Replacement:** এখন শুধু bulk কার্ডের জন্য, Brickland-এর মতো। sample কার্ডের জন্যও লাগবে কিনা জানাবেন।

৩. **Pre-Treatment requisition:** Issue Register-এর ফিল্টারে আছে, কিন্তু Brickland-এ এমন requisition তোলার কোনো স্ক্রিন নেই।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/ds01`):** ৫টা স্ক্রিনের ফর্ম, হিন্ট, বার্তা আর গ্রিড দেখা হয়েছে। demo-তে কোনো sample batch নেই, তাই তালিকার Preview খালি এসেছে।
- **Production-এর টেবিল দুই scope-এর জন্য একটাই:** 17/07 Bulk Batch Production-এ বাড়তি ঘর যোগ হবে (Chk Roll Dt, By-Pass Check Roll, Scouring)।
- **Board-এর ক্ষমতার নিয়ম sample মেশিনেও খাটে।**
