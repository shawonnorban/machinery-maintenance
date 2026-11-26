# Inventory

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/03`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Opening Stock | `/m/general-store/gs-opening` | 1 | 13 | 1 |
| 2 | Stock Receive | `/m/general-store/gs-receive` | 1 | 15 | 1 |
| 3 | Stock Issue | `/m/general-store/gs-issue` | 1 | 13 | 1 |
| 4 | Stock Return | `/m/general-store/gs-return` | 1 | 14 | 1 |
| 5 | Stock Adjustment | `/m/general-store/gs-adjustment` | 1 | 13 | 1 |
| 6 | Stock Transfer | `/m/general-store/gs-transfer` | 1 | 13 | 1 |
| 7 | Current Stock | `/m/general-store/gs-current-stock` | 1 | 3 | 0 |
| 8 | Stock Ledger | `/m/general-store/gs-ledger` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Opening Stock

`/m/general-store/gs-opening`

**ভিউ**
> 08 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Price (৳) | number |  |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, STORE, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Opening Stock, Opening Stock List (0)
- বাটন/অ্যাকশন: All categories, Select…, Add Item, Clear, Save, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Price (৳) | number |  |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Stock Receive

`/m/general-store/gs-receive`

**ভিউ**
> 09 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Supplier | select |  | No supplier |
| Challan No. | text |  |  |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Price (৳) | number |  |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, STORE, SUPPLIER, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Stock Receive, Stock Receive List (0)
- বাটন/অ্যাকশন: All categories, Select…, Add Item, Clear, Save, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Supplier | select |  | No supplier |
| Challan No. | text |  |  |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Price (৳) | number |  |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Stock Issue

`/m/general-store/gs-issue`

**ভিউ**
> 10 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Requisition No. | select |  | Direct issue (no requisition) |
| Employee / Receiver | text |  |  |
| Remarks | textarea |  |  |
| Issue Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, STORE, DEPARTMENT, REQUISITION, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Stock Issue, Stock Issue List (0)
- বাটন/অ্যাকশন: Select…, All categories, Add Item, Clear, Save, All departments, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Requisition No. | select |  | Direct issue (no requisition) |
| Employee / Receiver | text |  |  |
| Remarks | textarea |  |  |
| Issue Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Stock Return

`/m/general-store/gs-return`

**ভিউ**
> 11 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Original Issue No. | select |  | Without an issue reference |
| Returned By | text |  |  |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Return Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, STORE, DEPARTMENT, AGAINST ISSUE, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Stock Return, Stock Return List (0)
- বাটন/অ্যাকশন: —, All categories, Select…, Add Item, Clear, Save, All departments, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Original Issue No. | select |  | Without an issue reference |
| Returned By | text |  |  |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Return Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Stock Adjustment

`/m/general-store/gs-adjustment`

**ভিউ**
> 12 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Adjustment Type* | select | ✔ | Select…, Increase, Decrease, Damage, Lost, Physical Count Correction, Other |
| Reference No. | text |  |  |
| Reason* | textarea | ✔ |  |
| Adjustment Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, STORE, TYPE, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Stock Adjustment, Stock Adjustment List (0)
- বাটন/অ্যাকশন: All categories, Select…, Add Item, Clear, Save, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Adjustment Type* | select | ✔ | Select…, Increase, Decrease, Damage, Lost, Physical Count Correction, Other |
| Reference No. | text |  |  |
| Reason* | textarea | ✔ |  |
| Adjustment Qty* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Stock Transfer

`/m/general-store/gs-transfer`

**ভিউ**
> 13 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| From Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| To Store* | select | ✔ | Select…, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  | Item name, code or doc no. |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |
- গ্রিড কলাম: DOC NO., DATE, FROM STORE, TO STORE, ITEMS, TOTAL QTY, VALUE (৳), STATUS, USER
- সেকশন: New Stock Transfer, Stock Transfer List (0)
- বাটন/অ্যাকশন: All categories, Select…, Add Item, Clear, Save, Search, Reset, Excel

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| From Store* | select | ✔ | Select…, Main Store, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| To Store* | select | ✔ | Select…, Electrical Store, Maintenance Store, Packaging Store, Safety Store |
| Reference No. | text |  |  |
| Remarks | textarea |  |  |
| Quantity* | number | ✔ |  |
| Line Remarks | text |  |  |
| Search | text |  |  |
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
| Status | select |  | Any status, Posted, Cancelled |

#### Current Stock

`/m/general-store/gs-current-stock`

**ভিউ**
> 14 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Item name, code or doc no. |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Stock Status | select |  | Any status, In Stock, Low, Out of Stock |
- গ্রিড কলাম: ITEM CODE, ITEM, CATEGORY, UNIT, QTY, PRICE (৳), STOCK VALUE (৳), MIN., BY STORE, RACK, STATUS
- সেকশন: Current Stock (0)
- বাটন/অ্যাকশন: All categories, Search, Reset, Excel, Print / PDF

#### Stock Ledger

`/m/general-store/gs-ledger`

**ভিউ**
> 15 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Store | select |  | All stores, Electrical Store (ELEC), Main Store (MAIN), Maintenance Store (MAINT), Packaging Store (PACK), Safety Store  |
| Item | select |  | All items |
- বাটন/অ্যাকশন: Search, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 10/01-এর তালিকা (`gs_items`, `gs_stores`, `gs_departments`, `gs_suppliers`, `gs_categories`, `gs_units`) আর `gs_stock_movements`। Item master-এর Opening Stock-ও এই টেবিলে movement হিসেবে লেখা হয়।
- Excel-এর জন্য OpenSpout, আর print-এর জন্য popup window-এর ধাঁচ (Merchandise-এ যেভাবে আছে)।
- `ApiController::allow()`, `ApiResponse`, advisory lock দিয়ে নম্বর (Material Procurement-এর `Procurement::nextNumber`-এর মতো, তবে ৬ অঙ্ক)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_23_000100_create_gs_documents`, মডিউল `app/Modules/GeneralStore`:

