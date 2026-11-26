# Costing

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/05`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Spec-Wise CM Rate | `/m/ie/item-spec-wise-cm` | 3 | 10 | 1 |
| 2 | Overhead Cost Allocation | `/m/ie/oh-cost-allocation` | 4 | 9 | 1 |
| 3 | Daily Line Cost Sheet | `/m/ie/daily-line-costing-operation` | 5 | 19 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Spec-Wise CM Rate

`/m/ie/item-spec-wise-cm`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Itm Group & Category, New CM rate

**ট্যাব: Itm Group & Category**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search item groups | search |  | Search… |
- সেকশন: Itm Group, Itm Category
- বাটন/অ্যাকশন: Itm Group & Category, New CM rate, New Itm Group, Knit Tops GRP-KNIT 3, Bottoms GRP-BOTM 1, Outerwear GRP-OUTR 0

**ট্যাব: New CM rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Item Specification* | select | ✔ | Select… |
| Buyer | select |  | Select…, LPP (LPP) |
| Booking Month* | text | ✔ | 2026-08 |
| Order Qty* | number | ✔ |  |
| CM/Dz* | number | ✔ |  |
| Budget Exp % | number |  |  |
| Lead Time | number |  |  |
| Confirm Date | date |  |  |
| Ship Date | date |  |  |
- সেকশন: New CM rate
- বাটন/অ্যাকশন: Itm Group & Category, New CM rate, Reset, Save CM rate

**ডায়ালগ: New CM rate**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Item Specification* | select | ✔ | Select… |
| Buyer | select |  | Select…, LPP (LPP) |
| Booking Month* | text | ✔ |  |
| Order Qty* | number | ✔ |  |
| CM/Dz* | number | ✔ |  |
| Budget Exp % | number |  |  |
| Lead Time | number |  |  |
| Confirm Date | date |  |  |
| Ship Date | date |  |  |
- খালি Save-এ মেসেজ: CM/Dz is required.

#### Overhead Cost Allocation

`/m/ie/oh-cost-allocation`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Monthly Cost Re-arrange, Section Cost Heads, New cost head

**ট্যাব: Monthly Cost Re-arrange**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. (BCL) |
| Select Year | select |  |  |
| Month | select |  |  |
| Copy From | select |  |  |
- সেকশন: Monthly Cost Re-arrange
- বাটন/অ্যাকশন: Monthly Cost Re-arrange, Section Cost Heads, New cost head, Show, Save Sheet

**ট্যাব: Section Cost Heads**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search sections | search |  | Search… |
- সেকশন: Section, Monthly OP + ASO Salary & OT Rate
- বাটন/অ্যাকশন: Monthly Cost Re-arrange, Section Cost Heads, New cost head, New Section, Garments Section 19, Cutting Section Allocation target 11, Knitting Section Allocation target 18, Dyeing Section Allocation target 17, Printing Section Allocation target 15, Production Overhead 16, Administrative Expenses 5, Commercial Overhead 11, Financial Overhead 2, Head Office Expenses 18

**ট্যাব: New cost head**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Monthly Expense* | number | ✔ |  |
| WrkDay/Mn | number |  |  |
- সেকশন: New cost head
- বাটন/অ্যাকশন: Monthly Cost Re-arrange, Section Cost Heads, New cost head, Select…, Reset, Save cost head

**ডায়ালগ: New cost head**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Select Year* | select | ✔ | Select…, 2023, 2024, 2025, 2026, 2027, 2028, 2029 |
| Monthly Expense* | number | ✔ |  |
| WrkDay/Mn | number |  |  |
- খালি Save-এ মেসেজ: Monthly Expense is required.

#### Daily Line Cost Sheet

`/m/ie/daily-line-costing-operation`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Order Config Data, Line Wise Prod, Overall Profit Lost, New line costing row

**ট্যাব: Order Config Data**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
- সেকশন: Order Config Data
- বাটন/অ্যাকশন: Order Config Data, Line Wise Prod, Overall Profit Lost, New line costing row, Refresh, Save

**ট্যাব: Line Wise Prod**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
- সেকশন: Line Wise Prod
- বাটন/অ্যাকশন: Order Config Data, Line Wise Prod, Overall Profit Lost, New line costing row, Refresh, Save

**ট্যাব: Overall Profit Lost**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date | date |  |  |
- সেকশন: Overall Profit Lost
- বাটন/অ্যাকশন: Order Config Data, Line Wise Prod, Overall Profit Lost, New line costing row, Refresh

**ট্যাব: New line costing row**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date* | date | ✔ |  |
| Buyer | select |  | Select…, LPP (LPP) |
| StyleNo* | text | ✔ |  |
| Order No* | text | ✔ |  |
| GMT Item | text |  |  |
| %BgtAsCM | number |  |  |
| ExcRate | number |  |  |
| SMV* | number | ✔ |  |
| FOB | number |  |  |
| Rate/Pcs(CUT) | number |  |  |
| Rate/Pcs(SEW) | number |  |  |
| Rate/Pcs(FIN) | number |  |  |
- সেকশন: New line costing row
- বাটন/অ্যাকশন: Order Config Data, Line Wise Prod, Overall Profit Lost, New line costing row, Reset, Save line costing row

**ডায়ালগ: New line costing row**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Floor* | select | ✔ | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Date* | date | ✔ |  |
| Buyer | select |  | Select…, LPP (LPP) |
| StyleNo* | text | ✔ |  |
| Order No* | text | ✔ |  |
| GMT Item | text |  |  |
| %BgtAsCM | number |  |  |
| ExcRate | number |  |  |
| SMV* | number | ✔ |  |
| FOB | number |  |  |
| Rate/Pcs(CUT) | number |  |  |
| Rate/Pcs(SEW) | number |  |  |
| Rate/Pcs(FIN) | number |  |  |
- খালি Save-এ মেসেজ: SMV must be greater than zero.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy` (14/01 আর 14/04-এর পরে), নতুন টেবিল `ws_` নামে।
- **14/04 থেকে নেওয়া:**
  - Item Specification (`ws_specs`): CM রেট এই স্পেসিফিকেশন ধরে দেওয়া হয়।
  - Itm Group & Category: Spec-Wise CM Rate-এ একই ট্যাব। 14/04-এর SideGrid একটা ফাংশনে (`itemGroupsGrid`) বের করে দুই জায়গায় ব্যবহার করা হয়েছে।
  - GMT Item List-এর CM/Dz আর BgtExp% এখন এখান থেকে পড়ে।
