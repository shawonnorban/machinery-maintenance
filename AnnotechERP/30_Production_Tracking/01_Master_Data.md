# Master Data

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Production Tracking](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `30/01`

**⬅ ফিরে যান:** [Production Tracking](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Production Master Data | `/m/production-tracking/production-data` | 4 | 4 | 3 |
| 2 | Line Capacity Master | `/m/production-tracking/line-capacity` | 1 | 2 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Production Master Data

`/m/production-tracking/production-data`

**ট্যাব: All**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
- সেকশন: Cutting entries, Sewing entries, Finishing entries
- বাটন/অ্যাকশন: All, Cutting, Sewing, Finishing, Add Cutting, Add Sewing, Add Finishing

**ট্যাব: Cutting**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
- সেকশন: Cutting entries
- বাটন/অ্যাকশন: All, Cutting, Sewing, Finishing, Add Cutting

**ট্যাব: Sewing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
- সেকশন: Sewing entries
- বাটন/অ্যাকশন: All, Cutting, Sewing, Finishing, Add Sewing

**ট্যাব: Finishing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date | date |  |  |
- সেকশন: Finishing entries
- বাটন/অ্যাকশন: All, Cutting, Sewing, Finishing, Add Finishing

**ডায়ালগ: Add Cutting** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date * | date | ✔ |  |
| Buyer | text |  |  |
| Style | text |  |  |
| PO | text |  |  |
| Item | text |  |  |
| Order Qty | number |  |  |
| Target (pcs) | number |  |  |
| Cut Pcs Ready | number |  |  |
| Bundles Ready | number |  |  |
| Bundles Issued to Sewing | number |  |  |
| Reject | number |  |  |
| Remarks | textarea |  |  |

**ডায়ালগ: Add Sewing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date | date |  |  |

**ডায়ালগ: Add Finishing**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date | date |  |  |

#### Line Capacity Master

`/m/production-tracking/line-capacity`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Section | select |  | All sections, Sewing, Finishing |
| Search line | search |  | Line code or name |
- গ্রিড কলাম: Line, Section, Type, Standard Capacity, Default Eff., Incl. Efficiency, Effective, Status, Updated
- সেকশন: Lines without capacity today, Capacities
- বাটন/অ্যাকশন: Add Capacity, Edit

**ডায়ালগ: Add Capacity** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Section | select |  | Sewing, Finishing |
| Line * | select | ✔ | Select line…, LN-001 — 1 · SWEING, LN-002 — 2 · SWEING, LN-003 — 3 · SWEING, LN-004 — 4 · SWEING, LN-005 — 5 · SWEING, L |
| Pieces per hour | radio |  |  |
| Pieces per day | radio |  |  |
| Standard capacity (pcs/hour) * | number | ✔ |  |
| Default efficiency (%) | number |  |  |
| Capacity already includes efficiency Then the efficiency-adjusted method is refu | checkbox |  |  |
| Effective from * | date | ✔ |  |
| Effective to | date |  |  |
| Active | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Select a line.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- লাইন: সুইং = Capacity Planning-এর `planning_lines`, ফিনিশিং = `production_lines` (line_type FINISHING); নতুন লাইন টেবিল নেই।
- প্রকৃত উৎপাদন: সুইং = Assembly Floor-এর `asm_bundle_outs`; ফিনিশিং = `asm_finishing_outs`; কাটিং = Cutting Floor-এর `cut_bundles` (Sewing Bundle IN `asm_bundle_ins` পেলে "Issued")।
- ত্রুটি: সুইং রিজেক্ট/রিওয়ার্ক = `asm_quality_inspections`, যে লাইন বান্ডেল সেলাই করেছে তার নামে (`asm_bundle_ins` দিয়ে)।
- অর্ডার/বায়ার/স্টাইল/PO: Merchandise Desk-এর `merch_orders` + `buyers`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/ProductionTracking/Database/Migrations/2027_03_29_000100_create_production_tracking.php`:

| টেবিল | ফিল্ড |
|---|---|
| `pt_settings` | company_id (একটা সারি), refresh_seconds, stale_seconds, on_track_pct, below_pct, approval_workflow, smv_method, updated_by |
| `pt_line_capacities` | section (SEWING/FINISHING), line_id, capacity_type (PER_HOUR/PER_DAY), standard_capacity, default_efficiency, includes_efficiency, effective_from, effective_to, active, remarks |
| `pt_daily_plans` | plan_no (DPP-YYYYMMDD-NNN), revision, current, plan_date, section, line_id, order_id → merch_orders, buyer_name, style, po_no, method (A–D), planned_hours, capacity (json snapshot), calculated_target, proposed_target, override_reason, approved_target, status, created_by, approved_by/at |
| `pt_daily_plan_events` | plan_id, action (CREATE/EDIT/SUBMIT/APPROVE/REJECT/CANCEL/REVISE), from_status, to_status, note, by, at |
| `pt_entries` | section (CUTTING/SEWING/FINISHING), entry_date, line_id, buyer_name, style, po_no, item, order_qty, target, production, bundles_ready, bundles_issued, reject, rework, smv, manpower, available_hours, plan_hours, run_day, total_produced, remarks |
| `pt_tv_screens` | name, section, line_ids (json), layout (AUTO/1/2/3/4/6), refresh_seconds, show_defects, active, token_hash (sha256), token_hint (শেষ ৪ অক্ষর), issued_at, last_seen_at |

## ৫. Workflow / Status ও Business Rules

- **একটা লাইনের দিনের টার্গেট:** সেই দিনের current প্ল্যান — Approval workflow চালু থাকলে শুধু APPROVED, বন্ধ থাকলে PENDING_APPROVAL-ও। প্ল্যান না থাকলে Production Master Data এন্ট্রির টার্গেট। দুটো কখনো যোগ হয় না।
- **প্রকৃত (Actual):** ERP-র লেনদেন (উপরে "বিদ্যমান কোড") + Production Master Data-র production। রিজেক্ট/রিওয়ার্ক কখনো Actual-এ যোগ হয় না।
- **ব্যান্ড:** অর্জন ≥ On track % → সবুজ; Below target %-এর নিচে → লাল; মাঝে → হলুদ (Attention); টার্গেট নেই → ধূসর "No target"। সীমা Settings-এ (ডিফল্ট ৯০ / ৭৫)।
- সময়: কারখানার ঘড়ি `Asia/Dhaka` (PtBook::TZ); সার্ভার আর ব্রাউজারে একই লেখা।
- **ক্যাপাসিটি:** একই লাইনে একই সময়ে দুটো সক্রিয় ক্যাপাসিটি থাকতে পারে না (effective_from–to ওভারল্যাপ ফেরত)। "Capacity already includes efficiency" টিক দিলে সেই লাইনে পদ্ধতি C নেওয়া হয় না।
- আজ যে লাইনের কোনো ক্যাপাসিটি নেই সেগুলো "Lines without capacity today" তালিকায় দেখায় — তাদের প্ল্যান বানানো যায় না।
- **Production Master Data:** দিনের Daily Production Report-এর কলাম — কাটিং (লাইন ছাড়া: target, cut pcs ready, bundles ready/issued, reject), সুইং ও ফিনিশিং (লাইনসহ: target, production, reject, rework, SMV, man power, available/plan hour, run day, total)। All/Cutting/Sewing/Finishing ট্যাব পেজ রিলোড ছাড়াই বদলায়।

## ৬. API ও পারমিশন

রুট `app/Modules/ProductionTracking/Routes/api.php` (প্রিফিক্স `/api/v1/production-tracking`):

| রুট | কাজ | পারমিশন |
|---|---|---|
| GET `/lookups` | লাইন, অর্ডার, বায়ার, স্টাইল, সেটিংস | productiontracking.view |
| GET `/board/{all,cutting,sewing,finishing,buyer,style,po,report}` | ড্যাশবোর্ড/রিপোর্ট ডেটা | productiontracking.view |
| GET/PUT `/settings` | সেটিংস | view / settings.manage |
| GET/POST/PATCH `/capacities` | লাইন ক্যাপাসিটি | view / capacity.manage |
| GET/POST/PATCH/DELETE `/entries` | Production Master Data | view / entry.manage |
| GET `/plans`, POST `/plans/preview`, POST/PATCH `/plans`, POST `/plans/{id}/{submit,approve,reject,cancel,revise}`, GET `/plans/{id}/history` | দৈনিক প্ল্যান | view / plan.prepare / plan.approve |
| GET/POST/PATCH/DELETE `/tv-screens`, GET `/tv-screens/{id}/preview`, POST/DELETE `/tv-screens/{id}/link` | টিভি স্ক্রিন ও লিংক | view / tv.manage |
| GET `/api/v1/public/production-tracking/tv/{token}` | টিভির ডিসপ্লে — সাইন-ইন ছাড়া, শুধু পড়া | লিংক নিজেই |

রোল (RoleSeeder): FACTORY_ADMIN সব; FACTORY_MANAGER সব (settings ছাড়া); LINE_CHIEF view + entry.manage; MAINTENANCE_MANAGER view।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `frontend/src/app/(app)/production-tracking/[screen]/` — `page.js` (স্ক্রিন বাছাই), `screens.js` (প্রতি স্ক্রিনের ডেটা), `actions.js` (`ptAction`, সব লেখা এক জায়গায়)।
- কম্পোনেন্ট `frontend/src/components/productiontracking/`: `common.jsx` (PeriodBar, LiveBar, Kpi, LineCard, Legend, Table, CSV, `clock`/`stamp`), `boards.jsx`, `plans.jsx`, `capacity.jsx`, `entries.jsx`, `tv.jsx`, `tv-display.jsx`, `settings.jsx`।
- টিভি পেজ `frontend/src/app/tv/[token]/page.js` — `src/proxy.js`-এ `/tv/` পাবলিক।
- লেবেল `lang/{en,bn}/productiontracking.php`; মেনু MenuRegistry মডিউল `PRODUCTION_TRACKING` (PRODUCTION সেকশন), ৭ ফোল্ডার, ১৩ স্ক্রিন।
- Line Capacity Master: সেকশন/লাইন ফিল্টার, "Lines without capacity today" চিপ, Add/Edit মোডাল (সেকশন, লাইন, ধরন রেডিও, স্ট্যান্ডার্ড, দক্ষতা, includes-efficiency, কার্যকর তারিখ)।
- Production Master Data: তারিখ, ট্যাব, তিন সেকশনের KPI, প্রতি সেকশনের তালিকা + Edit/Delete, Add মোডাল।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/ProductionTracking/Database/Migrations/2027_03_29_000100_create_production_tracking.php`
- `app/Modules/ProductionTracking/Http/Controllers/Api/DailyPlanApiController.php`
- `app/Modules/ProductionTracking/Http/Controllers/Api/ProductionTrackingApiController.php`
- `app/Modules/ProductionTracking/Http/Controllers/Api/TvScreenApiController.php`
- `app/Modules/ProductionTracking/Models/PtDailyPlan.php`
- `app/Modules/ProductionTracking/Models/PtDailyPlanEvent.php`
- `app/Modules/ProductionTracking/Models/PtEntry.php`
- `app/Modules/ProductionTracking/Models/PtLineCapacity.php`
- `app/Modules/ProductionTracking/Models/PtSetting.php`
- `app/Modules/ProductionTracking/Models/PtTvScreen.php`
- `app/Modules/ProductionTracking/Routes/api.php`
- `app/Modules/ProductionTracking/Support/ProductionBoard.php`
- `app/Modules/ProductionTracking/Support/PtBook.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `lang/en/productiontracking.php`
- `lang/bn/productiontracking.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Frontend**
- `frontend/src/components/productiontracking/boards.jsx`
- `frontend/src/components/productiontracking/capacity.jsx`
- `frontend/src/components/productiontracking/common.jsx`
- `frontend/src/components/productiontracking/entries.jsx`
- `frontend/src/components/productiontracking/plans.jsx`
- `frontend/src/components/productiontracking/settings.jsx`
- `frontend/src/components/productiontracking/tv-display.jsx`
- `frontend/src/components/productiontracking/tv.jsx`
- `frontend/src/app/(app)/production-tracking/[screen]/page.js`
- `frontend/src/app/(app)/production-tracking/[screen]/screens.js`
- `frontend/src/app/(app)/production-tracking/[screen]/actions.js`
- `frontend/src/app/tv/[token]/page.js`
- `frontend/src/proxy.js`

**Tests**
- `tests/Feature/ProductionTracking/ProductionTrackingTest.php`
- `tests/Feature/Identity/MenuAccessTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

সব ফাইল উপরের তালিকায়; মডিউল `app/Modules/ProductionTracking`।

## ৯. টেস্ট ও যাচাই

`tests/Feature/ProductionTracking/ProductionTrackingTest.php`:
- ক্যাপাসিটি থেকে টার্গেট (C: ১২০ × ৮ × ৮০% = ৭৬৮), কারণ ছাড়া override ফেরত, নিজের প্ল্যান নিজে অনুমোদন নিষেধ, অনুমোদনের পরই ড্যাশবোর্ডে টার্গেট, ইতিহাস।
- রিজেক্টে কারণ বাধ্যতামূলক, সংশোধনে নতুন Rev. ২ খসড়া আর পুরোনোটা current নয়।
- ক্যাপাসিটি ছাড়া প্ল্যান ফেরত, একই লাইনে ওভারল্যাপ ক্যাপাসিটি ফেরত, দক্ষতা-ধরা ক্যাপাসিটিতে পদ্ধতি C ফেরত।
- Production Master Data-র টোটাল টার্গেট একবারই গোনা (প্ল্যান থাকলে প্ল্যান), কাটিং KPI, ভিউয়ার লিখতে পারে না।
- টিভি লিংক সাইন-ইন ছাড়া খোলে, Preview লিংক বদলায় না, Revoke-এর পর 404।
- `tests/Feature/Identity/MenuAccessTest.php`: কোনো স্ক্রিনের কোড ফোল্ডারের কোডের সাথে মিলবে না।

ব্রাউজার যাচাই (ui.mjs, dev DB-তে রেখে দেওয়া): তিনটা ক্যাপাসিটি (LINE01 ১২০/ঘণ্টা ৮০%, LINE02 ১০০০/দিন, Finishing Line1 ১৫০/ঘণ্টা ৯০%); DPP-20261115-001 (C, ৭৬৮ → ৭৬০ অনুমোদিত) আর -002 (B, রিজেক্ট → ৬ ঘণ্টায় এডিট → অনুমোদিত); কাটিং/সুইং/ফিনিশিং এন্ট্রি; টিভি "Sewing Floor 1 — TV A" ও "TV B" (লিংকসহ)।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১১-১৫):** Brickland-এর ১৩টা স্ক্রিন — ব্যবহারকারীর অনুরোধ "PRODUCTION এর Production Tracking বাকি আছে"।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - Reports আর Settings স্ক্রিনের মেনু কোড ফোল্ডারের কোডের সাথে এক ছিল → sync-এ ফোল্ডার আর স্ক্রিন একে অপরকে মুছে দিচ্ছিল, "NN of 11" দেখাচ্ছিল। কোড `…_SCREEN` করা হয়েছে, আর একটা টেস্ট সব মডিউলে এটা আটকায়।
  - সময়গুলো সার্ভারে (UTC) আর ব্রাউজারে আলাদা লেখা হচ্ছিল → hydration error। এখন সব সময় `Asia/Dhaka`-তে।
  - টিভির Preview বাটন নতুন লিংক ইস্যু করছিল, ফলে চালু টিভির লিংক বাতিল হয়ে যাচ্ছিল। এখন Preview আলাদা `/preview` endpoint দিয়ে ERP-র ভেতরেই দেখায়, লিংক ছোঁয় না।
  - Production Master Data-র সুইং টার্গেট প্ল্যান + এন্ট্রি দুটোই যোগ করছিল (৩,২৭০)। এখন ড্যাশবোর্ডের নিয়মেই (প্ল্যান, না থাকলে এন্ট্রি)।
  - All Dashboard-এর ট্রেন্ডে এন্ট্রির উৎপাদন আসছিল না। এখন যে ঘণ্টায় রেকর্ড হয়েছে সেই ঘণ্টায় গোনা হয়।
  - প্ল্যান টেবিল খুব চওড়া ছিল → Edit/Cancel/History শুধু আইকন (aria-label সহ)।
