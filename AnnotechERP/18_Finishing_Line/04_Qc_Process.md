# QC Process

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `18/04`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Finishing Quality Entry | `/m/dyeing-finishing/finishing-qc-entry` | 1 | 0 | 0 |
| 2 | Shade, GSM & Width Test | `/m/dyeing-finishing/shade-gsm-width-check` | 2 | 11 | 1 |
| 3 | Four-Point Fabric Inspection | `/m/dyeing-finishing/fabric-inspection-4-point` | 3 | 29 | 2 |
| 4 | Rejection & Re-Process Advice | `/m/dyeing-finishing/qc-rejection-re-process-advice` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Finishing Quality Entry

`/m/dyeing-finishing/finishing-qc-entry`

**ভিউ**
> 14 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Shade, GSM & Width Test

`/m/dyeing-finishing/shade-gsm-width-check`

**ট্যাব: Batch List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Batch # | text |  |  |
| Status | select |  | Pending, Approved, Rejected |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Batch List 0, New shade decision, --Style --, -- Color --, Load, Excel, Clear

**ট্যাব: New shade decision**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch* | select | ✔ |  |
| Buyer A/C | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Lot # | text |  |  |
| Batch Qty | number |  |  |
| Model/Continuity | text |  |  |
| Status* | select | ✔ | Select…, Pending, Approved, Rejected |
| Remarks | textarea |  |  |
- সেকশন: New shade decision
- বাটন/অ্যাকশন: Batch List 0, New shade decision, -- Style --, -- Color --, -- Not yet decided --, Reset, Save shade decision

**ডায়ালগ: New shade decision**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch* | select | ✔ |  |
| Buyer A/C | select |  |  |
| Order # | select |  | LPP-2026-000008 [529], LPP-2026-000007 [1111], LPP-2026-000006 [529], LPP-2026-000005 [234234], LPP-2026-000004 [867MI], |
| Lot # | text |  |  |
| Batch Qty | number |  |  |
| Model/Continuity | text |  |  |
| Status* | select | ✔ | Select…, Pending, Approved, Rejected |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch is required.

#### Four-Point Fabric Inspection

`/m/dyeing-finishing/fabric-inspection-4-point`

**ট্যাব: Roll Inspection List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch No | text |  | B-18091297 |
| Roll No | text |  | Scan Fabric Roll Barcode |
| Ref # | text |  |  |
| Result | select |  | Pass, Hold, Reject |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Search Data
- বাটন/অ্যাকশন: Roll Inspection List 0, Finish Fabric Inspection, New inspection, Load, New Inspection, Excel, Hold List, Reset

**ট্যাব: Finish Fabric Inspection**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing inspection | select |  |  |
| Batch No* | select | ✔ |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Color | text |  |  |
| Lot # | text |  |  |
| Weights | text |  |  |
| Insp. Date* | date | ✔ |  |
| Shift* | select | ✔ | Select…, A, B, C, General |
| Actual Dia | number |  |  |
| Shinkage L% | number |  |  |
| Shinkage W% | number |  |  |
| Spirality | number |  |  |
| Ref # | text |  |  |
| Verdict* | select | ✔ | Select…, Pass, Hold, Reject |
| Shade Note | textarea |  |  |
| QC Comment | textarea |  |  |
| Fabric Roll No | text |  |  |
| Weight (Kg) | number |  |  |
| 4-Point | number |  |  |
| Grade | text |  |  |
| Result | select |  | Select…, Pass, Hold, Reject |
| Defect | text |  |  |
- সেকশন: Finish Fabric Inspection, Batch Details, Lab Report, Inspection, Roll Inspection List
- বাটন/অ্যাকশন: Roll Inspection List 0, Finish Fabric Inspection, New inspection, -- Select --, Add Roll, Cancel, Back To List, Clear, Save

**ট্যাব: New inspection**
- সেকশন: New inspection
- বাটন/অ্যাকশন: Roll Inspection List 0, Finish Fabric Inspection, New inspection, Reset, Save inspection

