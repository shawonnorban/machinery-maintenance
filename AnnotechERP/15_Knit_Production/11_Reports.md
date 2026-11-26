# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন (৮টার ৮টা স্ক্রিন — পাঁচ রিপোর্ট সেটে ১৪৩টা রিপোর্ট, আর তিনটা খোঁজার স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/11`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Knit Production Reports | `/m/knitting/knitting-reports` | 1 | 67 | 0 |
| 2 | Yarn Stock Position | `/m/knitting/yarn-current-stock` | 1 | 13 | 0 |
| 3 | Grey Fabric Stock Position | `/m/knitting/fabric-current-stock` | 1 | 6 | 0 |
| 4 | Yarn Store Register Report | `/m/knitting/yarn-store-report` | 1 | 31 | 0 |
| 5 | Grey Fabric Store Report | `/m/knitting/gfs-report` | 1 | 39 | 0 |
| 6 | Knit Quality Report | `/m/knitting/qc-report` | 1 | 24 | 0 |
| 7 | Flat Knit Production Report | `/m/knitting/flatknit-report` | 1 | 18 | 0 |
| 8 | Daily Knit Output Report | `/m/knitting/daily-knitting-production-report` | 1 | 6 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Knit Production Reports

`/m/knitting/knitting-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Yarn Purchase Requisition Statement | radio |  |  |
| Grey Fabric Stock Summary | radio |  |  |
| Yarn Purchase Requisition Summery | radio |  |  |
| Yarn Requisition Statement for Knitting Sample - Y/D (By Date) | radio |  |  |
| Yarn Requisition Statement for Knitting Sample - Solid (By Date) | radio |  |  |
| Projection Order Grey Stock Summary | radio |  |  |
| Yarn Requsition Statment for Knitting Bulk (By Date) | radio |  |  |
| Order Wise Yarn Issue Register | radio |  |  |
| Order and Color wise Fabric Production Statement | radio |  |  |
| Order and Color wise Knitting Statement - New | radio |  |  |
| Daily Knitting Production with Value | radio |  |  |
| Daily M/C and Shift wise Knitting Statement | radio |  |  |
| Party Wise Order Statement (S/C Outhouse - Knitting) | radio |  |  |
| Party wise Knitting Performance Summary (S/C Out-house) | radio |  |  |
| M/C wise knitting production - capacity vs achievement | radio |  |  |
| Order and Color wise Production Status (S/C Out-house) | radio |  |  |
| Daily Order and Color wise Knitting Production | radio |  |  |
| Daily Roll Labelling Statement (S/C Out-house) | radio |  |  |
| Knitting Department Followup Report | radio |  |  |
| Knitting Program for Sample | radio |  |  |
| Job Wise Knitting Program | radio |  |  |
| Date Wise Knit Card Report | radio |  |  |
| Yarn To Finish Fabric Status | radio |  |  |
| Color wise Yarn Lot Alocation and lot wise Knitting Production | radio |  |  |
| Order and Color wise Summery | radio |  |  |
| Sample Grey Fabric Receive and Delivery Status | radio |  |  |
| Sample Grey Fabric Collar & Culf Receive and Delivery Status | radio |  |  |
| Sample Grey Fabric Receive and Delivery Status Test | radio |  |  |
| Date Wise Sample Grey Fabric Receive and Delivery | radio |  |  |
| Lot wise loose yarn statement | radio |  |  |
| Lot Wise Yarn Statement Report | radio |  |  |
| M/C and Date wise Needle Broken | radio |  |  |
| Needle Reason Type Wise Summary | radio |  |  |
| Daily M/C and Shift wise Needle Statement | radio |  |  |
| Needle Stock Taking | radio |  |  |
| M/C and Operator wise Oil Consumption & Unit Cost | radio |  |  |
| M/C and Shift wise Oil Consumption & Unit Cost | radio |  |  |
| Date Wise M/C Oil Consumption | radio |  |  |
| Oil Stock Taking | radio |  |  |
| S/C Party Wise Rate | radio |  |  |
| Order and Color wise Grey Fabric Transfer Statement | radio |  |  |
| S/C Pre-Treatment Ledger for All | radio |  |  |
| Monthly Grey QC Summary Report | radio |  |  |
| Rack Wise Stock Report | radio |  |  |
| Save Fabric Quantity | radio |  |  |
| Order wise requisition, Receive and Issue Report | radio |  |  |
| Physical Dyed Yarn Inventory Report | radio |  |  |
| Physical Yarn Inventory Report | radio |  |  |
| Buyer Wise Yarn In-Hand Status | radio |  |  |
| Roll List without Grey Fabric Receive | radio |  |  |
| Knit Loan Ledger Details | radio |  |  |
| Knit Loan Ledger Summary | radio |  |  |
| Knit Card Wise Quality Check Status | radio |  |  |
| Knit Needle Loan Statement | radio |  |  |
| Sample Fabric Progress Report | radio |  |  |
| Knit Oil Loan Ledger Details | radio |  |  |
| Knit Oil Loan Ledger Summary | radio |  |  |
| Knit Oil Loan Statement | radio |  |  |
| Program wise Process Loss (Yarn vs Fabric) | radio |  |  |
| M/C wise Efficiency, Downtime and Running Cost | radio |  |  |
| Program wise Knitting Cost per Kg | radio |  |  |
| Yarn Item | select |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| YR Approve? | select |  | All, All, Approved, Not Approved |
| Date Type | select |  | Document Date, Approved Date, Delivery Date |
| Export to Excel? Ticked, Preview draws the report ready to be taken off — use Ex | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview, Reset

#### Yarn Stock Position

`/m/knitting/yarn-current-stock`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Spin Type | select |  | Combed, Carded, Open End, Ring Spun, Vortex, Semi Combed |
| Cotton Type | select |  | Conventional, Organic, Bci, Recycled, Supima, Egyptian |
| Slub? | checkbox |  |  |
| Mellange? | checkbox |  |  |
| Solid? | checkbox |  |  |
| Y/D? | checkbox |  |  |
| S/C-IN? | checkbox |  |  |
| Brand | select |  |  |
| Lot | text |  | -All- |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer | select |  |  |
| Job#/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Order | select |  | -Select- |
- সেকশন: Yarn Current Stock
- বাটন/অ্যাকশন: --Count--, -- Fiber Composition --, -All-, -- Select --, Search, Reset

#### Grey Fabric Stock Position

`/m/knitting/fabric-current-stock`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Is G/F Fab? | select |  | Grey Fabric, Collar / Cuff |
| Order Type | select |  | Bulk, Y/D, Sample, Flat Bed RIB |
| Buyer A/C | select |  |  |
| Work Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
- সেকশন: Fabric Store Stock
- বাটন/অ্যাকশন: --Color--, Search, Reset

#### Yarn Store Register Report

`/m/knitting/yarn-store-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Yarn Receive Statement | radio |  |  |
| Yarn Issue Statement | radio |  |  |
| Order Wise Yarn Issue Register with Value | radio |  |  |
| Order Wise Yarn Issue Register | radio |  |  |
| Party Wise Yarn Issue Statement | radio |  |  |
| Yarn Issue Return Statement | radio |  |  |
| Department wise Excess Yarn Summery | radio |  |  |
| Excess Yarn Usage Statement | radio |  |  |
| Excess Yarn Statement | radio |  |  |
| Advance Yarn Report | radio |  |  |
| Lot and Count Wise Loose Yarn Stock | radio |  |  |
| Yarn Stock With Value | radio |  |  |
| Yarn Stock With Summary Value | radio |  |  |
| Loose Yarn Statement (S/C Out-house) | radio |  |  |
| Yarn Inventory Aging | radio |  |  |
| Finishing Production Report 4A | radio |  |  |
| Delivery Challan for Yarn Return to Supplier | radio |  |  |
| Daily and Monthly Yarn Operation, Stock and Value Report | radio |  |  |
| Buyer Wise Yarn Stock Taking Report | radio |  |  |
| Yarn Stock Taking Report | radio |  |  |
| Lot wise loose yarn statement | radio |  |  |
| Lot Wise Yarn Statement Report | radio |  |  |
| Production Report With Target Qty | radio |  |  |
| Yarn Purchase Order Statement | radio |  |  |
| Yarn Proforma Invoice Statement | radio |  |  |
| Yarn Closing Stock Summary | radio |  |  |
| Dyed Yarn Receive Challan Statement | radio |  |  |
| Excess Yarn Target Report | radio |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? Ticked, Preview draws the report ready to be taken off — use Ex | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview, Reset

