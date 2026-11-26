# Accessories & Trims

> **স্ট্যাটাস:** ✅ সম্পন্ন (ছয়টা স্ক্রিনই চালু: A1 Booking Lookup, A2 Sourcing, A3 Trims Dyeing Programme, A4 Trims Accessories Booking, A5 Booking Register, A6 Purchase Register)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/08`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Accessories Booking Lookup | `/m/merchandising/accessories-booking-view` | 2 | 9 | 0 |
| 2 | Trims & Accessories Sourcing | `/m/merchandising/sourcing-of-trims-accessories` | 3 | 15 | 1 |
| 3 | Trims Dyeing Programme | `/m/merchandising/dyeing-program-for-trims` | 3 | 15 | 1 |
| 4 | Trims Accessories Booking | `/m/merchandising/accessories-booking-trims` | 2 | 8 | 0 |
| 5 | Accessories Booking Register | `/m/merchandising/accessories-booking-list` | 2 | 9 | 0 |
| 6 | Accessories Purchase Register | `/m/merchandising/accessories-purchase-list` | 1 | 6 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Accessories Booking Lookup

`/m/merchandising/accessories-booking-view`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Style/Order | search |  |  |
| Ship Month from | date |  |  |
| Ship Month to | date |  |  |
| PO # | search |  |  |
| Status | select |  | All, Draft, Submitted, Approved, Rejected, Cancelled |
| Search Accessory bookings with their PO, subject, dates, order, supplier and sta | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Booking No, Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, PO #, Subject of PO, PO Date, Del.Date, Supplier, Items, Status
- বাটন/অ্যাকশন: Booked Trims, Clear, Export, Booking No, Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, PO #, Subject of PO, PO Date, Del.Date, Supplier, Items, Status

**ট্যাব: Booked Trims**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search bookings | search |  | Search… |
- সেকশন: Bookings, Trims
- বাটন/অ্যাকশন: Booked Trims, ACB-00002 3563464 0, ACB-00001 848338 2

#### Trims & Accessories Sourcing

`/m/merchandising/sourcing-of-trims-accessories`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All |
| Work Style | search |  |  |
| Order # | search |  |  |
| Order Type | select |  | All |
| Shipment from | date |  |  |
| Shipment to | date |  |  |
| Supplier | select |  | All |
| Search Trim sourcing per confirmed order, with the supplier chosen and whether t | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer A/C, Style Name, Style #, Order #, Order Type, Order Qty., Order Conf. Date, Shipment Date, Lead Time, Trim, Supplier, Nominated?, ACTION
- বাটন/অ্যাকশন: Details, New sourcing line, Clear, Export, Buyer A/C, Style Name, Style #, Order #, Order Type, Order Qty., Order Conf. Date, Shipment Date, Lead Time, Trim, Supplier, Nominated?

**ট্যাব: Details**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Confirmed Orders, Trims & Accessories
- বাটন/অ্যাকশন: Details, New sourcing line

**ট্যাব: New sourcing line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Order* | select | ✔ | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Description* | text | ✔ |  |
| Buyer Nominated? A nominated supplier cannot be substituted without the buyer's  | checkbox |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New sourcing line
- বাটন/অ্যাকশন: Details, New sourcing line, Select…, Reset, Save sourcing line

**ডায়ালগ: New sourcing line**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order* | select | ✔ | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Description* | text | ✔ |  |
| Buyer Nominated? A nominated supplier cannot be substituted without the buyer's  | checkbox |  |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Order is required.

#### Trims Dyeing Programme

`/m/merchandising/dyeing-program-for-trims`

**ট্যাব: Records 2**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prod. Type | select |  | All, Bulk, Sample, Development |
| Buyer A/C | select |  | All |
| Style/Ord# | search |  |  |
| Program Date from | date |  |  |
| Program Date to | date |  |  |
| Status | select |  | All, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Search Dyeing programs for tape, thread and other dyeable trims, by order and pr | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Prod. Type, Buyer A/C, Style/Ord#, Program Date, Items, Remarks, Status, ACTION
- বাটন/অ্যাকশন: Program Items, New dyeing program, Clear, Export, Program No, Prod. Type, Buyer A/C, Style/Ord#, Program Date, Items, Remarks, Status, Edit

**ট্যাব: Program Items**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search programs | search |  | Search… |
- সেকশন: Programs, Items
- বাটন/অ্যাকশন: Program Items, New dyeing program, TDP-00002 Development 0, TDP-00001 Sample 0

**ট্যাব: New dyeing program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prod. Type | select |  | Select…, Bulk, Sample, Development |
| Buyer A/C | select |  | Select… |
| Style/Ord# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Program Date* | date | ✔ |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New dyeing program
- বাটন/অ্যাকশন: Program Items, New dyeing program, Reset, Save dyeing program

**ডায়ালগ: New dyeing program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Prod. Type | select |  | Select…, Bulk, Sample, Development |
| Buyer A/C | select |  | Select… |
| Style/Ord# | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Program Date* | date | ✔ |  |
| Status | select |  | Select…, Draft, Submitted, In Progress, Sent, Approved, Rejected, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Program Date is required.

#### Trims Accessories Booking

`/m/merchandising/accessories-booking-trims`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk New, Development, Repeat, Projection, Buffer, Special Sample |
| Buyer A/C | select |  | All |
| Work Style | search |  | 0376 |
| Order # | search |  |  |
| Req Type | select |  | All, Bulk, Sample, Buffer |
| Search Confirmed orders with their buyer, style, quantity and shipment, and what | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, Shipment Date
- বাটন/অ্যাকশন: Bookings, Clear, Export, Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, Shipment Date

**ট্যাব: Bookings**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Orders, Accessory Bookings
- বাটন/অ্যাকশন: Bookings, LPP-2026-000003 / 531 12 Oct 2026 0, LPP-2026-000006 / 529 12 Oct 2026 0, LPP-2026-000004 / 867MI 01 Nov 2026 0, LPP-2026-000008 / 529 01 Nov 2026 0, LPP-2026-000005 / 234234 10 Feb 2027 0, LPP-2026-000002 / 927 10 Oct 2027 0, LPP-2026-000007 / 1111 12 Dec 2027 0

#### Accessories Booking Register

`/m/merchandising/accessories-booking-list`

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
- সেকশন: Orders, Accessory Bookings
- বাটন/অ্যাকশন: Bookings, LPP-2026-000003 / 531 12 Oct 2026 0, LPP-2026-000006 / 529 12 Oct 2026 0, LPP-2026-000004 / 867MI 01 Nov 2026 0, LPP-2026-000008 / 529 01 Nov 2026 0, LPP-2026-000005 / 234234 10 Feb 2027 0, LPP-2026-000002 / 927 10 Oct 2027 0, LPP-2026-000007 / 1111 12 Dec 2027 0

#### Accessories Purchase Register

`/m/merchandising/accessories-purchase-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req Month | select |  | All |
| Buyer A/C | select |  | All |
| Job/Work Style# | search |  |  |
| Order# | search |  |  |
| Search Accessory purchase requisitions with their order, supplier, items and sta | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Req Date, Ship Date, Buyer, Job No, Order No, Item List
- বাটন/অ্যাকশন: Clear, Export, Requisition No, Req Date, Ship Date, Buyer, Job No, Order No, Item List
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **অর্ডার:** `Order` (CONFIRMED / IN_PRODUCTION), `Style`, `Buyer`। তালিকার ধরন T1 (`AccRequisitionApiController::options`) থেকে নেওয়া হয়েছে, এখন সেটা `Support/TrimOrders`-এ।
- **Trim:** `MaterialItem` (kind TRIMS)। **সাপ্লায়ার:** `MaterialSupplier`। **রং:** `Colour`।
- **Accessories Purchase Register:** 06/06-এর T1 `AccRequisition`, শুধু পড়া হয়।
- **UI:** বাঁয়ে তালিকা, ডানে প্যানেল, আর Add-ডায়ালগ। এই ধরন 06/11-এর `BookingItemsPanel` থেকে নেওয়া।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_acc_bookings`:** booking_no (`ACB-00001`, কোম্পানির ক্রমিক), order_id, po_no, subject, supplier_id, req_type (BULK/SAMPLE/BUFFER), po_date, delivery_date, status (DRAFT/SUBMITTED/APPROVED/REJECTED/CANCELLED)।
- **`merch_acc_booking_lines`:** booking_id, item_id, description, colour, size, qty, uom, rate, status, sort_order।
- **`merch_trim_sourcings`:** order_id, item_id, description, supplier_id, nominated, status (DRAFT/SUBMITTED/IN_PROGRESS/SENT/APPROVED/REJECTED/CANCELLED), remarks।
- **`merch_trim_dyeing_programs`:** program_no (`TDP-00001`), prod_type (BULK/SAMPLE/DEVELOPMENT), buyer_id, order_id, program_date, status (সাতটা, sourcing-এর মতো), remarks।
- **`merch_trim_dyeing_items`:** program_id, item_id, description, colour_id, colour_name, qty, uom।
- **Model:** `AccBooking`, `AccBookingLine`, `TrimSourcing`, `TrimDyeingProgram`, `TrimDyeingItem`। অর্ডারের সারি তৈরি হয় `Support/TrimOrders`-এ। Lead Time = Conf. Date থেকে Shipment পর্যন্ত দিন।

## ৫. Workflow / Status ও Business Rules

- **অর্ডার:** Booking, Register আর Sourcing-এর তালিকায় শুধু কনফার্মড (বা উৎপাদনে থাকা) অর্ডার আসে, যার শিপমেন্ট আগে সে আগে।
- **Book accessories:** শুধু হেডার (PO #, Subject, Supplier, Req Type, PO Date, Del. Date)। Req Type বাধ্যতামূলক, ডিফল্ট Bulk। Del. Date PO Date-এর আগে হতে পারে না। নতুন বুকিং থাকে Draft অবস্থায়।
- **Booked Trims:**
  - "Add trim line" সঙ্গে সঙ্গে সেভ হয়। Description আর Qty (> ০) বাধ্যতামূলক, UOM-এর ডিফল্ট Pcs।
  - grid-এ Description, Colour, Size, Qty, UOM আর Rate বদলানো যায়। Save করলে সব রাখা হয়। Remove করা লাইন Save করলে মুছে যায়, আর Revert সব আগের অবস্থায় ফেরায়।
  - অন্য বুকিংয়ের লাইন পাঠালে আটকায়: "Row n: that line is not on this booking."।
- **Sourcing:**
  - Order আর Description বাধ্যতামূলক। Trim বাছলে Description ভরে যায়।
  - Buyer Nominated? দিলে Supplier লাগে।
  - নমিনেশন বহাল থাকলে সাপ্লায়ার বদলানো যায় না। আগে টিক তুলতে হয় (বায়ারের সম্মতিতে), তারপর বদল।
  - Details-এ প্রতিটা লাইনের Supplier, Nominated আর Status সেট করে Save করা যায়।
- **Dyeing Programme:**
  - Program Date বাধ্যতামূলক। Buyer না দিলে অর্ডারের বায়ার বসে।
  - Add item-এ Description, Colour Name আর Qty বাধ্যতামূলক। Colour বাছলে Colour Name ভরে যায়।
  - Records-এর Edit দিয়ে একই ফর্মে সংশোধন করা যায়।
- **Purchase Register:** T1-এর PRQ-ACC রিকুইজিশন (ডামি ছাড়া), সাথে অর্ডারের Ship Date, বায়ার, Job, Order আর আইটেমের তালিকা।

## ৬. API ও পারমিশন

পারমিশন: পড়তে `merchandise.accessories.view`, লিখতে `merchandise.accessories.manage`।

| Method | Path |
|---|---|
| GET | `/merchandise/acc-bookings/options`, `/merchandise/acc-bookings/orders`, `/merchandise/acc-bookings` (Lookup), `/merchandise/acc-bookings/purchase-register` |
| GET / POST | `/merchandise/acc-bookings/orders/{order_no}` (বুকিং / Book accessories) |
| GET | `/merchandise/acc-bookings/{booking_no}` |
| POST / PUT | `/merchandise/acc-bookings/{booking_no}/lines` (Add trim line / grid Save) |
| GET | `/merchandise/trim-sourcings/options`, `/merchandise/trim-sourcings/orders`, `/merchandise/trim-sourcings/orders/{order_no}`, `/merchandise/trim-sourcings` |
| POST / PUT / DELETE | `/merchandise/trim-sourcings`, `/merchandise/trim-sourcings/{id}` |
| GET / POST | `/merchandise/trim-dyeing/options`, `/merchandise/trim-dyeing` |
| GET / PUT | `/merchandise/trim-dyeing/{program_no}` |
| POST / DELETE | `/merchandise/trim-dyeing/{program_no}/items`, `/merchandise/trim-dyeing/{program_no}/items/{id}` |

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Merchandise Desk → OPERATION → **Accessories & Trims** (Style & Order-এর পরে)। ক্রম Brickland-এর মতো।

- **A1 Accessories Booking Lookup** (`/merchandise-desk/accessories-booking-view`):
  - **Records:** ফিল্টার Buyer A/C, Style/Order, Ship Month from/to, PO #, Status। কলাম Booking No, Buyer Name, Style Description, Style #, Order #, Order Type, Order Qty, PO #, Subject of PO, PO Date, Del.Date, Supplier, Items, Status।
  - **Booked Trims:** বাঁয়ে Bookings (সার্চ আর ট্রিমের সংখ্যা)। ডানে "Trims — ACB-…": Export, Add trim line (ডায়ালগ), grid (Item, Description, Colour, Size, Qty, UOM, Rate, Status, Action), নিচে "n trim lines.", Revert আর Save।
- **A2 Trims & Accessories Sourcing:**
  - **Records:** ফিল্টার Buyer A/C, Work Style, Order #, Order Type, Shipment from/to, Supplier। কলাম Buyer A/C, Style Name, Style #, Order #, Order Type, Order Qty., Order Conf. Date, Shipment Date, Lead Time, Trim, Supplier, Nominated?, আর Edit (Details-এ নিয়ে যায়)।
  - **Details:** Confirmed Orders আর "Trims & Accessories — order"। প্রতিটা লাইনে Supplier, Nominated আর Status সেট করে Save, আর Remove।
  - **New sourcing line:** Order*, Trim & Accessory, Description*, Supplier, Buyer Nominated? (hint সহ), Status, Remarks; Reset আর Save sourcing line।
- **A3 Trims Dyeing Programme:**
  - **Records:** ফিল্টার Prod. Type, Buyer A/C, Style/Ord#, Program Date from/to, Status। কলাম Program No … Status, আর Edit।
  - **Program Items:** Programs তালিকা আর "Items — TDP-…", Add item (Trim, Description*, Colour, Colour Name*, Qty*, UOM), Remove।
  - **New dyeing program:** Edit থেকে খুললে নাম হয় "Edit dyeing program"।
- **A4 Trims Accessories Booking / A5 Accessories Booking Register:**
  - **Records:** কনফার্মড অর্ডার। Register-এ বাড়তি কলাম Order Conf. Date আর Lead Time, ফিল্টারও Brickland-এর মতো আলাদা।
  - **Bookings:** বাঁয়ে Orders ("order / style", ship date, বুকিংয়ের সংখ্যা)। ডানে "Accessory Bookings — …" আর Book accessories ডায়ালগ।
- **A6 Accessories Purchase Register:** একটাই ভিউ। ফিল্টার Req Month, Buyer A/C, Job/Work Style#, Order#। কলাম Requisition No, Req Date, Ship Date, Buyer, Job No, Order No, Item List, Status।
- **কম্পোনেন্ট:** `AccBookingOrders`, `AccBookedTrims`, `TrimSourcingDetails`, `TrimSourcingForm`, `TrimDyeingItems`, `TrimDyeingForm`। স্ক্রিনগুলো `acc-trims.js`-এ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_12_04_000100_create_merchandise_acc_bookings.php`, `Models/AccBooking.php`, `Models/AccBookingLine.php`, `Models/TrimSourcing.php`, `Models/TrimDyeingProgram.php`, `Models/TrimDyeingItem.php`, `Support/TrimOrders.php`, `Http/Controllers/Api/AccBookingApiController.php`, `Http/Controllers/Api/TrimSourcingApiController.php`, `Http/Controllers/Api/TrimDyeingApiController.php`, `Routes/api.php` |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MERCHANDISE.ACC_TRIMS` ফোল্ডার, OPERATION-এর ভেতরে; ছয়টা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/acc-trims.js` (`ACC_TRIMS_SCREENS`, `ACC_TRIMS_MANAGE`), `page.js`, `actions.js` (`accBookingsForOrder`, `bookAccessories`, `loadAccBooking`, `addAccBookingLine`, `saveAccBookingLines`, `trimSourcingForOrder`, `createTrimSourcing`, `updateTrimSourcing`, `deleteTrimSourcing`, `loadTrimDyeing`, `saveTrimDyeing`, `addTrimDyeingItem`, `removeTrimDyeingItem`), `frontend/src/components/merchandise/acc-booking-orders.jsx`, `acc-booked-trims.jsx`, `trim-sourcing.jsx`, `trim-dyeing.jsx` |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.acb`, `master.tsr`, `master.tdp`, `acb`, `tsr`, ফোল্ডার `acc_trims`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseAccTrimsTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **বুকিং আর লাইনের Status বদলানো:** Brickland-এ কোনো বাটন দেখা যায়নি। Approval Center-এ `ACCESSORIES-BOOKING` ধরন আছে, পরে চাইলে সেটার সাথে যুক্ত করা যায়।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseAccTrimsTest`:** ২টা টেস্ট, ৭৯টা assertion।
  - কনফার্মড অর্ডারের তালিকা (buyer, style, type, qty, conf, ship, lead time ৬০, বুকিংয়ের সংখ্যা)।
  - Del. Date আগে হলে আটকায়। ACB-00001, ACB-00002 তৈরি হয়।
  - Add trim line-এর বাধ্যতামূলক ঘর। grid-এর বার্তা Row n ধরে। অন্য বুকিংয়ের লাইন আটকায়। Save করলে Qty বদলায় আর সরানো লাইন মোছে।
  - Lookup-এর কলাম। STORE_MANAGER শুধু দেখতে পারে।
  - Sourcing: বাধ্যতামূলক ঘর, Nominated দিলে Supplier লাগে, নমিনেশন বহাল থাকলে সাপ্লায়ার বদলায় না, টিক তুলে Approved, Records, Remove।
  - Dyeing: Program Date বাধ্যতামূলক, TDP-00001, অর্ডার থেকে বায়ার, আইটেমের ঘর, Colour, এডিট, Records, আইটেম Remove।
  - Purchase Register।