| টেবিল | কী রাখে |
|---|---|
| `gs_documents` | doc_type (OPENING / RECEIVE / ISSUE / RETURN / ADJUST / TRANSFER), doc_no (OP- / RC- / IS- / RT- / AD- / TR- + YYYY-NNNNNN), doc_date, gs_store_id (Transfer-এ From Store), to_store_id, gs_supplier_id, challan_no, reference_no, gs_department_id, person (Issue-এর Employee / Receiver, Return-এর Returned By), against_id (Return-এর মূল Issue), requisition_id (10/05-এর জন্য রাখা), adjustment_type, remarks (Adjustment-এ Reason), status POSTED / CANCELLED, created_by, cancelled_by, cancelled_at, cancel_reason |
| `gs_document_lines` | gs_item_id, quantity (যা লেখা হয়েছে; Count-এ গোনা পরিমাণ), movement_qty (স্টকে যা গেল, চিহ্নসহ), unit_price, suggested_price (Issue-এ শেষ ক্রয়মূল্য), line_remarks, sort_order |
| `gs_stock_movements` + | নতুন ঘর `gs_document_id` আর `is_reversal`। Transfer-এ দুটো movement: From Store-এ −, To Store-এ + |
| `gs_audit_trail` | user_id, action (CREATE / UPDATE / PRICE_CHANGE / REPORT_EXPORT), screen (gs-receive …), reference, summary, created_at (মাইক্রোসেকেন্ড)। শুধু যোগ করা যায়; Administration → Audit Log (10/07) এটা পড়বে |

## ৫. Workflow / Status ও Business Rules

- **সব ডকুমেন্টে:**
  - Save করলেই POSTED, সাথে সাথে স্টকে movement পড়ে। খসড়া নেই।
  - একটাও আইটেম না দিলে: "Pick at least one item."
  - পরিমাণ না দিলে বা ০ হলে: "Line 1: Quantity must be a number greater than zero."
  - একই কোম্পানিতে একসাথে একটাই posting চলে (advisory lock), তাই স্টক পড়া আর লেখা মেলে।
  - Save-এর পরে ওপরে সবুজ banner ("RC-2026-000001 posted. Stock is updated.") আর Print Voucher / Dismiss, সাথে toast ("Stock Receive RC-… posted. Stock is updated."), ফর্ম খালি হয়, তালিকা আর আইটেমের স্টক নতুন করে আসে।
  - Audit-এ CREATE: "Stock Receive RC-2026-000001: 2 item(s), 55 qty, ৳4200" (Brickland-এর মতোই ইংরেজিতে)।