#### Grey Fabric Store Report

`/m/knitting/gfs-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Grey Fabric Stock Detail | radio |  |  |
| Buyer A/C wise Grey Fabric Stock | radio |  |  |
| Grey Fabric Stock by Dia and Yarn | radio |  |  |
| Projection Order Grey Fabric Stock by Fin Dia | radio |  |  |
| Grey Fabric Delivery Challan (S/C In-house) | radio |  |  |
| Yarn Transfer Challan (S/C In-house) | radio |  |  |
| S/C Yarn Delivery Challan (Out House) | radio |  |  |
| S/C Program Cum Contract (Out House) | radio |  |  |
| Party Wise Order Statement (S/C Outhouse - Knitting) | radio |  |  |
| Party and Order wise YD Register (S/C Outhouse) | radio |  |  |
| Party wise Yarn Balance (S/C Outhouse - Knitting) | radio |  |  |
| Party wise Yarn Balance (S/C Outhouse - YD) | radio |  |  |
| Party wise Ledger (S/C Outhouse - Knitting) | radio |  |  |
| Supplier Wise Yarn Test Result | radio |  |  |
| Party wise Ledger (S/C Outhouse - YD) | radio |  |  |
| Order wise Y/D Ledger | radio |  |  |
| Party Wise Bill Statement (S/C Out-house) | radio |  |  |
| Party wise ledger (S/C In-house) | radio |  |  |
| Party wise Knitting Performance by Program (S/C Out-house) | radio |  |  |
| Fabric Reject Summery (S/C Out-house) | radio |  |  |
| Daily Trims Receive Statement | radio |  |  |
| Party Wise Order Statement (S/C In-house) | radio |  |  |
| Utilization of Left-Over Grey Fabric – Transfer and Re-Use Summery | radio |  |  |
| Utilization of Left-Over Grey Fabric Re-use with Value | radio |  |  |
| Utilization of Left-Over Grey Fabric – Transfer and Re-Use | radio |  |  |
| Party wise Stock Summery (S/C In-house) | radio |  |  |
| Party Wise Summery (S/C In-house) | radio |  |  |
| Party Wise Bill Statement (S/C In-house) | radio |  |  |
| Order and Color wise Roll Available in Grey Store (S/C In-house) | radio |  |  |
| Order and Color wise Grey Fabric Transfer Statement | radio |  |  |
| S/C Pre-Treatment Ledger | radio |  |  |
| Order and Color wise Fabric Delivery Status | radio |  |  |
| Buyer A/C | select |  |  |
| Production Type | select |  | Bulk, Y/D, Sample, Flat Bed RIB |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? Ticked, Preview draws the report ready to be taken off — use Ex | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview, Reset

#### Knit Quality Report

`/m/knitting/qc-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Grey Fabric Roll Inspection | radio |  |  |
| Shift Wise Grey Fabric Roll Inspection | radio |  |  |
| Roll List Without Grey QC | radio |  |  |
| Order and Color wise Inspection Report | radio |  |  |
| Date wise Grey Fabric QC Status | radio |  |  |
| Grey Fabric Delivery Challan to Store | radio |  |  |
| Roll# wise Grey Fabric Delivery Challan to Store | radio |  |  |
| Monthly Out Side Quality Summary | radio |  |  |
| Daily Return Challan for Grey QC Deduction Fabric | radio |  |  |
| Monthly Quality Summary Report Before Dyeing | radio |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| QC Status Type | select |  | Draft, Passed, Hold, Rejected |
| Knit Card# | text |  | Card number |
| Yarn Item | select |  |  |
| QC Grade | select |  | A, B, C, D |
| Shift | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Production Type | select |  | Bulk, Y/D, Sample, Flat Bed RIB |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Roll No | text |  | Roll no |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? Ticked, Preview draws the report ready to be taken off — use Ex | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: -- Color --, -- All QC --, -- All Counts --, Preview, Reset

