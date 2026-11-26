# Operation

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/01`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sales & Shipment Settings | `/m/sales-delivery/sd-settings` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sales & Shipment Settings

`/m/sales-delivery/sd-settings`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search The Sales & Shipment settings: shipping rules, alert windows, document nu | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: RULES, ALERT DAYS, PREFIXES, Number Width, Updated By, Updated At, ACTION, Ship Against Delivered, Require Export Docs, Due, Certificate, Order Amendment, Challan Return, S/C Return, S/C Reconciliation, Shipment, Shipment Booking, Shipment Invoice, Packing List, Document Handover
- বাটন/অ্যাকশন: Export, Number Width, Updated By, Updated At, Ship Against Delivered, Require Export Docs, Due, Certificate, Order Amendment, Challan Return, S/C Return, S/C Reconciliation, Shipment, Shipment Booking, Shipment Invoice, Packing List, Document Handover, Edit
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **নতুন মডিউল `app/Modules/SalesShipment`:** Sales & Shipment-এর জন্য আলাদা মডিউল ফোল্ডার, নিজের টেবিল (`ss_*`), lang (`salesshipment.php`) আর টেস্ট ফোল্ডার। পুরোনো মডিউলের কিছু নেওয়া হয়নি।
- **20/07 Assembly Floor-এর Shipment (`asm_shipments`):** ফ্লোরের Ready থেকে শিপমেন্ট। 21/05-এ Sales & Shipment-এর শিপমেন্ট বানানোর সময় এর সাথে সম্পর্ক ঠিক করা হবে (খোলা প্রশ্ন ২)।
- **`SsSettings::nextNo()`:** পরের সাব-মডিউলগুলো (অর্ডার সংশোধন, চালান রিটার্ন, S/C রিটার্ন ও মিলান, শিপমেন্ট, বুকিং, ইনভয়েস, প্যাকিং লিস্ট, ডকুমেন্ট হস্তান্তর) নম্বর এখান থেকে নেবে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_20_000100_create_ss_settings.php`। প্রতিটা কোম্পানির জন্য একটা সারি।

- **`ss_settings`:**
  - `company_id` (unique)
  - নিয়ম: `ship_against_delivered` (ডিফল্ট false), `require_export_docs` (ডিফল্ট true)
  - অ্যালার্টের দিন: `due_alert_days` (৭), `cert_alert_days` (৩০)
  - ৯টা প্রিফিক্স: `prefix_amendment` SOA, `prefix_challan_return` DCR, `prefix_sc_return` SCRT, `prefix_sc_reconciliation` SCRC, `prefix_shipment` SHP, `prefix_booking` BKG, `prefix_invoice` INV, `prefix_packing_list` PL, `prefix_handover` DH
  - `seq_width` (৬), `updated_by`
- ডিফল্টগুলো Brickland ডেমোর মান থেকে নেওয়া। সারি না থাকলে `SsSettings::get()` এই ডিফল্ট দেয়।

## ৫. Workflow / Status ও Business Rules

- **দুই ট্যাব, পেজ রিলোড ছাড়াই বদলায়:**
  - **Current Settings 1:** এক সারির গ্রিড। গ্রুপ হেডার RULES / ALERT DAYS / PREFIXES, তারপর Number Width, Updated By, Updated At আর ACTION (Edit)।
  - **Edit settings:** দুটো চেকবক্স, প্রতিটার নিচে মানে লেখা। তিন কলামে Due alert window আর Certificate expiry window (দিন), ৯টা প্রিফিক্স, Sequence width (অঙ্ক; নিচে লেখা থাকে কোন নম্বর ছাপা হবে, যেমন "6 prints SHP-2026-000001")। নিচে Cancel আর Update settings।
- **ভ্যালিডেশন:**
  - দিন ১ থেকে ৩৬৫।
  - প্রিফিক্স ২ থেকে ৬টা বড় হাতের অক্ষর (A–Z)। ইনপুটে ছোট হাতের অক্ষর লিখলে বড় হাতের হয়ে যায়, অক্ষর ছাড়া অন্য কিছু ঢোকে না।
  - প্রতিটা সিরিজের প্রিফিক্স আলাদা হতে হবে ("SHP is already the Shipment prefix — every series needs its own.")।
  - নম্বরের অঙ্ক ৪ থেকে ৮।
