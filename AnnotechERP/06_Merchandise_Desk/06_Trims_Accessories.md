# Trims & Accessories

> **স্ট্যাটাস:** ✅ সম্পন্ন (১৯টা স্ক্রিনই চালু: T1–T13 এন্ট্রি আর সেটআপ, T14–T19 ছয়টা Closing)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/06`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Accessories Purchase Indent | `/m/merchandising/accessories-pr` | 3 | 23 | 1 |
| 2 | Accessories Purchase Order | `/m/merchandising/accessories-po` | 3 | 31 | 1 |
| 3 | Accessories Receipt Report | `/m/merchandising/accessories-mrr` | 3 | 29 | 0 |
| 4 | Procurement Cost Head | `/m/merchandising/procurement-cost-head` | 2 | 8 | 1 |
| 5 | Procurement Posting Setup | `/m/merchandising/procurement-posting-setup` | 1 | 0 | 1 |
| 6 | Accessories Supplier Return Challan | `/m/merchandising/accessories-return-challan-to-supplier` | 2 | 19 | 1 |
| 7 | Purchased Fabric Opening Balance | `/m/merchandising/purchase-fabric-opening-balance` | 3 | 37 | 0 |
| 8 | Dummy Order Accessories Indent | `/m/merchandising/accessories-pr-dummy-ord` | 4 | 22 | 0 |
| 9 | Purchased Fabric Indent | `/m/merchandising/purchase-fabric-pr` | 2 | 20 | 1 |
| 10 | Purchased Fabric Purchase Order | `/m/merchandising/purchase-fabric-po` | 3 | 46 | 1 |
| 11 | Purchased Fabric Receipt Report | `/m/merchandising/purchase-fabric-mrr` | 2 | 41 | 1 |
| 12 | Fabric Supplier Return Challan | `/m/merchandising/fabric-return-challan-to-supplier` | 2 | 19 | 1 |
| 13 | Finished Fabric Opening Balance | `/m/merchandising/finish-fabric-opening-balance` | 2 | 21 | 0 |
| 14 | Purchased Fabric Closing | `/m/merchandising/purchase-fabric-closing` | 2 | 1 | 0 |
| 15 | Finished Fabric Closing | `/m/merchandising/finish-fabric-closing` | 2 | 1 | 0 |
| 16 | Trims Stock Closing | `/m/merchandising/trims-closing` | 2 | 1 | 0 |
| 17 | Batch Fabric Stock Closing | `/m/merchandising/batch-fabric-closing` | 2 | 1 | 0 |
| 18 | Grey Fabric Leftover Closing | `/m/merchandising/gray-fab-left-over-closing` | 2 | 1 | 0 |
| 19 | Finished Fabric Leftover Closing | `/m/merchandising/finish-fab-left-over-closing` | 2 | 1 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Accessories Purchase Indent

`/m/merchandising/accessories-pr`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req Month | select |  |  |
| Buyer A/C | select |  |  |
| Job/Order # | select |  |  |
| Supplier | select |  |  |
| Status | select |  | Draft, Submitted, Approved, Rejected, Closed |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Search Purchase requisitions with their date, buyer account, order, supplier, it | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reqisition No, Req Date, Addition For, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List, ACTION
- বাটন/অ্যাকশন: Accessories Booking BOM for PR, Requisition Entry, Clear, Add New, Export, Reqisition No, Req Date, Addition For, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List

**ট্যাব: Accessories Booking BOM for PR**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk, Development, Repeat, Projection, Buffer, Special Sample |
| Buyer A/C | select |  |  |
| Job/Order # | select |  | LPP-2026-000001, LPP-2026-000002, LPP-2026-000003, LPP-2026-000004, LPP-2026-000005, LPP-2026-000006, LPP-2026-000007, L |
| Req Type | select |  | All, Requisition for Accessories Purchase |
- গ্রিড কলাম: Buyer Name, Style Description, Job #, Order #, Order Type, Order Qty, Shipment Date, Action
- সেকশন: Search, Details Data
- বাটন/অ্যাকশন: Accessories Booking BOM for PR, Requisition Entry, Show, PR List, Add PR

**ট্যাব: Requisition Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search Order List | search |  | search order, job, style… |
| Existing requisition | select |  |  |
| Requisition No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Status | text |  | Draft |
| Job/Order # | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Addition For | text |  | Short Qty |
| BOM | text |  | The order's approved BOM |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Order #, Job · Style, BOM
- সেকশন: Order List, Requisition Entry, Item List
- বাটন/অ্যাকশন: Accessories Booking BOM for PR, Requisition Entry, LPP-2026-000007, LPP-2026-000002, LPP-2026-000005, LPP-2026-000001, LPP-2026-000004, LPP-2026-000008, LPP-2026-000003, LPP-2026-000006, Select…, Add item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Type | select |  | All, Bulk, Development, Repeat, Projection, Buffer, Special Sample |
| Buyer A/C | select |  |  |
| Job/Order # | select |  | LPP-2026-000001, LPP-2026-000002, LPP-2026-000003, LPP-2026-000004, LPP-2026-000005, LPP-2026-000006, LPP-2026-000007, L |
| Req Type | select |  | All, Requisition for Accessories Purchase |
- খালি Save-এ মেসেজ: That record no longer exists.

#### Accessories Purchase Order

`/m/merchandising/accessories-po`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PO No | search |  | PO No |
| PR No | search |  | PR No |
| Supplier | select |  |  |
| Source | select |  |  |
| Payment Term | select |  |  |
| Payment Method | select |  |  |
| PO Date from | date |  |  |
| PO Date to | date |  |  |
| Search Purchase orders with their receive status, supplier, store, requisitions, | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Receive Status, Revision Status, PO No, PO Date, PR Number(s), MRR(s), Supplier Name, Store, Inco Term, Payment Term, Payment Method, Value, ACTION
- বাটন/অ্যাকশন: Purchase Order Entry, Requisition List, Clear, Add New, Export, Receive Status, Revision Status, PO No, PO Date, PR Number(s), MRR(s), Supplier Name, Store, Inco Term, Payment Term, Payment Method, Value

**ট্যাব: Purchase Order Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing purchase order | select |  |  |
| PO No | text |  | [Auto Gen] |
| PO Date* | date | ✔ |  |
| Source* | select | ✔ | Select…, Local, Import |
| Against PR | select |  | Select… |
| Beneficiary | select |  | Select…, Brickland Composite Ltd. |
| Billed To | select |  | Select…, Brickland Composite Ltd. |
| Is Online? | checkbox |  |  |
| Same as Company! Bills the beneficiary rather than a separate company. | checkbox |  |  |
| Inco Term | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| Place of Delivery | text |  |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Payment Term* | select | ✔ | Select…, At Sight, LC at 30 days, LC at 60 days, LC at 90 days, Cash on Delivery, Advance, Credit 30 days |
| Mode of Shipment* | select | ✔ | Select…, By Road, By Sea, By Air, By Courier |
| Delivery Date* | date | ✔ |  |
| Cash Supplier | text |  |  |
| Other Term | textarea |  |  |
| Remarks | textarea |  |  |
- সেকশন: Purchase Order Entry, Master Data, Terms & Condition, Details Data
- বাটন/অ্যাকশন: Purchase Order Entry, Requisition List, Select…, Add to PO, Clear, Save purchase order

**ট্যাব: Requisition List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | search |  | Search… |
- সেকশন: Buyer, Requisitions
- বাটন/অ্যাকশন: Purchase Order Entry, Requisition List

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing purchase order | select |  |  |
| PO No | text |  |  |
| PO Date* | date | ✔ |  |
| Source* | select | ✔ | Select…, Local, Import |
| Against PR | select |  | Select… |
| Beneficiary | select |  | Select…, Brickland Composite Ltd. |
| Billed To | select |  | Select…, Brickland Composite Ltd. |
| Is Online? | checkbox |  |  |
| Same as Company! Bills the beneficiary rather than a separate company. | checkbox |  |  |
| Inco Term | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| Place of Delivery | text |  |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Payment Term* | select | ✔ | Select…, At Sight, LC at 30 days, LC at 60 days, LC at 90 days, Cash on Delivery, Advance, Credit 30 days |
| Mode of Shipment* | select | ✔ | Select…, By Road, By Sea, By Air, By Courier |
| Delivery Date* | date | ✔ |  |
| Cash Supplier | text |  |  |
| Other Term | textarea |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A purchase order with no lines is not a purchase order.

#### Accessories Receipt Report

`/m/merchandising/accessories-mrr`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Receipt Entry, Receive List

**ট্যাব: Receipt Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing receipt | select |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| MRR NO | text |  | [Auto Gen] |
| Date of Receive* | date | ✔ |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Source Type* | select | ✔ | Select…, Local, Import |
| Challan No* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| PO | select |  | Select… |
| PR | select |  | Select… |
| Remarks | textarea |  |  |
| Discount % | number |  |  |
| Discount Amount | number |  |  |
| VAT % | number |  |  |
| Supplier TDS % | number |  |  |
| Supplier VDS % | number |  |  |
| Goods Value | text |  | On save |
| Other Cost | text |  | On save |
| Landed Cost | text |  | On save |
| VAT Base | text |  | On save |
| VAT | text |  | On save |
| Supplier TDS | text |  | On save |
| Supplier VDS | text |  | On save |
| Net Payable | text |  | On save |
| Accounts Voucher | text |  | Raised on finalise |
| MRR Type* | select | ✔ | Select…, Po, Pi, Exchangeable |
- সেকশন: Receipt Entry, Master Data, Discount, VAT & Deductions at Source, Bill Summary, Received Items, Other Cost
- বাটন/অ্যাকশন: Receipt Entry, Receive List, Select…, Add received line, Add other cost, Clear, Save receipt

**ট্যাব: Receive List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search suppliers | search |  | Search… |
- সেকশন: Customer, Purchase orders
- বাটন/অ্যাকশন: Receipt Entry, Receive List

#### Procurement Cost Head

`/m/merchandising/procurement-cost-head`

**ট্যাব: Records 5**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Other cost heads a receipt can carry, whether each is landed into stock v | search |  | Search 5 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Cost Head, Landed Cost?, VAT Applicable?, Expense Ledger, Order, Status, ACTION
- বাটন/অ্যাকশন: New cost head, Export, Code, Cost Head, Landed Cost?, VAT Applicable?, Expense Ledger, Order, Status, Edit

**ট্যাব: New cost head**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Code* | text | ✔ | CARRIAGE |
| Cost Head* | text | ✔ | Carriage Inward |
| Landed Cost? Spread over the received lines and added to their stock value. | checkbox |  |  |
| VAT Applicable? Inside the VAT base the receipt's VAT % is applied to. | checkbox |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- সেকশন: New cost head
- বাটন/অ্যাকশন: New cost head, Select…, Reset, Save cost head

**ডায়ালগ: New cost head**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Code* | text | ✔ |  |
| Cost Head* | text | ✔ |  |
| Landed Cost? Spread over the received lines and added to their stock value. | checkbox |  |  |
| VAT Applicable? Inside the VAT base the receipt's VAT % is applied to. | checkbox |  |  |
| Display Order | number |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Code is required.

#### Procurement Posting Setup

`/m/merchandising/procurement-posting-setup`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: New posting setup

**ডায়ালগ: New posting setup**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Voucher Type* | select | ✔ | Select…, Bank Payment Voucher (BPV), Bank Receive Voucher (BRV), Cash Payment Voucher (CPV), Cash Receive Voucher (CRV), |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Company is required.

#### Accessories Supplier Return Challan

`/m/merchandising/accessories-return-challan-to-supplier`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | search |  |  |
| MRR No | search |  |  |
| Return Challan Type | select |  |  |
| Company | select |  |  |
| Supplier | select |  |  |
| Store | select |  |  |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Return challans against a receipt, with their type, company, supplier, st | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store, ACTION
- বাটন/অ্যাকশন: Accessories Return Challan to Supplier, Clear, Add New, Export, Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store

**ট্যাব: Accessories Return Challan to Supplier**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search MRR List | search |  | search... |
| Return Type* | select | ✔ | Select…, Rejected, Excess, Short Shipped, Off Shade, Sample Return, Other |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Challan No | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  | Gate Pass No |
| Vehicle No. | text |  | Vehicle No |
| Driver Name | text |  | Carrier No |
| Remarks | textarea |  |  |
- গ্রিড কলাম: MRR NO, MRR Date, CHALAN
- সেকশন: MRR List, Accessories Return Challan to Supplier, Master List, Details Data
- বাটন/অ্যাকশন: Accessories Return Challan to Supplier, -- Select Supplier/Cutomer --, Select…, Add returned line, Back To List, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search MRR List | search |  |  |
| Return Type* | select | ✔ | Select…, Rejected, Excess, Short Shipped, Off Shade, Sample Return, Other |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Challan No | text |  |  |
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  |  |
| Vehicle No. | text |  |  |
| Driver Name | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines is not a return challan.

#### Purchased Fabric Opening Balance

`/m/merchandising/purchase-fabric-opening-balance`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Submitted, Approved, Posted, Locked |
| Receiving Store | select |  |  |
| Source | select |  |  |
| OB Date from | date |  |  |
| OB Date to | date |  |  |
| Search Purchased fabric opening-stock documents with their number, status, store | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: OB No, OB Date, Company, Receiving Store, Style/Job/Order, Source, Reference, Currency, Conv Rate, 1USD=BDT, Items, Rolls, Total Qty, Total OB Cost, ACTION
- বাটন/অ্যাকশন: Opening Balance Entry, Approval History, Clear, Add New, Export, OB No, OB Date, Company, Receiving Store, Style/Job/Order, Source, Reference, Currency, Conv Rate, 1USD=BDT, Items, Rolls, Total Qty, Total OB Cost

**ট্যাব: Opening Balance Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing opening balance | select |  |  |
| OB No | text |  | [Auto Gen] |
| Status | text |  | Draft |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| OB Date* | date | ✔ |  |
| Style/Job/Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conversion Rate | number |  |  |
| 1 USD = BDT | number |  | 123.78 |
| Source* | select | ✔ | Select…, Physical Stock Count, Legacy ERP Migration, Stock Take Adjustment, Other |
| Reference No | text |  | Count sheet or legacy document no |
| Attachment | text |  | Path or link, or attach a file |
| Attach | file |  |  |
| Remarks | textarea |  |  |
| Fabric Category | select |  | Select…, Purchase Fabric, Woven Fabric, Knit Fabric, Denim, Lining |
| Description | text |  |  |
| Fabric Item | text |  |  |
| Fabric Type | select |  | Select…, 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| FIN GSM | number |  |  |
| FIN DIA | number |  |  |
| DIA UOM | select |  | Select…, Inch, Cm |
| DIA Type | select |  | Select…, Open, Tube |
| Batch No | text |  |  |
| No. of Roll | number |  |  |
| Roll Nos | text |  | R-01, R-02, R-03 |
| OB UOM | select |  | Select…, Kg, Lbs, Yds, Mtr |
| OB Qty | number |  |  |
| OB Rate | number |  |  |
- সেকশন: Opening Balance Entry, Header, Source & Reference, Fabric Details
- বাটন/অ্যাকশন: Opening Balance Entry, Approval History, Select…, Add Row, Cancel, Back To List, Clear, Save opening balance

**ট্যাব: Approval History**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search opening balances | search |  | Search… |
- সেকশন: Opening Balances, Workflow steps
- বাটন/অ্যাকশন: Opening Balance Entry, Approval History

#### Dummy Order Accessories Indent

`/m/merchandising/accessories-pr-dummy-ord`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk, Development, Repeat, Projection, Buffer, Special Sample |
| Buyer A/C | select |  | All |
| Job/Order # | select |  | All, LPP-2026-000001, LPP-2026-000002, LPP-2026-000003, LPP-2026-000004, LPP-2026-000005, LPP-2026-000006, LPP-2026-0000 |
| Req Type | select |  | All, Requisition for Accessories Purchase |
| Search Orders a dummy-order requisition may be raised against, with their buyer, | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer Name, Style Description, Job #, Order #, Order Type, Order Qty, Shipment Date, ACTION
- বাটন/অ্যাকশন: Requisition Entry (Dummy Order), PR List, Order BOM, Clear, Export, Buyer Name, Style Description, Job #, Order #, Order Type, Order Qty, Shipment Date, View BOM

**ট্যাব: Requisition Entry (Dummy Order)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search Order List | search |  | search order, job, style… |
| Existing requisition | select |  |  |
| Requisition No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Status | text |  | Draft |
| Job/Order # | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Addition For | text |  | Short Qty |
| BOM | text |  | The order's approved BOM |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Order #, Job · Style, BOM
- সেকশন: Order List, Requisition Entry (Dummy Order), Item List
- বাটন/অ্যাকশন: Requisition Entry (Dummy Order), PR List, Order BOM, LPP-2026-000007, LPP-2026-000002, LPP-2026-000005, LPP-2026-000001, LPP-2026-000004, LPP-2026-000008, LPP-2026-000003, LPP-2026-000006, Select…, Add item, Back To List, Clear, Save requisition

**ট্যাব: PR List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req Month | select |  |  |
| Supplier | select |  |  |
| Buyer A/C Group | select |  |  |
| Status | select |  | Draft, Submitted, Approved, Rejected, Closed |
| Req / Job / Order No | search |  |  |
- গ্রিড কলাম: Req No, Req Date, Addition For, Buyer A/C Group, Job No, Order No, Supplier, Item List, Status, Action
- সেকশন: Search, Requisitions
- বাটন/অ্যাকশন: Requisition Entry (Dummy Order), PR List, Order BOM, Show, Order List

**ট্যাব: Order BOM**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Orders, Approved BOM
- বাটন/অ্যাকশন: Requisition Entry (Dummy Order), PR List, Order BOM

#### Purchased Fabric Indent

`/m/merchandising/purchase-fabric-pr`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Req Month | select |  |  |
| Buyer A/C | select |  |  |
| Job/Order # | select |  |  |
| Supplier | select |  |  |
| Status | select |  | Draft, Submitted, Approved, Rejected, Closed |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Search Purchase requisitions with their date, buyer account, order, supplier, it | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reqisition No, Req Date, Ship Date, GSM, DIA, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List, ACTION
- বাটন/অ্যাকশন: Fabric Import Requisition Entry, Clear, Add New, Export, Reqisition No, Req Date, Ship Date, GSM, DIA, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List

**ট্যাব: Fabric Import Requisition Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search Order List | search |  | search order, job, style… |
| Existing requisition | select |  |  |
| Requisition No | text |  | [Auto Gen] |
| Req Date* | date | ✔ |  |
| Status | text |  | Draft |
| Job/Order # | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Ship Date | date |  |  |
| GSM | text |  | 180 |
| DIA | text |  | 26 inch |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Order #, Job · Style, Ship Date
- সেকশন: Order List, Fabric Import Requisition Entry, Fabric Item Details
- বাটন/অ্যাকশন: Fabric Import Requisition Entry, LPP-2026-000007, LPP-2026-000002, LPP-2026-000005, LPP-2026-000001, LPP-2026-000004, LPP-2026-000008, LPP-2026-000003, LPP-2026-000006, Select…, Add item, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search Order List | search |  |  |
| Existing requisition | select |  |  |
| Requisition No | text |  |  |
| Req Date* | date | ✔ |  |
| Status | text |  |  |
| Job/Order # | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Ship Date | date |  |  |
| GSM | text |  |  |
| DIA | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requisition with no items is not a requisition.

#### Purchased Fabric Purchase Order

`/m/merchandising/purchase-fabric-po`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PO No | search |  | PO No |
| PR No | search |  | PR No |
| Supplier | select |  |  |
| Source | select |  |  |
| Payment Term | select |  |  |
| Payment Method | select |  |  |
| PO Date from | date |  |  |
| PO Date to | date |  |  |
| Search Purchase orders with their receive status, supplier, store, requisitions, | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Receive Status, Revision Status, PO No, PO Date, PR Number(s), MRR(s), Supplier Name, Store, Inco Term, Payment Term, Payment Method, PO Group, Value, ACTION
- বাটন/অ্যাকশন: Fabric Purchase Order Entry, Requisition List, Clear, Add New, Export, Receive Status, Revision Status, PO No, PO Date, PR Number(s), MRR(s), Supplier Name, Store, Inco Term, Payment Term, Payment Method, PO Group, Value

**ট্যাব: Fabric Purchase Order Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Approved PR List | search |  | search PR… |
| Existing purchase order | select |  |  |
| PO No | text |  | [Auto Gen] |
| PO Date* | date | ✔ |  |
| Source* | select | ✔ | Select…, Local, Import |
| Against PR | select |  | Select… |
| Beneficiary | select |  | Select…, Brickland Composite Ltd. |
| Billed To | select |  | Select…, Brickland Composite Ltd. |
| Is Online? | checkbox |  |  |
| Same as Company! Bills the beneficiary rather than a separate company. | checkbox |  |  |
| PO Group | text |  |  |
| Inco Term | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| Place of Delivery | text |  |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Payment Term* | select | ✔ | Select…, At Sight, LC at 30 days, LC at 60 days, LC at 90 days, Cash on Delivery, Advance, Credit 30 days |
| Mode of Shipment* | select | ✔ | Select…, By Road, By Sea, By Air, By Courier |
| Delivery Date* | date | ✔ |  |
| Cash Supplier | text |  |  |
| Other Term | textarea |  |  |
| Remarks | textarea |  |  |
| Discount % | number |  |  |
| Discount Amount | number |  |  |
| VAT % | number |  |  |
| Supplier TDS % | number |  |  |
| Supplier VDS % | number |  |  |
| Adjustment (+/−) | text |  |  |
| Adjustment Note | text |  |  |
| PO Value | text |  | On save |
| Other Cost | text |  | On save |
| VAT | text |  | On save |
| Supplier TDS | text |  | On save |
| Supplier VDS | text |  | On save |
| Net PO Amount | text |  | On save |
- গ্রিড কলাম: PR No, Req Date, Order
- সেকশন: Approved PR List, Fabric Purchase Order Entry, Master Data, Terms & Condition, Cost, Tax & Adjustment, PO Amount, Details Data, Other Cost
- বাটন/অ্যাকশন: Fabric Purchase Order Entry, Requisition List, -- Any Supplier --, Select…, Add to PO, Add other cost, Clear, Save purchase order

**ট্যাব: Requisition List**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search buyers | search |  | Search… |
- সেকশন: Buyer, Requisitions
- বাটন/অ্যাকশন: Fabric Purchase Order Entry, Requisition List

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Approved PR List | search |  |  |
| Existing purchase order | select |  |  |
| PO No | text |  |  |
| PO Date* | date | ✔ |  |
| Source* | select | ✔ | Select…, Local, Import |
| Against PR | select |  | Select… |
| Beneficiary | select |  | Select…, Brickland Composite Ltd. |
| Billed To | select |  | Select…, Brickland Composite Ltd. |
| Is Online? | checkbox |  |  |
| Same as Company! Bills the beneficiary rather than a separate company. | checkbox |  |  |
| PO Group | text |  |  |
| Inco Term | select |  | Select…, FOB, CFR, CIF, CPT, EXW, DDP |
| Place of Delivery | text |  |  |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Payment Term* | select | ✔ | Select…, At Sight, LC at 30 days, LC at 60 days, LC at 90 days, Cash on Delivery, Advance, Credit 30 days |
| Mode of Shipment* | select | ✔ | Select…, By Road, By Sea, By Air, By Courier |
| Delivery Date* | date | ✔ |  |
| Cash Supplier | text |  |  |
| Other Term | textarea |  |  |
| Remarks | textarea |  |  |
| Discount % | number |  |  |
| Discount Amount | number |  |  |
| VAT % | number |  |  |
| Supplier TDS % | number |  |  |
| Supplier VDS % | number |  |  |
| Adjustment (+/−) | text |  |  |
| Adjustment Note | text |  |  |
| PO Value | text |  |  |
| Other Cost | text |  |  |
| VAT | text |  |  |
| Supplier TDS | text |  |  |
| Supplier VDS | text |  |  |
| Net PO Amount | text |  |  |
- খালি Save-এ মেসেজ: A purchase order with no lines is not a purchase order.

#### Purchased Fabric Receipt Report

`/m/merchandising/purchase-fabric-mrr`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | search |  | MRR-IMF-01… |
| MRR Date from | date |  |  |
| MRR Date to | date |  |  |
| Company | select |  |  |
| Store | select |  |  |
| Supplier | select |  |  |
| Payment Method | select |  |  |
| Requisition Type | select |  |  |
| PR | search |  |  |
| PO | search |  |  |
| Search Material receipt reports with their status, company, supplier, store and  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Is Finalized, Is Accounting, MRR No, MRR Date, PR, PO, Company, Supplier, Store, Req. Type, Pay Method, Source Type, LC No, RCV Qty, Exch/Ret Qty, Net Amount, Remarks, Goods Value, VAT, TDS / VDS, Net Payable, Voucher, ACTION
- বাটন/অ্যাকশন: Fabric MRR, Clear, Add New, Export, Is Finalized, Is Accounting, MRR No, MRR Date, PR, PO, Company, Supplier, Store, Req. Type, Pay Method, Source Type, LC No, RCV Qty, Exch/Ret Qty, Net Amount

**ট্যাব: Fabric MRR**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Receive List | search |  | search... |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| MRR NO | text |  | [Auto Gen] |
| Source Type* | select | ✔ | Select…, Local, Import |
| Date of Receive* | date | ✔ |  |
| Challan No* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Import LC No | text |  |  |
| LC Date | date |  |  |
| BILL OF Entry | text |  |  |
| Bill of Entry Date | date |  |  |
| Remarks | textarea |  |  |
| Discount % | number |  |  |
| Discount Amount | number |  |  |
| VAT % | number |  |  |
| Supplier TDS % | number |  |  |
| Supplier VDS % | number |  |  |
| Goods Value | text |  | On save |
| Other Cost | text |  | On save |
| Landed Cost | text |  | On save |
| VAT Base | text |  | On save |
| VAT | text |  | On save |
| Supplier TDS | text |  | On save |
| Supplier VDS | text |  | On save |
| Net Payable | text |  | On save |
| Accounts Voucher | text |  | Raised on finalise |
- গ্রিড কলাম: PO Number, PO Date
- সেকশন: Receive List, Fabric MRR, Master List, Discount, VAT & Deductions at Source, Bill Summary, Details Data, Other Cost
- বাটন/অ্যাকশন: Fabric MRR, -- Select Supplier/Cutomer --, Select…, Add received line, Add other cost, Back To List, Clear, Save receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Receive List | search |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Payment Method* | select | ✔ | Select…, Cash, Lc, Cheque, Tt, Consignment |
| MRR NO | text |  |  |
| Source Type* | select | ✔ | Select…, Local, Import |
| Date of Receive* | date | ✔ |  |
| Challan No* | text | ✔ |  |
| Challan Date* | date | ✔ |  |
| Currency* | select | ✔ | Select…, BDT, USD, EUR, CNY, INR |
| Conv. Rate | number |  |  |
| Import LC No | text |  |  |
| LC Date | date |  |  |
| BILL OF Entry | text |  |  |
| Bill of Entry Date | date |  |  |
| Remarks | textarea |  |  |
| Discount % | number |  |  |
| Discount Amount | number |  |  |
| VAT % | number |  |  |
| Supplier TDS % | number |  |  |
| Supplier VDS % | number |  |  |
| Goods Value | text |  |  |
| Other Cost | text |  |  |
| Landed Cost | text |  |  |
| VAT Base | text |  |  |
| VAT | text |  |  |
| Supplier TDS | text |  |  |
| Supplier VDS | text |  |  |
| Net Payable | text |  |  |
| Accounts Voucher | text |  |  |
- খালি Save-এ মেসেজ: A receipt with no lines is not a receipt.

#### Fabric Supplier Return Challan

`/m/merchandising/fabric-return-challan-to-supplier`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | search |  |  |
| MRR No | search |  |  |
| Return Challan Type | select |  |  |
| Company | select |  |  |
| Supplier | select |  |  |
| Store | select |  |  |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Return challans against a receipt, with their type, company, supplier, st | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store, ACTION
- বাটন/অ্যাকশন: Fabric Return Challan to Supplier, Clear, Add New, Export, Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store

**ট্যাব: Fabric Return Challan to Supplier**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search MRR List | search |  | search... |
| Return Type* | select | ✔ | Select…, Rejected, Excess, Short Shipped, Off Shade, Sample Return, Other |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Challan No | text |  | [Auto Gen] |
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  | Gate Pass No |
| Vehicle No. | text |  | Vehicle No |
| Driver Name | text |  | Carrier No |
| Remarks | textarea |  |  |
- গ্রিড কলাম: MRR NO, MRR Date, CHALAN
- সেকশন: MRR List, Fabric Return Challan to Supplier, Master List, Details Data
- বাটন/অ্যাকশন: Fabric Return Challan to Supplier, -- Select Supplier/Cutomer --, Select…, Add returned line, Back To List, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search MRR List | search |  |  |
| Return Type* | select | ✔ | Select…, Rejected, Excess, Short Shipped, Off Shade, Sample Return, Other |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Challan No | text |  |  |
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  |  |
| Vehicle No. | text |  |  |
| Driver Name | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines is not a return challan.

#### Finished Fabric Opening Balance

`/m/merchandising/finish-fabric-opening-balance`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Opening Balance Entry

**ট্যাব: Opening Balance Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing opening balance | select |  |  |
| OB Date* | date | ✔ |  |
| Style/Job/Order | select |  | Select…, LPP-2026-000008 · 529 (529), LPP-2026-000007 · 1111 (1111), LPP-2026-000006 · 529 (529), LPP-2026-000005 · 2342 |
| Currency | text |  |  |
| Conv Rate | number |  |  |
| 1USD=BDT | number |  | 123.78 |
| Fab Category | text |  |  |
| Fab Description | text |  |  |
| Fab Item | text |  |  |
| Fab Color | text |  |  |
| Fab Type | select |  | Select…, 1x1 Rib, 2x2 Rib, Fleece, French Terry, Interlock, Lycra Single Jersey, Mesh, Pique / Lacoste, Single Jersey |
| Fabric Group | select |  | Select…, Body, Collar, Lining, Rib |
| FIN. GSM | number |  |  |
| FIN. DIA | number |  |  |
| DIA MOU | select |  | Select…, Inch, Cm |
| DIA Type | select |  | Select…, Open, Tube |
| Batch No | text |  |  |
| No Of Roll | number |  |  |
| OB UOM | select |  | Select…, Kg, Lbs, Yds, Mtr |
| OB QTY | number |  |  |
| OB COST | number |  |  |
- সেকশন: Opening Balance Entry, Fabric Items
- বাটন/অ্যাকশন: Opening Balance Entry, Select…, Add Row, Cancel, Clear, Save opening balance

#### Purchased Fabric Closing

`/m/merchandising/purchase-fabric-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close