#### Flat Knit Production Report

`/m/knitting/flatknit-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Yarn Requsition Statement for Collar/Cuff | radio |  |  |
| Order wise Flat Knit Requirement | radio |  |  |
| Daily Collar/Cuff Production | radio |  |  |
| Flat Knit Stock Detail | radio |  |  |
| Daily Collar/Cuff Transfer to QC and Store | radio |  |  |
| Buyer A/C wise Flat Knit Stock | radio |  |  |
| Order wise Collar/Cuff Stock | radio |  |  |
| Store wise Collar/Cuff Stock | radio |  |  |
| Date and MC# wise Collar/Cuff Production | radio |  |  |
| Order wise Yarn Issue Register for Collar Cuff Production | radio |  |  |
| Collar/Cuff Challan Receive (S/C Out-house) | radio |  |  |
| Flat Knit Production Statement | radio |  |  |
| Production Type | select |  | Bulk, Y/D, Sample, Flat Bed RIB |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Req. Type | select |  | Solid, Y/D |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? Ticked, Preview draws the report ready to be taken off — use Ex | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview, Reset

#### Daily Knit Output Report

`/m/knitting/daily-knitting-production-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Prod. Flr | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Shift | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| From Date | date |  |  |
| To Date | date |  |  |
| Job | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
- সেকশন: Filter Options
- বাটন/অ্যাকশন: Show, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Ledger, যেখান থেকে সব স্টক রিপোর্ট পড়ে:**
  - সুতা: `knit_yarn_moves` আর `knit_yarn_lots` (15/03)।
  - গ্রে: `knit_grey_moves` আর `knit_grey_lots` (15/05)।
  - নিডল আর তেল: `knit_needle_moves`, `book` = NEEDLE বা OIL (15/07–10)।
