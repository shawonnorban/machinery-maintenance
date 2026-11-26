# OT & Salary

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/06`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Garment Overtime Plan | `/m/ie/ot-plan-garment-division` | 3 | 11 | 1 |
| 2 | Monthly Salary & OT Rates | `/m/ie/monthly-op-aso-salary-ot-rate` | 3 | 8 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Garment Overtime Plan

`/m/ie/ot-plan-garment-division`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: OT Plan, New OT plan row

**ট্যাব: OT Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
- সেকশন: OT Plan
- বাটন/অ্যাকশন: OT Plan, New OT plan row, Refresh, Save

**ট্যাব: New OT plan row**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| MP (Gen.)* | number | ✔ |  |
| WH (Gen.) | number |  |  |
| prevWH | number |  |  |
| OT Hours to Plan* | number | ✔ |  |
| OP per hour | number |  |  |
| ASO per hour | number |  |  |
| Remarks | text |  |  |
- সেকশন: New OT plan row
- বাটন/অ্যাকশন: OT Plan, New OT plan row, Select…, Reset, Save OT plan row

**ডায়ালগ: New OT plan row**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| MP (Gen.)* | number | ✔ |  |
| WH (Gen.) | number |  |  |
| prevWH | number |  |  |
| OT Hours to Plan* | number | ✔ |  |
| OP per hour | number |  |  |
| ASO per hour | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Plan at least one operator or ASO per hour.

#### Monthly Salary & OT Rates

`/m/ie/monthly-op-aso-salary-ot-rate`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Cost Particulars, New salary and OT rate

**ট্যাব: Cost Particulars**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Select Year | select |  | 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
- সেকশন: Cost Particulars
- বাটন/অ্যাকশন: Cost Particulars, New salary and OT rate, Show, Save

**ট্যাব: New salary and OT rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| OP Salary (Avg)* | number | ✔ |  |
| ASO Salary (Avg)* | number | ✔ |  |
| OP OT Rate (Avg)* | number | ✔ |  |
| ASO OT Rate (Avg)* | number | ✔ |  |
- সেকশন: New salary and OT rate
- বাটন/অ্যাকশন: Cost Particulars, New salary and OT rate, Select…, Reset, Save salary and OT rate

**ডায়ালগ: New salary and OT rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| OP Salary (Avg)* | number | ✔ |  |
| ASO Salary (Avg)* | number | ✔ |  |
| OP OT Rate (Avg)* | number | ✔ |  |
| ASO OT Rate (Avg)* | number | ✔ |  |
- খালি Save-এ মেসেজ: All four rates are required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy`, নতুন টেবিল `ws_` নামে।
- **14/05 থেকে নেওয়া:**
  - Daily Line Cost-এর Overall Profit Lost। এখন সেটা এই মাসিক রেট দিয়ে Sal[g] আর OTBill পূরণ করে (`WsCosting::salaryRate()`)।
  - শিটের কার্ডের অংশগুলো (Card, Files, Footer, NumberCell …) `costing.jsx` থেকে export করে আবার ব্যবহার করা হয়েছে।