#### Finished Fabric Closing

`/m/merchandising/finish-fabric-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close

#### Trims Stock Closing

`/m/merchandising/trims-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close

#### Batch Fabric Stock Closing

`/m/merchandising/batch-fabric-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close

#### Grey Fabric Leftover Closing

`/m/merchandising/gray-fab-left-over-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close

#### Finished Fabric Leftover Closing

`/m/merchandising/finish-fab-left-over-closing`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Show Exception, Month Close

**ট্যাব: Show Exception**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search periods | search |  | Search… |
- সেকশন: Periods, Exceptions
- বাটন/অ্যাকশন: Show Exception, Month Close
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **স্টাইল BOM** (`merch_style_boms` → `merch_style_bom_versions` → `merch_style_bom_lines`): indent ভরা হয় অর্ডারের স্টাইলের সর্বশেষ **অনুমোদিত** ভার্সন থেকে। লাইনের `category` থেকে MAIN_FABRIC বাদ যায়, আর আসে consumption, per_gmt, conversion, allow_pct, apply_addl আর ভার্সনের `addl_pct`।
- **Material Procurement-এর master** (`mp_items` kind TRIMS, `mp_suppliers`): BOM লাইনের item code বা নাম মিলিয়ে আইটেম ধরা হয়। লাইনের supplier লেখাটা নাম, short name বা code-এর সাথে মিলিয়ে সাপ্লায়ার ধরা হয়। PO-র Supplier/Spinner আর Item Info-ও এখান থেকে আসে।
- **Order** (`merch_orders`, CONFIRMED বা IN_PRODUCTION) আর **Buyer Office**: Buyer A/C। Requisition List বায়ার ধরে এটাই দেখায়।
- **Inventory-র `stores`:** PO-র Delivery To (Trims & accessories store ইত্যাদি)।
- **কোম্পানি:** Beneficiary আর Billed To-তে শুধু কলারের নিজের কোম্পানি।
- **স্টক:** finalize করা MRR-এর লাইন বাদ finalize করা ফেরত চালানের লাইন (T6) = ট্রিমসের স্টক। `AccRequisitionApiController::stockOf()` এখান থেকে পড়ে। Trims store তৈরি হলে issue-ও বাদ যাবে।
- **Accounting:** শুধু master আছে (`acc_accounts` ইত্যাদি), ভাউচার টেবিল নেই। তাই Finalize MRR এখনো খসড়া ভাউচার তৈরি করে না।
  - Voucher Type-এর তালিকা `AccPaymentMode::VOUCHER_TYPES` থেকে আসে (BPV, BRV, CPV, CRV, CV, BV, JV)। Brickland-এর BILLV এখানে BV।
- **USD রেট:** `tx_exchange_rates` (TradeExport) থেকে, OB তারিখে বা তার আগে সর্বশেষ USD রেট। T7-এর "1 USD = BDT"-এর ডিফল্ট এখান থেকে আসে।
- **ফাইল:** `App\Shared\Files\Actions\StoreFileAttachment` (শুধু JPEG/PNG/WebP/HEIC/PDF, ১০ MB পর্যন্ত)। ডাউনলোড `/api/files/{id}/download`।
- **Master:** `colours`, `fabric_types`, `fiber_compositions`, `merch_orders` থেকে T7-এর Color, Fabric Type, Composition আর Style/Job/Order।
- **স্টাইলের ফ্যাব্রিক** (`merch_style_fabrics`, source PURCHASED/MANUFACTURED): T9 অর্ডার বাছলে শুধু PURCHASED ফ্যাব্রিক দিয়ে লাইন ভরে।

## ৪. ডাটা মডেল (Entity ও Field)

**T1 Accessories Purchase Indent**:
- **`merch_acc_requisitions`:** req_no (`PRQ-ACC-YYYYMM####`), req_date, buyer_office_id, order_id, supplier_id, addition_for, bom_version_id, remarks, status, submitted_at, decided_at, decided_by, created_by।
- **`merch_acc_requisition_lines`:** bom_line_id, item_id, item_code, description, uom, required_qty, stock_qty, short_qty, required_date, supplier_id, remarks, sort_order।
  - নিজের সাপ্লায়ার ছাড়া লাইন হেডারের সাপ্লায়ার পায়।
- **Model:** `AccRequisition` (STATUSES, EDITABLE = DRAFT/REJECTED, `isEditable()`) আর `AccRequisitionLine`।
- **T8:** একই টেবিলে `dummy` ফ্ল্যাগ (migration `2026_11_27_000100`)। ডামি-অর্ডার indent-এর নম্বর `PRQ-DUM-YYYYMM####`।

**T2 Accessories Purchase Order**:
- **`merch_acc_pos`:**
  - Master Data: po_no (`PO-ACC-YYYYMM####`), po_date, supplier_id, source (LOCAL/IMPORT), requisition_id (Against PR), beneficiary_company_id, billed_to_company_id, delivery_store_id, is_online, same_as_company।
  - Terms & Condition: inco_term, place_of_delivery, payment_method, currency, conv_rate, payment_term, mode_of_shipment, delivery_date, cash_supplier, other_term, remarks।
  - অবস্থা: revision, receive_status (PENDING/PARTIAL/RECEIVED), created_by।
- **`merch_acc_po_lines`:** item_id, requisition_id, requisition_line_id, specification, uom, qty, unit_price, note, sort_order।
- **Model:** `AccPurchaseOrder` (তালিকা: SOURCES, INCO_TERMS, PAYMENT_METHODS, CURRENCIES, PAYMENT_TERMS, SHIPMENT_MODES, RECEIVE_STATUSES; `isEditable()` = Pending) আর `AccPurchaseOrderLine`।

**T3 Accessories Receipt Report**:
- **`merch_proc_cost_heads`** (T4-এর টেবিল, T3-এর সাথে তৈরি; T4 স্ক্রিন এর ওপর বানানো): code, name, landed, vat_applicable, expense_ledger_id, sort_order, active।
  - কোম্পানি প্রথমবার খুললে Brickland-এর ৫টা head তৈরি হয় (`MerchandiseDefaults::ensureCostHeads`)।
  - CARRIAGE, LC-CHARGE, INSURANCE আর LOADING: landed, VAT ছাড়া। CNF: landed, VAT-সহ।
- **`merch_acc_mrrs`:**
  - Master Data: mrr_no (`MRR-ACC-YYYYMM####`), mrr_type (PO/PI/EXCHANGEABLE), unit_company_id, supplier_id, store_id, mrr_date, payment_method, source_type, challan_no, challan_date, currency, conv_rate, po_id, requisition_id, requisition_type, remarks।
  - কর্তন: discount_pct, discount_amount, vat_pct, tds_pct, vds_pct।
  - বিল (সেভে হিসাব হয়): goods_value, other_cost, landed_cost, vat_base, vat, tds, vds, net_payable।
  - অবস্থা: voucher_no, status (DRAFT/FINALIZED), finalized_at, finalized_by।
