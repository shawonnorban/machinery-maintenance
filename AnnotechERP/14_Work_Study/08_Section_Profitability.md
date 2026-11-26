# Section Profitability

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/08`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Section Profit & Loss Setup | `/m/ie/sec-wise-profit-loss-config` | 3 | 15 | 1 |
| 2 | Section Profit & Loss Entry | `/m/ie/sec-wise-profit-loss-trans` | 3 | 12 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Section Profit & Loss Setup

`/m/ie/sec-wise-profit-loss-config`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Select Year | select |  | 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
- সেকশন: Selection
- বাটন/অ্যাকশন: Income Target Config, New income target, -- Month --, Show, Update, Excel, Reset

**ট্যাব: Income Target Config**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Select Year | select |  |  |
| Month | select |  |  |
| Copy From | select |  |  |
- সেকশন: Income Target Config
- বাটন/অ্যাকশন: Income Target Config, New income target, Show, Save, Update

**ট্যাব: New income target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Sub-Section | text |  |  |
| Daily Prod Trgt* | number | ✔ |  |
| Daily Prod Trgt (In-house) | number |  |  |
| Daily Prod Trgt (Sub-contract) | number |  |  |
| Avg Rate* | number | ✔ |  |
| WrkDay/Mn* | number | ✔ |  |
| OH Calculated (BDT) | number |  |  |
- সেকশন: New income target
- বাটন/অ্যাকশন: Income Target Config, New income target, Select…, Reset, Save income target

**ডায়ালগ: New income target**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Sub-Section | text |  |  |
| Daily Prod Trgt* | number | ✔ |  |
| Daily Prod Trgt (In-house) | number |  |  |
| Daily Prod Trgt (Sub-contract) | number |  |  |
| Avg Rate* | number | ✔ |  |
| WrkDay/Mn* | number | ✔ |  |
| OH Calculated (BDT) | number |  |  |
- খালি Save-এ মেসেজ: Daily production target and average rate are both required.

#### Section Profit & Loss Entry

`/m/ie/sec-wise-profit-loss-trans`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: SecWise Profit/Loss-Trans, New section result

**ট্যাব: SecWise Profit/Loss-Trans**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Date | date |  |  |
- সেকশন: SecWise Profit/Loss-Trans
- বাটন/অ্যাকশন: SecWise Profit/Loss-Trans, New section result, Refresh, Save

**ট্যাব: New section result**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Date* | date | ✔ |  |
| Income Target | number |  |  |
| Income | number |  |  |
| Prod Target | number |  |  |
| Prod Qty | number |  |  |
| OH Cost | number |  |  |
| Mat Cost | number |  |  |
| Expense | number |  |  |
| Section Comments | textarea |  |  |
- সেকশন: New section result
- বাটন/অ্যাকশন: SecWise Profit/Loss-Trans, New section result, Select…, Reset, Save section result

**ডায়ালগ: New section result**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Date* | date | ✔ |  |
| Income Target | number |  |  |
| Income | number |  |  |
| Prod Target | number |  |  |
| Prod Qty | number |  |  |
| OH Cost | number |  |  |
| Mat Cost | number |  |  |
| Expense | number |  |  |
| Section Comments | textarea |  |  |
- খালি Save-এ মেসেজ: Company is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy`, নতুন টেবিল `ws_` নামে।
- **Company Management / HR থেকে নেওয়া:** সেকশন (`sections`, বিভাগসহ)। Brickland-এর পিকারের মতো "Section — Department" নামে দেখায়। শিটে কোম্পানির সব চালু সেকশন আসে।
- **14/03 থেকে নেওয়া:** কোম্পানি/বছর/মাস ফর্মের ধাঁচ (Excess Yarn-এর মতো)।
- **14/05 থেকে নেওয়া:** শিটের কার্ডের অংশগুলো (`costing.jsx`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000500_create_work_study_section_profit`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_section_targets` | period, section_id, sub_section, daily_target, daily_target_inhouse (SC-INH), daily_target_subcon (SC-OH), avg_rate, work_days, **target_income** (হিসাব করা), oh_calculated | company + period + section |
| `ws_section_results` | result_date, section_id, income_target, income, prod_target, prod_qty, oh_cost, mat_cost, expense, comments, created_by | company + date + section |

Profit/Loss টেবিলে রাখা হয় না, পড়ার সময় হিসাব হয়।

## ৫. Workflow / Status ও Business Rules

- **Section Profit & Loss Setup:**
  - **Target Income** = Daily Prod Trgt × Avg Rate × WrkDay/Mn, সেভের সময় হিসাব হয়, কখনো লেখা হয় না। Brickland-এর probe: ৫,০০০ × ১.৫ × ২৬ = ১,৯৫,০০০।
  - **Income Target Config শিট:**
    - কোম্পানি, বছর আর মাস ("2026 | October") বেছে Show চাপলে সব সেকশন আসে।
    - কলাম: Section, Sub-Section, Unit (Pcs), Daily Prod Trgt, SC-INH, SC-OH, Avg Rate, WrkDay/Mn, Target Income, OH Calculated।
    - নতুন সারি খোলে Sub-Section "Cutting [BDT=]" আর WrkDay/Mn ২৬-এ, Brickland-এর মতো।
    - দৈনিক টার্গেট ছাড়া সেকশন কিছু লেখে না। টার্গেট মুছলে সেকশনটা বাদ যায়। টার্গেট আছে কিন্তু রেট নেই, এমন হলে আটকায়।
    - **Update (Copy From):** অন্য মাসের সংখ্যা এই মাসে লেখে। এখানে আগে থেকে সেট করা সেকশন ছোঁয় না।
    - সারাংশ: "2026 | December · 144 section(s) · 2 set · target income 595,000.00 BDT"।
  - **New income target:** দৈনিক টার্গেট বা রেট না দিলে Brickland-এর মেসেজ আসে: "Daily production target and average rate are both required."। একটা সেকশনের এক মাসে একটাই টার্গেট। OH Calculated এখানে লেখা যায়, শিটে শুধু দেখায়।
  - **Records:** Section, Sub-Section, Period, তিনটা দৈনিক টার্গেট, Avg Rate, WrkDay/Mn, Target Income, OH Calculated।
- **Section Profit & Loss Entry:**
  - **SecWise Profit/Loss-Trans শিট:**
    - কোম্পানি আর তারিখ বেছে Refresh চাপলে সব সেকশন আসে।
    - **IncomeTarget** খোলে সেই মাসের দিনের ভাগে (দৈনিক টার্গেট × Avg Rate)। **Prod Target** খোলে দৈনিক টার্গেটে। Brickland-এর probe: ৭,৫০০ আর ৫,০০০।
    - **Profit/Loss** = Income − OH Cost − Mat Cost − Expense, কখনো লেখা হয় না। Brickland-এর probe: ৮,০০০ − (২,০০০ + ৩,০০০ + ৫০০) = ২,৫০০।
    - আয়, উৎপাদন, তিনটা খরচ সব শূন্য আর মন্তব্য খালি হলে সেকশন কিছু লেখে না। শুধু টার্গেট (যেটা নিজে থেকে খোলে) পোস্ট হয় না। পোস্ট করা সেকশন খালি করলে মুছে যায়।
    - সারাংশ: "19 Dec 2026 · 144 section(s) · 2 posted · profit 1,000.00 BDT"।
  - **New section result:** কোম্পানি না দিলে Brickland-এর মেসেজ আসে: "Company is required."। একটা সেকশনের একদিনে একটাই ফলাফল।
  - **Records:** Section, Date, IncomeTarget, Income, Prod Target, Prod Qty, Total Cost, Profit/Loss, Status (Profit / Loss / Break-even)।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-section-targets|ws-section-results}`।
- **নিজস্ব endpoint** (`WsSectionProfitApiController`):
  - `GET /workstudy/section-pl/options`
  - `GET|PUT /workstudy/section-pl/targets?period=`, `POST …/targets/copy`
  - `GET|PUT /workstudy/section-pl/results?date=`
  - `GET /workstudy/section-pl/export/{targets|results}/{pdf|xlsx}`
- **পারমিশন:** নতুন `workstudy.profit.view` আর `workstudy.profit.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF কিছুই পায় না, কারণ এটা ফ্লোরের কাজ নয়।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Section Profitability ফোল্ডার (Floor Operations-এর পরে, Brickland-এর ক্রমে), দুটো স্ক্রিন।
- **শিটের কম্পোনেন্ট** (`section-profit.jsx`): TargetConfigSheet, ResultSheet।
- **URL:** মাস (`?period=`) আর তারিখ (`?date=`) URL-এ থাকে, রিলোড ছাড়া।
- **PDF আর Excel:** সার্ভারের `TablePdf`, দুটো শিটেই (Brickland-এ দুটোতেই আছে)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000500_create_work_study_section_profit.php`
- `app/Modules/WorkStudy/Models/WsSectionTarget.php`
- `app/Modules/WorkStudy/Models/WsSectionResult.php`
- `app/Modules/WorkStudy/Support/WsSectionProfit.php`
- `app/Modules/WorkStudy/Resources/SectionTargetResource.php`
- `app/Modules/WorkStudy/Resources/SectionResultResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsSectionProfitApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/sp-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/section-profit.jsx`
- `frontend/src/app/api/work-study/section-pl/[sheet]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`sp.*`)