- **Capacity Planning (12/02) থেকে নেওয়া:**
  - ফ্লোর (`floors`) আর লাইন (`planning_lines`)।
  - দিনের লাইন প্ল্যান (`planning_daily_plans`): কোন লাইনে কোন অর্ডার, manpower, ঘণ্টা আর target।
- **Merchandise থেকে নেওয়া:** বায়ার, আর অর্ডার (স্টাইল, আইটেম, FOB = unit price)।
- **PDF আর Excel:** শেয়ার করা `TablePdf` (mPDF) আর OpenSpout।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000100_create_work_study_costing`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `ws_cm_rates` | spec_id, buyer_id, booking_month (2026-08), order_qty, cm_per_dozen, budget_exp_pct, lead_time_days, confirm_date, ship_date, created_by | company + spec + booking month |
| `ws_oh_sections` | name, name_bn, sort_order, is_target ("Money may be re-arranged into it?") | company + name |
| `ws_oh_heads` | section_id, name, name_bn, sort_order, active | — |
| `ws_oh_costs` | period (2026-10), head_id, monthly_expense, work_days, cutting, sewing, knitting, dyeing, dyeing_finishing, printing | company + period + head |
| `ws_line_costings` | floor_id, cost_date, buyer_id, style_no, order_no, gmt_item, bgt_as_cm_pct, exc_rate, smv, fob, rate_cut, rate_sew, rate_fin | company + floor + date + order |
| `ws_line_productions` | floor_id, prod_date, planning_line_id, buyer_id, order_no, item_name, op, aso, wh, target, prod_g, prod_aot, prod_eot, prod_2ot, prod_5ot, manot_aot … manot_5ot | company + floor + date + line |

## ৫. Workflow / Status ও Business Rules

- **Spec-Wise CM Rate:**
  - প্রতি বুকিং মাসে একটা স্পেসিফিকেশনের একটাই CM ("This specification already has a CM rate for that booking month.")।
  - Booking Month লেখা হয় 2026-08 ধাঁচে। Order Qty আর CM/Dz লাগে। খালি Save-এ Brickland-এর মেসেজ আসে: "CM/Dz is required."।
  - Budget Exp % ০–১০০। Ship Date, Confirm Date-এর আগে হতে পারে না।
  - GMT Item List অর্ডারের বুকিং মাসে স্পেসিফিকেশনের রেট খোঁজে। না পেলে আগের মতো ০ দেখায়।
- **Overhead Cost Allocation:**
  - **শুরুর ডাটা:** প্রথমবার খুললে Brickland-এর ১১টা সেকশন আর তাদের ১৪৯টা খাত তৈরি হয়। Cutting, Knitting, Dyeing, Dyeing Finishing আর Printing "Allocation target"।
  - **Section Cost Heads:**
    - বাঁদিকে সেকশন (খাতের সংখ্যাসহ), ডানদিকে খাতের তালিকা জায়গায় বদলানো যায়।
    - যে খাতের কোনো মাসে সংখ্যা আছে, সেটা সরানো যায় না ("… clear Active instead")।
    - New Section একটা dialog।
  - **New cost head:** কোম্পানি, বছর, মাস, খাত, মাসিক খরচ আর কর্মদিবস। একটা খাতের একটা মাসে একটাই লাইন। টাকা কোথায় যায় তা লেখা হয় শিটে।
  - **Records:** Re-arranged = ছয় কলামের যোগফল। Per Day = মাসিক খরচ ÷ কর্মদিবস (কর্মদিবস না থাকলে ফাঁকা), Brickland-এর মতো।
  - **Monthly Cost Re-arrange:**
    - কোম্পানি, বছর আর মাস বেছে Show চাপলে সক্রিয় সব খাত সেকশনের ক্রমে আসে। কোনো মাসে সংখ্যা থাকা নিষ্ক্রিয় খাতও আসে।
    - কলাম: Monthly Expense, WrkDay/Mn, আর ছয়টা প্রোডাকশন কলাম (Cutting, Sewing, Knitting, Dyeing, Dyeing Finishing, Printing)।
    - **Copy From:** অন্য মাসের সংখ্যা দিয়ে গ্রিড বানায়, কিছু লেখে না। Save Sheet সবসময় উপরে বাছা মাসে লেখে।
    - বণ্টন করা লাইনে Monthly Expense লাগে। পুরো ফাঁকা লাইন সেভে মুছে যায়।
    - কর্মদিবস ফাঁকা থাকলে উপরের লাইনের মান নেয়।
- **Daily Line Cost Sheet** (ফ্লোর কোড আর তারিখ দিয়ে, Refresh চাপলে):
  - **Order Config Data:**
    - সারি = দিনের লাইন প্ল্যানে ওই ফ্লোরে লোড হওয়া অর্ডার, আর এখানে আগে কস্ট করা অর্ডার।
    - নতুন সারিতে SMV আসে প্ল্যান থেকে, FOB অর্ডারের দাম থেকে।
    - কোনো ঘরে মান থাকলে SMV আর StyleNo লাগে। কোনো ঘরেই মান না থাকলে সেভে বাদ যায়।
  - **Line Wise Prod:**
    - ফ্লোরের প্রতিটা লাইন।
    - প্ল্যান থেকে অর্ডার, ঘণ্টা আর target আসে। OP আর ASO আসে স্টাইলের স্পেসিফিকেশন থেকে (না থাকলে manpower)।
    - Prod[Total] আর Man OT[Total] নিজে যোগ হয়।
    - উৎপাদন থাকলে অর্ডার লাগে।
  - **Overall Profit Lost** (সব হিসাব করে বের করা, Brickland-এর সংখ্যার সাথে মিলিয়ে):
    - Effi = মোট উৎপাদন × SMV ÷ ((OP + ASO) × WH × ৬০) × ১০০।
    - FOB Earn = উৎপাদন × FOB।
    - CMEarn[স্তর] = সেই স্তরের উৎপাদন × (Cut + Sew + Fin রেট)।
    - **দিনের ওভারহেড** = মাসের (Cutting + Sewing) কলামের যোগ ÷ প্রতিটা লাইনের কর্মদিবস (ফাঁকা হলে উপরের লাইনের)। সেদিন যত লাইন চলেছে তাদের মধ্যে সমান ভাগ, তারপর প্রতিটা লাইনের উৎপাদন অনুযায়ী স্তরে ভাগ।
    - Pro/Loss = CMEarn − Sal (বা OTBill) − OH। Total Cost = সব Sal + OTBill + OH।
    - **বেতন আর OT রেট:** আসে Monthly Salary & OT Rates (14/06) থেকে। সেটা তৈরি না হওয়া পর্যন্ত Sal আর OTBill ০, আর শিটের নিচে Brickland-এর মতো লেখা থাকে "no salary rate for the month"।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{ws-cm-rates|ws-oh-sections|ws-oh-costs|ws-line-costings}`।