- **`merch_acc_mrr_lines`:** item_id, po_line_id, description, batch, uom, qty, rate, amount, landed_amount।
- **`merch_acc_mrr_costs`:** cost_head_id, landed, vat_applicable (head-এর তখনকার অবস্থা), amount, remarks।
- **Model:** `ProcCostHead`, `AccReceipt` (TYPES, STATUSES, REQUISITION_TYPES = Brickland-এর ২২টা store requisition type), `AccReceiptLine`, `AccReceiptCost`।

**T5 Procurement Posting Setup**:
- **`merch_proc_posting_setups`:** unit_company_id (প্রতি কোম্পানিতে একটা), voucher_type, inventory_ledger_id (Dr), vat_ledger_id (Dr), payable_ledger_id (Cr), tds_ledger_id (Cr), vds_ledger_id (Cr), active।
- **Model:** `ProcPostingSetup` (LEDGERS)।

**T6 Accessories Supplier Return Challan**:
- **`merch_acc_returns`:** challan_no (`RTN-ACC-YYYYMM####`), return_type (REJECTED/EXCESS/SHORT_SHIPPED/OFF_SHADE/SAMPLE_RETURN/OTHER), unit_company_id, supplier_id, mrr_id, challan_date, store_id, return_by (ব্যবহারকারী), gate_pass_no, vehicle_no, driver_name, remarks, status (DRAFT/FINALIZED), finalized_at, finalized_by।
- **`merch_acc_return_lines`:** mrr_line_id, item_id (MRR লাইন থেকে), description, uom, qty, reason।
- **Model:** `AccReturn` (TYPES), `AccReturnLine`।

**T7 Purchased Fabric Opening Balance**:
- **`merch_pfab_obs`:** ob_no (`PFOB-YYYYMM####`), unit_company_id, store_id, ob_date, order_id, currency, conv_rate, usd_bdt, source (PHYSICAL_COUNT/LEGACY_MIGRATION/STOCK_TAKE/OTHER), reference_no, attachment, attachment_file_id, remarks, status (DRAFT/SUBMITTED/APPROVED/POSTED/LOCKED)।
- **`merch_pfab_ob_lines`:** category, description, fabric_item, colour_id, fabric_type_id, fabric_group, composition_id, gsm, dia, dia_uom, dia_type, batch_no, rolls, roll_nos, uom, qty, rate, amount (OB মুদ্রায়), cost_bdt।
- **`merch_pfab_ob_events`:** action, from_status, to_status, user_id, remarks, acted_at (Approval History)।
- **`merch_pfab_ledger`** (purchased-fabric stock ledger, পরের ফ্যাব্রিক স্ক্রিনগুলোও এখানে লিখবে): store_id, source (OB), source_id, source_line_id, txn_date, batch_no, fabric_item, description, colour_id, fabric_type_id, composition_id, uom, qty, cost_bdt।
- **Model:** `FabricOpeningBalance` (STATUSES, SOURCES, CATEGORIES, GROUPS, DIA_UOMS, DIA_TYPES, UOMS), `FabricOpeningBalanceLine`, `FabricOpeningBalanceEvent`, `PurchasedFabricLedger`।

**T9 Purchased Fabric Indent**:
- **`merch_pfab_indents`:** req_no (`PRQ-FAB-YYYYMM####`), req_date, buyer_office_id, order_id, supplier_id, ship_date, gsm, dia, remarks, status, submitted_at, decided_at, decided_by।
- **`merch_pfab_indent_lines`:** style_fabric_id, fabric_type_id, description, construction, composition_id, colour_id, qty, uom (KG/YDS/MTR/LBS/PCS), gsm, dia, supplier_id, required_date, remarks।
- **Model:** `PurchasedFabricIndent` (STATUSES, EDITABLE, UOMS), `PurchasedFabricIndentLine`।

**T10 Purchased Fabric Purchase Order**:
- **`merch_pfab_pos`:** T2-এর সব হেডার ফিল্ড (po_no `PO-FAB-YYYYMM####`) আর po_group।
  - কর্তন: discount_pct, discount_amount, vat_pct, tds_pct, vds_pct, adjustment, adjustment_note।
  - অঙ্ক (সেভে হিসাব হয়): po_value, other_cost, vat, tds, vds, net_amount।
  - অবস্থা: revision, receive_status।
- **`merch_pfab_po_lines`:** indent_id, indent_line_id, আর indent লাইনের ফ্যাব্রিকের কপি (fabric_type_id, description, construction, composition_id, colour_id, gsm, dia, uom); qty, rate, amount, remarks।
- **`merch_pfab_po_costs`:** cost_head_id, vat_applicable (head-এর তখনকার অবস্থা), amount, remarks।
- **Model:** `PurchasedFabricPo`, `PurchasedFabricPoLine`, `PurchasedFabricPoCost`।

**T11 Purchased Fabric Receipt Report**:
- **`merch_pfab_mrrs`:** mrr_no (`MRR-IMF-YYYYMM####`), unit_company_id, supplier_id, store_id, po_id, mrr_date, payment_method, source_type, challan_no, challan_date, currency, conv_rate।
  - আমদানির ঘর: lc_no, lc_date, bill_entry_no, bill_entry_date; remarks।
  - কর্তন: discount_pct, discount_amount, vat_pct, tds_pct, vds_pct।
  - অঙ্ক (সেভে): goods_value, other_cost, landed_cost, vat_base, vat, tds, vds, net_amount, net_payable; voucher_no।
  - অবস্থা: status (DRAFT/FINALIZED), finalized_at, finalized_by।
- **`merch_pfab_mrr_lines`:** po_line_id, PO লাইনের ফ্যাব্রিকের কপি (fabric_type_id, description, composition_id, colour_id, gsm, dia, uom), batch_no, rolls, receive_qty, reject_qty, accept_qty, rate, amount, landed_amount, reject_reason।
- **`merch_pfab_mrr_costs`:** cost_head_id, landed আর vat_applicable (head-এর তখনকার অবস্থা), amount, remarks।
- **Model:** `PurchasedFabricMrr` (শুধু DRAFT এডিট করা যায়), `PurchasedFabricMrrLine`, `PurchasedFabricMrrCost`। Finalize করলে `merch_pfab_ledger`-এ source `MRR`।

**T12 Fabric Supplier Return Challan**:
- **`merch_pfab_returns`:** T6-এর হেডারের মতো (challan_no `RTN-FAB-YYYYMM####`, return_type, unit_company_id, supplier_id, mrr_id → `merch_pfab_mrrs`, challan_date, store_id, return_by, gate_pass_no, vehicle_no, driver_name, remarks, status, finalized_at/by)।
- **`merch_pfab_return_lines`:** mrr_line_id (→ `merch_pfab_mrr_lines`, free লাইনে খালি), description, uom, qty, reason।
- **Model:** `FabricReturn`, `FabricReturnLine` (ধরন `AccReturn::TYPES`)। Finalize করলে `merch_pfab_ledger`-এ source `RTN`, ঋণাত্মক qty আর cost।

**T13 Finished Fabric Opening Balance**:
- **`merch_ffab_obs`:** ob_no (`FFOB-YYYYMM####`), store_id, ob_date, order_id, currency (লেখা, ডিফল্ট USD), conv_rate, usd_bdt। Company আর workflow নেই, কারণ Brickland-এ নেই।
- **`merch_ffab_ob_lines`:** category (লেখা, ডিফল্ট "Finish Fabric"), description, fabric_item, colour (লেখা), fabric_type_id, fabric_group, composition_id, gsm, dia, dia_uom, dia_type, batch_no, rolls, uom, qty, rate, cost_bdt।
- **Model:** `FinishedFabricOpeningBalance`, `FinishedFabricOpeningBalanceLine`। তালিকার মান T7-এর `FabricOpeningBalance`-এর ধ্রুবক থেকে।

**T14–T19 ছয়টা Closing** (Purchased Fabric, Finished Fabric, Trims Stock, Batch Fabric Stock, Grey Fabric Leftover, Finished Fabric Leftover):
- **`merch_stock_closings`:** kind (`purchased-fabric` / `finished-fabric` / `trims` / `batch-fabric` / `grey-leftover` / `finished-leftover`), period (`YYYY-MM`), status (OPEN/CLOSED), checked_at, exceptions, closed_at, closed_by, remarks। প্রতি kind আর মাসে একটা সারি।
- **`merch_stock_closing_exceptions`:** closing_id, document, doc_no, doc_date, status। প্রতিবার Month Close চালালে নতুন করে তৈরি হয়।
- **Model:** `StockClosing`, `StockClosingException`। নিয়ম `Support/StockPeriods`-এ।

## ৫. Workflow / Status ও Business Rules

**T1 Accessories Purchase Indent:**
- **স্ট্যাটাসের প্রবাহ:** Draft → **Submit** → Submitted → **Approve** (নিশ্চিতকরণসহ) → Approved, অথবা **Reject** → Rejected → **Reopen** → Draft। Closed স্ট্যাটাস রাখা আছে।
- **বদলানো আর মোছা:** শুধু Draft বা Rejected indent বদলানো বা মোছা যায়। অন্য অবস্থায় বার্তা আসে: "This requisition is Submitted — it can no longer be changed or removed."
- **প্রয়োজনীয় পরিমাণের হিসাব:** order qty × (consumption ÷ per_gmt) × (1 + allow% + addl%, apply_addl হলে) ÷ conversion, তিন দশমিক পর্যন্ত। Short = required − stock (কখনো শূন্যের নিচে না)।
- **যাচাই:**
  - Req Date আর Job/Order # বাধ্যতামূলক।
  - কমপক্ষে একটা আইটেম লাগবে।
  - প্রতি সারিতে আইটেম বা বিবরণ থাকতে হবে, আর পরিমাণ শূন্যের বেশি হতে হবে ("Row n: …")।
- **একই আইটেম দুবার:** একই অর্ডারের নিয়মিত (Addition For ফাঁকা) indent-এ যে আইটেম আগেই আছে, সেটা আবার তোলা যায় না। এখানে Rejected indent ধরা হয় না। তবে "Addition For" দিলে আবার তোলা যায়। addition indent কখনো নিয়মিত indent-কে আটকায় না।
- **নম্বর:** advisory lock-এর ভেতরে মাস অনুযায়ী ক্রমিক নম্বর দেওয়া হয়।

**T8 Dummy Order Accessories Indent:**
- **অর্ডার:** T1-এর মতোই requisition, তবে বাতিল ছাড়া যেকোনো অবস্থার অর্ডারে তোলা যায়, কনফার্ম না হলেও। T1-এ যেমন শুধু Confirmed বা In Production।
- **আলাদা রাখা:** নিয়মিত আর ডামি indent-এর তালিকা আলাদা (`?dummy=1`)। "একই আইটেম দুবার"-এর চেক নিজের ধরনের মধ্যে হয়, তাই ডামি indent পরে আসা নিয়মিত indent-কে আটকায় না।
- **বাকি সব T1-এর মতো:** Submit / Approve / Reject / Reopen, এডিটের নিয়ম আর হেডারের সাপ্লায়ার। অনুমোদিত ডামি indent থেকে T2-তে PO দেওয়া যায়।
- **BOM endpoint:** এখন প্রতি লাইনে BOM-এর `rate` আর `bom_remarks`-ও আসে (Order BOM টেবিলের জন্য)।

**T2 Accessories Purchase Order:**
- **কোন PR-এর বিপরীতে অর্ডার:** শুধু **অনুমোদিত** PR-এর বিপরীতে PO দেওয়া যায় (Against PR বা লাইনের মাধ্যমে)। নইলে বার্তা আসে: "Only an approved requisition can be ordered against."
- **PR-এর লাইন কতটা বাকি:** required − অন্য সব PO-তে ওই লাইনের মোট qty। PO নিজের qty নিজের বিপরীতে গোনে না, তাই এডিট করে বাকির পুরোটা পর্যন্ত বাড়ানো যায়।
  - একই PO-র দুই সারিতে একই PR লাইন থাকলে দুটো যোগ করে ধরা হয়।
  - বেশি হলে বার্তা আসে: "Row n: only 8 is left to order on requisition PRQ-…"।
- **Requisition List:** বায়ার ধরে সেই অনুমোদিত PR-গুলো দেখায় যাদের কিছু বাকি আছে, যতক্ষণ না সবটা অর্ডার হয়।
  - Buyer A/C ছাড়া PR এখানে আসে না, Brickland-এর মতোই।
  - বায়ার না বাছা পর্যন্ত PR-এর তালিকা পড়া হয় না (`?buyer=`)।
- **PR থেকে PO-তে আনা ("Add to PO"):**
  - PR-এ যা বাকি সেটা লাইন হিসেবে আসে। আইটেম ছাড়া লাইনের বিবরণ যায় Specification-এ।
  - সাপ্লায়ার আসে PR-এর হেডার থেকে, না থাকলে লাইনগুলোর একমাত্র সাপ্লায়ার।
  - ডেলিভারির তারিখ হয় PR-এর সবচেয়ে আগের required date।
  - শুধু Against PR বাছলে লাইন ভরে না, Brickland-এর মতোই।
- **যাচাই:**
  - বাধ্যতামূলক: Supplier/Spinner, PO Date, Source, Payment Method, Currency, Payment Term, Mode of Shipment আর Delivery Date ("… is required.")।
  - Delivery Date PO Date-এর আগে হতে পারে না।
  - লাইন ছাড়া সেভ হয় না: "A purchase order with no lines is not a purchase order."।
  - প্রতি সারিতে আইটেম লাগবে, আর qty শূন্যের বেশি হতে হবে।
- **Same as Company!:** চেক দিলে Billed To-তে Beneficiary বসে।
- **Currency:** BDT হলে Conv. Rate সবসময় ১। UOM না দিলে আইটেমের UOM বসে।
- **রিভিশন আর লক:**
  - প্রতিবার সেভ করলে রিভিশন বাড়ে। Records-এ দেখায় "Original" বা "Revised (n)"।
  - কিছু রিসিভ হয়ে গেলে (Partial/Received) এখানে আর বদলানো বা মোছা যায় না: "…revise it instead."।
  - Pending PO মুছলে তার PR আবার অর্ডারযোগ্য হয়।
  - কোনো রিসিপ্ট (draft হলেও) PO-র লাইন ধরে রাখলে সেই PO আর বদলানো বা মোছা যায় না: "This order has a receipt against it…"।

**T3 Accessories Receipt Report:**
- **বিলের হিসাব (প্রতিবার সেভে):**
  - Goods = Σ qty × rate।
  - Discount = Discount % > ০ হলে goods × %, নইলে Discount Amount (goods-এর বেশি না)।
  - Landed Cost = goods − discount + landed other cost।
  - VAT Base = goods − discount + VAT-যুক্ত other cost। VAT = base × VAT %।
  - Supplier TDS = (goods − discount + সব other cost) × TDS %, VAT বাদে।
  - Supplier VDS = VAT base × VDS %, তবে কখনো VAT-এর বেশি না।
  - Net Payable = goods − discount + other cost + VAT − TDS − VDS।
  - প্রতিটা লাইনের landed অংশ মূল্যের অনুপাতে ভাগ হয়, মূল্য শূন্য হলে পরিমাণের অনুপাতে।
- **PO-র বাকি:** ordered − অন্য সব রিসিপ্টে (draft + final) ওই লাইনের qty। বেশি হলে বার্তা: "Row n: only 40 is left to receive on PO …"। একই PO লাইন দুই সারিতে থাকলে যোগ করে ধরা হয়।
- **অন্য সাপ্লায়ারের PO** (হেডারে বা লাইনে) দেওয়া যায় না।
- **যাচাই:**
  - বাধ্যতামূলক দশটা ফিল্ড: Company, Supplier, Receiving Store, Date of Receive, Payment Method, Source Type, Challan No, Challan Date, Currency, MRR Type।
  - Challan Date রিসিভের তারিখের পরে হতে পারে না।
  - লাইন ছাড়া সেভ হয় না।
  - Other cost-এ head আর শূন্যের বেশি অঙ্ক লাগবে।
- **Receive List:** সাপ্লায়ার ধরে সেই PO-গুলো দেখায় যেগুলো Received হয়নি আর কিছু বাকি আছে। সাপ্লায়ার না বাছা পর্যন্ত PO পড়া হয় না।
- **PO থেকে আনা ("Receive"):** সাপ্লায়ার, স্টোর (PO-র Delivery To), পেমেন্ট, সোর্স, মুদ্রা আর PR আসে PO থেকে। লাইনে আসে বাকি qty আর PO-র unit price।
- **Finalize MRR** (নিশ্চিতকরণসহ):
  - রিসিপ্ট লক হয়, তারপর আর বদলানো বা মোছা যায় না।
  - মাল স্টকে ওঠে, landed cost-এ।
  - PO-র Receive Status হয় Partial বা Received, শুধু finalize করা রিসিপ্ট ধরে।
  - Received PO আর তালিকা বা অপশনে আসে না।
  - **খসড়া ভাউচার এখনো তৈরি হয় না।** Accounting-এ ভাউচার টেবিল আর T5 Posting Setup তৈরি হলে যোগ হবে।
- **Draft মোছা:** draft রিসিপ্ট মুছলে PO আবার রিসিভযোগ্য হয়।

**T4 Procurement Cost Head:**
- **Code:** upper-case-এ রাখা হয়, আর কোম্পানির মধ্যে একক। বাধ্যতামূলক: "Code is required.", "Cost Head is required."।
- **Expense Ledger:** landed না হলে লাগবে: "Expense Ledger is required when the head is not a landed cost."। লেজার আসে Chart of Accounts-এর postable আর active একাউন্ট থেকে।
- **রিসিপ্টের ফ্ল্যাগ:** রিসিপ্ট সেভের সময় head-এর দুটো ফ্ল্যাগ তার খরচের লাইনে কপি করে রাখে। তাই head বদলালে আগের রিসিপ্ট বদলায় না।
- **মোছা:** কোনো রিসিপ্টে ব্যবহার হওয়া head মোছা যায় না (বন্ধ করা যায়)। বন্ধ head রিসিপ্টের অপশনে আসে না।

**T5 Procurement Posting Setup:**
- **প্রতি কোম্পানিতে একটা setup।** দ্বিতীয়টা দিলে বার্তা: "This company already has a posting setup — edit that one instead."।
- **বাধ্যতামূলক:** Company, Voucher Type আর পাঁচটা লেজার ("Inventory (Dr) is required." ইত্যাদি)। লেজার postable হতে হবে, group heading নেওয়া যায় না।
- **Finalize-এর শর্ত:** কোম্পানির সক্রিয় setup না থাকলে MRR finalize করা যায় না। বার্তা: "Company … has no active procurement posting setup — the receipt is not finalised rather than posted somewhere by guess."। এটা Brickland-এর নিয়ম।
- **মোছা:** কোনো finalize করা রিসিপ্ট যে setup-এর ওপর দাঁড়িয়ে, সেটা মোছা যায় না (বন্ধ করা যায়)।
- **খসড়া ভাউচার এখনো তৈরি হয় না:** Accounting-এ ভাউচার টেবিল তৈরি হলে Finalize এই setup-এর লেজারে ভাউচার তুলবে।
  - Dr: Inventory (landed cost) আর VAT Receivable।
  - Cr: Supplier Payable (net), TDS আর VDS।