- **আগের helper:**
  - `KnitCollarCuff::figures`, `produced`, `delivered`, `received`, `issued`।
  - `KnitProduction::categories`।
  - `KnittingLabels::machines`, `refs`।
  - `YarnProcurement::names`।
- **Merchandise Reports (06/13)-এর `ReportSet`:** নেওয়া হয়নি। Brickland এখানে ফল dialog-এ নয়, ফর্মের নিচে দেখায়, আর প্রতিটা সেটের প্যারামিটার বক্স আলাদা।
- **`RecordsGrid`:** ফলের গ্রিড এটাই। দুই সারির হেডারের জন্য এতে কলামের `group` যোগ হয়েছে (নিচে দেখুন)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন কোনো table নেই। রিপোর্ট শুধু পড়ে, কিছু লেখে না।

| অংশ | যে table পড়ে |
|---|---|
| Production | `knit_rolls`, `knit_cards`, `knit_programs`, `knit_prod_orders`, `knit_roll_inspections`, `knit_grey_receipt_rolls`, `knit_capacity_plans` |
| Yarn | `knit_yarn_lots`/`moves`, purchase requisition, PO, receipt, issue indent, issue, issue return, supplier return, party challan, rack, period line, Y/D dyed receipt |
| Grey | `knit_grey_lots`/`moves`, transfer, grey receipt |
| QC | inspection + lines, grey receipt note, out-house grey receipt আর return |
| Collar/Cuff | requirement, production, challan, programme, yarn indent আর issue |
| Needle/Oil | broken, indent আর issue, oil consumption, loan opening/receipt/indent/return, operator posting, Machinery Maintenance-এর `breakdowns` আর `assets` |
| S/C | out-house programme, grey receipt আর return, bill, in-house receipt (programme), yarn challan, delivery, Y/D grey transfer আর dyed receipt, yarn test result |