- **নম্বরের নিয়ম (`SsSettings::nextNo`):** প্রিফিক্স-বছর-চলমান নম্বর, অঙ্কসংখ্যা settings অনুযায়ী (SHP-2026-000001)। প্রতি বছর নম্বর নতুন করে শুরু হয়। প্রিফিক্স বদলালে নতুন প্রিফিক্সে ১ থেকে শুরু হয়।
- **যে প্রথম সেভ করে তার নাম আর সময়** Updated By / Updated At-এ দেখায়। সেভ না হওয়া পর্যন্ত ওপরে লেখা থাকে "এখনো সেভ হয়নি — এগুলো ডিফল্ট"।
- যে রোল শুধু দেখতে পারে, তার জন্য Edit ট্যাব আর Edit বাটন থাকে না, শুধু একটা নোটিস।

## ৬. API ও পারমিশন

- **পারমিশন (নতুন গ্রুপ `salesshipment`):**
  - `salesshipment.settings.view`
  - `salesshipment.settings.manage`
  - FACTORY_ADMIN আর FACTORY_MANAGER দুটোই পায়; AUDITOR পায় শুধু view।
- **API (`SsSettingsApiController`):** `GET /sales-shipment/settings` · `PUT /sales-shipment/settings`
- **মেনু:** নতুন মডিউল SALES & SHIPMENT (Truck আইকন, SALES & MERCHANDISING গ্রুপে)। ফোল্ডার OPERATION, স্ক্রিন `/sales-shipment/sd-settings`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/sales-shipment/settings.jsx` (`SalesShipmentSettings`)। রুট `app/(app)/sales-shipment/[screen]/` (`page.js`, `screens.js`, `actions.js`): পরের সাব-মডিউলের স্ক্রিনগুলোও এখানে যোগ হবে।
- **Current Settings:** গ্রুপ হেডারসহ ব্র্যান্ড-রঙের হেডার, প্রিফিক্স মনোস্পেস ফন্টে, সংখ্যা ডানে।
- **Edit settings:** চেকবক্স, দিন/অঙ্কের ঘরে ডানপাশে একক লেখা, প্রতিটা ঘরের নিচে ইঙ্গিত, আর ভুল হলে সেই ঘরের নিচে লাল বার্তা। সেভ হলে toast দেখায় আর Current ট্যাবে ফিরে যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন):

**Backend (নতুন মডিউল `app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_20_000100_create_ss_settings.php`
- `Models/SsSetting.php`
- `Support/SsSettings.php` (ডিফল্টসহ settings, পরের নম্বর)
- `Http/Controllers/Api/SsSettingsApiController.php`
- `Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (নতুন), `lang/en/nav.php`, `lang/bn/nav.php` (বদলানো)

