# Report

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩৭টা রিপোর্টের ৩৪টা ভরা, ৩টা নির্ভরশীল মডিউলের অপেক্ষায়)
> **মডিউল:** [Finishing Line](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `18/06`

**⬅ ফিরে যান:** [Finishing Line](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Fabric Finishing Reports | `/m/dyeing-finishing/dyeing-finishing-report` | 1 | 43 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Fabric Finishing Reports

`/m/dyeing-finishing/dyeing-finishing-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Dyeing Finisging S/C Program Delivery | radio |  |  |
| Daily Production Report of Dyeing Finishing | radio |  |  |
| Machine & Shift Wise Details Production | radio |  |  |
| DF M/C Wise Production Against Target | radio |  |  |
| Dyeing Finishing Process Running (Floor Status) | radio |  |  |
| Party wise S/C Dyeing & Dyeing Finishing Production Summary | radio |  |  |
| S/C Program Delivery & Receive Ledger | radio |  |  |
| S/C Process Bill Register | radio |  |  |
| S/C Bill Outstanding by Party | radio |  |  |
| Fabric Hold List | radio |  |  |
| Fabric Hold List Summary | radio |  |  |
| Four-Point Inspection Register | radio |  |  |
| Buyer Fabric Shade Approval Status | radio |  |  |
| Finished Fabrics Delivery to Store | radio |  |  |
| Waiting for Delivery/Fabric Quality Report | radio |  |  |
| Finish Fabric Issue Register | radio |  |  |
| Monthly Production Report of Dyeing-Finishing | radio |  |  |
| M/C Wise Finishing Re-Process Qty with Reason | radio |  |  |
| Finish Fabric Current Stock | radio |  |  |
| Finish Fabric Stock Details Ledger | radio |  |  |
| Dyeing Finishing Process Type Register | radio |  |  |
| Machine & Process Mapping Register | radio |  |  |
| Party wise S/C Dyeing & Dyeing Finishing Production Register | radio |  |  |
| Wet & Hold Locked Fabric Summary | radio |  |  |
| Finishing Production Report 4A | radio |  |  |
| Order Monitoring Report | radio |  |  |
| S/C AOP Ledger | radio |  |  |
| Finish Fabric Stock Ledger(Sample) | radio |  |  |
| Waiting for Delivery/Fabric Quality (Sample) | radio |  |  |
| Finished Fabrics Delivery to Store (Sample) | radio |  |  |
| Day-Night Wise Production for Incentive | radio |  |  |
| Shift Wise Production for Incentive | radio |  |  |
| Dyeing - Finishing Floor 24hr Wet & Only Dry Hold Fabric | radio |  |  |
| Finish Fabric Left-Over Stock | radio |  |  |
| Utilization of Left-Over Finish Fabric – Transfer and Re-Use | radio |  |  |
| Fiber Composition and Color Group Wise Fabric Booking | radio |  |  |
| Daily S/C AOP Batch Report | radio |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store (ST-CHM), Cutting Sub-Store (ST-CUT), Dyeing Floor (ST-DYE), Finished Fabric Store (ST-FAB), Finished Goo |
| S/C Party | select |  | ACCESSORIES VIEW, Arafah Knit Composite Ltd, BCL, BCL COMPOSITE, CREATIVE TOUCH, KOYO TRIMS SOLUTION JP, NASIR INTERNATI |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **রিপোর্ট কার্ড:** Knit Production-এর `KnitReportSet` (`components/knitting/reports.jsx`)। এর `ns`/`prefix` প্রপ দিয়ে Finishing নিজের শব্দ (`finishing.rpt.*`) ব্যবহার করে।
- **উত্তরের গঠন:** Knit Production-এর রিপোর্টের মতোই — `columns` ("key:type"), `rows`, `stats` (প্রথমে Rows), আর `pending_module`।
- **ডাটা:** 18/01–18/05-এর সব টেবিল: প্রোডাকশন শিট, কার্ড আর তার ট্রেইল, S/C চালান/রিসিপ্ট/বিল, শেড/ইন্সপেকশন/পরামর্শ, স্টোরের লট/move/রিকুইজিশন/রিটার্ন।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন কোনো টেবিল নেই। রিপোর্ট Preview চাপার মুহূর্তে বিদ্যমান টেবিল থেকে পড়ে, কিছুই লেখে না।

## ৫. Workflow / Status ও Business Rules

- **বক্স:** Company (শুধু দেখানো), Store, S/C Party, From Date, To Date, আর Export to Excel? (সব সারি এক পাতায়)। To Date From Date-এর আগে হলে আটকায়। ফাঁকা বক্স মানে সব রেকর্ড।
- **খোলার সময়:** প্রথম রিপোর্ট সব রেকর্ড নিয়ে পড়ে দেখায়।
- **প্রোডাকশন:** শুধু পোস্ট করা শিট গোনা হয়।
  - Daily Production, Machine & Shift, Monthly।
  - Production vs Target: মেশিনের টার্গেট × শিফট সংখ্যা বনাম আউটপুট, অর্জন %।
  - 4A: ব্যাচ অনুযায়ী প্রসেস গ্রুপের আউটপুট (Wet/Dry/Compacting/Surface)।
  - Day-Night: আনলোড ০৬–১৮টা হলে দিন, বাকি রাত। Shift Incentive।
  - M/C Re-Process with Reason: QC পরামর্শ আর ডাই হাউসে ফেরত দুটো মিলিয়ে।
- **ফ্লোর:**
  - Floor Status: ডেলিভারি হয়নি এমন সব কার্ড, শেষ লোড থেকে কত ঘণ্টা।
  - Hold List / Summary।
  - Wet & Hold Summary।
  - 24hr Wet & Dry Hold: ২৪ ঘণ্টার বেশি একই ওয়েট বা হোল্ড স্টেজে থাকা।
  - Order Monitoring: অর্ডার অনুযায়ী রিসিভ, উৎপাদন, পাস, স্টোরে, ইস্যু।
- **S/C:** Program Delivery (পোস্ট করা চালানের লাইন, মূল্যসহ), Delivery & Receive Ledger, Party Summary, Party Register (পোস্ট করা রিসিপ্ট), Bill Register, Bill Outstanding (বিল হয়েছে − সম্পন্ন)।
- **QC:** Four-Point Register, Shade Approval Status, Waiting for Delivery (Pass হওয়া, Ready for Delivery কার্ড; বাল্ক আর স্যাম্পল আলাদা)।
- **স্টোর:**
  - Delivery to Store (খসড়া বাদ, ইল্ড %; বাল্ক আর স্যাম্পল আলাদা)।
  - Issue Register (Partial/Complete রিকুইজিশন)।
  - Current Stock, Stock Details Ledger (চলতি ব্যালান্সসহ), Stock Ledger (Sample)।
  - Left-Over: কাটিং থেকে নেওয়ার পরে যে লটে কিছু রয়ে গেছে আর কোনো খোলা রিকুইজিশন নেই।
  - Left-Over Utilization: রিটার্ন আর ট্রান্সফার।
- **মাস্টার:** Process Type Register, Machine & Process Mapping Register।
- **অপেক্ষায় থাকা রিপোর্ট:** S/C AOP Ledger আর Daily S/C AOP Batch (AOP মডিউল), Fiber Composition & Color Group Booking (ফেব্রিক বুকিংয়ের ফাইবার/কালার গ্রুপ)। এগুলো ফাঁকা গ্রিড দেখায়, সাথে কোন মডিউলের অপেক্ষা তা লেখা থাকে।

## ৬. API ও পারমিশন

- **পারমিশন:** `finishing.report.view`। FACTORY_ADMIN, FACTORY_MANAGER, LINE_CHIEF আর STORE_MANAGER পায়।
- **API (`FinReportApiController`):**
  - `GET /finishing/reports/options`
  - `GET /finishing/reports/{report}?store=&party=&from=&to=`
- **মেনু:** Finishing Line-এর নতুন ফোল্ডার REPORT, একটা স্ক্রিন (Fabric Finishing Reports)।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `finishing-line/[screen]`-এ, `rpt-screens.js` থেকে। `KnitReportSet`-এ `ns="finishing" prefix="rpt"`।
- **গঠন:** Brickland-এর মতো Selection of Parameters (৩৭টা রিপোর্টের রেডিও তালিকা, বক্সগুলো, Export to Excel?), Preview আর Reset। নিচে স্ট্যাট টাইল আর রিপোর্টের গ্রিড (Search, Export)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (একটা স্ক্রিন, ৩৭টা রিপোর্ট):

**Backend**
- `app/Modules/Finishing/Support/FinReports.php`
- `app/Modules/Finishing/Http/Controllers/Api/FinReportApiController.php`
- `app/Modules/Finishing/Routes/api.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/finishing.php`, `lang/bn/finishing.php` (বদলানো: `rpt` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/finishing-line/[screen]/rpt-screens.js` (নতুন), `page.js` আর `actions.js` (বদলানো: `finReportAction`)

**Test**
- `tests/Feature/Finishing/FinishingReportTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Finishing/FinishingReportTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট, ১৭২টা assertion।
- `test_every_report_reads_the_lines_records`:
  - একটা ব্যাচ পুরো লাইন দিয়ে চালানো হয়েছে: প্রোডাকশন, S/C, শেড, ইন্সপেকশন, স্টোর, কাটিং।
  - ৩৭টা রিপোর্টই কলাম আর Rows টাইল দেয়।
  - মিলিয়ে দেখা সংখ্যা:
    - Daily: ২টা সারি, ১৯৮ → ১৯৩, Compacting লস ৩.০৬%।
    - Target: ১ শিফট, টার্গেট ১২০, আউটপুট ৯৮, অর্জন ৮১.৬৭%।
    - S/C ব্যালান্স ০, রিসিভ ৫৯.৫। বকেয়া বিল ৪৭৬।
    - Four-Point Pass, ৮ পয়েন্ট। শেড Approved।
    - স্টোরে ইল্ড ৯৫.৫%। স্টক ৬৫.৫। লেজার RECEIPT ৯৫.৫ → ISSUE ৬৫.৫। ইস্যু ৩০।
    - Order Monitoring: স্টোরে ৯৫.৫, ইস্যু ৩০। Left-Over ৬৫.৫। ৮টা প্রসেস টাইপ।
  - AOP-এ pending_module দেখায়। তারিখ দিয়ে খোঁজা যায়, উল্টো তারিখ আর অজানা রিপোর্ট আটকায়।
- `test_the_floor_reports_read_where_each_batch_stands`: On Hold ব্যাচ Hold List, Summary, 24hr Dry Hold (৩০ ঘণ্টা) আর Floor Status-এ দেখায়।
- `test_who_may_read_the_reports`: LINE_CHIEF পড়তে পারে। TECHNICIAN পায় না।

**Regression:** `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/frpt01`), dev অ্যাপে (Delta Apparels):**
- খোলার সময় প্রথম রিপোর্ট (S/C Program Delivery): FSC-CH-2026110001, Calendering, ৯৯.২ kg × ৬ = ৫৯৫.২০। টাইল: Rows ১, Qty ৯৯.২, Value ৫৯৫.২০।
- Daily Production: DFP-2026110001-এর দুটো সারি (Stentering ৯৯.২ → ৯৭.৯, ১.৩১%; Compacting ৯৮.৪ → ৯৬.৯, ১.৫২%)।
  - সংশোধনের (FPA) পরের সংখ্যাই দেখায়।
  - লাইন কলামে প্রথমে "OPEN" কাঁচা লেখা আসছিল। শব্দে দেখানো হয় এমন ধরন করা হয়েছে।
- Floor Status: ফাঁকা। dev-এ এখন কোনো ব্যাচ ফ্লোরে নেই (একটা স্টোরে, একটা ফেরত), তাই এটা ঠিক।
- Current Stock: FSL-2026110001, রিসিভ ৯৭.৯, ব্যালান্স ৬২.৯, ৪ রোল।
- Order Monitoring: LPP-2026-000001: রিসিভ ৯৯.২, উৎপাদন ১৯৪.৮, পাস ৯৯.২, স্টোরে ৯৭.৯, ইস্যু ৪০।
- S/C AOP Ledger: "AOP is not built yet — this report fills once its screens exist."
- Four-Point Register: FFI-2026110001, ২টা রোল, ৩৩.৭ কেজি, ১৭ পয়েন্ট, Pass, Posted।
- **error:** ০।

**প্রিন্ট:** Brickland-এর রিপোর্ট স্ক্রিনে Preview আর Export to Excel আছে, আলাদা Print বাটন নেই। আমাদের গ্রিডের Export দিয়ে Excel পাওয়া যায়।

## ১০. খোলা প্রশ্ন

১. **রিপোর্টের কলাম:** Brickland-এর ডেমোতে কোনো রিপোর্টে সারি ছিল না, তাই কলাম আর হিসাব আমরা নিজেদের ডাটা থেকে বানিয়েছি। বিশেষ করে "Finishing Production Report 4A", "Order Monitoring", "Day-Night/Shift Wise Production for Incentive"-এর ধরন বা ফর্মুলা আলাদা হলে জানাবেন।

২. **বক্স চালু/বন্ধ:** Brickland প্রতিটা রিপোর্টে শুধু দরকারি বক্স চালু রাখে (যেমন Process Type Register-এ কোনো বক্স নেই)। আমাদের কার্ডে সব বক্স সবসময় খোলা থাকে, যে রিপোর্টের যা দরকার নেই তা উপেক্ষা হয়। প্রতিটা রিপোর্টে বক্স বন্ধ রাখতে হবে কিনা জানাবেন।

৩. **লেফট-ওভারের সংজ্ঞা:** লেফট-ওভার ধরা হয়েছে সেই লট, যা থেকে কাটিং নিয়েছে, যাতে আর খোলা রিকুইজিশন নেই আর কিছু রয়ে গেছে। অর্ডার শিপ হওয়া বা বন্ধ হওয়ার সাথে যুক্ত করতে হবে কিনা জানাবেন।

৪. **দিন-রাত:** আনলোডের সময় ০৬:০০–১৭:৫৯ দিন, বাকিটা রাত ধরা হয়েছে। কারখানার নিজের সময় আলাদা হলে জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/rprobe.mjs` → `out/frpt.json`):**
  - ৩৭টা রিপোর্টের নাম আর ক্রম মিলিয়ে দেখা হয়েছে। প্রতিটা রিপোর্টে কোন বক্স চালু থাকে, তাও রেকর্ড হয়েছে।
  - ডেমোতে কোনো সারি নেই, তাই কলাম পাওয়া যায়নি। যে রিপোর্টে তারিখের বক্স বন্ধ, সেখানে প্রোব অপেক্ষা করে থেমে গেছে।
- **একটাই শেয়ার করা কার্ড:** Knit Production, Dye House আর Finishing একই `KnitReportSet` ব্যবহার করে, প্রত্যেকে নিজের শব্দ নিয়ে।
- **18 Finishing Line শেষ:** ৬টা সাব-মডিউলের সবগুলোই সম্পন্ন।