## ৫. Workflow / Status ও Business Rules

- **কীভাবে পড়ে:**
  - Preview চাপলে রিপোর্টটা তখনই table থেকে পড়ে, কিছু cache হয় না।
  - পেজ খুললে প্রথম রিপোর্টটা সব ঘর ফাঁকা রেখে চলে, মানে যা আছে সব। Brickland-ও তাই করে।
- **তারিখ:** From/To ঘর কাগজের নিজের তারিখে খাটে।
  - Knit Production Reports-এ Date Type বলে requisition-এর কোন তারিখে খাটবে: Document, Approved, না Delivery।
  - YR Approve? approved (Approved/Ordered/Closed) আর বাকিগুলো আলাদা করে।
- **স্টক:**
  - সুতার স্টক রিপোর্ট তারিখ মানে না। Brickland-এর মতো, ব্যালান্স এই মুহূর্তের।
  - গ্রে স্টক To Date পর্যন্ত, না দিলে আজ পর্যন্ত।
- **Production:**
  - রিজেক্ট roll নিট হয়েছে কিন্তু production নয়, তাই production, rolls আর Daily Output থেকে বাদ।
  - Day/Night ভাগ মিলের ০৮:৩০ আর ২০:৩০ সীমায়: roll যে শিফটে লগ হয়েছে তার শুরুর সময় থেকে, শিফট না থাকলে roll-এর নিজের সময় থেকে।
- **সুতা:**
  - Alloc মানে খোলা (Open বা Partly Issued) issue indent-এর যা বাকি, একই lot নম্বরের lot-গুলোর বিপরীতে, আগেরটা আগে।
  - Stock (Free) = Physical − Alloc।
  - Excess হলো indent লাইনের চাওয়ার বেশি যা ইস্যু হয়েছে। যে ইস্যু সীমা পার করেছে, excess সেটার নামে গোনা হয়।
- **Loan:**
  - Receivable = ধার দেওয়া − ফেরত পাওয়া।
  - Payable = ধার নেওয়া − ফেরত দেওয়া।
  - Opening-এর Loan Issued আর Loan Received দুটোই গোনা হয়।
  - NEEDLE আর OIL খাতা আলাদা। Knit Loan Ledger-এ দুটোই থাকে।
- **S/C ledger:** Knitting ledger-এ BULK_SOLID আর SAMPLE_SOLID প্রোগ্রাম, Y/D ledger-এ PARTY_YD, Brickland-এর ডাটা যেমন দেখায়। বিল কেজি মেলানো হয় প্রোগ্রাম ধরে (in-house-এ program receipt ধরে)।
- **Grey Fabric Stock Position:**
  - প্রতি production order-এর জন্য একটা সারি, আর Brickland-এর মতো প্রতি collar/cuff requirement-এর জন্যও একটা।
  - Due for Prod. = target − যা নিট হয়েছে।
- **Brickland-এর নিজস্ব জোড়া:**
  - Daily Trims Receive আর Sample Grey Fabric Collar & Culf collar/cuff challan পড়ে।
  - Finishing Production Report 4A দিনভিত্তিক গ্রে QC পড়ে।
  - Order and Color wise Fabric Delivery Status, Order and Color wise Summery আর Knitting Statement - New একই হিসাব দেখায়।
