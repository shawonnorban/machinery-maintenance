# Yarn Procurement

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/02`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Yarn Purchase Indent | `/m/knitting/yarn-purchase-requisition` | 2 | 15 | 1 |
| 2 | Yarn Indent Approval List | `/m/knitting/yr-pr-approve-list` | 1 | 6 | 0 |
| 3 | Yarn Comparative Statement | `/m/knitting/comparative-statement` | 1 | 6 | 1 |
| 4 | Yarn Purchase Order | `/m/knitting/yarn-purchase-order` | 2 | 12 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Yarn Purchase Indent

`/m/knitting/yarn-purchase-requisition`

**ট্যাব: YR/PR List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| YR# | text |  | Search… |
| Company | select |  | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Status | select |  | Draft, Waiting Approval, Approved, Rejected, Ordered, Closed, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Yarn Purchase Requisition (YR/PR)
- বাটন/অ্যাকশন: YR/PR List 0, Yarn Purchase Requisition (YR/PR), Show, Add New, Reset

**ট্যাব: Yarn Purchase Requisition (YR/PR)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| YR# | text |  | Issued on save |
| YR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Required Date | date |  |  |
| Remarks | textarea |  |  |
- সেকশন: Yarn Purchase Requisition (YR/PR), Requisition, Raised Against, Yarn Required
- বাটন/অ্যাকশন: YR/PR List 0, Yarn Purchase Requisition (YR/PR), -- Select --, Add Yarn, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| YR# | text |  |  |
| YR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Required Date | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requisition that asks for no yarn asks for nothing.

#### Yarn Indent Approval List

`/m/knitting/yr-pr-approve-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| YR# | text |  | Search… |
| Company | select |  | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Decision Note | text |  | Why, in a line |
- সেকশন: YR/PR Approve List
- বাটন/অ্যাকশন: Show, Reset

#### Yarn Comparative Statement

`/m/knitting/comparative-statement`

**ট্যাব: Comparative Statement List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| CS No | text |  | Search… |
| Requisition No | select |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Status | select |  | Draft, Finalized, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Comparison of Price Quatation for Purchase Order
- বাটন/অ্যাকশন: Comparative Statement List 0, Show, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Search Requisition List | search |  |  |
| Existing comparison | select |  |  |
| CS No | text |  |  |
| CS Date* | date | ✔ |  |
| Requisition No* | select | ✔ |  |
| Date of Requisition | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A comparison with no yarn on it compares nothing.

#### Yarn Purchase Order

`/m/knitting/yarn-purchase-order`

**ট্যাব: Yarn Purchase Order (PO) List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| YR# | text |  | Requisition number |
| PO# | text |  | Search… |
| Inco Term | select |  | - Inco Term -, FOB — FOB, CIF — CIF, CFR — CFR, EXW — EXW, DDP — DDP |
| Payment Term | select |  | LC-SIGHT — LC-SIGHT, LC-30 — LC-30, LC-60 — LC-60, LC-120 — LC-120, TT-AFTER — TT-AFTER, TT-ADV — TT-ADV |
| Status | select |  | Draft, Placed, Part Received, Received, Closed, Cancelled |
| Method | select |  | L/C, T/T, Cash, Cheque |
- সেকশন: Yarn Purchase Order (PO) List
- বাটন/অ্যাকশন: Yarn Purchase Order (PO) List 0, Yarn Purchase Order (PO), -- Select Month --, -- Select --, Show, Add New, Reset

