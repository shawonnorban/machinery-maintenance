# Shipment

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/07`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Shipment Dashboard | `/m/garments/shipment-dashboard` | 1 | 4 | 0 |
| 2 | Shipment Entry | `/m/garments/shipment-entry` | 1 | 0 | 0 |
| 3 | Mark as Shipped | `/m/garments/shipment-mark-shipped` | 1 | 0 | 0 |
| 4 | Shipment History | `/m/garments/shipment-history` | 1 | 4 | 0 |
| 5 | Shipment Reports | `/m/garments/shipment-reports` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Shipment Dashboard

`/m/garments/shipment-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Buyer, PO, style, order, colour, destination, shipment no |
| Shipment Status All Planned Confirmed Shipped Cancelled Reversed (corrected) | select |  | All, Planned, Confirmed, Shipped, Cancelled, Reversed (corrected) |
| Shipment Date From | date |  |  |
| To | date |  |  |
- সেকশন: Shipments
- বাটন/অ্যাকশন: Search, CSV

#### Shipment Entry

`/m/garments/shipment-entry`

**ভিউ**
- সেকশন: Select from Ready for Shipment, Shipment Entry, Open Shipments

#### Mark as Shipped

`/m/garments/shipment-mark-shipped`

**ভিউ**
- সেকশন: Confirmed — Ready to Mark as Shipped, Planned — Awaiting Confirmation

#### Shipment History

`/m/garments/shipment-history`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Buyer, PO, style, order, colour, destination, shipment no |
| Shipment Status All Planned Confirmed Shipped Cancelled Reversed (corrected) | select |  | All, Planned, Confirmed, Shipped, Cancelled, Reversed (corrected) |
| Shipment Date From | date |  |  |
| To | date |  |  |
- সেকশন: Shipment History
- বাটন/অ্যাকশন: Search, CSV

#### Shipment Reports

`/m/garments/shipment-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report Daily Shipment Report Buyer-wise Shipment Report PO-wise Shipment Report  | select |  | Daily Shipment Report, Buyer-wise Shipment Report, PO-wise Shipment Report, Style-wise Shipment Report, Shipment Quantit |
| From | date |  |  |
| To | date |  |  |
| Buyer / PO / Style | text |  |  |
- সেকশন: Daily Shipment Report
- বাটন/অ্যাকশন: Run, Print, CSV
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **20/06 Ready For Shipment:** PO + রঙের সারি (Final Packed, Shipped, Ready, কার্টন, সাইজ, গন্তব্য) আর `asm_fg_ledger`। সারির হিসাব `ReadyBook`-এ সরানো হয়েছে, Ready আর Shipment দুটোই সেটা পড়ে।
- **20/04 Packing:** প্যাকড আউট ব্যাচ (FIFO-র জন্য `packed_out_at`), ব্যাচের গন্তব্য (শিপমেন্টের ডিফল্ট গন্তব্য)।
- **রিপোর্টের স্ক্রিন:** 20/04-এর `PackingReports` কম্পোনেন্ট এখন `prefix` নেয়, তাই Shipment-এর ১৭টা রিপোর্ট একই স্ক্রিনে (Print + CSV)।
- **Procurement-এর `shipments` টেবিল:** ইমপোর্ট শিপমেন্ট, এর সাথে সম্পর্ক নেই। তাই নতুন টেবিল `asm_shipments`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_13_000100_create_asm_shipments.php`।