- **Pending module:** যে রিপোর্টের উৎস-মডিউল এখনো নেই, সেটা ফাঁকা গ্রিড দেয় আর বার্তায় জানায়:
  - S/C Pre-Treatment Ledger (দুটো): ডাই হাউস।
  - Yarn Proforma Invoice Statement: কমার্শিয়াল।
- **Export to Excel? টিক দিলে:** গ্রিড সব সারি এক পাতায় দেখায় (pager ছাড়া)। গ্রিডের ওপরের Export CSV রাখে।

## ৬. API ও পারমিশন

পারমিশন `knitting.report.view` (নতুন)। FACTORY_ADMIN আর FACTORY_MANAGER পায়।

| Route | কাজ |
|---|---|
| `GET /knitting/reports/options` | সব ঘরের বাছাই: buyer, order, store, yarn item, count, composition, PO, brand, shade, shift, floor, colour, fabric type, QC user, grade, kind |
| `GET /knitting/reports/{set}/{report}` | set = knitting / yarn-store / gfs / qc / flatknit। অন্য সেটের রিপোর্ট চাইলে 404। ফেরত দেয় `columns` (key, type, group), `rows`, `stats` আর `pending_module` |
| `GET /knitting/reports/yarn-stock` | Yarn Stock Position |
| `GET /knitting/reports/grey-stock` | Grey Fabric Stock Position |
| `GET /knitting/reports/daily-output` | Daily Knit Output Report |

ঘরগুলো যাচাই হয় (validation):
- To Date From Date-এর আগে হতে পারে না।
- prod_type, qc_status, grade, req_type, part আর category নির্দিষ্ট তালিকা থেকে।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Knit Production → **Reports** ফোল্ডার, Oil Store-এর পরে। আটটা স্ক্রিন Brickland-এর ক্রমে।
- **`KnitReportSet`** (`components/knitting/reports.jsx`):
  - "Selection of Parameters" কার্ড: স্ক্রিনের hint, রিপোর্টের রেডিও তালিকা, সেটের ঘর, Export to Excel?, Preview আর Reset।
  - রেডিও: QC সেটে পাশাপাশি সাজানো, বাকিগুলোয় স্ক্রল করা যায় এমন তালিকা।
  - নিচে স্ট্যাট পিল, pending বার্তা, আর `RecordsGrid`।
- **`KnitPosition`:** তিনটা খোঁজার স্ক্রিন: ঘর, Search (Daily Output-এ Show), Reset, তারপর পিল আর গ্রিড।
- **যে ঘর বাছাই করার মতো কিছু নেই:** নিষ্ক্রিয় থাকে আর লেখে "No records to choose from yet.", Brickland-এর মতো।
- **`RecordsGrid`-এ দুই সারির হেডার:** কলামের `group` থাকলে ওপরের সারিতে গ্রুপের নাম বসে, যেমন DAY (8:30-20:30), COST (BDT) আর REQUIREMENT। আগের সব গ্রিড আগের মতোই থাকে।
- **Status-এর মান:** গ্রিডে শব্দে দেখায় ("In Knitting", "Partly Issued")।
- **Lang:** `knitting.rp.*` (en/bn)। কিছু ঘরের নাম স্ক্রিনভেদে আলাদা (`fx.*`/`px.*`), যেমন Yarn Stock-এর "Job#/W.Style" আর Grey Stock-এর "Order Type"।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৮টার ৮টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Http/Controllers/Api/KnitReportApiController.php`
- `app/Modules/Knitting/Support/Reports/ReportQuery.php` (ঘর আর নাম), `KnitReports.php` (চালানো, কলাম/স্ট্যাট, group)
- `app/Modules/Knitting/Support/Reports/ProductionReports.php`, `YarnReports.php`, `GreyReports.php`, `QcReports.php`, `CcReports.php`, `NeedleOilReports.php`, `ScReports.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (`KNITTING.REPORTS` + ৮টা)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php` (`knitting.report.view`)
- `lang/en/knitting.php`, `lang/bn/knitting.php` (`rp.*`, `prod.screens`/`descriptions`)

**Frontend**
- `frontend/src/components/knitting/reports.jsx`
- `frontend/src/components/erp/records-grid.jsx` (কলামের `group`)
- `frontend/src/app/(app)/knit-production/[screen]/rpt-screens.js`, `page.js`, `actions.js` (`reportAction`)

**Test**
- `tests/Feature/Knitting/KnittingReportsTest.php`

**Tool**
- `AnnotechERP/tools/erp_crawler/rprobe.mjs` (রিপোর্ট সেটের প্রতিটা রিপোর্ট চালিয়ে কলাম/স্ট্যাট পড়ে)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingReportsTest`, ২টা টেস্ট, ৫২৩টা assertion, test2 DB-তে পাস।
- **Production:**
  - দিন আর রাতের শিফটে দুটো roll আর একটা রিজেক্ট roll।
  - Daily M/C & Shift-এ দুই সারি, Production 43.2, রিজেক্ট বাদ।
  - Daily Output-এ Day 22.2 / 1, Night 21.0 / 1, QC 22.2, Pending 21.0, Store Pending 43.2। শিফট ফিল্টার কাজ করে।
