# Procurement / Purchase Module

**Module Key:** `procurement.purchase`
**নির্ভরতা:** [Master Data Module](01_Master_Data_Module.md) (Item, Supplier — বিদ্যমান Vendor মডিউল এক্সটেন্ড, UOM, HS Code), [Inventory Module](02_Inventory_Module.md) (Stock Available, Roll Inspection)

**⬅ ফিরে যান:** [Main Index](00_Main_Index.md)

---

## 1. Objective

গার্মেন্টস ফ্যাক্টরির সম্পূর্ণ Procurement সাইকেল — **Fabric Booking → Material Requirement → Purchase Requisition → RFQ → PO → PI/LC → Shipment → Receiving → Inspection → Landed Cost → Claim/Return** পর্যন্ত।

## 2. Complete Process Flow

```
Order/Style Confirmed
        ↓
Fabric Booking (Pre-Commitment)
        ↓
Material Requirement (from BOM)
        ↓
Auto/Manual Purchase Requisition
        ↓
Multi-level Approval
        ↓
RFQ → Quotation → Comparison
        ↓
Purchase Order
        ↓
PI (Proforma Invoice) → LC Opening (Import)
        ↓
Shipment Tracking (BL, Container, ETA)
        ↓
Gate Pass + Partial Receiving
        ↓
Fabric Inspection (Roll-wise — Inventory Module owns this)
        ↓
Accepted → Inventory | Rejected → Claim/Return
        ↓
Landed Cost
        ↓
Accounts Payable
```

## 3. Fabric Booking

Order কনফার্ম হওয়ার পর Supplier-এর সাথে Fabric বুকিং (PO-এর আগে)।

- Style/Order এর বিপরীতে Booking
- Item + Quantity + Required Date + Tentative Rate
- Supplier Reference
- Booking Status: Draft / Confirmed / Cancelled / Converted to PR/PO
- Booking থেকে সরাসরি PR/PO তৈরি করা যাবে
- Over Booking Warning