**Test**
- `tests/Feature/WorkStudy/WorkStudySectionProfitTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudySectionProfitTest` ১/১ (৪৭ assertion):**
  - **টার্গেট:** টার্গেট আর রেট ছাড়া Brickland-এর মেসেজ (দুই ঘরেই)। ১,৯৫,০০০, ডিফল্ট Sub-Section, পাঠানো target_income উপেক্ষিত। একই সেকশন-মাস দুবার চলে না।
  - **শিট:** সব সেকশন, WrkDay/Mn ২৬। রেট ছাড়া টার্গেট আটকায়। ৪,০০০ × ২ (রেট ৪, ২৫ দিন) = ২,০০,০০০। টার্গেট মুছলে বাদ যায়।
  - **Update:** নভেম্বরে আগে থেকে সেট করা Cutting ছোঁয় না, শুধু Sewing আনে। একই মাস থেকে কপি আটকায়।
  - **ফলাফল:** ৭,৫০০ আর ৫,০০০ খোলে। ২,৫০০ লাভ, Total Cost ৫,৫০০, Profit। কোম্পানি ছাড়া Brickland-এর মেসেজ। Loss। একই সেকশন-দিন দুবার চলে না। খালি করলে মুছে যায়।
  - **ফাইল আর পারমিশন:** দুটো PDF আর Excel তৈরি হয়। Line Chief ঢুকতে পারে না।
