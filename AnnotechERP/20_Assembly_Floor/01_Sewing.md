# Sewing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/01`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sewing Dashboard | `/m/garments/sewing-dashboard` | 1 | 19 | 0 |
| 2 | Bundle IN | `/m/garments/sewing-bundle-in` | 1 | 6 | 0 |
| 3 | Bundle OUT | `/m/garments/sewing-bundle-out` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sewing Dashboard

`/m/garments/sewing-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
| Search Every sewing line for the day — target, output, achievement, efficiency,  | search |  | Search 13 records… |
| Filter by Line | search |  |  |
| Filter by Floor | search |  |  |
| Filter by Style | search |  |  |
| Filter by Target | search |  |  |
| Filter by Output | search |  |  |
| Filter by Achv % | search |  |  |
| Filter by Eff % | search |  |  |
| Filter by Bdl IN | search |  |  |
| Filter by Running | search |  |  |
| Filter by Bdl OUT | search |  |  |
| Filter by WIP | search |  |  |
| Filter by Good | search |  |  |
| Filter by Rework | search |  |  |
| Filter by Reject | search |  |  |
| Filter by QC Pend | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Line, Floor, Style, Target, Output, Achv %, Eff %, Bdl IN, Running, Bdl OUT, WIP, Good, Rework, Reject, QC Pend, Status
- সেকশন: Floor
- বাটন/অ্যাকশন: -- All Lines --, Show, Today, Export, Line, Floor, Style, Target, Output, Achv %, Eff %, Bdl IN, Running, Bdl OUT, WIP, Good, Rework, Reject, QC Pend, Status

#### Bundle IN

`/m/garments/sewing-bundle-in`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Receive Date 09 Oct 2026 08 Oct 2026 07 Oct 2026 06 Oct 2026 05 Oct 2026 04 Oct  | select |  | 09 Oct 2026, 08 Oct 2026, 07 Oct 2026, 06 Oct 2026, 05 Oct 2026, 04 Oct 2026, 03 Oct 2026, 02 Oct 2026, 01 Oct 2026, 30  |
| Section | text |  |  |
| Floor* | select | ✔ | SWEING |
| Line* | select | ✔ |  |
| Scan Bundle Barcode | text |  | Scan Here |
| Short | number |  |  |
- গ্রিড কলাম: Select, SL, Time, Bundle No., Order / PO, Card No, Cutting#, Buyer, Style, Item, Color, Size, Bundle Qty, Qty, Short, Section, Floor, Line, SMV, Manpower, Daily Target, Bundle Status, Status, Action
- সেকশন: Sewing - Bundle IN, Line-wise Bundle IN
- বাটন/অ্যাকশন: Scan, Clear, Confirm Bundle IN, Take Action, Clear day, Show

#### Bundle OUT

`/m/garments/sewing-bundle-out`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Receive Date 09 Oct 2026 08 Oct 2026 07 Oct 2026 06 Oct 2026 05 Oct 2026 04 Oct  | select |  | 09 Oct 2026, 08 Oct 2026, 07 Oct 2026, 06 Oct 2026, 05 Oct 2026, 04 Oct 2026, 03 Oct 2026, 02 Oct 2026, 01 Oct 2026, 30  |
| Bundle Card | text |  | Bundle Card |
- গ্রিড কলাম: Select, Bundle Card, Card No, Cutting#, Buyer, Style, Item, Color, Size, Line, Bundle IN, Output, Balance, Bundle Status, Out At, Status, Action
- সেকশন: Sewing Bundle OUT, Sewing WIP by Line
- বাটন/অ্যাকশন: Receive, 0 Full Bundle, 0 Short Output, Take Action, Clear day, Show
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **19 Cutting Floor-এর `cut_bundles`:** স্ক্যান করা কার্ড এখান থেকে আসে। বান্ডেল নম্বর (BND-20261106-0001) বা বারকোড (BND202611060001), দুটোই চলে।
- **Capacity Planning-এর `planning_lines` আর `floors`:** ফ্লোর আর লাইনের তালিকা।
- **Work Study-র `ws_sewing_targets`:** লাইনের দিনের প্ল্যান — স্টাইল, আইটেম, SMV, ম্যানপাওয়ার (Used OP + ASO), টার্গেট (General + OT), কাজের ঘণ্টা।
  - টার্গেট শিট না থাকলে `ws_line_allocations`-এর খোলা অ্যালোকেশন (স্টাইল, SMV) আর লাইনের standard manpower।
- **গ্রিড:** `RecordsGrid` (Search, Export, পেজিং)। টিক বক্স আর Withdraw বাটন কলামের `render` দিয়ে।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন মডিউল `app/Modules/Assembly`, মাইগ্রেশন `2027_02_08_000100_create_asm_sewing.php`।

- **`asm_bundle_ins`** (Bundle IN): `bundle_id` (unique — একটা কার্ড একবারই ঢোকে), `receive_date`, `section` (ডিফল্ট "Sewing"), `floor_id`, `line_id`, `bundle_qty`, `short_qty`, `qty` (= bundle − short, লাইন যা পায়), আর সেই মুহূর্তের প্ল্যান `smv`, `manpower`, `daily_target`।
- **`asm_bundle_outs`** (Bundle OUT): `bundle_in_id` (unique — একবারই আউট), `out_date`, `mode` (FULL / SHORT), `output_qty`, `balance_qty` (= ইন − আউটপুট)।

## ৫. Workflow / Status ও Business Rules

- **ডেস্কের দিন:** Receive Date-এ আজ আর আগের ৬ দিন (মোট ৭ দিন)। এর বাইরের তারিখে স্ক্যান আটকায়।
- **Bundle IN:**
  1. Floor আর Line বাছাই (Section লেখা যায়, ডিফল্ট Sewing)। লাইন ওই ফ্লোরের চালু লাইন হতে হয়।
  2. কার্ড স্ক্যান বা হাতে লেখা, সাথে Short (ব্যাগে যে পিস কম)।
  3. Scan চাপলে কিছু সেভ হয় না। কার্ডের তথ্য আর লাইনের প্ল্যান (স্টাইল, SMV, ম্যানপাওয়ার, টার্গেট) পাশে দেখায়। লাইনের প্ল্যানের স্টাইল আর বান্ডেলের স্টাইল আলাদা হলে হলুদ সতর্কবার্তা দেখায় (আটকায় না)।
  4. Confirm Bundle IN চাপলে সেভ হয়।
  - কার্ড প্রিন্ট হয়ে থাকতে হয়। বাতিল বা প্রিন্ট-না-হওয়া কার্ড আটকায়। একটা কার্ড একবারই রিসিভ হয় (আগে হলে তারিখ আর লাইনসহ জানায়)।
  - Short বান্ডেলের পরিমাণের চেয়ে কম হতে হয়। কার্ড কাটার তারিখের আগের দিনে রিসিভ হয় না।
- **Bundle OUT:** প্রতিটা স্ক্যান নিজেই রেকর্ড হয়, আলাদা Save নেই।
  - Full Bundle: লাইন যা পেয়েছিল পুরোটাই আউটপুট।
  - Short Output: আগে Output Qty লিখতে হয়, তারপর স্ক্যান। ব্যালান্স = ইন − আউটপুট।
  - আউটপুট কখনো ইনের বেশি না। সেলাইয়ে না ঢোকা কার্ড আটকায়। একবারই আউট। ইনের তারিখের আগে আউট হয় না।
- **বান্ডেলের অবস্থা:** In Sewing → (আউট হলে) Sewing QC Pending।
- **Withdraw (Take Action = টিক দেওয়াগুলো, Clear day = দিনের সবগুলো যা তোলা যায়):**
  - দিন খোলা থাকতে (৭ দিনের মধ্যে) তোলা যায়।
  - বান্ডেল আউট হয়ে গেলে তার ইন তোলা যায় না; আগে আউট তুলতে হয়। আউট তুললে বান্ডেল আবার লাইনে ফিরে যায়।
- **Sewing Dashboard (তারিখ ও লাইন অনুযায়ী):** প্রতিটা চালু লাইনের জন্য:
  - Target ও Style: সেদিনের টার্গেট শিট থেকে। Output: সেদিনের আউটপুট।
  - Achv % = Output ÷ Target। Eff % = Output × SMV ÷ (ম্যানপাওয়ার × ঘণ্টা × ৬০)।
  - Bdl IN / Bdl OUT সেদিনের। Running = ঢুকেছে কিন্তু সেদিন পর্যন্ত আউট হয়নি এমন বান্ডেল। WIP = সেদিন পর্যন্ত ইন − আউটপুট।
  - QC Pend = সেদিন পর্যন্ত মোট আউটপুট। Good / Rework / Reject আসবে 20/03 Quality Management-এর সাথে, ততদিন ০।
  - Status: Running (লাইনে বান্ডেল আছে বা সেদিন স্ক্যান হয়েছে), নইলে Idle।

## ৬. API ও পারমিশন

- **পারমিশন:** `assembly.sewing.view`, `assembly.sewing.manage`। FACTORY_ADMIN, FACTORY_MANAGER আর LINE_CHIEF দুটোই পায়।
- **API (`SewingApiController`):**
  - `GET /assembly/sewing/options` — দিনগুলো, ফ্লোর, লাইন
  - `GET /assembly/sewing/resolve?code=&date=&line_id=` — সেভের আগে কার্ড আর প্ল্যান
  - `GET /assembly/sewing/ins?date=` · `POST /assembly/sewing/ins` · `POST /assembly/sewing/ins/withdraw`
  - `GET /assembly/sewing/outs?date=` · `POST /assembly/sewing/outs` · `POST /assembly/sewing/outs/withdraw`
  - `GET /assembly/sewing/dashboard?date=&line_id=`
- **মেনু:** নতুন মডিউল Assembly Floor (PRODUCTION গ্রুপ, Cutting Floor-এর পরে), ফোল্ডার SEWING, ৩টা স্ক্রিন: `/assembly-floor/sewing-dashboard`, `/assembly-floor/bundle-in`, `/assembly-floor/bundle-out`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `assembly-floor/[screen]/page.js`, `screens.js`, `actions.js`; কম্পোনেন্ট `components/assembly/sewing.jsx` (`SewingNav`, `BundleInDesk`, `BundleOutDesk`, `SewingBoard`)।
- **গঠন (Brickland-এর মতো):**
  - ওপরে Dashboard / Bundle IN / Bundle OUT বাটন।
  - Bundle IN: ৪ ধাপের স্ট্রিপ (Select Location → Scan Bundle → Auto Fetch Planning → Confirm), চিপ (Bundle IN, IN Qty, Short), লোকেশনের বার্তা, স্ক্যান বক্স, ডানে কার্ড ও প্ল্যান, Recently Scanned Bundles গ্রিড, নিচে GO >> Bundle OUT / Take Action / Clear day, আর Line-wise Bundle IN (Show)।
  - Bundle OUT: Receive Date, Bundle Card, Receive, Full Bundle / Short Output (সংখ্যাসহ), চিপ (Bundle OUT, Sewing Output, Balance), গ্রিড, GO বাটন, Sewing WIP by Line (Show)।
  - Dashboard: Date, Line (-- All Lines --), Show, Today, গ্রিড (Export গ্রিডেই)।
- তারিখ বদলালে পেজ রিলোড হয় না, শুধু ডাটা আসে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_08_000100_create_asm_sewing.php`
- `app/Modules/Assembly/Models/AsmBundleIn.php`, `app/Modules/Assembly/Models/AsmBundleOut.php`
- `app/Modules/Assembly/Support/SewingFloor.php`
- `app/Modules/Assembly/Http/Controllers/Api/SewingApiController.php`
- `app/Modules/Assembly/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (নতুন), `lang/en/nav.php`, `lang/bn/nav.php` (বদলানো)

**Frontend**
- `frontend/src/app/(app)/assembly-floor/[screen]/page.js`, `screens.js`, `actions.js`
- `frontend/src/components/assembly/sewing.jsx`

**Test**
- `tests/Feature/Assembly/AssemblySewingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblySewingTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ১১৬টা assertion।
- `test_a_bundle_goes_into_sewing_once_with_the_lines_plan`:
  - ৭টা দিন, ২টা ফ্লোর, ৩টা লাইন।
  - ছোট হাতের বারকোডেও কার্ড মেলে। প্ল্যান: 868MI, SMV 10, ম্যানপাওয়ার ৩৬, টার্গেট ৩৬০। resolve-এ কিছু সেভ হয় না।
  - প্রিন্ট-না-হওয়া, বাতিল আর অজানা কার্ড আটকায়। অন্য ফ্লোরের লাইন, ৭ দিনের বাইরের তারিখ, পুরো-বান্ডেল শর্ট আর ফ্লোর ছাড়া আটকায়।
  - Short ২ দিয়ে রিসিভ: লাইন পায় ৩৪। দ্বিতীয়বার আটকায় (তারিখ আর লাইনসহ)।
  - টার্গেট শিট ছাড়া লাইনে অ্যালোকেশনের SMV 9.5 আর standard manpower ৪০, টার্গেট নেই।
  - দিনের চিপ ১ / ৩৪ / ২, লাইন অনুযায়ী সারাংশ।