- **নিজস্ব endpoint** (`WsCostingApiController`):
  - `GET /workstudy/costing/options`
  - `GET|PUT /workstudy/costing/sections/{section}/heads`
  - `GET|PUT /workstudy/costing/sheet?period=2026-10[&copy=2026-09]`
  - `GET|PUT /workstudy/costing/day/config?floor=&date=`
  - `GET|PUT /workstudy/costing/day/lines?floor=&date=`
  - `GET /workstudy/costing/day/profit?floor=&date=`
  - `GET /workstudy/costing/export/{oh-sheet|day-config|day-lines|day-profit}/{pdf|xlsx}`
  - ফ্লোর যায় তার কোড দিয়ে, মাস 2026-10 ধাঁচে, দিন 2026-10-25 ধাঁচে। URL-এ ULID নেই।
- **পারমিশন:** নতুন `workstudy.costing.view` আর `workstudy.costing.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। Itm Group & Category ট্যাব 14/04-এর endpoint ব্যবহার করে, তাই সেটা দেখতে `workstudy.spec.view` লাগে।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Costing-এ তিনটা স্ক্রিন।
- **Spec-Wise CM Rate** (Records | Itm Group & Category | New CM rate): Records-এ Edit আর মোছা আছে। New ফর্মে USD, %, days ইউনিট দেখায়।
- **Overhead Cost Allocation** (Records | Monthly Cost Re-arrange | Section Cost Heads | New cost head):
  - Monthly শিট নিজস্ব কম্পোনেন্ট `CostSheet`।
  - URL-এ `?period=` থাকে, রিলোড ছাড়া। রিলোড দিলে একই মাস খোলে।
  - নিচে মোট সারি আছে। PDF আর Excel আছে।
- **Daily Line Cost Sheet** (Records | Order Config Data | Line Wise Prod | Overall Profit Lost | New line costing row):
  - তিনটা ট্যাবই `DaySheet`। URL-এ `?floor=&date=` থাকে।
  - প্রতিটার নিচে Brickland-এর সারাংশ লাইন থাকে, যেমন "19 Dec 2026 · 2 line(s) · CM earned … · profit …"।
  - লোকসান লাল রঙে দেখায়।
- **PDF:** সার্ভারের `TablePdf`।
  - ১৬টার বেশি কলাম হলে A3 landscape পাতা নেয়।
  - কোড বা সংখ্যার মতো ফাঁকাহীন মান এক লাইনে থাকে, যাতে "LINE0 / 1"-এর মতো শব্দ না ভাঙে। এই উন্নতি অ্যাপের সব সার্ভার-PDF পায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000100_create_work_study_costing.php`