- **HR & Payroll থেকে নেওয়া:** কর্মী (`hr_employees`), যেখানে ফ্লোর, বিভাগ, লাইন আর পদ আছে। OT Plan-এর manpower গ্রুপ আর MP (Gen.) এখান থেকে আসে।
- **Capacity Planning (12/02) থেকে নেওয়া:** ফ্লোর আর লাইন (`planning_lines`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000200_create_work_study_ot_salary`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_salary_rates` | period (2026-10), op_salary, aso_salary, op_ot_rate, aso_ot_rate | company + period |
| `ws_ot_plans` | plan_date, floor_id, planning_line_id, department_id, designation_id, mp_gen, wh_gen, prev_wh, slots (JSON: {"9": {"op", "aso"}, …}), remarks, created_by | একটা গ্রুপের একদিনে একটা প্ল্যান (অ্যাপে যাচাই) |

## ৫. Workflow / Status ও Business Rules

- **Monthly Salary & OT Rates:**
  - **New salary and OT rate:** কোম্পানি, বছর, মাস আর চারটা রেট। যেকোনো রেট বাদ গেলে Brickland-এর মেসেজ আসে: "All four rates are required."।
  - একটা মাসের একটাই লাইন। আছে এমন মাস দিলে মেসেজ আসে: "change them on Cost Particulars"।
  - **Cost Particulars:**
    - কোম্পানি আর বছর বেছে Show চাপলে বারো মাসই আসে, হিসাব হোক বা না হোক।
    - চারটা রেটই দেওয়া মাস রাখা হয়।
    - পুরো শূন্য মাস সেভে রেজিস্টার থেকে বাদ যায়।
    - অর্ধেক পূরণ মাস আটকায় (":month needs all four rates, or none.")।
  - **লাইন কস্টিংয়ে প্রভাব** (Brickland-এর probe-এর সংখ্যার সাথে হুবহু মেলে):
    - Sal[g] = (OP × OP বেতন + ASO × ASO বেতন) ÷ ২৬।
    - প্রতিটা OT স্তরের বিল = OT ম্যান-মিনিট ÷ ৬০ × (OP × OP OT রেট + ASO × ASO OT রেট) ÷ (OP + ASO)।
    - যে মাসের রেট নেই, সেখানে আগের মতো শূন্য আর "no salary rate for the month" দেখায়।
- **Garment Overtime Plan:**
  - **OT Plan** (ফ্লোর কোড আর তারিখ দিয়ে, Refresh চাপলে):
    - সারি = ফ্লোরে posted সক্রিয় কর্মীদের প্রতিটা গ্রুপ (বিভাগ, লাইন, পদ)। MP.(Gen.) হিসেবে তাদের সংখ্যা নিজে বসে।
    - সেদিন আগে পরিকল্পিত গ্রুপও আসে, যদিও সেই গ্রুপে কেউ posted না থাকে। Brickland-এ এমন গ্রুপ গ্রিডে দেখা যায় না, এখানে দেখা যায়।
    - ঘণ্টার কলাম Brickland-এর হুবহু: 9th(6PM), 10th(7PM), 11th(8PM), 12th(9PM), 13th(10PM), 14th(11PM), 15th(12PM), 17th(2AM), 20th(5AM)। প্রতিটায় OP আর ASO।
    - prevWH = গ্রুপের আগের পরিকল্পিত দিনের WH (Gen.)।
    - যে গ্রুপের কোনো ঘণ্টা পরিকল্পিত নেই সেটা কিছু লেখে না। ঘর খালি করলে আগের প্ল্যান মুছে যায়।
    - কোনো ঘণ্টায় MP.(Gen.)-এর বেশি লোক দিলে আটকায় (এখানে যোগ করা নিয়ম)।
  - **New OT plan row:**
    - তারিখ, ফ্লোর, লাইন (বাছা ফ্লোরের লাইন), বিভাগ, পদ, MP, WH, prevWH, OT ঘণ্টা আর প্রতি ঘণ্টায় OP ও ASO।
    - ৬টা থেকে ততগুলো ঘণ্টার সারি তৈরি হয়।
    - OP আর ASO দুটোই শূন্য হলে Brickland-এর মেসেজ আসে: "Plan at least one operator or ASO per hour."।
    - একই গ্রুপের একই দিনে দ্বিতীয় প্ল্যান চলে না।
  - **Records:** শুধু মোছা যায় (Brickland-এর মতো), বদলানো হয় শিটে। Hours Planned = পরিকল্পিত ঘণ্টার সংখ্যা।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-salary-rates|ws-ot-plans}`।
- **নিজস্ব endpoint** (`WsOvertimeApiController`):
  - `GET /workstudy/ot/options`
  - `GET|PUT /workstudy/ot/rates?year=2026`
  - `GET|PUT /workstudy/ot/plan?floor=&date=`
  - `GET /workstudy/ot/export/{rates|ot-plan}/{pdf|xlsx}`
  - ফ্লোর যায় কোড দিয়ে। URL-এ ULID নেই।
- **পারমিশন:** নতুন `workstudy.ot.view` আর `workstudy.ot.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → OT & Salary-তে দুটো স্ক্রিন।
- **Garment Overtime Plan** (Records | OT Plan | New OT plan row):
  - OT Plan শিট `OtPlanSheet`। URL-এ `?floor=&date=` থাকে।
  - নিচে সারাংশ থাকে: "19 Dec 2026 · 4 group(s) · 3 person-hour slot(s) planned"।
- **Monthly Salary & OT Rates** (Records | Cost Particulars | New salary and OT rate): Cost Particulars শিট `RateSheet`। URL-এ `?year=` থাকে। Records-এ Edit আর মোছা আছে।
- **PDF আর Excel:** সার্ভারের `TablePdf`। OT Plan-এর ২৫টা কলাম A3 পাতায় যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000200_create_work_study_ot_salary.php`
- `app/Modules/WorkStudy/Models/WsSalaryRate.php`
- `app/Modules/WorkStudy/Models/WsOtPlan.php`
- `app/Modules/WorkStudy/Support/WsOvertime.php`
- `app/Modules/WorkStudy/Support/WsCosting.php` (`salaryRate()`, Sal আর OTBill)
- `app/Modules/WorkStudy/Resources/SalaryRateResource.php`
- `app/Modules/WorkStudy/Resources/OtPlanResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsOvertimeApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/ot-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/overtime.jsx`
- `frontend/src/components/workstudy/costing.jsx` (শেয়ার করা অংশ export)
- `frontend/src/app/api/work-study/ot/[sheet]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`ot.*`)