**T6 Accessories Supplier Return Challan:**
- **MRR List:** finalize করা MRR, যেগুলোর কিছু ফেরত দেওয়া বাকি। Customer (সাপ্লায়ার) আর সার্চ দিয়ে খোঁজা যায়।
- **MRR থেকে লাইন ভরা:** MRR বাছলে কোম্পানি, সাপ্লায়ার আর স্টোর আসে। লাইনে আসে বিবরণ, UOM আর ফেরতযোগ্য পরিমাণ, পরে ফেরতের মতো ঠিক করা হয়।
- **যাচাই:**
  - বাধ্যতামূলক: Return Type (ডিফল্ট Rejected), Company, Supplier, MRR No, Challan Date, Returning Store, Return By।
  - শুধু finalize করা MRR নেওয়া যায়, আর MRR-এর সাপ্লায়ার আর চালানের সাপ্লায়ার এক হতে হবে।
  - চালানের তারিখ MRR-এর আগে হতে পারে না।
  - লাইন ছাড়া সেভ হয় না। প্রতি সারিতে বিবরণ আর শূন্যের বেশি পরিমাণ লাগবে।
- **ফেরতের সীমা:** MRR লাইনে রিসিভ − অন্য সব চালানে (draft + final) ফেরত। বেশি হলে বার্তা: "Row n: only 70 is left to return on receipt …"। হাতে লেখা লাইনও (MRR লাইন ছাড়া) দেওয়া যায়, সেটা স্টকে প্রভাব ফেলে না।
- **Finalize** (নিশ্চিতকরণসহ): চালান লক হয় আর ফেরত মাল স্টক থেকে বাদ যায়। draft চালান স্টক কমায় না।
- পুরোটা ফেরত হয়ে গেলে MRR আর MRR List-এ আসে না।

**T7 Purchased Fabric Opening Balance:**
- **মুদ্রা আর BDT cost:**
  - BDT: amount যেমন আছে তেমনই।
  - USD: amount × 1 USD = BDT।
  - অন্য মুদ্রা: amount × Conversion Rate (OB মুদ্রার প্রতি এককে USD) × 1 USD = BDT।
  - USD হলে Conversion Rate সবসময় ১। BDT হলে USD রেট লাগে না।
- **যাচাই:**
  - বাধ্যতামূলক: Company, Receiving Store, OB Date, Currency, Source। BDT না হলে 1 USD = BDT লাগবে; USD/BDT ছাড়া অন্য মুদ্রায় Conversion Rate লাগবে।
  - সারি ছাড়া সেভ হয় না।
  - প্রতি সারিতে Category, Fabric Item, Fabric Group, UOM আর শূন্যের বেশি OB Qty লাগবে। Add Row Brickland-এর মতোই প্রথম যে ফিল্ড বাকি তার নাম বলে ("Fabric Item is needed before the row can be added.")।
- **Batch:** এক OB-তে একবার, আর একই স্টোরে সব OB মিলিয়ে একবার (বড়-ছোট হাতের অক্ষর এক ধরা হয়)।
- **Workflow:**
  - Submit (manage) → Approve বা Reject (approve; Reject করলে Draft-এ ফেরে) → Post (manage) → Lock (approve)। Posted থাকলে Unpost করলে Approved-এ ফেরে।
  - প্রতিটা ধাপ (Create সহ) লগ হয়: কে করল, কোন অবস্থা থেকে কোন অবস্থায়, সময় আর মন্তব্য।
  - শুধু Draft এডিট, মোছা বা attach করা যায়।
- **Post:** প্রতিটা ব্যাচ purchased-fabric stock ledger-এ Receiving Store-এ, BDT cost-এ ওঠে। Unpost করলে লেজার থেকে নামে। Locked হলে আর Unpost করা যায় না।

**T9 Purchased Fabric Indent:**
- **অর্ডার বাছলে:** Buyer A/C, Ship Date, আর স্টাইলের **PURCHASED** ফ্যাব্রিক লাইন হিসেবে আসে:
  - Type, বিবরণ (না থাকলে fabric code), Construction = fabric code, Composition।
  - GSM = weight_from, DIA = width।
  - সাপ্লায়ার লেখা নাম, short name বা code দিয়ে মেলানো হয়। UOM ডিফল্ট Kg।
  - কেনা ফ্যাব্রিক না থাকলে নোটিশ আসে, তখন "Add item" দিয়ে হাতে যোগ করতে হয়।
- **অর্ডার:** শুধু Confirmed বা In Production।
- **যাচাই:**
  - Req Date আর অর্ডার বাধ্যতামূলক। লাইন ছাড়া সেভ হয় না: "A requisition with no items is not a requisition."।
  - প্রতি সারিতে Type বা বিবরণ, শূন্যের বেশি Qty আর UOM লাগবে।
- **একই ফ্যাব্রিক দুবার:** ফ্যাব্রিক মানে Type, Composition, Color আর বিবরণ একসাথে। একই indent-এ দুবার, বা একই অর্ডারের অন্য (রিজেক্টেড নয়) indent-এ আগে থাকলে আটকায়।
- **স্বয়ংক্রিয় মান:** নিজের সাপ্লায়ার ছাড়া লাইন হেডারের সাপ্লায়ার পায়। Ship Date না দিলে অর্ডার থেকে আসে।
- **Workflow** T1-এর মতো: Submit / Approve (নিশ্চিতকরণ) / Reject / Reopen, আর শুধু Draft বা Rejected এডিট করা যায়।

**T10 Purchased Fabric Purchase Order:**
- **লাইন:** প্রতিটা লাইন একটা অনুমোদিত PRQ-FAB লাইন অর্ডার করে, আর তার খোলা পরিমাণের বেশি হতে পারে না। খোলা পরিমাণ = indent লাইনের qty − অন্য সব PO-তে ওই লাইনের মোট qty।
  - PR লাইন ছাড়া সারি: "Row n: pick the approved requisition line it orders."।
  - বেশি হলে: "Row n: only 200 is left to order on requisition …"।
  - লাইনের ফ্যাব্রিকের বিবরণ indent লাইন থেকে কপি হয়। শুধু PO Qty, Rate আর Remarks বদলানো যায়।
- **Approved PR List:** অনুমোদিত indent, যার কিছু খোলা আছে। বাছলে খোলা লাইনগুলো যোগ হয় (আগে থাকা লাইন বাদ)। Against PR, সাপ্লায়ার আর ডেলিভারির তারিখ খালি থাকলে ভরে।
- **"Add to PO":** খালি লাইন দেয়। সেখানে খোলা PR লাইন বাছতে হয় ("PRQ-FAB-… · বিবরণ")।
- **অঙ্ক** (সেভে):
  - Net PO Amount = PO Value − Discount + Other Cost + VAT − TDS − VDS ± Adjustment।
  - VAT base = PO Value − Discount + VAT-যুক্ত other cost।
  - TDS = (PO Value − Discount + সব other cost) × %।
  - VDS = VAT base × %, তবে VAT-এর বেশি না।
- **বাকি সব T2-এর মতো:** বাধ্যতামূলক ফিল্ড, Same as Company, রিভিশন, আর রিসিভ হলে লক।
- **Requisition List:** বায়ার ধরে অনুমোদিত fabric indent, যেগুলোর কিছু বাকি (T2-এর কম্পোনেন্ট আবার ব্যবহার করা)।
- **রিসিভ হলে লক:** কোনো MRR (খসড়া হলেও) PO-র লাইন রিসিভ করলে PO আর এডিট বা মোছা যায় না। Records-এ MRR(s) কলামে নম্বর দেখায়।

**T11 Purchased Fabric Receipt Report:**
- **Receive List:** অপেক্ষমাণ বা আংশিক রিসিভ হওয়া PO-FAB, যার কিছু খোলা আছে। বাছলে সাপ্লায়ার, স্টোর (PO-র Delivery To), Payment Method, Source, Currency আর খোলা লাইন ভরে।
- **লাইন:** প্রতিটা লাইন একটা PO লাইন রিসিভ করে। Accept = Receive − Reject।
  - PO লাইন ছাড়া সারি: "Row n: pick the PO line it receives."।
  - Receive ০ বা খালি: "Row n: Receive Qty must be above zero."।
  - Reject > Receive: "Row n: Reject Qty cannot be more than Receive Qty."।
  - Accept খোলা পরিমাণের বেশি: "Row n: only 650 is left to receive on PO …"। খোলা = PO qty − অন্য সব MRR-এর accept।
  - অন্য সাপ্লায়ারের PO-র লাইন আটকায়।
- **অঙ্ক** (সেভে):
  - Goods = Σ accept × rate; Landed = Goods − Discount + landed-যুক্ত other cost।
  - VAT base = Goods − Discount + VAT-যুক্ত other cost; TDS = (Goods − Discount + সব other cost) × %; VDS = VAT base × %, তবে VAT-এর বেশি না।
  - Net Amount = Goods − Discount + Other; Net Payable = Net + VAT − TDS − VDS।
  - প্রতিটা ব্যাচের landed_amount = Landed × ব্যাচের amount / Goods (মূল্য না থাকলে accept অনুপাতে)।
- **Finalize MRR:** সক্রিয় T5 Posting Setup লাগে (নইলে T3-এর বার্তা)। লক হয়; প্রতিটা ব্যাচ purchased-fabric ledger-এ (qty = accept, cost_bdt = landed × conv rate); PO-র Receive Status Partial বা Received হয়।
- **Challan Date** Date of Receive-এর পরে হতে পারে না।

**T12 Fabric Supplier Return Challan:**
- **MRR List:** finalize হওয়া Fabric MRR, যার কিছু এখনো ফেরত দেওয়া যায়। বাছলে Company, Supplier, Store আর batch-এর লাইন ভরে। লাইনের বিবরণ হয় "ফ্যাব্রিক · batch", আর qty = accept − আগের সব চালানের ফেরত।
- **নিয়ম** T6-এর মতো, একই বার্তা:
  - চালানের তারিখ MRR-এর আগে হতে পারে না।
  - সাপ্লায়ার MRR-এর সাপ্লায়ারই হতে হবে।
  - বিবরণ বাধ্যতামূলক, Qty > ০।
  - accept-এর বেশি ফেরত হয় না: "Row n: only 600 is left to return on receipt …"।
- **Free লাইন** ("Add returned line"): রাখা যায়, তবে কোনো batch নেই, তাই ledger-এ যায় না।
- **Finalize:** লক হয়। প্রতিটা MRR batch-এর লাইন ledger-এ −qty হিসেবে বসে, খরচ = batch-এর landed ÷ accept × qty × conv rate।
- T11-এর Records-এর Exch/Ret Qty এই লাইনগুলো থেকে আসে।

**T13 Finished Fabric Opening Balance:**
- **Workflow নেই:** Brickland-এ সেভ করলেই রেকর্ড থাকে। Existing ড্রপডাউন দিয়ে লোড করে ঠিক করা বা মোছা যায়।
- **বাধ্যতামূলক:** Receiving Store, OB Date। লাইন না থাকলে: "A opening balance with no lines is not a opening balance."।
- **Add Row:** Brickland-এর ক্রমে প্রথম খালি ঘরের নাম বলে: Fab Description, Fab Item, Fab Type, Fabric Group ("… is needed before the row can be added."), তারপর OB QTY। সার্ভারও একই ক্রমে যাচাই করে ("Row n: Fab Type is needed.")।
- **Batch:** একই OB-তে একই batch দুবার থাকতে পারে না (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)।
- **খরচ:** cost_bdt = qty × OB COST × (BDT হলে ১, নইলে conv × 1USD=BDT)। USD হলে conv ১। USD রেট না দিলে OB তারিখের `TxExchangeRate` বসে।
- **সেভের toast:** "Saved opening balance for 01 Oct 2026 with 2 roll line(s)" (Brickland-এর লেখা)।

**T14–T19 ছয়টা Closing:**
- **Period:** "Jul/2026" বা "2026-07"। অন্য কিছু হলে: "The month being closed, as Jul/2026 or 2026-07."।
- **Month Close:** মাসের তারিখের খোলা ডকুমেন্ট খোঁজে:
  - Purchased Fabric: PFOB (Draft/Submitted/Approved), Fabric MRR (Draft), Fabric Return (Draft)।
  - Trims: Accessories MRR (Draft), Accessories Return (Draft)।
  - Finished Fabric: তার একমাত্র ডকুমেন্ট T13-এ workflow নেই, তাই কখনো খোলা থাকে না।
  - Batch Fabric, Grey আর Finished Leftover: এদের ডকুমেন্টের স্ক্রিন (ডাইং, অর্ডার ক্লোজিং) এখনো তৈরি হয়নি, তাই কিছু আটকায় না।
- **খোলা ডকুমেন্ট থাকলে refuse:** "Jul/2026 cannot close: 1 document is still open — see Show Exception."। তবু চেকটা (সময়, সংখ্যা, Remarks) আর exception-এর তালিকা রেকর্ড হয়, status Open।
- **না থাকলে Closed:** Closed At আর Closed By বসে। একই মাস আবার বন্ধ করা যায় না ("Jul/2026 is already closed.")। খোলার কোনো বাটন নেই, Brickland-এও নেই।
- **Closed মাসের guard:** সেই kind-এর ডকুমেন্ট ওই মাসের তারিখে সেভ, finalize, workflow (PFOB) বা Delete (FFOB) করা যায় না: "Jul/2026 is closed for purchased fabric — its documents can no longer be saved or finalised."। guard বসেছে T3, T6, T7, T11, T12, T13-এর controller-এ।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET | `/merchandise/acc-requisitions/options` | accessories.view |
| GET | `/merchandise/acc-requisitions/bom?order_id=` | accessories.view |
| GET / POST | `/merchandise/acc-requisitions` | view / manage |
| GET / PUT / DELETE | `/merchandise/acc-requisitions/{req_no}` | view / manage / manage |
| POST | `/merchandise/acc-requisitions/{req_no}/{submit,approve,reject,reopen}` | submit আর reopen: manage; approve আর reject: approve |
| — | উপরের options, index আর store-এ `dummy=1` / `dummy: true` দিলে ডামি-অর্ডার indent | একই |
| GET | `/merchandise/pfab-indents/options` | accessories.view |
| GET | `/merchandise/pfab-indents/fill?order_id=` | accessories.view |
| GET / POST | `/merchandise/pfab-indents` | view / manage |
| GET / PUT / DELETE | `/merchandise/pfab-indents/{req_no}` | view / manage / manage |
| POST | `/merchandise/pfab-indents/{req_no}/{submit,approve,reject,reopen}` | submit আর reopen: manage; approve আর reject: approve |
| GET | `/merchandise/pfab-pos/options` | accessories.view |
| GET | `/merchandise/pfab-pos/requisitions?buyer=` | accessories.view |
| GET | `/merchandise/pfab-pos/requisitions/{req_no}` | accessories.view |
| GET / POST | `/merchandise/pfab-pos` | view / manage |
| GET / PUT / DELETE | `/merchandise/pfab-pos/{po_no}` | view / manage / manage |
| GET | `/merchandise/pfab-mrrs/options` | accessories.view |
| GET | `/merchandise/pfab-mrrs/receivables/{po_no}` | accessories.view |
| GET / POST | `/merchandise/pfab-mrrs` | view / manage |
| GET / PUT / DELETE | `/merchandise/pfab-mrrs/{mrr_no}` | view / manage / manage |
| POST | `/merchandise/pfab-mrrs/{mrr_no}/finalize` | manage |
| GET | `/merchandise/pfab-returns/options` | accessories.view |
| GET | `/merchandise/pfab-returns/receipts/{mrr_no}` | accessories.view |
| GET / POST | `/merchandise/pfab-returns` | view / manage |
| GET / PUT / DELETE | `/merchandise/pfab-returns/{challan_no}` | view / manage / manage |
| POST | `/merchandise/pfab-returns/{challan_no}/finalize` | manage |
| GET | `/merchandise/ffab-obs/options` | accessories.view |
| GET / POST | `/merchandise/ffab-obs` | view / manage |
| GET / PUT / DELETE | `/merchandise/ffab-obs/{ob_no}` | view / manage / manage |
| GET / POST (Month Close) | `/merchandise/closings/{kind}` | view / manage |
| GET | `/merchandise/closings/{kind}/{YYYY-MM}/exceptions` | accessories.view |
| GET | `/merchandise/acc-purchase-orders/options` | accessories.view |
| GET | `/merchandise/acc-purchase-orders/requisitions?buyer=` | accessories.view |
| GET | `/merchandise/acc-purchase-orders/requisitions/{req_no}` | accessories.view |
| GET / POST | `/merchandise/acc-purchase-orders` | view / manage |
| GET / PUT / DELETE | `/merchandise/acc-purchase-orders/{po_no}` | view / manage / manage |
| GET | `/merchandise/acc-receipts/options` | accessories.view |
| GET | `/merchandise/acc-receipts/receivables?supplier=` | accessories.view |
| GET | `/merchandise/acc-receipts/receivables/{po_no}` | accessories.view |
| GET / POST | `/merchandise/acc-receipts` | view / manage |
| GET / PUT / DELETE | `/merchandise/acc-receipts/{mrr_no}` | view / manage / manage |
| POST | `/merchandise/acc-receipts/{mrr_no}/finalize` | manage |
| GET | `/merchandise/acc-receipts/ledgers` | accessories.view |
| GET / POST / PATCH / DELETE | `/erp/proc-cost-heads` (screen-kit resource `ProcCostHeadResource`) | accessories.view / manage |
| GET / POST / PATCH / DELETE | `/erp/proc-posting-setups` (screen-kit resource `ProcPostingSetupResource`) | accessories.view / manage |
| GET | `/merchandise/acc-returns/options` | accessories.view |
| GET | `/merchandise/acc-returns/receipts/{mrr_no}` | accessories.view |
| GET / POST | `/merchandise/acc-returns` | view / manage |
| GET / PUT / DELETE | `/merchandise/acc-returns/{challan_no}` | view / manage / manage |
| POST | `/merchandise/acc-returns/{challan_no}/finalize` | manage |
| GET | `/merchandise/fabric-obs/options` | accessories.view |
| GET / POST | `/merchandise/fabric-obs` | view / manage |
| GET / PUT / DELETE | `/merchandise/fabric-obs/{ob_no}` | view / manage / manage |
| GET | `/merchandise/fabric-obs/{ob_no}/history` | accessories.view |
| POST | `/merchandise/fabric-obs/{ob_no}/attachment` (multipart `file`) | manage |
| POST | `/merchandise/fabric-obs/{ob_no}/{submit,approve,reject,post,unpost,lock}` | submit/post/unpost: manage; approve/reject/lock: approve |