- **Knit Card:** Assigned 150, Produced 43.2, Rolls 2, Balance 106.8।
- **Grey Position:** Target 500, Due 456.8।
- **সুতা:**
  - Lot-এ 100, খোলা indent 30, তাই Alloc 30, Stock 70, Value 400।
  - Bulk indent বিবরণী: Requested 30, Pending 30।
  - Yarn Stock-এ Spin "Combed" composition-এর শব্দ থেকে মেলে, "Carded" মেলে না।
- **Loan:** Needle opening Loan Issued 10 আর Received 4, তাই Receivable 10, Payable 4। Oil খাতা ফাঁকা।
- **Pending:** S/C Pre-Treatment `pending_module = dyeing`।
- **সব রিপোর্ট:** পাঁচ সেটের ১৪৩টা রিপোর্টই 200 দেয়, আর প্রতিটা সারিতে প্রতিটা কলাম আছে।
- **বাধা:**
  - অন্য সেটের রিপোর্ট বা অজানা সেট চাইলে 404।
  - To < From হলে 422।
  - LINE_CHIEF (report view নেই) 403 পায়।

**Regression:** `MenuAccessTest` আর `RoleMatrixTest` আবার পাস।

**dev DB smoke:** ১৪৩টা রিপোর্ট আর তিনটা স্ক্রিন চালানো হয়েছে। error ০, ৩৫০টা সারি, কোনো সারিতে কলাম বাদ নেই।

**UI চেক (`AnnotechERP/tools/app_check/plans/rp01`–`rp03`):** error ০।
- আটটা স্ক্রিনই খোলে, প্রথম রিপোর্ট নিজে থেকে চলে।
- Daily M/C & Shift (From/To দিয়ে) চালানো হয়েছে।
- Cost per Kg-এ COST (BDT) / PER KG (BDT) দুই সারির হেডার আসে, Avg Cost/Kg 15.49।
- Flat Knit Requirement-এ REQUIREMENT / PRODUCED হেডার আসে।
- Pre-Treatment-এ ডাই হাউসের বার্তা দেখায়।
- Knit Loan Ledger-এ Receivable 80, Payable 45। Excel টিক দিলে সব ৯টা সারি এক পাতায়।
- Shift-wise QC চালানো হয়েছে।
- Yarn Stock-এ Y/D? টিক দিলে শুধু dyed lot (৩টা) আসে।
- Daily Output আর Grey Stock-এর দুই সারির হেডার Brickland-এর মতো।
- রিপোর্ট কিছু লেখে না, তাই dev-এ নতুন কোনো কাগজ তৈরি হয়নি।

