# General

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/04`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Shipment Planning | `/m/sales-delivery/shipment-planning` | 2 | 17 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Shipment Planning

`/m/sales-delivery/shipment-planning`

**ট্যাব: Shipment Plan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| SO No | text |  |  |
| Shipment No | text |  |  |
| Stage | select |  | All, Planned, Booked, Documentation Pending, Ready, Shipped, Closed, Cancelled |
| Shipment Date From | date |  |  |
| Shipment Date To | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Shipment Plan List 0, Shipment Plan Entry, All, Search, Reset

**ট্যাব: Shipment Plan Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order List | search |  | Search SO No, PO or customer… |
| Existing shipment plan | select |  |  |
| Sales Order* | select | ✔ | Select… |
| Shipment No | text |  | [Auto Gen] |
| Plan Date* | date | ✔ |  |
| Shipment Date* | date | ✔ |  |
| Ex-Factory Date | date |  |  |
| Shipment Mode* | select | ✔ | Select…, Sea, Air, Road, Sea-Air, Courier |
| Destination Port | text |  |  |
| Port of Loading | text |  | Chattogram |
| Incoterm | select |  | Select…, FOB, CFR, CIF, EXW, FCA, CPT, CIP, DAP, DDP |
| Remarks | textarea |  |  |
- গ্রিড কলাম: SO No, Customer, PO, Unplanned, Ship Date
- সেকশন: Order List, Shipment Plan Entry, Plan, Destination, Planned Lines
- বাটন/অ্যাকশন: Shipment Plan List 0, Shipment Plan Entry, -- Select Customer --, Select…, Add line, Back To List, Clear, Save shipment plan
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/02-এর সেলস অর্ডার আর ডেলিভারি:** শুধু কনফার্মড খোলা অর্ডার। লাইন সাইজ অনুযায়ী (চালানের মতো)। ডেলিভারির হিসাব আসে `SalesBook::sizeFigures` থেকে।
- **21/01-এর settings:**
  - শিপমেন্ট নম্বর `prefix_shipment` আর অঙ্কসংখ্যা থেকে (SHP-2026-000001)।
  - "Ship Against Delivered" চালু থাকলে প্ল্যান নিট ডেলিভারির বেশি হতে পারে না। 21/01-এর এই নিয়মটা এখানেই প্রথম কাজ করছে।
- **21/02-এর shared UI আর Order-to-Shipment স্ট্রিপ:** এই স্ক্রিন ধাপ ৪, তাই স্ট্রিপে ধাপ ৪ এখন লিংক। ধাপ ৩ (চালান) থেকে "Next step" এখন এখানে আসে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_23_000100_create_ss_shipments.php`:

- **`ss_shipments`** (SHP-…): পুরো শিপমেন্টের মূল কাগজ। 21/05-এর বুকিং, কনটেইনার, ইনভয়েস, প্যাকিং লিস্ট, কনফার্মেশন আর ক্লোজিং এর সাথেই যুক্ত হবে।
  - order, plan_date, shipment_date, ex_factory_date, shipment_mode (Sea / Air / Road / Sea-Air / Courier)।
  - destination country / port, port_of_loading, incoterm, remarks।
  - stage (PLANNING / BOOKED / DOCUMENTATION / READY / CONFIRMED "Shipped" / CLOSED / CANCELLED)।
- **`ss_shipment_lines`:** size, qty, carton_qty, rate, shipped_qty (কনফার্মেশনের সময় 21/05 বসাবে)।

## ৫. Workflow / Status ও Business Rules

- **"Order to Shipment · Step 4 of 12"** স্ট্রিপের নিচে দুই ট্যাব:
  - **Shipment Plan List:** ফিল্টার (কাস্টমার, SO, শিপমেন্ট নং, Stage, শিপমেন্টের তারিখ), ৪টা টাইল, ১৩ কলাম। সারির কাজ: Edit, Print, Cancel, Delete।
  - **Shipment Plan Entry:** বাঁদিকে Order List (কনফার্মড অর্ডার, যাদের প্ল্যান বাকি)। বাছলে অর্ডারের শিপমেন্টের তারিখ, গন্তব্য বন্দর / দেশ আর incoterm ভরে যায়, আর বাকি লাইনগুলো আসে।
- **সীমা:** প্রতিটা সাইজে প্ল্যান ≤ অর্ডার − ক্যানসেল না হওয়া অন্য শিপমেন্টে প্ল্যান। Settings-এ Ship Against Delivered চালু থাকলে আরও ≤ নিট ডেলিভারি − অন্য প্ল্যান (তখন স্ক্রিনে নোট দেখায়, বার্তাও আলাদা)।
- **তারিখ:** এক্স-ফ্যাক্টরি তারিখ শিপমেন্টের তারিখের পরে হতে পারে না।
- **কখন বদলানো যায়:** PLANNING অবস্থায় প্ল্যান বদলানো বা মোছা যায়। শিপমেন্ট এগোলে (বুকিং ইত্যাদি, 21/05) লক। Cancel করা যায় খোলা অবস্থায় (PLANNING থেকে READY), তখন পরিমাণ অর্ডারে ফিরে যায়।
- **সেলস অর্ডার তালিকা (21/02):** Shipped Qty আর Shipment Status এখন আসল হিসাব থেকে: Not Shipped / Planned / Partially Shipped / Shipped। শিপ হওয়া পরিমাণ আসবে 21/05-এর কনফার্মেশন থেকে।
- **মাস বন্ধ (21/02):** ওই মাসের শিপমেন্টের তারিখে খোলা শিপমেন্ট থাকলে SHIPMENT_PENDING ব্যতিক্রম আসে।