- `test_a_bundle_comes_off_the_line_never_more_than_went_on`:
  - রিসিভ না হওয়া কার্ড, Output Qty ছাড়া শর্ট, ৩৫ (> ৩৪), ইনের আগের তারিখ — সব আটকায়।
  - শর্ট ৩০ → ব্যালান্স ৪, QC Pending। দ্বিতীয়বার আটকায়। Full → ১০।
  - দিনের চিপ ২ / ৪০ / ৪, Full ১ / Short ১। লাইন অনুযায়ী WIP।
  - আউট হওয়া বান্ডেলের ইন তোলা আটকায়; আগে আউট তুলে তারপর ইন তোলা যায়, আবার রিসিভ হয়।
  - এক সপ্তাহ পরে দিন বন্ধ: কিছুই তোলা যায় না।
- `test_the_dashboard_reads_every_lines_day`:
  - LINE01: টার্গেট ৩৬০, আউটপুট ৫০, অর্জন 13.89%, দক্ষতা 2.31%, ইন ১, আউট ২, WIP ৪, QC Pend ৫০, Running।
  - LINE09: টার্গেট নেই, Running ১, WIP ১০। LINE02 Idle।
  - আগের দিন LINE01-এ একটা বান্ডেল চলছিল (WIP ২০)। লাইন ফিল্টারে ১টা সারি।
