# Order & Delivery

> **স্ট্যাটাস:** ✅ সম্পন্ন (৮টার ৮টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/02`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sales Order Entry | `/m/sales-delivery/sales-order` | 3 | 51 | 0 |
| 2 | Sales Order Amendment | `/m/sales-delivery/so-amendment` | 2 | 16 | 0 |
| 3 | Order Confirmation | `/m/sales-delivery/order-confirmation` | 1 | 6 | 0 |
| 4 | Delivery Challan Issue | `/m/sales-delivery/delivery-chalan` | 2 | 24 | 0 |
| 5 | Delivery Challan Return | `/m/sales-delivery/delivery-challan-return` | 2 | 14 | 0 |
| 6 | Delivery Bill Verification | `/m/sales-delivery/check-bill` | 2 | 16 | 0 |
| 7 | Delivery Completion | `/m/sales-delivery/delivery-completion` | 1 | 4 | 0 |
| 8 | Sales Order Month Close | `/m/sales-delivery/so-month-closing` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sales Order Entry

`/m/sales-delivery/sales-order`

**ট্যাব: Sales Order List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | text |  | Buyer… |
| Style | text |  | Style… |
| Order | text |  | Order… |
| Section | select |  | All, Accessories — Store, Embroidery — Store, Poly — Store, Printing — Store |
| SO No | text |  | SO No… |
| SC No | text |  | SC No… |
| PO/WO No | text |  | PO/WO No… |
| SO Create From Date | date |  |  |
| SO Create To Date | date |  |  |
| SO Type | select |  | All, General, Inter Company, Embroidery, Sub Contract |
| Target Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Sales Order List 0, Sales Order Entry, S/C Programme, All, Search, Reset

**ট্যাব: Sales Order Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing sales order | select |  |  |
| Search For | text |  |  |
| Is Inter Company | checkbox |  |  |
| Is Embroidery | checkbox |  |  |
| SO Type | select |  | Select…, General, Inter Company, Embroidery, Sub Contract |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| SO No | text |  | [Auto Gen] |
| Receive Date* | date | ✔ |  |
| Program By* | text | ✔ |  |
| Program To* | text | ✔ |  |
| Delivery Target Date* | date | ✔ |  |
| Same as Bill To! Ticked, the delivery party is taken from Bill To on save. | checkbox |  |  |
| Lead Time (Days) | number |  |  |
| Delivery Address | textarea |  |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Currency* | select | ✔ | Select…, BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Conv. In Amt. | number |  |  |
| Payment Term | select |  | Select…, At Sight, Cash on Delivery, Advance, Credit 30 days, Credit 60 days, Credit 90 days |
| Mode of Shipment* | select | ✔ | Select…, By Van, By Truck, By Courier, By Air, By Sea, By Hand |
| PO/PR/WO No.* | text | ✔ | PO/PR/WO NO… |
| Others Term | textarea |  |  |
| PR/PO Ref. | textarea |  | BUYER \| STYLE \| ORDER# |
| Remarks | textarea |  |  |
| Additional Qty (%) | number |  | additional % of total sales delivery qty |
| Season | text |  | SS27, AW26… |
| Department | text |  | Mens, Ladies, Kids… |
| Incoterm | select |  | Select…, FOB, CFR, CIF, EXW, FCA, CPT, CIP, DAP, DDP |
| Destination Port | text |  |  |
| Shipment Date* | date | ✔ |  |
| Items | select |  | Select… |
| Is Poly Item Poly and cartons are ordered by weight rather than by piece. | checkbox |  |  |
- সেকশন: Sales Order Entry, Master Data, Terms, Shipment, Details Data, Details Data — item lines, GMT Size \| Product, Other Particular
- বাটন/অ্যাকশন: Sales Order List 0, Sales Order Entry, S/C Programme, Select…, Add item line, Add size, Add particular, Back To List, Clear, Save sales order

**ট্যাব: S/C Programme**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search SO List | search |  | Search SO No or customer… |
| Existing S/C programme | select |  |  |
| Sales Order* | select | ✔ | Select… |
| SC No | text |  | [Auto Gen] |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| SC Date* | date | ✔ |  |
| Target Date* | date | ✔ |  |
| Process* | text | ✔ | Embroidery, Printing, Washing… |
| Remarks | textarea |  |  |
- গ্রিড কলাম: SO No, Customer, Qty
- সেকশন: SO List, S/C Programme, Programme Lines
- বাটন/অ্যাকশন: Sales Order List 0, Sales Order Entry, S/C Programme, -- Any Customer --, Select…, Add line, Back To List, Clear, Save S/C programme

#### Sales Order Amendment

`/m/sales-delivery/so-amendment`

**ট্যাব: Amendment List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Amend No | text |  | Amend No… |
| SO No | text |  | SO No… |
| State | select |  | All, Draft, Submitted, Approved, Applied, Rejected, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Amendment List 0, Sales Order Amendment Entry, All, Search, Reset

**ট্যাব: Sales Order Amendment Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Confirmed Orders | search |  | Search SO No or customer… |
| Existing sales order amendment | select |  |  |
| Sales Order* | select | ✔ | Select… |
| Amend No | text |  | [Auto Gen] |
| Amend Date* | date | ✔ |  |
| Reason | textarea |  | Why the order is changing — buyer revision, price renegotiation… |
| Remarks | textarea |  |  |
| Current Delivery Date | text |  |  |
| Revised Delivery Date* | date | ✔ |  |
| Current Shipment Date | text |  |  |
| Revised Shipment Date | date |  |  |
- গ্রিড কলাম: SO No, Customer, Qty, Rev.
- সেকশন: Confirmed Orders, Sales Order Amendment Entry, Amendment, Dates, Size-wise Revision
- বাটন/অ্যাকশন: Amendment List 0, Sales Order Amendment Entry, -- Any Customer --, Add size, Back To List, Clear, Save sales order amendment

#### Order Confirmation

`/m/sales-delivery/order-confirmation`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | text |  | Buyer… |
| SO No | text |  | SO No… |
| PO No | text |  | PO No… |
| Approval Status | select |  | All, Draft, Submitted, Checked, Approved, Confirmed |
| SO Create From Date | date |  |  |
| SO Create To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: All, Search, Reset

#### Delivery Challan Issue

`/m/sales-delivery/delivery-chalan`

**ট্যাব: Delivery Chalan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | text |  | Buyer… |
| Style | text |  | Style… |
| Order | text |  | Order… |
| Section | select |  | All, Accessories — Store, Embroidery — Store, Poly — Store, Printing — Store |
| Bill Status | select |  | All, Waiting For Bill, Partially Billed, Billed |
| Chalan No. | text |  |  |
| SO No. | text |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Delivery Chalan List 0, Delivery Chalan Entry, All, Search, Reset

**ট্যাব: Delivery Chalan Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search SO List | search |  | Search SO No, PO/WO or customer… |
| Existing delivery chalan | select |  |  |
| Sales Order* | select | ✔ | Select… |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Chalan No | text |  | [Auto Gen] |
| Chalan Date* | date | ✔ |  |
| Delivery Target Date* | date | ✔ |  |
| Mode of Shipment* | select | ✔ | Select…, By Van, By Truck, By Courier, By Air, By Sea, By Hand |
| Currency* | select | ✔ | Select…, BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Conv. In Amt. | number |  |  |
| Carrier Name | text |  |  |
| Carrier No | text |  |  |
| Gate Pass No | text |  | [Auto Gen] |
| PR/PO Ref. | textarea |  | BUYER \| STYLE \| ORDER# |
| Remarks | textarea |  |  |
- গ্রিড কলাম: SO No, Customer, Due Qty, Target
- সেকশন: SO List, Delivery Chalan Entry, Master Data, Carrier & References, Details Data
- বাটন/অ্যাকশন: Delivery Chalan List 0, Delivery Chalan Entry, -- Select Supplier/Customer --, Select…, Add line, Back To List, Clear, Save delivery chalan

#### Delivery Challan Return

`/m/sales-delivery/delivery-challan-return`

**ট্যাব: Challan Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Return No | text |  |  |
| Challan No | text |  |  |
| SO No | text |  |  |
| State | select |  | All, Draft, Confirmed, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Challan Return List 0, Delivery Challan Return Entry, All, Search, Reset

**ট্যাব: Delivery Challan Return Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Delivered Challan List | search |  | Search chalan no, SO no or customer… |
| Existing challan return | select |  |  |
| Original Challan* | select | ✔ | Select… |
| Order / PO / Buyer | text |  | Filled from the challan |
| Return No | text |  | [Auto Gen] |
| Return Date* | date | ✔ |  |
| Reason* | select | ✔ | Select…, Quality Issue, Wrong Item, Excess Delivery, Buyer Rejection, Damaged in Transit, Other |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Chalan No, SO No, Customer, Returnable
- সেকশন: Delivered Challan List, Delivery Challan Return Entry, Return Info, Remarks, Returned Goods
- বাটন/অ্যাকশন: Challan Return List 0, Delivery Challan Return Entry, -- Select Customer --, Add line, Back To List, Clear, Save challan return

#### Delivery Bill Verification

`/m/sales-delivery/check-bill`

**ট্যাব: Check Bill List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill No. | text |  |  |
| Company | select |  | All, Brickland Composite Ltd. |
| Status | select |  | All, Draft, Waiting for Bill Approval, Bill Verified, Bill Approved, Bill Paid, Bill Rejected |
| Finalized? | select |  | All, Yes, No |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Check Bill List 0, Check Bill Entry, All, Search, Reset

**ট্যাব: Check Bill Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Receive Challan List | search |  | Search chalan no, SO no or customer… |
| Existing check bill | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Currency* | select | ✔ | Select…, BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Loc Exchange Rate* | number | ✔ |  |
| Bill No | text |  | [Auto Gen] |
| Bill Date* | date | ✔ |  |
| Deduction | number |  |  |
| Remarks | textarea |  |  |
| Action Status* | select | ✔ | Select…, 68-Save As Draft, 69-Submit For Approval |
- গ্রিড কলাম: Chalan No, SO No, Customer, Unbilled
- সেকশন: Receive Challan List, Check Bill Entry, Bill Info, Bill Details
- বাটন/অ্যাকশন: Check Bill List 0, Check Bill Entry, -- Select Customer --, Select…, Add line, Back To List, Clear, Save check bill

#### Delivery Completion

`/m/sales-delivery/delivery-completion`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| SO No | text |  | SO No… |
| Status | select |  | All, Pending, Partial, Completed |
| Target From | date |  |  |
| Target To | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: All, Search, Reset

#### Sales Order Month Close

`/m/sales-delivery/so-month-closing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Period | text |  | Jul/2026 |
| Remarks | textarea |  |  |
| Close over exceptions (administrator) Only an administrator may close a month th | checkbox |  |  |
- সেকশন: Parameters
- বাটন/অ্যাকশন: Refresh, Month Close, Show Exception, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **21/01-এর `SsSettings`:** সংশোধনের নম্বর (SOA-2026-000001) আর চালান রিটার্নের নম্বর (DCR-2026-000001) settings-এর প্রিফিক্স আর অঙ্কসংখ্যা থেকে আসে।
- **মাস্টার (Brickland-এর লেবেলের সাথে মিলিয়ে):**
  - customers = Material Procurement-এর `mp_suppliers` ("নাম (কোড)", Brickland-এ "নাম (SUP-…)")।
  - sections = Company Management-এর `sections` ("সেকশন — ডিপার্টমেন্ট", Brickland-এ "Accessories — Store")।
  - items = `mp_items`; currencies = `currencies` ("BDT — Bangladeshi Taka"); countries = `origin_countries`; stores = `stores`; একক = UOM মাস্টার (UomCodes)।
- **নতুন ছোট মাস্টার `ss_particulars`:** Brickland-এর "Sales Order Particular" (Carton Dimension, Poly Thickness, Print Placement)। আমাদের কোনো মাস্টার ছিল না, তাই প্রথম ব্যবহারে এই তিনটা নিজে থেকে তৈরি হয় (খোলা প্রশ্ন ২)।
- **শেয়ার্ড UI:** `RecordsGrid` (গ্রুপ হেডার), `ConfirmDialog`, `lib/print-document` (View / Print), `FlowSteps`। FlowSteps-এ এখন যে ধাপ এখনো বানানো হয়নি সেটা লিংক ছাড়া দেখায়।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_21_000100_create_ss_order_delivery.php` (১৬টা টেবিল):

- **`ss_sales_orders`** (SO-YYYYMMDD-####):
  - মাস্টার ডাটা: search_for, is_inter_company, is_embroidery, so_type, customer / bill_to / delivery_to, same_as_bill_to, receive_date, program_by / to, delivery_target_date, lead_time_days, delivery_address।
  - শর্ত: payment_method, currency_id, conv_rate, payment_term, mode_of_shipment, po_wo_no, others_term, pr_po_ref, remarks, additional_qty_pct।
  - শিপমেন্ট: season, department, incoterm, destination_country_id, destination_port, shipment_date, item_id, is_poly_item।
  - অবস্থা: approval_status (DRAFT / SUBMITTED / CHECKED / APPROVED / CONFIRMED), state (OPEN / CLOSED), version, প্রতিটা ধাপের সময় আর কে।
- **লাইন:**
  - `ss_sales_order_lines`: sl_no, buyer, order_ref, style_ref, wo_ref, section, item, রং, rate, uom, specification, code, ওজন।
  - `ss_sales_order_sizes`: line, size, pcs_per_size, qty, rate (লাইনের রেট থেকে; সংশোধনে সাইজ অনুযায়ী বদলাতে পারে)।
  - `ss_sales_order_particulars`: line, particular, value।
- **`ss_sc_programmes`** (SC-YYYYMMDD-####) + লাইন (size, qty, rate)।
- **`ss_amendments`** (SOA-…) + লাইন (size, prev / revised qty ও rate): আগের ও সংশোধিত তারিখ, version, state (DRAFT / SUBMITTED / APPROVED / APPLIED / REJECTED / CANCELLED), snapshot (কার্যকর করার সময় অর্ডার যেমন ছিল)।
- **`ss_challans`** (DC-YYYYMMDD-####, গেট পাস GP-একই নম্বর) + লাইন (size, qty, rate, remarks): store, বাহক, state (DRAFT / SUBMITTED "At the Gate" / DELIVERED / CANCELLED), is_revised।
- **`ss_challan_returns`** (DCR-…) + লাইন (challan line, qty): reason (৬টা), state (DRAFT / CONFIRMED / CANCELLED)।
- **`ss_bills`** (CB-YYYYMMDD-####) + লাইন (challan line, qty, rate, exchange_rate): currency, loc_exchange_rate, deduction, remarks, state (DRAFT / WAITING_FOR_APPROVAL / VERIFIED / APPROVED / PAID / REJECTED)।
- **`ss_month_closes`:** period (YYYY-MM), orders_count, remarks, exceptions (override হলে কী খোলা ছিল), overridden_by, closed_by / at।

## ৫. Workflow / Status ও Business Rules

- **Sales Order Entry** ("Order to Shipment · Step 1 of 12" স্ট্রিপের নিচে), তিন ট্যাব:
  - **তালিকা:** ফিল্টার, ৪টা টাইল, ২৩ কলাম গ্রুপ হেডারসহ। সারির কাজ: View, Print, S/C Prog, Amend। S/C Prog আর Amend শুধু কনফার্মড খোলা অর্ডারে।
  - **এন্ট্রি:** Master Data / Terms / Shipment / Details Data, তারপর তিনটা গ্রিড। প্রতিটা লাইনে অন্তত একটা সাইজ লাগে। শিপমেন্ট আর ডেলিভারির লক্ষ্য রিসিভের আগে হতে পারে না। "Same as Bill To" টিক দিলে ডেলিভারি পার্টি = Bill To। ঠিকানা ফাঁকা থাকলে পার্টির ঠিকানা বসে। শুধু ড্রাফট বদলানো যায়।
  - **S/C Programme:** বাঁদিকে কনফার্মড অর্ডার; বাছলে লাইন আসে বাকি পরিমাণসহ। শূন্য লাইন বাদ যায়, অর্ডারের পরিমাণের বেশি যেতে পারে না।
- **Order Confirmation** (Step 2): Submit → Check → Approve → Confirm। Return to Draft করলে অনুমোদনের ধাপ মুছে যায়। Close Order শুধু কনফার্মড অর্ডারে, আর ড্রাফট বা গেটে থাকা চালান অথবা খোলা সংশোধন থাকলে হয় না।
- **Sales Order Amendment:**
  - শুধু কনফার্মড খোলা অর্ডারে, একসাথে একটাই খোলা সংশোধন।
  - সাইজ অনুযায়ী সংশোধিত পরিমাণ ও রেট। যা চালানে ধরা আছে (ক্যানসেল ছাড়া) বাদ কনফার্মড রিটার্ন, তার নিচে নামানো যায় না।
  - Submit → Approve / Reject → Apply। Apply করলে সাইজ, রেট আর তারিখ অর্ডারে লেখা হয়, অর্ডারের version এক বাড়ে, আর আগের অবস্থা snapshot-এ থাকে।
  - তালিকায় পুরো অর্ডারের আগের ও পরের পরিমাণ / মূল্য দেখায়।
- **Delivery Challan Issue** (Step 3):
  - শুধু কনফার্মড খোলা অর্ডারে।
  - প্রতিটা সাইজে সীমা = অর্ডার × (১ + অতিরিক্ত%) − অন্য চালানে ধরা (ড্রাফটসহ, যাতে দুই ডেস্ক একই পিস না দেয়) + কনফার্মড রিটার্ন।
  - Submit (গেটে) → Mark Delivered। Cancel করা যায় ড্রাফট বা গেটে থাকা অবস্থায়, তখন পরিমাণ অর্ডারে ফিরে যায়। Delete শুধু ড্রাফট।
  - আবার সেভ করলে Rev? = Yes।
  - বিলের অবস্থা (Waiting / Partially Billed / Billed) রিজেক্ট না হওয়া বিল থেকে হিসাব হয়।
- **Delivery Challan Return:**
  - শুধু ডেলিভারি হওয়া চালানে। তারিখ চালানের আগে হতে পারে না; কারণ বাধ্যতামূলক।
  - ফেরতযোগ্য = ডেলিভারি − ক্যানসেল না হওয়া অন্য রিটার্ন।
  - ড্রাফট কিছু বদলায় না। Confirm করলে অর্ডারের বাকি বাড়ে; কনফার্মড রিটার্ন ক্যানসেল করলে আবার কমে।
- **Delivery Bill Verification:**
  - ডেলিভারি হওয়া চালানের লাইন, এক বিলে এক কাস্টমার।
  - বিলযোগ্য = ডেলিভারি − অন্য বিলে দাবি (রিজেক্ট ছাড়া)।
  - কর্তন মোটের বেশি নয়। Net = Gross − Deduction; টাকা = পরিমাণ × রেট × লাইনের হার।
  - Action Status: 68 = ড্রাফট, 69 = Waiting for Approval। তারপর Verify → Approve (চূড়ান্ত) → Mark Paid; Reject করলে ডেস্কে ফেরত যায়, আবার বদলানো যায়।
  - মন্তব্যের [BILL_NO] / [BILL_DATE] / [SECTION] সেভের সময় বসে।
- **Delivery Completion:** কনফার্মড (খোলা ও বন্ধ) অর্ডার।
  - নিট = ডেলিভারি (গেটে + ডেলিভারি হওয়া) − কনফার্মড রিটার্ন; বাকি = অর্ডার − নিট।
  - অবস্থা: Pending / Partial / Completed। দিন = লক্ষ্য তারিখ পর্যন্ত, পেরোলে ঋণাত্মক।
  - সারির কাজ: Delivery Chalans (`?so=SO-…` দিয়ে ছাঁকা) আর View Order।
- **Sales Order Month Close:**
  - Show Exception দেখায়: ওই মাসে লক্ষ্য তারিখের বাকি থাকা অর্ডার, ড্রাফট বা গেটে থাকা চালান, অনুমোদন না হওয়া বিল, ড্রাফট রিটার্ন, কার্যকর না হওয়া সংশোধন।
  - ব্যতিক্রম থাকলে বন্ধ হয় না; override (`salesshipment.close.override`) দিয়ে শুধু অ্যাডমিন পারেন, তখন কী খোলা ছিল তা রাখা হয়।
  - ভবিষ্যতের মাস বন্ধ হয় না। বন্ধ মাসের তারিখে কোনো কাগজ সেভ হয় না, কোনো ধাপও নেওয়া যায় না। Reopen আছে।

## ৬. API ও পারমিশন

- **পারমিশন (`salesshipment.*`):**
  - order.view / manage / approve; delivery.view / manage; bill.view / manage / approve; close.manage / close.override।
  - FACTORY_ADMIN সব পায়। FACTORY_MANAGER override ছাড়া সব।
  - STORE_MANAGER আর STOREKEEPER: order.view, delivery.view, delivery.manage।
  - AUDITOR সব .view। VIEWER-ও সব .view পায় (তার নিয়মিত read-only সেট)।
- **API (`/sales-shipment/…`):**
  - `options`
  - `orders` (GET / POST / PUT {no}, POST {no}/{submit|check|approve|confirm|return-to-draft|close-order}), `confirmations`, `completion`
  - `sc-programmes` (+ `source/{so}`)
  - `amendments` (+ `orders`, `source/{so}`, POST {no}/{submit|approve|reject|apply|cancel})
  - `challans` (+ `orders`, `source/{so}`, DELETE, POST {no}/{submit|deliver|cancel})
  - `challan-returns` (+ `challans`, `source/{chalan}`, DELETE, POST {no}/{confirm|cancel})
  - `bills` (+ `challans`, `source/{chalan}`, DELETE, POST {no}/{verify|approve|pay|reject})
  - `month-close` (GET, `exceptions?period=`, POST, DELETE {period})
- **মেনু:** OPERATION-এর ভেতরে নতুন উপ-ফোল্ডার "Order & Delivery" (Brickland-এর মতো), ৮টা স্ক্রিন `/sales-shipment/{sales-order|so-amendment|order-confirmation|delivery-chalan|delivery-challan-return|check-bill|delivery-completion|so-month-closing}`।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:**
  - `components/sales-shipment/shared.jsx`: ট্যাব, Searching Data কার্ড, টাইল, রেজিস্টার + confirm, বাঁদিকের পিক-লিস্ট (খোঁজা, কাস্টমার ফিল্টার, পাতায় ১০টা), এডিট-গ্রিড, ফুট, Order-to-Shipment স্ট্রিপ।
  - `sales-order.jsx`, `amendment.jsx`, `registers.jsx` (Confirmation, Completion, Month Close), `challan.jsx` (চালান, রিটার্ন), `bill.jsx`।
- **নিয়ম মেনে:**
  - সব ট্যাব রিলোড ছাড়াই বদলায়। লিংকে শুধু পড়ার মতো রেফারেন্স (`?so=SO-…`, `?tab=sc-programme&so=…`), ULID নেই।
  - একক UOM মাস্টার থেকে।
- **প্রিন্ট:** View (preview) আর Print, `lib/print-document` দিয়ে। আছে Sales Order, Order Confirmation, Amendment, Delivery Challan (গেট পাসসহ), Challan Return আর Check Bill (মোট / কর্তন / নিট, টাকাসহ)।
- **Server actions:** `ssRead` / `ssWrite`, শুধু `/sales-shipment/` পাথে সীমাবদ্ধ।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৮টার ৮টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Database/Migrations/2027_02_21_000100_create_ss_order_delivery.php`
- `Models/` — SsParticular, SsSalesOrder, SsSalesOrderLine, SsSalesOrderSize, SsSalesOrderParticular, SsScProgramme, SsScProgrammeLine, SsAmendment, SsAmendmentLine, SsChallan, SsChallanLine, SsChallanReturn, SsChallanReturnLine, SsBill, SsBillLine, SsMonthClose
- `Support/SalesBook.php` (নাম, দিনভিত্তিক নম্বর, সাইজ ও চালান-লাইনের হিসাব, মাসের লক)
- `Http/Controllers/Api/SalesOrderApiController.php`, `AmendmentApiController.php`, `ChallanApiController.php`, `BillApiController.php`, `MonthCloseApiController.php`
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/shared.jsx`, `sales-order.jsx`, `amendment.jsx`, `registers.jsx`, `challan.jsx`, `bill.jsx` (নতুন)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js`, `actions.js` (বদলানো)
- `frontend/src/components/erp/flow-steps.jsx` (বদলানো: যে ধাপ বানানো হয়নি সেটা লিংক ছাড়া)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentOrderDeliveryTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentOrderDeliveryTest.php`):** test2 DB-তে পাস। SalesShipment-এর দুই ফাইল মিলিয়ে ১৩টা টেস্ট, ২৫৯টা assertion। MenuAccessTest + RoleMatrixTest-ও পাস (১৩টা, ৮৪টা)।
- **অর্ডার:** options-এ তিনটা particular আর "নাম (কোড)" কাস্টমার। আটকায়: লাইন ছাড়া, শিপমেন্ট রিসিভের আগে, অজানা SL#, সাইজ ছাড়া লাইন। SO-20261107-0001 তৈরি হয় (লিড টাইম ১৩, ডেলিভারি পার্টি আর ঠিকানা Bill To থেকে); ড্রাফট বদলানো যায়; তালিকার সংখ্যা ঠিক আসে; সাবমিটের পর বদলানো আটকায়।
- **অনুমোদনের ধাপ:** ক্রম ভাঙা আটকায়; STOREKEEPER চেক পারে না; Return to Draft ধাপ মোছে; কনফার্মে নাম আসে; খোলা চালান থাকলে Close আটকায়, চালান ক্যানসেলের পর বন্ধ হয়।
- **চালান ও রিটার্ন:** সীমা ৫২৫ (৫০০ + ৫%); ড্রাফট ৩০০ ধরে রাখে, তাই পরের চালানে সর্বোচ্চ ২২৫ (বার্তায় সীমা); গেট পাস নম্বর; Submit → Deliver; তালিকায় ৩০০ / Partial। রিটার্নে আটকায় ৩০১ আর কারণ ছাড়া; DCR-2026-000001; ড্রাফট কিছু বদলায় না, Confirm করলে সীমা ২২৫ → ২৬৫। Completion-এ ৩০০ / ৪০ / ২৬০ / ৭৪০ / Partial / ৬ দিন। গেটে থাকা চালান ক্যানসেল করলে পরিমাণ ফেরত যায়; ড্রাফট ছাড়া Delete আটকায়।
- **সংশোধন:** ২০০ চলে যাওয়ার পর ১৫০ আটকায় (বার্তায় সীমা ২০০); SOA-2026-000001, version 1; দ্বিতীয় খোলা সংশোধন আটকায়; অনুমোদনের আগে Apply আটকায়; STOREKEEPER Approve পারে না। Apply-তে সাইজ ৬০০ @ ৪, তারিখ বদলায়, version 1, snapshot-এ ৫০০; তালিকায় ১০০০ → ১১০০; Is Revised।
- **বিল:** গেটে থাকা চালানের বিল আটকায়; আরও আটকায় বেশি পরিমাণ, অন্য কাস্টমার আর মোটের বেশি কর্তন। CB-20261107-0001 ড্রাফট, মন্তব্যের placeholder বসে, ৮০০ / ৭৫০। চালানের অবস্থা Partially Billed, তারপর Billed। ড্রাফটে Verify আটকায়। Reject করলে Waiting For Bill, আবার সাবমিট → Verify → Approve → Paid (চূড়ান্ত, নিট ১১৫০)।
- **S/C:** ড্রাফট অর্ডারে আটকায়; শূন্য লাইন বা বেশি পরিমাণ আটকায়; SC-20261107-0001 (শূন্য লাইন বাদ); তালিকায় SC নং।
- **মাস বন্ধ:** অক্টোবরের ব্যতিক্রম UNDELIVERED আর OPEN_CHALLAN; ভুল মাস বা ভবিষ্যৎ আটকায়; ব্যতিক্রম থাকলে বন্ধ আটকায়; FACTORY_MANAGER override পারে না; মালিক override করে বন্ধ করেন (১টা অর্ডার); তারপর অক্টোবরের তারিখে অর্ডার আর চালানের ধাপ আটকায়; Reopen-এর পর চলে।
- **কে পারে:** STOREKEEPER অর্ডার দেখে আর চালান বানায়, কিন্তু অর্ডার লিখতে, বিল বা মাস বন্ধ করতে পারে না। AUDITOR সব দেখে, বন্ধ করতে পারে না। VIEWER দেখে, লিখতে পারে না। TECHNICIAN পায় না।

**UI চেক (`app_check/plans/od01`, `od02`; দুটোতেই error 0, dev ডাটা রাখা আছে):**
- SO-20261107-0001: Nitol Packaging, Poly Bag 12x16, ২ সাইজ (৬০০ + ৪০০) @ ৪.৫০, particular "Poly Thickness — 60 micron"।
- Order Confirmation-এ Submit → Check → Approve → Confirm।
- DC-20261107-0001 (Trims & accessories store, ৮০০ পিস) গেটে → ডেলিভারি।
- DCR-2026-000001 (২০ পিস, Quality Issue) কনফার্মড।
- SC-20261107-0001 (Square Textiles, Printing)।
- SOA-2026-000001: ১৪x১৮ সাইজ ৪০০ → ৫০০; তালিকায় ১০০০ → ১১০০, Applied।
- CB-20261107-0001: ৮০০ পিস, কর্তন ১০০, নিট ৩৫০০; Verify → Approve → Paid।
- Completion: ১১০০ / ৮০০ / ২০ / ৭৮০ / ৩২০ / Partial।
- Month Close: Nov/2026-এর Show Exception-এ "Undelivered order … 320 still to deliver"। কোনো dev মাস বন্ধ করা হয়নি।
- প্রিন্ট: Sales Order আর Check Bill-এর preview দেখে নেওয়া হয়েছে।

## ১০. খোলা প্রশ্ন

১. **বিল আর রিটার্ন:** Brickland-এর নিয়ম ধরে বিলযোগ্য = ডেলিভারি − অন্য বিল; ফেরত আসা পরিমাণ বাদ যায় না। ফলে UI চেকে ২০ পিস ফেরতের পরও পুরো ৮০০-র বিল হয়েছে। রিটার্ন বাদ দিয়ে বিল হবে কিনা জানাবেন।

২. **Sales Order Particular-এর মাস্টার:** Brickland-এ কোনো লাইব্রেরি মাস্টার থেকে আসে (Carton Dimension, Poly Thickness, Print Placement)। আমাদের এমন মাস্টার নেই, তাই এই তিনটা নিজে থেকে বসে; নতুন যোগের কোনো স্ক্রিন নেই। কোন স্ক্রিনে রাখা হবে জানাবেন।

৩. **Section-এর তালিকা:** Brickland-এ শুধু ৪টা ("Accessories — Store" ইত্যাদি), সম্ভবত Store ডিপার্টমেন্টের সেকশন। আমরা কোম্পানির সব সেকশন "সেকশন — ডিপার্টমেন্ট" হিসেবে দেখাচ্ছি। শুধু একটা ডিপার্টমেন্টের সেকশন দেখাতে হবে কিনা জানাবেন।

৪. **নম্বরের ধরন:** SO, চালান, গেট পাস, S/C আর বিলের নম্বর Brickland ডেমোতে দেখা যায়নি। এখন দিনভিত্তিক: SO-20261107-0001, DC-…, GP-…, SC-…, CB-…। সংশোধন আর চালান রিটার্ন 21/01-এর settings থেকে। অন্য ধরন লাগলে জানাবেন।

৫. **চালানের লাইন সাইজ অনুযায়ী:** Brickland-এর "SO item" কোন স্তরের, ডেমো খালি থাকায় বোঝা যায়নি। আমরা চালান, রিটার্ন আর বিল সাইজ (আইটেম · রং · সাইজ) অনুযায়ী রেখেছি, যাতে সংশোধনের সাইজ-হিসাবের সাথে মেলে।

৬. **Shipped Qty / Shipment Status:** সেলস অর্ডার তালিকায় এখন 0 / "Not Shipped"। 21/05 Shipment বানানোর পর আসল হিসাব আসবে। তখন মাস বন্ধের "শিপমেন্ট বাকি থাকা অর্ডার" ব্যতিক্রমও যোগ হবে; S/C রিটার্নের ব্যতিক্রম যোগ হবে 21/03-এ।

## ১১. As-built নোট

- **Brickland প্রোব:**
  - `erp_crawler/plans/od01` (৮টা স্ক্রিনের লেখা আর স্ক্রিনশট)।
  - প্রতিটা পেজের HTML-এর RSC payload থেকে স্ক্রিনের স্পেক: তালিকার কলাম (গ্রুপসহ), ফিল্টার, অবস্থা অনুযায়ী সারির কাজ আর confirm-এর লেখা, প্যানেলের ঘর, গ্রিডের কলাম, সারাংশ, নোট আর Action Status।
  - JS চাঙ্কে এই স্ক্রিনগুলোর লেখা নেই; স্পেক সার্ভার থেকে HTML-এর সাথে আসে।
- **সারির কাজ:** Brickland অবস্থা অনুযায়ী বাটন লুকায় (showWhen)। আমাদের RecordsGrid আগের মডিউলগুলোর মতো বাটন নিষ্ক্রিয় দেখায়।
- **মেনুর গঠন:** Brickland-এ OPERATION-এর ভেতরে "Order & Delivery" উপ-ফোল্ডার। আমাদের মেনুতেও একইভাবে (parent folder)।
- **UI চেকে ঠিক করা:** এন্ট্রির শেষ কার্ডে লম্বা নোট সরু হয়ে যাচ্ছিল, এখন আলাদা লাইনে দেখায়।
- **টেস্টে ধরা পড়া:** সংশোধনের তালিকায় আগে শুধু সংশোধিত লাইনের যোগফল আসছিল, এখন পুরো অর্ডারের আগের ও পরের পরিমাণ আসে।