## ৬. API ও পারমিশন

- **পারমিশন:** `salesshipment.shipment.view` / `manage`।
  - FACTORY_ADMIN, FACTORY_MANAGER আর STOREKEEPER পায় view + manage।
  - STORE_MANAGER পায় view।
  - AUDITOR আর VIEWER পায় view।
- **API:** `/sales-shipment/shipments` (GET / POST), `orders`, `source/{so}`, `{no}` (GET / PUT / DELETE), `{no}/cancel`।
- **মেনু:** OPERATION-এর ভেতরে নতুন উপ-ফোল্ডার "General", স্ক্রিন `/sales-shipment/shipment-planning`।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/sales-shipment/shipment-plan.jsx` (`ShipmentPlanDesk`)।
- **প্রিন্ট:** Shipment Plan (দেখে নেওয়া হয়েছে)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_23_000100_create_ss_shipments.php`
- `Models/SsShipment.php`, `Models/SsShipmentLine.php`
- `Http/Controllers/Api/ShipmentPlanApiController.php`
- `Http/Controllers/Api/SalesOrderApiController.php`, `MonthCloseApiController.php`, `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/shipment-plan.jsx` (নতুন)
- `frontend/src/components/sales-shipment/shared.jsx` (বদলানো: স্ট্রিপের ধাপ ৪ লিংক)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentPlanningTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentPlanningTest.php`):** test2 DB-তে পাস। Sales & Shipment-এর চার ফাইল মিলিয়ে ২০টা টেস্ট, ৪৪২টা assertion। MenuAccess + RoleMatrix-ও পাস।
- **প্ল্যান ও ক্যানসেল:** কনফার্ম না হওয়া অর্ডারে আটকায়। source-এ অর্ডারের তারিখ / incoterm / বন্দর আসে। আটকায় ৫০১ (সীমা ৫০০), শিপমেন্টের পরের এক্স-ফ্যাক্টরি তারিখ আর শূন্য লাইন। SHP-2026-000001 (৩০০ + ৩০০, রেট অর্ডার থেকে); বাকি ২০০; ২০১ আটকায়; SHP-2026-000002 (২০০); প্রথমটা বদলানো যায়। তালিকায় ৫৫০ / ২৪ কার্টন / ২২০০ / Hamburg / Sea; সেলস অর্ডারে "Planned"। BOOKED হলে বদল আর মোছা আটকায়; Cancel → বাকি ৬০০; দ্বিতীয়বার Cancel আটকায়; প্ল্যান অবস্থার শিপমেন্ট মোছা যায়।
- **Ship Against Delivered:** চালু করলে ডেলিভারি না হলে বাকি ০। ১২০ পিস গেটে যাওয়ার পর বাকি ১২০; ১২১ আটকায় (আলাদা বার্তা); ১২০ সেভ হয়।
- **মাস বন্ধ ও কে পারে:** SHIPMENT_PENDING ব্যতিক্রম (SHP-2026-000001, ২৫ নভেম্বর, PLANNING)। STORE_MANAGER দেখে, প্ল্যান করতে পারে না। FACTORY_MANAGER ক্যানসেল করে। AUDITOR দেখে। TECHNICIAN পায় না।

**UI চেক (`app_check/plans/sp01`; error 0, dev ডাটা রাখা আছে):**
- SHP-2026-000001: SO-20261107-0001-এর দুই সাইজ ৪০০ + ৫০০ (সংশোধনের পরের অর্ডার), ৪৫ কার্টন, মূল্য ৪,০৫০।
- এক্স-ফ্যাক্টরি ২৩ নভেম্বর, Chattogram → Hamburg, FOB, Sea।
- সেলস অর্ডার তালিকায় Shipment Status এখন "Planned"।
- প্রিন্টের preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **প্ল্যানে বাড়তি (overage):** ডেলিভারি চালানে অর্ডারের "Additional Qty (%)" পর্যন্ত বেশি দেওয়া যায়। কিন্তু Brickland-এর নোটে আছে প্ল্যান "অর্ডারে যা প্ল্যান বাকি" তার বেশি নয়, তাই শিপমেন্ট প্ল্যানে বাড়তি নেই। বাড়তি পরিমাণও শিপমেন্টে প্ল্যান করা যাবে কিনা জানাবেন।

২. **Assembly Floor-এর শিপমেন্টের সাথে সম্পর্ক:** 21/01-এর খোলা প্রশ্ন ২-এর মতোই। Sales & Shipment-এর শিপমেন্ট (SHP-2026-…) আর 20/07-এর ফ্লোর শিপমেন্ট (SHP-YYYYMMDD-…) এখন আলাদা। 21/05-এর কনফার্মেশন ফ্লোরের Ready স্টক থেকে কমাবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব:** RSC টুল দিয়ে নেওয়া হয়েছে কলাম, ফিল্টার, Stage-এর মান (PLANNING / BOOKED / DOCUMENTATION / READY / CONFIRMED "Shipped" / CLOSED / CANCELLED), প্যানেলের ঘর, ইঙ্গিত আর নোট।
- **`ss_shipments` টেবিল:** 21/05-এর ভিত। ইনভয়েস বা প্যাকিং লিস্ট হলে লাইন লক হবে, Brickland-এর নোট অনুযায়ী; এখন stage PLANNING ছাড়া সবই লক।
- **টেস্টে ধরা পড়া:** ক্যানসেলের পর বাকি পরিমাণের হিসাবে টেস্টের প্রত্যাশাটাই ভুল ছিল (কোড ঠিক ছিল); টেস্ট ঠিক করা হয়েছে।
