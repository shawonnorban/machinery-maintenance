# Floor Operations

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/07`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sewing Output Adjustment | `/m/ie/sewing-prod-adjustment` | 3 | 11 | 1 |
| 2 | Garment Wash Requisition | `/m/ie/gmt-wash-requisition` | 3 | 23 | 1 |
| 3 | Standard Minute Adjustment | `/m/ie/man-minutes-adjustment` | 3 | 7 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sewing Output Adjustment

`/m/ie/sewing-prod-adjustment`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Sewing Prod Adjustment, New production adjustment

**ট্যাব: Sewing Prod Adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
- সেকশন: Sewing Prod Adjustment
- বাটন/অ্যাকশন: Sewing Prod Adjustment, New production adjustment, -- Hour --, Refresh, Save

**ট্যাব: New production adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Order No | text |  |  |
| Item Name | text |  |  |
| Hour* | text | ✔ | 10AM |
| Prod (recorded)* | number | ✔ |  |
| Prod (adjusted)* | number | ✔ |  |
| Reason* | textarea | ✔ |  |
- সেকশন: New production adjustment
- বাটন/অ্যাকশন: Sewing Prod Adjustment, New production adjustment, Select…, Reset, Save production adjustment

**ডায়ালগ: New production adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Order No | text |  |  |
| Item Name | text |  |  |
| Hour* | text | ✔ |  |
| Prod (recorded)* | number | ✔ |  |
| Prod (adjusted)* | number | ✔ |  |
| Reason* | textarea | ✔ |  |
- খালি Save-এ মেসেজ: Date is required.

#### Garment Wash Requisition

`/m/ie/gmt-wash-requisition`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Wash Requisition List, New wash requisition

**ট্যাব: Wash Requisition List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Still open, Draft Created, Submitted, Verified, Complete, Cancelled |
| Buyer | select |  | LPP (LPP) |
| Requisition No | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Req Date From | date |  |  |
| Req Date To | date |  |  |
- সেকশন: Wash Requisition List
- বাটন/অ্যাকশন: Wash Requisition List, New wash requisition, Search, Apply

**ট্যাব: New wash requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date of Requisition* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Style/Order #* | text | ✔ |  |
| Order #* | text | ✔ |  |
| Shipment Date | date |  |  |
| Order Qty | number |  |  |
| Wash Qty (Pcs)* | number | ✔ |  |
| DF Process | text |  |  |
| GMT Production Category | text |  |  |
| Hourly Production (Pcs) | number |  |  |
| Daily Production (Pcs)* | number | ✔ |  |
| Start Date | date |  |  |
| Close Date | date |  |  |
| Remarks | textarea |  |  |
| Take Action* | select | ✔ | Select…, Draft Created, Submitted, Verified, Complete, Cancelled |
- সেকশন: New wash requisition
- বাটন/অ্যাকশন: Wash Requisition List, New wash requisition, Select…, Reset, Save wash requisition

**ডায়ালগ: New wash requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date of Requisition* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Buyer | select |  | Select…, LPP (LPP) |
| Style/Order #* | text | ✔ |  |
| Order #* | text | ✔ |  |
| Shipment Date | date |  |  |
| Order Qty | number |  |  |
| Wash Qty (Pcs)* | number | ✔ |  |
| DF Process | text |  |  |
| GMT Production Category | text |  |  |
| Hourly Production (Pcs) | number |  |  |
| Daily Production (Pcs)* | number | ✔ |  |
| Start Date | date |  |  |
| Close Date | date |  |  |
| Remarks | textarea |  |  |
| Take Action* | select | ✔ | Select…, Draft Created, Submitted, Verified, Complete, Cancelled |
- খালি Save-এ মেসেজ: Wash Qty (Pcs) is required.

#### Standard Minute Adjustment

`/m/ie/man-minutes-adjustment`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Man Minutes Adjustment, New man-minute adjustment

**ট্যাব: Man Minutes Adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
| Line | select |  |  |
- সেকশন: Man Minutes Adjustment
- বাটন/অ্যাকশন: Man Minutes Adjustment, New man-minute adjustment, Refresh, Save

**ট্যাব: New man-minute adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| OP (general)* | number | ✔ |  |
| ASO (general)* | number | ✔ |  |
- সেকশন: New man-minute adjustment
- বাটন/অ্যাকশন: Man Minutes Adjustment, New man-minute adjustment, Select…, Reset, Save man-minute adjustment

**ডায়ালগ: New man-minute adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| OP (general)* | number | ✔ |  |
| ASO (general)* | number | ✔ |  |
- খালি Save-এ মেসেজ: Date is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy`, নতুন টেবিল `ws_` নামে।
- **Capacity Planning (12/02) থেকে নেওয়া:** ফ্লোর আর লাইন (`planning_lines`)। ফ্লোর আর লাইন URL-এ যায় তাদের কোড দিয়ে।
- **Merchandise থেকে নেওয়া:** বায়ার।
- **HR & Payroll থেকে নেওয়া:** Requisition By-এর কর্মী তালিকা ("নাম (কোড)")। Attn To হলো কোম্পানির ইউজার ("নাম (ইমেইল)")।
- **14/06 থেকে নেওয়া:** OT Plan-এর নয়টা ওভারটাইম ঘণ্টা (`WsOvertime::SLOTS`, 9th(6PM) … 20th(5AM))। Man Minutes শিট একই ঘণ্টা ব্যবহার করে।
- **14/05 থেকে নেওয়া:** শিটের কার্ডের অংশগুলো (`costing.jsx`)।
- **শেয়ার্ড `TablePdf`:** নতুন ঐচ্ছিক কলাম-অপশন `nowrap` যোগ হয়েছে (কয়েক শব্দের কারণ যাতে অনেকগুলো সরু সংখ্যার কলামের পাশে ভেঙে না যায়)। ডিফল্ট বন্ধ, তাই অন্য কোনো PDF বদলায়নি।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000400_create_work_study_floor_operations`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_output_adjustments` | adjust_date, floor_id, planning_line_id (ঐচ্ছিক), hour (১–১১), buyer_id, order_no, item_name, recorded_qty, adjusted_qty, reason, created_by | line + date + hour |
| `ws_wash_requisitions` | requisition_no, requisition_date, requested_by (hr_employees), attn_to (users), floor_id, planning_line_id, buyer_id, style_no, order_no, shipment_date, order_qty, wash_qty, df_process, gmt_category, hourly_production, daily_production, start_date, close_date, remarks, status, created_by | company + requisition_no |
| `ws_man_minute_adjustments` | adjust_date, floor_id, planning_line_id, reason, used_op, used_aso, ot_slots (JSON, OT Plan-এর ঘণ্টা ধরে), **ot_op**, **ot_aso** (হিসাব করা), created_by | line + date + reason |