**ডায়ালগ: New inspection**
- খালি Save-এ মেসেজ: An inspection is written on the entry tab, where the batch is loaded and its rolls are weighed.

**ডায়ালগ: New Inspection**
- খালি Save-এ মেসেজ: An inspection is written on the entry tab, where the batch is loaded and its rolls are weighed.

#### Rejection & Re-Process Advice

`/m/dyeing-finishing/qc-rejection-re-process-advice`

**ভিউ**
> 17 of 22 Scaffold
- সেকশন: This screen is scaffolded, not built
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **18/02-এর ফিনিশিং কার্ড আর ফ্লোর বোর্ড:** `fin_batch_receipts`, `FinFloor::move`। ইন্সপেকশনের রায় আর রি-প্রসেস পরামর্শ একই কার্ডের স্টেজ সরায়, আর তা ট্রেইলে লেখা হয়।
- **18/01-এর মেশিন আর Machine-Process Mapping:** পরামর্শের মেশিন ব্যাচের ডায়ায় প্রসেসে ম্যাপ করা থাকতে হয়।
- **18/03-এর `ScRegister`:** ইন্সপেকশনের রেজিস্টারে ব্যবহার করা হয়েছে। এডিটরে chem-store-এর `LinesCard`/`Group`/`EditorFoot`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/Finishing/Database/Migrations/2027_02_05_000100_create_fin_qc_process.php`:
- **`fin_qc_entries`:** entry_date, receipt (কার্ড), machine, process, shift, roll_no (প্রতি কার্ডে ইউনিক), weight_kg, gsm, dia, shade (OK/OFF), result (OK/FAULT), defect, remarks।
- **`fin_shade_decisions`:** receipt (প্রতি কার্ডে একটা), buyer, order, style, colour, lot_no, batch_qty, model_continuity, status (PENDING/APPROVED/REJECTED), decided_by, decided_at, remarks।
- **`fin_inspections`:** ref_no (FFI-YYYYMM#### বা নিজের দেওয়া), receipt, insp_date, shift, actual_dia, shrinkage_l, shrinkage_w, spirality, inspector, shift_incharge, qc_incharge, verdict (PASS/HOLD/REJECT), shade_note, qc_comment, roll_count, total_kg, total_points, status (DRAFT/POSTED)।
- **`fin_inspection_rolls`:** inspection, roll_no (শিটে ইউনিক), weight_kg, points, grade, result, defect।
- **`fin_reprocess_advices`:** advice_no (FRA-…), advice_date, inspection (প্রতি ইন্সপেকশনে একটা), receipt, process, machine, rejected_rolls, rejected_kg, reason (SHADE/GSM/WIDTH/DEFECT/OTHER), remarks।
- **`FinFloor::batches`:** এখন ব্যাচের `lot_no`-ও দেয়।

## ৫. Workflow / Status ও Business Rules

- **Finishing Quality Entry** (Brickland-এ scaffold, পুরোটা বানানো হয়েছে):
  - প্রতিটা সারি একটা রোলের রিডিং।
  - শুধু ফ্লোরে থাকা ব্যাচ, আর তারিখ রিসিভের আগে হতে পারে না।
  - রোল নম্বর বড় হাতের অক্ষরে রাখা হয়, প্রতি ব্যাচে একবার। ত্রুটিপূর্ণ রোলে ডিফেক্ট লিখতে হয়।
- **Shade, GSM & Width Test:**
  - প্রতি ব্যাচে একটা সিদ্ধান্ত। আগে থেকে তালিকায় থাকা ব্যাচ আবার যোগ করতে গেলে আটকায়, তালিকা থেকে খুলতে বলে।
  - বায়ার, অর্ডার, স্টাইল, রং আর পরিমাণ না দিলে ব্যাচ থেকে আসে।
  - Approved বা Rejected করলে কে আর কখন সিদ্ধান্ত দিল লেখা হয়: যাঁর নাম বাছা হয়েছে, না হলে যিনি সেভ করেছেন। Pending-এ ফিরলে দুটোই মুছে যায়।
  - ব্যাচ পরে বদলানো যায় না।
- **Four-Point Fabric Inspection:**
  - ফ্লোরে থাকা ব্যাচ লোড করলে তার বায়ার, স্টাইল, রং, লট আর ওজন আসে।
  - ল্যাব রিপোর্ট, কে ইন্সপেক্ট করল, আর রায় দিতে হয়।
  - রোল নম্বর শিটে একবার। Ref # ফাঁকা থাকলে নম্বর বসে, আর নিজে দিলে ইউনিক হতে হয়।
  - Save করলে শিট খসড়া থাকে আর তিনটা মোট (রোল, কেজি, পয়েন্ট) আবার গোনা হয়।
  - রেজিস্টার থেকে Post করলে রায় ব্যাচে লেখা হয়: Pass → Ready for Delivery, Hold বা Reject → On Hold (ট্রেইলে QC_INSP)। পোস্ট করা শিট শুধু পড়া যায়।
  - তালিকায় Inspections, Passed, Held, Rejected গণনা, আর Hold List (হোল্ড রায়ের ফিল্টার)। রোল নম্বর দিয়ে খুঁজলে যে ইন্সপেকশনে রোলটা আছে সেটা আসে।
- **Rejection & Re-Process Advice** (Brickland-এ scaffold):
  - শুধু পোস্ট করা এমন ইন্সপেকশন, যা ব্যাচ বা তার কিছু রোল বাতিল করেছে আর যার জন্য এখনো পরামর্শ হয়নি।
  - বাতিল রোল আর কেজি ইন্সপেকশন থেকে গোনা হয়। রায় Reject কিন্তু কোনো রোল আলাদা করে Reject নয়, তাহলে সব রোল গোনা হয়।
  - মেশিন দিলে সেটা ব্যাচের ডায়ায় প্রসেসে ম্যাপ করা থাকতে হয়। কারণ Other হলে মন্তব্য লাগবে।
  - পরামর্শ দিলে স্থগিত ব্যাচ আবার চালানোর জন্য Awaiting Load-এ যায় (QC_ADV)।
  - ব্যাচ এর পরে না সরলে পরামর্শ মোছা যায়, আর মুছলে ব্যাচ আবার আগের স্টেজে ফেরে (QC_UNDO)।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `finishing.qc.view` / `finishing.qc.manage`
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়। LINE_CHIEF আর STORE_MANAGER শুধু view পায়।
- **Screen-kit resource:** `/api/v1/erp/fin-qc-entries`, `/api/v1/erp/fin-shade-decisions`, `/api/v1/erp/fin-reprocess-advices`
- **API (`FinInspectionApiController`):**
  - `GET /finishing/qc/options`
  - `GET|POST /finishing/inspections`
  - `GET|PUT /finishing/inspections/{ref}`
  - `POST /finishing/inspections/{ref}/post`
- **মেনু:** Finishing Line-এর নতুন ফোল্ডার QC PROCESS, ৪টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `finishing-line/[screen]`-এ, `qc-screens.js` থেকে। এডিটর `components/finishing/qc-process.jsx`-এ (`FinInspectionEditor`)।
- **Shade Test:** Brickland-এর মতো Batch List (Searching Data: Buyer A/C, Style, Color, Batch #, Status; Load / Clear) আর New shade decision (Decided By-এ "-- Not yet decided --")।
- **Four-Point:**
  - Roll Inspection List: Search Data, চারটা গণনা, Hold List, New Inspection, আর রেজিস্টারে Edit/Post।
  - Finish Fabric Inspection এন্ট্রি ট্যাব: Batch Details, Lab Report, Inspection, Roll Inspection List (Add Roll)।
  - New inspection ট্যাব এন্ট্রি ট্যাবে পাঠায়।
- **Quality Entry আর Advice:** Records আর Add New।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend**
- `app/Modules/Finishing/Database/Migrations/2027_02_05_000100_create_fin_qc_process.php`
- `app/Modules/Finishing/Models/`: `FinQcEntry`, `FinShadeDecision`, `FinInspection`, `FinInspectionRoll`, `FinReprocessAdvice`
- `app/Modules/Finishing/Resources/FinQcResource.php`, `FinQcEntryResource.php`, `FinShadeDecisionResource.php`, `FinReprocessAdviceResource.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinInspectionApiController.php`
- `app/Modules/Finishing/Support/FinFloor.php` (বদলানো: `lot_no`), `app/Modules/Finishing/Routes/api.php` (বদলানো)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/finishing.php`, `lang/bn/finishing.php` (বদলানো: `qc` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/qc-screens.js` (নতুন), `page.js` আর `actions.js` (বদলানো)
- `frontend/src/components/finishing/qc-process.jsx`

**Test**
- `tests/Feature/Finishing/FinishingQcProcessTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Finishing/FinishingQcProcessTest.php`):** test2 DB-তে পাস, ৫টা টেস্ট, ১২০টা assertion।
- `test_a_roll_is_read_as_it_comes_off_the_machine`: ডিফেক্ট ছাড়া ত্রুটিপূর্ণ রোল আর রিসিভের আগের তারিখ আটকায়। R-01 বড় হাতের অক্ষরে রাখা হয়, আর একই ব্যাচে আবার দিলে আটকায়।
- `test_one_shade_decision_per_batch_stamped_when_decided`:
  - রং আর পরিমাণ ব্যাচ থেকে আসে। একই ব্যাচ দুবার যোগ আটকায়।
  - Approved করলে কে আর কখন বসে, অন্য এডিটেও তা থাকে। Pending-এ ফিরলে মুছে যায়। ব্যাচ বদলানো আটকায়।
- `test_the_four_point_verdict_moves_the_batch_on_the_floor_board`:
  - রোল ছাড়া শিট, একই রোল দুবার আর একই Ref # আটকায়।
  - FFI-2026110001-এ ২টা রোল, ৩৫.৭ কেজি, ২০ পয়েন্ট। গ্রেড বড় হাতের অক্ষরে রাখা হয়।
  - Hold পোস্ট করলে ব্যাচ On Hold-এ যায়, আর পোস্টের পরে এডিট আটকায়। Pass (QC-77) পোস্ট করলে Ready for Delivery-তে যায় (QC_INSP)। রোল নম্বর দিয়ে খোঁজা যায়।
- `test_rejected_rolls_are_advised_back_onto_the_floor`:
  - পাস হওয়া ইন্সপেকশন, ম্যাপ না করা মেশিন, আগের তারিখ আর মন্তব্য ছাড়া Other আটকায়।
  - FRA-2026110001-এ ২টা বাতিল রোল, ৩৭.৫ কেজি। ব্যাচ Awaiting Load-এ যায়। দ্বিতীয় পরামর্শ আটকায়।
  - মুছলে ব্যাচ On Hold-এ ফেরে। ব্যাচ বোর্ডে এগোনোর পরে মোছা আটকায়।
- `test_who_may_judge_quality`: LINE_CHIEF দেখতে পারে, সিদ্ধান্ত দিতে পারে না। FACTORY_MANAGER পারে। TECHNICIAN কিছুই পায় না।

**Regression:** `FinishingScProcessTest` (৪টা, ১১৭টা), `FinishingProductionTest` (৬টা, ২০৬টা), `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/fqc01`), dev অ্যাপে (Delta Apparels):**
- **Quality Entry:** B-26110011, শিফট A। রোল r-01 সেভ হয়েছে R-01 হিসেবে: ১৬.৫ কেজি, GSM ১৮১, ডায়া ৭২, On Shade, OK।
- **Shade Test:**
  - B-26110011, "Body + rib, one continuity", Approved।
  - বায়ার LPP, অর্ডার, স্টাইল, রং Black আর পরিমাণ ৯৯.২ ব্যাচ থেকে নিজে বসেছে।
  - সিদ্ধান্তদাতা Rahim Uddin, সময় লেখা হয়েছে।
- **Four-Point:**
  - B-26110011 লোড করতেই বায়ার, স্টাইল, রং, লট L-1010-B আর ওজন "৯৯.২ kg / 6" বসেছে।
  - ল্যাব রিপোর্ট: ডায়া ৭২, শ্রিংকেজ −৩.৫ / −২, স্পাইরালিটি ৪। রায় Pass।
  - রোল: R-01 ১৬.৫ কেজি ৬ পয়েন্ট A, R-02 ১৭.২ কেজি ১১ পয়েন্ট B।
  - Save করলে FFI-2026110001 হয়েছে: ২টা রোল, ৩৩.৭ কেজি, ১৭ পয়েন্ট। গণনায় Inspections ১, Passed ১।
  - রেজিস্টার থেকে Post করা হয়েছে। এন্ট্রি ট্যাবে এখন "Posted", সব ঘর শুধু পড়া যায়।
- **Process-Wise Delivery (18/02):** B-26110011 এখন Ready for Delivery।
- **Rejection & Re-Process Advice:** Add New খোলে। dev-এ কোনো রিজেক্ট হওয়া ইন্সপেকশন নেই, তাই তালিকা ফাঁকা। এই অংশ টেস্টে যাচাই হয়েছে।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে। B-26110011 এখন 18/05-এর স্টোরে নেওয়ার জন্য প্রস্তুত।

**প্রিন্ট:** Brickland-এর এই চারটা স্ক্রিনে Print বাটন নেই।

## ১০. খোলা প্রশ্ন

১. **শেড সিদ্ধান্ত আর ইন্সপেকশন:** এখন শেড সিদ্ধান্ত Approved না হলেও ইন্সপেকশনে Pass দেওয়া যায়। Pass-এর আগে শেড অ্যাপ্রুভাল বাধ্যতামূলক হবে কিনা জানাবেন।

২. **গ্রেড:** রোলের গ্রেড হাতে লেখা হয় (Brickland-এর মতো)। ফোর-পয়েন্ট থেকে নিজে হিসাব হবে কিনা (যেমন প্রতি ১০০ বর্গগজে পয়েন্ট, A/B/C-এর সীমা) জানাবেন।

৩. **রিজেক্টের পরে ব্যাচ:** Hold আর Reject দুটোই ব্যাচকে On Hold-এ রাখে। পরামর্শ দিলে পুরো ব্যাচ Awaiting Load-এ যায়, শুধু বাতিল রোলগুলো নয়। পাস হওয়া রোল আলাদা করে স্টোরে পাঠানোর (ব্যাচ ভাগ করার) দরকার আছে কিনা জানাবেন।

৪. **Quality Entry-র কাজ:** রোলের রিডিং এখন শুধু রেকর্ড। ত্রুটিপূর্ণ রোল বা শেড ভুল হলে ব্যাচ নিজে থেকেই থামবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/fq01`):**
  - Shade Test আর Four-Point লাইভ দেখা হয়েছে। এন্ট্রি ট্যাবে Batch Details / Lab Report / Inspection / Roll Inspection List, আর তালিকায় চারটা গণনা।
  - Hold List একই পাতায় থাকে, অন্য কোনো স্ক্রিনে যায় না।
  - Quality Entry আর Rejection Advice scaffold, তাই তাদের এক লাইনের বিবরণ থেকে পুরোটা বানানো হয়েছে।
- **বানান:** Brickland-এ লেবেল "Shinkage L%/W%", আমরা লিখেছি "Shrinkage"।
- **একই কার্ড:** ইন্সপেকশন আর পরামর্শ 18/02-এর কার্ডের ট্রেইলে লেখা হয় (QC_INSP / QC_ADV / QC_UNDO)। Pass হওয়া ব্যাচ Ready for Delivery-তে থাকে, আর 18/05-এর ফিনিশড ফেব্রিক স্টোর সেখান থেকে নেবে।