- **পারমিশন:** `merchandise.accessories.view`, `merchandise.accessories.manage`, `merchandise.accessories.approve`।
- **রোল:**
  - FACTORY_ADMIN: তিনটাই।
  - FACTORY_MANAGER: view আর approve।
  - STORE_MANAGER: শুধু view।
- **মেনু:** ফোল্ডার `MERCHANDISE.TRIMS_ACCESSORIES`।
  - `MERCHANDISE.ACC_PR` → `/merchandise-desk/accessories-pr` (view/manage/approve)।
  - `MERCHANDISE.ACC_PO` → `/merchandise-desk/accessories-po` (view/manage)।
  - `MERCHANDISE.ACC_MRR` → `/merchandise-desk/accessories-mrr` (view/manage)।
  - `MERCHANDISE.PROC_COST_HEAD` → `/merchandise-desk/procurement-cost-head` (view/manage)।
  - `MERCHANDISE.PROC_POSTING` → `/merchandise-desk/procurement-posting-setup` (view/manage)।
  - `MERCHANDISE.ACC_RETURN` → `/merchandise-desk/accessories-return-challan-to-supplier` (view/manage)।
  - `MERCHANDISE.PFAB_OB` → `/merchandise-desk/purchase-fabric-opening-balance` (view/manage/approve)।
  - `MERCHANDISE.ACC_PR_DUMMY` → `/merchandise-desk/accessories-pr-dummy-ord` (view/manage/approve)।
  - `MERCHANDISE.PFAB_PR` → `/merchandise-desk/purchase-fabric-pr` (view/manage/approve)।
  - `MERCHANDISE.PFAB_PO` → `/merchandise-desk/purchase-fabric-po` (view/manage)।

## ৭. Frontend পেজ (লাইট থিম)

**T1 Accessories Purchase Indent** (`/merchandise-desk/accessories-pr`)-এ তিনটা ট্যাব, ট্যাব বদলালে পাতা রিলোড হয় না:
- **Records:**
  - ফিল্টার: Req Month, Buyer A/C, Job/Order #, Supplier, Status, Req Date from/to।
  - কলাম: Requisition No, Req Date, Addition For, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List, Status।
  - হেডারে "Add New" বাটন, যেটা BOM ট্যাবে নিয়ে যায়।
  - প্রতি সারিতে Print/PDF আর Edit। Draft বা Rejected হলে Delete-ও থাকে।
- **Accessories Booking BOM for PR:**
  - ফিল্টার: Type, Buyer A/C, Job/Order #, Req Type।
  - "PR List" বাটন।
  - অর্ডারের গ্রিড: Buyer Name, Style Description, Job #, Order #, Order Type, Order Qty, Shipment Date, BOM।
  - প্রতি সারিতে **Add PR**।
- **Requisition Entry:**
  - বাঁয়ে **Order List**।
  - ডানে ফর্ম: Existing requisition, Requisition No, Req Date*, Status, Buyer A/C (অর্ডার থেকে আসে, নিষ্ক্রিয়), Job/Order #, **Supplier**, Addition For ("Short Qty"), BOM, Remarks।
  - **Item List**: Item Name, Code, Description, UOM, Required, Stock, Short, Required Date, Supplier, Remarks।
  - বাটন: Back To List, Delete, Clear, Save requisition / Save changes, Submit, Approve (নিশ্চিতকরণ), Reject (নিশ্চিতকরণ), Reopen, Print/PDF।
  - **Purchase Order** বাটন: অনুমোদিত indent হলে T2-র Entry খোলে, PR-এর লাইন ভরা অবস্থায়।

**T2 Accessories Purchase Order** (`/merchandise-desk/accessories-po`)-এ তিনটা ট্যাব:
- **Records:**
  - ফিল্টার: PO No, PR No (লেখা), Supplier, Source, Payment Term, Payment Method, PO Date from/to।
  - কলাম: Receive Status, Revision Status, PO No, PO Date, PR Number(s), MRR(s), Supplier Name, Store, Inco Term, Payment Term, Payment Method, Value।
  - হেডারে "Add New" বাটন।
  - প্রতি সারিতে Edit আর Delete।
- **Purchase Order Entry:**
  - Existing purchase order (বাছলে url হয় `?ref=PO-…`)।
  - **Master Data**: Supplier/Spinner*, PO No [Auto Gen], PO Date*, Source* (Local), Against PR, Beneficiary, Billed To, Delivery To, Is Online?, Same as Company!।
  - **Terms & Condition**: Inco Term (FOB), Place of Delivery, Payment Method* (Lc), Currency* (BDT), Conv. Rate (১), Payment Term*, Mode of Shipment*, Delivery Date*, Cash Supplier, Other Term, Remarks।
  - **Details Data**: Item Info, Specification, UOM, Order Qty, Unit Price, Note, Remove। "Add to PO" দিয়ে খালি লাইন যোগ হয়। নিচে মোট আর মূল্য।
  - বাটন: Delete (নিশ্চিতকরণসহ, সেভ করা Pending PO হলে), Clear, Save purchase order / Save changes।
  - Badge: New, Rev n, অথবা Read only।
- **Requisition List:**
  - বাঁয়ে বায়ারের তালিকা, সার্চ আর সংখ্যাসহ।
  - বায়ার বাছলে ডানে তার অপেক্ষমাণ PR আসে: Req No, Req Date, Addition For, Job No, Order No, Supplier, Item List, Left to Order।
  - প্রতি সারিতে **Add to PO**, যেটা খোলে `?tab=entry&pr=PRQ-…`।
- **কম্পোনেন্ট:** `AccPoEntry`, `AccPoRequisitions`।

**T3 Accessories Receipt Report** (`/merchandise-desk/accessories-mrr`)-এ তিনটা ট্যাব:
- **Records:**
  - রেকর্ড না থাকলে Brickland-এর খালি অবস্থা: "No receipt records yet"।
  - ফিল্টার: MRR No, PO No (লেখা), Supplier, Store, Status, Date from/to।
  - কলাম: MRR No, Date of Receive, MRR Type, Supplier, Store, Challan No, PO No, PR No, Items, Landed Cost, Net Payable, Status।
- **Receipt Entry:**
  - Existing receipt।
  - **Master Data**: Company*, Supplier*, MRR NO, Receiving Store*, Date of Receive*, Payment Method*, Source Type*, Challan No*, Challan Date*, Currency*, Conv. Rate, PO (সাপ্লায়ারের; খালি হলে "No records to choose from yet."), PR, Requisition Type, MRR Type*, Remarks।
  - **Discount, VAT & Deductions at Source**: Discount %, Discount Amount (% দিলে নিষ্ক্রিয়), VAT %, Supplier TDS %, Supplier VDS %, Brickland-এর hint-সহ।
  - **Bill Summary**: আটটা মান ("On save") আর Accounts Voucher ("Raised on finalise")।
  - **Received Items**: Item, Description, Batch, UOM, Qty, Rate, Amount। প্রতি লাইনে PO নম্বর। "Add received line" বাটন।
  - **Other Cost**: Cost Head, Landed?, VAT?, Amount, Remarks। "Add other cost" বাটন।
  - বাটন: Delete, Clear, Save receipt / Save changes, Finalize MRR। Badge: New, Draft বা Finalised।
- **Receive List:**
  - বাঁয়ে "Customer" (Brickland-এর শিরোনাম): সাপ্লায়ার, সার্চ আর সংখ্যাসহ।
  - সাপ্লায়ার বাছলে PO-গুলো আসে: PO No, PO Date, Delivery Date, Store, Receive Status, Ordered, Received, Left to Receive।
  - প্রতি সারিতে **Receive**, যেটা খোলে `?tab=entry&po=PO-…`।
- **কম্পোনেন্ট:** `AccReceiptEntry`, `AccReceivables`।

**T4 Procurement Cost Head** (`/merchandise-desk/procurement-cost-head`), screen kit-এর `masterScreen` দিয়ে:
- **Records:** Code, Cost Head, Landed Cost?, VAT Applicable?, Expense Ledger, Order, Status। প্রতি সারিতে Edit আর "Remove cost head X"। মোছার ডায়ালগ "Remove this cost head?"।
- **New cost head:**
  - Code* ("CARRIAGE"), Cost Head* ("Carriage Inward")।
  - Landed Cost? (চেক করা), VAT Applicable?, দুটোই hint-সহ।
  - Expense Ledger, Display Order, Active? (চেক করা)।
  - উপরে নোট আর নিচে অডিটের লেখা। বাটন Reset আর Save cost head।
- **Edit cost head:** ট্যাব হিসেবে খোলে (`?tab=edit&ref=CNF`), "Rewriting CNF.", সাথে Cancel আর Update cost head।

**T5 Procurement Posting Setup** (`/merchandise-desk/procurement-posting-setup`), `masterScreen` দিয়ে:
- **Records:** Company, Voucher Type, Inventory, VAT Receivable, Supplier Payable, TDS Payable, VDS Payable, Status। রেকর্ড না থাকলে "No posting setup records yet"।
- **New posting setup:**
  - Company*, Voucher Type* ("usually Journal Voucher")।
  - Inventory (Dr)*, VAT Receivable (Dr)*, Supplier Payable (Cr)*, TDS Payable (Cr)*, VDS Payable (Cr)*, প্রতিটা Brickland-এর hint-সহ।
  - Active? (চেক করা)। উপরে নোট "One setup per company…"।
- **Edit posting setup:** ট্যাব হিসেবে খোলে (`?tab=edit&ref=<company>`)।

**T6 Accessories Supplier Return Challan** (`/merchandise-desk/accessories-return-challan-to-supplier`)-এ দুটো ট্যাব:
- **Records:**
  - ফিল্টার: Challan No, MRR No (লেখা), Return Challan Type, Company, Supplier, Store, Challan Date from/to।
  - কলাম: Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store।
  - হেডারে "Add New" বাটন। প্রতি সারিতে Edit আর Delete।
- **Accessories Return Challan to Supplier:**
  - বাঁয়ে **MRR List**: Customer ("-- Select Supplier/Cutomer --"), সার্চ, MRR NO / MRR Date / CHALAN, পাতা ভাগ।
  - ডানে Existing return challan, আর **Master List**: Return Type*, Company*, Supplier*, Challan No [Auto Gen], Challan Date*, MRR No, Returning Store*, Return By*, Gate Pass No, Vehicle No., Driver Name, Remarks।
  - **Details Data**: Description, UOM, Qty, Reason, Remove। "Add returned line" বাটন।
  - বাটন: Back To List, Delete, Clear, Save return challan / Save changes, Finalize। নিচে "* Action Status 41-Create Draft"।
- **কম্পোনেন্ট:** `AccReturnEntry`।

**T7 Purchased Fabric Opening Balance** (`/merchandise-desk/purchase-fabric-opening-balance`)-এ তিনটা ট্যাব:
- **Records:**
  - ফিল্টার: Status, Receiving Store, Source, OB Date from/to।
  - কলাম: OB No, OB Date, Company, Receiving Store, Style/Job/Order, Source, Reference, Currency, Conv Rate, 1USD=BDT, Items, Rolls, Total Qty, Total OB Cost, Status।
  - হেডারে "Add New" বাটন। প্রতি সারিতে Edit আর Delete।
- **Opening Balance Entry:**
  - Existing opening balance।
  - **Header**: OB No [Auto Gen], Status, Company*, Receiving Store*, OB Date*, Style/Job/Order, Currency* (USD), Conversion Rate (১), 1 USD = BDT (সর্বশেষ রেট)।
  - **Source & Reference**: Source* (Physical Stock Count), Reference No, Attachment আর Attach (সেভের পরে; ছবি/PDF), Remarks।
  - **Fabric Details**: উপরে এক ব্যাচের ফর্ম। ফিল্ড: Category, Description, Fabric Item, Color, Fabric Type, Fabric Group, Composition, FIN GSM, FIN DIA, DIA UOM, DIA Type, Batch No, No. of Roll, Roll Nos, OB UOM, OB Qty, OB Rate। বাটন Add Row আর Cancel।
  - নিচে এডিটযোগ্য গ্রিড: Category, Description, Item, Color, Type, Group, Composition, Batch, Roll, Qty, UOM, Rate, Currency, Conversion Rate, Amount, Remove। নিচে মোট (সারি, পরিমাণ, BDT cost)।
  - বাটন: Back To List, Delete, Clear, Save opening balance / Save changes, আর স্ট্যাটাস অনুযায়ী Submit / Approve / Reject / Post / Unpost / Lock। Reject, Post, Unpost আর Lock-এ নিশ্চিতকরণ লাগে।
- **Approval History:** বাঁয়ে ওপেনিং ব্যালান্সের তালিকা, সার্চ আর স্ট্যাটাসসহ। ডানে বাছা OB-র Workflow ধাপ: Step, From, To, By, At, Remarks।
- **কম্পোনেন্ট:** `FabricObEntry`, `FabricObHistory`। Attach যায় `/api/merchandise/fabric-ob-attachment/[balance]` route handler দিয়ে, যাতে ১ MB-র server action সীমায় না আটকায়।

**T8 Dummy Order Accessories Indent** (`/merchandise-desk/accessories-pr-dummy-ord`)-এ চারটা ট্যাব:
- **Records:** অর্ডারের গ্রিড (বাতিল ছাড়া সব)।
  - ফিল্টার: Type, Buyer A/C, Job/Order #, Req Type।
  - কলাম: Buyer Name, Style Description, Job #, Order #, Order Type, Order Qty, Shipment Date।
  - প্রতি সারিতে **View BOM**, যেটা Order BOM ট্যাবে সেই অর্ডারটা বেছে খোলে (`?tab=bom&order=…`)।
- **Requisition Entry (Dummy Order):** T1-এর `AccRequisitionEntry`, `dummy` prop-সহ। শিরোনাম আর hint Brickland-এর। `?order=` দিলে BOM থেকে ভরে যায়।
- **PR List:**
  - ফিল্টার: Req Month, Supplier, Buyer A/C Group, Status, Req / Job / Order No।
  - কলাম: Req No, Req Date, Addition For, Buyer A/C Group, Job No, Order No, Supplier, Item List, Status।
  - Edit চাপলে Entry খোলে। হেডারে "Order List" বাটন, আর Brickland-এর লেখা "View / Edit opens the requisition…"।
- **Order BOM:**
  - বাঁয়ে অর্ডার ("order · job", নিচে "job · BOM" বা "no approved BOM"), সার্চসহ।
  - ডানে Approved BOM: SL, Item Code, Item Name, UOM, Required Qty, Stock Qty, Short Qty, Rate, Remarks, Status (Short / In stock)। Export দিয়ে CSV।
- **কম্পোনেন্ট:** `OrderBomViewer`।

**T9 Purchased Fabric Indent** (`/merchandise-desk/purchase-fabric-pr`)-এ দুটো ট্যাব:
- **Records:**
  - ফিল্টার: Req Month, Buyer A/C, Job/Order #, Supplier, Status, Req Date from/to।
  - কলাম: Requisition No, Req Date, Ship Date, GSM, DIA, Buyer A/C Grp, Job No, Order No, Supplier Name, Item List, Status।
  - হেডারে "Add New" বাটন। প্রতি সারিতে Print/PDF, Edit আর Delete।
- **Fabric Import Requisition Entry:**
  - বাঁয়ে Order List: Buyer A/C, সার্চ, Order # / Job · Style / Ship Date।
  - ডানে ফর্ম: Existing requisition, Requisition No, Req Date*, Status, Buyer A/C (অর্ডার থেকে), Job/Order #, Supplier, Ship Date, GSM ("180"), DIA ("26 inch"), Remarks।
  - **Fabric Item Details**: Type, Description, Construction, Composition, Color, Qty, UOM, GSM, DIA, Supplier, Required Date, Remarks, Remove। নিচে মোট।
  - বাটন T1-এর মতো, Print/PDF সহ।
- **কম্পোনেন্ট:** `PfabIndentEntry`।

**T10 Purchased Fabric Purchase Order** (`/merchandise-desk/purchase-fabric-po`)-এ তিনটা ট্যাব:
- **Records:** T2-এর ফিল্টার। কলাম T2-এর মতো, সাথে **PO Group**। Value = Net PO Amount।
- **Fabric Purchase Order Entry:**
  - বাঁয়ে **Approved PR List**: Supplier ("-- Any Supplier --"), সার্চ ("search PR…"), PR No / Req Date / Order, পাতা ভাগ।
  - ডানে Existing purchase order, তারপর **Master Data** (T2 + PO Group), **Terms & Condition**, **Cost, Tax & Adjustment** (Discount %/Amount, VAT %, Supplier TDS %, Supplier VDS %, Adjustment (+/−) আর Note) আর **PO Amount** (PO Value, Other Cost, VAT, Supplier TDS, Supplier VDS, Net PO Amount — "On save")।
  - **Details Data**: PR No, Fabric Type, Description, Construction, Composition, Color, GSM, DIA, UOM, PR Qty, PO Qty, Rate, Amount, Remarks, Remove। "Add to PO" বাটন।
  - **Other Cost**: Cost Head, VAT?, Amount, Remarks। "Add other cost" বাটন।
  - বাটন: Delete, Clear, Save purchase order / Save changes। নিচে নিয়মের বাক্য।
- **Requisition List:** বায়ার ধরে অপেক্ষমাণ fabric indent, আর "Add to PO" (`?tab=entry&pr=PRQ-FAB-…`)।
- **কম্পোনেন্ট:** `PfabPoEntry`; Requisition List-এ `AccPoRequisitions`।

**T11 Purchased Fabric Receipt Report** (`/merchandise-desk/purchase-fabric-mrr`)-এ দুটো ট্যাব:
- **Records:**
  - ফিল্টার: MRR No, Date from/to, Company, Store, Supplier, Payment Method, PR, PO।
  - কলাম: Is Finalized, Is Accounting, MRR No, MRR Date, PR, PO, Company, Supplier, Store, Pay Method, Source Type, LC No, RCV Qty, Exch/Ret Qty (T12-এর ফেরত থেকে), Net Amount, Remarks, Goods Value, VAT, TDS / VDS, Net Payable, Voucher; Edit আর Delete।
- **Fabric MRR:**
  - বাঁয়ে **Receive List**: Customer (সাপ্লায়ার), সার্চ, PO Number / PO Date, পাতা ভাগ।
  - ডানে Existing receipt, তারপর **Master List**: Company, Supplier, Receiving Store, Payment Method, MRR NO, Source Type, Date of Receive, Challan No, Challan Date, Currency, Conv. Rate, Import LC No, LC Date, BILL OF Entry, Bill of Entry Date, Remarks।
  - **Discount, VAT & Deductions at Source** আর **Bill Summary** (T3-এর মতো, Accounts Voucher সহ)।
  - **Details Data**: Fabric Type (নিচে PO নম্বর), Description, Composition, Color, GSM, DIA, Batch, Rolls, UOM, PO Qty, Prev. Received, Receive Qty, Reject Qty, Accept Qty, Balance Qty, Rate, Amount, Reject Reason, Remove। "Add received line" বাটন, নিচে মোট।
  - **Other Cost**: Cost Head, Landed?, VAT?, Amount, Remarks।
  - নিচে Back To List, "* Action Status 97-Create MRR", তারপর Delete, Clear, Save receipt / Save changes, Finalize MRR (নিশ্চিতকরণ)।