- **লাইনের তথ্য:** আইটেম বাছলে নিচে Code, Unit, Current Stock (এই স্টোরে; থাকলে সবুজ, না থাকলে লাল), All stores, Price আর Value দেখায়; Quantity-র ঘরে একক লেখা থাকে। Category দিয়ে Item-এর তালিকা ছোট হয়।
- **দাম:** শেষ ক্রয়মূল্য = চালু Receive বা Opening-এর শেষ দামওয়ালা লাইন, না থাকলে আইটেমের নিজের দাম।
  - Opening আর Receive-এ Price ঐচ্ছিক, আইটেম বাছলে শেষ দাম বসে।
  - Issue-এ Unit Price বাধ্যতামূলক ("Line 1: Unit Price is required."), শেষ দাম বসে, নিচে "Last purchase price"। বদলালে audit-এ PRICE_CHANGE: "Stock Issue IS-…: A4 Paper unit price changed from ৳420 to ৳400"।
  - Return-এ মূল Issue-এর দাম; Adjustment আর Transfer-এ শেষ দাম (শুধু মূল্য দেখানোর জন্য)।
- **Opening Stock:** Date, Store, Reference No., Remarks।
- **Stock Receive:** Supplier (না দিলে "No supplier"), Challan No., Reference No.।
- **Stock Issue:**
  - Department বাধ্যতামূলক ("Department is required."), Employee / Receiver লেখা।
  - লাইনে Available Stock, Issue Qty, Unit Price, Total Value।
  - স্টকের বেশি হলে ঘরের নিচে লাল "Only 50 available", server-এ "Line 1: only 50 REAM of A4 Paper 80 GSM is available."
  - Requisition No.-এ "Direct issue (no requisition)" আর অনুমোদিত requisition-এর তালিকা; requisition বাছলে তার নিয়ম ([Requisition](05_Requisition.md))। Settings-এ সরাসরি Issue বন্ধ থাকলে শুধু requisition।
  - মোট: Total Items, Total Issue Qty, Total Issue Value।
- **Stock Return:**
  - Original Issue No. বাছলে ("IS-2026-000001 — Maintenance (24 Oct 2026)") Store আর Department সেই Issue থেকে বসে বন্ধ হয়ে যায়, আর Issue-এর যে আইটেম এখনো ফেরতযোগ্য সেগুলো লাইনে আসে, নিচে "Up to n"।
  - বেশি দিলে: "Line 1: only 4 REAM of A4 Paper can come back against IS-…"; Issue-এ না থাকা আইটেম: "Line 1: Stapler Pin is not on IS-…"।
  - "Without an issue reference"-এ Department ঐচ্ছিক, আইটেম যেকোনো।
- **Stock Adjustment:**
  - Adjustment Type আর Reason বাধ্যতামূলক।
  - Increase বাড়ায়; Decrease, Damage আর Lost কমায়।
  - Physical Count Correction-এ ঘরের নাম হয় "Physical Qty", নিচে "Difference −2"; স্টকে যায় পার্থক্যটুকু। গোনা পরিমাণ স্টকের সমান হলে: "Line 1: the physical count matches the stock — nothing to adjust."
  - Other-এ পরিমাণ চিহ্নসহ (− দিলে কমে), ০ চলে না।
  - কমানো স্টকের বেশি হলে: "Not enough stock: Safety Gloves in Main Store has 20 available, 25 needed." (Brickland-এর লেখা)।
- **Stock Transfer:** From Store, To Store (তালিকায় From Store থাকে না; একই হলে "To Store must be different from From Store."), Reference, Remarks। From Store-এর স্টকের বেশি নয়।
- **তালিকা:** Search (আইটেমের নাম, কোড বা ডক নং), From, To, Store, Item, (Receive-এ Supplier, Issue / Return-এ Department), Status; Excel। কলাম Brickland-এর ক্রমে। প্রতিটা সারিতে View (চোখ) আর Print।
- **View:** ধরন আর অবস্থার ব্যাজ, তথ্য (Created-এ কে আর কখন; বাতিল হলে Cancelled আর Cancel Reason), আইটেমের টেবিল, "Total Qty 45 · Total Value ৳900"; বাটন Cancel / Reverse, Print, Close।
- **Cancel / Reverse:**
  - Dialog: "Cancel TR-…? Its stock movement is reversed and it stays on record as Cancelled. This cannot be undone." Reason ছাড়া বাটন বন্ধ।
  - movement উল্টো চিহ্নে আবার লেখা হয় (`is_reversal`), ডকুমেন্ট থাকে CANCELLED হিসেবে। Audit-এ UPDATE: "Stock Issue IS-… cancelled and reversed: কারণ"।
  - যা ঢুকেছিল তা এখন স্টোরে না থাকলে আটকায়: "Cannot cancel: only 7 REAM of A4 Paper is left in Main Store."
  - যে Issue-এর বিপরীতে ফেরত আছে, আগে সেগুলো বাতিল করতে হয়।
  - আবার বাতিল করলে: "… is already cancelled."