**Test**
- `tests/Feature/WorkStudy/WorkStudyOvertimeTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudyOvertimeTest` ২/২ (৫৭ assertion):**
  - **রেট:**
    - একটা রেট বাদ দিলে Brickland-এর মেসেজ আসে। একই মাসে দুবার চলে না।
    - Cost Particulars-এ ১২ মাস আসে। অর্ধেক পূরণ নভেম্বর আটকায়। পুরো পূরণ করলে সেভ হয়, শূন্য করলে বাদ যায়।
  - **লাইন কস্টিং** (Brickland-এর probe লাইন): Sal[g] ১৫,৯২৩.০৮, Pro/Loss[g] −১৮,১৭০.৬৯, OTBill ৫৭.৫০ / ৩৮.৩৩ / ১৯.১৭, Total Cost ২৩,৮০৭.৩১, Profit −১৮,৭৬৩.৩১।
  - **ফাইল আর পারমিশন:** PDF আর Excel তৈরি হয়। Line Chief রেট দেখতে পায় না।
  - **OT Plan:**
    - ঘণ্টার কলাম Brickland-এর মতো। HR-এর ৬ জনের গ্রুপ MP ৬ নিয়ে আসে।
    - শূন্য প্রতি-ঘণ্টায় Brickland-এর মেসেজ আসে। ৩ ঘণ্টায় 9/10/11 slot তৈরি হয়। একই গ্রুপ দুবার চলে না।
    - শিটে MP-এর বেশি লোক আটকায়। এক ঘণ্টায় বদলালে Hours Planned ১ হয়। সব খালি করলে প্ল্যান মুছে যায়।
- **পুরনো টেস্ট আবার:** Work Study-র সব টেস্ট (১১/১১, ৩০৮ assertion) পাস।
- **ব্রাউজার (dev, os-ui1, ডাটা রাখা আছে):**
  - রেট: ডিসেম্বর ২০২৬ (১২,০০০ / ৯,০০০ / ৬০ / ৪৫) আর নভেম্বর ২০২৬ (১২,৫০০ / ৯,২০০ / ৬২ / ৪৬)।
  - 1st Floor A-1F, ১৯ ডিসেম্বর:
    - Daily Line Cost-এ এখন Sal[g] ১৫,৯২৩.০৮ আর OTBill দেখায়।
    - OT Plan: LINE01 Sewing / Jr. Operator, MP ৩৬, ৬টা–৮টা প্রতি ঘণ্টায় ৩০ OP + ৬ ASO।
  - dev-এর ৩ জন কর্মীকে A-1F ফ্লোরে বসানো হয়েছে, যাতে OT Plan-এ HR গ্রুপ দেখা যায়।
  - দুটো PDF (Cost Particulars, OT Plan) প্রিভিউ করে দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

- **২৬ দিন:** Brickland বেতনকে ২৬ দিনে ভাগ করে (probe-এর সংখ্যা তাই বলে)। এটা নির্দিষ্ট নাকি OH শিটের কর্মদিবস থেকে আসে, demo-তে দুটোই ২৬ ছিল বলে আলাদা করা যায়নি। এখানে নির্দিষ্ট ২৬ (`WsCosting::SALARY_DAYS`)।
- **OT Plan-এর ব্যবহার:** Brickland-এ OT Plan লাইন কস্টিংয়ের ManOT পূরণ করে না, আলাদা থাকে। এখানেও তাই।
- **লাইন:** HR কর্মীর লাইন machinery-র `production_lines`। প্ল্যানের লাইন সেলাইয়ের `planning_lines`। দুটো যুক্ত হয় `planning_lines.floor_line_id` দিয়ে। dev-এ এই সংযোগ নেই, তাই HR গ্রুপের লাইন ফাঁকা দেখায়।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৬):**
- দুটো স্ক্রিনই তৈরি। 14/05-এর Daily Line Cost এখন বেতন আর OT ধরে।
- **Brickland প্রোব (os01–os02):** লাইভ demo-তে লেখা হয়েছে:
  - অক্টোবর ২০২৬-এর রেট (১২,০০০ / ৯,০০০ / ৬০ / ৪৫)।
  - SWEING ফ্লোরে ২৫ অক্টোবরের একটা OT plan row।
  - তারপর 14/05-এর probe লাইনের Overall Profit থেকে Sal আর OTBill-এর সূত্র মেলানো হয়েছে।
