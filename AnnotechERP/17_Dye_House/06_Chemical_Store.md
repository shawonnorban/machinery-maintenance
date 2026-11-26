# Chemical Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (১৬টার ১৬টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/06`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Chemical Opening Balance | `/m/dyeing/stock-opening-balance` | 1 | 14 | 0 |
| 2 | Daily Dye & Chemical Receipt | `/m/dyeing/daily-dyes-chemical-receive` | 2 | 35 | 1 |
| 3 | Chemical Loan Opening Balance | `/m/dyeing/loan-opening-balance` | 1 | 5 | 0 |
| 4 | Chemical Item Stock | `/m/dyeing/chemical-item-stock` | 1 | 7 | 0 |
| 5 | Dye & Chemical Receipt Return Register | `/m/dyeing/daily-dyes-chemical-receive-return` | 2 | 21 | 1 |
| 6 | Chemical Store Transfer Issue | `/m/dyeing/store-transfer-requisition-and-issue` | 2 | 21 | 1 |
| 7 | Print Chemical Issue Note | `/m/dyeing/print-chemical-issue` | 2 | 21 | 2 |
| 8 | Machine Wash & Finishing Issue | `/m/dyeing/machine-wash-and-finishing-issue` | 2 | 18 | 2 |
| 9 | Batch Programme Chemical Issue | `/m/dyeing/batch-program-issue` | 2 | 22 | 2 |
| 10 | Dye & Chemical Sales | `/m/dyeing/dyes-chemical-sales` | 2 | 19 | 2 |
| 11 | Chemical Comparative Statement | `/m/dyeing/chemical-cs` | 1 | 7 | 1 |
| 12 | Chemical Loan Issue Indent | `/m/dyeing/requisition-for-loan-issue` | 1 | 8 | 1 |
| 13 | Chemical Loan Return from Party | `/m/dyeing/loan-return-by-party` | 1 | 8 | 1 |
| 14 | Dye Chemical Loan Receipt | `/m/dyeing/dc-loan-receive` | 1 | 10 | 1 |
| 15 | Chemical Loan Return Indent | `/m/dyeing/requisition-for-loan-return` | 1 | 8 | 1 |
| 16 | Chemical Month Closing | `/m/dyeing/month-closing` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Chemical Opening Balance

`/m/dyeing/stock-opening-balance`

**ট্যাব: Save**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| OB Date* | date | ✔ |  |
| Item Category | select |  | Chemical (IC-CHM), Dyes & Chemicals (IC-DYE), Dyes & Chemicals (CAT-DYECHEM), Reactive Dyes (IC-DYS) |
| 1USD=BDT | number |  | 123 |
| Source* | select | ✔ | Select…, Local, Import |
| Auto? Tick where the drum carries no lot number of its own and the store has to  | checkbox |  |  |
| Lot# | text |  | 1102250014 |
| Exp Date | date |  |  |
| Pack Qty. | number |  |  |
| Qty/Pack | number |  |  |
| Loose Qty | number |  |  |
| Total Qty. | number |  |  |
| Rate BDT | number |  |  |
- সেকশন: New opening row
- বাটন/অ্যাকশন: Save, -- Select Dyes/Chemical Item --, --Pack UoM--, --UoM--, Reset

#### Daily Dye & Chemical Receipt

`/m/dyeing/daily-dyes-chemical-receive`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No. | search |  | MRR-2026080001 |
| MRR Date from | date |  |  |
| MRR Date to | date |  |  |
| Company | select |  | All |
| Requisition Type | select |  | All |
| Supplier | select |  | All |
| Pay Method | select |  | All |
| Store | select |  | All |
| Source Type | select |  | All, Local, Import |
| Challan No | search |  |  |
| L/C No | search |  |  |
| Item Name | search |  | Caustic Soda |
| PR | search |  |  |
| PO | search |  |  |
| Status | select |  | All, Draft, Received, Verified, Cancelled |
| Search Dyes and chemicals received in against a supplier challan, and where each | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: MRR No, MRR DT, Challan No, Company, Supplier, Store, Req. Status, Is Finalized, Is Accounting, Items, ACTION
- বাটন/অ্যাকশন: Receive Dyes & Chemicals, Clear, Add New, Export, MRR No, MRR DT, Challan No, Company, Supplier, Store, Req. Status, Is Finalized, Is Accounting, Items

**ট্যাব: Receive Dyes & Chemicals**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing receipt | select |  |  |
| MRR No | text |  | Issued on save |
| MRR Date* | date | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Source Type* | select | ✔ | Select…, Local, Import |
| Challan No | text |  |  |
| L/C No | text |  |  |
| PR | text |  | PRQ-2026080012 |
| PO | text |  | PO-2026080034 |
| Requisition Type | text |  | Store Requisition |
| Pay Method | text |  | L/C at sight |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Exp Date | date |  |  |
| Qty | number |  |  |
| Rate BDT | number |  |  |
| Remarks | text |  |  |
- সেকশন: Receive Dyes & Chemicals, Receipt, Paperwork, Dyes & Chemicals Received
- বাটন/অ্যাকশন: Receive Dyes & Chemicals, -- Select Supplier --, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save receipt

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receipt | select |  |  |
| MRR No | text |  |  |
| MRR Date* | date | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Source Type* | select | ✔ | Select…, Local, Import |
| Challan No | text |  |  |
| L/C No | text |  |  |
| PR | text |  |  |
| PO | text |  |  |
| Requisition Type | text |  |  |
| Pay Method | text |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Exp Date | date |  |  |
| Qty | number |  |  |
| Rate BDT | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Chemical Loan Opening Balance

`/m/dyeing/loan-opening-balance`