- **রিগ্রেশন:** MenuAccess, RoleMatrix, ApiGateCoverage, AccessControlConfiguration, IconCatalogue, T1 আর এই টেস্ট — ৩২টা টেস্ট, ৩১৭টা assertion সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১০-১৮, owner লগইন):
  - সাইডবারে OPERATION → Accessories & Trims (৬), Style & Order-এর পরে ✔
  - Trims Accessories Booking: Records-এর ৭টা কলাম ✔ → Bookings → "Order / FLOW STYLE-01" → Book accessories: PO-ACB-1017, KOYO, 18 Oct → 10 Nov → ACB-00001 ✔
  - Booking Lookup → Booked Trims → ACB-00001 → Add trim line: Button 18L 4-Hole (Navy, 18L, 4000, 0.35) আর Main Label Woven (1000) ✔ → grid-এ Qty 4200 করলে "Edited" দেখায় → Save → DB-তে 4200 ✔
  - Sourcing: New sourcing line (Main Label, KOYO) → Details-এ Approved → Save ✔। Poly Bag-এর লাইনে সাপ্লায়ার ছাড়া Nominated দিলে বার্তা আসে, KOYO দিলে "Yes" ✔। Records-এ Lead Time ৯৬ দিন ✔
  - Dyeing: Sample, Order, মন্তব্য → TDP-00001 → Add item Sewing Thread 40/2, Navy 19-4024, ৬০ Cone ✔; Records ✔
  - Booking Register-এর ৯টা কলাম ✔; Purchase Register-এ PRQ-ACC-2026100001/02, Status "Approved" ✔
  - সব রেকর্ড dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **বুকিংয়ের নম্বর:** Brickland-এর মতো `ACB-00001` আর `TDP-00001` রাখা হয়েছে, মাস অনুযায়ী নয়।
