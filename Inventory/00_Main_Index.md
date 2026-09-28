# Annotech RMG — Inventory + Procurement System
## Main Index / Master Document

**Project:** Annotech RMG
**Date:** September 2026
**Factory Type:** Both Knit + Woven

এই ডকুমেন্ট পুরো সিস্টেম ডিজাইনের কেন্দ্রীয় সূচিপত্র। প্রতিটা মডিউলের বিস্তারিত স্পেসিফিকেশন আলাদা ফাইলে আছে, নিচের লিংক থেকে যাওয়া যাবে। Phase-wise Implementation Plan, Checklist এবং End-to-End Workflow এই মূল ফাইলে আছে, কারণ এগুলো একাধিক মডিউল জুড়ে কাজ করে।

---

## মডিউল ফাইলসমূহ

| ফাইল | বিষয়বস্তু |
|---|---|
| [01 — Master Data Module](01_Master_Data_Module.md) | Item Master (Base+Extension), Supplier, Department, Order/Style, UOM, HS Code — সব মডিউলের কমন রেফারেন্স ডাটা |
| [02 — Inventory Module](02_Inventory_Module.md) | Roll Management, Inspection, Split/Merge, Reservation, Issue+BOM Variance, Subcontract, Landed Cost↔Roll Cost |
| [03 — Procurement Module](03_Procurement_Module.md) | Fabric Booking, Material Requirement, Purchase Requisition, RFQ/PO, GRN, PI/LC/Shipment, Landed Cost, Return/Claim, Advance, Rate Contract, Scorecard |
| [04 — Floor/Store Requisition Module](04_Floor_Store_Requisition_Module.md) | User Floor Requisition, Store Fulfillment, Auto PR Trigger on Shortage |
| [05 — Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md) | RBAC, Approval Engine, Notification, GL Integration, Bonded Warehouse/UD-UP |

## মডিউল ডিপেন্ডেন্সি ডায়াগ্রাম

```
                    ┌─────────────────────────────┐
                    │   MASTER DATA MODULE          │
                    │   (কারো ওপর নির্ভর করে না)     │
                    └───────────┬───────────┬─────────┘
                                │           │
                     রেফারেন্স করে         রেফারেন্স করে
                                │           │
                 ┌──────────────▼──┐   ┌───▼───────────────┐
                 │ INVENTORY MODULE │   │ PROCUREMENT MODULE │
                 └──────────────────┘   └─────────────────────┘
                                │           │
                        রেফারেন্স করে (দুইটাই)
                                │
                 ┌──────────────▼──────────────┐
                 │  FLOOR/STORE REQUISITION      │
                 │  MODULE                       │
                 └────────────────────────────────┘

     [ SHARED SYSTEMS & COMPLIANCE ] — সব মডিউল এই সার্ভিস ব্যবহার করে
     (RBAC, Approval Engine, Notification, GL Integration, Compliance)
```