**ট্যাব: Save**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| OB Date* | date | ✔ |  |
| Category | select |  | Chemical (IC-CHM), Dyes & Chemicals (IC-DYE), Dyes & Chemicals (CAT-DYECHEM), Reactive Dyes (IC-DYS) |
| Loan Received | number |  |  |
| Loan Issued | number |  |  |
| Is Final Both sides have agreed the figure. A finalised position stops being edi | checkbox |  |  |
- সেকশন: New loan position
- বাটন/অ্যাকশন: Save, -- Select Supplier --, -- Select Dyes/Chemical Item --, --UoM--, Reset

#### Chemical Item Stock

`/m/dyeing/chemical-item-stock`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Receiving Store | select |  |  |
| Item | search |  | -- Select Dyes/Chemical Item -- |
| Lot# | search |  | 1102250014 |
| Expire Date from | date |  |  |
| Expire Date to | date |  |  |
| Search What each store is holding right now, lot by lot — worked out from every  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Store, Category, Item Name, Lot#, Unit Price, Stock, UoM, Exp Date
- বাটন/অ্যাকশন: Clear, Export, Store, Category, Item Name, Lot#, Unit Price, Stock, UoM, Exp Date

#### Dye & Chemical Receipt Return Register

`/m/dyeing/daily-dyes-chemical-receive-return`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | search |  | RTN-2026080001 |
| MRR No | search |  |  |
| Return Challan Type | select |  | All, Supplier Return, Damage Return, Excess Return, Wrong Item |
| Company | select |  | All |
| Supplier | select |  | All |
| Store | select |  | All |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Chemicals returned to the supplier on a challan, against the receipt they | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Rtn Challan#, Challan Date, MRR No, Return Chalan Type, Company, Supplier, Store, Is Finalized, Remarks, ACTION
- বাটন/অ্যাকশন: Return to Supplier, Clear, Add New, Export, Rtn Challan#, Challan Date, MRR No, Return Chalan Type, Company, Supplier, Store, Is Finalized, Remarks

**ট্যাব: Return to Supplier**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return | select |  |  |
| Rtn Challan# | text |  | Issued on save |
| Challan Date* | date | ✔ |  |
| MRR No* | select | ✔ |  |
| Return Challan Type* | select | ✔ | Supplier Return, Damage Return, Excess Return, Wrong Item |
| Company | select |  | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Return to Supplier, Going Back
- বাটন/অ্যাকশন: Return to Supplier, -- Select Supplier --, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save return

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return | select |  |  |
| Rtn Challan# | text |  |  |
| Challan Date* | date | ✔ |  |
| MRR No* | select | ✔ |  |
| Return Challan Type* | select | ✔ | Supplier Return, Damage Return, Excess Return, Wrong Item |
| Company | select |  | Brickland Composite Ltd. |
| Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Chemical Store Transfer Issue

`/m/dyeing/store-transfer-requisition-and-issue`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| REQ No | search |  | REQ-ST-2026060074 |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Req. Type | select |  | All, Central to Sub, Sub to Sub |
| From Store | select |  | All |
| To Store | select |  | All |
| Status | select |  | All, Waiting for Verification, Verified, Issued, Cancelled |
| Search Chemicals requisitioned from one store by another — what was asked, what  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Gate Pass, Req Date, Req. Type, From Store, To Store, Required (Kg), Issued( Kg), Balance (Kg), ACTION
- বাটন/অ্যাকশন: Raise a Store Transfer, Clear, Add New, Export, REQ No, Gate Pass, Req Date, Req. Type, From Store, To Store, Required (Kg), Issued( Kg), Balance (Kg)

**ট্যাব: Raise a Store Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| To Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass | text |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Raise a Store Transfer, What Moves
- বাটন/অ্যাকশন: Raise a Store Transfer, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save transfer

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| To Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Company | select |  | Brickland Composite Ltd. |
| Gate Pass | text |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Print Chemical Issue Note

`/m/dyeing/print-chemical-issue`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IS-PR-2026080001 |
| Date from | date |  |  |
| Date to | date |  |  |
| Buyer Name | select |  | All |
| Style | select |  | All |
| Print Type | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Print chemicals issued against a buyer's style and print type, with the b | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Buyer, Style, Print Type, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: New Print Chemical Issue, Clear, Add New, Export, REQ No, Req Date, Buyer, Style, Print Type, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks

**ট্যাব: New Print Chemical Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Buyer Name | select |  | LPP (LPP) |
| Print Type | text |  | Pigment |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Print Chemical Issue, Dyes & Chemicals
- বাটন/অ্যাকশন: New Print Chemical Issue, -- Select Style --, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save issue

**ডায়ালগ: New Print Chemical Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Buyer Name | select |  | LPP (LPP) |
| Print Type | text |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Buyer Name | select |  | LPP (LPP) |
| Print Type | text |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Machine Wash & Finishing Issue

`/m/dyeing/machine-wash-and-finishing-issue`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IS-MW-2026080001 |
| Date from | date |  |  |
| Date to | date |  |  |
| Company | select |  | All |
| Requisition Type | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals issued for washing a machine down and for finishing, and what i | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: New M/C Wash & Finishing Issue, Clear, Add New, Export, REQ No, Req Date, Company, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks

**ট্যাব: New M/C Wash & Finishing Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New M/C Wash & Finishing Issue, Dyes & Chemicals
- বাটন/অ্যাকশন: New M/C Wash & Finishing Issue, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save issue

**ডায়ালগ: New M/C Wash & Finishing Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Batch Programme Chemical Issue