**প্রিন্ট:** Brickland-এর এই আটটা স্ক্রিনে Print নেই, শুধু Preview আর Export। Export গ্রিডের CSV।

## ১০. খোলা প্রশ্ন

১. **Brickland-এর ডেমোতে ডাটা নেই:** ১৪৩টা রিপোর্টের ৯৪টায় Brickland ডেমোতে কোনো সারি দেখায়নি, তাই তাদের কলাম দেখা যায়নি। এদের কলাম আমরা ঠিক করেছি স্ট্যাট টাইল (যা Brickland দেখায়) আর একই ধাঁচের রিপোর্ট দেখে। ডাটা ঢুকলে Brickland-এর সাথে আবার মেলাতে হবে।

২. **Adv. Issue (Yarn Stock Position):** Brickland বলে না এটা কী। আমরা ধরেছি lot থেকে indent-এর চাওয়ার বেশি যা ইস্যু হয়েছে।

৩. **Saved (Yarn to Finish / Save Fabric Quantity):** Brickland-এর সংখ্যা (830) বুকিং − ইস্যু হওয়া সুতার সাথে মেলে, তাই সেভাবেই রাখা হয়েছে। আসল অর্থ মিলিয়ে দেখা দরকার।

৪. **Excess Yarn Target:** ৩% ধরা হয়েছে, Process Loss রিপোর্টের "Over 3%"-এর মতো। কারখানার নিজের লক্ষ্যমাত্রা আলাদা হলে setting লাগবে।

৫. **Daily Knitting Production with Value:** ইন-হাউস নিটিংয়ের রেট master নেই। রেট নেওয়া হয় অর্ডারের approved S/C প্রোগ্রামের গড় রেট থেকে, না থাকলে 0 (Brickland-এও 0.00)।

৬. **মেশিনের breakdown:** knit machine আর Machinery-এর asset সরাসরি যুক্ত নয়। মেলানো হয় serial নম্বরে, না হলে asset code = machine no হলে। এখন কোনো knit machine মেলে না, তাই Breakdowns/Downtime 0।

৭. **Yarn Stock-এর Spin/Cotton Type, Slub, Mellange:** সুতার master-এ আলাদা ঘর নেই। এগুলো composition-এর বিবরণের শব্দ থেকে মেলানো হয়।

৮. **QC deduction-এর return challan:** এমন কাগজ এখনো নেই। Daily Return Challan for Grey QC Deduction-এ সব deduction "Challan Pending" দেখায়।

৯. **Pending module:** S/C Pre-Treatment Ledger (দুটো) ডাই হাউস আসলে, Yarn Proforma Invoice কমার্শিয়াল ডেস্ক আসলে পূর্ণ হবে।

## ১১. As-built নোট

- **Brickland probe:**
  - নতুন টুল `rprobe.mjs` প্রতিটা রিপোর্ট বেছে, পুরো বছরের তারিখ দিয়ে Preview চালিয়েছে, তারপর স্ট্যাট, কলাম আর প্রথম সারি পড়েছে (`out/rs_*.json`)।
  - Brickland-এর Preview একটা server action (POST), শুধু পড়ে। টুল শুধু পেজ লোড আর Preview ক্লিকের সময় POST চলতে দেয়।
- **Report key:** Brickland-এর রেডিও value-এর মতোই, লেবেল থেকে slug ("Collar/Cuff" → `collarcuff`)। Method নাম তার camelCase।
- **Lang লেখার স্ক্রিপ্ট:** প্রথম রানে `rp` নামের পুরনো nested গ্রুপ (`ystore.rp`) মিলে যাওয়ায় `ystore`-এর শেষাংশ মুছে গিয়েছিল। `frontend/messages`-এর আগের JSON থেকে ফেরত আনা হয়েছে, আর পুরো ফাইল আগের সাথে মিলিয়ে দেখা হয়েছে: কিছু হারায়নি। স্ক্রিপ্ট এখন শুধু top-level গ্রুপ মেলায়।