- `app/Modules/WorkStudy/Models/WsCmRate.php`
- `app/Modules/WorkStudy/Models/WsOhSection.php`
- `app/Modules/WorkStudy/Models/WsOhHead.php`
- `app/Modules/WorkStudy/Models/WsOhCost.php`
- `app/Modules/WorkStudy/Models/WsLineCosting.php`
- `app/Modules/WorkStudy/Models/WsLineProduction.php`
- `app/Modules/WorkStudy/Support/WsCosting.php`
- `app/Modules/WorkStudy/Resources/CmRateResource.php`
- `app/Modules/WorkStudy/Resources/OhSectionResource.php`
- `app/Modules/WorkStudy/Resources/OhCostResource.php`
- `app/Modules/WorkStudy/Resources/LineCostingResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsCostingApiController.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsSpecApiController.php` (GMT Item List-এ CM/Dz আর BgtExp%)
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Shared/Pdf/TablePdf.php` (A3 পাতা, ফাঁকাহীন মান না ভাঙা)

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/cost-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/spec-screens.js` (`itemGroupsGrid`)
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/costing.jsx`
- `frontend/src/app/api/work-study/costing/[sheet]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`cost.*`)

**Test**
- `tests/Feature/WorkStudy/WorkStudyCostingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudyCostingTest` ২/২ (৮১ assertion):**
  - **CM রেট:**
    - CM/Dz ছাড়া Brickland-এর মেসেজ আসে। "Oct 2026" লিখলে মাসের ধাঁচের মেসেজ আসে। Confirm-এর আগে Ship Date চলে না।
    - একই মাসে দুবার চলে না, পরের মাসে চলে।
    - GMT Item List-এ ১৪.৫ / ৬ দেখায়।
    - Line Chief রেট বা শিট দেখতে পায় না।
  - **সেকশন আর খাত:** ১১টা সেকশন আর ১৪৯টা খাত আসে। জায়গায় নতুন খাত যোগ হয়। নতুন সেকশন (allocation target) dialog থেকে তৈরি হয়।
  - **New cost head:** খরচ ছাড়া মেসেজ আসে। Per Day ৯৬১৫.৩৮। একই মাসে দুবার চলে না।
  - **Monthly শিট:**
    - সেভ করলে Re-arranged ১,৫০,০০০ হয়।
    - বণ্টন করা লাইনে খরচ না দিলে আটকায়।
    - সংখ্যা থাকা খাত সরানো যায় না।
    - Copy From নভেম্বরে অক্টোবরের সংখ্যা আনে, কিন্তু লেখে না।
  - **দিন:**
    - প্ল্যানে লোড হওয়া অর্ডার আসে (SMV ৮.৫, FOB ৪.২)। SMV ছাড়া কস্ট করলে আটকায়।
    - লাইনে স্পেসিফিকেশনের OP ৩০ আর ASO ৬ আসে। অর্ডার ছাড়া উৎপাদনে আটকায়।
  - **Profit:** Brickland-এর হুবহু সংখ্যা মেলে: Effi ৩৮.১৭, OH[g] ৬৪০৭.৬১, Pro/Loss[g] −২২৪৭.৬১, Total Cost ৭৭৬৯.২৩, Profit −২৭২৫.২৩।
  - **ফাইল:** Profit PDF আর Monthly শিটের Excel তৈরি হয়। অজানা ফ্লোরে মেসেজ আসে।