`/m/dyeing/batch-program-issue`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IS-BP-2026080001 |
| Req Date from | date |  |  |
| Req Date to | date |  |  |
| Batch No | search |  | B-26080001 |
| M/C No | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals issued against a batch programme, with the machine and process  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Iss Store, Process Type, Batch No, M/C No, TTL Iss Qty, Req Done, Re-Process, ACTION
- বাটন/অ্যাকশন: New Batch Program Issue, Clear, Add New, Export, REQ No, Req Date, Company, Iss Store, Process Type, Batch No, M/C No, TTL Iss Qty, Req Done, Re-Process

**ট্যাব: New Batch Program Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Process Type | text |  | Dyeing |
| Against a Re-Process Ticked, the dye and water are charged to the re-process rat | checkbox |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Batch Program Issue, Dyes & Chemicals
- বাটন/অ্যাকশন: New Batch Program Issue, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save issue

**ডায়ালগ: New Batch Program Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Process Type | text |  |  |
| Against a Re-Process Ticked, the dye and water are charged to the re-process rat | checkbox |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Batch No | select |  |  |
| M/C No | select |  |  |
| Process Type | text |  |  |
| Against a Re-Process Ticked, the dye and water are charged to the re-process rat | checkbox |  |  |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Dye & Chemical Sales

`/m/dyeing/dyes-chemical-sales`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  | IS-SL-2026080001 |
| Date from | date |  |  |
| Date to | date |  |  |
| Company | select |  | All |
| Sales To | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals sold out of the store to another party — the same movement as a | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Sales To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: New Sales Issue, Clear, Add New, Export, REQ No, Req Date, Company, Sales To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks

**ট্যাব: New Sales Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  | Issued on save |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Rate BDT | number |  |  |
| Remarks | text |  |  |
- সেকশন: New Sales Issue, Dyes & Chemicals
- বাটন/অ্যাকশন: New Sales Issue, -- Select Party --, Add Item, Cancel, -- Select Dyes/Chemical Item --, --UoM--, Back To List, Clear, Save issue

**ডায়ালগ: New Sales Issue**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Rate BDT | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| REQ No | text |  |  |
| Req Date* | date | ✔ |  |
| Req. Type* | text | ✔ |  |
| Company | select |  | Brickland Composite Ltd. |
| From Store* | select | ✔ | Chemical Store (ST-CHM), Dyeing Floor (ST-DYE), Finished Goods Store (ST-FGD), Grey Fabric Store (ST-GRY), Maintenance S |
| Remarks | textarea |  |  |
| Lot# | text |  |  |
| Required Qty | number |  |  |
| Rate BDT | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A document with no items moves nothing. Add at least one line.

#### Chemical Comparative Statement

`/m/dyeing/chemical-cs`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| CS Ref | search |  | CS-… |
| CS Date from | date |  |  |
| CS Date to | date |  |  |
| Status | select |  | Draft, Submitted, Approved, Rejected, Closed |
| Chem Catagory | select |  | All |
| Search Comparative statements over dyes and chemical quotations, and where each  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Ref#, Chem Catagory, CS Date, Fab Unit, Mou Code, USD-2-BDT, WorkFlow, Quotes, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, Ref#, Chem Catagory, CS Date, Fab Unit, Mou Code, USD-2-BDT, WorkFlow, Quotes

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| CS Ref | search |  |  |
| CS Date from | date |  |  |
| CS Date to | date |  |  |
| Status | select |  | Draft, Submitted, Approved, Rejected, Closed |
| Chem Catagory | select |  | All |
| Search Comparative statements over dyes and chemical quotations, and where each  | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- খালি Save-এ মেসেজ: 0

#### Chemical Loan Issue Indent

`/m/dyeing/requisition-for-loan-issue`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Sales To | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals requisitioned to be lent out to a sister concern or a subcontra | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Sales To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, REQ No, Req Date, Company, Sales To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Requisition No | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Sales To | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals requisitioned to be lent out to a sister concern or a subcontra | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- খালি Save-এ মেসেজ: 0

#### Chemical Loan Return from Party

`/m/dyeing/loan-return-by-party`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan # | search |  | RTN-… |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Supplier | select |  | All |
| Store | select |  | All |
| Search Chemicals a party has sent back against a loan we issued them, and who to | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Return Challan No, Rtn. Date, Company, Store, Supplier Name, Return Type, Received By, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, Return Challan No, Rtn. Date, Company, Store, Supplier Name, Return Type, Received By

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Challan # | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Supplier | select |  | All |
| Store | select |  | All |
| Search Chemicals a party has sent back against a loan we issued them, and who to | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- খালি Save-এ মেসেজ: 0

#### Dye Chemical Loan Receipt

`/m/dyeing/dc-loan-receive`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| MRR No | search |  | MRR-DC-… |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Pay Method | select |  | All |
| Source Type | select |  | All |
| Challan No | search |  |  |
| L/C No | search |  |  |
| Search Dyes and chemicals borrowed in from a party, received on the party's own  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: MRR No, MRR Date, Company, Rcv Store, Supplier, Pay Method, Source Type, Challan No, L/C #, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, MRR No, MRR Date, Company, Rcv Store, Supplier, Pay Method, Source Type, Challan No, L/C #

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| MRR No | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Pay Method | select |  | All |
| Source Type | select |  | All |
| Challan No | search |  |  |
| L/C No | search |  |  |
| Search Dyes and chemicals borrowed in from a party, received on the party's own  | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- খালি Save-এ মেসেজ: 0

#### Chemical Loan Return Indent

`/m/dyeing/requisition-for-loan-return`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Return To | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals requisitioned to be returned against a loan we borrowed | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: REQ No, Req Date, Company, Return To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, REQ No, Req Date, Company, Return To, Req. Type, From Store, Required Qty, Issued Qty, Balance Qty, Remarks

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Requisition No | search |  |  |
| Month from | date |  |  |
| Month to | date |  |  |
| Company | select |  | All |
| Return To | select |  | All |
| Status | select |  | All, Waiting for Issue, Partial Issued, Issued, Cancelled |
| Search Chemicals requisitioned to be returned against a loan we borrowed | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- খালি Save-এ মেসেজ: 0

