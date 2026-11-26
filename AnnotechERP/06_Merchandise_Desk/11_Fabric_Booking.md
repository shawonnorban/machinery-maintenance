# Fabric Booking

> **স্ট্যাটাস:** ✅ সম্পন্ন (ছয়টা স্ক্রিনই চালু: F1 Bulk Fabric Booking Entry, F2 Approved Fabric Booking List, F3 Style-Wise Yarn Spec Confirmation, F4 Additional Fabric Booking Entry, F5 Purchase Buffer Booking, F6 Imported Fabric Booking)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/11`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Bulk Fabric Booking Entry | `/m/merchandising/bulk-fabric-booking` | 2 | 38 | 1 |
| 2 | Approved Fabric Booking List | `/m/merchandising/fabric-booking-list-approved` | 1 | 10 | 0 |
| 3 | Style-Wise Yarn Spec Confirmation | `/m/merchandising/style-wise-yarn-spec-confirm` | 1 | 8 | 1 |
| 4 | Additional Fabric Booking Entry | `/m/merchandising/additional-fabric-booking` | 3 | 16 | 1 |
| 5 | Purchase Buffer Booking | `/m/merchandising/buffer-booking-purchase` | 3 | 14 | 1 |
| 6 | Imported Fabric Booking | `/m/merchandising/import-fabric-booking-purchase` | 2 | 9 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Bulk Fabric Booking Entry

`/m/merchandising/bulk-fabric-booking`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Style# / Job/Work Style# | search |  | 0376 |
| Order# | search |  |  |
| Booking Date from | date |  |  |
| Booking Date to | date |  |  |
| Status | select |  | All, Draft, Submitted, Approved, Rejected, Cancelled |
| Search Fabric bookings with their date, style, order, revision, reference and ap | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Booking Date, Work Style#, Order#, Booking Ref#, Rev#, Buyer A/C, Ship.Date, Items, Qty, ACTION
- বাটন/অ্যাকশন: New Booking, Clear, Export, Booking Date, Work Style#, Order#, Booking Ref#, Rev#, Buyer A/C, Ship.Date, Items, Qty

**ট্যাব: New Booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| — | text |  |  |
| Buyer A/C | select |  |  |
| Job or Working Style | select |  | 529 — T-SHIRT, 1111 — gwewrtert, 121 — 1321, 529 — 539, 234234-32443 — 234234, 867MI — 867MI, 531 — Ladies Leggings, 927 |
| Order | select |  | Search…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Currency | select |  | USD, EUR, GBP, BDT |
| Conversion rate | text |  |  |
| Item or model number | text |  | Search… |
| Cutting wastage percent | text |  |  |
| Yarn dyeing wastage percent | text |  |  |
| All-over print wastage percent | text |  |  |
| Knit | radio |  |  |
| Woven | radio |  |  |
| Trims & Accessoreis | radio |  |  |
| Fabric | select |  | Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Interlock (INT |
| GMT Part | select |  | Search…, Applique, Arm Hole Band, Back Panel, Body, Body Parts, Collar, Cuff, Front Panel, Pocket, Sleeve, Waistband |
| Finish dia | text |  |  |
| Dia unit | select |  | Inch, Cm |
| Dia type | select |  | Open, Tube |
| Consumption | text |  |  |
| Consumption unit | select |  | Kg, Yds, Mtr |
| Consumption per | select |  | Dz, Pcs |
| All? | checkbox |  |  |
| GMT Item | select |  | 529 — T-SHIRT, 1111 — gwewrtert, 121 — 1321, 529 — 539, 234234-32443 — 234234, 867MI — 867MI, 531 — Ladies Leggings, 927 |
| All? | select |  | Search…, Apple Green (COL-APP001), Aqua (COL-AQU002), Army Green (COL-ARM003), Ash (COL-ASH004), Baby Pink (COL-PNK), Be |
| GMT Color? | checkbox |  |  |
| GMT Color? | select |  | Search…, Apple Green (COL-APP001), Aqua (COL-AQU002), Army Green (COL-ARM003), Ash (COL-ASH004), Baby Pink (COL-PNK), Be |
| Contrast? | text |  |  |
| Contrast? | checkbox |  |  |
| Additional quantity | text |  |  |
| Display order | text |  |  |
- গ্রিড কলাম: Disp Ord, GMT Part, Dia, Cons/Doz, GMT Item, GMT Color, Fab. Color, Size, Add Fin FabQty, Action
- সেকশন: New Booking, Key Information, Dia wise CAD Consumption, Applicable GMT Qty & Fabric Color
- বাটন/অ্যাকশন: New Booking, Consumption Entry, Process Data, Suggest, Add to Grid, Save, Cancel, Reset

**ডায়ালগ: New Booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| — | text |  |  |
| Buyer A/C | select |  |  |
| Job or Working Style | select |  | 529 — T-SHIRT, 1111 — gwewrtert, 121 — 1321, 529 — 539, 234234-32443 — 234234, 867MI — 867MI, 531 — Ladies Leggings, 927 |
| Order | select |  | Search…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Currency | select |  | USD, EUR, GBP, BDT |
| Conversion rate | text |  |  |
| Item or model number | text |  |  |
| Cutting wastage percent | text |  |  |
| Yarn dyeing wastage percent | text |  |  |
| All-over print wastage percent | text |  |  |
| Knit | radio |  |  |
| Woven | radio |  |  |
| Trims & Accessoreis | radio |  |  |
| Fabric | select |  | Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Interlock (INT |
| GMT Part | select |  | Search…, Applique, Arm Hole Band, Back Panel, Body, Body Parts, Collar, Cuff, Front Panel, Pocket, Sleeve, Waistband |
| Finish dia | text |  |  |
| Dia unit | select |  | Inch, Cm |
| Dia type | select |  | Open, Tube |
| Consumption | text |  |  |
| Consumption unit | select |  | Kg, Yds, Mtr |
| Consumption per | select |  | Dz, Pcs |
| All? | checkbox |  |  |
| GMT Item | select |  | 529 — T-SHIRT, 1111 — gwewrtert, 121 — 1321, 529 — 539, 234234-32443 — 234234, 867MI — 867MI, 531 — Ladies Leggings, 927 |
| All? | select |  | Search…, Apple Green (COL-APP001), Aqua (COL-AQU002), Army Green (COL-ARM003), Ash (COL-ASH004), Baby Pink (COL-PNK), Be |
| GMT Color? | checkbox |  |  |
| GMT Color? | select |  | Search…, Apple Green (COL-APP001), Aqua (COL-AQU002), Army Green (COL-ARM003), Ash (COL-ASH004), Baby Pink (COL-PNK), Be |
| Contrast? | text |  |  |
| Contrast? | checkbox |  |  |
| Additional quantity | text |  |  |
| Display order | text |  |  |
- খালি Save-এ মেসেজ: Add at least one line to the grid before saving.

#### Approved Fabric Booking List

`/m/merchandising/fabric-booking-list-approved`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk, Additional, Import, Buffer, Projection, Sample |
| Buyer A/C | select |  | All |
| Job/Work Style# | select |  | All, 76765 |
| Shipment Month from | date |  |  |
| Shipment Month to | date |  |  |
| Approve from | date |  |  |
| Approve to | date |  |  |
| Send Mail? | select |  | All, Yes, No |
| Search Fabric bookings with their date, style, order, revision, reference and ap | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Aprov.Date \| Prg/Rev Date, Ship.Date, Buyer A/C \| Style#, Job# \| Order#, Ref# \| SP#, Send Mail?, Send Date
- বাটন/অ্যাকশন: Clear, Export, Type, Aprov.Date \| Prg/Rev Date, Ship.Date, Buyer A/C \| Style#, Job# \| Order#, Ref# \| SP#, Send Mail?, Send Date

#### Style-Wise Yarn Spec Confirmation

`/m/merchandising/style-wise-yarn-spec-confirm`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Category | select |  | Select…, Bulk, Develop, Short-Bulk, Projection |
| Prod Category | text |  | Search... |
| Fabric | select |  | Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Interlock (INT |
| Gmt Item | text |  | -- Select Gmt Item -- |
| Gmt Parts | text |  | Search... |
| Gmt Color | text |  | Search... |
| All Col? | checkbox |  |  |
| Fab Color | text |  | Search... |
- সেকশন: Yarn / Fabric Mapping, Yarn/Fabric Mapping Form
- বাটন/অ্যাকশন: Spec Confirmation, -- Select --, New Entry, Save

**ডায়ালগ: New Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Category | select |  | Select…, Bulk, Develop, Short-Bulk, Projection |
| Prod Category | text |  |  |
| Fabric | select |  | Single Jersey (SJ), Lycra Single Jersey (LSJ), Pique / Lacoste (PIQ), 1x1 Rib (RIB1X1), 2x2 Rib (RIB2X2), Interlock (INT |
| Gmt Item | text |  |  |
| Gmt Parts | text |  |  |
| Gmt Color | text |  |  |
| All Col? | checkbox |  |  |
| Fab Color | text |  |  |

#### Additional Fabric Booking Entry

`/m/merchandising/additional-fabric-booking`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Job/W Style# | search |  | 0376 |
| Search Requisitions for additional fabric, with their request number, revision,  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Req Date, Req#, Rev#, Ref#, Buyer A/C, Job/Work Style#, Order#, Actv?, ACTION
- বাটন/অ্যাকশন: Booking Items, New requisition, Clear, Export, Req Date, Req#, Rev#, Ref#, Buyer A/C, Job/Work Style#, Order#, Actv?

**ট্যাব: Booking Items**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search bookings | search |  | Search… |
- সেকশন: Bookings, Fabric
- বাটন/অ্যাকশন: Booking Items, New requisition, BK-BUF-00001 Buffer 0

**ট্যাব: New requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Booking Date* | date | ✔ |  |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Work Style# | text |  |  |
| Ref# | text |  |  |
| SP# | text |  |  |
| Ship Date | date |  |  |
| Status | select |  | Select…, Draft, Submitted, Approved, Rejected, Cancelled |
| Send Mail? | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New requisition
- বাটন/অ্যাকশন: Booking Items, New requisition, Select…, Reset, Save requisition

**ডায়ালগ: New requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Booking Date* | date | ✔ |  |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Work Style# | text |  |  |
| Ref# | text |  |  |
| SP# | text |  |  |
| Ship Date | date |  |  |
| Status | select |  | Select…, Draft, Submitted, Approved, Rejected, Cancelled |
| Send Mail? | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Booking Date is required.

#### Purchase Buffer Booking

`/m/merchandising/buffer-booking-purchase`

**ট্যাব: Records 1**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buffer orders by buyer, with the style, order and the reference each was  | search |  | Search 1 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style #, Order #, Order Type, Reference No, Reference Date, Status, ACTION
- বাটন/অ্যাকশন: Booking Items, New buffer order, Clear, Export, Buyer Name, Style #, Order #, Order Type, Reference No, Reference Date, Status, Edit

**ট্যাব: Booking Items**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search bookings | search |  | Search… |
- সেকশন: Bookings, Fabric
- বাটন/অ্যাকশন: Booking Items, New buffer order, BK-BUF-00001 Buffer 0

**ট্যাব: New buffer order**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Booking Date* | date | ✔ |  |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Work Style# | text |  |  |
| Ref# | text |  |  |
| SP# | text |  |  |
| Ship Date | date |  |  |
| Status | select |  | Select…, Draft, Submitted, Approved, Rejected, Cancelled |
| Send Mail? | checkbox |  |  |
| Remarks | textarea |  |  |
- সেকশন: New buffer order
- বাটন/অ্যাকশন: Booking Items, New buffer order, Select…, Reset, Save buffer order

**ডায়ালগ: New buffer order**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Booking Date* | date | ✔ |  |
| Job/Work Style# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Work Style# | text |  |  |
| Ref# | text |  |  |
| SP# | text |  |  |
| Ship Date | date |  |  |
| Status | select |  | Select…, Draft, Submitted, Approved, Rejected, Cancelled |
| Send Mail? | checkbox |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Booking Date is required.

#### Imported Fabric Booking

`/m/merchandising/import-fabric-booking-purchase`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Style/Order | search |  | 0376 |
| Order # | search |  |  |
| Order Type | select |  | All, Bulk New, Development, Repeat, Projection, Buffer, Special Sample |
| Shipment from | date |  |  |
| Shipment to | date |  |  |
| Search Confirmed orders with their buyer, style, quantity and shipment, and what | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, Order Conf. Date, Shipment Date, Lead Time
- বাটন/অ্যাকশন: Bookings, Clear, Export, Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, Order Conf. Date, Shipment Date, Lead Time

**ট্যাব: Bookings**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Orders, Import Fabric Bookings
- বাটন/অ্যাকশন: Bookings, LPP-2026-000003 / 531 12 Oct 2026 0, LPP-2026-000006 / 529 12 Oct 2026 0, LPP-2026-000004 / 867MI 01 Nov 2026 0, LPP-2026-000008 / 529 01 Nov 2026 0, LPP-2026-000005 / 234234 10 Feb 2027 0, LPP-2026-000002 / 927 10 Oct 2027 0, LPP-2026-000007 / 1111 12 Dec 2027 0
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- স্টাইল আর অর্ডার (06/05, 06/07): প্রতিটা লাইনের পিস আসে অর্ডারের রং-সাইজ ব্রেকডাউন থেকে।
- CAD Consumption (06/08): Suggest স্টাইলের CAD রেকর্ড থেকে পার্ট ধরে গড় কনজাম্পশন পড়ে।
- মাস্টার: Fabric Type, Garment Part, Colour, Size (সাইজ গ্রুপ LETTER / NUMERIC / AGE), বায়ার অফিস (Buyer A/C)।
- Records-এর জন্য স্ক্রিন কিটের `UrlFilters` (text ফিল্টারসহ) আর `RecordsGrid` (rowKey = Booking Ref#)।
- নম্বর দেওয়ার ধাঁচ: `pg_advisory_xact_lock(hashtext(...))`-এর ভেতরে পরের নম্বর।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_fabric_bookings`:** ছয়টা স্ক্রিনই এই টেবিল ভাগ করবে, `booking_type` দিয়ে আলাদা।
  - booking_type: BULK, ADDITIONAL, BUFFER, IMPORT, PROJECTION, SAMPLE।
  - booking_no: প্রতি ধরনের নিজের prefix (BULK → `BFB-00001`, ADDITIONAL → `BK-ADD-00001` (Brickland-এ দেখা), BUFFER → `BK-BUF-`, IMPORT → `BK-IMP-00001` (Brickland-এ দেখা)); কোম্পানিতে ইউনিক।
  - currency: USD, EUR, GBP, BDT। status: DRAFT, SUBMITTED, APPROVED, REJECTED, CANCELLED।
  - বাকি কলাম: revision, booking_date, program_by, buyer_office_id, style_id, order_id, conv_rate, item_model, cutting_wastage, yd_wastage, aop_wastage, approved_at, approved_by, created_by।
  - F4-এ যোগ হয়েছে: work_style_no, ref_no, ship_date, remarks, active (Actv?)। লাইনে uom; gmt_style_id আর consumption এখন nullable, কারণ Booking Items-এর লাইন সাধারণ: ফ্যাব্রিক, রং (fab_colour_id + colour_spec-এ রঙের নাম), GSM, DIA (finish_dia), পরিমাণ (finish_qty), একক, রেট, প্রসেস লস।
  - F2-এ যোগ হয়েছে: approval_status আর approval_request_id (Approval Center-এর ইঞ্জিনের জন্য), sp_no (Brickland-এর SP#; F4/F5 ভরবে), send_mail, mail_sent_at।
- **`merch_yarn_specs` (F3):** style_id, prod_category (BULK, DEVELOP, SHORT_BULK, PROJECTION), fabric_type_id (Fabric Type মাস্টার), gmt_item (লেখা, স্টাইলের আইটেম থেকে প্রস্তাব), part_ids (JSON), all_colours, gmt_colour_ids আর fab_colour_ids (JSON, Colour মাস্টার), status (PENDING, CONFIRMED), confirmed_at, confirmed_by, created_by।
  - 06/05-এর Item/Combo/Model → Yarn/Fabric Mapping (`merch_style_fabric_maps`) থেকে আলাদা। সেটা স্টাইলের নিজের ফ্যাব্রিক/রং তালিকা আর অনুপাত ধরে চলে; F3 Brickland-এর মতো মাস্টার ডাটা ধরে চলে, আর নিশ্চিত/রিলিজ হয়।
- **`merch_fabric_booking_lines`:**
  - Enum কলাম: fabric_kind (KNIT, WOVEN, TRIMS), dia_unit (INCH, CM), dia_type (OPEN, TUBE), cons_unit (KG, YDS, MTR), cons_per (DZ, PCS)।
  - সার্ভারে হিসাব হয়: pieces, finish_qty।
  - Process Data: gsm, rate, process_loss।
  - বাকি কলাম: booking_id, fabric_type_id, garment_part_id, finish_dia, consumption, all_orders, gmt_style_id, all_colours, gmt_colour_id, all_sizes, size_group, fab_same_as_gmt, fab_colour_id, colour_spec, contrast, cutting_wastage, add_qty, disp_order।

## ৫. Workflow / Status ও Business Rules

- **Bulk Fabric Booking Entry (F1):**
  - Key Information-এ বাধ্যতামূলক: Date, Job/Working Style#, Order#, Currency, Conv. Loc., Item/Model #। বার্তা যেমন "Job/Working Style# is required."।
  - Buyer A/C ফাঁকা রাখলে স্টাইলের বায়ার অ্যাকাউন্ট বসে। Program By হলেন যিনি সেভ করছেন।
  - হেডার আর গ্রিড একসাথে এক সেভে যায়। গ্রিড খালি থাকলে Brickland-এর হুবহু বার্তা: "Add at least one line to the grid before saving."।
  - **নম্বর ও Rev:**
    - প্রথম সেভে `BFB-00001`, Rev 0।
    - পরের প্রতিটা সেভে নম্বর একই থাকে, Rev এক বাড়ে। Booking Ref#-এ দেখায় "BFB-00001 · Rev 1"।
    - সেভের পরে পাতা রিলোড না করেই url হয় `?tab=edit&ref=BFB-00001`।
  - **Add to Grid:**
    - দুই অর্ধেকই পূরণ করতে হয়: Fabric, Cons. আর GMT Item লাগে।
    - GMT Color না দিলে All? টিক; Size Range না দিলে All?; Fab. Color না দিলে "GMT Color?" টিক।
    - কিছু বাদ থাকলে বার্তা: "… is needed before the row can be added."।
  - **পিস:**
    - আসে অর্ডারের রং-সাইজ ব্রেকডাউন থেকে, GMT Color (নাম বা কোড মিলিয়ে) আর Size Range (সেই গ্রুপের সাইজ কোড) দিয়ে ছাঁকা।
    - Order# "All" টিক থাকলে স্টাইলের সব অর্ডার।
    - ব্রেকডাউন নেই এমন অর্ডার পুরো পরিমাণে ধরা হয়, শুধু All colours আর All sizes দুটোই টিক থাকলে।
  - **Finish Fab Qty:** কনজাম্পশন ÷ (Dz হলে ১২, Pcs হলে ১) × পিস × (১ + C. Wastage % ÷ ১০০) + AddQty। যেমন ১.২ kg/Dz, ১০০০ পিস, ৫ % হলে ১০৫ kg।
  - Process Data ট্যাবে প্রতিটা লাইনের GSM, Rate, P/Loss % জায়গাতেই বদলানো যায়; সেগুলো একই Save-এ যায়।
  - **Suggest:** বাছাই করা স্টাইলের CAD রেকর্ড থেকে পার্ট ধরে গড় কনজাম্পশন বসায়, বডি পার্ট আগে। CAD না থাকলে বার্তা: "No CAD consumption has been recorded for that style yet."।
  - Records-এর Remove বুকিং আর তার লাইন মুছে দেয়।

- **Approved Fabric Booking List (F2):**
  - Brickland-এ বাল্ক বুকিং অনুমোদনের কোনো পথ নেই: ফর্মে Status নেই, Approve বাটনও নেই। Buffer/Additional ফর্মে Status ঘর আছে, আর সেখানে Approved বাছলেই বুকিং তালিকায় ওঠে (অনুমোদনের তারিখ "—")।
  - ব্যবহারকারীর সিদ্ধান্ত (২০২৬-১০-১৫): বাল্ক বুকিং অনুমোদন হবে **Approval Center দিয়ে**।
  - বাল্ক শিটে "Submit for approval" বাটন আসে, শুধু সেভ হওয়া বুকিং Draft বা Rejected থাকলে।
    - শেষ সেভের পরে কিছু বদলানো থাকলে আগে সেভ করতে বলে: "Save the booking first — …"।
    - পাঠালে বুকিং Submitted হয়। কিছু বদলানো, মোছা বা আবার পাঠানো যায় না ("This booking is Submitted — it can no longer be changed or removed.")।
  - Approval Center-এ এন্ট্রি টাইপ `FABRIC_BOOKING` ("Fabric booking"):
    - চেইন যে মান পড়ে = প্রতিটা লাইনের Finish Fab Qty × Rate-এর যোগফল, বুকিংয়ের কারেন্সিতে।
    - অনুমোদনকারী নিজের পাঠানো অনুরোধে সই করতে পারেন না (ইঞ্জিনের নিয়ম)।
    - অনুরোধের লিংক সরাসরি বুকিংয়ের Edit ট্যাবে নিয়ে যায়।
  - **রায়:**
    - Approve → Approved, তারিখ আর শেষ সইকারীর নামসহ; বুকিং তালিকায় ওঠে, আর বদলায় না।
    - Reject (বা মেয়াদ পেরোনো) → Rejected; বদলে, সেভ করে আবার পাঠানো যায়।
    - অনুরোধ তুলে নিলে → Draft।
  - কোম্পানিতে ফ্যাব্রিক বুকিংয়ের কোনো চেইন না থাকলে পাঠানোর সাথে সাথেই অনুমোদিত হয় (approval_status NOT_REQUIRED, সইকারী নেই)।
  - অন্য ধরনের বুকিং (F4/F5) Brickland-এর মতো ফর্মের Status দিয়ে অনুমোদিত হবে; Approved বাছলে কে আর কখন, তা রাখা হয়। বাল্কের Status API দিয়ে বদলানো যায় না।
  - তালিকায় থাকে সব ধরনের অনুমোদিত বুকিং, সর্বশেষ অনুমোদন আগে।

- **Style-Wise Yarn Spec Confirmation (F3):**
  - Search Category (Bulk, Develop, Short-Bulk, Projection) আর Search Job/W.Style বাছাই করলে স্টাইলের বায়ার, স্টাইল আর বিবরণ দেখায়, আর সেই ক্যাটাগরির ম্যাপিং তালিকা আসে। ক্যাটাগরি বদলালে স্টাইল খালি হয়ে যায় (Brickland-এর মতো)।
  - স্টাইল বাছাইয়ের আগে ফর্ম বন্ধ থাকে।
  - **Save:** প্রতিটা টিক দেওয়া Prod Category-র জন্য আলাদা একটা ম্যাপিং হয় (Brickland-এ দেখা)। বার্তাগুলো Brickland-এর হুবহু:
    - "Fabric is needed before this can be saved."
    - "Gmt Item is needed before this can be saved."
    - "Gmt Color is needed before this can be saved." (All Col? টিক থাকলে লাগে না)
    - "Fab Color is needed before this can be saved."
    - Prod Category আর Gmt Parts-এর জন্যও একই ধাঁচ।
  - নতুন ম্যাপিং Pending। Confirm → Confirmed (কে আর কখন রাখা হয়); Release → আবার Pending।
  - নিশ্চিত ম্যাপিং মোছা যায় না: "A confirmed specification cannot be removed. Release it first."। ম্যাপিং বদলানোর পথ নেই (Brickland-এর মতো): রিলিজ করে মুছে আবার এন্ট্রি করতে হয়।
  - **Spec Confirmation তালিকা:** স্টাইল ধরে।
    - কোনো স্টাইলের ম্যাপিং থাকলে আর সবগুলো নিশ্চিত হলে Confirm; না হলে Pending (ম্যাপিং ছাড়া স্টাইলও Pending)।
    - Confirm Date = সর্বশেষ নিশ্চিত করার দিন; Order/Style = স্টাইলের অর্ডার নম্বর; Order Qty = মোট; Ship Date = প্রথম শিপমেন্ট; Modification On = স্টাইল বা ম্যাপিংয়ের শেষ বদলের দিন।
    - From/To Date এই শেষ বদলের দিন ধরে ছাঁকে। Company শুধু চলতি কোম্পানি।
    - Open ডায়ালগ বন্ধ করে সেই স্টাইলটা লোড করে।

- **Additional Fabric Booking Entry (F4):**
  - **New requisition:**
    - ঘর: Booking Date*, Style#, Job/Work Style#, Buyer A/C, Company (চলতি), Work Style#, Ref#, SP#, Ship Date, Status (Draft…Cancelled), Send Mail?, Remarks।
    - খালি Save-এ "Booking Date is required." (Brickland-এর হুবহু)।
    - Job/Work Style# বাছাই করলে স্টাইল ফাঁকা থাকলে অর্ডারেরটা বসে, শিপ তারিখও। অন্য স্টাইলের অর্ডার নিলে বার্তা আসে: "That job/work style is not of the style picked."
    - Buyer A/C ফাঁকা থাকলে স্টাইলের (বা অর্ডারের) বায়ার অ্যাকাউন্ট বসে।
    - তৈরির সময় `BK-ADD-00001`, Rev 0। সেভের পরে ফর্ম খালি হয়ে পরেরটার জন্য তৈরি থাকে (Brickland-এর মতো)।
  - **Edit requisition** ("Rewriting 16 Oct 2026. Save to keep the changes, or cancel to leave it as it was."): Update requisition প্রতিবার Rev এক বাড়ায়; Cancel Records-এ ফেরে।
  - Status Approved দিয়ে সেভ করলে কে আর কখন অনুমোদন দিলেন, তা রাখা হয়, আর বুকিং F2-এর তালিকায় ওঠে (SP# আর Send Mail?-সহ)। অন্য কোনো Status দিলে অনুমোদনের তথ্য মুছে যায়।
  - এই ধরনের বুকিং অনুমোদনের পরেও খোলা থাকে (Brickland-এর মতো Status বদলানো যায়)। শুধু বাল্ক বুকিং অনুমোদনে আটকে যায়।
  - **Booking Items:**
    - বাঁয়ে সব ধরনের বুকিং, লাইনের সংখ্যাসহ (Brickland-এর মতো Bulk আর Buffer-ও); খোঁজা যায়।
    - ডানে বাছাই করা বুকিংয়ের ফ্যাব্রিক লাইন।
    - **Add fabric line** (ডায়ালগ) লাইনটা সাথে সাথে সেভ করে। লাগে Fabric Type ("Fabric Type is required.") আর Qty ("Qty is required.")।
    - রং বাছাই করলে রঙের নাম বসে, বদলানো যায়। UOM না দিলে Kg।
    - Process Loss % ফাঁকা থাকলে Dyeing Process Loss Norms (`dyeing_loss_bands`) থেকে বসে: রঙের ডাই কালার গ্রুপ (White, Light, Medium, Dark…) আর GSM যে ব্যান্ডে পড়ে, তার শতাংশ। ফ্যাব্রিকের নিজের ব্যান্ড আগে, সব ফ্যাব্রিকের ব্যান্ড পরে; কিছু না মিললে ০।
    - বাকি ঘর (Colour, GSM, DIA, Qty, UOM, Rate, P/Loss %) জায়গায় বদলে **Save**; বদলানো লাইনে "Edited"। সরানো লাইন Save-এ মোছে। Revert আগের অবস্থায় ফেরায়।
    - বাল্ক বুকিংয়ের লাইন শুধু দেখানো হয়। সেগুলো Bulk Fabric Booking Entry-তে হিসাব করে বসে, তাই এখানে যোগ করা বা বদলানো যায় না।

- **Purchase Buffer Booking (F5):** F4-এর মতো একই রিকুইজিশন আর একই Booking Items প্যানেল, শুধু ধরন BUFFER। পার্থক্যগুলো Brickland-এ দেখা:
  - নম্বর `BK-BUF-00001` থেকে, Additional-এর নম্বর থেকে আলাদা গোনা হয়।
  - ট্যাব আর বাটনের নাম: New buffer order / Edit buffer order, Save buffer order / Update buffer order।
  - Records-এ ফিল্টার নেই, শুধু খোঁজা। সারিতে Edit আর মোছা দুটোই আছে।
  - মোছার আগে নিশ্চিতকরণ চাওয়া হয়: "The buffer order and its fabric lines are removed."। মুছলে লাইনও মোছে।

- **Imported Fabric Booking (F6):**
  - **Records:** কনফার্মড অর্ডার (CONFIRMED আর IN_PRODUCTION), সবচেয়ে আগে যার শিপমেন্ট সেটা আগে।
    - Lead Time = কনফার্মের তারিখ থেকে শিপমেন্ট পর্যন্ত দিন।
    - সারিতে কোনো অ্যাকশন নেই (Brickland-এর মতো)।
  - **Bookings:**
    - বাঁয়ে অর্ডার (অর্ডার / স্টাইল, শিপমেন্টের তারিখ, আমদানি বুকিংয়ের সংখ্যা)।
    - ডানে বাছাই করা অর্ডারের আমদানি বুকিং।
  - **Raise booking (ডায়ালগ):**
    - ঘর: Booking Date* ("Booking Date is required."), Ref# (এলসি রেফারেন্স), SP#, Ship Date, Remarks।
    - অর্ডারের বিপরীতে সাথে সাথে সেভ হয়; নম্বর `BK-IMP-00001`।
    - স্টাইল, বায়ার অ্যাকাউন্ট আর কারেন্সি অর্ডার থেকে আসে।
    - শুধু কনফার্মড অর্ডারে তোলা যায়: "An import booking is raised only against a confirmed order."
  - **Approve:** একবার চাপলেই Approved (কে আর কখন রাখা হয়), আর বুকিং F2-এর তালিকায় ওঠে। তারপর Approve আর Remove দুটোই বন্ধ, আর Booking Items প্যানেলে লাইনও আর বদলানো যায় না ("This booking is approved and closed to changes.")।
  - **Remove:** শুধু খসড়ায়, নিশ্চিত করার পরে ("Remove this booking? The booking and its fabric lines are removed.")। অনুমোদিত বুকিংয়ে: "An approved import booking cannot be removed."
  - ফ্যাব্রিকের লাইন যোগ হয় F4/F5-এর Booking Items প্যানেলে, যেখানে সব ধরনের বুকিং দেখা যায়।

## ৬. API ও পারমিশন

- `GET /merchandise/fabric-bookings/options`: ইউজার, বায়ার অফিস, স্টাইল (আইটেমসহ), অর্ডার, ফ্যাব্রিক, পার্ট, রং, সাইজ গ্রুপ আর সব enum।
- `GET /merchandise/fabric-bookings/suggest?style_id=`: CAD রেকর্ড আর পার্ট ধরে গড়।
- `GET /merchandise/fabric-bookings?type=BULK` (Records) আর `POST /merchandise/fabric-bookings?type=BULK` (নতুন বুকিং)।
- `GET/PUT/DELETE /merchandise/fabric-bookings/{booking_no}`: url-এ Booking Ref#, ULID নয়।
- `POST /merchandise/fabric-bookings/{booking_no}/submit` (F2): Approval Center-এ পাঠায়; Submitted বা Approved বুকিংয়ে PUT/DELETE 422 দেয়।
- `GET /merchandise/fabric-bookings/approved` (F2): সব ধরনের অনুমোদিত বুকিং, অর্ডার, জব, স্টাইল, শিপ তারিখ আর মেইলের তথ্যসহ।
- Approval Center: `ApprovalEntities`-এ `FABRIC_BOOKING` → `FabricBooking` (Approvable); Workflow Designer-এ এই এন্ট্রি টাইপে চেইন বানানো যায়।
- `GET /merchandise/yarn-specs/options`, `GET /merchandise/yarn-specs?style_id=&category=`, `POST /merchandise/yarn-specs` (প্রতিটা ক্যাটাগরির জন্য একটা), `POST /merchandise/yarn-specs/{id}/confirm`, `POST /merchandise/yarn-specs/{id}/release`, `DELETE /merchandise/yarn-specs/{id}`, `GET /merchandise/yarn-specs/confirmation?state=&buyer_id=&style_id=&from=&to=` (F3)।
- **F4 রিকুইজিশন:** `GET /merchandise/fabric-requisitions/options`, `GET/POST /merchandise/fabric-requisitions?type=ADDITIONAL`, `GET/PUT /merchandise/fabric-requisitions/{booking_no}` (শুধু ADDITIONAL/BUFFER)।
- **F5:** `GET/POST /merchandise/fabric-requisitions?type=BUFFER`, `DELETE /merchandise/fabric-requisitions/{booking_no}` (শুধু ADDITIONAL/BUFFER; বাল্কে 404)। Records-এর সারিতে এখন `buyer` (স্টাইলের বায়ার) আর `style` (স্টাইল নম্বর)-ও আসে।
- **F6:** `GET /merchandise/import-bookings/orders`, `GET /merchandise/import-bookings?order_id=`, `POST /merchandise/import-bookings`, `POST /merchandise/import-bookings/{booking_no}/approve`, `DELETE /merchandise/import-bookings/{booking_no}`।
- **F4 Booking Items:** `GET /merchandise/fabric-booking-items` (সব বুকিং), `GET /merchandise/fabric-booking-items/{booking_no}` (লাইন, ফ্যাব্রিক আর রং), `POST …/{booking_no}` (একটা লাইন যোগ), `PUT …/{booking_no}` (জায়গায় বদল আর সরানো)।
- পারমিশন: `merchandise.booking.view`, `merchandise.booking.manage`। FACTORY_ADMIN দুটোই পায়; FACTORY_MANAGER আর STORE_MANAGER শুধু view।
- মেনু: ফোল্ডার `MERCHANDISE.FABRIC_BOOKING` ("FABRIC BOOKING"), ভেতরে `MERCHANDISE.BULK_FABRIC_BOOKING` → `/merchandise-desk/bulk-fabric-booking` `MERCHANDISE.APPROVED_FABRIC_BOOKING` → `/merchandise-desk/fabric-booking-list-approved` (শুধু view) `MERCHANDISE.YARN_SPEC_CONFIRM` → `/merchandise-desk/style-wise-yarn-spec-confirm` `MERCHANDISE.ADDITIONAL_FABRIC_BOOKING` → `/merchandise-desk/additional-fabric-booking` `MERCHANDISE.BUFFER_BOOKING` → `/merchandise-desk/buffer-booking-purchase` আর `MERCHANDISE.IMPORT_FABRIC_BOOKING` → `/merchandise-desk/import-fabric-booking-purchase`।

## ৭. Frontend পেজ (লাইট থিম)

- **Bulk Fabric Booking Entry** (`/merchandise-desk/bulk-fabric-booking`):
  - **Records:**
    - ফিল্টার: Buyer A/C, Style# / Job/Work Style# আর Order# (লিখে Enter), Booking Date from/to, Status।
    - কলাম: Booking Date, Work Style#, Order#, Booking Ref#, Rev#, Buyer A/C, Ship.Date, Items, Qty, Status।
    - সারিতে Edit (ট্যাব খোলে) আর Remove।
  - **New Booking / Edit booking BFB-…** (`?tab=new`, `?tab=edit&ref=BFB-00001`):
    - Key Information: Booking Ref#, Date*, Program By, Buyer A/C, Job/Working Style*, Style Desc, Order#*, Currency*, Conv. Loc.*, Item/Model #* (স্টাইলের আইটেম থেকে প্রস্তাব), Cutting / YD / AOP Wastage %।
    - নিচে দুটো লোকাল ট্যাব; বদলালে পাতা যায় না।
  - **Consumption Entry ট্যাব:**
    - বাঁয়ে "Dia wise CAD Consumption": Knit / Woven / Trims & Accessories, Suggest, Fabric*, GMT Part, Finish Dia + Inch/Cm + Open/Tube, Cons.* + Kg/Yds/Mtr + Per Dz/Pcs।
    - ডানে "Applicable GMT Qty & Fabric Color": Order# All, GMT Item*, GMT Color* + All, Size Range* + All, Fab. Color* + GMT Color?, Color Spec. + Contrast?, C. Wastage %*, AddQty, Disp Order, Add to Grid।
    - তার নিচে গ্রিড: Disp Ord, GMT Part, Dia, Cons/Doz, GMT Item, GMT Color, Fab. Color, Size, Add Fin FabQty, Action।
  - **Process Data ট্যাব:** Fabric, GMT Part, GSM, Rate, P/Loss %।
  - একেবারে নিচে Save, Cancel (Records-এ ফেরে), Reset আর "n line(s) on the booking."।

- **Bulk শিটে F2-এর যোগ:**
  - সেভ হওয়া Draft/Rejected বুকিংয়ে Reset-এর পাশে "Submit for approval"।
  - অবস্থার নোটিশ:
    - Submitted: "With the approvers in the Approval Center — …"
    - Approved: "Approved on 16 Oct 2026 by … — the booking is on the Approved Fabric Booking List and closed to changes."
    - Rejected: "Rejected by the approvers — …"
  - Submitted বা Approved থাকলে সব ঘর বন্ধ; Add to Grid, লাইন সরানো, Save আর Reset লুকানো, শুধু Cancel থাকে।
- **Approved Fabric Booking List** (`/merchandise-desk/fabric-booking-list-approved`): Brickland-এর মতো একটাই ভিউ, ট্যাব নেই।
  - ফিল্টার: Type (All, Bulk, Additional, Import, Buffer, Projection, Sample), Buyer A/C, Job/Work Style#, Shipment Month from/to, Approve from/to, Send Mail? (All/Yes/No)।
  - কলাম: Type, Aprov.Date | Prg/Rev Date ("15 Oct 2026 | 15 Oct 2026 (r1)"), Ship.Date, Buyer A/C | Style#, Job# | Order#, Ref# | SP#, Send Mail?, Send Date।
  - সার্চ আর Export আছে; ACTION কলাম নেই (Brickland-এর মতো)।

- **Style-Wise Yarn Spec Confirmation** (`/merchandise-desk/style-wise-yarn-spec-confirm?category=BULK&style=FLOW-STYLE-01`): Brickland-এর মতো একটাই কাজের পাতা, ট্যাব নেই। url-এ স্টাইল নম্বর থাকে, ULID নয়; সব কাজ রিলোড ছাড়া।
  - **উপরের অংশ:** Spec Confirmation বাটন, Search Category, Search Job/W.Style, আর Buyer, Style, Style Description।
  - **বাঁয়ে "Yarn / Fabric Mapping"** (সংখ্যাসহ):
    - কলাম: Cat (+ Pending/Confirmed), Ratio/Fab Color, Fabric, Item (নিচে পার্টসের নাম), Gmt Color ("All" যখন All Col?), Action।
    - Action-এ Confirm, Release আর মোছার আইকন।
    - খালি থাকলে "Nothing mapped yet" আর পরামর্শ।
  - **ডানে "Yarn/Fabric Mapping Form":**
    - New Entry (ফর্ম খালি করে)।
    - ঘর: Prod Category* (একাধিক, শুরুতে চারটাই), Fabric*, Gmt Item* (স্টাইলের আইটেম থেকে প্রস্তাব), Gmt Parts* (একাধিক), Gmt Color* (একাধিক), All Col?, Fab Color* (একাধিক)।
    - নিচে Brickland-এর লেখা আর Save।
  - **Spec Confirmation ডায়ালগ ("Yarn Spec Confirmation List"):**
    - ফিল্টার: From/To Date, Confirm/Pending? (শুরুতে Pending), Buyer, Job /Work Style#, Company; তারপর Search।
    - কলাম: Status, Buyer, Job./W.Style, Style Desc., Order/Style, Order Qty, Confirm Date, Ship Date, Modification On, Act (Open)।

- **Additional Fabric Booking Entry** (`/merchandise-desk/additional-fabric-booking`): ট্যাব Records, Booking Items, New requisition / Edit requisition।
  - **Records:**
    - ফিল্টার: Buyer A/C, Job/W Style# (লিখে Enter)।
    - কলাম: Req Date, Req#, Rev#, Ref#, Buyer A/C, Job/Work Style#, Order#, Actv?, Status।
    - সারিতে শুধু Edit (`?tab=edit&ref=BK-ADD-00001`); Brickland-এর মতো Remove নেই।
  - **Booking Items** (`?tab=items&booking=BK-ADD-00001`):
    - বাঁয়ে "Bookings" (সংখ্যা, খোঁজা, প্রতিটায় নম্বর, ধরন, লাইনের সংখ্যা)।
    - ডানে "Fabric — BK-ADD-00001": Add fabric line, তারপর গ্রিড (Fabric, Colour, GSM, DIA, Qty, UOM, Rate, P/Loss %, Status, Action)।
    - নিচে "n fabric lines. The process-loss percentage defaults from the dyeing chart…", Revert আর Save। বুকিং বদলালে পাতা রিলোড হয় না।
  - **New requisition / Edit requisition:** তিন কলামে উপরের ঘরগুলো; নিচে Reset + Save requisition, অথবা Cancel + Update requisition।

- **Purchase Buffer Booking** (`/merchandise-desk/buffer-booking-purchase`): ট্যাব Records, Booking Items, New buffer order / Edit buffer order।
  - Records-এর কলাম: Buyer Name, Style #, Order #, Order Type ("Buffer Order"), Reference No (বুকিং নম্বর), Reference Date (বুকিংয়ের তারিখ), Status।
  - সারিতে Edit আর মোছা। ফিল্টার নেই।
  - ফর্ম আর Booking Items F4-এর মতোই, একই কম্পোনেন্ট দিয়ে।

- **Imported Fabric Booking** (`/merchandise-desk/import-fabric-booking-purchase`): ট্যাব Records আর Bookings।
  - **Records:**
    - ফিল্টার: Buyer A/C, Style/Order আর Order # (লিখে Enter), Order Type, Shipment from/to।
    - কলাম: Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, Order Conf. Date, Shipment Date, Lead Time।
  - **Bookings** (`?tab=bookings&order=<অর্ডার নম্বর>`):
    - বাঁয়ে "Orders" (খোঁজা যায়)।
    - ডানে "Import Fabric Bookings — অর্ডার / স্টাইল": Export আর Raise booking।
    - কলাম: Booking Ref#, Booking Date, Rev#, Ref#, Ship Date, Items, Status, Action (Approve, Remove)।
    - নিচে "n booking(s). The fabric lines are added on the Booking Items panel."।
    - অর্ডার বদলালে পাতা রিলোড হয় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| F1 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_19_000100_create_merchandise_fabric_bookings.php`, `Models/FabricBooking.php`, `Models/FabricBookingLine.php`, `Http/Controllers/Api/FabricBookingApiController.php`, `Routes/api.php` |
| F1 পারমিশন ও মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` |
| F1 ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/booking.js` (`bulkBooking`), `page.js` (BOOKING_SCREENS / BOOKING_MANAGE), `actions.js` (`saveFabricBooking`, `deleteFabricBooking`, `suggestFabricBooking`), `frontend/src/components/merchandise/fabric-booking-sheet.jsx`, `frontend/src/components/ui/select.jsx` (aria-label এখন ট্রিগারে) |
| F1 ভাষা | `lang/en/merchandise.php`, `lang/bn/merchandise.php` (`master.bfb`, `booking.*`, ফোল্ডার আর স্ক্রিনের নাম/বিবরণ) |
| F1 টেস্ট | `tests/Feature/Merchandise/MerchandiseFabricBookingTest.php` |
| F2 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_19_000200_add_approval_to_merchandise_fabric_bookings.php`, `Models/FabricBooking.php` (Approvable, LOCKED, SCREENS), `FabricBookingApiController.php` (`submit`, `approved`, লক, `statusOf`), `Routes/api.php` |
| F2 Approval Center | `app/Modules/Approval/Support/ApprovalEntities.php`, `app/Modules/Approval/Models/ApprovalWorkflow.php` (ENTITY_TYPES), `frontend/src/app/(app)/approval-center/[screen]/screens.js`, `lang/{en,bn}/approval.php` (`center.entities.FABRIC_BOOKING`) |
| F2 ফ্রন্টএন্ড ও মেনু | `booking.js` (`approvedList`), `actions.js` (`submitFabricBooking`), `fabric-booking-sheet.jsx` (Submit, অবস্থার নোটিশ, লক), `MenuRegistry.php`, `lang/{en,bn}/merchandise.php` (`master.abl`, `bfb.submit`/`state`, `booking.locked`) |
| F3 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_19_000300_create_merchandise_yarn_specs.php`, `Models/YarnSpec.php`, `Http/Controllers/Api/YarnSpecApiController.php`, `Routes/api.php` |
| F3 ফ্রন্টএন্ড ও মেনু | `frontend/src/components/merchandise/yarn-spec-desk.jsx` (`YarnSpecDesk`, `ConfirmationList`), `frontend/src/components/ui/toast.jsx` (অজানা ধরনে info), `booking.js` (`yarnSpec`), `actions.js` (`loadYarnSpecs`, `saveYarnSpec`, `confirmYarnSpec`, `releaseYarnSpec`, `deleteYarnSpec`, `searchYarnSpecConfirmation`), `MenuRegistry.php`, `lang/{en,bn}/merchandise.php` (`master.ysc`, `yarn_spec`) |
| F3 টেস্ট | `tests/Feature/Merchandise/MerchandiseYarnSpecTest.php` |
| F4 ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_19_000400_add_requisition_fields_to_merchandise_fabric_bookings.php`, `Models/FabricBooking.php` (BK-ADD- prefix, নতুন ঘর, `isLocked` শুধু বাল্কে), `Models/FabricBookingLine.php` (uom), `Http/Controllers/Api/FabricRequisitionApiController.php`, `Routes/api.php` |
| F4 ফ্রন্টএন্ড ও মেনু | `frontend/src/components/merchandise/requisition-form.jsx`, `frontend/src/components/merchandise/booking-items-panel.jsx`, `booking.js` (`requisitionScreen`; F5 এটাই ব্যবহার করবে), `actions.js` (`saveFabricRequisition`, `loadBookingItems`, `addBookingItem`, `saveBookingItems`), `MenuRegistry.php`, `lang/{en,bn}/merchandise.php` (`master.afb`, `master.bki`, `requisition`) |
| F5 | `FabricRequisitionApiController.php` (`destroy`, Records-এ buyer/style, বুকিংয়ের তালিকা id ধরে সাজানো), `FabricBooking.php` (SCREENS-এ BUFFER), `booking.js` (`requisitionScreen`-এ buffer লেআউট), `actions.js` (`deleteFabricRequisition`), `MenuRegistry.php`, `lang/{en,bn}/merchandise.php` (`master.pbb`) |
| F6 | `app/Modules/Merchandise/Http/Controllers/Api/ImportBookingApiController.php`, `Models/FabricBooking.php` (BK-IMP- prefix, অনুমোদিত IMPORT লক), `FabricRequisitionApiController.php` (লক করা বুকিংয়ের আলাদা বার্তা), `Routes/api.php`, `frontend/src/components/merchandise/import-booking-desk.jsx`, `booking.js` (`importBooking`), `actions.js` (`loadImportBookings`, `raiseImportBooking`, `approveImportBooking`, `removeImportBooking`), `MenuRegistry.php`, `lang/{en,bn}/merchandise.php` (`master.ifb`, `import_booking`, `requisition.locked`), `tests/Feature/Merchandise/MerchandiseImportBookingTest.php` |
| F4 টেস্ট | `tests/Feature/Merchandise/MerchandiseFabricRequisitionTest.php` |
| টুল | `AnnotechERP/tools/erp_crawler/panel.mjs` (`html` স্টেপ), `AnnotechERP/tools/app_check/ui.mjs` (`AS_USER`/`AS_PASS` দিয়ে অন্য ইউজার, `ERRLINES`) |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- F2–F6 একই `merch_fabric_bookings` টেবিল আর `FabricBookingApiController` (`?type=`) ব্যবহার করবে; `booking.js`-এ নতুন স্ক্রিন যোগ হবে।
- F4/F5-এ ফর্মের Status আর Send Mail? ঘর `statusOf` আর `send_mail` কলাম ব্যবহার করবে; তাদের অনুমোদিত বুকিং F2-তে নিজে থেকেই উঠবে (`SCREENS`-এ স্ক্রিন যোগ করতে হবে)।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseFabricBookingTest`** (৩টা টেস্ট, ৪৮ assertion) যা ঢাকে:
  - বাধ্যতামূলক ঘর আর খালি গ্রিডের বার্তা।
  - BFB-00001 নম্বর আর প্রতি সেভে Rev বাড়া।
  - রং, সাইজ গ্রুপ আর সব অর্ডার ধরে পিস ও Finish Fab Qty।
  - ধরন অনুযায়ী Records, আর মোছা।
  - CAD থেকে Suggest।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার শুধু পড়তে পারেন।
- **F2 টেস্ট** (একই ফাইলে আরও ২টা; এখন মোট ৫টা টেস্ট, ৯২ assertion):
  - চেইনসহ: Submit → Submitted/PENDING; Approval Center-এ মান ১৩৯.৩ USD।
  - অপেক্ষার সময় PUT/DELETE/আবার Submit 422।
  - ম্যানেজার Reject → Rejected; বদলে আবার Submit; Approve → Approved, সইকারীর নামসহ।
  - তালিকায় BULK · BFB-00001 · Rev 1 · 637PK · Job 529। ম্যানেজার তালিকা পড়তে পারেন, কিন্তু পাঠাতে পারেন না।
  - চেইন ছাড়া: সাথে সাথে Approved (NOT_REQUIRED), আর মোছা যায় না।
  - `tests/Feature/Approval`, `ApiGateCoverageTest`, `AuditLogApiTest`: ৪৭টা টেস্ট সবুজ।
- **F6 টেস্ট** `MerchandiseImportBookingTest` (২টা টেস্ট, ৩৯ assertion):
  - Records-এ শুধু কনফার্মড অর্ডার, Lead Time ২৪ দিন (৮ অক্টোবর থেকে ১ নভেম্বর)।
  - Booking Date বার্তা; খসড়া অর্ডারে বুকিং তোলা যায় না।
  - BK-IMP-00001, তারপর 00002; অর্ডারে বুকিংয়ের সংখ্যা ২।
  - খসড়ায় লাইন যোগ করা যায়; খসড়া মোছা যায়।
  - Approve → লক: মোছা আর লাইন যোগে বার্তা, প্যানেলে editable = false।
  - F2-এর তালিকায় IMPORT · SP-IMP-1 · অর্ডার আর জব।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার শুধু পড়তে পারেন।
  - গেট কভারেজ + F1/F3/F4/F5/F6 একসাথে: ১৬টা টেস্ট, ২৭৪ assertion সবুজ।
- **F6 ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - Records-এ Brickland-এর বিবরণ আর কলাম; dev অর্ডার "Order · FLOW STYLE-01 · Bulk New · 1000" ✔
  - Bookings-এ "Order / FLOW STYLE-01 · 12 Jan 2027 · 0" → খালি নোটিশ, url `?tab=bookings&order=Order` ✔
  - খালি Raise-এ "Booking Date is required." ✔; LC-IMP-1 · SP-IMP-1 · 20 Dec 2026 দিয়ে BK-IMP-00001 ✔
  - Booking Items প্যানেলে BK-IMP-00001-এ Interlock · 400 · 5.2 → Items ১ ✔
  - Approve → Approved, Approve আর Remove দুটোই বন্ধ ✔
  - BK-IMP-00002 তুলে নিশ্চিত করে মোছা ✔
  - Approved তালিকায় "Import · 16 Oct 2026 | 16 Oct 2026 (r0) · … · BK-IMP-00001 | SP-IMP-1" ✔
  - BK-IMP-00001 আর তার লাইন dev DB-তে রাখা আছে।
- **F5 টেস্ট** (একই ফাইলে আরও ১টা; ফাইলে এখন ৩টা টেস্ট, ৭১ assertion):
  - Additional থাকার পরেও প্রথম buffer `BK-BUF-00001`, তারপর `BK-BUF-00002`।
  - Records-এ বায়ার "LPP Spolka Akcyjna", স্টাইল 637PK, অর্ডার নম্বর।
  - লাইনসহ মুছলে লাইনও যায়; বাল্ক বুকিং এই পথে মোছা যায় না (404)।
  - গেট কভারেজ + F1/F3/F4/F5 একসাথে: ১৪টা টেস্ট, ২৩৫ assertion সবুজ।
- **F5 ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - খালি Records আর Brickland-এর বিবরণ ✔
  - Style# FLOW STYLE-01 · BUF-REF-1 · Approved দিয়ে সেভ; তারপর অর্ডার দিয়ে BUF-REF-2 ✔
  - Records-এ "LPP Spolka Akcyjna · FLOW STYLE-01 · Order · Buffer Order · BK-BUF-00002 · Draft" আর "… — · BK-BUF-00001 · Approved"; কলামের নাম Brickland-এর মতো ✔
  - Edit-এ "Rewriting 16 Oct 2026", SP# SP-BUF-7 দিয়ে Update ✔
  - Booking Items-এ BK-BUF-00001-এ Interlock · Navy Blue · 80 · 3.6 ✔
  - BK-BUF-00002 মোছার সময় নিশ্চিতকরণ "…The buffer order and its fabric lines are removed." → মোছা গেছে ✔
  - Approved তালিকায় "Buffer · … · BK-BUF-00001 | SP-BUF-7 · No" ✔
  - BK-BUF-00001 আর তার লাইন dev DB-তে রাখা আছে।
- **F4 টেস্ট** `MerchandiseFabricRequisitionTest` (২টা টেস্ট, ৫৪ assertion):
  - Booking Date বার্তা; অন্য স্টাইলের অর্ডারে বার্তা।
  - অর্ডার থেকে স্টাইল আর বায়ার অ্যাকাউন্ট আসে; BK-ADD-00001, Rev 0, তারপর আপডেটে Rev 1।
  - Records-এর ঘরগুলো; BUFFER তালিকা খালি।
  - লাইনের দুটো বার্তা।
  - প্রসেস লস: Single Jersey 160 GSM কালো (Dark) → ফ্যাব্রিকের নিজের ব্যান্ড ৯.৫; 190 GSM → সব ফ্যাব্রিকের ব্যান্ড ১২। রঙের নাম বদলানো যায়, UOM Yds।
  - Save-এ পরিমাণ/রেট/লস বদলায় আর বাদ দেওয়া লাইন মোছে; অন্য কোথাও মোছা লাইনে বার্তা আসে।
  - Approved → F2-এর তালিকায় (SP-9, Send Mail হ্যাঁ, আজকের তারিখ), তারপরও লাইন যোগ করা যায়।
  - বাল্ক বুকিং প্যানেলে শুধু দেখা যায় (লাইন যোগ করলে বার্তা); রিকুইজিশনের পথে বাল্ক পাওয়া যায় না (404)।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার শুধু পড়তে পারেন।
- **F4 ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - Records খালি ✔; খালি Save-এ "Booking Date is required." ✔
  - তারিখ, Job/Work Style# (FLOW STYLE-01 অর্ডার), Ref# ADD-REF-1, SP# SP-101, Send Mail?, Remarks দিয়ে Save → Records-এ "BK-ADD-00001 · Rev 0 · ADD-REF-1 · LPP S.A. · FLOW STYLE-01 · Order · Yes · Draft" ✔
  - Edit-এ "Rewriting 16 Oct 2026" ✔; Status Approved → Update ✔
  - Booking Items-এ BK-ADD-00001 (Additional, ০) আর BFB-00001 (Bulk, ২) ✔; খালি নোটিশ ✔
  - Add fabric line: Interlock · Navy Blue · 180 GSM · 70 DIA · 120 · 3.8; তারপর Lycra Single Jersey · Off White · 30 Yds ✔
  - Qty 120 → 150 করলে "Edited", Save-এর পরে 150 ✔
  - BFB-00001 শুধু দেখা যায় ("…its lines are shown here only") ✔
  - Approved তালিকায় "Additional · 16 Oct 2026 | 16 Oct 2026 (r1) · … · BK-ADD-00001 | SP-101 · Yes" ✔
  - DB-তে দুটো লাইন (150 Kg, 30 Yds)। Navy Blue-র কোনো কালার গ্রুপ নেই, তাই প্রসেস লস ০। ডাটা রাখা আছে।
- **F3 টেস্ট** `MerchandiseYarnSpecTest` (২টা টেস্ট, ৫৫ assertion):
  - options: ক্যাটাগরি, স্টাইল/বায়ার/বিবরণ, "Black (COL-BLK)", কোম্পানি।
  - চারটা "is needed" বার্তা; All Col? দিয়ে গার্মেন্টের রং ছাড়া সেভ।
  - এক Save-এ BULK আর DEVELOP দুটো ম্যাপিং।
  - Confirm (সইকারীর নাম) → মোছা আটকায়।
  - তালিকা: সব নিশ্চিত হওয়ার আগে Pending, তারপর Confirm (Order Qty 100, Confirm Date আজ); ম্যাপিং ছাড়া স্টাইল Pending; From আগামীকাল দিলে খালি।
  - Release → Pending, তারপর মোছা যায়।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার শুধু পড়তে পারেন।
- **F3 ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - স্টাইল ছাড়া পাতা: "Pick a search job/w.style…" ✔
  - FLOW STYLE-01 বাছাই → Buyer LPP, Style, Flow tee; url `?category=BULK&style=FLOW-STYLE-01` ✔
  - খালি Save → শুধু "Fabric is needed before this can be saved." ✔ (প্রথম যা বাকি, Brickland-এর মতো একটা করে)
  - Fabric/Item/Parts দিয়ে Save → "Gmt Color is needed…" ✔
  - Interlock · FLOW TEE · Body · Navy Blue দিয়ে Save → চারটা ক্যাটাগরিতে চারটা ম্যাপিং; Bulk তালিকায় সারি ✔
  - Confirm → Confirmed ✔; নিশ্চিত সারি মুছলে "A confirmed specification cannot be removed. Release it first." ✔
  - New Entry → Lycra Single Jersey · Collar · All Col? · Navy Blue → Gmt Color-এ "All" ✔
  - Spec Confirmation → Search → "Pending · LPP Spolka Akcyjna · FLOW STYLE-01 · Flow tee · Order · 1000 · — · 12 Jan 2027 · 15 Oct 2026 · Open" ✔; Open স্টাইলটা লোড করে ✔
  - ক্যাটাগরি Develop করলে স্টাইল খালি হয় ✔; আবার স্টাইল → Develop-এর দুটো সারি; Interlock Confirm ✔
  - DB-তে ৮টা ম্যাপিং (Bulk আর Develop-এর Interlock নিশ্চিত); ডাটা রাখা আছে।
  - একটা বাগ পাওয়া আর ঠিক করা: টোস্টে `type: "error"` দিলে শেয়ার্ড `Toast` পুরো পাতা ভেঙে দিত (আইকন undefined)। এখন অজানা ধরন info-তে পড়ে, আর এই স্ক্রিন "warning" দেয়।
- **F2 ব্রাউজার যাচাই** (২০২৬-১০-১৬):
  - dev কোম্পানিতে `FABRIC_BOOKING` চেইন (Factory Manager সই করেন) রাখা আছে।
  - তালিকা প্রথমে খালি ✔
  - BFB-00001-এ Item/Model বদলে Submit → "Save the booking first" ✔; Reset করে Submit → Submitted নোটিশ ✔
  - Approval Center → Pending Requests-এ "BFB-00001 · Fabric booking · 472.5 USD · Factory Manager" ✔
  - manager@delta.test (Nasrin Akter) Open → মন্তব্য → Approve ✔
  - owner-এর শিটে "Approved on 16 Oct 2026 by Nasrin Akter …", সব ঘর বন্ধ, শুধু Cancel ✔; Records-এ Status Approved ✔
  - তালিকায় সারি "Bulk · 15 Oct 2026 | 15 Oct 2026 (r1) · 12 Jan 2027 · LPP S.A. | FLOW STYLE-01 · 252525 | Order · BFB-00001 | — · No · —" ✔
  - ফিল্টার: Additional → খালি; Bulk + Send Mail Yes → খালি; Bulk + No → সারি ✔
  - ডাটা dev DB-তে রাখা আছে।
- **F1 ব্রাউজার যাচাই** (২০২৬-১০-১৫, `AnnotechERP/tools/app_check/ui.mjs`, owner লগইন):
  - খালি Save-এ বার্তা ✔
  - CAD ছাড়া Suggest-এ বার্তা ✔
  - Fabric ছাড়া Add to Grid-এ বার্তা ✔
  - FLOW STYLE-01 / Order দিয়ে দুটো লাইন ✔: Interlock · Body 1.2 Kg/Dz → ১০৫ kg; Lycra Single Jersey · Collar 0.2 Kg/Dz + AddQty 1.5 → ১৯ kg
  - Process Data-এ GSM 220, Rate 4.5, P/Loss 8 ✔
  - Save → BFB-00001, আবার Save → Rev 1 ✔
  - Records-এ সারি (Items 2, Qty 124) ✔
  - Edit ট্যাবে Process Data সহ সব ফিরে আসে ✔
  - ডাটা dev DB-তে রেখে দেওয়া আছে।

## ১০. খোলা প্রশ্ন

- Brickland-এ Records-এর Edit বাটনে কিছু হয় না। এখানে Edit ট্যাব খোলে।
- Finish Fab Qty-র সূত্র Brickland-এ দেখা যায়নি, কারণ সেখানে লাইভ ডাটা নেই। এখানে ধরা হয়েছে কনজাম্পশন × পিস × (১ + কাটিং ওয়েস্টেজ) + AddQty।
- YD / AOP ওয়েস্টেজ এখন শুধু সংরক্ষিত হয়, হিসাবে ঢোকে না। SME নিশ্চিত করবেন।

- Send Mail? আর Send Date এখন শুধু দেখানো হয়: বাল্কে "No" আর "—"। মেইল কাকে আর কখন যাবে, তা F4/F5 (যাদের ফর্মে Send Mail? আছে) বানানোর সময় ঠিক হবে।
- Brickland-এর তালিকায় Buffer বুকিং অনুমোদনের তারিখ ছাড়াই ("—") আসে, কারণ সেটা ফর্মের Status দিয়ে Approved করা। এখানে Status দিয়ে Approved করলেও তারিখ রাখা হয়।

## ১১. As-built নোট

- **F1 (২০২৬-১০-১৫):**
  - Buyer A/C বাধ্যতামূলক নয়, Brickland-এর মতোই; ফাঁকা রাখলে স্টাইলেরটা বসে।
  - Brickland-এ Suggest খালি ফেরে; এখানে এটা CAD Consumption থেকে পড়ে।
  - বুকিং মোছায় এখনো কোনো বাধা নেই। অনুমোদন (F2) এলে অনুমোদিত বুকিং মোছা আটকানো হবে।
- **শেয়ার্ড `Select`:** `aria-label` এখন আসল ট্রিগার বাটনে যায়; আগে এটা রুটে পড়ে হারিয়ে যেত। ফলে স্ক্রিন রিডার আর ব্রাউজার টেস্ট সিলেক্টকে নাম ধরে খুঁজে পায়।
- **Base UI `Checkbox`:** মোড়ানো `<label>` থাকলে এটা `aria-labelledby` বসায়, আর সেটা `aria-label`-কে ছাপিয়ে যায়। তাই টেস্টে চেকবক্স ধরা হয় `[aria-label=…]` দিয়ে।
- **F2 (২০২৬-১০-১৬):**
  - অনুমোদন Approval Center দিয়ে (ব্যবহারকারীর সিদ্ধান্ত)। এটাই Brickland-এর বাইরের একমাত্র বাটন ("Submit for approval"), কারণ Brickland-এ বাল্ক বুকিং অনুমোদনের কোনো পথ নেই।
  - Approval Center-এর অনুরোধের ডায়ালগ dev-এ প্রথমবার খুলতে কয়েক সেকেন্ড "Loading…" দেখায় (Turbopack কম্পাইল); তারপর স্বাভাবিক।
  - Ship.Date আর বাকি তারিখ Brickland-এর মতো "12 Jan 2027" ধাঁচে লেখা।
- **F3 (২০২৬-১০-১৬):**
  - Brickland-এর ম্যাপিং টেবিলে প্রতিটা সারিতে আর হেডারে চেকবক্স আছে, কিন্তু টিক দিলে কিছু হয় না (কোনো একসাথে-কাজের বাটন নেই)। তাই এখানে চেকবক্স রাখা হয়নি।
  - Brickland টেবিলে Gmt Parts দেখায় না; এখানে Item-এর নিচে ছোট করে দেখানো হয়।
  - Brickland-এর তালিকায় একটা ম্যাপিং নিশ্চিত হলেও স্টাইল "Pending" দেখায়, অথচ "Confirm" ফিল্টারে আসে। এখানে নিয়মটা একটাই: সব ম্যাপিং নিশ্চিত হলে Confirm।
  - Brickland-এ কোনো স্টাইল বাছাই করার সময় non-GET অনুরোধ আটকালে পাতা ভেঙে যায় ("This page couldn't load"); তাই প্রোব WRITE=1 দিয়ে চালানো হয়েছে।
- **F4 (২০২৬-১০-১৬):**
  - নম্বর Brickland-এর মতো `BK-ADD-00001` (আগে ধরে নেওয়া `AFB-` বদলানো হয়েছে)।
  - Brickland-এর Booking Items প্যানেলে বাল্ক বুকিংয়ের লাইনও বদলানো যায় বলে মনে হয়। এখানে সেগুলো শুধু দেখা যায়, কারণ বাল্কের লাইন অর্ডার আর কনজাম্পশন থেকে হিসাব হয়।
  - Brickland-এ Buyer A/C তালিকা খালি থাকে ("No records to choose from yet."); এখানে বায়ার অফিসের তালিকা আসে, আর ফাঁকা রাখলে স্টাইলেরটা বসে।
  - Send Mail? আর Send Date: এখন শুধু ফ্ল্যাগ রাখা হয় আর F2-তে দেখানো হয়। মেইল কাকে যাবে, তা ঠিক করা বাকি (খোলা প্রশ্ন)।
  - শেয়ার্ড বাগ ঠিক করা: সার্ভার থেকে খালি `errors` এলে ফর্মগুলো (F1 শিট, F3, F4) ফাঁকা লাল নোটিশ দেখাত। এখন মূল বার্তাটা দেখায় (`messagesOf`)।
  - পরীক্ষার সময় dev কোম্পানির সাবস্ক্রিপশন READ_ONLY হয়ে গিয়েছিল (ডেমো ইনভয়েস INV-2026-000002-এ ৩১,৭৫০ টাকা বাকি, due 2026-09-30), তাই সব সেভ আটকে যাচ্ছিল। ব্যবহারকারীর অনুমতিতে অ্যাপের নিজের পেমেন্ট এন্ডপয়েন্ট দিয়ে বাকিটা পরিশোধ করা হয়েছে (রেফারেন্স DEV-SETTLE-2026-10-16), তারপর `billing:advance` চালিয়ে কোম্পানি আবার ACTIVE।
- **F5 (২০২৬-১০-১৬):**
  - F4-এর কোড পুরোটাই আবার ব্যবহার করা হয়েছে (`requisitionScreen` আর `FabricRequisitionApiController`); শুধু Records-এর লেআউট, নাম আর মোছার সুবিধা আলাদা।
  - Booking Items-এর বুকিং তালিকা এখন ULID (`id`) ধরে সাজানো হয়, যাতে একই সেকেন্ডে তৈরি বুকিংয়ের ক্রম না বদলায়।
- **F6 (২০২৬-১০-১৬):**
  - নম্বর Brickland-এর মতো `BK-IMP-00001` (আগে ধরে নেওয়া `IFB-` বদলানো হয়েছে)।
  - অনুমোদিত আমদানি বুকিং বাল্কের মতো লক হয় (Brickland-এ Approve-এর পরে দুটো বাটনই বন্ধ হয়ে যায়)। Additional আর Buffer খোলা থাকে, কারণ তাদের Status ফর্মেই বদলানো হয়।
  - dev অর্ডারে কনফার্মের তারিখ নেই, তাই Order Conf. Date আর Lead Time "—" দেখায়।
  - **06/11 সম্পূর্ণ:** সব বুকিং একই টেবিলে (`merch_fabric_bookings`) থাকে, `booking_type` দিয়ে আলাদা। Approved Fabric Booking List পাঁচটা ধরনের অনুমোদিত বুকিংই দেখায়।
