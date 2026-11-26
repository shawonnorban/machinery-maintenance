# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Cutting Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `19/04`

**⬅ ফিরে যান:** [Cutting Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Bundle Report | `/m/cutting/bundle-report` | 1 | 9 | 0 |
| 2 | Additional Cutting List | `/m/cutting/additional-cutting-list` | 1 | 10 | 0 |
| 3 | PO-wise Bundle Report | `/m/cutting/po-wise-bundle-report` | 1 | 9 | 0 |
| 4 | Style-wise Bundle Report | `/m/cutting/style-wise-bundle-report` | 1 | 9 | 0 |
| 5 | Size-wise Bundle Report | `/m/cutting/size-wise-bundle-report` | 1 | 9 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Bundle Report

`/m/cutting/bundle-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| PO | text |  |  |
| Style | text |  |  |
| Buyer | text |  |  |
| Color | text |  |  |
| Size | text |  |  |
| Serial No | text |  |  |
| Status All Created Printed Reprinted Cancelled | select |  | All, Created, Printed, Reprinted, Cancelled |
- গ্রিড কলাম: SL, Bundle No., PO, Style, Buyer, Color, Size, Serial From, Serial To, Qty, Status, Print Date
- সেকশন: 1 Filter, 2 Bundle Report
- বাটন/অ্যাকশন: Show

#### Additional Cutting List

`/m/cutting/additional-cutting-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| PO No | text |  |  |
| Style | text |  |  |
| Color | text |  |  |
| Cutting Number | text |  |  |
| Bundle Number | text |  |  |
| Size | text |  |  |
| Batch Number | text |  |  |
| Cutting Status Additional + Partial Additional Partial Additional | select |  | Additional + Partial, Additional, Partial Additional |
- গ্রিড কলাম: SL, PO No, Style, Buyer, Color, Buyer Order Qty, Total Cutting Qty, Additional Qty, Additional %
- গ্রিড কলাম: SL, Date, PO No, Style, Color, Cutting No, Bundle No, Size, Parts Name, Batch No, Qty, Cutting Status, Additional Qty, Buyer Order Qty, Total Cutting Qty, Additional %
- সেকশন: 1 Filter, 2 Additional Cutting by PO / Style / Color, 3 Additional Cutting Bundles
- বাটন/অ্যাকশন: Show

#### PO-wise Bundle Report

`/m/cutting/po-wise-bundle-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| PO | text |  |  |
| Style | text |  |  |
| Buyer | text |  |  |
| Color | text |  |  |
| Size | text |  |  |
| Serial No | text |  |  |
| Status All but Cancelled Created Printed Reprinted Cancelled | select |  | All but Cancelled, Created, Printed, Reprinted, Cancelled |
- গ্রিড কলাম: PO, Style, Total Bundle, Total Qty
- সেকশন: 1 Filter, 2 PO-wise Bundle Report
- বাটন/অ্যাকশন: Show

#### Style-wise Bundle Report

`/m/cutting/style-wise-bundle-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| PO | text |  |  |
| Style | text |  |  |
| Buyer | text |  |  |
| Color | text |  |  |
| Size | text |  |  |
| Serial No | text |  |  |
| Status All but Cancelled Created Printed Reprinted Cancelled | select |  | All but Cancelled, Created, Printed, Reprinted, Cancelled |
- গ্রিড কলাম: Style, Total Bundle, Total Qty
- সেকশন: 1 Filter, 2 Style-wise Bundle Report
- বাটন/অ্যাকশন: Show

#### Size-wise Bundle Report

`/m/cutting/size-wise-bundle-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| PO | text |  |  |
| Style | text |  |  |
| Buyer | text |  |  |
| Color | text |  |  |
| Size | text |  |  |
| Serial No | text |  |  |
| Status All but Cancelled Created Printed Reprinted Cancelled | select |  | All but Cancelled, Created, Printed, Reprinted, Cancelled |
- গ্রিড কলাম: Size, Total Bundle, Total Qty
- সেকশন: 1 Filter, 2 Size-wise Bundle Report
- বাটন/অ্যাকশন: Show
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **19/01–19/03-এর `cut_bundles`:** চারটা রিপোর্টই সেই বান্ডেল কার্ড থেকে পড়ে। কোনো নতুন টেবিল নেই।
- **গ্রিড:** `RecordsGrid` (Search, Export, পেজিং)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন কোনো টেবিল নেই। রিপোর্ট Show চাপার মুহূর্তে `cut_bundles` থেকে পড়ে।

## ৫. Workflow / Status ও Business Rules

- **ফিল্টার (চারটাতেই এক):** From/To Date, PO, Style, Buyer, Color, Size, Serial No, Status।
  - PO, স্টাইল, বায়ার আর রং লেখার অংশ মিললেই ধরে। সাইজ পুরোটা মিলতে হয়।
  - Serial No দিলে যে কার্ডের সিরিয়ালের মধ্যে সেটা পড়ে, সেই কার্ড আসে।
  - To Date From Date-এর আগে হলে আটকায়।
- **Bundle Report:** প্রতিটা কার্ড (ক্রম, বান্ডেল নং, PO, স্টাইল, বায়ার, রং, সাইজ, সিরিয়াল থেকে/পর্যন্ত, পরিমাণ, অবস্থা, প্রিন্টের তারিখ)। অবস্থার ডিফল্ট All (বাতিলসহ)।
- **PO-wise / Style-wise / Size-wise:** PO+স্টাইল, স্টাইল বা সাইজ অনুযায়ী Total Bundle আর Total Qty। অবস্থার ডিফল্ট All but Cancelled।
  - সাইজ সাজানো হয় পোশাকের ক্রমে: XXS, XS, S, M, L, XL, XXL…, তারপর সংখ্যার সাইজ ছোট থেকে বড়।
- **নিচে মোট:** "Total Bundle: n · Total Qty: n Pcs"।
- **Additional Cutting List (২০২৬-১১-১৬ যোগ):** বায়ারের অর্ডারের বেশি কাটা কার্ড।
  - **কোন কার্ড:** বান্ডেল তৈরির সময়েই ঠিক হয় (`cutting_status`)। PO-র রং আর পার্টসে আগে যত কাটা হয়েছে, এই কার্ডসহ তা অর্ডার ছাড়ালে Additional। অর্ডার কার্ডের ভেতরে শেষ হলে Partial Additional, তখন অতিরিক্ত = যতটা ছাড়াল। বাতিল কার্ড কখনো গোনা হয় না।
  - **ফিল্টার:** From/To Date (শুরুতে গত ৩০ দিন), PO, Style, Color, Cutting Number, Bundle Number, Size, Batch Number, Cutting Status (Additional + Partial / Additional / Partial Additional)। Show আর Print।
  - **২ PO / Style / Color অনুযায়ী:** Buyer Order Qty (রঙের সাইজ-ভাগ, না থাকলে PO-র মোট), Total Cutting Qty (সব সময়ের, যে পার্টস সবচেয়ে বেশি কাটা সেটাই), Additional Qty, Additional % (অর্ডার ০ হলে N/A)। Cutting List-ও একই নিয়মে (`CutTotals`)।
  - **৩ বান্ডেল:** তারিখ, PO, স্টাইল, রং, কাটিং নং, বান্ডেল নং, সাইজ, পার্টস, ব্যাচ, পরিমাণ, অবস্থা, কার্ডের অতিরিক্ত, অর্ডার, মোট কাটা, %। নিচে "Bundles: n · Additional Cutting: n Pcs" (কার্ডগুলোর অতিরিক্তের যোগফল, Brickland-এর মতো)।
  - **Print:** Brickland-এর মতো আলাদা জানালায় landscape টেবিল: শিরোনাম, "from to to · n bundles · Additional n Pcs", বান্ডেলের কলাম আর Total Additional।

## ৬. API ও পারমিশন

- **পারমিশন:** `cutting.report.view`। FACTORY_ADMIN, FACTORY_MANAGER, LINE_CHIEF আর STORE_MANAGER পায়।
- **API (`CutReportApiController`):** `GET /cutting/reports/{bundle|po|style|size}?from=&to=&po=&style=&buyer=&colour=&size=&serial=&status=`
- `GET /cutting/additional-cutting?from=&to=&po=&style=&colour=&cutting_no=&bundle_no=&size=&batch_no=&status=` (Additional Cutting List)।
- **মেনু:** Cutting Floor-এর ফোল্ডার REPORTS, ৫টা স্ক্রিন — Brickland-এর ক্রমে Bundle Report-এর পরে Additional Cutting List (`CUTTING.RPT_ADDITIONAL`)।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `cutting-floor/[screen]/screens.js` (`report(kind, slug)`), কম্পোনেন্ট `components/cutting/reports.jsx` (`CutReport`)।
- Additional Cutting List: `additionalCutting` স্ক্রিন, `AdditionalCuttingList` কম্পোনেন্ট (৩ অংশ, Print `lib/print-document` দিয়ে)।
- **গঠন:** Brickland-এর মতো "১ Filter" (বক্সগুলো আর Show) আর "২ <রিপোর্টের নাম>" (গ্রিড, Search, Export, নিচে মোট)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend**
- `app/Modules/Cutting/Http/Controllers/Api/CutReportApiController.php` (`additional()` যোগ)
- `app/Modules/Cutting/Support/CutTotals.php` (নতুন: অর্ডার আর মোট কাটার এক নিয়ম; `CutBundleApiController::cuttingRows` এটাই ব্যবহার করে)
- `app/Modules/Cutting/Routes/api.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/cutting.php`, `lang/bn/cutting.php` (বদলানো: `rpt` গ্রুপ আর রিপোর্টের শিরোনাম)

**Frontend**
- `frontend/src/components/cutting/reports.jsx`
- `frontend/src/app/(app)/cutting-floor/[screen]/screens.js`, `actions.js` (বদলানো: `cutReport`, `additionalCutting`)

**Test**
- `tests/Feature/Cutting/CuttingReportTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Cutting/CuttingReportTest.php`):** test2 DB-তে পাস, ২টা টেস্ট, ২৬টা assertion।
- `test_the_bundle_reports_count_the_cards`: দুটো PO-তে ৫টা কার্ড, তার একটা বাতিল।
  - Bundle Report: ৫টা কার্ড / ১৪১ পিস (বাতিলসহ)। শুধু বাতিল দিলে ১টা।
  - বায়ার "rol" দিলে ROLY-র ২টা। সিরিয়াল ৩৩ দিলে ২টা। সাইজ s আর PO 1156 দিলে ৩৬ পিস।
  - PO-wise: 11565705 (৩টা / ১০৬), 4500085753 (১টা / ২৫), মোট ৪ / ১৩১ (বাতিল বাদ)।
  - Style-wise ২টা সারি। Size-wise S (২ / ৬১) → M (১ / ৪০) → XL (১ / ৩০)। শুধু বাতিল দিলে M / ১০।
  - উল্টো তারিখ আর অজানা রিপোর্ট আটকায়।
- `test_who_may_read_the_bundle_reports`: STORE_MANAGER পড়তে পারে। TECHNICIAN পায় না।

**Additional Cutting List (২০২৬-১১-১৬):** `test_the_additional_cutting_list_shows_the_cards_cut_past_the_order` — ৩০০-র অর্ডারে ২০০+৯০ নিয়মিত, XL ১–৩০ Partial (২০), XL ৩১–৫০ Additional (২০); ৩১০-এর এক কার্ড Partial (১০); বাতিল কার্ড বাদ; অর্ডার ০-এর PO-তে সব অতিরিক্ত আর % null। মোট ৪ কার্ড / ৬৫ পিস; Status, Bundle No, Cutting No + Size, Batch + Color, বাইরের তারিখ আর REGULAR ফিল্টার; Cutting List-এ একই ৩০০ / ৩৪০ / ৪০ / ১৩.৩৩%। পারমিশন টেস্টে STORE_MANAGER পড়ে, TECHNICIAN পায় না। `tests/Feature/Cutting` (৭টা) আর `MenuAccessTest` (৭টা) পাস।

**UI চেক (`app_check/plans/acl.json`, dev-এ রেখে দেওয়া):** New Cutting দিয়ে PO-1001 / FLOW STYLE-01 / White / M / Front Panel / B-101-এ চারটা কার্ড — BND-20261116-0001 (১–৫০০) আর 0002 (৫০১–৯৮০) Regular, 0003 (৯৮১–১০২০) Partial ২০, 0004 (১০২১–১০৫০) Additional ৩০। রিপোর্টে PO-1001 ১০০০ / ১০৫০ / ৫০ / ৫.০০%, নিচে "Bundles: 2 · Additional Cutting: 50 Pcs"; Partial ফিল্টারে শুধু 0003 / ২০ পিস। Print-এর প্রিভিউ Brickland-এর কলামের সাথে মেলে। error ০।

**Regression:** `CuttingBundleTest` (৪টা, ৮০টা), `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/cutr01`), dev অ্যাপে:**
- Bundle Report: BND-20261106-0002 আর 0001, দুটোই Printed, প্রিন্টের সময়সহ। নিচে "Total Bundle: 2 · Total Qty: 72 Pcs"।
- PO-wise: PO-SS27-01 / SS27-TEE01 / ২ / ৭২। Style-wise: SS27-TEE01 / ২ / ৭২। Size-wise: S / ২ / ৭২।
- Status=Printed দিয়ে Show: দুটো কার্ড।
- **error:** ০।

**প্রিন্ট:** Brickland-এর এই স্ক্রিনগুলোতে Print বাটন নেই। গ্রিডের Export দিয়ে ফাইল নেওয়া যায়।

## ১০. খোলা প্রশ্ন

১. **রং ও পার্টস অনুযায়ী রিপোর্ট:** এখন PO, স্টাইল আর সাইজ অনুযায়ী গোনা হয়। রং বা পার্টস অনুযায়ী (একই গার্মেন্টের কয়েকটা পার্টস) আলাদা রিপোর্ট লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/cr01`):**
  - ডেমোতে আসল ডাটা ছিল: Bundle Report-এ "Total Bundle: 2000 · Total Qty: 52,707 Pcs"। Size-wise-এ XXS, XS, S, M, L, XL, তারপর ১৩০, ১৪০, ১৫০ — এই ক্রমই রাখা হয়েছে।
  - PO-wise-এ একই PO নম্বরের আলাদা সারি থাকে, যেমন "(BOTTOM)" অংশ।
- **19 Cutting Floor শেষ:** ৪টা সাব-মডিউলের সবগুলোই সম্পন্ন।
- **পঞ্চম রিপোর্ট পাওয়া গেছে (২০২৬-১১-১৬):** ব্যবহারকারী জানালেন Additional Cutting List বাকি। সাইডবারের REPORTS ফোল্ডার খুলে (`sidelinks`-এ বন্ধ ফোল্ডারের লিংক আসে না) `/m/cutting/additional-cutting-list` পাওয়া গেল। `out/menu.json`-এ যোগ করে ক্রল করা হয়েছে; Brickland-এর Print পপআপও ধরে দেখা হয়েছে। আগের খোলা প্রশ্ন ১ এতে মিটে গেছে।