#### Chemical Month Closing

`/m/dyeing/month-closing`

**ট্যাব: Month Close**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Period* | text | ✔ | Jul/2026 |
| Remarks | textarea |  |  |
- সেকশন: New closing
- বাটন/অ্যাকশন: Month Close, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **আইটেম আর স্টোর:** ডাই আর কেমিক্যাল হলো Dyes Chemical Store বিভাগের আইটেম (`items`), ক্যাটাগরি `item_categories` থেকে। স্টোর `stores` থেকে, 17/05-এর রিকুইজিশনের মতোই।
- **সাপ্লায়ার আর পার্টি:** `mp_suppliers` (Material Procurement-এর সাপ্লায়ার মাস্টার)। Sales To, লোনের পার্টি আর Return To-ও এখান থেকে আসে।
- **নম্বর:** `DyeBatches::nextNumber()`। pg advisory lock নেয়, আর হাতে লেখা নম্বর পার হয়ে যায়।
- **ধরন:** Knit Production-এর Needle Store (15/07)-এর লেজারের ধাঁচ, অর্থাৎ moves-এর যোগফলই স্টক, আর মাস ক্লোজ হলে নতুন movement আটকায়। তবে কেমিক্যালের লেজার লট ধরে চলে, আর নিজের টেবিলে থাকে।
- **17/05-এর 10-Issue:** এখন এই লেজার থেকে স্টক কমায়।
- **ইউনিট:** মাস্টার থেকে (`UomSelect`, `UomCodes`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_29_000100_create_dye_chem_store.php`, ১৫টা টেবিল:
- **`dye_chem_lots`:** store, item, lot_no (প্রতি স্টোর আর আইটেমে ইউনিক), exp_date, uom, rate_bdt, source। একই কেমিক্যালের দুই ব্যাচের ড্রাম মানে দুটো লট।
- **`dye_chem_moves`:** lot, move_date, kind (OPENING / RECEIPT / LOAN_IN / LOAN_BACK / RETURN / TRANSFER_OUT / TRANSFER_IN / PRINT / WASH / BATCH / SALE / LOAN_OUT / LOAN_RETURN / REQUISITION), doc_no, doc_line_id, qty (চিহ্নসহ, + ঢুকলে, − বের হলে), value। একটা লটের স্টক হলো তার moves-এর যোগফল। ব্যালেন্স আর কোথাও রাখা হয় না।
- **`dye_chem_openings`:** company, store, ob_date, category, usd_rate, item, source, auto_lot, lot_no, exp_date, pack_qty, pack_uom, qty_per_pack, loose_qty, total_qty, uom, rate_bdt, lot। প্রতি স্টোরে প্রতি লটে একটা সারি।
- **`dye_chem_receipts` আর `_lines`:**
  - kind: PURCHASE (MRR-YYYYMM####), LOAN_IN (MRR-DC-…), LOAN_BACK (RTN-LP-…)।
  - হেডার: doc_date, company, store, supplier, source, challan_no, lc_no, pr_no, po_no, req_type, pay_method। LOAN_BACK-এ থাকে return_type আর received_by।
  - status: DRAFT / RECEIVED / VERIFIED / CANCELLED, সঙ্গে finalized_* আর verified_*।
  - লাইন: item, lot_no, exp_date, qty, uom, rate_bdt, remarks, lot।
- **`dye_chem_returns` আর `_lines`:** RTN-YYYYMM####, return_date, receipt, return_type (SUPPLIER / DAMAGE / EXCESS / WRONG_ITEM), supplier আর store (রিসিপ্টেরটাই), status DRAFT / FINALIZED / CANCELLED। লাইনে থাকে receipt_line, item, lot_no, qty, uom।
- **`dye_chem_issues` আর `_lines`:**
  - kind আর prefix: TRANSFER (REQ-ST-), PRINT (IS-PR-), WASH (IS-MW-), BATCH (IS-BP-), SALE (IS-SL-), LOAN_OUT (IS-LN-), LOAN_RETURN (IS-LR-)।
  - হেডার: issue_date, req_type, company, store, to_store, gate_pass, buyer, order, print_type, batch, machine, process_type, is_reprocess, supplier (Sales To / Return To), remarks।
  - status: ট্রান্সফারে WAITING_VERIFICATION → VERIFIED; বাকিগুলোতে WAITING; তারপর PARTIAL / ISSUED / CANCELLED।
  - লাইন: item, lot_no (ঐচ্ছিক), required_qty, issued_qty, uom, rate_bdt (বিক্রিতে বাধ্যতামূলক), remarks।
- **`dye_chem_loan_openings`:** ob_date, supplier, category, item, uom, loan_received, loan_issued, is_final। প্রতি সাপ্লায়ার আর আইটেমে একটা।
- **`dye_chem_cs`, `_lines`, `_quotes`:** CS-YYYYMM####, cs_date, category, factory (Fab Unit), mou_code, usd_rate, remarks, status DRAFT / SUBMITTED / APPROVED / REJECTED / CLOSED, decision_note। লাইনে item, qty, uom, chosen_supplier। কোটেশনে line, supplier, rate_usd, rate_bdt।
- **`dye_chem_closings` আর `_lines`:** period (YYYY-MM, ইউনিক), remarks, lots, closing_qty, closing_value, closed_by। প্রতিটা লাইনে মাস শেষের লট ব্যালেন্স আর মূল্য।

## ৫. Workflow / Status ও Business Rules

- **লট আর স্টক:**
  - স্টক রাখা হয় লট ধরে (স্টোর + আইটেম + লট নম্বর)। লট নম্বর trim আর বড় হাতের অক্ষরে রাখা হয়।
  - Chemical Item Stock-এ দেখায় যেসব লটে স্টক আছে: স্টোর, ক্যাটাগরি, আইটেম, লট, একক দাম, স্টক, ইউনিট, মেয়াদ।
- **Opening Balance:**
  - সেভ করলেই লটের ওপেনিং স্টকে ঢোকে।
  - একই স্টোরে একই লট দুবার দেওয়া যায় না।
  - Total ফাঁকা রাখলে প্যাক × প্রতি প্যাক + লুজ ধরা হয়। গোনা সংখ্যা দিলে সেটাই থাকে।
  - Auto? টিক দিলে স্টোর লট নম্বর দেয় (OB + YYMM + ####)।
  - লটে অন্য কোনো movement হওয়ার পরে ওপেনিং আর বদলানো বা মোছা যায় না।
- **Receipt (তিন ধরন):**
  - খসড়া (DRAFT) অবস্থায় স্টকে কিছু ঢোকে না, আর ইচ্ছামতো বদলানো যায়।
  - রেজিস্টারে Finalise করলে প্রতিটা লাইন নিজের লটে ঢোকে (RECEIVED)। লট না থাকলে তৈরি হয়, মেয়াদ, রেট আর সোর্স নিয়ে।
  - Verify (শুধু কেনার রিসিপ্টে) মানে অ্যাকাউন্টস মেনে নিয়েছে: VERIFIED, Is Accounting = Yes।
  - Cancel শুধু খসড়ায়।
  - কেনা আর লোন রিসিপ্টে Source Type লাগবে। লোনে সাপ্লায়ার লাগবে। LOAN_BACK-এ Return Type লাগবে।
- **Receipt Return:**
  - শুধু ফাইনাল করা কেনার রিসিপ্টের বিপরীতে হয়। রিসিপ্ট বাছলে সাপ্লায়ার আর স্টোর রিসিপ্ট থেকে আসে।
  - প্রতিটা লাইন রিসিপ্টের একটা লট। সেই লাইনে যা এসেছিল, তার থেকে অন্য চালু রিটার্ন বাদ দিয়ে যা থাকে, তার বেশি ফেরত যায় না।
  - তারিখ রিসিপ্টের আগে হতে পারে না।
  - Finalise করলে সেই লট থেকে স্টক কমে। লটে না থাকলে আটকায়।
- **Issue (সাত ধরন):**
  - তোলার সময় স্টক নড়ে না।
  - রেজিস্টারের Issue dialog-এ প্রতিটা লাইনের চাওয়া, ইস্যু হওয়া, বাকি আর স্টোরে থাকা পরিমাণ দেখায়। স্টোর এখন কত দেবে তা লেখে, বাকির বেশি নয়।
  - লট দেওয়া থাকলে সেই লট থেকে নেয়। না থাকলে যেটার মেয়াদ আগে শেষ সেটা থেকে নেয় (মেয়াদ না থাকা লট শেষে)।
  - স্টোরে কম থাকলে আটকায় আর জানায় কত আছে।
  - সব দেওয়া হলে ISSUED, কম দিলে PARTIAL।
  - Cancel করলে বাকি অংশ বাদ যায়, যা ইস্যু হয়েছে তা থাকে।
  - শুধু WAITING বা WAITING_VERIFICATION অবস্থায় বদলানো যায়।
- **Store Transfer:**
  - দুটো আলাদা স্টোর লাগে। Req. Type হয় Central to Sub বা Sub to Sub।
  - আগে Verify, তারপর Issue।
  - যা ইস্যু হয়, তা একই লট নম্বর, মেয়াদ আর রেট নিয়ে যে স্টোরে যাচ্ছে সেখানে ঢোকে।
  - Gate Pass ইস্যুর সময় লেখা যায়।
- **বিক্রি:** প্রতিটা লাইনে রেট লাগে। রেজিস্টারে টাকার পরিমাণ দেখায়। স্টক থেকে বের হয় লটের দামে।
- **ব্যাচ ইস্যু:** batch বাছলে তার মেশিন বসে। Against a Re-Process টিক দেওয়া যায়। রেজিস্টারে Req Done আর Re-Process দেখায়।
- **লোন:**
  - আমাদের দেনা = ওপেনিং Loan Received + ফাইনাল করা লোন রিসিপ্ট − Loan Return Indent। নতুন Return Indent এর বেশি হতে পারে না (চালু ইনডেন্টগুলোর চাওয়া পরিমাণও বাদ যায়)।
  - পার্টির দেনা = ওপেনিং Loan Issued + Loan Issue Indent-এ ইস্যু হওয়া − চালু Loan Return from Party। পার্টি এর বেশি ফেরত দিতে পারে না।
  - Loan Opening Balance-এ প্রতি সাপ্লায়ার আর আইটেমে একটা পজিশন থাকে। ফাইনাল হলে আর বদলানো বা মোছা যায় না। এতে স্টক নড়ে না।
- **Comparative Statement:**
  - সাপ্লায়ারদের কলামে রেট দেওয়া হয়, টাকায় বা ডলারে। ডলারে দিলে USD-2-BDT রেট লাগে, আর টাকা নিজে থেকে হিসাব হয়।
  - সবচেয়ে কম টাকার রেট চিহ্নিত থাকে।
  - বাছাই করা সাপ্লায়ারের ওই আইটেমে কোটেশন থাকতে হবে।
  - Submit-এর আগে প্রতিটা আইটেমে কোটেশন আর সাপ্লায়ার বাছা লাগবে।
  - ধাপ: Draft → Submit → Approve বা Reject → Close। Reject হলে নোট থাকে, আর সেভ করলে আবার Draft হয়।
- **Month Closing:**
  - Period লেখা যায় 2026-07 বা Jul/2026 আকারে। ভবিষ্যতের মাস বা আগে ক্লোজ হওয়া মাস দেওয়া যায় না।
  - মাসে খোলা কাগজ থাকলে আটকায় আর তালিকা দেখায়: খসড়া রিসিপ্ট বা রিটার্ন, শেষ না হওয়া ইস্যু, অপেক্ষায় থাকা 17/05 রিকুইজিশন।
  - ক্লোজ হলে মাস শেষের প্রতিটা লটের ব্যালেন্স আর মূল্য রাখা হয়।
  - ক্লোজ হওয়া মাসে (বা তার আগের কোনো মাসে) আর কোনো পোস্টিং হয় না: ওপেনিং, রিসিপ্ট, রিটার্ন, ইস্যু, 10-Issue সব আটকায়।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.chemstore.view`: FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER।
  - `dyehouse.chemstore.manage`: ওই তিনজনই।
  - `dyehouse.chemstore.issue` (Finalise আর Issue): FACTORY_ADMIN আর STORE_MANAGER।
  - `dyehouse.chemstore.approve` (রিসিপ্ট Verify, CS Approve/Reject): FACTORY_ADMIN আর FACTORY_MANAGER।
  - `dyehouse.chemstore.close` (Month Close): FACTORY_ADMIN আর STORE_MANAGER।
- **Screen-kit resource:**
  - `/api/v1/erp/dye-chem-openings`
  - `/api/v1/erp/dye-chem-loan-openings`
- **API:**
  - `GET /dyehouse/chem-store/options`, `GET /dyehouse/chem-store/stock`
  - `GET|POST /dyehouse/chem-receipts?kind=`, `GET|PUT /dyehouse/chem-receipts/{no}`, `GET /dyehouse/chem-receipts/{no}/return-source`, `POST /dyehouse/chem-receipts/{no}/{finalise|verify|cancel}`
  - `GET|POST /dyehouse/chem-returns`, `GET|PUT /dyehouse/chem-returns/{no}`, `POST /dyehouse/chem-returns/{no}/{finalise|cancel}`
  - `GET|POST /dyehouse/chem-issues?kind=`, `GET|PUT /dyehouse/chem-issues/{no}`, `POST /dyehouse/chem-issues/{no}/{verify|issue|cancel}`
  - `GET|POST /dyehouse/chem-cs`, `GET|PUT /dyehouse/chem-cs/{no}`, `POST /dyehouse/chem-cs/{no}/{submit|approve|reject|close}`
  - `GET|POST /dyehouse/chem-closings`
- **মেনু:** Dye House-এর নতুন ফোল্ডার CHEMICAL STORE, ১৬টা স্ক্রিন, Brickland-এর ক্রমে।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** সব স্ক্রিন `dye-house/[screen]`-এ, `chem-screens.js` থেকে। কম্পোনেন্ট `components/dyehouse/chem-store.jsx`-এ।
- **Opening, Loan Opening আর Month Closing:** Records আর ফর্ম ট্যাব (Brickland-এর "Save" / "Month Close")। Records-এ Edit আর Delete একই পাতায়।
- **Receipt (তিন ধরন), Return, Issue (সাত ধরন), CS:**
  - Records আর editor ট্যাব। editor-এ আগের কাগজ খোলার জন্য "Existing …" select।
  - হেডারের অংশগুলো Brickland-এর মতো (Receipt / Paperwork, Going Back, What Moves…)।
  - লাইনের এন্ট্রি সারির নিচে Add Item আর Cancel, তারপর লাইনের টেবিল। টেবিলের প্রতিটা ঘর এডিট করা যায়।
- **রেজিস্টার:**
  - সারির বাটন: Edit (`?tab=entry&doc=…` দিয়ে editor খোলে), Finalise, Verify, Issue, Cancel, Submit, Approve, Reject, Close।
  - প্রতিটা বাটন অবস্থা অনুযায়ী চালু বা বন্ধ থাকে, আর কাজের আগে নিশ্চিত করতে হয়।
  - Issue বাটন একটা sheet খোলে: লাইন ধরে চাওয়া, ইস্যু হওয়া, বাকি আর স্টোরে থাকা পরিমাণ, আর এখন কত দেওয়া হবে। ট্রান্সফারে Gate Pass-ও লেখা যায়।
- **CS editor:** সাপ্লায়ার যোগ করলে কলাম বাড়ে। কোটেশন টাকায় বা ডলারে। সবচেয়ে কম রেটের ঘর সবুজ। প্রতিটা সারিতে বাছাই করা সাপ্লায়ার।
- **Brickland-এ ভাঙা স্ক্রিন:** Brickland-এ CS, Loan Issue Indent, Loan Return from Party, Loan Receipt আর Loan Return Indent-এর "Add New" কিছুই খোলে না। পাশের স্ক্রিনগুলোর ধাঁচে এগুলো পুরোপুরি বানানো হয়েছে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১৬টার ১৬টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_29_000100_create_dye_chem_store.php`
- `app/Modules/DyeHouse/Models/`: `DyeChemLot`, `DyeChemMove`, `DyeChemOpening`, `DyeChemReceipt`, `DyeChemReceiptLine`, `DyeChemReturn`, `DyeChemReturnLine`, `DyeChemIssue`, `DyeChemIssueLine`, `DyeChemLoanOpening`, `DyeChemCs`, `DyeChemCsLine`, `DyeChemCsQuote`, `DyeChemClosing`, `DyeChemClosingLine`
- `app/Modules/DyeHouse/Support/ChemStore.php` (লেজার: লট, FEFO take, মাস লক, exceptions, লোনের হিসাব)
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeChemReceiptApiController.php`, `DyeChemIssueApiController.php`, `DyeChemStoreApiController.php` (নতুন)
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeRequisitionApiController.php` (বদলানো: 10-Issue স্টক থেকে কমায়)
- `app/Modules/DyeHouse/Resources/DyeChemOpeningResource.php`, `DyeChemLoanOpeningResource.php`
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `chem` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/chem-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/chem-store.jsx` (`ChemReceiptEditor`, `ChemReturnEditor`, `ChemIssueEditor`, `ChemCsEditor`, `ChemRegister`)

**Test**
- `tests/Feature/DyeHouse/DyeHouseChemStoreTest.php` (নতুন)
- `tests/Feature/DyeHouse/DyeHouseRequisitionTest.php` (বদলানো: স্টোরে ওপেনিং স্টক, 10-Issue-এর পরে স্টক)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseChemStoreTest.php`):** test2 DB-তে পাস, ৭টা টেস্ট, ২৩৫টা assertion।
- `test_opening_balance_books_each_lot_into_stock`:
  - লট না দিলে আর মোট শূন্য হলে আটকায়।
  - প্যাক × প্রতি প্যাক + লুজ = ১০৩.৫। লট নম্বর বড় হাতে, ইউনিট KG, মূল্য ১৮,৬৩০।
  - একই লট দুবার দিলে আটকায়। Auto? দিলে লট নম্বর OB…0001, গোনা মোট রাখা হয়।
  - নড়াচড়ার আগে recount চলে। ইস্যুর পরে বদলানো বা মোছা আটকায়।
- `test_receipts_count_in_once_finalised_and_returns_go_back_against_them`:
  - তিনটা বার্তা দেখা হয়েছে। খসড়ায় স্টক শূন্য।
  - Finalise করলে লট, রেট আর মেয়াদ নিয়ে স্টক ঢোকে। তারপর বদলানো আটকায়, Verify করলে Is Accounting হয়।
  - খসড়া রিসিপ্টের বিপরীতে রিটার্ন আটকায়। রিসিপ্টের লাইনের চেয়ে বেশি ফেরত আটকায়।
  - রিটার্ন Finalise করলে স্টক ২০০ থেকে ১৮০ হয়।
- `test_issues_take_the_soonest_to_expire_first_and_transfers_land_in_the_receiving_store`:
  - কম স্টকে ইস্যু আটকায়, কত আছে জানায়। বাকির চেয়ে বেশি ইস্যু আটকায়।
  - লট ছাড়া ইস্যু আগে মেয়াদ শেষ হওয়া লট থেকে নেয়। কম দিলে PARTIAL হয়, Cancel করলে বাকি ০।
  - একই স্টোরে ট্রান্সফার আটকায়। Verify-এর আগে ইস্যু আটকায়।
  - ইস্যু হলে একই লট আর মেয়াদ নিয়ে সাব-স্টোরে ঢোকে, Gate Pass লেখা থাকে।
  - বিক্রিতে পার্টি আর রেট লাগে।
- `test_loans_both_ways_stay_within_what_is_owed`:
  - ওপেনিং ফাইনাল হলে আটকায়। লোন রিসিপ্ট MRR-DC-।
  - দেনার চেয়ে বেশি Return Indent আটকায়। চালু ইনডেন্টের পরিমাণও বাদ যায়।
  - Loan Issue IS-LN-। পার্টি যা পাওনা তার বেশি ফেরত দিলে আটকায়। Finalise করলে স্টকে ফেরে।
- `test_comparative_statement_runs_draft_to_closed`:
  - ডলারে কোট দিলে USD রেট লাগে, ১.৩৮ × ১২২ = ১৬৮.৩৬।
  - কোট না দেওয়া সাপ্লায়ার বাছলে আটকায়। সাপ্লায়ার না বাছলে Submit আটকায়।
  - ধাপ: Submit, Reject (নোটসহ), সেভ করলে আবার Draft, Submit, Approve, Close।
- `test_month_closing_lists_open_paper_and_then_locks_the_month`:
  - ভুল মাস, ভবিষ্যতের মাস আর খোলা কাগজ থাকলে আটকায়, তালিকা দেখায়।
  - ক্লোজ হলে ৩৬ পরিমাণ আর ৫,৪০০ টাকা রাখা হয়। একই মাস দুবার ক্লোজ আটকায়।
  - ক্লোজ মাসে ওপেনিং আর রিসিপ্ট আটকায়। চলতি মাসে চলে।
- `test_who_may_raise_issue_approve_and_close`: FACTORY_MANAGER ইস্যু বা ক্লোজ করতে পারে না। STORE_MANAGER ইস্যু আর ক্লোজ করতে পারে, তবে CS অনুমোদন দিতে পারে না। TECHNICIAN কিছুই পায় না।

**17/05-এর test (`DyeHouseRequisitionTest`, বদলানো):** ৪টা টেস্ট, ১০৭টা assertion। স্টোরে ওপেনিং স্টক দেওয়া থাকে, আর 10-Issue-এর পরে স্টক ৯৫.৫ আর ৮৮ হয়।

**Regression:** `DyeHouseLabDipTest` (৩টা টেস্ট, ৯৩টা assertion), `DyeHouseBatchTest` (৫টা, ১৭৩টা), `DyeHouseSampleTest` (৩টা, ৯৯টা) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/cs01`), dev অ্যাপে:**
- **Opening Balance:** চারটা লট, Chemical & dyes store (DHK-CHM)-এ, ২০২৬-১১-০১ তারিখে: CS-2611 (Caustic ৫০০ KG), AA-2611 (Acetic ২০০ LTR), HP-2611 (Hydrogen ৩০০ LTR), SA-2611 (Soda Ash ৪০০ KG)। Item Stock-এ চারটাই দেখা গেছে।
- **Receipt:** MRR-2026110001 (Square Textiles, Import, CH-2611-77, দুটো লট CS-2611B ২৫০ আর SA-2611B ৩০০)। নিশ্চিত করে Finalise, তারপর Verify, শেষে VERIFIED।
- **Return:** RTN-2026110001, MRR-2026110001-এর বিপরীতে, Damage, CS-2611B থেকে ১০। Finalise-এর পরে লটে ২৪০ থাকে।
- **M/C Wash Issue:** IS-MW-2026110001 (Hydrogen ১২, Acetic ৪)। Issue sheet-এ Acetic ২ দেওয়া হয়েছে, ফলে PARTIAL।
- **Store Transfer:** REQ-ST-2026110001, DHK-CHM থেকে GAZ-CHM, CS-2611-এর ৪০। Verify, তারপর Issue (Gate Pass GP-1105)। GAZ-CHM-এ ৪০ ঢুকেছে।
- **17/05 Print desk:** IR-DC-2026110002-এর 10-Issue এখন স্টক থেকে কমেছে (Acetic ২০০ − ২ − ৩ = ১৯৫)।
- **Loan Opening:** Square Textiles, Soda Ash, ধার নেওয়া ৫০।
- **CS:** CS-2026110001 (USD ১২২, MOU-26, Caustic ১০০০, Square ৯৬ আর Coats ৯৩.৫, Coats বাছা হয়েছে)। Submit-এর পরে SUBMITTED।
- **Month Close:** Nov/2026 দিলে আটকায়, দুটো খোলা কাগজের তালিকা দেখায় (IS-MW-2026110001, IR-DC-2026110003)। কোনো মাস লক হয়নি।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।
- **দেখার পরে ঠিক করা:** Item Stock-এ দুটো স্টোরের নাম একই দেখাচ্ছিল। এখন রেজিস্টার আর স্টকে স্টোরের কোডসহ দেখায় (Chemical & dyes store (DHK-CHM))।