**ট্যাব: Yarn Purchase Order (PO)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| YR# | text |  | Requisition number |
| PO# | text |  | Search… |
| Inco Term | select |  | - Inco Term -, FOB — FOB, CIF — CIF, CFR — CFR, EXW — EXW, DDP — DDP |
| Payment Term | select |  | LC-SIGHT — LC-SIGHT, LC-30 — LC-30, LC-60 — LC-60, LC-120 — LC-120, TT-AFTER — TT-AFTER, TT-ADV — TT-ADV |
| Status | select |  | Draft, Placed, Part Received, Received, Closed, Cancelled |
| Method | select |  | L/C, T/T, Cash, Cheque |
- সেকশন: Yarn Purchase Order (PO) List
- বাটন/অ্যাকশন: Yarn Purchase Order (PO) List 0, Yarn Purchase Order (PO), -- Select Month --, -- Select --, Show, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Finalized Comparisons | search |  |  |
| Existing purchase order | select |  |  |
| PO No | text |  |  |
| PO Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Comparative Statement | select |  |  |
| YR# | select |  |  |
| Buyer A/C | select |  |  |
| Job# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Source* | select | ✔ | Select…, Local, Import |
| Method* | select | ✔ | Select…, L/C, T/T, Cash, Cheque |
| Inco Term | select |  | - Inco Term -, FOB — FOB, CIF — CIF, CFR — CFR, EXW — EXW, DDP — DDP |
| Payment Term | select |  | LC-SIGHT — LC-SIGHT, LC-30 — LC-30, LC-60 — LC-60, LC-120 — LC-120, TT-AFTER — TT-AFTER, TT-ADV — TT-ADV |
| Ship Mode | select |  | SEA — SEA, AIR — AIR, ROAD — ROAD |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Conv. Rate | number |  |  |
| Delivery Date | date |  |  |
| Shipment Date | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A purchase order that buys nothing orders nothing.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/Knitting`, নতুন টেবিল `knit_yarn_` নামে।
- **Yarn Item:** Merchandise-এর Fiber Composition master (`fiber_compositions`, যেমন "100% Cotton", "60% Cotton 40% Polyester")।
  - Brickland-এর demo-তে Yarn Item তালিকা খালি ছিল (Category "Yarn" বাছলেও)। আমাদের কোথাও আলাদা yarn item master নেই, তাই এটাই সবচেয়ে কাছের master।
- **15/01 থেকে নেওয়া:** Count, Yarn Count Master থেকে (`knit_yarn_counts`)।
- **অন্যান্য master:**
  - UOM আসে UOM master থেকে (`UomCodes`)।
  - Store আসে `stores` থেকে।
  - Spinner/Supplier আসে Material Procurement-এর supplier master (`mp_suppliers`) থেকে। Brickland-এর তালিকাও একই SUP- কোডের।
  - Currency আসে Settings-এর currencies থেকে।
  - Buyer আর Style/Order# আসে Merchandise থেকে।
- **Color:** Brickland-এর নিজস্ব ২১টা রঙের তালিকা (Average Colour … Yarn Dyed), constant হিসেবে।
- **Inco Term, Payment Term, Ship Mode:** Brickland-এর তালিকা হুবহু (FOB/CIF/CFR/EXW/DDP; LC-SIGHT … TT-ADV; SEA/AIR/ROAD)।
- **ছাঁচ:** Merchandise-এর Accessories Requisition/PO (06/06)। হেডার আর লাইন একসাথে সেভ, মাস ধরে নম্বর, "Existing …" বাছলে ফর্মে লোড হয়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_02_000100_create_knitting_yarn_procurement`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_requisitions` | requisition_no (YR-2026120001), requisition_date, store_id, buyer_id, order_id, required_date, remarks, status, submitted_at, decision_note, decided_at, decided_by |
| `knit_yarn_requisition_lines` | composition_id, yarn_count_id, colour, brand, uom, qty_local, qty_import, knit_start_date, ship_date, booking_price, remarks |
| `knit_yarn_comparisons` | cs_no (YCS-…), cs_date, requisition_id, requisition_date (কপি), remarks, status, finalized_at |
| `knit_yarn_comparison_lines` | sl_no, yarn ফিল্ড, qty_local, qty_import, present_stock, pipeline, তারিখ, booking_price, buyer_id, supplier_id (Finalized With) |
| `knit_yarn_offers` | sl_no, source (LOCAL/IMPORT), supplier_id, price, currency_id, conf_qty, lead_days, remarks |
| `knit_yarn_pos` | po_no (YPO-…), po_date, supplier_id, store_id, comparison_id, requisition_id, buyer_id, order_id, source, method, inco_term, payment_term, ship_mode, currency_id, conv_rate, delivery_date, shipment_date, remarks, status, placed_at |
| `knit_yarn_po_lines` | yarn ফিল্ড, lot_no, uom, qty, rate, received_qty (স্টোর মিলিয়ে রাখবে) |

## ৫. Workflow / Status ও Business Rules

- **Yarn Purchase Indent:**
  - **হেডার আর লাইন একসাথে সেভ হয়।** নম্বর মাস ধরে: YR-2026120001।
  - **হেডার:** YR Date আর Store বাধ্যতামূলক; Raised Against-এ Buyer, Style/Order#, Required Date।
  - **লাইন:** Yarn Item বা Count অন্তত একটা লাগে, UOM লাগে, আর Local + Import শূন্যের বেশি হতে হয়। Total = Local + Import, নিজে থেকে হিসাব হয়।
  - লাইন না দিলে Brickland-এর মেসেজ আসে: "A requisition that asks for no yarn asks for nothing."।
  - **অবস্থার ধারা:** Draft → (রেজিস্টার থেকে Submit) Waiting Approval → Approved / Rejected → Ordered (PO Place হলে) → Closed। Draft বা Rejected থেকে Cancel করা যায়।
  - শুধু Draft আর Rejected ফর্মে বদলানো বা মোছা যায়। জমা পড়ার পর ফর্ম আর নেয় না।
  - **রেজিস্টার:** YR#, Buyer, Status আর তারিখ দিয়ে খোঁজা যায়। কিছু না দিলে সাম্প্রতিকগুলো দেখায়।
- **Yarn Indent Approval List:**
  - শুধু Waiting Approval দেখায়, Required Date-এর ক্রমে।
  - টিক দিয়ে Decision Note লিখে Approve বা Reject করা যায়।
  - কারণ ছাড়া Reject আটকায় ("A rejection with no reason is one nobody can answer.")।
  - Reject হলে ডেস্কে ফেরত যায়; নোটটা সেখানে দেখা যায়, আবার লিখে জমা দেওয়া যায়।
- **Yarn Comparative Statement:**
  - একই স্ক্রিনে List আর ডেস্ক, রিলোড ছাড়া।
  - বাঁয়ে Approved/Ordered requisition-এর তালিকা। Compare This চাপলে Details Data গ্রিড ভরে: লাইনগুলো SL # ১, ২, ৩ … হিসেবে, সাথে Present Stock আর L/C Open Pipeline।
  - **Pipeline** = placed (Placed/Part Received) PO-তে একই Yarn Item ও Count-এর অর্ডার − গৃহীত।
  - **Qty in Kg** = Total − Present Stock − Pipeline (শূন্যের নিচে নয়), মানে আসলে কত অর্ডার করার মতো।
  - **SL #:** খালি রাখলে পরের খালি নম্বর বসে। একই SL দুবার চলে না।
  - **Offers:** প্রতিটা লাইনে প্রতিটা spinner প্রতিটা দামের ঘরে (Local/Import) এক সারি। Offer-এর SL # কোনো লাইনে না থাকলে আটকায়। প্রতিটা SL-এর সর্বনিম্ন দামে "Lowest" চিহ্ন বসে।
  - লাইন না দিলে Brickland-এর মেসেজ আসে: "A comparison with no yarn on it compares nothing."।
  - **Finalize (list থেকে):** অন্তত একটা offer আর প্রতিটা লাইনে Finalized With spinner লাগে। চূড়ান্ত শিট আর সম্পাদনা নেয় না। Draft থেকে Cancel করা যায়।
- **Yarn Purchase Order:**
  - **ডেস্কের বাঁয়ে Search Finalized Comparisons:** প্রতিটা চূড়ান্ত CS-এর প্রতিটা spinner এক সারিতে, সাথে Items, Qty (Kg) আর Value।
  - **Order This:** সেই spinner-এর লাইন আসে, Qty = অর্ডার করার মতো পরিমাণ, Rate = সেই spinner-এর দাম (দুই ঘরে দাম দিলে সস্তাটা)। সাথে requisition, buyer, store, source আর currency (সাথে conv. rate) বসে।
  - CS ছাড়া সরাসরি অনুমোদিত requisition বেছেও PO তোলা যায়।
  - **Amount** = Qty × Rate। নম্বর মাস ধরে: YPO-2026120001।
  - লাইন না দিলে Brickland-এর মেসেজ আসে: "A purchase order that buys nothing orders nothing."।
  - Supplier, Store, Source, Method আর Currency বাধ্যতামূলক।
  - **অবস্থার ধারা:** Draft → (list থেকে Place) Placed → (15/03 Yarn Store-এর রিসিট থেকে) Part Received / Received → Closed। Draft থেকে Cancel করা যায়।
  - Place হলে requisition Ordered হয়, আর placed PO-র পরিমাণ পরের comparison-এর pipeline-এ ধরা পড়ে। Place-এর পর ফর্ম আর বদলায় না।
  - **রেজিস্টার:** YR#, PO#, Month, Party, Inco Term, Payment Term, Status আর Method দিয়ে খোঁজা যায়।

## ৬. API ও পারমিশন

- **Requisition:**
  - `GET /knitting/yarn/requisitions/options`
  - `GET|POST /knitting/yarn/requisitions`, `GET|PUT|DELETE /knitting/yarn/requisitions/{YR#}`
  - `POST …/{YR#}/{submit|cancel|close}`
- **Approval:** `GET /knitting/yarn/approvals`, `POST /knitting/yarn/approvals/decide`
- **Comparison:**
  - `GET /knitting/yarn/comparisons/options`, `GET …/comparisons/source/{YR#}`
  - `GET|POST /knitting/yarn/comparisons`, `GET|PUT|DELETE …/{CS#}`
  - `POST …/{CS#}/{finalize|cancel}`
- **PO:**
  - `GET /knitting/yarn/orders/options`, `GET …/orders/source?cs=&supplier=` বা `?requisition=`
  - `GET|POST /knitting/yarn/orders`, `GET|PUT|DELETE …/{PO#}`
  - `POST …/{PO#}/{place|close|cancel}`
- সব ডকুমেন্ট URL-এ যায় নম্বর দিয়ে (`?ref=YR-2026120001`), কোনো ULID নয়।
- **পারমিশন:** নতুন `knitting.yarn.view`, `knitting.yarn.manage` আর `knitting.yarn.approve`। তিনটাই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF কোনোটাই পায় না।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Knit Production → Yarn Procurement ফোল্ডার (Master Data-র পরে, Brickland-এর মতো), চারটা স্ক্রিন।
- **কম্পোনেন্ট** (`components/knitting/`):
  - `yarn-common.jsx`: কার্ড আর ফিল্ড, status chip, yarn কলামগুলো, Copy/Remove, আর `PSelect` (ফাঁকা select-এ Brickland-এর লেখা যেমন "-- Select Store --")।
  - `yarn-requisition.jsx`: RequisitionList, RequisitionEntry, ApprovalList।
  - `yarn-comparison.jsx`: ComparisonScreen।
  - `yarn-po.jsx`: PoList, PoEntry।
- **ট্যাব:**
  - Indent: YR/PR List আর Yarn Purchase Requisition (YR/PR)।
  - CS: শুধু Comparative Statement List; ডেস্ক একই জায়গায় খোলে।
  - PO: PO List আর Yarn Purchase Order (PO)।
- Brickland-এর এই চারটা স্ক্রিনে Print, PDF বা Excel বোতাম নেই, তাই এখানেও রাখা হয়নি।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_02_000100_create_knitting_yarn_procurement.php`
- `app/Modules/Knitting/Models/YarnRequisition.php`, `YarnRequisitionLine.php`, `YarnComparison.php`, `YarnComparisonLine.php`, `YarnOffer.php`, `YarnPurchaseOrder.php`, `YarnPurchaseOrderLine.php`
- `app/Modules/Knitting/Support/YarnProcurement.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnRequisitionApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnComparisonApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnPurchaseOrderApiController.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/yarn-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js` (`yarnAction`)
- `frontend/src/components/knitting/yarn-common.jsx`
- `frontend/src/components/knitting/yarn-requisition.jsx`
- `frontend/src/components/knitting/yarn-comparison.jsx`
- `frontend/src/components/knitting/yarn-po.jsx`

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php` (`yarn.*`)

**Test**
- `tests/Feature/Knitting/KnittingYarnProcurementTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`KnittingYarnProcurementTest` ১/১ (৬২ assertion):**
  - **Indent:** লাইন ছাড়া Brickland-এর মেসেজ। পরিমাণ ছাড়া লাইন আটকায়। YR-2026100001, মোট ৭০০।
  - **Approval:** Submit-এর পর ফর্ম আটকায়। কারণ ছাড়া Reject আটকায়। Reject হলে নোটসহ ফেরত আসে। আবার লিখে জমা দিলে Approve হয়।
  - **CS:** SL ১ আর ২ ভরে। লাইন ছাড়া মেসেজ। ভুল SL-এর offer আটকায়। YCS-2026100001। Spinner ছাড়া Finalize আটকায় (SL # 1, 2)। Finalize হয়। তারপর সম্পাদনা আটকায়।
  - **PO:** প্রতিটা spinner এক সারি; ৭০০ × ৩.০৫ (Import-এর সস্তা দাম) = ২,১৩৫। লাইন ছাড়া মেসেজ। Method ছাড়া আটকায়। YPO-2026100001। Place হলে requisition Ordered হয়। তারপর সম্পাদনা আটকায়। রেজিস্টারে খোঁজা যায়।
  - **Pipeline:** placed PO-র ৭০০ পরের comparison-এ pipeline হিসেবে আসে।
  - **পারমিশন:** Line Chief ঢুকতে পারে না।
- **ব্রাউজার (dev, ui_yp1–ui_yp5, ডাটা রাখা আছে):**
  - **Indent YR-2026120001:** Yarn store (DHK-YRN), LPP, দুই লাইন। 100% Cotton 30/1 Natural ৫০০ + ২০০, আর 60/40 CVC 24/1 Grey ৩০০; মোট ১,০০০।
  - Submit হয়েছে; তারপর Approval List-এ "Within booking budget" নোটসহ Approve।
  - **CS YCS-2026100001:** Square Textiles (৩.২০, ৩.১০) আর Coats (৩.৪৫)। Lowest চিহ্ন বসেছে। দুই লাইনই Square Textiles-এর সাথে Finalized।
  - **PO YPO-2026120001:** Order This-এ ৭০০ × ৩.২০ + ৩০০ × ৩.১০ = ৩,১৭০। L/C, CFR, LC-60, SEA, USD ১১৯.৫, Lot SQ-3011।
  - PO Placed হয়েছে, YR Ordered।
  - সব ফাঁকা select-এ Brickland-এর লেখা দেখায়।

## ১০. খোলা প্রশ্ন

- **Yarn Item-এর master:** Brickland-এর demo-তে Yarn Item তালিকা খালি, আর কোনো yarn item master স্ক্রিনও নেই। এখানে Merchandise-এর Fiber Composition ব্যবহার করা হয়েছে। আলাদা yarn item master দরকার হলে বলবেন।
- **Category:** Brickland-এর Indent-এ "Category" (store item category, যেমন Yarn) আছে; এটা শুধু Yarn Item তালিকা ছাঁকে। এখানে Yarn Item সবসময় সুতা, তাই রাখা হয়নি।
- **Brand:** Brickland-এ খালি select; কোথাও brand-এর master নেই। এখানে লেখার ঘর।
- **Present Stock:** Yarn Store (15/03) তৈরি হলে সেখান থেকে আসবে। এখন ০, কিন্তু CS-এ হাতে বদলানো যায়।
- **Received:** PO-র received কলাম আর Part Received/Received অবস্থা 15/03-এর Daily Yarn Receipt মিলিয়ে রাখবে।
- **Brickland-এ ডাটা দিয়ে প্রবাহ চালানো যায়নি:** demo-তে yarn item না থাকায় Indent সেভ করা যায়নি। তাই approval, CS আর PO-র ধারা Brickland-এর স্ক্রিনের নোট (যেমন "Submitting it from the register…", "Finalising it — from the list…", "Placing it from the list…") আর কলাম দেখে বানানো হয়েছে।
- **List-এর কলাম:** Brickland-এ কোনো রেকর্ড না থাকায় register-এর কলাম দেখা যায়নি। কলাম ঠিক করা হয়েছে ফর্মের ফিল্ড থেকে।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৭):**
- চারটা স্ক্রিনই তৈরি।
- **Brickland প্রোব (yp01–yp07):** চারটা স্ক্রিনের ফর্ম, গ্রিডের কলাম, অপশন (রং, UOM, শর্ত, spinner), বোতাম আর নোট তোলা হয়েছে।