**Frontend**
- `frontend/src/app/(app)/sales-shipment/[screen]/page.js`, `screens.js`, `actions.js` (নতুন)
- `frontend/src/components/sales-shipment/settings.jsx` (নতুন)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentSettingsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/SalesShipment/SalesShipmentSettingsTest.php`):** test2 DB-তে পাস (৫টা টেস্ট, ৫৪টা assertion)। MenuAccessTest + RoleMatrixTest-ও পাস (১৩টা টেস্ট, ৮৪টা assertion)।
- `test_the_defaults_show_until_the_settings_are_first_saved`: saved false; মান No / Yes / 7 / 30 / SOA … DH / 6; উদাহরণ SHP-2026-000001।
- `test_the_settings_are_saved_per_company_with_who_changed_them`: সেভের পর saved true, Updated By = মালিকের নাম, উদাহরণ SHIP-2026-00001; আবার সেভ করলে একই সারি বদলায়; অন্য কোম্পানি তখনো ডিফল্ট দেখে।
- `test_the_windows_prefixes_and_width_are_checked`: আটকায় দিন ০ আর ৩৬৬, প্রিফিক্স "B" / "bkg" / "BOOKING", অঙ্ক ৩ আর ৯, আর দুই সিরিজে একই প্রিফিক্স (বার্তায় কোন সিরিজের); কিছুই সেভ হয় না।
- `test_the_next_number_follows_the_prefix_and_width`: প্রথম নম্বর SHP-2026-000001; শেষটা ০০০০০৯ হলে পরেরটা ০০০০১০ (অন্য কোম্পানির নম্বর গোনা হয় না); প্রিফিক্স SHIP আর অঙ্ক ৫ করলে SHIP-2026-00001; ২০২৫ সালের তারিখে SHIP-2025-00001।
- `test_who_may`: AUDITOR দেখতে পারে, বদলাতে পারে না; FACTORY_MANAGER বদলাতে পারে; TECHNICIAN আর VIEWER পায় না।

**UI চেক (`app_check/plans/ss01`, `ss01b`; দুটোতেই error 0, dev ডাটা রাখা আছে):**
- প্রথমে ডিফল্ট: "No Yes 7 30 SOA DCR SCRT SCRC SHP BKG INV PL DH 6 — —"।
- Edit-এ Shipment Invoice-এর প্রিফিক্স SHP দিলে Update-এ বার্তা আসে "SHP is already the Shipment prefix — every series needs its own."।
- INV ফিরিয়ে আর Due ১০ করে সেভ: সারিতে Updated By দেখায় "Rahim Uddin", সাথে সময়।
- Sequence width ৮ লিখলে নিচে লেখা বদলায় "8 prints SHP-2026-00000001"। Cancel করলে কিছু বদলায় না।
- চেকবক্সে টিক দিয়ে আর Due আবার ৭ করে সেভ করলে সারিতে "Yes Yes 7 30 …" দেখায়।
- শেষে dev-এ Ship Against Delivered আবার No করে রাখা হয়েছে (Brickland-এর মান)। পরের সাব-মডিউলের শিপমেন্ট টেস্টে এটা কাজে লাগবে।

## ১০. খোলা প্রশ্ন

১. **"Ship Against Delivered" কোন ডেলিভারি ধরবে:** নিয়মটা এখন সেভ হয়, কিন্তু কাজ করবে 21/02 Order & Delivery আর 21/05 Shipment বানানোর পরে। "নিট ডেলিভারি" মানে ডেলিভারি চালান বাদ চালান রিটার্ন, এভাবেই ধরা হবে। অন্য হিসাব লাগলে জানাবেন।

২. **দুই শিপমেন্ট:** 20/07 Assembly Floor-এর Shipment (ফ্লোরের Ready থেকে, SHP-YYYYMMDD-####) ইতিমধ্যে আছে। 21/05 Sales & Shipment-এর Shipment এই settings-এর প্রিফিক্স/নম্বর (SHP-2026-000001) নেবে। দুটো আলাদা কাগজ থাকবে, নাকি 21/05-এর শিপমেন্টই ফ্লোরের Ready থেকে কমাবে, জানাবেন।

৩. **প্রিফিক্স বদলালে আগের নম্বর:** প্রিফিক্স বদলালে আগের কাগজের নম্বর যেমন ছিল তেমনই থাকে; নতুন প্রিফিক্সে নম্বর ১ থেকে শুরু হয়। Brickland-এও তাই কিনা ডেমো থেকে বোঝা যায়নি।

## ১১. As-built নোট

- **Brickland প্রোব:** ট্যাব, গ্রুপ হেডার, কলাম, Edit ফর্মের ঘর, ইঙ্গিতের লেখা, ডিফল্ট মান আর Update settings বাটন লাইভ পেজ থেকে নেওয়া।
- **Brickland-এর Export বাটন:** এক সারির settings-এ রাখা হয়নি। গ্রিডটা RecordsGrid নয়, গ্রুপ হেডারসহ নিজস্ব টেবিল, তাই Search / Items per page / Export নেই। দরকার হলে যোগ করা যাবে।
- **Sales & Shipment প্রথম সাব-মডিউল:** নতুন মডিউল, মেনু, পারমিশন গ্রুপ আর রুট এখানেই বসানো হয়েছে; পরের ৬টা সাব-মডিউল এগুলোতেই যোগ হবে।