**প্রিন্ট:** Brickland-এর কোনো স্ক্রিনেই Print বাটন নেই (Records-এ Export আছে, সেটা RecordsGrid-এর Export দিয়ে মেলে)।

## ১০. খোলা প্রশ্ন

১. **Loan Return from Party-র Return Type:** Brickland-এর ফর্ম খোলে না, তাই অপশন জানা নেই। আমরা দুটো রেখেছি: Full Return আর Partial Return। আসল অপশনগুলো জানাবেন।

২. **Sales To আর লোনের পার্টি:** এখন সাপ্লায়ার মাস্টার (`mp_suppliers`) থেকে আসে। সিস্টার কনসার্ন বা সাবকন্ট্রাক্টরের জন্য আলাদা পার্টি মাস্টার লাগবে কিনা জানাবেন।

৩. **Is Accounting:** এখন রিসিপ্ট Verify করলে Yes হয়। আসল অ্যাকাউন্টিং (ভাউচার) জোড়া হবে Accounts মডিউলের সময়।

৪. **CS-এর Fab Unit আর Mou Code:** Fab Unit-কে ফ্যাক্টরি ইউনিট ধরা হয়েছে (`factories`), Mou Code রাখা হয়েছে লেখা হিসেবে। Brickland-এর demo-তে কোনো CS নেই, তাই এর মানে নিশ্চিত হওয়া যায়নি।