- **কম্পোনেন্ট:** `PfabMrrEntry`।

**T12 Fabric Supplier Return Challan** (`/merchandise-desk/fabric-return-challan-to-supplier`)-এর ট্যাব (Records, Fabric Return Challan to Supplier), ফিল্টার, কলাম আর Entry হুবহু T6-এর মতো:
- **Records-এর কলাম:** Is Finalized, MRR No, Rtn Challan#, Challan Date, Return Chalan Type, Company, Supplier, Store।
- **Entry:** MRR List, Master List, Details Data (Description, UOM, Qty, Reason), "* Action Status 41-Create Draft"।
- **কম্পোনেন্ট:** `AccReturnEntry` আবার ব্যবহার করা, `labels` prop দিয়ে শিরোনাম, toast আর নিশ্চিতকরণের লেখা বদলানো।

**T13 Finished Fabric Opening Balance** (`/merchandise-desk/finish-fabric-opening-balance`)-এ দুটো ট্যাব:
- **Records:** OB Date, Company, Receiving Store, Style/Job/Order, Currency, Conv Rate, 1USD=BDT, Items, Total Qty। সারিতে শুধু Remove, Brickland-এর মতো। এডিট হয় Entry-র Existing ড্রপডাউন দিয়ে।
- **Opening Balance Entry:**
  - Existing opening balance ("01 Oct 2026 · Fabric store · FFOB-…")।
  - হেডারে Receiving Store*, OB Date*, Style/Job/Order, Currency, Conv Rate, 1USD=BDT।
  - **Fabric Items**: উপরে-ডানে Add Row আর Cancel। ফর্মে ১৬টা ঘর।
  - grid: Fab Category, Fab Description, Fab Item, Fab Color, Fab Type, Fab Group, Composition, Batch, Roll, Qty, Rate, FIN.GSM, FIN.DIA, DIA MOU, DIA Type, OB UOM, Remove। সব ঘর এডিট করা যায়। নিচে মোট qty আর BDT খরচ।
  - নিচে নিয়মের বাক্য, Delete (লোড করা হলে), Clear, Save opening balance / Save changes।
- **কম্পোনেন্ট:** `FfabObEntry`।

**T14–T19 ছয়টা Closing** (`/merchandise-desk/purchase-fabric-closing`, `finish-fabric-closing`, `trims-closing`, `batch-fabric-closing`, `gray-fab-left-over-closing`, `finish-fab-left-over-closing`)-এ তিনটা ট্যাব, Brickland-এর মতো:
- **Records:** Period, Checked At, Exceptions, Closed At, Closed By, Remarks, Status (Open/Closed)। সারিতে কোনো বাটন নেই। খালি থাকলে "No purchase fabric close records yet"।
- **Show Exception:**
  - বাঁয়ে **Periods**: সার্চ, প্রতিটা মাসের status আর exception-এর সংখ্যা।
  - ডানে "Exceptions · Oct/2026": Document, Doc No, Date, Status। নিচে লেখা: "1 exceptions. Clear the exception on its own screen, then run Month Close again — the list is rebuilt each time."।
  - মাস না বাছা পর্যন্ত কিছু পড়া হয় না ("Select a period")।
- **Month Close:** "New purchase fabric close", Period* ("Jul/2026", hint সহ), Remarks; নিচে Reset আর Month Close।
- **কম্পোনেন্ট:** `StockClosingExceptions`, `StockMonthClose` (`stock-closing.jsx`)। স্ক্রিন `closingScreen(slug, kind)` দিয়ে ছয়বার।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_21_000100_create_merchandise_acc_requisitions.php`, `2026_11_22_000100_create_merchandise_acc_purchase_orders.php`, `2026_11_23_000100_create_merchandise_acc_receipts.php`, `Models/AccRequisition.php`, `Models/AccRequisitionLine.php`, `Models/AccPurchaseOrder.php`, `Models/AccPurchaseOrderLine.php`, `Models/ProcCostHead.php`, `Models/AccReceipt.php`, `Models/AccReceiptLine.php`, `Models/AccReceiptCost.php`, `Http/Controllers/Api/AccRequisitionApiController.php`, `Http/Controllers/Api/AccPurchaseOrderApiController.php`, `Http/Controllers/Api/AccReceiptApiController.php`, `Resources/ProcCostHeadResource.php`, `Resources/ProcPostingSetupResource.php`, `Http/Controllers/Api/AccReturnApiController.php`, `Http/Controllers/Api/FabricOpeningBalanceApiController.php`, `Models/FabricOpeningBalance.php`, `Models/FabricOpeningBalanceLine.php`, `Models/FabricOpeningBalanceEvent.php`, `Models/PurchasedFabricLedger.php`, `Database/Migrations/2026_11_26_000100_create_merchandise_fabric_opening_balances.php`, `Database/Migrations/2026_11_27_000100_add_dummy_to_merchandise_acc_requisitions.php`, `Database/Migrations/2026_11_28_000100_create_merchandise_pfab_indents.php`, `Http/Controllers/Api/PurchasedFabricIndentApiController.php`, `Models/PurchasedFabricIndent.php`, `Models/PurchasedFabricIndentLine.php`, `Database/Migrations/2026_11_29_000100_create_merchandise_pfab_pos.php`, `Http/Controllers/Api/PurchasedFabricPoApiController.php`, `Models/PurchasedFabricPo.php`, `Models/PurchasedFabricPoLine.php`, `Models/PurchasedFabricPoCost.php`, `Database/Migrations/2026_11_30_000100_create_merchandise_pfab_mrrs.php`, `Http/Controllers/Api/PurchasedFabricMrrApiController.php`, `Models/PurchasedFabricMrr.php`, `Models/PurchasedFabricMrrLine.php`, `Models/PurchasedFabricMrrCost.php`, `Database/Migrations/2026_12_01_000100_create_merchandise_pfab_returns.php`, `Http/Controllers/Api/FabricReturnApiController.php`, `Models/FabricReturn.php`, `Models/FabricReturnLine.php`, `Database/Migrations/2026_12_02_000100_create_merchandise_ffab_obs.php`, `Http/Controllers/Api/FinishedFabricObApiController.php`, `Models/FinishedFabricOpeningBalance.php`, `Models/FinishedFabricOpeningBalanceLine.php`, `Database/Migrations/2026_12_03_000100_create_merchandise_stock_closings.php`, `Http/Controllers/Api/StockClosingApiController.php`, `Models/StockClosing.php`, `Models/StockClosingException.php`, `Support/StockPeriods.php` (guard: `AccReceipt`, `AccReturn`, `FabricOpeningBalance`, `PurchasedFabricMrr`, `FabricReturn`, `FinishedFabricOb` controller), `Models/AccReturn.php`, `Models/AccReturnLine.php`, `Database/Migrations/2026_11_25_000100_create_merchandise_acc_returns.php`, `Models/ProcPostingSetup.php`, `Database/Migrations/2026_11_24_000100_create_merchandise_proc_posting_setups.php`, `Support/MerchandiseDefaults.php` (`COST_HEADS`, `ensureCostHeads`), `Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php` |
| পারমিশন ও মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/accessories.js` (`ACCESSORIES_SCREENS`, `ACCESSORIES_MANAGE`, `costHead` আর `postingSetup` = `masterScreen`), `page.js`, `actions.js` (`accRequisitionBom`, `loadAccRequisition`, `saveAccRequisition`, `transitionAccRequisition`, `deleteAccRequisition`, `loadAccPurchaseOrder`, `saveAccPurchaseOrder`, `deleteAccPurchaseOrder`, `loadPoRequisitions`, `loadAccReceipt`, `saveAccReceipt`, `finalizeAccReceipt`, `deleteAccReceipt`, `loadReceivables`, `returnFromReceipt`, `loadAccReturn`, `saveAccReturn`, `finalizeAccReturn`, `deleteAccReturn`, `loadFabricOb`, `loadFabricObHistory`, `saveFabricOb`, `transitionFabricOb`, `deleteFabricOb`, `fillPfabIndent`, `loadPfabIndent`, `savePfabIndent`, `transitionPfabIndent`, `deletePfabIndent`, `pfabPoFromIndent`, `loadPfabPoRequisitions`, `loadPfabPo`, `savePfabPo`, `deletePfabPo`, `pfabMrrFromOrder`, `loadPfabMrr`, `savePfabMrr`, `finalizePfabMrr`, `deletePfabMrr`, `pfabReturnFromReceipt`, `loadPfabReturn`, `savePfabReturn`, `finalizePfabReturn`, `deletePfabReturn`, `loadFfabOb`, `saveFfabOb`, `deleteFfabOb`, `loadClosingExceptions`, `closeStockMonth`; ছয়টা Closing = `closingScreen`), `frontend/src/components/merchandise/acc-requisition-entry.jsx` (`AccRequisitionEntry`), `acc-po-entry.jsx` (`AccPoEntry`), `acc-po-requisitions.jsx` (`AccPoRequisitions`), `acc-receipt-entry.jsx` (`AccReceiptEntry`), `acc-receivables.jsx` (`AccReceivables`), `acc-return-entry.jsx` (`AccReturnEntry`, T6 আর T12), `fabric-ob-entry.jsx` (`FabricObEntry`), `fabric-ob-history.jsx` (`FabricObHistory`), `order-bom-viewer.jsx` (`OrderBomViewer`), `pfab-indent-entry.jsx` (`PfabIndentEntry`), `pfab-po-entry.jsx` (`PfabPoEntry`), `pfab-mrr-entry.jsx` (`PfabMrrEntry`), `ffab-ob-entry.jsx` (`FfabObEntry`), `stock-closing.jsx` (`StockClosingExceptions`, `StockMonthClose`), `frontend/src/app/api/merchandise/fabric-ob-attachment/[balance]/route.js` |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.apr`, `master.apo`, `master.mrr`, `master.pch`, `master.pps`, `master.rtn`, `acc_rtn`, `master.fob`, `fob`, `master.dum`, `master.pfi`, `pfi`, `master.fpo`, `fpo`, `master.fmrr`, `fmrr`, `master.frt`, `master.ffob`, `ffob`, `master.clo`, `clo`, `master.noun.{cost_head,posting_setup}`, `master.new.{cost_head,posting_setup}`, `acc_requisition`, `acc_po`, `acc_mrr`, ফোল্ডার `trims_accessories`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseAccRequisitionTest.php`, `tests/Feature/Merchandise/MerchandiseAccPurchaseOrderTest.php`, `tests/Feature/Merchandise/MerchandiseAccReceiptTest.php`, `tests/Feature/Merchandise/MerchandiseProcCostHeadTest.php`, `tests/Feature/Merchandise/MerchandiseProcPostingSetupTest.php`, `tests/Feature/Merchandise/MerchandiseAccReturnTest.php`, `tests/Feature/Merchandise/MerchandiseFabricOpeningBalanceTest.php`, `tests/Feature/Merchandise/MerchandiseDummyIndentTest.php`, `tests/Feature/Merchandise/MerchandisePurchasedFabricIndentTest.php`, `tests/Feature/Merchandise/MerchandisePurchasedFabricPoTest.php`, `tests/Feature/Merchandise/MerchandisePurchasedFabricMrrTest.php`, `tests/Feature/Merchandise/MerchandiseFabricReturnTest.php`, `tests/Feature/Merchandise/MerchandiseFinishedFabricObTest.php`, `tests/Feature/Merchandise/MerchandiseStockClosingTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **ডাইং আর অর্ডার ক্লোজিং তৈরি হলে:** Batch Fabric, Grey আর Finished Leftover Closing-এর `StockPeriods::openDocuments`-এ তাদের ডকুমেন্ট যোগ হবে, আর তাদের controller-এ guard বসবে।
  - Fabric MRR আর Return তৈরি হলে `merch_pfab_ledger`-এ লিখবে (source আলাদা)।
  - Fabric Opening Balance, Dummy Order Indent।
  - Fabric Indent/PO/MRR/Return, Finished Fabric Opening।
  - ছয়টা Closing।
- **Trims store তৈরি হলে:** `stockOf()` থেকে issue বাদ যাবে (ফেরত এখনই বাদ যায়)।
- **Accounting-এ ভাউচার তৈরি হলে:** Finalize MRR T5 setup-এর লেজারে খসড়া ভাউচার তৈরি করবে, আর Accounts Voucher ঘর ভরবে। T5 এখন তৈরি।
- **Accessories PO-র MRR(s) কলাম:** এখনো ফাঁকা (Fabric PO-তে T11-এ ভরেছে)।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseAccRequisitionTest`** (২টা টেস্ট, ৫৬টা assertion):
  - BOM অনুমোদনের আগে অর্ডারে BOM দেখায় না। অনুমোদনের পরে সর্বশেষ অনুমোদিত ভার্সন দেখায় (BOM-T1 V2; Draft V1 বাদ)।
  - MAIN_FABRIC বাদ যায়। বোতাম: ১০০ × ১২/১২ × (১ + ৫% + ৩%) = ১০৮। থ্রেড: ১০০ × ১৫০ ÷ ৪০০০ = ৩.৭৫ কোন।
  - যাচাইয়ের বার্তা মিলিয়ে দেখা হয়। নম্বর PRQ-ACC-2026100001; Buyer A/C অর্ডার থেকে আসে।
  - একই আইটেম দ্বিতীয়বার আটকায়, কিন্তু Addition For দিলে চলে। Records-এর কলাম মিলিয়ে দেখা হয়; মোছার পরে 404।
  - Draft অবস্থায় approve করা যায় না। Submitted হলে এডিট বা মোছা যায় না। STORE_MANAGER তৈরি করতে পারেন না (403)।
  - Reject → Reopen → Submit → Approve; Approved indent reopen করা যায় না।
- **`MerchandiseAccPurchaseOrderTest`** (২টা টেস্ট, ৭১টা assertion):
  - হেডারের সাপ্লায়ার PR-এর লাইনে বসে।
  - অনুমোদনের আগে PR তালিকায় আসে না, আর তার বিপরীতে PO দেওয়া যায় না।
  - অনুমোদনের পরে Requisition List: বায়ার আর তার PR-এর সংখ্যা দেখায়; বায়ার না বাছলে PR আসে না; বাছলে বাকি ২১৮।
  - PR থেকে আনলে সাপ্লায়ার, সবচেয়ে আগের তারিখ (২৮ অক্টোবর) আর প্রতিটা লাইনের বাকি আসে।
  - আটটা "… is required." বার্তা, Delivery Date আগে হলে বার্তা, লাইন ছাড়া বার্তা, আইটেম ছাড়া সারির বার্তা যাচাই হয়। একই PR লাইন দুই সারিতে যোগ করে ১০৮ ছাড়ালে বার্তা।
  - PO-ACC-2026100001: USD 121.5, Same as Company হলে Billed To = Beneficiary, মূল্য ৯৬, UOM আইটেম থেকে।
  - বাকি ৮টা বোতাম; ৯টা দিলে বার্তা। নিজের qty নিজের বিপরীতে গোনে না, তাই ১০৮ পর্যন্ত বাড়ানো যায় (Rev 1, BDT হলে rate ১)।
  - Records-এর কলাম মিলিয়ে দেখা হয়। ট্যাগ লাইন PO থেকে সরালে তার ১১০ আবার বাকি দেখায়।
  - STORE_MANAGER দেখতে পারেন কিন্তু PO দিতে পারেন না (403)।
  - Partial রিসিভ হলে এডিট আর মোছায় বার্তা আসে, editable false। Pending অবস্থায় মুছলে PR-এর ৫০ আবার বাকি দেখায়।
- **`MerchandiseAccReceiptTest`** (১টা টেস্ট, ৭৭টা assertion):
  - ৫টা ডিফল্ট cost head; Receive List-এ সাপ্লায়ার আর বাকি ১৫০; PO থেকে আনলে header আর লাইন আসে।
  - দশটা "… is required.", চালানের তারিখ, লাইন ছাড়া, অন্য সাপ্লায়ারের PO, বাকির বেশি, cost head/অঙ্কের বার্তা যাচাই হয়।
  - বিল: goods ৬১, ১০% discount, landed ৮৩.৯, VAT base ৬৩.৯, VAT ৬.৩৯, TDS ১.৬৮, VDS ১২.৭৮ থেকে VAT-এর সীমায় ৬.৩৯, net ৮২.২২। লাইনে landed ৭০.১৫ আর ১৩.৭৫।
  - Draft রিসিপ্টেই PO লক হয়, কিন্তু Pending থাকে। বাকি ৪০; ৪১ দিলে বার্তা; draft মুছলে আবার ৪০।
  - Finalize → PO Partial; লক বার্তা; আবার finalize করা যায় না। T1-এ নতুন indent-এ বোতামের স্টক ৬০, ঘাটতি ৪০।
  - টাকার অঙ্কে discount ৫ দিয়ে বাকি ৪০ রিসিভ → PO Received, তালিকা আর অপশন থেকে বাদ। Records যাচাই। STORE_MANAGER 403।
- **`MerchandiseProcCostHeadTest`** (১টা টেস্ট, ৩২টা assertion):
  - ৫টা ডিফল্ট head; খালি সেভের বার্তা; একই code আটকায়।
  - landed না হলে লেজার লাগে। লেজার তালিকায় "5310 — Bank Charges Expense"। code upper-case হয় (BANK-CHG)।
  - landed করলে লেজার সরানো যায়।
  - Carriage দিয়ে রিসিপ্ট (landed ১৫)। তারপর Carriage-কে expense আর VAT-যুক্ত করলেও রিসিপ্টের ফ্ল্যাগ আর landed ১৫ থাকে।
  - ব্যবহৃত head মোছা যায় না, অব্যবহৃত মোছা যায় (204)। বন্ধ head রিসিপ্টের অপশনে আসে না। STORE_MANAGER 403।
- **`MerchandiseProcPostingSetupTest`** (১টা টেস্ট, ৩০টা assertion):
  - সাতটা "… is required."; group heading লেজার নেওয়া যায় না।
  - সেভ হলে লেবেল আসে ("1120 — Inventory — Trims" ইত্যাদি)। দ্বিতীয় setup দিলে বার্তা।
  - বন্ধ setup থাকলে finalize আটকায়; চালু করে (আর BV) finalize চলে। finalize হওয়া রিসিপ্টের setup মোছা যায় না। STORE_MANAGER 403।