- **Current Stock:**
  - Filter: Search, Store, Category, Stock Status।
  - ছয়টা tile: Items, Total Qty, Stock Value, Priced Items (n / মোট), Low Stock, Out of Stock।
  - কলাম: Item Code, Item (ক্লিক করলে তার Ledger, `?item=ITM-001`), Category, Unit, Qty, Price, Stock Value, Min., By Store ("Main Store: 12, Safety Store: 8"), Rack, Status।
  - অবস্থা: ০ বা কম হলে Out of Stock, ন্যূনতমের সমান বা কম হলে Low, না হলে In Stock।
  - Excel আর Print / PDF।
- **Stock Ledger:**
  - Filter: From, To, Store, Item। আইটেম না বাছলে Brickland-এর বার্তা।
  - Tile: Item, Opening, Total In, Total Out, Closing Balance।
  - কলাম: Date, Type, Doc No., Store, Party (Receive-এ সাপ্লায়ার, Issue / Return-এ ডিপার্টমেন্ট, Transfer-এ অন্য স্টোর), Receive, Issue, Return, Adjustment, Transfer, Balance, User। শুরুতে Opening Balance সারি।
  - বাতিলের সারি কেটে দেখায় ("Receive (reversal)"), পরিমাণ উল্টো চিহ্নে।
  - Excel আর Print / PDF।

## ৬. API ও পারমিশন

| Method | Path |
|---|---|
| GET | `/general-store/{kind}/options`, `/{kind}/documents`, `/{kind}/documents/excel` (kind = opening, receive, issue, return, adjustment, transfer) |
| POST | `/general-store/{kind}/documents` |
| GET / POST | `/general-store/documents/{no}`, `/documents/{no}/returnable`, `/documents/{no}/cancel` |
| GET | `/general-store/current-stock`, `/current-stock/excel`, `/ledger?item=`, `/ledger/excel`, `/dashboard` |

- **পারমিশন** (নতুন): `store.inventory.view`, `store.inventory.post`, `store.inventory.cancel`।
  - FACTORY_ADMIN আর STORE_MANAGER: তিনটাই।
  - STOREKEEPER: view আর post।
  - FACTORY_MANAGER আর MAINTENANCE_MANAGER: শুধু view।
- Excel ডাউনলোডে audit-এ REPORT_EXPORT লেখা হয়।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: General Store → GENERAL STORE (Dashboard), MASTER DATA, INVENTORY (Opening Stock, Stock Receive, Stock Issue, Stock Return, Stock Adjustment, Stock Transfer, Current Stock, Stock Ledger)।
- রুট `/general-store/gs-opening` … `/gs-ledger`। ছয়টা ডকুমেন্ট-স্ক্রিন একটাই component (`StoreDocumentScreen`) দিয়ে, প্রতিটার নিজের হেডার, লাইনের ঘর, তালিকার কলাম আর filter।
- কোনো কাজে পাতা রিলোড হয় না; Current Stock থেকে Ledger-এ যাওয়া আলাদা স্ক্রিন, তাই সেটা লিংক।
- Excel যায় route handler `/api/general-store/excel/[kind]` দিয়ে। Print খোলে popup window, যেখান থেকে browser-এর Save as PDF।
- টাকার অঙ্ক Brickland-এর মতো lakh ভাগে (৳2,10,000)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Database/Migrations/2026_12_23_000100_create_gs_documents.php`
- `app/Modules/GeneralStore/Models/GsDocument.php`
- `app/Modules/GeneralStore/Models/GsDocumentLine.php`
- `app/Modules/GeneralStore/Models/GsAuditEntry.php`
- `app/Modules/GeneralStore/Models/GsStockMovement.php` (gs_document_id, is_reversal)
- `app/Modules/GeneralStore/Support/GsBook.php`
- `app/Modules/GeneralStore/Http/Controllers/Api/GsDocumentApiController.php`
- `app/Modules/GeneralStore/Http/Controllers/Api/GsStockApiController.php`
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (GENERAL_STORE.HOME, GENERAL_STORE.INVENTORY আর ৯টা স্ক্রিন)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php` (`store.inventory.*`)
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/general-store/[screen]/page.js`
- `frontend/src/app/(app)/general-store/[screen]/screens.js`
- `frontend/src/app/(app)/general-store/[screen]/actions.js` (`gsAction`)
- `frontend/src/app/api/general-store/excel/[kind]/route.js`
- `frontend/src/components/generalstore/common.jsx`
- `frontend/src/components/generalstore/document.jsx`
- `frontend/src/components/generalstore/stock.jsx`
- `lang/en/store.php`, `lang/bn/store.php` (`inv.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreInventoryTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_ ধাপ ২-এ (২০২৬-১০-২৪) requisition-এর বিপরীতে Issue, Settings-এর Issue-নিয়ম আর ভাউচারের নাম যোগ হয়েছে।