৫. **CS থেকে Purchase Order:** অনুমোদিত CS থেকে এখনো কিছু তৈরি হয় না। কেমিক্যাল কেনার PO-র স্ক্রিন কোন মডিউলে থাকবে (Material Procurement নাকি এখানে), সেটা ঠিক হলে জোড়া হবে।

৬. **17/05 রিকুইজিশন আর 17/06 ইস্যু:** দুটো আলাদা কাগজ, আর দুটোই স্টক কমায়। 10-Issue পুরো পরিমাণ যেটার মেয়াদ আগে শেষ সেই লট থেকে নেয়। Batch Programme Chemical Issue-এর মতো ইস্যু কি রিকুইজিশনের বিপরীতে হবে (রিকুইজিশন বাছলে লাইন আসবে), জানাবেন।

৭. **Month Closing:** কোম্পানির সব কেমিক্যাল স্টোর একসাথে ক্লোজ হয় (Brickland-এর ফর্মে স্টোর নেই)। ক্লোজ ফেরানোর (reopen) কোনো উপায় নেই।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/cs01`, `cs02`):**
  - ১৬টা স্ক্রিনের ফর্ম, হিন্ট, গ্রিড আর ফিল্টার দেখা হয়েছে।
  - demo-তে কোনো স্ক্রিনে রেকর্ড নেই, তাই সারির বাটন দেখা যায়নি। Finalise, Verify, Issue আর Cancel এসেছে Brickland-এর হিন্ট থেকে ("finalises the receipt on the register", "verified and issued on the register")।
- **নম্বরের ফরম্যাট:** Brickland-এর placeholder অনুযায়ী: MRR-, MRR-DC-, RTN-, REQ-ST-, IS-PR-, IS-MW-, IS-BP-, IS-SL-, CS-। নিজেদের দেওয়া: IS-LN-, IS-LR-, RTN-LP-, আর Auto লটের OB।
- **একটা editor, অনেক স্ক্রিন:** তিন ধরনের রিসিপ্ট একটা editor-এ, সাত ধরনের ইস্যু আরেকটায়। শুধু kind আর হেডারের ফিল্ড বদলায়।