- **`MerchandiseAccReceiptTest` আপডেট:** setup ছাড়া finalize করলে বার্তা আসে, তারপর setup দিয়ে finalize (T5-এর নিয়ম)।
- **`MerchandiseAccReturnTest`** (১টা টেস্ট, ৬২টা assertion):
  - MRR List-এ শুধু finalize করা MRR; Return By-তে কোম্পানির ব্যবহারকারী; ছয়টা ধরন।
  - MRR থেকে লাইন ভরা (বোতাম ১০০, লেবেল ৫০); draft MRR থেকে 404।
  - সাতটা "… is required."; draft MRR, অন্য সাপ্লায়ার, চালানের তারিখ MRR-এর আগে, লাইন ছাড়া, বাকির বেশি, বিবরণ ছাড়া — সবগুলোর বার্তা যাচাই।
  - RTN-ACC-2026100001: ৩০ বোতাম আর হাতে লেখা "Packing carton"; বাকি ৭০; ৭১ দিলে বার্তা।
  - draft-এ স্টক ১০০, finalize-এর পরে ৭০; লক বার্তা।
  - বাকি সব ফেরত (EXCESS) দিলে MRR তালিকা থেকে বাদ যায়। Records যাচাই। STORE_MANAGER 403।
- **`MerchandiseFabricOpeningBalanceTest`** (১টা টেস্ট, ৬৯টা assertion):
  - options-এ USD রেট ১১৯.৫ (সর্বশেষ)। পাঁচটা "… is required."; USD রেট আর Conversion Rate-এর বার্তা; সারি ছাড়া।
  - সারির বার্তা (Fabric Item, Fabric Group, Qty); একই OB-তে batch দুবার (b-1/B-1)।
  - USD-এ ১৪০ × ১১৯.৫ = ১৬,৭৩০ আর ২,৩৯০, মোট ১৯,১২০। একই স্টোরে B-1 আটকায়, অন্য স্টোরে চলে। EUR ১০ × ১.০৮ × ১১৯.৫ = ১,২৯০.৬০। BDT ২,০০০।
  - Draft-এ PDF attach হয়।
  - Draft থেকে approve আটকায়; Submit করলে এডিট আটকায়; Reject (মন্তব্যসহ) → Submit → Approve → Post (লেজারে ২টা লাইন, স্টোর আর cost) → Unpost (লেজার খালি) → Post → Lock → Unpost আর মোছা আটকায়।
  - History-তে নয়টা ধাপ, Reject-এর মন্তব্য আর কে করল। Records যাচাই। BDT draft মোছা যায়। STORE_MANAGER 403।
- **`MerchandiseDummyIndentTest`** (১টা টেস্ট, ২৫টা assertion):
  - Draft অর্ডার নিয়মিত অপশনে আসে না, ডামিতে আসে; BOM লাইনে rate ০.৪৫ আর remarks।
  - PRQ-DUM-2026100001, dummy true; দ্বিতীয় ডামিতে একই বোতাম আটকায়।
  - তালিকা আলাদা (ডামি / নিয়মিত / options)। Confirm করার পরে নিয়মিত PRQ-ACC-2026100001 আটকায় না। এডিটের পরেও dummy থাকে।
  - অনুমোদিত ডামি PR T2-এর Requisition থেকে আনা যায় আর Against PR-এর অপশনে আসে।
- **`MerchandisePurchasedFabricIndentTest`** (১টা টেস্ট, ৫২টা assertion):
  - options-এ অর্ডার আর শিপ ডেট, পাঁচটা UOM।
  - fill: শুধু PURCHASED Poplin আসে (MANUFACTURED SJ বাদ); GSM ১১০, DIA ৫৮, সাপ্লায়ার short name "star" দিয়ে মেলে।
  - বার্তা: তারিখ আর অর্ডার, লাইন ছাড়া, সারির তিনটা (প্রথম সারিতে ফাঁকা ঘর রেখে, যাতে সারির নম্বর ঠিক থাকে কিনা দেখা যায়), একই indent-এ দুবার।
  - PRQ-FAB-2026100001: শিপ ডেট অর্ডার থেকে, হেডারের সাপ্লায়ার দ্বিতীয় লাইনে। দ্বিতীয় indent-এ Poplin আটকায়। Records যাচাই।
  - Draft থেকে approve আটকায় → Submit → এডিট আটকায় → Reject → Reopen → এডিট → Submit → Approve → মোছা আটকায়। STORE_MANAGER 403।
- **`MerchandisePurchasedFabricPoTest`** (১টা টেস্ট, ৪৬টা assertion):
  - Draft indent তালিকায় আসে না; অনুমোদনের পরে Approved PR List, খোলা লাইন (১,২০০ আর ৮০), Requisition List-এ বাকি ১,২৮০।
  - indent থেকে সাপ্লায়ার আর সবচেয়ে আগের তারিখ আসে।
  - বাধ্যতামূলক ফিল্ডের বার্তা, লাইন ছাড়া, PR লাইন ছাড়া সারি, আর ১,২০১ দিলে "only 1200 is left"।
  - অঙ্ক: ২,৮২০ − ১৪১ (৫%) + ১৫০ + VAT ২৭২.৯০ − TDS ৫৬.৫৮ − VDS ২৭২.৯০ (VAT-এর সীমায়) − ১০ = ২,৭৬২.৪২। লাইনে ফ্যাব্রিকের কপি আর PR Qty।
  - বাকি ২০০; ২০১ দিলে বার্তা। নিজের qty নিজের বিপরীতে গোনে না, তাই ১,২০০ পর্যন্ত বাড়ানো যায় (Rev 1, ৩,০০০)। Requisition List-এ বাকি ৮০।
  - Records যাচাই। PARTIAL হলে এডিট আর মোছা আটকায়। STORE_MANAGER 403।
- **রিগ্রেশন:**
  - T1-এর সময়: Identity, Fabric Requisition, Reports আর TNA মিলিয়ে ৯৫টা টেস্ট। এতে একটা ব্যর্থতা ধরা পড়ে: approve পারমিশন মেনুতে ছিল না। ঠিক করা হয়েছে।
  - T2-এর পরে: T1, T2, MenuAccess আর RoleMatrix মিলিয়ে ১৭টা টেস্ট, ২০৮টা assertion সবুজ।
  - T3-এর পরে: T1, T2, T3, MenuAccess আর RoleMatrix — ১৮টা টেস্ট, ২৮৫টা assertion সবুজ।
  - T4-এর পরে: T1 থেকে T4, Buyer (sample-types resource), MenuAccess আর RoleMatrix — ২৩টা টেস্ট, ৩৭৫টা assertion সবুজ।
  - T5-এর পরে: T1 থেকে T5, Buyer, MenuAccess আর RoleMatrix — ২৪টা টেস্ট, ৪০৮টা assertion সবুজ।
  - T6-এর পরে: T1 থেকে T6, MenuAccess আর RoleMatrix — ২১টা টেস্ট, ৪১২টা assertion সবুজ।
  - T7-এর পরে: T1 থেকে T7, MenuAccess আর RoleMatrix — ২২টা টেস্ট, ৪৮১টা assertion সবুজ।
  - T8-এর পরে: T1 থেকে T8, MenuAccess আর RoleMatrix — ২৩টা টেস্ট, ৫০৬টা assertion সবুজ।
  - T9-এর পরে (ছয়টা controller-এ সারির ক্রমের বাগ ঠিক করার পরেও): T1 থেকে T9, MenuAccess আর RoleMatrix — ২৪টা টেস্ট, ৫৫৮টা assertion সবুজ।
  - T10-এর পরে: T1 থেকে T10, MenuAccess আর RoleMatrix — ২৫টা টেস্ট, ৬০৪টা assertion সবুজ।
  - T11-এর পরে: T1 থেকে T11, MenuAccess আর RoleMatrix — ২৬টা টেস্ট, ৬৫৩টা assertion সবুজ। `MerchandisePurchasedFabricMrrTest` একাই ৪৯টা: Receive List, PO থেকে ভরা, বাধ্যতামূলক ঘর, আংশিক ফাঁকা প্রথম সারি, Reject > Receive, খোলার বেশি, অঙ্ক আর landed ভাগ, খোলা ৪০০ বাকি, PO লক, Posting Setup ছাড়া finalize আটকানো, ledger, Records, PO Partial, STORE_MANAGER শুধু দেখতে পারে।
  - T12-এর পরে: T6, T11, T12, MenuAccess আর RoleMatrix — ১৬টা টেস্ট, ২৪৪টা assertion সবুজ। `MerchandiseFabricReturnTest` একাই ৫২টা: শুধু finalize হওয়া MRR তালিকায়, batch-এর বিবরণ, বাধ্যতামূলক ঘর, MRR-এর আগের তারিখ, আংশিক ফাঁকা প্রথম সারি, accept-এর বেশি, free লাইন, বাকি ৫৫০, MRR-এর Ret Qty ৫০, ledger −৫০ / −১৩৫.৩০, লক, Records, STORE_MANAGER শুধু দেখতে পারে।
  - T13: `MerchandiseFinishedFabricObTest` — ২৭টা assertion: options, বাধ্যতামূলক ঘর, লাইন ছাড়া, প্রতি সারির প্রথম খালি ঘর, qty ০, batch দুবার, USD খরচ (conv ১ বাধ্য, OB তারিখের রেট), BDT-তে এডিট, Records, STORE_MANAGER মুছতে পারে না, Delete।
  - T14–T19: `MerchandiseStockClosingTest` — ৩৮টা assertion: Period বাধ্যতামূলক আর ফরম্যাট, খোলা PFOB-এ refuse আর রেকর্ড, Show Exception, Post করার পরে Closed (Closed By, Remarks), আবার বন্ধ করা যায় না, closed মাসে PFOB unpost আটকায়, Finished Fabric-এর মাস বন্ধ হলে FFOB সেভ আর Delete আটকায় কিন্তু অন্য মাস খোলা থাকে, বাকি চারটা kind সঙ্গে সঙ্গে বন্ধ হয়, অজানা kind 404, STORE_MANAGER শুধু দেখতে পারে।
  - সব শেষে: T1 থেকে T19, MenuAccess আর RoleMatrix — ২৯টা টেস্ট, ৭৭০টা assertion সবুজ (guard বসানোর পরে)।
- **ব্রাউজার যাচাই, T1** (২০২৬-১০-১৭, owner লগইন):
  - dev ডাটা: ৪টা ট্রিমস আইটেম। কনফার্মড অর্ডার "Order" (১০০০ পিস)-এর জন্য অনুমোদিত BOM-000201 V1।
  - BOM ট্যাব → Add PR → ৪টা লাইন: বোতাম ৪,২০০, লেবেল ১,০৪০, থ্রেড ৩৬ কোন, পলি ১,০০০ ✔
  - PRQ-ACC-2026100001: Save → Submit → Approve ✔
  - PRQ-ACC-2026100002 ("Poly bag wastage"): Submit → Approve, নিশ্চিতকরণ ডায়ালগে Brickland-এর বাক্য ✔
  - Records-এ Status আর Print/PDF ✔; Entry-তে Supplier ফিল্ড ✔
- **ব্রাউজার যাচাই, T2:**
  - Records খালি; ফিল্টার আর ১৩টা কলাম Brickland-এর মতো ✔
  - Requisition List-এ "LPP S.A. 1" → PRQ-ACC-2026100001 (বাকি ৬,২৭৬) → Add to PO ✔
  - Entry-তে ৪টা লাইন, প্রতিটায় PR নম্বর; সাপ্লায়ার KOYO, ডেলিভারি ২০২৬-১১-০৫ ✔
  - বোতাম ৪,৩০০ দিলে বার্তা "only 4200 is left" ✔
  - ৪,০০০-এ সেভ → PO-ACC-2026100001, Rev 0; এডিট → Rev 1; Same as Company দিয়ে → Rev 2, Billed To = Delta Apparels Ltd ✔
  - Records: Pending, Revised, Trims & accessories store, FOB, At Sight, Lc, ১৩,৮৭৮.০০ BDT ✔
  - Requisition List-এ এখন PRQ-1-এর বাকি ২০০ বোতাম আর PRQ-2 (১,০৮০) ✔
- **ব্রাউজার যাচাই, T3:**
  - Records-এ খালি অবস্থা ✔
  - Receive List-এ "KOYO TRIMS SOLUTION JP (SUP-10006) 1" → PO-ACC-2026100001, বাকি ৬,০৭৬ ✔
  - PO থেকে Entry: ৪টা লাইন, PO-র দামসহ ✔
  - বোতাম ৪,৫০০ দিলে বার্তা "only 4000 is left" ✔
  - ৩,০০০ বোতাম, ৫% discount, VAT ৭.৫%, TDS ২%, Carriage ৫০০ আর C&F ২০০ দিয়ে সেভ → MRR-ACC-2026100001 ✔
  - বিল: goods ১৩,০২৮, other ৭০০, landed ১৩,০৭৬.৬০, VAT base ১২,৫৭৬.৬০, VAT ৯৪৩.২৫, TDS ২৬১.৫৩, net ১৩,৭৫৮.৩২ ✔
  - Finalize → নিশ্চিতকরণ → Finalised ✔; PO এখন Partial ✔; T1-এ বোতামের স্টক ৩,০০০, ঘাটতি ১,২০০ ✔
- **ব্রাউজার যাচাই, T4:**
  - Records-এ ৫টা head আর ৮টা কলাম ✔
  - New-এ নোট আর hint ঠিক আছে; Landed আর Active চেক করা ✔; খালি সেভে দুটো বার্তা ✔
  - Bank Charges, Landed বন্ধ করে লেজার ছাড়া সেভ → লেজারের বার্তা ✔ → লেজার দিয়ে সেভ → BANK-CHG ✔
  - CNF-এর Edit ট্যাব ("Rewriting CNF.") → ক্রম ২৫ → Update ✔
  - Remove ডায়ালগের লেখা Brickland-এর সাথে হুবহু মিলেছে ✔ → Cancel
  - BANK-CHG-এর লেজার পরে Edit থেকে "5010103 — Bank Charge - TT" করা হয়েছে, কারণ টেস্ট প্রথমে ভুল করে একটা ব্যাংক একাউন্ট বেছেছিল।
- **ব্রাউজার যাচাই, T5:**
  - dev-এর Chart of Accounts-এ VAT Receivable, TDS Payable আর VDS Payable ছিল না। পাশের একাউন্টের parent-এর নিচে যোগ করা হয়েছে: 1020304, 2020304, 2020305।
  - খালি অবস্থা ✔; নোট আর hint ঠিক আছে ✔; খালি সেভে সাতটা বার্তা ✔
  - Delta Apparels Ltd, JV, 1120 / 1020304 / 2020201 / 2020304 / 2020305 দিয়ে সেভ ✔; Records-এ সারি ✔
  - PO-ACC-2026100001-এর বাকি ১,০০০ বোতাম → MRR-ACC-2026100002 → Finalize চলেছে (setup আছে) ✔; PO এখন Received ✔
- **ব্রাউজার যাচাই, T6:**
  - Records: ফিল্টার আর কলাম Brickland-এর মতো; খালি অবস্থা ✔
  - MRR List-এ KOYO-র দুটো MRR ✔ → MRR-ACC-2026100001 বাছলে ৪টা লাইন আর MRR No ✔
  - Return By ছাড়া সেভ করলে বার্তা ✔
  - বোতাম ২০০ ("Broken holes"), গাড়ি DHK-METRO-11-2233, চালক Karim, বাকি ৩টা লাইন সরিয়ে সেভ → RTN-ACC-2026100001 ✔
  - Finalize → নিশ্চিতকরণ → Finalised ✔; Records-এ "Yes" ✔; T1-এ বোতামের স্টক ৪,০০০ থেকে ৩,৮০০, ঘাটতি ৪০০ ✔
- **ব্রাউজার যাচাই, T7:**
  - Records: ফিল্টার আর ১৫টা কলাম ✔; 1 USD = BDT-এর ডিফল্ট ১১৯.৫ ✔
  - Fabric Item ছাড়া Add Row → "Fabric Item is needed before the row can be added." ✔
  - দুটো সারি যোগ: SJ-160-WHT (৪২০ kg × ৩.৪) আর RIB-1X1-WHT (৮৫ kg × ৪.১) → মোট ৫০৫, ২,১২,২৯১.৭৫ BDT ✔ → সেভ PFOB-2026100001 ✔
  - `.txt` attach করলে বার্তা আসে (শুধু ছবি/PDF) ✔
  - Submit → Approve → Post (নিশ্চিতকরণ) → Posted, Unpost আর Lock বাটন ✔; Records ✔; Approval History-তে চারটা ধাপ ✔
  - দ্বিতীয় OB, BDT আর Legacy ERP Migration: FLC-280-NVY ১৫০ × ৫২০ = ৭৮,০০০ ✔ → PFOB-2026100002 (Draft), PDF attach করে লিংক ✔
  - টেস্ট-ডাটার একটা ভুল: PFOB-2026100001-এর প্রথম সারির Type এসেছে "Lycra Single Jersey", কারণ টেস্ট "Single Jersey" লেখা প্রথম অপশনটা বেছে নিয়েছিল। এটা Posted, তাই রেখে দেওয়া হয়েছে।
- **ব্রাউজার যাচাই, T8:**
  - Records-এ অর্ডারের গ্রিড আর View BOM ✔ → Order BOM-এ "Approved BOM — Order · 252525", চারটা লাইন: বোতাম Short (স্টক ৩,৮০০ / প্রয়োজন ৪,২০০), বাকিগুলো In stock ✔
  - Entry (`?order=Order`): "Requisition Entry (Dummy Order)", BOM থেকে চারটা লাইন ✔ → সেভ PRQ-DUM-2026100001 → Submit ✔
  - PR List-এ শুধু এটা ✔; T1-এর তালিকায় এটা নেই ✔
- **ব্রাউজার যাচাই, T9:**
  - Records: ফিল্টার আর কলাম ✔; খালি অবস্থা ✔
  - Order List-এ "Order · FLOW STYLE-01 · 12 Jan 2027" ✔ → বাছলে Ship Date ২০২৭-০১-১২ ✔; স্টাইলে কেনা ফ্যাব্রিক নেই, তাই নোটিশ ✔
  - GSM ১৬০, DIA "72 inch open", সাপ্লায়ার KOYO; Fleece ৬৫০ kg আর 1x1 Rib (প্রথমে ০ দিয়ে → "Row 2: Qty must be above zero." ✔ → ৯০) → সেভ PRQ-FAB-2026100001, মোট ৭৪০ ✔
  - Submit → Approve (নিশ্চিতকরণ) → Approved ✔; Records-এ সারি ✔