- **বুকিং আর লাইনের Status বদল:** Brickland-এ কোনো বাটন দেখা যায়নি, তাই এখানে সব Draft থাকে।
- **Sourcing-এর Records-এ ACTION:** Brickland-এ এই কলামের বাটন দেখা যায়নি (রেকর্ড ছিল না)। এখানে "Edit", যা সেই অর্ডারের Details খোলে। URL-এ অর্ডার নম্বর থাকে, id নয়।
- **Trims Accessories Booking-এর Req Type ফিল্টার:** Brickland-এ ফিল্টারটা আছে, কিন্তু Records অর্ডারের তালিকা, বুকিংয়ের নয়। তাই এখানে বাদ রাখা হয়েছে।
- **নমিনেটেড সাপ্লায়ার:** Brickland-এর hint ("cannot be substituted without the buyer's agreement") অনুযায়ী টিক বহাল থাকলে সাপ্লায়ার বদলানো আটকানো হয়।
- **bn অনুবাদ:** ছোট শব্দগুলো বাংলায় অনুবাদ হয়েছে। লম্বা hint-বাক্যগুলো এখনো ইংরেজিতে।

## ১১. As-built নোট

- **A1–A6 ✅** (২০২৬-১০-১৮)। ব্যবহারকারী জানিয়েছিলেন যে Brickland-এ OPERATION-এর ভেতরে Accessories & Trims আছে, তার পরে বানানো হয়েছে।
- **লাইভ প্রোবের ফাইল:** `tools/erp_crawler/plans/atb1.json` থেকে `atb4.json`। এতে আছে অর্ডারের তালিকা, Book accessories ডায়ালগ, Booked Trims-এর grid আর Add trim line, Sourcing-এর New sourcing line আর তার ড্রপডাউন, Dyeing-এর Program Items আর Add item, Register আর Purchase Register। Brickland-এ কোনো রেকর্ড তৈরি করা হয়নি।
