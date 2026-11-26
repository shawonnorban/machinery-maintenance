# Reports

> **স্ট্যাটাস:** 🟡 আংশিক (৩৬টার ১৭টা রিপোর্ট চলে; বাকিগুলো উৎস-মডিউলের অপেক্ষায়)
> **মডিউল:** [Work Study](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `14/02`

**⬅ ফিরে যান:** [Work Study](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Work Study Reports | `/m/ie/ie-reports` | 2 | 48 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Work Study Reports

`/m/ie/ie-reports`

**ট্যাব: IE Reports**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Efficiency Report | radio |  |  |
| Date Wise NPT Report | radio |  |  |
| Date Wise Input, Output & WIP Report | radio |  |  |
| Line Wise Profit-Loss Report | radio |  |  |
| Daily Line Costing Summary | radio |  |  |
| Monthly Line Costing Summary | radio |  |  |
| Manpower Requirment Plan | radio |  |  |
| Line Wise Plan + Actual Prod With Value | radio |  |  |
| GMT Finishing WIP Report | radio |  |  |
| Floor Wise Plan Prod with Value | radio |  |  |
| Date Wise Target Summary | radio |  |  |
| Machine Variance-Layout vs Costing | radio |  |  |
| Target & Achievement Summary | radio |  |  |
| Monthly Achievement Summary | radio |  |  |
| Capacity Vs Booking Summary Report | radio |  |  |
| Date Wise Plan Vs Target Vs Input Vs Production | radio |  |  |
| Cost Analysis for Print/Embroidery | radio |  |  |
| IE-Gmt Item Analysis Report | radio |  |  |
| Target & Achv In Gen and OT Hour | radio |  |  |
| Manpower Utilization Report | radio |  |  |
| Pre-Post Layout Balancing Efficiency | radio |  |  |
| Day Wise Pre-Post Balancing Efficiency | radio |  |  |
| GMT Item Wise Profit-Loss Summary | radio |  |  |
| GMT Item Wise Pcs Transactions | radio |  |  |
| Planning Vs IE Target Vs Production Summary | radio |  |  |
| Daily Manpower Status | radio |  |  |
| Section Wise Profit-Loss Summary | radio |  |  |
| Order Wise Profit-Loss Transaction | radio |  |  |
| Line Wise Hourly Production Status | radio |  |  |
| Line Feeding & SCO Report | radio |  |  |
| OT Plan- Garment Division | radio |  |  |
| OT Plan vs Actual- Garment Division | radio |  |  |
| Line Wise Profit/Loss-Hour Slab | radio |  |  |
| Daily Manpower Utilization | radio |  |  |
| Daily Line Wise Sewing Target | radio |  |  |
| CPM Based Profitability Report | radio |  |  |
| Office | select |  | Brickland Composite Ltd. Head Office (BR-0001) |
| Buyer A/C | select |  |  |
| Work Style/Job# | select |  |  |
| Prod Flr(s) | select |  | Search…, Cuting Floor, Finishing, Finishing, Packing, SWEING |
| Production Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: IE Reports, Preview

**ট্যাব: Preview**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Unit | select |  | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Prod Flr(s) | select |  | Select…, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Work Style/Job# | text |  |  |
| Production Date* | date | ✔ |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: New report run
- বাটন/অ্যাকশন: IE Reports, Preview, Select…, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/WorkStudy`। রিপোর্টগুলো নতুন কিছু রাখে না, ওয়ার্ক স্টাডির নিজের শিট পড়ে:
  - **14/03:** টার্গেট শিট (`ws_sewing_targets`), NPT (`ws_npt_entries`), change-over (`ws_changeovers`, `ChangeoverResource::present` থেকে Feeding Start/End)।
  - **14/05:** Daily Line Cost-এর `WsCosting::profit()`। Line, Floor, Order, Item আর Hour Slab-এর লাভ-ক্ষতি সেখান থেকেই আসে, তাই রিপোর্ট আর শিটের সংখ্যা কখনো আলাদা হয় না।
  - **14/06:** OT Plan (`ws_ot_plans`)।
  - **14/08:** সেকশনের ফলাফল (`ws_section_results`, `WsSectionResult::profit()`)।
  - **12/02:** Capacity Planning-এর দিনের প্ল্যান (`planning_daily_plans`)।
- **শেয়ার্ড `TablePdf`:** প্রতিটা রিপোর্টের PDF। Excel-এর জন্য OpenSpout।
- **Brickland প্রোব:** Brickland-এর Preview বোতাম একটা server action চালায়, কিন্তু demo-তে কিছু দেখায় না (scaffold)। "Build Brickland scaffolds" সিদ্ধান্ত অনুযায়ী এখানে রিপোর্টগুলো পুরো বানানো হয়েছে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_31_000600_create_work_study_report_runs`:

| টেবিল | ফিল্ড |
|---|---|
| `ws_report_runs` | report_key, factory_id (Unit), buyer_id, floor_id, order_no (Work Style/Job#), production_date, export_excel, rows_count, created_by |

রিপোর্টের তালিকা (`WsReports::REPORTS`) কোডে আছে। প্রতিটা রিপোর্টের সাথে থাকে Brickland-এর category, প্যারামিটার আর কোন মডিউলের অপেক্ষায় আছে (যদি থাকে)।

## ৫. Workflow / Status ও Business Rules

- **IE Reports ট্যাব:** বাঁয়ে Brickland-এর ৩৬টা রিপোর্ট, একই ক্রমে। ডানে বাছা রিপোর্টের প্যারামিটার।
  - **প্যারামিটার Brickland থেকে হুবহু নেওয়া (প্রতিটা রিপোর্ট আলাদা করে প্রোব করা):** Office, Buyer A/C, Work Style/Job#, Style/Order#, Prod Flr(s), Prod Line, Production Date, From/To Date, Qty Pluck Type, Exclude car?, Day#, Export to Excel?।
  - **Preview:** রিপোর্ট সেই মুহূর্তে পড়ে ডায়ালগে দেখায়। ডায়ালগে থাকে টেবিল, মোটের সারি, সারির সংখ্যা, PDF আর Excel। Export to Excel? টিক দিলে সরাসরি Excel ডাউনলোড হয়।
  - **মোটের সারির শতাংশ:** মোট সংখ্যা থেকে আবার হিসাব হয় (যেমন মোট উৎপাদিত মিনিট ÷ মোট প্রাপ্ত মিনিট), গড় করে নয়।
  - **তারিখের সীমা:** একবারে সর্বোচ্চ ৯২ দিন। শেষের তারিখ শুরুর আগে হতে পারে না।
  - **Office:** সেই ইউনিটের (factory) বিল্ডিংয়ের ফ্লোরগুলো। Prod Flr(s) আর Prod Line একাধিক বাছা যায়।
- **যে ১৭টা রিপোর্ট চলে (উৎস):**
  - **দক্ষতা (Efficiency):**
    - **Efficiency Report:** উৎপাদিত মিনিট (উৎপাদন × SMV) ÷ প্রাপ্ত মিনিট ((OP + ASO) × WH × ৬০)।
    - **Date Wise NPT Report।**
    - **Date Wise Target Summary।**
    - **Target & Achievement Summary।**
    - **Monthly Achievement Summary:** Exclude car? টিক দিলে "car" সারি বাদ যায়।
    - **Target & Achv In Gen and OT Hour।**
    - **Daily Line Wise Sewing Target:** ঘণ্টায় টার্গেট = Target[Gen] ÷ WH।
  - **কস্টিং (Costing):**
    - **Line Wise Profit-Loss Report, Daily Line Costing Summary, Monthly Line Costing Summary, GMT Item Wise Profit-Loss Summary, Order Wise Profit-Loss Transaction, Line Wise Profit/Loss-Hour Slab:** 14/05-এর `profit()` থেকে।
    - **Section Wise Profit-Loss Summary:** 14/08 থেকে।
  - **উৎপাদন (Production):** **Line Feeding & SCO Report।**
  - **লোকবল (Manpower):** **OT Plan- Garment Division।**
  - **প্ল্যানিং (Planning):** **Planning Vs IE Target Vs Production Summary:** 12/02-এর প্ল্যান, IE টার্গেট আর উৎপাদন পাশাপাশি।
  - অফ রাখা টার্গেট-সারি (Is Off?) কোনো রিপোর্টে আসে না।
- **যে ১৯টা অপেক্ষায়:** তালিকায় থাকে, প্যারামিটারও দেখায়। Preview চাপলে বলে কোন মডিউলের জন্য অপেক্ষা করছে, কোনো সংখ্যা বানায় না। PDF/Excel দেয় না।
  - **Garments Production (সেলাইয়ের ইনপুট/আউটপুট/WIP):** Input, Output & WIP; Line Wise Plan + Actual; GMT Finishing WIP; Floor Wise Plan Prod; Date Wise Plan Vs Target Vs Input Vs Production; GMT Item Wise Pcs; Line Wise Hourly Production Status।
  - **HR হাজিরা:** Manpower Requirment Plan; Manpower Utilization; Daily Manpower Status; OT Plan vs Actual; Daily Manpower Utilization।
  - **লাইন লেআউট (Assembly Floor):** Machine Variance; IE-Gmt Item Analysis; Pre-Post Layout Balancing; Day Wise Pre-Post Balancing।
  - **Capacity booking:** Capacity Vs Booking Summary।
  - **Printing/Embroidery:** Cost Analysis for Print/Embroidery।
  - **Finance & Ledger (প্রতি মিনিটের খরচ):** CPM Based Profitability।
- **Preview ট্যাব (New report run):**
  - ফিল্ড: Report*, Unit, Buyer A/C, Prod Flr(s), Work Style/Job#, Production Date*, Export to Excel? (Brickland-এর মতো)।
  - রিপোর্ট না দিলে Brickland-এর মেসেজ আসে: "Report is required."।
  - সেভের সময় সেই দিনের রিপোর্ট পড়ে কত সারি পেল তা রাখে। Brickland-এর demo সবসময় ১ দেখায়, এখানে আসল সংখ্যা।
- **Records:** Report, Category (Brickland-এর: Efficiency/Production/Costing/Manpower/Planning/Engineering), Unit (না বাছলে "All"), Production Date, Rows, Excel?।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/ws-report-runs`।
- **নিজস্ব endpoint** (`WsReportApiController`):
  - `GET /workstudy/reports/options`
  - `GET /workstudy/reports/{report}/preview?date=|from=&to=&office=&buyer=&order=&style=&floors=&lines=&car=`
  - `GET /workstudy/reports/{report}/export/{pdf|xlsx}`
  - রিপোর্ট URL-এ যায় key দিয়ে (`target-achievement`)। Office যায় ইউনিট কোডে, Buyer বায়ার কোডে, ফ্লোর আর লাইন কোডে (`A-1F/LINE01`)। কোনো ULID URL-এ যায় না।
- **পারমিশন:** নতুন `workstudy.report.view`। পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF পায় না, কারণ রিপোর্টে লাভ-ক্ষতি আছে।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Work Study → Reports ফোল্ডার (Master Data-র পরে, Brickland-এর মতো) → Work Study Reports।
- ট্যাব: Records, IE Reports, Preview (New report run)।
- **কম্পোনেন্ট** (`reports.jsx`): IeReports। প্যারামিটার বদলায় বাছা রিপোর্ট অনুযায়ী। ফলাফল আসে ডায়ালগে, রিলোড ছাড়া।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/WorkStudy/Database/Migrations/2026_12_31_000600_create_work_study_report_runs.php`
- `app/Modules/WorkStudy/Models/WsReportRun.php`
- `app/Modules/WorkStudy/Support/WsReports.php`
- `app/Modules/WorkStudy/Resources/ReportRunResource.php`
- `app/Modules/WorkStudy/Http/Controllers/Api/WsReportApiController.php`
- `app/Modules/WorkStudy/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/work-study/[screen]/page.js`
- `frontend/src/app/(app)/work-study/[screen]/rp-screens.js`
- `frontend/src/app/(app)/work-study/[screen]/actions.js`
- `frontend/src/components/workstudy/reports.jsx`
- `frontend/src/app/api/work-study/reports/[report]/[format]/route.js`

**Lang**
- `lang/en/workstudy.php`, `lang/bn/workstudy.php` (`rp.*`)

**Tooling**
- `AnnotechERP/tools/erp_crawler/panel.mjs`: নতুন `popclick` (নতুন ট্যাব ধরা) আর `netclick` (একটা ক্লিকে কী রিকোয়েস্ট যায়) ধাপ।

**Test**
- `tests/Feature/WorkStudy/WorkStudyReportsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন। অপেক্ষার ১৯টা রিপোর্ট উৎস-মডিউল তৈরি হলে `WsReports`-এ যোগ হবে।_

## ৯. টেস্ট ও যাচাই

- **`WorkStudyReportsTest` ১/১ (৪৪ assertion):**
  - **তালিকা:** ৩৬টা রিপোর্ট, প্রথমটা Efficiency/Efficiency। লাইন যায় কোডে। ১৭টা চলে।
  - **Efficiency:** ৬৯.৪৪% আর ৫০.৯৩%, মোট ৬০.১৯% (মোট থেকে আবার হিসাব)। অফ সারি বাদ। তারিখ ছাড়া আটকায়। অর্ডার আর Office দিয়ে ছাঁকা।
  - **অর্জন:** ৯৩.৫২% আর ১১০%। Monthly-তে car বাদ। Gen আর OT আলাদা (৯২.৫৯%, ১০০%)। উল্টো তারিখ আর ৯২ দিনের বেশি আটকায়। ঘণ্টায় টার্গেট ১৪০।
  - **NPT আর SCO:** ১২০ NPT। Feeding Start/End আর Finalized।
  - **লাভ-ক্ষতি:** ৫০০ পিস × ২.৫ = ১,২৫০ CM। Hour Slab-এ Gen ১,০০০ আর AOT ২৫০। Daily, Item, Order।
  - **সেকশন:** দুই দিনে আয় ১৪,০০০, লাভ ৩,০০০, Profit।
  - **অপেক্ষার রিপোর্ট:** "garments" বলে, কোনো সারি দেয় না। তার PDF 404।
  - **ফাইল:** PDF আর Excel তৈরি হয়।
  - **Report run:** রিপোর্ট ছাড়া Brickland-এর মেসেজ। Efficiency-তে ২ সারি, অপেক্ষার রিপোর্টে ০।
  - **পারমিশন:** Line Chief ঢুকতে পারে না।
- **ব্রাউজার (dev, ui_rp1–ui_rp2):** dev ডাটা থেকে ডিসেম্বর ২০২৬:
  - Target & Achievement: LINE01, ১,২২০ / ৯৫০, ৭৭.৮৭%।
  - NPT ১২০।
  - Line P/L: LINE01 আর LINE05, মোট −৩১,১৯৬.৮৮ (14/05-এর শিটের সাথে মেলে)।
  - Section P/L: মোট লাভ ২,৮০০।
  - Input/Output/WIP দেখায় যে Garments Production-এর অপেক্ষায়।
  - New report run: Efficiency, A-1F, ১৯ ডিসেম্বর, ১ সারি (dev DB-তে রাখা)।
  - তিনটা PDF (Target & Achievement, Line P/L, Section P/L) প্রিভিউ করা হয়েছে।

## ১০. খোলা প্রশ্ন

- **রিপোর্টের কলাম:** Brickland-এর demo কোনো রিপোর্ট দেখায় না, তাই কলাম রিপোর্টের নাম আর আমাদের শিটের ডাটা থেকে ঠিক করা হয়েছে। Brickland-এর আসল প্রিন্ট পাওয়া গেলে মিলিয়ে নেওয়া উচিত।
- **NPT-এর Gmt. Section আর Fin Teams:** Brickland-এর NPT রিপোর্ট এ দুটো দিয়েও ছাঁকে। আমাদের NPT-তে সেকশন বা ফিনিশিং টিম রাখা হয় না, তাই এ দুটো দেখানো হয়নি।
- **Qty Pluck Type আর Day#:** শুধু অপেক্ষার রিপোর্টে আছে। সেগুলো তৈরি হলে কাজে লাগবে।
- **Rows:** Brickland-এর run সবসময় ১ দেখায়। এখানে আসল সারির সংখ্যা রাখা হয়।
- **বাকি ১৯টা রিপোর্ট:** Garments Production, HR হাজিরা, Assembly Floor-এর লেআউট, Capacity booking, Printing/Embroidery আর Finance-এর প্রতি-মিনিট খরচ তৈরি হলে এখানে যোগ হবে।

## ১১. As-built নোট

**আংশিক সম্পন্ন (২০২৬-১০-২৭):**
- স্ক্রিন, তিনটা ট্যাব, ৩৬টা রিপোর্টের তালিকা আর প্যারামিটার তৈরি। ১৭টা রিপোর্ট চলে।
- **Brickland প্রোব (rp01–rp10):**
  - ৩৬টা রিপোর্ট আলাদা করে বেছে প্রতিটার প্যারামিটার তোলা হয়েছে।
  - প্রতিটার একটা run সেভ করে Category তোলা হয়েছে।
  - Preview-এর নেটওয়ার্ক দেখা হয়েছে: একটা server action, কোনো ফলাফল দেখায় না।