- `test_who_may_scan_bundles_on_and_off_the_lines`: LINE_CHIEF স্ক্যান করতে পারে। STORE_MANAGER আর TECHNICIAN পায় না।

**Regression:** `MenuAccessTest` (৬টা, ৩৪টা), `RoleMatrixTest` (৭টা, ৫০টা) আর `CuttingBundleTest` (৪টা, ৮০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/sw01`), dev অ্যাপে:**
- Bundle IN: 1st Floor / LINE01। BND202611060001 স্ক্যান, Short ১: প্রিভিউতে কার্ড আর "Line is handed 35"। এই লাইনে আজকের টার্গেট শিট নেই, তাই প্ল্যানে সেই বার্তা দেখায়।
- দুটো কার্ড কনফার্ম: চিপ Bundle IN 2 · IN Qty 71 · Short 1। Line-wise: LINE01 / ২ / ৭২ / ১ / ৭১।
- Bundle OUT: 0001 Full (৩৫), 0002 Short Output ৩০ (ব্যালান্স ৬)। চিপ ২ / ৬৫ / ৬। WIP by Line: ৭১ / ৬৫ / ৬।
- Dashboard: LINE01 আউটপুট ৬৫, ইন ২, আউট ২, WIP ৬, QC Pend ৬৫, Running। বাকি ৭টা লাইন Idle।
- **error:** ০।

**প্রিন্ট:** Brickland-এর এই তিন স্ক্রিনে Print বাটন নেই। গ্রিডের Export দিয়ে ফাইল নেওয়া যায়।

## ১০. খোলা প্রশ্ন

১. **"Finalizing closes it":** Brickland-এর Bundle IN ডেস্কে লেখা আছে দিন finalize হলে রিসিপ্ট আর তোলা যায় না, কিন্তু finalize করার কোনো বাটন বা স্ক্রিন পাওয়া যায়নি। এখন দিন খোলা থাকে ৭ দিন (আজ আর আগের ৬ দিন)। আলাদা Day Finalize লাগবে কিনা, আর দিনটা কতদিন খোলা থাকবে, জানাবেন।

২. **পার্টস অনুযায়ী কার্ড:** কাটিংয়ে প্রতিটা পার্টসের (Front Panel, Back Panel, Sleeve…) আলাদা কার্ড হয়। সেলাইয়ে এখন প্রতিটা কার্ড আলাদা ঢোকে আর বের হয়, তাই এক গার্মেন্টের কয়েকটা পার্টস-কার্ড আউট হলে আউটপুট কয়েকবার গোনা হতে পারে। সেলাইয়ে কি পুরো সেট (সব পার্টস) একসাথে ঢোকে, নাকি শুধু একটা মূল কার্ড স্ক্যান হয়, জানাবেন।

৩. **প্রিন্ট না হওয়া কার্ড:** এখন শুধু প্রিন্ট হওয়া কার্ড সেলাইয়ে নেওয়া যায় (কাগজের কার্ড না থাকলে স্ক্যান হয় না, এই ধারণায়)। হাতে লিখে প্রিন্ট-না-হওয়া কার্ডও নেওয়া লাগবে কিনা জানাবেন।

৪. **স্টাইল না মিললে:** লাইনের প্ল্যানের স্টাইল আর বান্ডেলের স্টাইল আলাদা হলে এখন শুধু সতর্কবার্তা দেখায়, আটকায় না। আটকানো লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/sw01`):**
  - ড্যাশবোর্ডের ডেমোতে LN-001-এ Running ১ আর WIP ২২২ ছিল কিন্তু সেদিন Bdl IN ০। তাই Running আর WIP সেদিন পর্যন্ত জমা হিসাবে ধরা হয়েছে, শুধু সেদিনের না।
  - LN-002-এ বাকি সব ০ কিন্তু QC Pend ১ — QC Pend-ও জমা হিসাব।
- **"GO >> Finishing Bundle IN":** 20/02 Finishing-এর সাথে চালু হয়েছে। Finishing রিসিভ করা বান্ডেলের Sewing OUT আর তোলা যায় না, গ্রিডে অবস্থা "In Finishing"।
- **Good / Rework / Reject:** 20/03 Quality Management আসার আগে পর্যন্ত ০।