- **পুরনো টেস্ট আবার:** Work Study-র সব টেস্ট (১৬/১৬, ৫০৬ assertion) পাস।
- **ব্রাউজার (dev, ui_sp1–ui_sp4, ডাটা রাখা আছে):**
  - টার্গেট ডিসেম্বর ২০২৬: Cutting (৫,০০০ / ১,০০০ / ৫০০, ১.৫, ২৬ → ১,৯৫,০০০) আর Sewing Input (৪,০০০, ৪, ২৫ → ৪,০০,০০০)। Update দিয়ে জানুয়ারি ২০২৭-এ দুটোই আনা হয়েছে।
  - ফলাফল ১৯ ডিসেম্বর: Cutting লাভ ২,৫০০, Sewing Input ক্ষতি ১,৫০০ (মোট ১,০০০)। New ফর্মে ২০ ডিসেম্বর Cutting লাভ ১,৮০০।
  - দুটো PDF (Income Target Config, SecWise Profit/Loss-Trans) প্রিভিউ করে দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

- **OH Calculated কোথা থেকে আসবে:** Brickland-এর শিটে এটা শুধু দেখায় (demo-তে ০), New ফর্মে লেখা যায়। এখানেও তাই। 14/05-এর OH Cost Allocation থেকে সেকশনভিত্তিক হিসাব করে বসানো উচিত কিনা, Brickland-এ দেখা যায়নি।
- **"[BDT=]" Sub-Section:** Brickland নতুন সারিতে "Cutting [BDT=]" বসায়। এর মানে স্পষ্ট নয় (সম্ভবত টাকার অঙ্ক লেখার জায়গা)। হুবহু রাখা হয়েছে।
- **Records-এর Selection প্যানেল:** Brickland-এর Setup-এর Records ট্যাবে কোম্পানি/বছর/মাস/Copy From আর Show/Update/Excel থাকে। এখানে Records সাধারণ গ্রিড (খোঁজা আর Export সহ)। মাস ধরে দেখা আর Update Income Target Config ট্যাবে আছে, তাই একই জিনিস দুই জায়গায় রাখা হয়নি।
- **আয় আর খরচের উৎস:** Income, OH, Mat Cost আর Expense এখন হাতে লেখা হয় (Brickland-এর মতো)। পরে Accounting আর Store মডিউল থেকে আসবে কিনা, রিপোর্ট (14/02) বানানোর সময় দেখা হবে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৭):**
- দুটো স্ক্রিনই তৈরি।
- **Brickland প্রোব (sp01–sp05):** লাইভ demo-তে লেখা হয়েছে:
  - একটা আয়ের টার্গেট (Cutting, অক্টোবর ২০২৬, ৫,০০০ / ১,০০০ / ৫০০, ১.৫, ২৬)।
  - একটা সেকশনের ফলাফল (Cutting, ২৫ অক্টোবর, ৮,০০০ আয়, তিনটা খরচ ২,০০০ / ৩,০০০ / ৫০০)।
  - সেগুলো থেকে Target Income, দিনের ভাগ আর Profit/Loss-এর সূত্র মেলানো হয়েছে।
