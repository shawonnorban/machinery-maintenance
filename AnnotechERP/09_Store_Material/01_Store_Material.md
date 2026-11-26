# Store & Material

> **স্ট্যাটাস:** ✅ সম্পন্ন (৯টা স্ক্রিন চালু)
> **মডিউল:** [Store & Material](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `09/01`

**⬅ ফিরে যান:** [Store & Material](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dashboard | `/m/inventory/store-dashboard` | 1 | 6 | 0 |
| 2 | In-House / GRN | `/m/inventory/in-house-grn` | 2 | 13 | 0 |
| 3 | Fabric Warehouse | `/m/inventory/fabric-warehouse` | 1 | 3 | 0 |
| 4 | Material Out | `/m/inventory/material-out` | 1 | 9 | 0 |
| 5 | Material Return | `/m/inventory/material-return` | 1 | 9 | 0 |
| 6 | Stock Adjustment | `/m/inventory/stock-adjustment` | 1 | 7 | 0 |
| 7 | Stock Ledger | `/m/inventory/stock-ledger` | 1 | 3 | 0 |
| 8 | Traceability | `/m/inventory/traceability` | 1 | 1 | 0 |
| 9 | Reports | `/m/inventory/store-reports` | 1 | 5 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dashboard

`/m/inventory/store-dashboard`

**ভিউ**
> 01 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | All, LPP |
| PO Number | select |  | All, 5871, 11528573, 11542029 |
| Style | select |  | All, 531, 867MI-05X, 927 |
| Material | select |  | All, Cotton Jersey (White), Polyester (Black), RAB (WHITE) |
| Date From | date |  |  |
| Date To | date |  |  |
- গ্রিড কলাম: DATE, PR NO, GRN NO, REFERENCE, IN QTY, OUT QTY, RETURN QTY, BALANCE
- সেকশন: Material Movement, Stock by Material Type
- বাটন/অ্যাকশন: Search, Reset

#### In-House / GRN

`/m/inventory/in-house-grn`

**ট্যাব: Against PR**
> 02 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Purchase Requisition No* | text | ✔ | PR-2026-00025 |
- সেকশন: In-House / Goods Receive Note (GRN)
- বাটন/অ্যাকশন: Against PR, Direct Receive (Fabric / Accessories), Search

**ট্যাব: Direct Receive (Fabric / Accessories)**
> 02 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | text |  |  |
| PO Number | text |  |  |
| Style | text |  |  |
| Order No | text |  |  |
| Supplier | text |  |  |
| Price Currency | select |  | Select…, BDT, USD |
| #1 Type | select |  | Select…, Fabric, Trims & Accessories |
| Material* | select | ✔ | Select material…, FAB-001 — Cotton Jersey (White), FAB-005 — Denim (Indigo), FAB-003 — Linen Blend (Natural), FAB-002 —  |
| Receive Qty* | text | ✔ | 0 |
| Unit Price (BDT) | text |  | Optional |
| Received By | text |  | Employee ID / Card / Proxy No. |
| Remarks | text |  |  |
- সেকশন: Direct Receive — Fabric / Trims & Accessories, Stock For (optional — leave blank for general stock), Materials (0 ready to stock)
- বাটন/অ্যাকশন: Against PR, Direct Receive (Fabric / Accessories), Add Fabric, Add Trims / Accessories, Confirm Receive, Reset

#### Fabric Warehouse

`/m/inventory/fabric-warehouse`

**ভিউ**
> 03 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | All, LPP |
| Style | select |  | All, 531, 867MI-05X, 927 |
| Material | select |  | All, Cotton Jersey (White), Polyester (Black), RAB (WHITE) |
- গ্রিড কলাম: ROLL NO, MATERIAL, COLOR, DYE LOT, GSM, WIDTH, UNIT, RECV QTY, OUT QTY, AVAILABLE
- সেকশন: Fabric Warehouse — Stock View
- বাটন/অ্যাকশন: Search, Reset, Stock List, Roll Wise, Color Wise, Dye Lot Wise, Movement

#### Material Out

`/m/inventory/material-out`

**ভিউ**
> 05 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Material Type* | select | ✔ | All, Fabric, Trims & Accessories |
| Material* | select | ✔ | Select…, Cotton Jersey (White) — LPP / 927, Polyester (Black) — LPP / 531, RAB (WHITE) — LPP / 867MI-05X |
| Out Qty* | text | ✔ |  |
| Unit | text |  |  |
| Floor* | select | ✔ | Select…, Cuting Floor, Finishing, SWEING |
| Section* | select | ✔ | Select… |
| Line* | select | ✔ | Select… |
| Receiver* | text | ✔ | Employee ID / Card / Proxy No. |
| Remarks | text |  |  |
- সেকশন: Material Out MO-2026-00004
- বাটন/অ্যাকশন: Select…, Confirm Material Out, Reset

#### Material Return

`/m/inventory/material-return`

**ভিউ**
> 06 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Material Out No* | text | ✔ | MO-2026-00015 |
| Return Qty* | text | ✔ |  |
| Return Reason* | select | ✔ | Select…, Excess Material, Production Completed, Wrong Issue, Line Cancelled, Replacement, Damaged Return, Other |
| Floor | text |  |  |
| Section | text |  |  |
| Line* | text | ✔ |  |
| Returned By | text |  | Employee ID / Card / Proxy No. |
| Received By | text |  | Employee ID / Card / Proxy No. |
| Remarks | text |  |  |
- সেকশন: Material Return MR-2026-00002
- বাটন/অ্যাকশন: Confirm Return, Reset

#### Stock Adjustment

`/m/inventory/stock-adjustment`

**ভিউ**
> 07 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Material* | select | ✔ | Select…, Cotton Jersey (White) — LPP / 927, Polyester (Black) — LPP / 531, RAB (WHITE) — LPP / 867MI-05X |
| System Qty | text |  |  |
| Physical Qty* | text | ✔ |  |
| Reason* | select | ✔ | Select…, Physical Count Difference, Damaged / Spoiled, Lost / Missing, Found in Count, Measurement Difference, Other |
| Remarks | textarea |  |  |
| Material | select |  | Select…, Cotton Jersey, Polyester, RAB |
| Minimum Qty | text |  |  |
- গ্রিড কলাম: ADJ NO, DATE, MATERIAL, BUYER / STYLE, SYSTEM, PHYSICAL, DIFFERENCE, REASON, REQUESTED / APPROVED, STATUS
- সেকশন: Stock Adjustment SA-2026-00001, Adjustments, Minimum Stock Level
- বাটন/অ্যাকশন: Reset, Submit for Approval, Save

#### Stock Ledger

`/m/inventory/stock-ledger`

**ভিউ**
> 08 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Material | select |  | All, Cotton Jersey (White), Polyester (Black), RAB (WHITE) |
| Date From | date |  |  |
| Date To | date |  |  |
- গ্রিড কলাম: DATE, REFERENCE, TYPE, IN QTY, OUT QTY, RETURN QTY, BALANCE
- সেকশন: Stock Ledger
- বাটন/অ্যাকশন: Search, Reset

#### Traceability

`/m/inventory/traceability`

**ভিউ**
> 09 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PR | text |  | PR-2026-00025 |
- সেকশন: Material Traceability (Full Chain)
- বাটন/অ্যাকশন: Trace by PR, Trace by GRN, Trace by Material Out, Trace by Roll No, Search

#### Reports

`/m/inventory/store-reports`

**ভিউ**
> 10 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | All, LPP |
| Style | select |  | All, 531, 867MI-05X, 927 |
| Material | select |  | All, Cotton Jersey (White), Polyester (Black), RAB (WHITE) |
| Date From | date |  |  |
| Date To | date |  |  |
- গ্রিড কলাম: MATERIAL, UNIT, PR QTY, IN-HOUSE, OUT QTY, RETURN QTY, CURRENT STOCK
- সেকশন: Reports, Stock Summary Report
- বাটন/অ্যাকশন: View, Export Excel, Export PDF, Print
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **08 Material Procurement:**
  - অনুমোদিত PR আর তার লাইন (`mp_requisitions`, `mp_requisition_lines`, অনুমোদিত সরবরাহকারী আর কোটেশন)।
  - Fabric / Accessories master (`mp_items`)।
  - `Procurement::nextNumber` (GRN-2026-00001, MO-, MR-, SA-)।
- **06 Merchandise:** order (`merch_orders`: buyer, PO, style, order নং)। PR যে order-এর, তার stock সেই buyer / PO / style / order-এর নামে থাকে।
- **24/03 HR Employee:** Received By, Receiver, Returned By খোঁজা হয় কর্মীর code বা card / proxy নং দিয়ে (`hr_employees`)। ব্যবহারকারীর সিদ্ধান্ত ছিল আগে HR-এর কর্মী-তালিকা বানানো, সেটা হয়ে গেছে।
- **Company Management:** floor, section আর production line।
- **File attachment** (`StoreFileAttachment`, FileApiController-এ `sm_grn`), আর route handler-এর ধাঁচ: ১ MB-র বড় ফাইল server action দিয়ে পাঠানো যায় না।
- **OpenSpout:** Reports-এর Excel export।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_21_000100_create_store_material_tables`, মডিউল `app/Modules/StoreMaterial`:

| টেবিল | কী রাখে |
|---|---|
| `sm_stocks` | material (mp_items) + buyer / PO / style / order; সব ফাঁকা হলে general stock। একক থাকে, পরিমাণ রাখা হয় না, ledger থেকে হিসাব হয় |
| `sm_ledger` | প্রতিটা চলাচল: GRN, MATERIAL_OUT, MATERIAL_RETURN, ADJUSTMENT, REVERSAL। ঘর in / out / return, reference আর source। entry শুধু যোগ করা যায়; ভুল শোধরাতে reversal দিতে হয় |
| `sm_grns`, `sm_grn_lines` | GRN, kind PR বা DIRECT; status CONFIRMED বা REVERSED। Reverse-এর লোক, সময় আর কারণ থাকে। লাইনে PR-এর লাইন, bucket, kind (FABRIC/TRIMS = গুদাম), পরিমাণ আর দাম |
| `sm_material_outs` | MO: bucket, purpose, পরিমাণ, floor / section / line, receiver (কর্মী) |
| `sm_material_returns` | MR: MO, পরিমাণ, reason, line, returned by আর received by |
| `sm_adjustments` | SA: system আর physical পরিমাণ, reason; status PENDING / APPROVED / REJECTED, সাথে যিনি চেয়েছেন আর যিনি সিদ্ধান্ত দিয়েছেন |
| `sm_min_levels` | প্রতিটা material-এর ন্যূনতম stock |
| `sm_rolls` | (মাইগ্রেশন `2026_12_22_000100_create_sm_rolls`) GRN-এর fabric লাইনের প্রতিটা রোল: roll no, dye lot, GSM, width, একক আর পরিমাণ। GRN, লাইন, bucket আর material-এর সাথে যুক্ত |
| `sm_roll_moves` | রোলের প্রতিটা চলাচল: GRN, MATERIAL_OUT, MATERIAL_RETURN, REVERSAL; in / out / return। এখান থেকেই রোলের Available হিসাব হয়। `sm_ledger`-এর মতোই শুধু যোগ করা যায় |
| `sm_material_returns.roll_id` | যে রোলে ফেরত এল (MO রোল ধরে দেওয়া হলে) |

## ৫. Workflow / Status ও Business Rules

### In-House / GRN

- **Against PR:**
  - **নিয়ম:** শুধু **অনুমোদিত PR**।
    - "No Purchase Requisition PR-… was found."
    - "PR-… is submitted — the store can receive only against an approved PR."
  - **PR পেলে আসে:**
    - PR-এর তথ্য: merchandiser, buyer, PO, style, order, PR date, supplier, department, required date, purchase ref (QT নং)।
    - প্রতিটা অনুমোদিত লাইন: PR Qty, Received, Pending, Receive Qty।
  - **ঘরের নিয়ম:**
    - Pending-এর বেশি লিখলে ঘরের নিচে "Max n" দেখায়, আর Confirm বন্ধ থাকে।
    - server-এও আটকায়: "Cotton Jersey: no more than the pending 100 KG can be received."
    - একটারও পরিমাণ না দিলে: "Enter the receive quantity of at least one item."
  - **নিশ্চিতকরণ:** "Confirm In-House?" dialog, Brickland-এর লেখায়। তারপর GRN-2026-NNNNN।
  - **কোথায় যায়:** stock আসে order-এর buyer / PO / style / order-এর নামে। গুদাম নির্ভর করে লাইনের ধরনের ওপর: Fabric হলে Fabric Warehouse, Trims হলে Trims & Accessories Warehouse।
  - **PR-এর Pending:** নিশ্চিত GRN থেকে হিসাব হয়।
- **Direct Receive:**
  - **কার জন্য stock:** Buyer / PO / Style / Order (ঐচ্ছিক; ফাঁকা হলে general stock)।
  - **হেডার:** Supplier (লেখা), Price Currency (BDT / USD)।
  - **লাইন:** ধরন, Material (master থেকে), Receive Qty, Unit Price। "Add Fabric" / "Add Trims / Accessories" দিয়ে লাইন যোগ হয়।
  - "n ready to stock" দেখায়, আর নিশ্চিতকরণের dialog আসে।
- **GRN-এর রূপ** (`?grn=GRN-2026-00001`; URL-এ id নয়, GRN নং থাকে):
  - দুই কলামে তথ্য: বাঁয়ে GRN আর PR / buyer, ডানে supplier, warehouse, received by, confirmed by আর remarks।
  - Confirmed বা Reversed ব্যাজ; বাটন Print, Reverse আর New GRN।
  - **GRN Items:** SL, Material, Unit, PR Qty, Prev Recv, Current Recv, Total Recv, Pending, Warehouse।
  - **Attachments:** Document Name আর File (PDF বা ছবি, ≤ ৫ MB)।
- **রোল আর ডাই লট** (ব্যবহারকারীর নির্দেশে, ২০২৬-১০-২৪; Brickland-এ এই ঘর নেই, ওদের Roll Wise ফাঁকা থাকে):
  - Against PR আর Direct দুই জায়গাতেই fabric লাইনে পরিমাণ দিলে নিচে "Roll-wise" অংশ খোলে। এটা ঐচ্ছিক; রোল না দিলে লাইন আগের মতো একসাথে নেওয়া হয়।
  - **প্রতিটা রোলে:** Roll No আর Qty বাধ্যতামূলক; Dye Lot, GSM আর Width ঐচ্ছিক। "Add Roll" দিয়ে সারি যোগ হয়। নিচে "3 roll(s) · 30 of 30 KG" দেখায়, না মিললে লাল হয় আর Confirm বন্ধ থাকে।
  - **server-এর নিয়ম** (বার্তা লাইনের নিজের key-তে, `lines.{line}.rolls`):
    - রোলের যোগফল লাইনের সমান হতে হবে: "The rolls add up to 30 KG; the line receives 40 KG."
    - "Roll 2: enter the roll number." / "Roll R-02: enter its quantity."
    - একই নম্বর দুবার দিলে: "Roll R-01 is entered twice."
    - অন্য চালু GRN-এ একই নম্বর থাকলে: "Roll R-01 is already in stock on GRN-…"। Reverse হওয়া GRN-এর নম্বর আবার নেওয়া যায়।
    - রোল শুধু fabric-এ: "Rolls are kept for fabric only."
  - Roll No বড় হাতের অক্ষরে রাখা হয় (r-01 → R-01)।
  - GRN Items-এ লাইনের নিচে "Rolls (3)" আর প্রতিটা রোলের chip: নম্বর · Lot · GSM · Width · পরিমাণ।
- **Reverse:**
  - শুধু Store Manager (`storematerial.manage`), কারণ বাধ্যতামূলক।
  - GRN-এর পরিমাণ stock থেকে বেরিয়ে যায় (REVERSAL entry), আর PR-এর Pending ফিরে আসে।
  - **আটকায়:** এর কিছু আগেই দেওয়া হয়ে থাকলে ("… cannot be reversed — some of it has already been issued."), আর দ্বিতীয়বার Reverse করলে। GRN-এর কোনো রোল থেকে কিছু দেওয়া হলেও একই বার্তায় আটকায়, bucket-এ যথেষ্ট থাকলেও।
  - Reverse হলে রোলগুলোও REVERSAL পায়, আর তালিকা থেকে সরে যায়।

### Material Out

- **ঘরের ক্রম:**
  - Material Type (All / Fabric / Trims) দিয়ে Material-এর তালিকা ছোট হয়। তালিকায় শুধু stock আছে এমন bucket, যেমন "Pique 220 GSM — LPP / SS27-TEE01"। পাশে Available Stock আর Unit।
  - Purpose: Brickland-এর ১১টা।
  - Floor বাছলে Section, Section বাছলে Line।
  - Receiver বাধ্যতামূলক, কর্মী খুঁজে নাম দেখায়।
- **বার্তা:** ৭টা ঘরেরই "… is required."
- **পরিমাণ:** stock-এর বেশি হলে "No more than the 40 KG in stock can go out."
- **অচেনা কর্মী:** "No employee with ID or card no. … was found."
- **রোল ধরে দেওয়া:** বাছা fabric bucket-এ রোল থাকলে নিচে "Rolls in this stock" টেবিল আসে: Roll No, Dye Lot, GSM, Width, Available আর Out Qty।
  - কোনো রোলে পরিমাণ দিলে Out Qty নিজে রোলগুলোর যোগফল হয়ে যায় আর বন্ধ থাকে।
  - রোলের চেয়ে বেশি হলে: "Roll R-02 has only 15 KG."
  - যোগফল না মিললে: "The rolls add up to 15 KG; 20 KG is going out."
  - অন্য bucket-এর রোল হলে: "A roll picked is not in this stock."
  - রোল না দিলে আগের মতোই bucket থেকে দেওয়া হয়।

### Material Return

- **MO নং দিলে আসে:** Original Out Qty, Previously Returned, Eligible Return Qty, material, purpose, receiver, floor আর section; line আগে থেকে বসানো থাকে।
- **ঘর:** Return Qty, Reason (৭টা), Line, Returned By আর Received By (কর্মী)।
- **পরিমাণ:** ফেরতযোগ্যের বেশি হলে "No more than the eligible 30 KG can come back."
- **ফেরত যায়** একই bucket-এ (MATERIAL_RETURN)।
- **রোল:** MO রোল ধরে দেওয়া হলে Roll বাছা বাধ্যতামূলক ("Pick the roll it comes back on.")। তালিকায় দেখায় "DL-R003 · Lot LOT-B · may return 3 KG"। রোলটা যা নিয়েছিল তার বেশি ফেরত নেওয়া যায় না: "Roll R-02 may take back no more than 5 KG."

### Stock Adjustment

- **ফর্ম:** Material (stock-এর bucket); System Qty নিজে বসে; Physical Qty; Difference রঙে দেখায় (বেশি হলে সবুজ, কম হলে লাল); Reason (৬টা); Remarks।
- **Submit for Approval:** PENDING হয়। গোনা আর সিস্টেম মিললে আটকায়।
- **Adjustments তালিকা:** Store Manager Approve বা Reject করেন। Approve হলে ADJUSTMENT entry পড়ে, বাড়তি হলে in, কম হলে out। এর মধ্যে stock গোনার চেয়েও কমে গেলে Approve আটকায়।
- **Minimum Stock Level:** material ধরে, Store Manager বসান। এর থেকে Low Stock Report হয়।

### Stock Ledger, Dashboard, Traceability, Reports

- **Stock Ledger:**
  - ঘর: Material আর তারিখের filter; Date, Reference, Type, In, Out, Return, Balance।
  - তারিখ দিলে আগের হিসাব "Opening balance" হিসেবে দেখায়।
- **Dashboard:**
  - **Filter:** Buyer → PO → Style → Material, তারিখ।
  - **৬টা tile:** PR Quantity (অনুমোদিত PR-এর অনুমোদিত লাইন), Total In-House (GRN বাদ reversal), Pending, Material Out, Material Return, Current Stock। একক মিশে থাকলে নিচে লেখে "Mixed units — pick a material for one unit"।
  - **Material Movement:** নতুনটা আগে, PR নং আর চলমান balance-সহ।
  - **Stock by Material Type:** donut চার্ট।
- **Traceability:**
  - **খোঁজার উপায়:** Trace by PR, GRN, Material Out বা Roll No। Roll No-তে বার্তা আসে "No roll … is recorded."।
  - **প্রতিটা material-এর শৃঙ্খল:** GRN → Warehouse → Material Out → Floor / Line আর Receiver → Return, তারপর Available Qty। GRN নং-এ ক্লিক করলে GRN খোলে।
- **Reports** (৮টা; একটা থেকে আরেকটায় যেতে পাতা রিলোড হয় না):
  - **তালিকা:** Stock Summary, GRN, Material Out, Material Return (Total সারিসহ), Stock Ledger (ADJ কলামসহ), Traceability, Fabric Roll, Low Stock।
  - **কলাম:** Brickland-এর হুবহু।
  - **Filter:** Buyer, Style, Material, তারিখ।
  - **View:** পাতায় টেবিল দেখায়।
  - **Export Excel:** server থেকে আসল XLSX।
  - **Export PDF:** server থেকে আসল PDF ফাইল (২০২৬-১০-২৪-এ যোগ হয়েছে, নিচে দেখুন)।
  - **Print:** browser-এর print।

## ৬. API ও পারমিশন

| অনুমতি | কাজ | Role |
|---|---|---|
| `storematerial.view` | সব স্ক্রিন দেখা | FACTORY_ADMIN, STORE_MANAGER, STOREKEEPER, FACTORY_MANAGER (Owner সব) |
| `storematerial.receive` | GRN আর attachment | FACTORY_ADMIN, STORE_MANAGER, STOREKEEPER |
| `storematerial.issue` | Material Out আর Return | একই |
| `storematerial.adjust` | Adjustment-এর অনুরোধ | একই |
| `storematerial.manage` | GRN Reverse, Adjustment-এর সিদ্ধান্ত, Minimum Stock | FACTORY_ADMIN, STORE_MANAGER |

| Method | Path |
|---|---|
| GET | `/store-material/filters`, `/dashboard`, `/fabric-warehouse?view=stock\|roll\|color\|lot\|movement`, `/trims-warehouse`, `/stock-ledger`, `/employee?id=` |
| GET | `/store-material/grns/options`, `/requisitions/{pr}`, `/grns/{grn}` |
| POST | `/store-material/grns/against-pr`, `/grns/direct`, `/grns/{grn}/reverse`, `/grns/{grn}/attachments` |
| GET / POST | `/store-material/issue-options`, `/material-outs`, `/material-outs/{mo}`, `/material-returns`, `/adjustments`, `/adjustments/{sa}/decide` |
| PUT | `/store-material/min-levels` |
| GET | `/store-material/traceability?by=PR\|GRN\|MO\|ROLL&q=`, `/reports/{report}`, `/reports/{report}/excel` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: **INVENTORY & STORES** → Store & Material (সবার আগে, Gate Pass আর General Store-এর ওপরে)। Brickland-এর মতোই ১০টা স্ক্রিন ফোল্ডার ছাড়া সরাসরি: Dashboard, In-House / GRN, Fabric Warehouse, Trims & Accessories, Material Out, Material Return, Stock Adjustment, Stock Ledger, Traceability, Reports।

- **সব স্ক্রিনে:**
  - filter-এর এক সারি, শেষে Search আর Reset; ফাঁকা select-এ "All"।
  - কর্মীর ঘরে খোঁজার বাটন, পাশে নাম ("50001001 (Mohammad Sayem)")।
  - ঘর বদলালে সেই ঘরের বার্তা মুছে যায়।
  - কোনো কাজে পাতা রিলোড হয় না।
- **রোলের ভিউ:**
  - **Fabric Warehouse:**
    - Roll Wise-এ প্রতিটা রোল: Recv, Out, Available। GSM আর Width রোলে না থাকলে master থেকে নেওয়া হয়।
    - Dye Lot Wise-এ material আর lot ধরে রোল-সংখ্যা ও পরিমাণ।
    - Color Wise-এ রোল-সংখ্যা।
    - Stock List-এ Roll No আর Dye Lot-এর তালিকা ("R-01, R-02, R-03 +1")।
  - **Trace by Roll No:** রোলটার নিজের শৃঙ্খল আসে, ওপরে Roll, Dye Lot, GSM আর Width। শৃঙ্খলে থাকে ওর GRN, যে MO-গুলো ওই রোল থেকে কত নিয়েছে, ওই রোলে কী ফেরত এসেছে, আর এখন কত আছে। অচেনা নম্বরে বার্তা: "No roll … is recorded."
  - **Fabric Roll Report:** Roll No, GRN, buyer, style, fabric, colour, dye lot, GSM, width, received, out, returned, available। Status: In stock, Partly out, All out বা Reversed।
  - **Material Out Report:** Roll কলামে MO-র রোলগুলো ("DL-R001, DL-R003")।
- **route handler:** `/api/store-material/grn-attachment/[grn]` (upload) আর `/api/store-material/report-excel/[report]` (download)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/StoreMaterial/Database/Migrations/2026_12_21_000100_create_store_material_tables.php`, `2026_12_22_000100_create_sm_rolls.php`, `Models/{Stock,Grn,GrnLine,MaterialOut,MaterialReturn,Adjustment,MinLevel,LedgerEntry,Roll,RollMove}.php`, `Support/StoreBook.php`, `Http/Controllers/Api/{GrnApiController,StockApiController,IssueApiController,TraceApiController}.php`, `Routes/api.php` |
| শেয়ার করা | `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (`sm_grn`) |
| পারমিশন / মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`storematerial.*`), `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` (`STORE_MATERIAL` মডিউল আর ১০টা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/store-material/[screen]/{page,screens,actions}.js`, `frontend/src/app/api/store-material/grn-attachment/[grn]/route.js`, `report-excel/[report]/route.js`, `frontend/src/components/storematerial/{common,dashboard,grn,warehouse,issue,trace}.jsx` |
| ভাষা | `lang/{en,bn}/storematerial.php` |
| টেস্ট | `tests/Feature/StoreMaterial/StoreGrnTest.php`, `StoreIssueTest.php`, `StoreRollTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **রোল আর ডাই লট (হয়ে গেছে, ২০২৬-১০-২৪):** Brickland-এর In-House স্ক্রিনে রোল ধরে নেওয়ার ঘর নেই, ওদের Roll Wise আর Dye Lot ফাঁকা থাকে ("No rolls recorded")। ব্যবহারকারীর নির্দেশে এখানে রোল যোগ হয়েছে। নিয়ম ৫-এ, ফাইল ওপরের টেবিলে।
- **PDF library (হয়ে গেছে, ২০২৬-১০-২৪):** ব্যবহারকারীর নির্দেশে mPDF (`mpdf/mpdf` ^8.3) যোগ হয়েছে। তার ওপর শেয়ার করা library `App\Shared\Pdf\TablePdf` বানানো হয়েছে।
  - **রূপ:** landscape A4; ওপরে শিরোনাম, কোম্পানি আর filter-এর লাইন; প্রতি পাতায় হেডার আবার আসে; Total সারি ঐচ্ছিক।
  - **ফুটার:** কোম্পানির timezone-এ তৈরির সময়, সাথে "পাতা n / N"।
  - **বড় report:** সারি ৪০০টা করে খণ্ডে লেখা হয়; ২০,০০০ সারির পরে থেমে বার্তা দেয়।
  - **কলামের প্রস্থ:** প্রথম খণ্ডের লেখা দেখে ঠিক হয়।
  - **বাংলা:** FreeSerif-এ যুক্তাক্ষরসহ আসে। FreeSerif-এর Bold-এ বাংলা নেই, তাই মোটা অংশেও সাধারণ হরফ বসে। Noto Sans Bengali চেষ্টা করা হয়েছিল, কিন্তু mPDF তার GPOS Lookup Type 5, Format 3 পড়তে পারে না।
  - **ব্যবহার:**
    - Store-এ `GET /store-material/reports/{report}/pdf`, সাথে route handler `/api/store-material/report-pdf/[report]`; Export PDF এখন আসল ডাউনলোড।
    - Reporting মডিউলেও `PdfReportWriter` নিবন্ধিত হয়েছে, তাই সেখানকার export-এও PDF চালু।
  - **temp folder:** `storage/app/mpdf-{uid}`। console (root) আর web (www-data) আলাদা folder পায়, যাতে একজনের তৈরি cache-এ অন্যজন আটকে না যায়।

## ৯. টেস্ট ও যাচাই

- **`StoreGrnTest`:** ২টা টেস্ট, ৭৭টা assertion।
  - **Against PR:**
    - না-থাকা আর জমা দেওয়া PR-এ Brickland-এর বার্তা।
    - অনুমোদিত PR-এ supplier, আর দুটো লাইনের PR Qty / Received / Pending।
    - শূন্য পরিমাণ, বেশি পরিমাণ আর অচেনা কর্মী আটকায়।
    - GRN-2026-00001 হয় (PR, Poly Plan, "50001001 (Mohammad Sayem)", Fabric WH); prev 0, total 40, pending 60।
    - দ্বিতীয় GRN-এ prev 40, total 65।
  - **একই হিসাব সবখানে:** Fabric Warehouse ৬৫; Roll Wise ফাঁকা; ledger-এর balance 40 → 65; dashboard-এ PR 600, in-house 65, pending 535, আর movement-এ PR নং।
  - **Reverse:** Storekeeper 403 পান; কারণ বাধ্যতামূলক; Reversed হলে pending আবার 60, ledger-এ REVERSAL আর balance 40; দ্বিতীয়বার আটকায়।
  - **ledger:** entry বদলানো যায় না।
  - **Direct:**
    - material আর পরিমাণের বার্তা।
    - LPP / 927-এর জন্য trims আর fabric; general stock; filter।
  - **Attachment:** ফাইল ছাড়া বা ভুল ধরনে আটকায়, PDF সংযুক্ত হয়।
  - **কর্মী খোঁজা:** card বা code দিয়ে পাওয়া যায়; ১০০১ পাওয়া যায় না।
  - **অনুমতি:** Factory Manager GRN করতে পারেন না।
- **`StoreIssueTest`:** ১টা টেস্ট, ৭১টা assertion।
  - **MO:**
    - ৭টা বার্তা; বেশি পরিমাণ আর অচেনা কর্মী আটকায়।
    - MO-2026-00001 (Cutting Floor, Cutting Table 1, Sayem); তারপর available 10।
    - issued GRN-এর Reverse আটকায়।
  - **MR:**
    - না-থাকা MO আটকায়; 30 / 0 / 30; বেশি ফেরত আটকায়।
    - MR-2026-00001 হয় (6, eligible 24)।
  - **SA:**
    - মিলে গেলে আটকায়।
    - PENDING (16 → 15, −1); stock 16 থাকে; Storekeeper 403 পান।
    - Approve হলে stock 15; আবার সিদ্ধান্ত আটকায়।
  - **Traceability:** PR, GRN আর MO তিনভাবেই একই শৃঙ্খল আসে (40 / 30 / 6 / 15, floor, receiver)। Roll-এ বার্তা।
  - **Report:**
    - ৮টা report-এর সারি মেলে। Ledger-এ GRN, Out, Return, Adjustment (adj −1, balance 15)।
    - Minimum 50 দিলে Low Stock-এ shortfall 35।
    - তারিখের filter কাজ করে; Excel-এর content-type ঠিক; অজানা report 404।
- **রিগ্রেশন** (২০২৬-১০-২৪, একা চালানো): StoreMaterial, HrPayroll, MaterialProcurement, ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration, ErpMasterDataSeeder, NotificationRouting, Entitlement, UsageMeter, Billing, Subscription, UserApi, UserManagement, CompanyUser, Invite, Auth, Merchandise-এর PurchasedFabricMrr আর AccReceipt, এবং FabricInspectionPhoto।
  - ১৯৭টা টেস্টের মধ্যে ১৯৬টা সবুজ, ১৬৫৫টা assertion।
  - একটা ব্যর্থ হয়েছিল: MenuAccessTest বলেছিল `storematerial.manage` কোনো মেনুতে নেই। In-House / GRN আর Stock Adjustment মেনুতে এটা যোগ করার পরে MenuAccess, RoleMatrix আর ApiGateCoverage আবার চালানো হয়েছে: ১৭টা টেস্ট সবুজ, ১০১টা assertion।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৩/২৪, owner):
  - **GRN:**
    - PR-2026-00003-এ "is submitted" বার্তা ✔ PR-2026-00002-এ Pique 300 KG; ৪০০ লিখলে "Max 300", Confirm বন্ধ ✔
    - ১০০ KG, Received By 50001001 দিয়ে → GRN-2026-00001 (LPP, PO-SS27-01, SS27-TEE01, Fabric Warehouse) ✔
  - **Direct:** Main Label 2000 PCS, LPP / SS27-TEE01, Local Market, BDT 1.2 → GRN-2026-00002 ✔ Attachments-এ "Delivery challan" (PDF) ✔ Reverse "Counted twice by mistake" → Reversed ✔
  - **MO আর MR:**
    - Pique-এর ১০০ KG থেকে ৩০ KG কাটিংয়ে (Ground Floor · Cutting · Knit M 1, Sayem) → MO-2026-00001 ✔
    - MR-এ MO দিলেই 30 / 0 / 30, ৫ KG Excess Material → MR-2026-00001 ✔
  - **SA:** 75 থেকে 74, Damaged → SA-2026-00001 Pending, তারপর Approve → Approved ✔
  - **অন্য স্ক্রিন:**
    - Fabric Warehouse → Movement ✔
    - Dashboard: PR 300, In-House 100, Pending 200, Fabric 100% ✔
    - Traceability by PR: পুরো শৃঙ্খল, হাতে ৭৪ KG ✔
    - Stock Ledger Report: ADJ −1, balance 74 ✔
  - **ধরা পড়া সমস্যা, ঠিক করা হয়েছে:**
    - GRN-এর তথ্যের কলাম এলোমেলো ছিল।
    - filter দুই কলামে ছড়িয়ে যাচ্ছিল।
    - ফাঁকা select-এ "Select…" দেখাচ্ছিল, এখন "All"।
    - floor / section ছাড়া line তালিকায় আসছিল না।
    - পুরনো error থেকে যাচ্ছিল।
  - সব রেকর্ড dev DB-তে রাখা আছে।

- **রোল আর ডাই লট (২০২৬-১০-২৪):**
  - **`StoreRollTest`:** ২টা টেস্ট, ৭২টা assertion।
    - রোলের যোগফল, নম্বর, পরিমাণ, একই নম্বর দুবার, অন্য GRN-এ থাকা নম্বর।
    - Direct receive-এর রোল।
    - Roll / Dye Lot / Color / Stock List ভিউ।
    - রোল ধরে MO: রোলের বেশি আটকায়, যোগফল না মিললে আটকায়।
    - রোলে ফেরত: রোল বাধ্যতামূলক, রোলের সীমা।
    - রোল দেওয়া GRN-এর Reverse আটকায়; অক্ষত GRN Reverse হয়, আর তার নম্বর আবার নেওয়া যায়।
    - Trace by Roll; Fabric Roll আর Material Out report; রোলের move বদলানো যায় না।
  - পুরনো `StoreGrnTest` আর `StoreIssueTest` সহ Store Material-এর ৫টা টেস্ট সবুজ, ২২৬টা assertion। দ্বিতীয় test DB-তে চালানো হয়েছে, কারণ মূল DB-তে পুরো suite চলছিল।
  - **ব্রাউজার যাচাই** (owner):
    - **GRN-2026-00003** (Direct, LPP / 927, Single Jersey 160 GSM 30 KG): DL-R001 (LOT-A, 180, 72", 12), DL-R002 (LOT-A, 10), DL-R003 (LOT-B, 8)। যোগফল 27 থাকার সময় লাল বার্তা আর Confirm বন্ধ ছিল। GRN-এ "Rolls (3)" chip দেখায়।
    - **Fabric Warehouse:** Roll Wise-এ তিনটা রোল দেখায়। Dye Lot Wise-এ LOT-A-তে ২টা রোল (22 KG), LOT-B-তে ১টা (8 KG)।
    - **MO-2026-00002:** DL-R001 থেকে 7 আর DL-R003 থেকে 3 নেওয়া হয়েছে; Out Qty নিজে 10 হয়েছে।
    - **MR-2026-00002:** রোল না বাছায় প্রথমে "Pick the roll it comes back on." এসেছে। তারপর DL-R003-এ 1 KG ফেরত।
    - **Trace by Roll dl-r003:** GRN-2026-00003 → MO-2026-00002 (3) → MR-2026-00002 (1)।
    - **Fabric Roll Report:** DL-R001 Partly out (5), DL-R002 In stock (10), DL-R003 Partly out (6)।
    - **Material Out Report:** Roll কলামে "DL-R001, DL-R003"।
    - **ছোট সংশোধন:** Reset-এর পরেও receiver-এর সবুজ নাম থেকে যাচ্ছিল। এখন নাম শুধু তখনই দেখায়, যখন ঘরে সেই নম্বরটাই আছে।
  - সব রেকর্ড dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **সময় অঞ্চল:** GRN-এর তারিখ server-এর দিন ধরে পড়ে। ঢাকায় মধ্যরাতের পরে কাজ করলে আগের দিনের তারিখ আসতে পারে; অন্য মডিউলেও একই আচরণ। app-এর timezone বদলানো হবে কিনা, তা আলাদা সিদ্ধান্ত।
- **দুটো আলাদা হিসাব:** 08/06-এর follow-up-এ পাওয়া পরিমাণ আর Merchandise-এর খসড়া MRR আলাদা হিসাব, store-এর GRN আলাদা। Brickland-এও তাই (PR-2026-00005-এ follow-up ৫০০ দেখালেও store-এ received ০ ছিল)।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/st01.json` থেকে `st12.json`।
  - st06 আর st07-এ লেখার অনুমতি ব্যবহার করে Brickland-এ PR-2026-00005-এর বিপরীতে ১ roll-এর GRN-2026-00005 নেওয়া হয়েছে, যাতে নিশ্চিতকরণ, GRN-এর রূপ, Reverse dialog আর Attachments দেখা যায়।
- **ক্রম:** ব্যবহারকারীর সিদ্ধান্তে আগে 24/03 HR Employee-এর তিনটা স্ক্রিন বানানো হয়েছে, তারপর এই মডিউল।
- **Brickland থেকে মেলানো:** dashboard-এর "Mixed units", Pending = PR − In-House, Reverse-এর "issued হলে আটকায়" আর প্রতিটা report-এর কলামের ক্রম।