- **ব্রাউজার যাচাই, T10:**
  - Records-এর ১৪টা কলাম, PO Group সহ ✔
  - Approved PR List-এ PRQ-FAB-2026100001 ✔ → বাছলে দুটো লাইন (Fleece ৬৫০ kg, Cuff rib ৯০ kg), ফ্যাব্রিকের বিবরণসহ ✔
  - শর্ত ছাড়া সেভ করলে বার্তা ✔
  - Rate ৫২০ আর ৪৮০, LC at 30 days, By Road, Fabric store, PO Group SS27-FLEECE, Discount ২%, VAT ৫%, Adjustment −৫০০, Carriage ৩,০০০ দিয়ে সেভ → PO-FAB-2026100001, Rev 0 ✔
  - অঙ্ক: PO Value ৩,৮১,২০০, Other Cost ৩,০০০, VAT ১৮,৬৭৮.৮০, Net ৩,৯৪,৭৫৪.৮০ (হাতে মিলিয়েছি) ✔; Records ✔
  - dev DB-তে রাখা আছে।
  - dev DB-তে রাখা আছে।
  - dev DB-তে রাখা আছে।
  - সব dev DB-তে রাখা আছে।
  - dev DB-তে রাখা আছে।
  - সব dev DB-তে রাখা আছে।
  - সব dev DB-তে রাখা আছে।
  - সব রেকর্ড dev DB-তে রাখা আছে।
  - সব রেকর্ড dev DB-তে রাখা আছে।

- **ব্রাউজার যাচাই, T11:**
  - Records-এর ২১টা কলাম ✔; ট্যাব Records আর Fabric MRR ✔
  - Receive List-এ PO-FAB-2026100001 ✔ → বাছলে Fleece ৬৫০ kg আর Cuff rib ৯০ kg, PO Qty/Prev. Received/Balance সহ ✔; স্টোর "Fabric store" PO থেকে ✔
  - Fleece ৭২০ দিয়ে সেভ → "Row 1: only 650 is left to receive on PO PO-FAB-2026100001." ✔
  - Challan DC-55120, LC-2026-0417, Bill of Entry C-88214; Fleece ৪২০ (Reject ১২, "Holes in 2 rolls", ১৮ রোল, FL-B01) আর Cuff rib ৯০ (CR-B01); VAT ৫%, Carriage ২,৫০০ → MRR-IMF-2026100001 ✔
  - অঙ্ক: Goods ২,৫৫,৩৬০ (৪০৮ × ৫২০ + ৯০ × ৪৮০), Landed ২,৫৭,৮৬০, VAT ১২,৭৬৮, Net Payable ২,৭০,৬২৮ ✔; Balance ২৪২ ✔
  - Finalize MRR (নিশ্চিতকরণ) → Finalised ✔; Records-এ সারি ✔; PO-FAB-2026100001 এখন Partial, MRR(s)-এ MRR-IMF-2026100001 ✔
  - সব রেকর্ড dev DB-তে রাখা আছে।

- **ব্রাউজার যাচাই, T12:**
  - শিরোনাম, ট্যাব আর Records-এর ৮টা কলাম Brickland-এর মতো ✔
  - MRR List-এ MRR-IMF-2026100001 · DC-55120 ✔ → বাছলে Company, KOYO, Fabric store আর দুটো batch-এর লাইন ✔
  - Off Shade, Return By Rahim, Gate Pass GP-2026-311, Vehicle, Driver; Fleece ৫০০ দিয়ে সেভ → "Row 1: only 408 is left to return on receipt MRR-IMF-2026100001." ✔
  - ১২ আর কারণ দিয়ে, Cuff rib-এর লাইন সরিয়ে সেভ → RTN-FAB-2026100001 ✔ → Finalize → Finalised ✔
  - Records-এ সারি ✔; T11 Records-এ Exch/Ret Qty ১২ ✔
  - সব রেকর্ড dev DB-তে রাখা আছে।

- **ব্রাউজার যাচাই, T13:**
  - শিরোনাম, ট্যাব আর Records-এর ৯টা কলাম Brickland-এর মতো ✔
  - খালি সেভ → "Receiving Store is required." ✔
  - Add Row: প্রথমে "Fab Description is needed…", বিবরণ আর Item দেওয়ার পরে "Fab Type is needed…" ✔
  - Navy brushed fleece (Fleece, Body, ২৮০ GSM, ৭২ DIA, FF-B01, ১৪ রোল, ৩৮০ kg, ৪.৩৫) আর Navy cuff rib (1x1 Rib, Rib, FF-B02, ৩ রোল, ৪৫ kg, ৫.১০) → grid-এ দ্বিতীয় সারির Qty ৪৬.৫ করা ✔
  - সেভ → FFOB-2026100001; মোট ৪২৬.৫, ২,২৫,৮৭২.৯২ BDT (USD ১১৯.৫) ✔; Records-এ সারি ✔
  - dev DB-তে রাখা আছে।

- **ব্রাউজার যাচাই, T14–T19:**
  - শিরোনাম, তিনটা ট্যাব, Records-এর ৭টা কলাম আর খালি অবস্থার লেখা Brickland-এর মতো ✔
  - Month Close-এ "October 2026" → ফরম্যাটের বার্তা ✔
  - "Oct/2026" আর Remarks → "Oct/2026 cannot close: 1 document is still open — see Show Exception." ✔ (PFOB-2026100002 এখনো Draft)
  - Show Exception: Periods-এ "Oct/2026 · Open · 1" ✔ → বাছলে PFOB-2026100002, ০২ Oct 2026, Draft, আর নিচে মোট সংখ্যা ✔
  - Records-এ Oct/2026, Exceptions ১, Open ✔
  - Trims Stock Closing: "2026-09" → Closed; Records-এ Sep/2026, ০, Closed At, Rahim Uddin, Closed ✔
  - Grey Fabric Leftover Closing-এর শিরোনাম আর Show Exception-এর খালি অবস্থা ✔
  - dev DB-তে রাখা আছে। Purchased Fabric-এর অক্টোবর ইচ্ছা করে বন্ধ করা হয়নি, যাতে dev ডাটা এডিট করা যায়।

## ১০. খোলা প্রশ্ন

- **T1 "Add New":** Brickland-এর Records-এর "Add New" ডায়ালগ আসলে BOM-এর সার্চ। এখানে সেটা BOM ট্যাবে নিয়ে যায়।
- **T2 PO নম্বর:** Brickland-এ PO সেভ হচ্ছিল না (বাটন সক্রিয়, কোনো ভুলের বার্তা নেই, রেকর্ডও হয় না), তাই নম্বরের ধরন, সারির অ্যাকশন আর এডিট অবস্থা দেখা যায়নি। এখানে নম্বর `PO-ACC-YYYYMM####`, সারিতে Edit আর Delete।
- **T2 Requisition List:** Brickland-এ বায়ার অ্যাকাউন্ট নেই, তাই তালিকা সবসময় খালি দেখায়। ভেতরের অংশ বানানো হয়েছে পাতার লেখা থেকে ("requisitions … still waiting to be ordered")।
- **T3 Records:** Brickland-এ কোনো রিসিপ্ট নেই, আর সেভ হচ্ছিল না, তাই গ্রিডের কলাম দেখা যায়নি। কলামগুলো এখানে বেছে নেওয়া।
- **T3 খসড়া ভাউচার:** Brickland-এর Finalize খসড়া ভাউচার তৈরি করে। এখানে Accounting-এর ভাউচার আর T5 Posting Setup তৈরি হলে যোগ হবে।
- **T6 Finalize বাটন:** Brickland-এর নতুন চালানে শুধু Back To List, Clear আর Save দেখা যায়। কিন্তু Records-এ "Is Finalized" কলাম আছে, তাই সেভ করা চালানে Finalize আর Delete রাখা হয়েছে। Brickland-এ সেভ হয় না, তাই এটা যাচাই করা যায়নি।
- **T7 workflow বাটন:** Brickland-এ সেভ হচ্ছিল না, তাই সেভের পরের বাটন দেখা যায়নি। প্রবাহ আর Unpost/Lock বানানো হয়েছে পাতার নিয়মের বাক্য থেকে ("Draft → Submitted → Approved → Posted → Locked … a locked balance can no longer be unposted")। Reject করলে Draft-এ ফেরে, কারণ Records-এর Status ফিল্টারে আলাদা "Rejected" নেই।
- **T7 Attach:** Brickland-এ নতুন অবস্থাতেই Attach বাটন আছে। এখানে আগে সেভ করতে হয়, কারণ ফাইল OB-র সাথে যুক্ত থাকে। ফাইলের ধরন প্রজেক্টের নিয়মে সীমিত (ছবি/PDF), তাই Excel কাউন্ট শিট PDF করে দিতে হবে।
- **T7 OB নম্বর:** Brickland-এর ধরন দেখা যায়নি; এখানে `PFOB-YYYYMM####`।
- **T8 PR List-এর Show বাটন:** Brickland-এ ফিল্টার দিয়ে Show চাপতে হয়। এখানে ফিল্টার বদলালেই তালিকা বদলায়, অন্য তালিকাগুলোর মতো। লেখা খোঁজার ঘর Enter চাপলে বা ঘর ছাড়লে কাজ করে।
- **T8 নম্বর:** Brickland-এর ডামি indent-এর নম্বর দেখা যায়নি (সেভ হয় না); এখানে `PRQ-DUM-…`।
- **T10 Approved PR List-এ বাছা:** Brickland-এর লেখা "Pick an approved requisition … to fill the details"। এখানে বাছলে তার খোলা লাইন যোগ হয়, আগের লাইন মোছে না। তাই একাধিক PR এক PO-তে নেওয়া যায় (PR Number(s))।
- **T10 "Add to PO":** Brickland-এর খালি লাইনের ভেতর দেখা যায়নি। এখানে খোলা PR লাইন বাছার ঘর রাখা হয়েছে, কারণ নিয়মে প্রতিটা লাইনকে একটা indent লাইন অর্ডার করতে হয়।
- **T11 Source Type:** Brickland-এর নতুন MRR-এ ডিফল্ট IMPORT। এখানে নতুন ফর্মে IMPORT থাকে, তবে Receive List থেকে PO বাছলে PO-র Source বসে, কারণ রিসিপ্ট সেই অর্ডারেরই।
- **T11 Finalize আর Delete:** Brickland-এর নতুন MRR-এ শুধু Back To List, Clear আর Save দেখা যায় (সেভ হয় না)। T3-এর মতো সেভ করা খসড়ায় Finalize MRR আর Delete রাখা হয়েছে, কারণ Records-এ "Is Finalized" কলাম আছে।
- **T11 Receive List-এর "Customer":** Brickland-এ লেবেল Customer, কিন্তু তালিকায় সাপ্লায়ার। এখানেও লেবেল Customer, অপশন সাপ্লায়ার।
- **T12 MRR No ঘর:** Brickland-এর Master List-এ MRR No ঘর দেখা যায় না, কিন্তু চালান কোন MRR-এর তা জানতে হয়। T6-এর মতো শুধু-পড়ার ঘর রাখা হয়েছে।
- **T13 নম্বর:** Brickland-এর Existing-এ "OB-1" দেখা যায়। এখানে মাস অনুযায়ী `FFOB-YYYYMM####`।
- **T13 Records-এ Edit নেই:** Brickland-এর সারিতে শুধু Remove আছে, তাই এখানেও। এডিট হয় Entry-র Existing ড্রপডাউন দিয়ে।
- **Closing-এর exception কোনগুলো:** Brickland শুধু বলে "documents standing in the way of closing it"। এখানে ধরা হয়েছে: সেই kind-এর স্টকে প্রভাব ফেলা ডকুমেন্ট, যেগুলো মাসের তারিখে এখনো finalize বা post হয়নি। Indent আর PO স্টক বদলায় না, তাই ধরা হয়নি।
- **Closed মাসের guard:** Brickland-এ সেভ হয় না, তাই এটা দেখা যায়নি। কিন্তু পাতার নিয়মের বাক্য ("a later rate change cannot restate a period that has already closed") অনুযায়ী বন্ধ মাসের ডকুমেন্ট আর বদলানো যায় না।
- **Closing আবার খোলা:** Brickland-এ কোনো বাটন নেই, এখানেও নেই। দরকার হলে পরে যোগ করা যাবে।
- **Closing-এ ব্যালান্সের snapshot:** Brickland-এর Records-এ পরিমাণ বা মূল্যের কলাম নেই, তাই রাখা হয়নি। রিপোর্ট লাগলে ledger থেকে মাস-শেষের হিসাব বের করা যায়।

## ১১. As-built নোট

- **T1 Accessories Purchase Indent ✅** (২০২৬-১০-১৭)।
- **T2 Accessories Purchase Order ✅** (২০২৬-১০-১৭)।
- **T3 Accessories Receipt Report ✅** (২০২৬-১০-১৭)। খসড়া ভাউচার বাকি।
- **T4 Procurement Cost Head ✅** (২০২৬-১০-১৭)। screen kit-এর resource দিয়ে বানানো।
- **T5 Procurement Posting Setup ✅** (২০২৬-১০-১৭)। এখন থেকে MRR finalize-এর আগে setup লাগে।
- **T6 Accessories Supplier Return Challan ✅** (২০২৬-১০-১৭)।
- **T7 Purchased Fabric Opening Balance ✅** (২০২৬-১০-১৭)। purchased-fabric stock ledger শুরু হয়েছে।
- **T8 Dummy Order Accessories Indent ✅** (২০২৬-১০-১৭)। T1-এর টেবিল আর Entry আবার ব্যবহার করা হয়েছে।
- **T9 Purchased Fabric Indent ✅** (২০২৬-১০-১৭)।
- **T10 Purchased Fabric Purchase Order ✅** (২০২৬-১০-১৭)। Receive Status আর MRR(s) T11-এ ভরে।
- **T11 Purchased Fabric Receipt Report ✅** (২০২৬-১০-১৭)। purchased-fabric ledger-এ এখন OB আর MRR দুটো উৎস। খসড়া ভাউচার T3-এর মতোই বাকি।
- **T12 Fabric Supplier Return Challan ✅** (২০২৬-১০-১৭)। T6-এর Entry আবার ব্যবহার করা হয়েছে। purchased-fabric ledger-এ এখন উৎস OB, MRR আর RTN।
- **T13 Finished Fabric Opening Balance ✅** (২০২৬-১০-১৭)। Workflow নেই (Brickland-এর মতো)। T15 Finished Fabric Closing এর লাইন পড়বে।
- **T14–T19 ছয়টা Closing ✅** (২০২৬-১০-১৭)। এক টেবিল, এক controller, এক কম্পোনেন্ট, `kind` দিয়ে আলাদা। বন্ধ মাসের guard ছয়টা controller-এ।
- **Trims & Accessories সম্পূর্ণ: ১৯টা স্ক্রিনের সবগুলো ✅।**
- **বাগ ঠিক করা হয়েছে (T9-এর টেস্টে ধরা পড়েছে):**
  - সমস্যা: Laravel-এর `validate()` লাইনগুলো rule-এর ক্রমে ফেরত দেয়। যে সারিতে কোনো ঘর ফাঁকা, সেটা শেষে চলে যেত। ফলে "Row 1"-এর বার্তা "Row 3" দেখাত, আর লাইন ভুল ক্রমে সেভ হতে পারত।
  - সমাধান: এখন validate-এর পরে `ksort` করে আসল ক্রম রাখা হয়। ঠিক করা হয়েছে T1 (AccRequisition), T2, T3 (লাইন আর other cost), T6, T7 আর T9-এ।
- **T1-এ পরে ঠিক করা হয়েছে** (T2-এর লাইভ প্রোবে ধরা পড়েছে):
  - হেডারের Supplier ফিল্ড ফিরিয়ে আনা হয়েছে। পুরোনো ক্রলার button-ধরনের combobox ধরতে পারেনি, তাই আগে ভুল করে সরানো হয়েছিল।
  - Records-এ Status কলাম আর Print/PDF যোগ হয়েছে।
  - Approve-এর আগে নিশ্চিতকরণ যোগ হয়েছে।
  - Brickland-এর hint আর নিয়মের বাক্য বসানো হয়েছে, আর "Item Name" কলাম।
- **মেনু:** `MERCHANDISE.ACC_PR`-এ view, manage আর approve তিনটা পারমিশনই আছে।
- **বাগ ঠিক করা হয়েছে:** একই আইটেমের চেকে addition indent-ও গোনা হচ্ছিল। এখন শুধু নিয়মিত indent ধরা হয়।
- **লাইভ প্রোবের ফাইল:** `tools/erp_crawler/plans/apo1.json` থেকে `apo11.json`। T3-এর জন্য `mrr1.json` আর `mrr2.json` (Supplier, Receiving Store আর Requisition Type-এর অপশন, cost head, Posting Setup)। T4-এর জন্য `pch1.json` আর `pch2.json` (New/Edit ট্যাব, Expense Ledger-এর অপশন, খালি সেভ, Remove ডায়ালগ)। T5-এর জন্য `pps1.json` (New ট্যাব, voucher type, পাঁচটা লেজার, নিয়মের লেখা)। T6-এর জন্য `rtn1.json` আর `rtn2.json` (MRR List, Supplier, Returning Store আর Return By-এর অপশন, লাইনের কলাম, খালি সেভ)। T7-এর জন্য `fob1.json` থেকে `fob5.json` (Header-এর ডিফল্ট, button-ড্রপডাউন, Add Row-এর ধাপে ধাপে বার্তা, গ্রিডের কলাম, Approval History)। T8-এর জন্য `dum1.json` আর `dum2.json` (Records, View BOM, Entry-র ফিল্ড, PR List, Order BOM-এর তালিকা আর BOM টেবিল)। T9-এর জন্য `fpr1.json` (Records, Entry-র ফিল্ড, অর্ডার বাছার পরে Ship Date, Add item-এর কলাম)। T10-এর জন্য `fpo1.json` (সব অংশ আর ফিল্ড, ডিফল্ট, hint আর নিয়মের লেখা, Details আর Other Cost-এর কলাম, Requisition List)। T12-এর জন্য `frt1.json` (Master List-এর সব ঘর, button-ড্রপডাউন, লাইনের কলাম, খালি সেভের বার্তা)। T14–T19-এর জন্য `clo1.json` আর `clo2.json` (তিনটা ট্যাব, Month Close-এর ঘর আর hint, ছয়টা স্ক্রিনের লেখা; Brickland-এ Jul/2026 বন্ধ করে Records-এর কলাম আর Exceptions-এর অবস্থা দেখা হয়েছে, সেই সারি সেখানে রয়ে গেছে, কারণ মোছার বাটন নেই)। T13-এর জন্য `ffob1.json` থেকে `ffob6.json` (ঘর আর ডিফল্ট, Add Row-এর ধাপে ধাপে বার্তা, grid-এর কলাম, Brickland-এ সেভ সফল হয়েছে, তারপর Records-এর কলাম, Existing-এর লেবেল, লোড অবস্থা আর Remove)। probe-এ বানানো রেকর্ড Brickland থেকে মুছে দেওয়া হয়েছে। এর মধ্যে Brickland-এ PRQ-ACC-2026100001 অনুমোদন করা হয়েছে, আর সেখানে একটা PR আর একটা PO সেভের চেষ্টা হয়েছিল, কিন্তু রেকর্ড তৈরি হয়নি।