## ৫. Workflow / Status ও Business Rules

- **Sewing Output Adjustment:**
  - **Sewing Prod Adjustment শিট:**
    - ফ্লোর, তারিখ আর ঘণ্টা (১–১১) বেছে Refresh চাপলে ফ্লোরের প্রতিটা চালু লাইন আসে: Line, Buyer, Order No, Item Name, Recorded আর Prod।
    - Recorded = সিস্টেমে রেকর্ড হওয়া ঘণ্টার উৎপাদন। Prod = যে সংখ্যায় সংশোধন হবে।
    - রেকর্ড করা সংখ্যায় রেখে দেওয়া লাইন কিছু লেখে না। আবার রেকর্ড করা সংখ্যায় ফেরালে সংশোধন মুছে যায় (Brickland-এর নিয়ম)।
    - সংশোধন সেভের সময়ের Recorded সংখ্যাটা সাথে রেখে দেয়, তাই অডিটযোগ্য থাকে।
    - নিচে Brickland-এর সারাংশ: "19 Dec 2026 · hour 2 · 5 line(s) · 125 pcs adjusted" (সেভ করা সারিগুলোর Prod − Recorded যোগফল)।
  - **New production adjustment:**
    - তারিখ, ফ্লোর, লাইন (ঐচ্ছিক, Brickland-এর মতো), বায়ার, Order No, Item Name, ঘণ্টা, Prod (recorded), Prod (adjusted) আর কারণ।
    - তারিখ না দিলে Brickland-এর মেসেজ: "Date is required."।
    - একটা লাইনের একটা ঘণ্টায় একটাই সংশোধন।
    - Prod (adjusted) রেকর্ড করা সংখ্যার সমান হলে আটকায় (শিটের নিয়মের সাথে মিল রেখে)।
  - **Records:** Date, Line, Buyer, Order No, Item Name, Hour, Prod, Adjusted, Status (Increased / Decreased)।