> **টিম রিভিউর পর চূড়ান্ত (সেপ্টেম্বর ২০২৬):**
> - **Item Master = আলাদা মডিউল, আলাদা `items` টেবিল** (`app/Modules/ItemMaster`, সাইডবারে নিজস্ব গ্রুপ, permission `itemmaster.item.*`)। `SparePart` মেইনটেন্যান্স ক্যাটালগ হিসেবে অপরিবর্তিত। Item-এর স্টক লেজার Phase 1-এ **নিজস্ব `item_stock_*` টেবিলে** (`ItemLedger`, স্পেয়ার-পার্টস লেজার অপরিবর্তিত — [Inventory §16](02_Inventory_Module.md#16-relationship-with-existing-inventory-code))।
> - **Store হায়ারার্কি:** `Company → Factory → Warehouse → Store → Bin` — বিদ্যমান কোডবেসের `app/Modules/Inventory` মডিউল রিইউজ হবে, নতুন সমান্তরাল Store এনটিটি না (বিস্তারিত [Master Data §5](01_Master_Data_Module.md#5-store-existing-warehouse-hierarchy-reuse) ও [Inventory §16](02_Inventory_Module.md#16-relationship-with-existing-inventory-code)-এ)।
> - **WorkOrderPart অপরিবর্তিত থাকবে:** কাজের অর্ডারে পার্ট রিকোয়েস্ট/ইস্যুর বিদ্যমান ফ্লো (`WorkOrderPart`) যেভাবে আছে সেভাবেই চলবে; Floor/Store Requisition এটার প্রতিস্থাপন না, বরং সাধারণ ফ্লোর/ডিপার্টমেন্ট প্রয়োজনের জন্য একটা সমান্তরাল, আলাদা সিস্টেম (বিস্তারিত [Floor/Store Requisition §1](04_Floor_Store_Requisition_Module.md)-এ)।

---

# PHASE-WISE IMPLEMENTATION INSTRUCTION

## Phase 0 — Foundation
- [Master Data](01_Master_Data_Module.md): Supplier (বিদ্যমান Vendor মডিউল এক্সটেন্ড হবে), Item Master (Base+Extension), Department, UOM & Conversion, HS Code/Tax — এসব **reference/lookup, real FK দিয়ে**; ঐতিহাসিক ডাটা সুরক্ষায় ট্রানজেকশন-লাইনে টার্গেটেড স্ন্যাপশট কলাম (unit_cost/rate/spec) ব্যবহার হবে
- [Procurement](03_Procurement_Module.md#9-grn-goods-receipt-note--formal-definition): GRN entity (header+line) formal build
- [Shared Systems](05_Shared_Systems_and_Compliance.md): Role/Permission, Approval Engine, Notification, Audit Log — এগুলো **বিদ্যমান প্ল্যাটফর্মে ইতিমধ্যে ইমপ্লিমেন্ট করা আছে**; এই ফেজে শুধু নতুন Document Type/Event-গুলো (PR, PO, Roll Unreserve ইত্যাদি) সেই বিদ্যমান ব্যবস্থায় **কনফিগার** করতে হবে, নতুন করে বানাতে হবে না

## Phase 1 — Core Master + Basic Procure-to-Store
- [Master Data](01_Master_Data_Module.md): Item Master Quantity/Lot tracking mode
- [Procurement](03_Procurement_Module.md#5-purchase-requisition-pr): PR + Single-level Approval + PO (Local flow only) + Basic Receiving (non-fabric)
- [Procurement](03_Procurement_Module.md#8-local-vs-import-purchase): Local Purchase Flow
- [Inventory](02_Inventory_Module.md): Reorder Level Alert
- [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md): বেসিক Floor Requisition ফর্ম + Item Library Department dropdown

## Phase 2 — Fabric Core (Roll + Inspection + Import Basics)
- [Inventory](02_Inventory_Module.md#3-fabric-roll-management): Fabric Roll Management + Inspection + Knit/Woven Defect Catalogue
- [Procurement](03_Procurement_Module.md#2-complete-process-flow): Import Flow skeleton (PO with PI reference), Partial Receiving, GRN→Inspection trigger
- [Inventory](02_Inventory_Module.md#10-landed-cost--roll-cost-timing): Landed Cost — Provisional cost logic (শুধু ধাপ ১–২)
- *(যদি ফ্যাক্টরি মূলত Export/Bonded হয়, [Shared Systems — Bond/UD-UP](05_Shared_Systems_and_Compliance.md) এখানেই আনার কথা বিবেচনা করুন)*

## Phase 3 — Booking, Reservation, Requirement, Issue & Variance, Floor Requisition Full

> **বাস্তবায়নের ক্রম:** Phase 3 দুই ভাগে করা হয়েছে। **3A** — Item Reservation (soft/hard/earmark) + পূর্ণ Floor Requisition fulfilment, returnable আইটেম সহ। **3B** — ন্যূনতম Production মাস্টার (Order/Style/BOM/Cutting Program), Fabric Booking + auto soft reservation, Material Requirement + Auto PR, Issue to Production + BOM Variance। দুটোই হয়ে গেছে। 3A আগে করা হয়েছে ইচ্ছাকৃতভাবে: Booking-এর "auto soft reservation" ওই ভিতটাই পুনর্ব্যবহার করে।
>
> Production মাস্টারটি ([Master Data §6](01_Master_Data_Module.md#6-order--style--cutting-program-master)) কোডবেসে ছিল না; স্পেক অনুযায়ী Merchandising মডিউল না থাকায় এখানেই ন্যূনতম সংস্করণ তৈরি হয়েছে — `app/Modules/Production`, ইচ্ছাকৃতভাবে পাতলা (কস্টিং/স্যাম্পলিং/শিপমেন্ট নেই)।
- [Procurement](03_Procurement_Module.md#3-fabric-booking): Fabric Booking + Auto Reservation Sync
- [Procurement](03_Procurement_Module.md#4-material-requirement--auto-pr): Material Requirement + Auto PR (পূর্ণ)
- [Inventory](02_Inventory_Module.md#7-reservation--allocation): Reservation & Allocation (Soft/Hard)
- [Inventory](02_Inventory_Module.md#9-issue-to-production--bom-consumption-variance): Issue to Production + BOM Consumption Variance
- [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md): পূর্ণ Store Fulfillment Logic + Shortage → Auto PR
- [Shared Systems](05_Shared_Systems_and_Compliance.md): Approval Engine multi-level upgrade

## Phase 4 — Roll Split/Merge, Yarn, Chemical, Subcontract
- [Inventory](02_Inventory_Module.md#5-roll-splitting-logic): Roll Split/Merge Logic
- [Inventory](02_Inventory_Module.md#12-other-features): Yarn Lot-wise + Chemical Expiry/FIFO
- [Inventory](02_Inventory_Module.md#8-subcontract--job-work-fabric-tracking): Subcontract/Job-work Tracking
- [Inventory](02_Inventory_Module.md#10-landed-cost--roll-cost-timing): Landed Cost recalculation cascade to Split/Merged rolls

## Phase 5 — PI/LC/Shipment + Landed Cost Full + Return/Claim/Advance
- [Procurement](03_Procurement_Module.md#10-pi--lc--shipment-tracking): PI + LC + Shipment Tracking পূর্ণ
- [Procurement](03_Procurement_Module.md#11-landed-cost): Landed Cost পূর্ণ (multi-GRN distribution, rate lock)
- [Procurement](03_Procurement_Module.md#12-purchase-return--claim) + [Inventory Roll Status Link](02_Inventory_Module.md#11-purchase-return--roll-status-link)
- [Procurement](03_Procurement_Module.md#13-advance-payment-to-supplier): Advance Payment + Outstanding Report
- [Shared Systems](05_Shared_Systems_and_Compliance.md): GL Integration wiring

## Phase 6 — Compliance, Rate Contract, Scorecard, Full Reports
- [Shared Systems](05_Shared_Systems_and_Compliance.md): Bonded Warehouse/UD-UP (যদি Phase 2-এ না করা হয়ে থাকে)
- [Procurement](03_Procurement_Module.md#14-rate-contract--framework-agreement): Rate Contract
- [Procurement](03_Procurement_Module.md#15-supplier-performance-scorecard): Supplier Scorecard
- [Inventory](02_Inventory_Module.md#12-other-features): Physical Inventory (Cycle Count + Full Stock Taking)
- সব বাকি Reports — [Inventory Reports](02_Inventory_Module.md#14-key-reports), [Procurement Reports](03_Procurement_Module.md#17-key-reports), [Floor Requisition Reports](04_Floor_Store_Requisition_Module.md#10-reports)

---

# IMPLEMENTATION CHECKLIST

## Phase 0
- [x] Supplier/Vendor Master schema + approval workflow (`vendors` টেবিল এক্সটেন্ড: bank/BIN/trade licence/supply categories/approval_status/payment type)
- [x] Item Master — নিজস্ব `items` + `item_categories` + Fabric/Yarn/Chemical extension টেবিল, `/items` API, `/item-master` UI, legacy ইমপোর্ট কমান্ড ([Inventory §16](02_Inventory_Module.md#16-relationship-with-existing-inventory-code)-এ বিস্তারিত)
- [x] Department Lookup List + `default_item_type` অ্যাট্রিবিউট সহ (গ্যাপ-ফিক্স)
- [x] বিদ্যমান `Store` মডেলে `store_type` (General/Bonded/Sub-store) কলাম যোগ (নতুন টেবিল না — [Master Data §5](01_Master_Data_Module.md#5-store-existing-warehouse-hierarchy-reuse))
- [x] UOM & Conversion Master (`uoms`, `uom-conversions`) + item-wise `purchase_uom_id`
- [x] HS Code & Tax Master
- [x] GRN Header + Line schema (`goods_receipts`, `goods_receipt_lines`; multi-PO/multi-GRN workflow টেস্ট Phase 1-এ)
- [x] Item Selection — `GET /spare-parts?search=&item_department_id=&item_type=&sub_category_id=` ফিল্টার; Typeahead UI Floor Requisition ফর্মের সাথে Phase 3-এ
- [x] Numbering: `PURCHASE_REQUISITION`/`FLOOR_REQUISITION`/`PURCHASE_ORDER`/`FABRIC_BOOKING` যোগ; Approval/Notification event কনফিগারেশন যার যার ফেজে (PR → Phase 1)
- [x] Accounts মাস্টার ডাটা: Bank, Payment Type, GL Account (নতুন `finance` গ্রুপ)

## Phase 1
- [x] Item Master Quantity/Lot tracking মোড টেস্ট — নিজস্ব `item_stock_balances`/`item_stock_transactions` + `ItemLedger` (QUANTITY pooled, LOT per-lot balance ও lot_no বাধ্যতামূলক; ROLL/SERIAL Phase 2 পর্যন্ত refused) — `tests/Feature/ItemMaster/ItemLedgerTest`
- [x] item_type auto-suggestion Department-এর `default_item_type` থেকে (`SaveItem::withDerivedItemType`, `ItemApiTest`)
- [x] PR creation + approval flow — `/purchase-requisitions`, Approval Engine-এ `PURCHASE_REQUISITION` entity type (workflow না থাকলে সরাসরি APPROVED) — `PurchaseRequisitionApiTest`
- [x] PO creation (Local flow) + revision history — `/purchase-orders`, PR থেকে বা সরাসরি; `purchase_order_revisions` স্ন্যাপশট, অনুমোদিত অঙ্ক ছাড়ালে পুনরায় approval; IMPORT refused (Phase 4) — `PurchaseOrderApiTest`
- [x] Direct GRN → Stock post (non-fabric) — `/goods-receipts` (PO-র বিপরীতে বা direct), `complete` → `ItemLedger::post('RECEIPT')` per line; over-receipt `procurement.allow_over_receipt` সেটিং দিয়ে
- [x] Reorder level alert টেস্ট — outbound movement-এ crossing হলে STOREKEEPER/STORE_MANAGER-কে `LOW_STOCK` (২৪ ঘণ্টায় একবার); `GET /items/low-stock` + `/item-master/low-stock` পেজ
- [x] Floor Requisition ফর্ম (Item department dropdown সব দেখাচ্ছে — `FloorRequisitionApiTest`); store review/issue Phase 3
- [x] Item Deactivate হলে Draft/Pending ট্রানজেকশনে block-with-message (`ItemLineResolver::assertAllActive`, submit-এ 409) + Approved/Closed ট্রানজেকশনে `item_code`/`item_name` স্ন্যাপশট অক্ষত

## Phase 2
- [x] Roll fields ও status transition — নিজস্ব `fabric_rolls` টেবিল (`app/Modules/ItemMaster`, item ledger-এর precedent অনুসরণ করে — [§16](02_Inventory_Module.md#16-relationship-with-existing-inventory-code)), স্পেক-এর সব ফিল্ড; স্ট্যাটাস RECEIVED→UNDER_INSPECTION→AVAILABLE/DAMAGED/RETURNED reachable, RESERVED/ISSUED/CLOSED/SPLIT/MERGED/SENT_FOR_SUBCONTRACT Phase 3/4-এর জন্য কলাম-রেডি — `tests/Feature/ItemMaster/FabricInspectionTest`
- [x] Knit + Woven আলাদা Defect Catalogue — `fabric_defects` + Master Data type `fabric-defects` (platform-seeded ২০টি ডিফল্ট, কোম্পানি নিজেরটাও যোগ করতে পারে) — `MasterDataApiTest`
- [x] Inspection screen (single ownership) — `/item-master/inspection` (queue) ও `/item-master/inspection/{roll}` (inspect ফর্ম); permission `itemmaster.roll.view_any`/`.inspect`
- [x] GRN → Inspection Pending → Result sync ফিরে GRN-এ — GRN complete-এ ROLL লাইন `FabricRoll` (UNDER_INSPECTION) তৈরি করে, GRN status নিজে `UNDER_INSPECTION`; `InspectFabricRoll` প্রতিটি রোলের পর `GoodsReceiptLine.inspection_status` (PENDING/PASSED/FAILED/PARTIAL) ও, সব রোল resolve হলে, GRN-কে `COMPLETED`-এ ফেরত পাঠায়
- [x] Reject → Roll status Damaged/Return — Grade একটাই ফিল্ড (A/B/C/Reject); Reject হলে disposition (Damaged/Returned) বাধ্যতামূলক, রোলের status হয়ে যায় — Claim/Return workflow ট্রিগার হয় না (Phase 5)
- [x] Provisional Unit Cost হিসাব সঠিক কিনা — রোলের `unit_cost` = GRN লাইনের rate (PO-লিংকড হলে PO লাইনের rate); টেস্টে যাচাই করা

## Phase 3
- [x] Fabric Booking → Auto Soft Reservation — `ManageFabricBooking::confirm()` প্রতি লাইনে `reservation_type = BOOKING`-এর earmark তোলে (মাল মিলে, তাক-এ নেই — তাই earmark, encumbrance নয়) — `tests/Feature/Procurement/FabricBookingTest`
- [x] Booking Convert to PO → Reservation ref আপডেট — `convertToRequisition()` DRAFT PR তোলে (`source_reference = booking.id`) ও `converted_pr_id` লিখে রাখে। **সচেতন বিচ্যুতি:** দাবিটি booking line-এর সাথেই কি-করা থাকে, PR-এ সরানো হয় না — সূত্র দুদিক থেকেই অনুসরণযোগ্য, আর release/fill একটাই কি ধরে কাজ করে
- [x] Booking Cancel → Reservation Auto Release — `cancel()` দাবি `CANCELLED` করে দেয়; না করলে ওই গজ চিরকাল রিকোয়ারমেন্ট থেকে বাদ থাকত আর ফ্যাক্টরি চুপচাপ কম কিনত
- [x] Material Requirement ফর্মুলা সঠিক আউটপুট — `MaterialRequirement` (রিয়েল-টাইম, কিছু সংরক্ষণ করে না); ওয়েস্টেজ অ্যালাউন্স BOM-এর `requiredFor()`-এর ভিতরেই — `tests/Feature/Procurement/MaterialRequirementTest`
- [x] Auto PR generation (Material Requirement + Floor Shortage — দুই সোর্স থেকেই) — Floor Shortage: `FulfilFloorRequisition::recordShortage()`; Material Requirement: `RaiseRequirementRequisition::forProgram()` (প্রোগ্রামপ্রতি একবার, DRAFT-এ)
- [x] Reservation Soft vs Hard Allocation — নিজস্ব `item_reservations` টেবিল (`app/Modules/ItemMaster`): soft = (item, bin, lot) ব্যালেন্সে `quantity_reserved` বাড়ায়, hard = একটি নির্দিষ্ট রোল `AVAILABLE→RESERVED`; কোনো ledger row লেখে না (কিছু নড়ে না) — `tests/Feature/ItemMaster/ItemReservationTest`
- [x] Issue to Production → BOM Variance ক্যালকুলেশন — `IssueToProduction`; প্রতি ইস্যুতে ভ্যারিয়েন্সের স্ন্যাপশট রাখা হয় (পরে আজকের BOM দিয়ে হিসাব করলে ইতিহাস বদলে যেত) — `tests/Feature/Production/IssueToProductionTest`
- [x] Variance tolerance exceed → Approval trigger — টলারেন্স ছাড়ালে সারি `PENDING_APPROVAL`, **স্টক নড়ে না**; অনুমোদন এলে তখন ledger-এ পোস্ট হয়। Approval entity `PRODUCTION_ISSUE`; টলারেন্স সেটিং `production.bom_variance_tolerance_percent` (ডিফল্ট ৫%, স্পেক বলেছে সংখ্যা এখনো অনির্ধারিত)
- [x] Floor Requisition Full/Partial Issue + Shortage → Auto PR বাধ্যতামূলক টেস্ট — `FulfilFloorRequisition::issue()`-এর শেষে `recordShortage()` এড়ানো যায় না; সব short লাইন মিলে **একটি** PR — `tests/Feature/Requisition/FloorRequisitionFulfilmentTest`
- [x] Floor Requisition Shortage → Auto PR-এর জন্য Reservation Earmark তৈরি হচ্ছে কিনা (গ্যাপ-ফিক্স) — প্রতি short লাইনে একটি `PENDING_ARRIVAL` earmark; GRN complete হলে `ReserveItemStock::fillPendingEarmarks()` পুরনো দাবি আগে পূরণ করে ও `EARMARK_STOCK_ARRIVED` পাঠায়
- [x] একাধিক Floor Requisition একই সময়ে একই আইটেমের Shortage PR/Reservation তৈরি করলে race condition/double-count হচ্ছে না (concurrency টেস্ট, গ্যাপ-ফিক্স) — ইস্যুর আগে `ItemLedger::lockBalance()`; শেষ ইউনিট দুজন একসাথে নিতে পারে না (409)
- [x] Floor Requisition → PR ফিরে notification → Store fulfillment close — `FLOOR_REQUISITION_ISSUED` (অনুরোধকারীকে), `FLOOR_REQUISITION_SHORTAGE` (স্টোর ম্যানেজারকে, DRAFT PR অপেক্ষমাণ), `EARMARK_STOCK_ARRIVED`; `close` বাকি দাবি ছেড়ে দেয়, ধার দেওয়া লাইন ফেরত না এলে ব্লক করে

## Phase 4
- [x] Roll Split validation (child sum = parent) — `SplitFabricRoll`; টুকরোর যোগফল প্যারেন্টের সমান না হলে 409, কারণ আধা মিটার হারানো স্প্লিট আসলে ছদ্মবেশী স্টক অ্যাডজাস্টমেন্ট। প্যারেন্ট `SPLIT` (ক্লোজড), শেড/লট/বিন অপরিবর্তিত, খরচ প্রতি-ইউনিট হিসেবেই বাহিত। **RESERVED রোল স্প্লিট করলে দাবিটি টুকরোগুলোতে সরে যায়** (প্যারেন্টের দাবি কারণসহ ক্লোজ) — `tests/Feature/ItemMaster/RollSplitMergeTest`
- [x] Roll Merge — weighted average cost — `MergeFabricRolls`; (২০০×২৫০ + ৫০×৩০০)/২৫০ = ২৬০, সরল গড় ২৭৫ নয়। শুধু একই item/shade/lot/bin-এর `AVAILABLE` রোল; সোর্সগুলো `MERGED` + `merged_into_roll_id`
- [x] Subcontract Out/In challan + partial return — `subcontract_challans`, `ManageSubcontractChallan`, `/subcontract-challans` API। রোল `SENT_FOR_SUBCONTRACT`, **ledger-এ কিছুই যায় না** (কিছু খরচ হয়নি, বিক্রিও হয়নি)। লাইন সেটেল হয় `sent = returned + wastage` হলে; ফেরত এলে রোল আসল দৈর্ঘ্য নিয়ে তাকে ফেরে — `tests/Feature/ItemMaster/SubcontractChallanTest`
- [x] Landed Cost cascade to Split/Merged children — `RecalculateRollCosts`; সংশোধিত দর চাইল্ড রোলে নামে ও মার্জড রোলকে সোর্স থেকে পুনরায় ওয়েটেড করে। ইস্যু/ক্লোজড রোলে `cost_adjusted_after_issue` ফ্ল্যাগ ওঠে (§10.5)। ল্যান্ডেড কস্ট **পোস্টিং** Phase 5-এর, এটি রোল-লেজারের নিজের অর্ধেক
- [~] Yarn lot-wise issue to knitting — লট-ভিত্তিক ট্র্যাকিং, `item_lots` ও FIFO/নিকটতম-মেয়াদ সাজেশন হয়ে গেছে (`LotSuggestion`)। "Issue to Knitting" ধাপটি প্রোডাকশন ডোমেইনের, যা এই প্রজেক্টের বাইরে রাখা হয়েছে (ব্যবহারকারীর সিদ্ধান্ত — অন্য প্রজেক্টে আছে)
- [x] Chemical expiry alert + FIFO suggestion — `item_lots`-এ expiry/manufactured/supplier lot (GRN-এর গেটে পড়া); `LotSuggestion` ক্রম: নিকটতম মেয়াদ → FIFO → মেয়াদোত্তীর্ণ সবার শেষে ও ফ্ল্যাগসহ (লুকিয়ে নয় — যে লট স্ক্রিন থেকে হারিয়ে যায় তা কেউ অবলোপনও করে না)। দৈনিক `itemmaster:expiry-alerts`, সেটিং `inventory.lot_expiry_notice_days` (ডিফল্ট ৩০) — `tests/Feature/ItemMaster/LotExpiryTest`

## Phase 5
- [x] PI ↔ PO linkage + version control — `proforma_invoices` PO-র সাথে বাঁধা; সংশোধন মানে **নতুন ভার্সন** (`version`, `supersedes_id`), এডিট নয় — ব্যাংক পুরনো কাগজের বিপরীতেই এলসি খুলেছিল, ওভাররাইট করলে এলসি এমন এক দলিলের সাথে মিলত যা আর সে কথা বলে না। পুরনোটি `SUPERSEDED`, তার বিপরীতে নতুন এলসি খোলা 409 — `tests/Feature/Procurement/ImportDocumentTest`
- [x] LC amount vs PO amount matching — `LetterOfCredit::varianceAgainstOrder()` (lc.amount − order.subtotal) API ও স্ক্রিন দুই জায়গাতেই সংখ্যা হিসেবে দেখায়, কাগজে কষতে হয় না। অ্যামেন্ডমেন্ট নিজের সারিতে থাকে — ব্যাংক প্রতিটির জন্য চার্জ নেয় আর কারণটাই অর্ডার কীভাবে নড়ল তার ইতিহাস; কারণ ছাড়া অ্যামেন্ডমেন্ট 422
- [x] Shipment ETA tracking + alert — `shipments`-এ `original_eta` **ও** `eta` দুটোই থাকে; শুধু পূর্বাভাসটাই নড়ে, প্রতিশ্রুতিটা নয় (ফারাকই সাপ্লায়ারের ডেলিভারি রেকর্ড)। `document_status` মালের status থেকে আলাদা — কাগজ ব্যাংকে আটকে থাকলে পৌঁছানো মালও ছাড় করানো যায় না। দৈনিক `procurement:shipment-alerts`: আসন্ন, বিলম্বিত ও মেয়াদ ফুরানো এলসি; সেটিং `procurement.shipment_eta_notice_days` (৭) ও `procurement.lc_expiry_notice_days` (১৫)
- [x] Landed Cost distribution (multi-GRN, qty/value) — `ManageLandedCost`; একাধিক GRN একসাথে, basis `VALUE`/`QTY`; `distribute()` **শেষ লাইনে অবশিষ্টটুকু** দেয় যাতে শেয়ারের যোগফল ঠিক মেলে (রাউন্ডিং-এ পয়সা হারায় না)। QUANTITY আইটেম `ItemLedger::revalue()` (শূন্য-পরিমাণ সারি, শুধু WAC নড়ে), ROLL আইটেম `RecalculateRollCosts::forGoodsReceipt()`
- [x] Landed Cost post-এর পর rate lock — `goods_receipts.landed_cost_posted_at`; `GoodsReceipt::isRateLocked()` সত্য হলে রিসিট আর সম্পাদনযোগ্য নয়, কারণ ওই দর এখন রিপোর্ট হয়ে যাওয়া একটি ভ্যালুয়েশনের অংশ — `tests/Feature/Procurement/LandedCostTest`
- [x] Return → Roll status "Returned" + stock decrease + Debit Note — `ManagePurchaseReturn`; অনুমোদনের আগে **তাক থেকে কিছুই নামে না**। dispatch-এ গেট পাস, `ADJUSTMENT_OUT` পোস্ট ও `DEBIT_NOTE` নম্বর এক ট্রানজ্যাকশনে (গেটে এগুলো একটাই ঘটনা)। ডেবিট নোট নম্বর ড্রাফটে নয়, ডিসপ্যাচে বরাদ্দ হয় — বাতিল রিটার্ন সিরিজে ফাঁক রেখে যেত — `tests/Feature/Procurement/PurchaseReturnTest`
- [x] Claim raise → settlement tracking — `ManageSupplierClaim`; কাপড় কেটে ফেলা হলে ফেরত দেওয়ার কিছু থাকে না, তাই দাবিটা টাকার — **ledger-এ কিছুই যায় না**। কিস্তিতে সেটেলমেন্ট, বকেয়ার বেশি দিলে 409 — `tests/Feature/Procurement/ClaimAndAdvanceTest`
- [x] Advance payment → auto/manual adjustment — `ManageSupplierAdvance`; সমন্বয় আলাদা সারিতে, `reverse()` সারি মুছে না, `reversed_at` স্ট্যাম্প করে (একই রিসিটে দুবার সমন্বয় ঠিক সেই ভুল যা পরে ব্যাখ্যা করতে হয়)। `outstandingFor($vendorId)` §১৩-র রিপোর্ট
- [x] GL entries প্রতিটি event-এ সঠিক — `gl_event_mappings` (event → debit/credit account_id, **হার্ডকোড নয়**) + `gl_journal_entries`/`gl_journal_lines`; §২-র ছয়টি ইভেন্টই ওয়্যার করা। `(company, event, source_type, source_id)` unique — একই GRN দুবার বইয়ে ওঠে না। পোস্টিং **সাইড-ইফেক্ট**: ম্যাপিং না থাকলে/বন্ধ থাকলে কিছুই থ্রো করে না, কারণ অ্যাকাউন্টস টিম চার্ট শেষ করেনি বলে গেটে মাল আটকানো চলে না (§৫.১)। প্রতিটি কোম্পানি প্লেসহোল্ডার (suspense) চার্ট নিয়ে শুরু করে — `SeedGlDefaults`, অনবোর্ডিংয়ে ও `finance:seed-gl-defaults`; sign-off এলে `/settings/gl-mapping` স্ক্রিনে শুধু ডাটা বদলায় — `tests/Feature/Finance/GlPostingTest`
- **সচেতন সম্প্রসারণ:** IMPORT পারচেজ অর্ডার আর refused নয় (Phase 1-এ ছিল, কারণ তখন LC/কাস্টমস-এর কাগজ ছিল না)। টাইপটা অর্ডারের গায়ে একটা লেবেল মাত্র — PI/LC/Shipment নিজেদের টেবিলে থাকে, তাই অর্ডারে নতুন কোনো ফিল্ড লাগেনি

## Phase 6
- [x] Bond License + UD/UP entity + consumption reconciliation — নিজস্ব `Compliance` মডিউল + `/bond-licences` ও `/utilisation-declarations` API + `/compliance/*` UI ([Shared Systems §3.6](05_Shared_Systems_and_Compliance.md#36-যেভাবে-তৈরি-হয়েছে-phase-6))। এনটাইটলমেন্টের বিপরীতে আমদানি আর ঘোষণার বিপরীতে খরচ — দুটোই **গণনা করা হয়** (GRN লাইন ও স্টক লেজার থেকে), পাশে কোনো running total রাখা হয়নি — রিসিট বাতিল হলেই দ্বিতীয় উত্তরটা ভুল হয়ে যেত। অপচয় লাইনপ্রতি শতকরা; রিকনসিলিয়েশনের পার্থক্য **সংকেতযুক্ত** (কম খরচ মানে রপ্তানির সমর্থন ছাড়া বন্ডেড মাল, বেশি মানে ঘাটতি — দুটো উল্টো সমস্যা)। মেয়াদ কোনো status নয়, তারিখ থেকে পড়া হয়; মেয়াদোত্তীর্ণ লাইসেন্সের বিপরীতে ঘোষণা ইস্যু 422 — `tests/Feature/Compliance/BondComplianceTest`
- [x] Bonded store_type + duty ক্যালকুলেশন — `AssessBondedDuty` + `/duty-assessments` API + `/compliance/duty-assessments` UI। শুল্ক বসানো **সচেতন কাজ, লেজারের সাইড-ইফেক্ট নয়** — বন্ডেড ফ্যাবরিক কাটিংয়ে ইস্যু করা বন্ডের উদ্দেশ্যই, লঙ্ঘন নয়; প্রতিটি আউটবাউন্ড মুভমেন্টে অটো শুল্ক বসালে সিস্টেমটা প্রথম দিনেই অব্যবহার্য হতো, তাই `reason` মানুষ দেয়। `store_type = BONDED` না হলে 422; VAT শুল্ক-পরিশোধিত মূল্যের ওপর (কাস্টমস যেভাবে কষে); হার সারিতে কপি হয় বলে পরে হার বদলালেও পুরনো অ্যাসেসমেন্ট আগের কথাই বলে। **HS কোড না থাকলে সারি বাদ যায় না, শূন্য হারে লেখা হয়** (`is_rated: false`) — যে চালানের হার কেউ বলতে পারল না সেটা কাস্টমসের কাছে প্রশ্ন, মুছে দেওয়ার সারি নয়। Import PO-তে `bond_applicable`/`bond_licence_id`/`duty_exemption_reference` আর লাইনে `hs_code_id`
- [x] Rate Contract → PO তে rate auto-pull — `rate_contracts`/`rate_contract_lines` + `RateContractResolver` + `/rate-contracts` API + `/procurement/rate-contracts` UI ([§14](03_Procurement_Module.md#14-rate-contract--framework-agreement))। PO লাইনে `rate` না থাকলেই কেবল চুক্তির দর বসে — ক্রেতার টাইপ করা দর কখনো ওভাররাইট হয় না; দর কোন চুক্তি থেকে এল তা `purchase_order_lines.rate_contract_id`-এ লেখা থাকে। `min_quantity` দিয়ে price break; দুটি সক্রিয় চুক্তি একই আইটেমের একই ব্যান্ডে থাকতে পারে না (চেক activate-এ, draft-এ নয়)। Expiry কোনো status নয় — তারিখ থেকে পড়া হয় — `tests/Feature/Procurement/RateContractTest`
- [x] Supplier Scorecard metrics auto-calculate — `supplier_scorecards` + `CalculateSupplierScorecard` + সাপ্তাহিক `procurement:score-suppliers` + `/procurement/scorecards` UI ([§15](03_Procurement_Module.md#15-supplier-performance-scorecard))। চারটি মাপকাঠি (on-time, quality by **value**, price index, claims) সিস্টেমে আগে থেকে লেখা তথ্য থেকেই। **তথ্য না থাকা মানে শূন্য নয়** — মাপকাঠি null থাকে, ওজন থেকে বাদ যায়, বাকিগুলো দিয়ে গড় পুনঃনর্মালাইজ হয় (নইলে যাকে কেউ অর্ডার দেয়নি সে প্রকৃত ব্যর্থ সাপ্লায়ারের নিচে নামত)। রোলিং তিন মাসের উইন্ডো, সংরক্ষিত স্ন্যাপশট; স্কোর `vendors.rating`-এ ৫-পয়েন্ট স্কেলে auto-link — `tests/Feature/Procurement/SupplierScorecardTest`
- [x] Physical Inventory (cycle + full count) + adjustment posting — `stock_counts`/`stock_count_lines` + `ConductStockCount` + `/stock-counts` API + `/item-master/stock-counts` UI। **শিটই ফ্রিজ** (system_qty শিট তৈরির সময় জমে, পোস্টে আর পড়া হয় না), **অ্যাডজাস্টমেন্ট পার্থক্য বসায়** (জোর করে ব্যালেন্স বসালে ফ্রিজ-পরবর্তী মুভমেন্ট মুছে যেত), **না-গোনা তাক শূন্য নয়** (submit 409)। ব্লাইন্ড কাউন্টে system_qty API পেলোড থেকেই বাদ; "তাকে পাওয়া গেছে" লাইনের দর আসে আইটেমের অন্যত্র প্রচলিত গড় থেকে (ADJUSTMENT_IN WAC নাড়ায়, শূন্য দরে ঢোকালে বিনের গড় নেমে যেত)। Approval entity `STOCK_COUNT` (পরম variance value), পোস্টিং `ADJUSTMENT_IN/OUT` (idempotency `count:{line}`), GL ইভেন্ট `STOCK_ADJUSTMENT`, দৈনিক `itemmaster:chase-counts`। ROLL/SERIAL শিটে নেই — ওগুলো রেজিস্টার চেক, পরিমাণ গোনা নয় — `tests/Feature/ItemMaster/StockCountTest`
- [~] সব রিপোর্ট generate ও validate — **২৭টি হয়েছে**: ইনভেন্টরির নয়টি ([§14.1](02_Inventory_Module.md#141-যেভাবে-তৈরি-হয়েছে-phase-6)): valuation, reserved vs available, ageing, slow/non-moving, lot expiry, roll ledger, shade-wise stock, inspection register, split/merge history — সবই বিদ্যমান `ReportRegistry`-তে, তাই এক্সপোর্ট/কিউয়িং/পারমিশন বিনা পরিবর্তনে চলে। এজিং সর্বশেষ আগমন থেকে গোনে (লেজারে লেয়ার নেই); স্লো-মুভিং চাহিদা মাপে, রাইট-অফকে "নড়াচড়া" ধরে না। Procurement §17-এর দশটি ([§17](03_Procurement_Module.md#17-key-reports)) ও Floor Requisition §10-এর পাঁচটি ([§10](04_Floor_Store_Requisition_Module.md#10-reports)) — নতুন `procurement` ও `requisition` গ্রুপে। Shared Systems §3.5-এর তিনটিও নতুন `compliance` গ্রুপে ([§3.6](05_Shared_Systems_and_Compliance.md#36-যেভাবে-তৈরি-হয়েছে-phase-6))। Pending রিপোর্টগুলো লাইন ধরে, হেডার ধরে নয়; borrowed ও overdue একটাই তালিকা (সীমা সেটিং থেকে); consumption-এ ফেরত আসা ধার বাদ যায়। **বাকি দুটি ইচ্ছাকৃতভাবে নয়:** Material Requirement vs Ordered vs Received আর BOM-vs-Actual — দুটোই প্রোডাকশন ডোমেইনের, যা স্কোপের বাইরে

## Phase 7 — Fabric Inspection (Four-Point System)

> **কেন আলাদা ফেজ:** Phase 2-এ ইন্সপেকশন বসানো হয়েছিল রোলপ্রতি একটা গ্রেড আর ইন্সপেক্টরের হাতে টাইপ করা একটা "points" সংখ্যা দিয়ে, আর §4.3-এ সেটাকে জানা গ্যাপ হিসেবেই লেখা ছিল। ব্যবহারকারী তাঁর নিজের দুটো ফর্ম (**OCS Fabric Inspection Report** ও **Visual Fabric Inspection Report**, Form JMCL-07) দেখানোর পর স্পষ্ট হলো ফারাকটা ছোট নয় — মিলের প্রক্রিয়ায় রায়ের একক **লট**, স্কোর **ফর্মুলা থেকে আসে**, আর নমুনা **ফেল করলে বাড়ে**। তাই আলাদা ফেজ।

- [x] ৪-পয়েন্ট auto-scoring — `FourPointScore`; `মোট পয়েন্ট × ৩৬ × ১০০ ÷ (ইন্সপেক্টেড গজ × প্রস্থ)`। ব্যবহারকারীর শিটের চারটি রোলেই হুবহু মেলে (16.45 / 21.11 / 22.30 / 19.34), আর টেস্ট ওই সংখ্যাগুলোই ধরে রাখে — ফর্মুলা "দেখতে ঠিক" নয়, ইন্সপেক্টর যা লিখেছেন তাই
- [x] লট-লেভেল ইন্সপেকশন হেডার — নতুন `fabric_inspections`; লটে রোল/গজ, ইন্সপেক্টেড রোল/গজ, মোট পয়েন্ট, pooled শতাংশ, PASS/FAIL। **হিসাব pooled, রোল-শতাংশের গড় নয়** (১৫৩ পয়েন্ট / ৪৬৬ গজ = 19.69, গড় হতো 19.79) — নইলে ছোট বাজে রোল বড় ভালো রোলের সমান ওজন পেত
- [x] প্রতি ডিফেক্টের পয়েন্ট-ক্লাস ট্যালি — `fabric_roll_defects`-এ `point_class` (১–৪) ও `occurrences`; ত্রুটির পয়েন্ট তার দৈর্ঘ্য দিয়ে, ধরন দিয়ে নয়, তাই একই ডিফেক্ট এক রোলে কয়েকটা ক্লাসে বসতে পারে — Visual ফর্মের ট্যালি ঘরের মতো
- [x] নমুনা এসকালেশন ১০% → ১৫% → ১০০%/ফেরত — এসকালেট মানে **নতুন শিট**, `supersedes_id` দিয়ে আগেরটার দিকে দেখায়; ফেল করা শিট কখনো এডিট করে পাস বানানো হয় না, ওটাই ক্লেইমের প্রমাণ। ১০০%-এর পর এসকালেশন 409 — এরপর সিদ্ধান্ত বাণিজ্যিক
- [x] প্রস্থ তিন জায়গায় + Marked vs Actual দৈর্ঘ্য — স্কোরে প্রস্থের গড় যায়; দুটো দৈর্ঘ্যের একটা না থাকলে ঘাটতি **null**, শূন্য নয় (না-মাপা রোল "ঠিক আছে" দেখাত)
- [x] মাল্টি-লাইট শেড গ্রিড — D-65 / TL-83 / A × (অনুমোদিত ফ্যাব্রিক, end-to-end, side-to-center); পুরনো `shade_match` boolean সব ঘর পরিষ্কার হলেই true
- [x] Woven ডিফেক্ট ক্যাটালগ ১০ → ২০ — ব্যবহারকারীর শিটে ছাপা ঘরগুলোর ধরনে (Knot, Naps, Thick Yarn, Foreign Fibre, Colour Out, Wavy Fault, Stop Mark…)
- [x] সই ও পারমিশন — `inspected_by` → `verified_by` → `approved_by`; লট গ্রহণ/প্রত্যাখ্যান নতুন `itemmaster.inspection.approve`-এ (স্টোর ম্যানেজার), রোল তোলা স্টোরকিপারের। **কাগজের পাঁচ সই → তিন ধাপ, সচেতন সরলীকরণ**
- [x] গ্রেড স্কোর থেকে, আর বসে অনুমোদনের সময় — পাস করা লটের খারাপ রোলও ব্যবহারযোগ্য কাপড়; ডেলিভারি নেওয়ার সিদ্ধান্ত যে সই করে তার, হিসাবের নয়
- [x] Inspection Register রিপোর্টে নতুন তথ্য — `fabric_inspection_register`-এ শিট নম্বর ও `penalty_percent` যোগ; কাঁচা পয়েন্ট গজের হিসাব ছাড়া কিছু বলে না, আর সিদ্ধান্তটা শতাংশের ওপরেই হয়
- [ ] Photo আপলোড — Phase 2 থেকেই খোলা গ্যাপ, এখনো শুধু লিখিত Remarks
- [ ] গ্রহণযোগ্য সীমা নিশ্চিত করা — `inspection.four_point_acceptance` ডিফল্ট ২৮ ধরা হয়েছে; ব্যবহারকারীর শিটে ২২.৩০% রোল PASS বলে সীমা অন্তত তার বেশি, কিন্তু প্রকৃত সংখ্যা বায়ারভেদে বদলায়

## Cross-Cutting (প্রতিটি ফেজে)
- [x] Negative Stock ব্লক — দুটো লেজারেই (`ItemLedger::assertSufficientStock`, `InventoryLedger`) গার্ড, একই সেটিং `inventory.allow_negative_stock` (ফ্যাক্টরি-স্কোপড) দিয়ে ইচ্ছাকৃত ছাড়; না থাকলে 409 আর ব্যালান্স অপরিবর্তিত। ISSUE মাপা হয় **অসংরক্ষিত স্টকের** বিপরীতে (on_hand − reserved), অন্য আউটবাউন্ড on_hand-এর — রিজার্ভেশন থাকলেও ভৌতিক মাল সরানো যায়। **লেজার বাইপাস করে কোথাও ট্রানজাক্শন লেখা হয় না** (`ItemStockTransaction::create` কেবল `ItemLedger`-এ) — `tests/Feature/ItemMaster/ItemLedgerTest`, `tests/Feature/Inventory/InventoryLedgerTest`
- [x] সব Critical Transaction-এ Audit Log — `AuditServiceProvider::AUDITED` তালিকায় এই প্রজেক্টের পনেরোটি মডেল যোগ করা হয়েছে: টাকা (PurchaseReturn, SupplierClaim, SupplierAdvance, RateContract, LandedCostVoucher, FabricBooking, SubcontractChallan), বই (GlAccount, GlEventMapping), কাস্টমস (BondLicence, UtilisationDeclaration, DutyAssessment, HsCode), স্টক সিদ্ধান্ত (StockCount) আর ব্যাংক (LetterOfCredit)। **লেজারের সারি ইচ্ছাকৃতভাবে বাইরে** (`ItemStockTransaction`, `GlJournalLine`, `ItemReservation`) — ওগুলো নিজেই append-only রেকর্ড, দুবার লিখলে মানুষের নেওয়া সিদ্ধান্তগুলো সিস্টেমের নিজের লেখা সারির নিচে চাপা পড়ত
- [x] RBAC অনুযায়ী unauthorized action ব্লক — নতুন `tests/Feature/Identity/ApiGateCoverageTest` রাউটিং টেবিল পড়ে প্রতিটি `api/v1` অ্যাকশন থেকে গেটে পৌঁছায় কিনা যাচাই করে (কন্ট্রোলারের নিজস্ব হেলপার পর্যন্ত অনুসরণ করে, FormRequest-এর `authorize()`-ও দেখে)। **পরীক্ষা করে অনুরোধ পাঠানো হয় না** — প্রতিটি টেস্ট অনুমতিপ্রাপ্ত লোক হয়ে ডাকে বলে ভুলে যাওয়া গেট কখনো ধরা পড়ত না, ভাঙন হয়েই জানা যেত। ইনভেন্টরি/প্রকিউরমেন্ট/কমপ্লায়েন্সের প্রতিটি এন্ডপয়েন্ট `allow()` ডাকে। ছাড় পাওয়া তালিকাগুলো নিজেই যাচাই হয়: প্রতিটি প্রিফিক্স এখনো কোনো রাউটে মিলতে হবে, আর যে দুটি অ্যাকশনের গেট collaborator-এ, সেই collaborator-এর চেকটিও উপস্থিত কিনা পরীক্ষা করা হয় — সাথে পুরনো `RoleMatrixTest` (সাতটি নিয়ম) ও `AuthorizationTest`
- [x] Notification সঠিক role-এর কাছে যাচ্ছে কিনা — নতুন `tests/Feature/Notification/NotificationRoutingTest`। `MaintenanceNotifier::holdersOf()` অজানা রোল পেলে **খালি তালিকা ফেরত দেয়, কিছু থ্রো করে না** — যা স্টাফ না থাকা কোম্পানির জন্য সঠিক, আর রোলের নাম বদলালে/ভুল লিখলে নীরব গর্ত। তাই টেস্ট সোর্স পড়ে প্রতিটি `holdersOf('...')` রোল RoleSeeder-এ আছে কিনা, প্রতিটি `eventType:` `Notification::EVENT_TYPES`-এ আছে কিনা, আর প্রতিটি টেনান্ট ইভেন্টের title/body en ও bn দুই ভাষায় আছে কিনা মিলিয়ে দেখে — সোর্স থেকে, কল করে নয়, কারণ বছরে একবার চলা সুইপেই এসব লুকায়

---

# END-TO-END WORKFLOW (Unified)

```
                         ORDER / STYLE CONFIRMED
                                   │
                                   ▼
                          FABRIC BOOKING (Draft)  ── Procurement
                                   │  Confirm
                                   ▼
                 Auto Soft Reservation (Inventory) created
                                   │
                                   ▼
                    MATERIAL REQUIREMENT CALCULATION ── Procurement
        (BOM Required − Stock Available − Booked − Ordered)
                                   │
                    ┌──────────────┴───────────────┐
                    ▼                               ▼
          Net Requirement = 0                Net Requirement > 0
                                                      │
                                                      ▼
                                     AUTO / MANUAL PURCHASE REQUISITION
                                          (অথবা Floor Requisition Shortage
                                           থেকেও আসতে পারে)
                                                      │
                                              Approval Engine
                                                      │
                                    ┌─────────────────┴─────────────────┐
                                    ▼                                   ▼
                            purchase_type = Local              purchase_type = Import
                                    │                                   │
                                    ▼                                   ▼
                        RFQ (optional) → PO                   RFQ → Quotation → PO
                                    │                                   │
                                    ▼                                   ▼
                        Direct Gate Pass + GRN            PI → LC Opening → Shipment
                                    │                                   │
                                    │                                   ▼
                                    │                      Gate Pass + Partial Receiving (GRN)
                                    └─────────────┬─────────────────────┘
                                                  ▼
                                     GRN Complete → "Under Inspection"
                                                  │
                                                  ▼
                                Roll Entry (Fabric) / Lot Entry (Yarn/Chemical)
                                                  │
                                                  ▼
                              INSPECTION (Inventory Module ownership)
                                                  │
                         ┌────────────────────────┴────────────────────────┐
                         ▼                                                 ▼
                    Accepted (A/B Grade)                          Rejected / Damaged
                         │                                                 │
                         ▼                                                 ▼
             Roll/Lot Status → Available                    Purchase Return / Claim
                         │
                         ▼
                LANDED COST POST (Finance)
                         │
                         ▼
          Roll Unit Cost Recalculated (cascade to Split/Merged)
                         │
                         ▼
              Reservation upgraded: Soft → Hard
                         │
        ┌────────────────┴─────────────────┐
        ▼                                   ▼
  ROLL SPLIT/MERGE                   (skip if not needed)
        │
        ▼
  ISSUE TO PRODUCTION (Cutting Program)
        │
        ▼
  BOM vs ACTUAL VARIANCE CALCULATED
        │
   ┌────┴─────┐
   ▼          ▼
Within     Exceeds Tolerance → Approval Engine
Tolerance
   │          │
   └────┬─────┘
        ▼
  GL Posting → Reports → Accounts Payable → Advance Adjustment → Final Settlement
```

### সমান্তরাল সাব-ওয়ার্কফ্লো ১: Floor/Store Requisition
```
User Requisition (Department dropdown → Item সিলেক্ট) → Submit
        ↓
Store Keeper Stock চেক → Full/Partial/No Issue
        ↓
Shortage থাকলে → Auto Purchase Requisition (Procurement flow-তে ঢুকবে, উপরের ডায়াগ্রাম দ্রষ্টব্য)
        ↓
Stock এলে Notification → Store Fulfill করবে → Closed
```

### সমান্তরাল সাব-ওয়ার্কফ্লো ২: Subcontract (Dyeing/Printing)
```
Available Roll → Subcontract Challan Out → Status "Sent for Subcontract"
        ↓
Subcontractor Processing
        ↓
Return In (full/partial) → Quality Check → Status back to Available
        ↓
Wastage/Shrinkage recorded → Wastage Entry report
```

---

## Sign-off Note

এই মাল্টি-ফাইল ডকুমেন্টেশন সেট Inventory, Procurement, Floor/Store Requisition এবং তাদের কমন Master Data ও Shared Systems-কে আলাদা ফাইলে ভাগ করে unified Implementation guideline হিসেবে তৈরি করা হয়েছে। ডেভেলপমেন্ট শুরুর আগে Finance টিমের সাথে GL mapping ([Shared Systems](05_Shared_Systems_and_Compliance.md)) এবং Customs/Compliance টিমের সাথে Bond/UD-UP ([Shared Systems](05_Shared_Systems_and_Compliance.md)) অংশ চূড়ান্ত validate করে নেওয়া জরুরি।

**Document End**