**Reservation Sync:** Booking Confirmed হলে [Inventory Module](02_Inventory_Module.md#71-fabric-booking--reservation-sync)-এ Auto Soft Reservation তৈরি হয় — পূর্ণ লজিক ওখানে দেখুন।

### 3.1 যেভাবে তৈরি হয়েছে (Phase 3B)

`fabric_bookings` + `fabric_booking_lines` (Procurement মডিউলে), `ManageFabricBooking` অ্যাকশন, `/fabric-bookings` API, permission `procurement.booking.view_any|manage`।

- **Confirm** — প্রতি লাইনে `ItemReservation` (`reservation_type = BOOKING`, `reference_type = fabric_booking_line`), status `PENDING_ARRIVAL`। **Earmark, encumbrance নয়:** কাপড় মিলে আছে, তাকে নেই — ধরে রাখার মতো কিছু নেই। GRN-এ এলে `fillPendingEarmarks()` সেটাকে সত্যিকারের hold-এ বদলে দেয়।
- **Cancel** — দাবি `CANCELLED`। না ছাড়লে ওই গজ চিরকাল রিকোয়ারমেন্ট থেকে বাদ থাকত আর ফ্যাক্টরি চুপচাপ কম কিনত।
- **Convert** — DRAFT পারচেজ রিকুইজিশন (`pr_type = AUTO`, `source_reference = booking.id`), `converted_pr_id` লেখা হয়। স্পেকের "ref আপডেট" এখানে দাবিটিকে PR-এ সরিয়ে নয়, booking line-এর সাথেই রেখে পূরণ করা হয়েছে — সূত্র দুদিক থেকেই অনুসরণযোগ্য থাকে আর release/fill একটাই কি ধরে কাজ করে।
- **Over Booking Warning** — স্টাইলের BOM-এর তুলনায় বেশি বুক করলে সতর্কবার্তা ফেরত আসে, **কনফার্ম আটকায় না**। মার্চেন্ডাইজার বায়ারের ফোরকাস্ট ধরে বুক করে, যা সপ্তাহে সপ্তাহে বদলায়; আজকের BOM দেখিয়ে বুকিং আটকে দিলে মানুষ সিস্টেমকে পাশ কাটিয়ে চলত। স্টাইল না দিলে তুলনা করার কিছু থাকে না, তখন কিছু বলে না — সিজনের শুরুতে এটাই স্বাভাবিক।

## 4. Material Requirement & Auto PR

```
Net Requirement = BOM Required Qty
                 – Stock Available (Inventory, real-time)
                 – Already Booked (Confirmed Fabric Booking)
                 – Already Ordered (Approved PO pending_qty)
                 + Approved Wastage Allowance
```

- এই ক্যালকুলেশন **real-time** হবে
- Reserved stock (অন্য Order-এর Hard Allocated) কখনো "Stock Available"-এ ধরা হবে না
- Net Requirement > 0 হলে Draft Auto PR তৈরি হবে (নিচে সেকশন ৬)

### 4.1 যেভাবে তৈরি হয়েছে (Phase 3B)

`app/Modules/Procurement/Services/MaterialRequirement` — `forProgram()` ও `forStyle()`; API `GET /cutting-programs/{program}/material-requirement`, `GET /production-styles/{style}/material-requirement`।

- **কিছুই সংরক্ষণ করা হয় না।** রিকোয়ারমেন্টের স্ন্যাপশট একটা GRN আসামাত্রই বাসি হয়ে যায়, আর বাসি সংখ্যা যেটা কর্তৃত্বপূর্ণ দেখায় সেটা কোনো সংখ্যা না থাকার চেয়ে খারাপ।
- **ওয়েস্টেজ আলাদা পদ নয়।** স্পেকের ফর্মুলায় এটি চতুর্থ পদ হিসেবে যোগ হয়; এখানে BOM-এর `requiredFor()`-এর ভিতরেই ঢোকানো — ফল একই, কিন্তু অ্যালাউন্সটা যে কনজাম্পশনের, তার পাশেই থাকে।
- **মুক্ত স্টক = on hand − reserved**, শুধু ওই ফ্যাক্টরির বিনগুলোর মধ্যে। অন্য অর্ডারের জন্য ধরে রাখা কাপড় তাকেই আছে এবং "উপলব্ধ" নয় — ধরলে কম কেনা হতো আর ঘাটতি কাটিং টেবিলে গিয়ে ধরা পড়ত।
- **Already Booked** শুধু *এই স্টাইলের* কনফার্মড বুকিং, আর তার মধ্যেও দাবির বাকি অংশটুকু — আংশিক পূরণ হওয়া দাবি তো ইতিমধ্যে স্টক হয়ে গেছে, দুবার গোনা হতো।
- **Auto PR** (`RaiseRequirementRequisition::forProgram`) — শুধু net > 0 লাইনগুলো, DRAFT-এ, `source_reference = cutting_program.id`। একই প্রোগ্রামে দ্বিতীয়বার চাইলে 409 — নীরবে দুবার কিনে ফেলার চেয়ে ড্রাফটটা এডিট করা ভালো।

## 5. Purchase Requisition (PR)

*(সম্পূর্ণ ডিজাইন — নিচের সব সাব-সেকশনে)*

### 5.1 দুই ধরনের উৎস

| Type | উৎস |
|---|---|
| **Auto PR (System Generated)** | Material Requirement calculation থেকে |
| **Manual PR** | ডিপার্টমেন্ট থেকে সরাসরি চাহিদা |
| **Floor Requisition Shortage থেকে** | [Floor/Store Requisition Module](04_Floor_Store_Requisition_Module.md#4-store-level-fulfillment-logic)-এ Store-এ স্টক না থাকলে অটো তৈরি হয় |

> **নোট:** Store Indent (এক স্টোর থেকে আরেক স্টোরে Internal Transfer) এটা Purchase না — আলাদা ওয়ার্কফ্লো, PR-এর সাথে গুলানো যাবে না।

> **স্ট্যাটাস (Phase 3A):** Floor Requisition Shortage উৎসটা এখন সত্যিকারের — `FulfilFloorRequisition::recordShortage()` সব short লাইন মিলে একটি PR তোলে (`pr_type = FROM_FLOOR_REQUISITION`, `source_reference = floor_requisition.id` — কলামটা ULID, তাই নম্বর নয় আইডি; মানুষের পড়ার নম্বরটা `notes`-এ), DRAFT-এ রেখে, §5.3-এর "Requester Draft রিভিউ করে Submit করবে" অনুযায়ী। Material Requirement উৎসটা Phase 3B-র কাজ, কারণ Order/Style/BOM ডোমেইন এখনো নেই।

### 5.2 PR Header + Line

**Header:** pr_no, pr_date, requested_by, department/store_id, pr_type (Auto/Manual/Emergency/FromFloorRequisition), source_reference (material_requirement_id / booking_id / floor_requisition_id / null), priority, status

**Line:** item_id, requested_qty, uom, required_date, style_id/order_id/cutting_program_id (যদি প্রযোজ্য), estimated_rate, current_stock_qty, already_ordered_qty (অটো দেখাবে)

### 5.3 Auto PR Generation Logic

```
Material Requirement Engine রিয়েল-টাইম চেক করবে
        ↓
Net Requirement > 0 → Draft Auto PR তৈরি
        ↓
Same Item + Same Store-এ একাধিক Order/Style থেকে Requirement এলে
        ↓
Consolidation: এক PR Line-এ Combine (style-wise breakdown detail-এ থাকবে)
        ↓
Requester Draft রিভিউ করে Submit করবে (সরাসরি Approval-এ যাবে না)
```

### 5.4 Manual PR Flow

```
Requester PR তৈরি (item, qty, reason, required date) → Submit
        ↓
System Current Stock + Already Ordered দেখাবে (warning, block না)
        ↓
Approval Matrix অনুযায়ী রাউটিং
```

### 5.5 Consolidation Logic

একই আইটেম একাধিক PR-এ (বিভিন্ন Order/Department থেকে) এলে RFQ/PO আগে Consolidate করা হবে:

```
Approved PR List (একই item, কাছাকাছি required_date)
        ↓
Procurement Officer "Consolidate" সিলেক্ট করবে
        ↓
একটাই RFQ/PO তৈরি, কিন্তু প্রতিটা original PR-এর reference থেকে যাবে
        ↓
PO → GRN হলে, প্রতিটা PR-অনুযায়ী qty allocate হবে
```

### 5.6 Status Flow

```
Draft → Submitted → Pending Approval → Approved → Partially Converted (to PO)
      → Fully Converted → Closed
                    │
                    └→ Rejected / Cancelled (যেকোনো ধাপে)
```

> **As built (Phase 1):** `purchase_requisitions` + `purchase_requisition_lines` (`app/Modules/Procurement`), API `/purchase-requisitions` (index/show/store/update/submit/cancel, `form-options`), UI `/procurement/requisitions`। Status: `DRAFT → PENDING_APPROVAL → APPROVED → PARTIALLY_CONVERTED → FULLY_CONVERTED`, যেকোনো ধাপে `REJECTED`/`CANCELLED` ("Submitted" আলাদা status না — submit-ই approval-এ পাঠায়; workflow না থাকলে সরাসরি `APPROVED`)। লাইনে `item_code`/`item_name` স্ন্যাপশট, `current_stock_qty`/`already_ordered_qty` (§5.8 duplicate warning `open_duplicates[]` হিসেবে detail-এ, block না)। Approval: `ApprovalWorkflow.entity_type = PURCHASE_REQUISITION`, `condition_json.min_cost` = estimated_total-এর threshold। Permission: `procurement.requisition.view_any|create`।

### 5.7 Approval Matrix (উদাহরণ)

| PR Value/Qty | Approver |
|---|---|
| ছোট | Store/Dept Manager |
| মাঝারি | Procurement Manager |
| বড় | Procurement Manager + Finance/GM |

Auto PR-এ threshold কম রাখা যায়; Manual/Emergency PR-এ কড়াকড়ি বেশি। পূর্ণ Approval Engine ডিজাইন [Shared Systems](05_Shared_Systems_and_Compliance.md)-এ।

> **নোট:** ছোট/মাঝারি/বড়-এর প্রকৃত টাকার অংক এখনো ঠিক করা হয়নি — এটা পরে ঠিক হবে, Phase 0/1 শুরু করার জন্য ব্লকিং না। বাস্তবায়নের সময় Settings-এ configurable থ্রেশহোল্ড হিসেবে রাখা হবে, যাতে পরে সংখ্যা বদলাতে কোড পরিবর্তন না লাগে।

### 5.8 Duplicate/Over-Requisition Protection
একই item + order/style-এর active PR/PO থাকলে Warning (block না); Approver override করতে পারবে কিন্তু override reason mandatory।

## 6. RFQ & Quotation Comparison

- একাধিক Vendor ([Master Data](01_Master_Data_Module.md#3-supplier--vendor-master))
- Technical Compliance চেক
- Side-by-side Comparison
- Lowest Price / Fastest Delivery / Best Technical Match
- Approved PR (single বা consolidated) → RFQ; Rate Contract থাকলে RFQ স্কিপ করে সরাসরি PO

## 7. Purchase Order (PO)

**Header:** po_no, vendor, currency, exchange_rate, import_local flag (**purchase_type: Local/Import**), pi_number, lc_number, payment_terms, delivery_terms, approval status

**Items:** Technical Specification (Width, GSM, Construction, Shade, Tolerance), Quality Standard, received_qty, pending_qty

**Delivery Schedule:** Multiple dates সাপোর্ট
**Revision:** Full history with reason

> **As built (Phase 1, Local flow):** `purchase_orders` + `purchase_order_lines` + `purchase_order_revisions`; API `/purchase-orders` (store/update/submit/revise/cancel/close, `{id}/receivable`) ও `POST /purchase-requisitions/{id}/orders` (PR-এর outstanding লাইন থেকে, `ordered_qty` PR লাইনে জমা হয়); UI `/procurement/orders`। `purchase_type` শুধু `LOCAL` গৃহীত (IMPORT → 422) — **Phase 5-এ খুলে দেওয়া হয়েছে**: PI/LC/Shipment নিজেদের টেবিলে থাকায় IMPORT অর্ডারে বাড়তি কোনো ফিল্ড লাগে না, টাইপটা অর্ডারের গায়ে একটা লেবেল মাত্র (§10)। `revise` = আগের header+lines `snapshot_json`-এ, `revision_no++`; subtotal `approved_subtotal` ছাড়ালে `PENDING_APPROVAL`-এ ফিরে যায় (`PURCHASE_ORDER` entity type)। Delivery Schedule (multi-date) ও Technical Spec কলাম (`technical_spec_json`) আছে, UI Phase 2-এ। Permission: `procurement.order.view_any|create`।

## 8. Local vs Import Purchase

*(দুটো পৃথক Flow — নিচে)*

### Import Flow
```
PR → RFQ → PO → PI → LC → Shipment → Gate Pass → Receiving → Inspection → Landed Cost → AP
```

### Local Flow (Yarn/Trims/Chemical/Spare Parts/Local Fabric)
```
PR → (RFQ Optional) → PO → Direct Gate Pass + Receiving → Inspection (if applicable) → Landed Cost (Freight/VAT only) → AP
```
PI/LC স্কিপ হবে; PO-তে `purchase_type` ফ্ল্যাগ অনুযায়ী সিস্টেম UI/ফ্লো নিজে থেকে অ্যাডজাস্ট করবে।

## 9. GRN (Goods Receipt Note) — Formal Definition

**GRN Header:** grn_no, grn_date, store_id, po_id (nullable — non-PO/Direct GRN সাপোর্ট), vendor_id (বিদ্যমান [Vendor মডিউলের](01_Master_Data_Module.md#3-supplier--vendor-master) রেকর্ডে real FK), gate_pass_no, grn_type (Import/Local/Return-from-Subcontract), status (Draft/Under Inspection/Completed/Cancelled)

**GRN Line:** grn_line_id, item_id, po_line_id (nullable), received_qty, uom, batch/lot_no, roll_id list (fabric হলে), rate, currency, inspection_status (Pending/Passed/Failed/Partial)

**নিয়ম:**
- একটি GRN একাধিক PO থেকে multiple items কভার করতে পারবে (multi-PO GRN)
- একটি PO একাধিক GRN-এ receive হতে পারবে (Partial Receiving)
- GRN Complete না হওয়া পর্যন্ত Inventory-তে stock post হবে না ("Under Inspection" স্ট্যাটাসে থাকবে)
- Inspection process [Inventory Module](02_Inventory_Module.md#4-fabric-inspection-at-receiving) owns করবে

> **As built (Phase 1, non-fabric):** API `/goods-receipts` (store — `po_id` দিলে PO-র outstanding লাইন থেকে, না দিলে direct; update; `complete`; cancel), UI `/procurement/receipts` ও PO পেজ থেকে "Receive"। `goods_receipt_lines` += `bin_id`, `item_code`/`item_name` স্ন্যাপশট, `accepted_qty`; `po_id`/`po_line_id` এখন real FK। `complete` এক ট্রানজ্যাকশনে প্রতিটি লাইন `ItemLedger::post('RECEIPT', qty, rate)` (idempotency key `grn:{line_id}`), PO লাইনের `received_qty` ও PO status (`PARTIALLY_RECEIVED`/`RECEIVED`) আপডেট। LOT আইটেমে lot_no, সব লাইনে receiving store-এর bin বাধ্যতামূলক; ordered-এর বেশি গ্রহণ `procurement.allow_over_receipt` সেটিং (default off)। Multi-PO GRN এখনো বাকি (এক GRN = এক PO বা direct)। Permission: `procurement.receipt.view_any|create|complete`।
>
> **As built (Phase 2, fabric):** ROLL-ট্র্যাকড লাইনে `complete` আর refuse করে না — প্রতিটি physical roll-এর জন্য `rolls[line_id][]` payload (roll_no, shade, মাপ, bin_id) লাগে ("Roll Entry" ধাপ), যা থেকে `App\Modules\ItemMaster` `FabricRoll` রো তৈরি হয় (status `UNDER_INSPECTION`, `unit_cost` = এই লাইনের rate — Provisional Unit Cost)। কোনো লাইন রোল তৈরি করলে GRN নিজে `COMPLETED`-এর বদলে **`UNDER_INSPECTION`**-এ যায় (নিয়ম অনুযায়ী); PO-র `received_qty` তখনই বাড়ে (গ্রহণ করা মানেই এসেছে, ইনস্পেকশন পাশ করা না) — over-receipt guard রোলে প্রযোজ্য না। GRN `UNDER_INSPECTION` অবস্থায় থাকলে cancel refused (আগে রোলগুলো resolve করতে হবে)। সব রোল ইনস্পেক্ট হলে (`FabricRollApiController::inspect`) GRN নিজে থেকে `COMPLETED`-এ ফেরে ও `inspection_status` (PASSED/FAILED/PARTIAL) সেট হয় — বিস্তারিত [Inventory §4](02_Inventory_Module.md#4-fabric-inspection-at-receiving)। SERIAL আইটেম এখনো refused (Asset মডিউলের কাজ, এই লেজারের বাইরে)। Multi-PO GRN ও Import/PI/LC এখনো Phase 5-এর কাজ।

## 10. PI + LC + Shipment Tracking

**PI:** PI Number, Date, Amount, Currency; Linked with PO; Version Control
**LC:** LC Number, Bank, Amount, Expiry Date; LC vs PO Amount Matching; Amendment History; Status (Opened/Shipped/Closed)
**Shipment Tracking:** BL/AWB Number, Container Number, Shipping Date, ETA, Actual Arrival Date, Document Status

> **As built (Phase 5):** `proforma_invoices`, `letters_of_credit`, `letter_of_credit_amendments`, `shipments` (`app/Modules/Procurement`), সব `ManageImportDocuments` অ্যাকশনের ভিতর দিয়ে; API একটাই কন্ট্রোলার `ImportDocumentApiController` — `GET /purchase-orders/{order}/import-documents` (একটি অর্ডারের পুরো ফাইল, ঘটনার ক্রমে), `GET /shipments` (বোর্ড, ETA অনুসারে **আরোহী** — "এ সপ্তাহে কী নামছে" প্রশ্নের উত্তর), আর PI/LC/Shipment-এর নিজস্ব অ্যাকশন রুট। UI: `/procurement/shipments` (বোর্ড) ও `/procurement/orders/{id}/import-documents` (তিনটি কার্ড — ইনভয়েস, এলসি, শিপমেন্ট)। Permission `procurement.import.view_any|manage` (স্টোরকিপার দেখে, বাইং ডেস্ক লেখে)।
>
> তিনটি সিদ্ধান্ত যা ইচ্ছাকৃত:
>
> - **PI সম্পাদনা হয় না, ভার্সন হয়** (`version`, `supersedes_id`)। ব্যাংক একটি নির্দিষ্ট দলিলের বিপরীতে এলসি খুলেছিল; ওটা ওভাররাইট করলে এলসি এমন কাগজের সাথে মিলত যা আর সে কথা বলে না। `SUPERSEDED` PI-এর বিপরীতে নতুন এলসি খোলা 409।
> - **অ্যামেন্ডমেন্ট নিজের টেবিলে**, `bank_charge` ও `reason` সহ। ব্যাংক প্রতিটির জন্য ফি নেয় আর কারণগুলোই অর্ডার কীভাবে নড়ল তার ইতিহাস; বর্তমান অঙ্কের মধ্যে গুঁজে দিলে দুটোই হারায়।
> - **`original_eta` কখনো নড়ে না**, শুধু `eta` নড়ে। প্রতিশ্রুতি আর বাস্তবের ফারাকই সাপ্লায়ারের ডেলিভারি রেকর্ড (`delayDays()`), আর প্রতি সপ্তাহে চুপচাপ আপডেট হওয়া একটা ফিল্ডে কোনো রেকর্ডই থাকে না। `document_status` মালের status থেকে আলাদা — কাগজ ব্যাংকে থাকলে পৌঁছানো মালও ছাড় করানো যায় না, আর শুধু "arrived" লেখা থাকলে মনে হতো যায়।
>
> নাম্বারিং: PI নম্বর সাপ্লায়ারের, LC নম্বর ব্যাংকের — তাই শুধু `SHIPMENT` (`SHP-{FACTORY}-{YYYY}-{SEQ}`) আমাদের সিরিজ। দৈনিক `procurement:shipment-alerts` তিনটি ঝাড়ু দেয়: আসন্ন (`SHIPMENT_ARRIVING`), ইটিএ পার (`SHIPMENT_DELAYED`, প্রতিদিন যতক্ষণ না নামে) আর মেয়াদ ঘনানো এলসি (`LC_EXPIRING`) — সেটিং `procurement.shipment_eta_notice_days` (৭), `procurement.lc_expiry_notice_days` (১৫); ডকুমেন্টপ্রতি দিনে একবারের বেশি নয়। টেস্ট `tests/Feature/Procurement/ImportDocumentTest`।

## 11. Landed Cost

- Freight, Duty, C&F, Insurance, Bank Charges, Other
- Distribution by Qty বা Value
- Multiple GRN-এ Apply
- Post করার পর Rate লক

**Roll Cost Timing (Inventory-এর সাথে ইন্টিগ্রেশন):** পূর্ণ লজিক [Inventory Module](02_Inventory_Module.md#10-landed-cost--roll-cost-timing)-এ — সংক্ষেপে, GRN Complete-এ Provisional Cost বসে, Landed Cost Post হলে Roll-এর unit_cost recalculate হয় (Split/Merge cascade সহ)।

> **As built (Phase 5):** `landed_cost_vouchers` + `landed_cost_charges` + `landed_cost_receipts` + `landed_cost_allocations`, `ManageLandedCost` (`create/update/preview/post/cancel`), API `/landed-costs` (+ `{id}/preview`, `{id}/post`), UI `/procurement/landed-costs`। একাধিক GRN এক ভাউচারে; basis `VALUE` বা `QTY`।
>
> `distribute()` **শেষ লাইনকে অবশিষ্টটুকু** দেয়, প্রতি লাইনে আলাদা করে রাউন্ড করে না — নইলে শেয়ারের যোগফল মোট চার্জের সমান হতো না আর প্রতি ভাউচারে কয়েক পয়সা হারাত। QUANTITY/LOT আইটেম `ItemLedger::revalue()`-এ যায়: **শূন্য-পরিমাণের** একটি `LANDED_COST` সারি, শুধু WAC নড়ে (কিছু আসেওনি, যায়ওনি — শুধু যা আছে তার দাম বদলেছে); ROLL আইটেম `RecalculateRollCosts::forGoodsReceipt()`-এ, যা Split চাইল্ড ও Merged রোলে cascade করে।
>
> **Rate lock:** পোস্ট হলে ওই রিসিটগুলোতে `landed_cost_posted_at` বসে; `GoodsReceipt::isEditable()` এখন `DRAFT && ! isRateLocked()` — কারণ ওই দর এখন রিপোর্ট হয়ে যাওয়া ভ্যালুয়েশনের অংশ। ভুল হলে উল্টো চার্জের দ্বিতীয় ভাউচার, undo নয়। স্ক্রিনে **preview** আলাদা বোতাম, কারণ পোস্টিং ফেরানো যায় না — সংখ্যাগুলো আগে দেখা যায়, পরে নয়। টেস্ট `tests/Feature/Procurement/LandedCostTest`।

## 12. Purchase Return + Claim

### Return
Quality Fail/Excess/Wrong Item/Damage → Approval → Outward Gate Pass → Stock কমানো → Debit Note

**Roll Status Link:** পূর্ণ লজিক [Inventory Module](02_Inventory_Module.md#11-purchase-return--roll-status-link)-এ।

### Claim
Claim Amount, Reason, Supporting Documents; Status (Raised/Accepted/Settled/Rejected); Settlement Tracking

## 13. Advance Payment to Supplier

- Advance Payment Entry
- PO/PI এর বিপরীতে Advance
- Bill আসার পর Auto/Manual Adjustment
- Outstanding Advance Report

> **As built (Phase 5):** `purchase_returns` + `purchase_return_lines` (`ManagePurchaseReturn`, `PurchaseReturn implements Approvable`), `supplier_claims` (`ManageSupplierClaim`), `supplier_advances` + `supplier_advance_adjustments` (`ManageSupplierAdvance`); API `/purchase-returns` (`POST /goods-receipts/{grn}/returns` থেকে শুরু), `/supplier-claims`, `/supplier-advances` (+ `outstanding`); UI `/procurement/returns`, `/procurement/claims`, `/procurement/advances`, আর রিসিট পেজ থেকে "Return goods"।
>
> - **অনুমোদনের আগে তাক থেকে কিছুই নামে না** — draft ও approval কোনো ledger সারি লেখে না। `dispatch` এক ট্রানজ্যাকশনে গেট পাস, `ADJUSTMENT_OUT` পোস্ট ও `DEBIT_NOTE` নম্বর একসাথে করে, কারণ গেটে এগুলো একটাই ঘটনা। ডেবিট নোট নম্বর **ডিসপ্যাচে** বরাদ্দ হয়, ড্রাফটে নয় — বাতিল হওয়া রিটার্ন নইলে সিরিজে ফাঁক রেখে যেত। `ADJUSTMENT_OUT` বেছে নেওয়া হয়েছে `ISSUE`-এর বদলে: ফ্যাক্টরি কিছু ব্যবহার করেনি, মাল গেট দিয়ে ফেরত গেছে; আর নতুন একটা `RETURN_OUT` টাইপ বানালে type-ভিত্তিক প্রতিটি রিপোর্টকে নতুন শব্দ শেখাতে হতো।
> - **Claim হলো টাকা, স্টক নয়** — কাপড় কেটে ফেলা হলে ফেরত দেওয়ার কিছু নেই, অথচ ক্ষতিটা সমান বাস্তব। কিস্তিতে সেটেলমেন্ট; বকেয়ার বেশি দিলে 409 (ওটা টাইপো, উদারতা নয়)। ROLL `ISSUED`/`CLOSED` হলে রিটার্ন refuse করে বার্তা দেয় "claim তুলুন"।
> - **Advance:** সমন্বয় আলাদা সারিতে; `reverse()` সারি মোছে না, `reversed_at` স্ট্যাম্প করে — একই রিসিটে দুবার সমন্বয় ঠিক সেই ভুল যা পরে ব্যাখ্যা করতে হয়। Refund আর adjustment আলাদা: একটা টাকা ফেরত, আরেকটা বিলে কাটা। `outstandingFor($vendorId)` §১৩-র Outstanding Report।
>
> নাম্বারিং: `PURCHASE_RETURN` (PRT-), `DEBIT_NOTE` (DN-), `SUPPLIER_CLAIM` (CLM-), `SUPPLIER_ADVANCE` (ADV-)। টেস্ট `tests/Feature/Procurement/PurchaseReturnTest`, `ClaimAndAdvanceTest`।

## 14. Rate Contract / Framework Agreement

- Long-term Rate Fix, Validity Period, Item-wise Rate
- PO তৈরির সময় Rate Contract থেকে Rate auto-pull

> **As built (Phase 6):** `rate_contracts` + `rate_contract_lines`, অ্যাকশন `ManageRateContract`, সার্ভিস `RateContractResolver`, API `/rate-contracts` (+ `lookup`), UI `/procurement/rate-contracts`। নাম্বারিং `RATE_CONTRACT` (`RC-`)। Permission `procurement.contract.view_any|manage` (স্টোরকিপার পড়ে, বাইং ডেস্ক চুক্তি করে)। Status: `DRAFT → ACTIVE → CANCELLED`।
>
> চারটি সিদ্ধান্ত:
>
> - **Expiry কোনো status নয়।** তারিখই বলে দেয় চুক্তি আজ খাটে কিনা; সংরক্ষিত `EXPIRED` ততক্ষণই সত্যি থাকত যতক্ষণ কোনো sweep চালানো হয় — মাঝের সময়টায় ভুল, আর ভুলটা ঠিক সেই দিকে যেখানে গত বছরের দর এ বছরের অর্ডারে চুপচাপ বসে যায়। তাই `isLive()`/`hasExpired()` ক্যালেন্ডার থেকে পড়া হয়, স্ক্রিনে "Active" আর "In force" আলাদা করে দেখানো হয়।
> - **যে লাইনে দর লেখা আছে সেটা কখনো ওভাররাইট হয় না।** কন্ট্রাক্ট শুধু তখনই জিজ্ঞাসা করা হয় যখন লাইনে `rate` **নেই** (`array_key_exists`)। ক্রেতা দর টাইপ করলে সেটা একটা সিদ্ধান্ত — এককালীন ছাড়, সংশোধন — আর নিঃশব্দে বদলে দিলে সিদ্ধান্তটাও যেত, চিহ্নও থাকত না। এজন্য `purchase_order_lines.rate` এখন optional।
> - **দর কোথা থেকে এল তা লেখা থাকে** (`purchase_order_lines.rate_contract_id`)। ছয় মাস পরে "এই ১৯০ কেন?" প্রশ্নের উত্তর থাকে, যা কপি করা সংখ্যায় কখনো থাকে না।
> - **দুটি সক্রিয় চুক্তি একই আইটেমের একই ব্যান্ডে থাকতে পারে না** — তাহলে "চুক্তির দর"-এর দুটো মান হয়ে যেত আর সিস্টেম অনুমান করত। চেকটা **activate**-এ, draft-এ নয়: পুরনোটা চলতে চলতেই নতুন চুক্তি টাইপ করা দরকার হয়, শুধু চালু করা যায় না যতক্ষণ পুরনোটা বাতিল বা মেয়াদোত্তীর্ণ না হয়।
>
> **Price break** `rate_contract_lines.min_quantity` দিয়ে — "৫০০ গজ পর্যন্ত ২০০, তার ওপরে ১৯০" একই চুক্তিতে দুই লাইন। কলামটা না থাকলে ব্রেক লিখতে দ্বিতীয় চুক্তি লাগত, আর তখন একই দিনে দুটো চুক্তি একই আইটেম ঢাকত। Resolver সবচেয়ে উঁচু যে ব্যান্ড অর্ডার ছোঁয় সেটাই নেয়; একাধিক চুক্তি খাটলে **সংকীর্ণটা জেতে** (ফ্যাক্টরি-নির্দিষ্ট > company-wide, তারপর নতুনতর) — তবে সেটা নিরাপত্তা-জাল, মূল ব্যবস্থা নয়।
>
> চুক্তি ডিফল্টে **company-wide** (`factory_id` null): মিলের সাথে ঠিক করা দর সাধারণত পুরো গ্রুপের, আর প্রতি ফ্যাক্টরিতে এক সারি রাখলে দর নড়লেই পাঁচ জায়গায় সংশোধন করতে হতো — যার চারটা শেষমেশ বেমিল থাকত। টেস্ট `tests/Feature/Procurement/RateContractTest`।

## 15. Supplier Performance Scorecard

On-time Delivery %, Quality Rejection %, Price Competitiveness, Claim Frequency, Overall Score, Blacklist Option — [Supplier Master](01_Master_Data_Module.md#3-supplier--vendor-master)-এ Rating auto-link হবে

> **As built (Phase 6):** `supplier_scorecards`, অ্যাকশন `CalculateSupplierScorecard`, কমান্ড `procurement:score-suppliers` (সাপ্তাহিক), API `/supplier-scorecards` (+ `/calculate`, `/vendors/{vendor}/scorecard`), UI `/procurement/scorecards`। Permission `procurement.scorecard.view_any|calculate` — স্কোরিং চালালে rating নড়ে, তাই সেটা স্টোরকিপারের হাতে নয়। স্কোর হলে `vendors.rating`-এ ৫-পয়েন্ট স্কেলে বসে (Master Data §3), কারণ তালিকা থেকে সাপ্লায়ার বাছার সময় পাঁচ-তারার ভাষাই চেনা।
>
> **পুরো ডিজাইনের মূল নিয়ম: তথ্য না থাকা মানে খারাপ স্কোর নয়।** যাকে কেউ অর্ডারই দেয়নি তার on-time শতাংশ নেই; যে আইটেম একটাই মিল দেয় তার দরের তুলনা নেই। শূন্য বসালে **যাকে কেউ ব্যবহার করেনি সে প্রকৃত ব্যর্থ সাপ্লায়ারের নিচে চলে যেত** — উল্টো ফল। তাই তথ্যহীন মাপকাঠি null থাকে, ওজন থেকে বাদ যায়, আর বাকিগুলো দিয়ে গড় **পুনঃনর্মালাইজ** হয়; কার্ডে `components_json`-এ লেখা থাকে কোনগুলো গোনা হয়েছিল, যাতে ৭০ মানে কী তা পড়া যায়।
>
> চারটি মাপকাঠি ও তাদের সিদ্ধান্ত:
>
> - **On-time** — যে অর্ডারে `delivery_date` ছিল কেবল তার বিপরীতে। তারিখ না দেওয়া অর্ডার বাদ, "সময়মতো" ধরা হয় না: দেরি মাপার কিছুই নেই, আর ধরে নিলে **যে সাপ্লায়ারকে ফ্যাক্টরি কখনো তারিখে বাঁধেনি তাকেই সবচেয়ে ভালো দেখাত**।
> - **Quality** — সংখ্যায় নয়, **মূল্যে**: একটি বাতিল রোল আর একটি বাতিল বোতাম সমান ব্যর্থতা নয়। ডিসপ্যাচ হওয়া purchase return + REJECT গ্রেডের রোল; যে রোল একই সাথে return-লাইনে আছে সেটি **বাদ** দেওয়া হয় যাতে দুবার না গোনা হয় — যে স্কোরকার্ড বাড়িয়ে দোষ দেখায় সাপ্লায়ার সেটাকে উড়িয়ে দিতে পারে, তাতে লাভের চেয়ে ক্ষতি বেশি।
> - **Price** — একই আইটেমে এই সাপ্লায়ারের ওয়েটেড গড় বনাম অন্য সবার ওয়েটেড গড়। সূচক ১.০০ = বাজারদর; প্রতি ১% বেশি হলে ২ পয়েন্ট কাটে (২৫% বেশি → ৫০)। অন্য কেউ ওই আইটেম না দিলে null।
> - **Claims** — রিসিটপ্রতি ক্লেইম। প্রত্যাহার করা ক্লেইম (`WITHDRAWN`) গোনা হয় না — ওটা ফ্যাক্টরির নিজের ভুল ছিল।
>
> ওজন `SupplierScorecard::WEIGHTS`-এ (delivery ৩০, quality ৩০, price ২০, claims ২০) — মডেলে, কারণ এটা ফ্যাক্টরির নীতি; দেরি আর গুণমান দরের চেয়ে ভারী, কারণ সস্তা-কিন্তু-দেরি মিল লাইন থামায় আর থেমে যাওয়া লাইনের খরচ সাশ্রয়ের চেয়ে বেশি।
>
> স্কোরকার্ড একটি **সংরক্ষিত স্ন্যাপশট**, চাওয়ামাত্র হিসাব নয়: পেছনের সংখ্যা নড়ে (নভেম্বরে সেটল হওয়া ক্লেইম অক্টোবরের চেহারা বদলায়), আর নিঃশব্দে নিজেকে বদলে ফেলা কার্ড ঠিক সেই আলোচনাতেই অকেজো যার জন্য সে আছে — মিলের সামনে বসা। উইন্ডো **রোলিং তিন মাস**: এক ক্যালেন্ডার মাসে অনেক মিলের একটাই রিসিট থাকে, আর তখন একটি দেরি ০% হয়ে যেত। সাপ্তাহিক রান, দৈনিক নয় — তিন মাসের উইন্ডোতে সোমবারের ফল রবিবারের প্রায় সমান, আর প্রতিদিন কাঁপতে থাকা rating কেউ সাপ্লায়ারকে উদ্ধৃত করার মতো বিশ্বাস করে না।
>
> **নোট:** বিদ্যমান `VendorPerformanceReport` (SRS 32) ওয়ারেন্টি ক্লেইম ও সার্ভিস কন্ট্রাক্ট নিয়ে — মেইনটেন্যান্স-দিকের ভেন্ডর আচরণ। এটি সাপ্লাই-দিকের, তাই দুটো আলাদা রাখা হয়েছে। **Blacklist** আলাদা কিছু নয়: `vendors.status = BLACKLISTED` আগে থেকেই আছে আর `ManagePurchaseOrder::assertCanSupply()` সেটাকে সম্মান করে। টেস্ট `tests/Feature/Procurement/SupplierScorecardTest`।

## 16. Status Flows

- **Booking:** Draft → Confirmed → Converted → Cancelled
- **PR:** Draft → Submitted → Approved → Converted → Cancelled
- **PO:** Draft → Approved → Partially Received → Fully Received → Short Closed
- **LC:** Opened → Shipped → Documents Received → Closed
- **Claim:** Raised → Under Negotiation → Settled/Rejected

## 17. Key Reports

Fabric Booking Status, Material Requirement vs Ordered vs Received, Pending PR/PO, LC Outstanding, Shipment ETA Report, Vendor Scorecard, Claim Register, Advance Outstanding, Landed Cost Summary, Purchase Return Register

> **As built (Phase 6):** দশটি রিপোর্ট বিদ্যমান `ReportRegistry`-তে, নতুন `procurement` গ্রুপে (`app/Modules/Reporting/Reports/Procurement`) — তাই `/reports` স্ক্রিন, CSV/এক্সপোর্ট, কিউয়িং ও permission-ভিত্তিক ফিল্টারিং বিনা পরিবর্তনে কাজ করে। প্রতিটি রিপোর্ট নিজের ডেটার permission বহন করে (`procurement.order.view_any`, `procurement.import.view_any`, …), কোনো সাধারণ "রিপোর্ট দেখার" অনুমতি নয়।
>
> `procurement_pending_requisitions`, `procurement_pending_orders`, `procurement_fabric_bookings`, `procurement_lc_outstanding`, `procurement_shipment_eta`, `procurement_landed_cost_summary`, `procurement_return_register`, `procurement_claim_register`, `procurement_advance_outstanding`, `procurement_supplier_scorecard`।
>
> কয়েকটি সিদ্ধান্ত:
>
> - **Pending PO লাইন ধরে, হেডার ধরে নয়।** অর্ধেক গৃহীত অর্ডার না "বাকি" না "সম্পূর্ণ"; হেডারে গুটিয়ে আনলে হয় বাকিটা বাড়িয়ে দেখাতে হতো, নয় লুকাতে হতো। দেরি মাপা হয় লাইনের নিজের `required_date` ধরে, না থাকলে অর্ডারের `delivery_date` — দুটোই না থাকলে **রায় ছাড়াই** দেখানো হয়, "সময়মতো" ধরে নয়।
> - **Pending PR বলে কার অপেক্ষায়** — অনুমোদনের না ক্রয়ের। দুটোর জন্য দুজন আলাদা মানুষকে নড়তে হয়, আর একটামাত্র "pending" সংখ্যা কাউকেই কিছু বলে না।
> - **Advance Outstanding পিরিয়ড রিপোর্ট নয়** — দুই বছর আগে দেওয়া যে অ্যাডভান্স কখনো সমন্বয় হয়নি সেটাই এই রিপোর্টের কারণ, আর ত্রৈমাসিকে ছেঁটে দিলে সবচেয়ে পুরনো ও খারাপগুলোই লুকিয়ে যেত। বদলে বয়স দেখানো হয়।
> - **Return Register-এ যা এখনো যায়নি তাও থাকে**, `stock_moved` কলামসহ: অনুমোদিত অথচ ডিসপ্যাচ না হওয়া রিটার্ন মানে স্টোর ভাবছে মাল চলে গেছে আর তাকে এখনো পড়ে আছে।
>
> **যেটি বানানো হয়নি:** *Material Requirement vs Ordered vs Received* — প্রোডাকশন ডোমেইনের (কাটিং প্রোগ্রাম), যা ব্যবহারকারীর সিদ্ধান্তে এই প্রজেক্টের বাইরে। `MaterialRequirement` সার্ভিস ও `/material-requirement` API আছে; মার্জের সময় রিপোর্টে মোড়ানো যাবে। টেস্ট: বিদ্যমান `ReportCatalogTest` দশটিরই অনুবাদ, permission ও কলাম-আকার যাচাই করে।

## 18. Core Business Rules

1. Fabric Booking ছাড়াও সরাসরি PR/PO করা যাবে (flexible)
2. Approved PR ছাড়া RFQ/PO ব্লক
3. Approved PO ছাড়া Receiving ব্লক
4. Fabric Inspection ছাড়া Roll Available হবে না
5. Over Booking ও Over Receiving-এ Warning/Block (configurable)
6. Landed Cost Post-এর পর Rate পরিবর্তন বন্ধ
7. Advance Adjustment না করলে Bill Fully Settle হবে না
8. সব Critical Action-এ Audit Log

---

**পরবর্তী:** [Inventory Module](02_Inventory_Module.md) | [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md) | [Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md)