- **`asm_shipments`:**
  - `shipment_no` (SHP-YYYYMMDD-####), `order_id`, `po_no`, `buyer_id`, `style`, `colour`।
  - `qty`, `carton_qty`, `ship_date`, `destination`, `mode` (SEA / AIR / ROAD), `vehicle_ref` (ট্রাক / কন্টেইনার / ফ্লাইট), `remarks`।
  - `status`: PLANNED → CONFIRMED → SHIPPED; খোলা অবস্থায় CANCELLED; শিপড হওয়ার পর সংশোধনে REVERSED।
  - `ready_before`, `remaining_after` (শিপড হওয়ার সময় লেখা হয়)।
  - `confirmed_at/by`, `shipped_at/by`, `cancelled_at/by`, `cancel_reason`, `reversed_at/by`, `reverse_reason`, `corrects_id` (সংশোধনের নতুন শিপমেন্ট কোনটার বদলে), `created_by`।
- **স্টক থেকে আউট:** `asm_fg_ledger`-এ SHIPMENT লাইন (প্যাক করা ব্যাচ আর সাইজ অনুযায়ী); সংশোধনে একই লাইনগুলোর SHIPMENT_REVERSAL (ইন)।

## ৫. Workflow / Status ও Business Rules

- **Shipment Entry:**
  - বাঁয়ে রেডি পরিমাণসহ PO / রঙের তালিকা (Ready, Available, অবস্থা)। একটা বাছলে ডানে বায়ার, PO, স্টাইল, রং, স্টকে থাকা সাইজ, PO-র শিপমেন্টের তারিখ, Ready, On Open Shipments, কার্টন।
  - ঘর: Shipment Qty (সর্বোচ্চ Available), Cartons, Shipment Date (আজ), Destination (খালি রাখলে ব্যাচের গন্তব্য), Mode, Truck / Container / Flight, Remarks।
  - Ready Before / Shipment / Remaining Ready সাথে সাথে দেখায়। Available-এর বেশি দিলে লাল বার্তা আর বাটন বন্ধ; সার্ভারও আটকায়।
  - Ready-র চেয়ে কম হলে "Create Partial Shipment"। শিপমেন্ট PLANNED অবস্থায় তৈরি হয়।
  - Available = Ready − খোলা (Planned / Confirmed) শিপমেন্টের পরিমাণ, যাতে একই পিস দুবার শিপমেন্টে না ওঠে।
- **Confirm:** PLANNED → CONFIRMED।
- **Mark as Shipped:** শুধু CONFIRMED। নিশ্চিত হতে জিজ্ঞেস করে।
  - পরিমাণ এখনো Ready-র মধ্যে আছে কিনা আবার যাচাই হয়।
  - স্টক থেকে আউট হয় সবচেয়ে পুরনো প্যাক করা ব্যাচ আগে, ব্যাচের ভেতরে সাইজের ক্রমে।
  - Ready Before আর Remaining লেখা হয়। Remaining ০ হলে PO / রং Shipment Completed হয়ে চালু Ready তালিকা থেকে সরে যায়।
- **Cancel:** কারণসহ। PLANNED → শিপমেন্টের লোক, CONFIRMED → সুপারভাইজার। বাতিল হলে পরিমাণ আবার Available হয়।
- **Correct:** শুধু SHIPPED, শুধু সুপারভাইজার, কারণসহ।
  - স্টকে পুরোটা ফেরত (Shipment Reversal), অবস্থা REVERSED।
  - সংশোধিত পরিমাণ দিলে সাথে সাথে নতুন শিপমেন্ট তৈরি হয়ে শিপড হয় ("Corrects SHP-…")। Available-এর বেশি দিলে পুরো সংশোধন বাতিল।
- **Dashboard / History:**
  - সব শিপমেন্ট নতুনটা আগে। Search (বায়ার, PO, স্টাইল, অর্ডার, রং, গন্তব্য, শিপমেন্ট নং), অবস্থা, শিপমেন্টের তারিখের সীমা, CSV।
  - প্রতিটা সারিতে অবস্থা অনুযায়ী Confirm / Mark as Shipped / Correct / Cancel।
  - ড্যাশবোর্ডের ৮টা হিসাব: Ready, আজকের শিপমেন্ট, মোট শিপড, বাকি (খোলা শিপমেন্ট), আংশিক আর সম্পন্ন PO / রং, রেডি কার্টন, বায়ার।
- **রিপোর্ট (১৭টা):**
  - শিপমেন্ট (তারিখের সীমায় শিপড): Daily, Buyer, PO (অর্ডার পরিমাণসহ), Style, Quantity, Partial, Completed, History (শিপমেন্টের তারিখে, সব অবস্থা)।
  - রেডি (এখনকার অবস্থা): Ready, Buyer / PO / Style-wise Ready, Ready Quantity (সাইজ অনুযায়ী), Pending, Partially Shipped।
  - Finished Goods Stock (এখনকার) আর Finished Goods Ledger (তারিখের সীমায়)।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `assembly.shipment.view`
  - `assembly.shipment.manage` (এন্ট্রি, কনফার্ম, শিপড, Planned বাতিল)
  - `assembly.shipment.supervise` (Confirmed বাতিল, Correct)
  - FACTORY_ADMIN আর FACTORY_MANAGER তিনটাই পায়। LINE_CHIEF শুধু view।
- **API (`ShipmentApiController`, `ShipmentReportApiController`):**
  - `GET /assembly/shipments/dashboard?q=&status=&from=&to=` · `GET /assembly/shipments/history?…` · `GET /assembly/shipments/entry` (রেডি সারি, খোলা শিপমেন্ট, রিপোর্টের তালিকা)
  - `POST /assembly/shipments` · `POST /assembly/shipments/{id}/confirm|ship|cancel|correct`
  - `GET /assembly/shipments-reports?kind=&from=&to=&q=`
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার SHIPMENT, ৫টা স্ক্রিন: `/assembly-floor/shipment-dashboard`, `/shipment-entry`, `/shipment-mark-shipped`, `/shipment-history`, `/shipment-reports`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/shipment.jsx` (`ShipmentNav`, `ShipmentTable`, `ShipmentFilter`, `ShipmentDashboard`, `ShipmentEntry`, `MarkShipped`, `ShipmentHistory`); রিপোর্ট `PackingReports` (`prefix="sh.rep"`)।
- **ওপরের বাটন (Brickland-এর মতো):** Dashboard, Shipment Entry, Mark as Shipped, History, Reports।
- **Shipment Entry:** বাঁয়ে রেডি তালিকা (রেডিও বাটন), ডানে শিপমেন্ট ফর্ম আর Ready Before / Shipment / Remaining Ready; নিচে Open Shipments। Ready for Shipment থেকে "Shipment" চাপলে সেই PO / রং আগে থেকে বাছা থাকে (URL-এ PO আর রং, কোনো ULID নয়)।
- **Mark as Shipped:** ওপরে "Ready Qty − Shipment Qty = Remaining Ready Qty" নিয়মের কার্ড; নিচে Confirmed আর Planned তালিকা।
- **শিপমেন্টের টেবিল:** SL, Shipping Ref. (কে এন্ট্রি / কনফার্ম / শিপ করেছেন, মাউস রাখলে), Buyer, PO, Style, Color, Ship Qty, Cartons, Ready Before, Remaining, Date, Destination (Mode · গাড়ি), Status (বাতিলের কারণ, রিভার্সের কারণ, "Corrects …"), Action।
- **অন্য স্ক্রিনে বদল:** Ready for Shipment (Shipment বাটন, বিস্তারিতে শিপমেন্ট) আর Packing Dashboard (Shipment প্যানেল, Total Shipped)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_13_000100_create_asm_shipments.php`
- `app/Modules/Assembly/Models/AsmShipment.php`
- `app/Modules/Assembly/Support/ReadyBook.php` (নতুন: PO / রঙের সারি আর শিপমেন্ট দেখানো, Ready-ও এটা ব্যবহার করে)
- `app/Modules/Assembly/Http/Controllers/Api/ShipmentApiController.php`, `ShipmentReportApiController.php`
- `app/Modules/Assembly/Http/Controllers/Api/ReadyApiController.php` (বদলানো: ReadyBook, বিস্তারিতে শিপমেন্ট)
- `app/Modules/Assembly/Http/Controllers/Api/PackingApiController.php` (বদলানো: ড্যাশবোর্ডে শিপমেন্ট, Total Shipped, Ready)
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/shipment.jsx` (নতুন)
- `frontend/src/components/assembly/packing.jsx` (বদলানো: রিপোর্টের `prefix`, শিপমেন্ট প্যানেল)
- `frontend/src/components/assembly/ready.jsx` (বদলানো: Shipment বাটন, বিস্তারিতে শিপমেন্ট)
- `frontend/src/app/(app)/assembly-floor/[screen]/page.js` (বদলানো: query), `screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyShipmentTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyShipmentTest.php`):** test2 DB-তে পাস, ২টা টেস্ট, ২৩১টা assertion। Assembly-র ১৯টা টেস্টই একসাথে পাস (১,০৯৫টা assertion); MenuAccessTest + RoleMatrixTest (১৩টা, ৮৪টা assertion) পাস।
- `test_a_shipment_is_entered_confirmed_and_shipped_oldest_batch_first`:
  - দুটো ব্যাচ (S ১০, পরে M ৮), একই PO-রং → একটা সারি, Ready ১৮, সাইজ S, M, গন্তব্য Gdansk।
  - ১৯ আটকায়; ১২ → SHP-…-0001 Planned, আংশিক, ডিফল্ট গন্তব্য। এরপর Available ৬ (খোলা শিপমেন্ট ১২), ৭ আটকায়।
  - কনফার্ম ছাড়া শিপড আটকায়; কনফার্ম; দ্বিতীয়বার কনফার্ম আটকায়; শিপড → Ready Before ১৮, Remaining ৬।
  - স্টক থেকে আউট: আগের ব্যাচের S ১০, তারপর পরের ব্যাচের M ২। সারি Partially Shipped; স্টকে M ৬।
  - বাকি ৬ (Air, Warsaw) → Remaining ০, Shipment Completed; ড্যাশবোর্ডের ৮টা হিসাব; বিস্তারিতে দুটো শিপমেন্ট; History-র ফিল্টার; Packing Dashboard-এ Total Shipped ১৮।
- `test_cancel_correct_reports_and_who_may`:
  - LINE_CHIEF দেখতে পারে, এন্ট্রি / কনফার্ম / সংশোধন পারে না।
  - Planned বাতিল (কারণ বাধ্যতামূলক), Available আবার ১০; বাতিলের পর কনফার্ম আটকায়।
  - শিপড ১০ → সংশোধন ১১ আটকায় (কিছুই বদলায় না); সংশোধন ৭ → মূলটা REVERSED, নতুন SHP-…-0003 শিপড, "Corrects SHP-…-0002", Ready Before ১০, Remaining ৩; লেজারে আউট ১০, ফেরত ১০, আউট ৭।
  - রিভার্সড আবার সংশোধন আটকায়; শুধু রিভার্স (পরিমাণ ছাড়া) → Ready আবার ১০।
  - FACTORY_MANAGER কনফার্মড বাতিল করতে পারে।
  - ১৭টা রিপোর্ট; Daily, PO (অর্ডার ৬০০), Quantity, Partial, History (৫টা অবস্থা), Ready, Ready Quantity (সাইজ), Pending, FG Stock (ইন ২৭, আউট ২৩, ব্যালেন্স ৪), FG Ledger; বায়ার দিয়ে খোঁজা; অচেনা রিপোর্ট আটকায়।
  - STORE_MANAGER আর TECHNICIAN পায় না।

**UI চেক (`AnnotechERP/tools/app_check/plans/sh01`), dev অ্যাপে (ডাটা রেখে দেওয়া হয়েছে):**
- Ready for Shipment-এর "Shipment" → Shipment Entry, PO-SS27-01 / Navy Blue আগে থেকে বাছা (Ready ৬২, ২ কার্টন)।
- ৪০ পিস, ১ কার্টন, Hamburg, Sea, MSKU1234567 → "Ready Before ৬২ · Shipment ৪০ · Remaining ২২" → Create Partial Shipment → SHP-20261107-0001 Planned → Confirm।
- Mark as Shipped → শিপড। ড্যাশবোর্ড: Ready ২২, আজ ৪০, মোট ৪০; সারিতে Ready Before ৬২, Remaining ২২, Correct বাটন।
- Ready for Shipment: Ready ২২, Shipped ৪০, Partially Shipped।
- রিপোর্ট: Daily (১টা শিপমেন্ট, ৪০), Finished Goods Ledger (Packing Out ৬২ ইন, Shipment ৪০ আউট), প্রিন্টের PDF নেওয়া হয়েছে।
- Packing Dashboard-এর Shipment প্যানেলে SHP-20261107-0001।
- **error:** ০।

## ১০. খোলা প্রশ্ন

১. **Sales & Delivery-র সাথে যোগ:** Brickland-এর Shipment Entry-তে "Sales & Delivery Shipment" বাছাই করা যায় (শিপড হলে সেখানে FINAL packing list তৈরি হয়), আর তালিকায় "S&D …" নম্বর দেখায়। আমাদের Sales & Delivery মডিউল এখনো তৈরি হয়নি, তাই ঘরটা রাখা হয়নি। সেই মডিউল বানানোর সময় যোগ করা হবে কিনা জানাবেন।

২. **সংশোধনের নতুন শিপমেন্টের তারিখ:** সংশোধিত পরিমাণে আবার শিপ করলে নতুন শিপমেন্টের তারিখ এখন আজকের দিন, আর সেটা সরাসরি শিপড হয় (Brickland-এর "re-ship now")। মূল শিপমেন্টের তারিখ রাখা হবে কিনা জানাবেন।

৩. **শিপমেন্টের তারিখ বনাম শিপড হওয়ার দিন:** শিপমেন্টের রিপোর্ট (Daily ইত্যাদি) শিপড হওয়ার দিন ধরে গোনে, আর History ধরে শিপমেন্টের তারিখ (এন্ট্রিতে দেওয়া)। কোন তারিখে গোনা হবে জানাবেন।

৪. **রেডি রিপোর্টে তারিখ:** রেডি আর Finished Goods Stock-এর রিপোর্ট এখনকার অবস্থা দেখায়, From / To তারিখ মানে না। নির্দিষ্ট দিনের অবস্থা লাগবে কিনা জানাবেন।

৫. **শিপমেন্টের রোল:** Brickland "Shipment Operator" আর "Manager"-এর কথা বলে। আমাদের রোলে এই দুটো নেই, তাই এখন Factory Admin আর Factory Manager শিপমেন্ট চালান, Line Chief শুধু দেখেন। আলাদা Shipment / Commercial রোল লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/sh01` আর চার স্ক্রিনের এক JS চাঙ্ক):** ডেমোতে কোনো শিপমেন্ট নেই, তাই কলাম, অবস্থা (Planned / Confirmed / Shipped / Cancelled / Reversed, শিপড আর Remaining ০ হলে "Completed"), নিয়ম ("never above the Ready quantity", "oldest packed batch first"), Correct-এর দুই প্রশ্ন (কারণ, সংশোধিত পরিমাণ), কোন রোল কোন বাটন পায় আর ১৭টা রিপোর্টের নাম চাঙ্ক থেকে নেওয়া।
- **Brickland URL-এ ULID দেয়, আমরা দিই না:** Brickland Ready থেকে Shipment Entry খোলে `?order=<id>&color=` দিয়ে; আমাদের নিয়মে URL-এ ULID নেই, তাই `?po=…&colour=…`।
- **একসাথে দুজন:** এন্ট্রি, শিপড আর সংশোধনের সময় প্যাকড আউট ব্যাচগুলো লক করে আবার হিসাব হয়, যাতে একই পিস দুবার শিপ না হয়।