- **পুরনো টেস্ট আবার:**
  - WorkStudySpecificationTest আর WorkStudyMasterTest (৭/৭) পাস।
  - TablePdf বদলের পর ReportExport, GatePassFlow আর GeneralStoreReport পাস।
- **ব্রাউজার (dev, cs-ui1–cs-ui4, ডাটা রাখা আছে):**
  - CM রেট: SS27-TEE01/1, 2026-10, ১৯০০, ১৪.৫। GMT Item List-এ ১৪.৫ / ৬ দেখায়।
  - ডিসেম্বর ২০২৬: Wages And Allowance ২,৫০,০০০ (২৬ দিন, Cutting ২০,০০০, Sewing ১,৩০,০০০), আর Salary And Allowance ৫২,০০০ (Sewing)।
  - 1st Floor A-1F, ১৯ ডিসেম্বর ২০২৬: LPP-2026-000001 কস্ট করা (০.৬ / ৩.৫ / ১.১)। LINE01-এ ৯৫০ আর LINE05-এ ৬৮০ পিস।
  - Profit: CM ৮৪৭৬, লাভ ৭০৬.৭৬। LINE05 লোকসানে, লাল দেখায়।
  - চারটা PDF (শিট, Order Config, Line Wise, Profit) প্রিভিউ করে ডিজাইন ঠিক করা হয়েছে।

## ১০. খোলা প্রশ্ন

- **বেতন আর OT রেট (14/06):** মীমাংসিত (২০২৬-১০-২৬)। `WsCosting::salaryRate()` এখন Monthly Salary & OT Rates থেকে পড়ে, আর Sal[g] ও OTBill Brickland-এর সূত্রে পূরণ হয়।
- **টার্গেট শিট (14/03):** মীমাংসিত (২০২৬-১০-২৭)। Daily Line Cost এখন Garment Target Setting-এর শিট থেকে অর্ডার আর OP/ASO/WH/Target নেয়। সেদিন টার্গেট না থাকলে 12/02-এর লাইন প্ল্যান থেকে।
- **কোন কলামে গার্মেন্টের ওভারহেড:** Brickland-এর probe-এ দিনের ওভারহেডে Cutting আর Sewing দুটো কলামই গোনা হয়েছে। Finishing বা Packing ফ্লোরের জন্য আলাদা নিয়ম আছে কিনা তা demo থেকে বোঝা যায়নি।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৫):**
- তিনটা স্ক্রিনই তৈরি, Brickland-এর ট্যাব, ফিল্ড, মেসেজ আর হিসাব মেনে।
- **Brickland প্রোব (cs01–cs05):** লাইভ demo-তে লেখা হয়েছে:
  - একটা CM রেট (529/1, 2026-08)।
  - অক্টোবর ২০২৬-এর একটা cost head, আর শিটে Cutting/Sewing বণ্টন।
  - SWEING ফ্লোরে ২৫ অক্টোবরের একটা line costing row আর line 1-এর উৎপাদন।
  - তা থেকে Effi, OH ভাগ আর Pro/Loss-এর সূত্র মেলানো হয়েছে।
- সেকশন আর খাতের তালিকা Brickland থেকে হুবহু নেওয়া (বানান ভুলসহ, যেমন "Conveyence")।