- **Garment Wash Requisition:**
  - **New wash requisition:**
    - Brickland-এর সব ফিল্ড: তারিখ, Requisition By, Attn To, ফ্লোর, লাইন, বায়ার, Style/Order #, Order #, শিপমেন্ট তারিখ, Order Qty, Wash Qty, DF Process, GMT Production Category, ঘণ্টায় আর দিনে উৎপাদন, শুরু আর শেষের তারিখ, মন্তব্য, Take Action।
    - Wash Qty না দিলে Brickland-এর মেসেজ: "Wash Qty (Pcs) is required."।
    - শেষের তারিখ শুরুর আগে হতে পারে না।
    - নম্বর সেভের সময় তৈরি হয়, মাস ধরে: GMT-WR-2026120001 (Brickland-এর ধাঁচ)।
  - **Wash Requisition List:**
    - Status (-- All -- / Still open / প্রতিটা ধাপ), বায়ার, রিকুইজিশন নং, স্টাইল, অর্ডার আর তারিখের সীমা দিয়ে খোঁজা যায়। ডিফল্ট "Still open" = সম্পূর্ণ বা বাতিল নয়।
    - প্রতিটা সারির Take Action বেছে Apply চাপলে এক ধাপ এগোয়: Create Draft → Draft Created, Verify & Submit Requisition → Submitted, Verify Requisition → Verified, Complete Requisition → Complete, Cancel Requisition → Cancelled।
    - সম্পূর্ণ রিকুইজিশনে কোনো অ্যাকশন নেই (Brickland-এর নোট অনুযায়ী)। সার্ভারও আটকায়।
    - নিচে সারাংশ: "2 requisition(s) · 2 still open"।
  - **Records:** REQ No, Req Date, Buyer, Style #, Order #, Ship Date, Order Qty, Remarks, Status।