## ৯. টেস্ট ও যাচাই

- **`GeneralStoreInventoryTest`:** ২টা টেস্ট, ৯৯টা assertion।
  - Receive: ফাঁকা লাইন, পরিমাণ ছাড়া লাইন, দুই লাইনের Receive (মোট 55, মূল্য 4200), audit-এর লেখা।
  - Issue: শেষ দাম 420 আর স্টোরের স্টক options-এ; Department আর Unit Price বাধ্যতামূলক; স্টকের বেশি আটকায়; দাম 400 করলে PRICE_CHANGE।
  - Return: ফেরতযোগ্য 4; বেশি আর Issue-এ না থাকা আইটেম আটকায়; Store, Department আর দাম Issue থেকে।
  - Cancel: ফেরত থাকা Issue আটকায়, কারণ বাধ্যতামূলক, দ্বিতীয়বার আটকায়, স্টক ফিরে আসে; স্টক চলে যাওয়ার পরে Receive বাতিল আটকায়।
  - তালিকার filter, Excel, আর Ledger-এর ছয়টা সারি ও tile।
  - Opening; Adjustment-এর ধরন আর কারণ, Decrease-এর সীমা, Count (−2), একই গোনায় আটকায়, Increase, Other (−3)।
  - Transfer: একই স্টোর আর বেশি পরিমাণ আটকায়, দুই দিকের movement।
  - Current Stock (By Store, অবস্থা, tile, store আর status filter, Excel), Dashboard, অনুমতি (STOREKEEPER বাতিল করতে পারে না; FACTORY_MANAGER শুধু দেখে), audit বদলানো যায় না।
- **ব্রাউজার যাচাই** (owner, dev DB-তে রাখা):
  - **RC-2026-000001:** A4 Paper 80 GSM 50 (দাম নিজে 520) আর Ball Pen Blue 100 @ 8, Challan CH-1001।
  - **IS-2026-000001:** Maintenance, Rahim (50001001)। প্রথমে 60 দিলে লাল বার্তা আর toast; তারপর 20 @ 440।
  - **RT-2026-000001:** IS-2026-000001-এর বিপরীতে 2; Store আর Department নিজে বসে বন্ধ।
  - **AD-2026-000001:** Ball Pen Count 98 (Difference −2)।
  - **TR-2026-000001:** Ball Pen 10, Main → Electrical; তারপর View থেকে "Wrong store picked" কারণে বাতিল।
  - **Current Stock:** ১৬টা আইটেম, মূল্য ৳21,924; নাম থেকে Ledger খোলে (50 → 30 → 32)।
  - **Excel:** তিনটা workbook (Issue তালিকা, Current Stock, Ledger) খুলে সারি মেলানো হয়েছে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **নম্বরের prefix:** Brickland-এ OP-, RC-, IS- দেখা গেছে। Return, Adjustment আর Transfer-এর কোনো ডকুমেন্ট ওদের সিস্টেমে নেই, তাই RT-, AD-, TR- ধরে নেওয়া হয়েছে।
- **Other ধরনের Adjustment:** Brickland-এ দিক বোঝা যায়নি। এখানে চিহ্নসহ পরিমাণ নেওয়া হয় (− দিলে কমে)।
- **Return-এর ফেরতযোগ্য সীমা:** "Up to 45"-এর server-বার্তা Brickland-এ দেখা হয়নি (লেখা হয়নি), এখানে নিজেদের লেখা।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs01.json` থেকে `gs07.json`।
  - gs04 আর gs05-এ লেখার অনুমতি ব্যবহার করে Brickland-এ RC-2026-000003 (STAPLAR PIN 1) নিয়ে আবার বাতিল করা হয়েছে, যাতে Save, banner, View আর Cancel / Reverse দেখা যায়; ওদের স্টকে মোট কোনো প্রভাব নেই। Decrease আর Count-এর যাচাই আটকে যাওয়ায় সেগুলোতে কিছু লেখা হয়নি।
- **ধাপ:** ব্যবহারকারীর নির্দেশে General Store তিন ধাপে। এটা ধাপ ১ (Inventory-র ৮টা আর Dashboard)।