- **Standard Minute Adjustment:**
  - **Man Minutes Adjustment শিট:**
    - ফ্লোর, তারিখ আর লাইন বেছে Refresh চাপলে Brickland-এর ১৫টা কারণ আসে। প্রতিটায় Used OP/ASO (সাধারণ শিফট) আর নয়টা ওভারটাইম ঘণ্টার OP/ASO।
    - Man OT OP/ASO = নয়টা ঘণ্টার যোগফল, কখনো লেখা হয় না।
    - শূন্যে রাখা কারণ কিছু লেখে না। ঘর খালি করলে সংশোধন উঠে যায়।
    - সারাংশ: "LINE01 · 19 Dec 2026 · 15 reason(s) · 8 OP and 2 ASO booked away" (Used + OT)। Brickland-এর probe-এ ২+৩ = ৫ OP, ১+১ = ২ ASO মিলেছে।
  - **New man-minute adjustment:** তারিখ, ফ্লোর, লাইন, কারণ, OP (general), ASO (general)। ওভারটাইম ঘণ্টা শিটে পূরণ হয়। একটা লাইনের একদিনে একটা কারণ একবারই। কাউকে না দেখালে আটকায়।
  - **Records:** Date, Line, Reason, OP, ASO, OT OP, OT ASO।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-output-adjustments|ws-wash-requisitions|ws-man-minute-adjustments}`।
- **নিজস্ব endpoint** (`WsFloorOpsApiController`):
  - `GET /workstudy/floor-ops/options`
  - `GET|PUT /workstudy/floor-ops/output?floor=&date=&hour=`
  - `GET|PUT /workstudy/floor-ops/minutes?floor=&line=&date=`
  - `GET /workstudy/floor-ops/wash?status=&buyer=&no=&style=&order=&from=&to=`, `POST …/wash/action`
  - `GET /workstudy/floor-ops/export/{output|minutes|wash}/{pdf|xlsx}`
- **পারমিশন:** নতুন `workstudy.floorops.view` আর `workstudy.floorops.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF পায় শুধু দেখার অনুমতি।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Floor Operations ফোল্ডার (OT & Salary-র পরে, Brickland-এর ক্রমে), তিনটা স্ক্রিন।
- **শিটের কম্পোনেন্ট** (`floor-ops.jsx`): OutputSheet, WashList, MinuteSheet।
- **URL:** ফ্লোর, তারিখ, ঘণ্টা, লাইন আর List-এর খোঁজ URL-এ থাকে, রিলোড ছাড়া।
- **PDF আর Excel:** সার্ভারের `TablePdf`, তিনটা শিটেই (Brickland-এ তিনটাতেই আছে)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000400_create_work_study_floor_operations.php`
- `app/Modules/WorkStudy/Models/WsOutputAdjustment.php`
- `app/Modules/WorkStudy/Models/WsWashRequisition.php`
- `app/Modules/WorkStudy/Models/WsManMinuteAdjustment.php`
- `app/Modules/WorkStudy/Support/WsFloorOps.php`
- `app/Modules/WorkStudy/Resources/OutputAdjustmentResource.php`
- `app/Modules/WorkStudy/Resources/WashRequisitionResource.php`
- `app/Modules/WorkStudy/Resources/ManMinuteAdjustmentResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsFloorOpsApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Shared/Pdf/TablePdf.php` (ঐচ্ছিক `nowrap` কলাম)

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/fo-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/floor-ops.jsx`
- `frontend/src/app/api/work-study/floor-ops/[sheet]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`fo.*`)

**Test**
- `tests/Feature/WorkStudy/WorkStudyFloorOperationsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudyFloorOperationsTest` ২/২ (৭৭ assertion):**
  - **আউটপুট:** শিটে দুটো লাইন আসে। ভুল ঘণ্টা আটকায়। LINE01 ৫৫-তে সেভ হয় (Increased), LINE02 কিছু লেখে না। আবার রেকর্ড করা সংখ্যায় ফেরালে মুছে যায়।
  - **New production adjustment:** তারিখ ছাড়া Brickland-এর মেসেজ। রেকর্ড করা সংখ্যার সমান হলে আটকায়। ১২০ → ১১০ Decreased। একই লাইন-ঘণ্টা দুবার চলে না। শিটে রেকর্ড করা সংখ্যা সেভের সময়েরটাই দেখায়।
  - **Man minutes:** ১৫টা কারণ। Man OT ৩ OP আর ১ ASO যোগ হয় (পাঠানো ৯৯ উপেক্ষিত)। কাউকে না দেখালে আটকায়। একই কারণ দুবার চলে না। তালিকার বাইরের কারণ আটকায়। ঘর খালি করলে উঠে যায়।
  - **Wash:** Wash Qty ছাড়া Brickland-এর মেসেজ। শেষের তারিখ শুরুর আগে আটকায়। GMT-WR-2026100001 আর …0002 (পাঠানো নম্বর উপেক্ষিত)। Submit → Verify → Complete। Complete-এ অ্যাকশন আটকায়। Still open / COMPLETE / ALL আর স্টাইল দিয়ে খোঁজা।
  - **ফাইল আর পারমিশন:** তিনটা PDF আর Excel তৈরি হয়। Line Chief List দেখতে পায়, Apply করতে পারে না।
- **পুরনো টেস্ট আবার:** Work Study-র সব টেস্ট (১৫/১৫, ৪৫৯ assertion) পাস।
- **ব্রাউজার (dev, ui_fo1–ui_fo6, ডাটা রাখা আছে):**
  - আউটপুট: A-1F, ১৯ ডিসেম্বর ২০২৬, ঘণ্টা ২, LINE01 → ১২৫ (LPP, LPP-2026-000026, T-Shirt)। New form-এ LINE05, ঘণ্টা ৩, ১২০ → ১১০।
  - Wash: GMT-WR-2026120001 (১,২০০ পিস, Enzyme Wash, Submitted) আর GMT-WR-2026120002 (৮০০ পিস, Draft Created)।
  - Man minutes: LINE01-এ Manpower Used In Others (২+১, OT ৩+১) আর Sample Making (৩)। New form-এ LINE05 Training Center (৪+১)।
  - তিনটা PDF (আউটপুট শিট, Wash List, Man Minutes) প্রিভিউ করে দেখা হয়েছে। Man Minutes-এর Reason কলাম ভেঙে যাচ্ছিল, `nowrap` দিয়ে ঠিক করা হয়েছে।

## ১০. খোলা প্রশ্ন

- **Recorded কোথা থেকে আসবে:** ঘণ্টাভিত্তিক সেলাই উৎপাদন এখনো কোনো মডিউলে রেকর্ড হয় না, তাই Recorded আপাতত ০ (Brickland-এর demo-তেও ০ ছিল)। Garments Production-এর Sewing Output তৈরি হলে `WsFloorOps::recorded()` সেখান থেকে পড়বে।
- **New production adjustment-এর Hour:** Brickland-এর ফর্মে Hour একটা লেখার ঘর ("10AM"), কিন্তু শিটে ১–১১ বাছাই। এখানে ফর্মেও ১–১১ বাছাই রাখা হয়েছে, যাতে ফর্মে লেখা সংশোধন শিটে দেখা যায়।
- **Take Action-এর লেবেল:** Brickland দেখায় "39-Create Draft", "39-Verify & Submit Requisition" ইত্যাদি। "39-" Brickland-এর ভেতরের কোনো কোড মনে হয়, তাই বাদ দেওয়া হয়েছে।
- **Complete রিকুইজিশন:** Brickland-এর নোট বলে সম্পূর্ণ রিকুইজিশনে অ্যাকশন নেই, কিন্তু demo-তে select তখনো দেখা যায়। এখানে নোট অনুযায়ী করা হয়েছে। বাতিল রিকুইজিশনে অ্যাকশন খোলা থাকে।
- **কস্টিংয়ে প্রভাব:** সংশোধিত আউটপুট আর অন্য কাজে যাওয়া man-minute 14/05-এর Daily Line Cost বা efficiency-তে কাটা হবে কিনা, তা Brickland-এ দেখা যায়নি। আপাতত আলাদা রাখা হয়েছে। রিপোর্ট (14/02) বানানোর সময় দেখা হবে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৭):**
- তিনটা স্ক্রিনই তৈরি।
- **Brickland প্রোব (fo01–fo10):** লাইভ demo-তে লেখা হয়েছে:
  - একটা আউটপুট সংশোধন (SWEING, ২৫ অক্টোবর ২০২৬, ঘণ্টা ২, LN-001 → ৫৫)।
  - একটা man-minute সংশোধন (লাইন 1, Manpower Used In Others, ২+১, OT ৩+১)।
  - একটা wash রিকুইজিশন (GMT-WR-2026100001), Draft থেকে Submitted, Verified হয়ে Complete পর্যন্ত।
  - সেগুলো থেকে সারাংশ, নম্বরের ধাঁচ আর ধাপের নিয়ম মেলানো হয়েছে।
