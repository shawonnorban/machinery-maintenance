# Batch (Bulk)

> **স্ট্যাটাস:** ✅ সম্পন্ন (১৪টার ১৪টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/03`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dye Batch Plan | `/m/dyeing/dyeing-batch-plan` | 2 | 14 | 1 |
| 2 | Bulk Grey Fabric Indent | `/m/dyeing/grey-fabric-requisition-bulk` | 1 | 3 | 1 |
| 3 | Bulk Grey Fabric Issue Register | `/m/dyeing/grey-fabric-requisition-issue-list-bulk` | 1 | 7 | 0 |
| 4 | Batch Card Splitting | `/m/dyeing/batch-card-split` | 1 | 22 | 1 |
| 5 | Sub-Contract Pre-Treatment Programme | `/m/dyeing/sc-pre-treatment-program-heat-set-etc` | 2 | 8 | 1 |
| 6 | Sub-Contract Pre-Treatment Challan | `/m/dyeing/sc-pre-treatment-challan` | 2 | 6 | 1 |
| 7 | Sub-Contract Pre-Treatment Receipt | `/m/dyeing/sc-pre-treatment-program-receive` | 2 | 9 | 1 |
| 8 | Sub-Contract Pre-Treatment Bill | `/m/dyeing/sc-pre-treatment-bill` | 2 | 11 | 1 |
| 9 | Dye Batch from Finished Fabric | `/m/dyeing/dyeing-batch-src-f-fab` | 1 | 4 | 1 |
| 10 | Order-Wise Grey Transfer from Batch Store | `/m/dyeing/order-wise-grey-fabric-trans-from-batch-store` | 2 | 12 | 1 |
| 11 | Batch Colour Replacement Transfer | `/m/dyeing/dyeing-color-transfer-replace` | 2 | 13 | 2 |
| 12 | Batch Transfer to Leftover | `/m/dyeing/batch-transfer-to-left-over` | 2 | 15 | 1 |
| 13 | Grey to Leftover Transfer | `/m/dyeing/gray-2-leftover-transfer-batch-end` | 1 | 0 | 0 |
| 14 | Manual Dye Batch Entry | `/m/dyeing/manual-batch-for-dyeing` | 2 | 14 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dye Batch Plan

`/m/dyeing/dyeing-batch-plan`

**ট্যাব: Batch Plan Board**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Plan | select |  |  |
| Company/Party | select |  | All companies, Brickland Composite Ltd. |
| S/B | select |  | All, Bulk, Sample |
| Scale | select |  | Scale, 1Hr, 2Hr, 3Hr, 4Hr, 6Hr, 8Hr, 12Hr, 1 Day |
| Cell Width(%) | number |  |  |
- সেকশন: Batch Plan Board
- বাটন/অ্যাকশন: Plan List 0, Batch Plan Board, New Plan, Move, Program

**ট্যাব: New Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Plan Code | text |  | Left blank, the screen issues one |
| Plan Name* | text | ✔ | August 2026 — Bulk Loading |
| Plan Date* | date | ✔ |  |
| Company/Party | select |  | Brickland Composite Ltd. |
| S/B* | select | ✔ | Select…, Bulk, Sample |
| Scale* | select | ✔ | Select…, 1Hr, 2Hr, 3Hr, 4Hr, 6Hr, 8Hr, 12Hr, 1 Day |
| Cell Width(%)* | number | ✔ |  |
| Remarks | textarea |  | Why this plan was re-cut, what it supersedes |
| Is Active? A retired plan drops out of the board's Search Plan box; its batches  | checkbox |  |  |
- সেকশন: New batch plan
- বাটন/অ্যাকশন: Plan List 0, Batch Plan Board, New Plan, Reset

**ডায়ালগ: New Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Plan Code | text |  |  |
| Plan Name* | text | ✔ |  |
| Plan Date* | date | ✔ |  |
| Company/Party | select |  | Brickland Composite Ltd. |
| S/B* | select | ✔ | Select…, Bulk, Sample |
| Scale* | select | ✔ | Select…, 1Hr, 2Hr, 3Hr, 4Hr, 6Hr, 8Hr, 12Hr, 1 Day |
| Cell Width(%)* | number | ✔ |  |
| Remarks | textarea |  |  |
| Is Active? A retired plan drops out of the board's Search Plan box; its batches  | checkbox |  |  |

#### Bulk Grey Fabric Indent

`/m/dyeing/grey-fabric-requisition-bulk`

**ট্যাব: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch#* | select | ✔ | search Batch#… |
| Dyeing Rate* | select | ✔ | White · water 0 / wash 0, Light · water 0 / wash 0, Medium · water 0 / wash 0, Dark · water 0 / wash 0, Extra Dark · wat |
| Proj Rate* | number | ✔ | 52.00 |
- সেকশন: New requisition
- বাটন/অ্যাকশন: Create Requisition, -- Select Store --, Reset

**ডায়ালগ: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch#* | select | ✔ | search Batch#… |
| Dyeing Rate* | select | ✔ | White · water 0 / wash 0, Light · water 0 / wash 0, Medium · water 0 / wash 0, Dark · water 0 / wash 0, Extra Dark · wat |
| Proj Rate* | number | ✔ |  |
- খালি Save-এ মেসেজ: Batch# is required.

#### Bulk Grey Fabric Issue Register

`/m/dyeing/grey-fabric-requisition-issue-list-bulk`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Order/Style | select |  |  |
| Color | select |  |  |
| Requisition Store | select |  |  |
| Batch/Pre-Treatment No | search |  | B-26080001 |
| Search Every grey fabric requisition raised against a batch — what was asked for | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Req Date, Req(Kg), Issue(Kg), Batch No, Items, Requisition Type, Company, ACTION
- বাটন/অ্যাকশন: Clear, Export, Requisition No, Req Date, Req(Kg), Issue(Kg), Batch No, Items, Requisition Type, Company

#### Batch Card Splitting

`/m/dyeing/batch-card-split`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Batch Plan | search |  | search Batch#… |
| Batch No | text |  | Pick one on the left |
| Present Batch plan Qty | text |  |  |
| Present Batch Card Qty | text |  |  |
| Present Lot No | text |  |  |
| Old Machine | text |  |  |
| New Batch plan Qty* | number | ✔ |  |
| New Batch Card Qty* | number | ✔ |  |
| New Lot No* | text | ✔ |  |
| New Machine | select |  |  |
| Item | select |  |  |
| New Batch Qty(Kg) | number |  |  |
| New Issue Qty(Kg) | number |  |  |
| New Roll Qty | number |  |  |
| New Issue Roll Qty | number |  |  |
| New Qty(PCs) | number |  |  |
| New Issue Qty(PCs) | number |  |  |
| New Issue Roll | number |  |  |
| New YDs | number |  |  |
| New Kg | number |  |  |
| New Issue Pcs | number |  |  |
| New Issue Kg | number |  |  |
- গ্রিড কলাম: Batch No, Qty(Kg)
- সেকশন: Batch Plan, Batch Card Split, Gery fab(Knit Fab), Collar Cuff, Trims
- বাটন/অ্যাকশন: Add, Cancel, Clear, Save split

**ডায়ালগ: Add**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Batch Plan | search |  |  |
| Batch No | text |  |  |
| Present Batch plan Qty | text |  |  |
| Present Batch Card Qty | text |  |  |
| Present Lot No | text |  |  |
| Old Machine | text |  |  |
| New Batch plan Qty* | number | ✔ |  |
| New Batch Card Qty* | number | ✔ |  |
| New Lot No* | text | ✔ |  |
| New Machine | select |  |  |
| Item | select |  |  |
| New Batch Qty(Kg) | number |  |  |
| New Issue Qty(Kg) | number |  |  |
| New Roll Qty | number |  |  |
| New Issue Roll Qty | number |  |  |
| New Qty(PCs) | number |  |  |
| New Issue Qty(PCs) | number |  |  |
| New Issue Roll | number |  |  |
| New YDs | number |  |  |
| New Kg | number |  |  |
| New Issue Pcs | number |  |  |
| New Issue Kg | number |  |  |
- খালি Save-এ মেসেজ: Pick the batch to split on the left first.

#### Sub-Contract Pre-Treatment Programme

`/m/dyeing/sc-pre-treatment-program-heat-set-etc`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Program # | search |  | DSC-PT-… |
| Supplier | select |  | All |
| Despatched? | select |  | All, Despatched, Awaiting challan |
| Search Every pre-treatment programme sent to a subcontractor — when it was issue | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Issue Date, Exp. Delivery Date, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Program No, Issue Date, Exp. Delivery Date, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Issue Date* | date | ✔ |  |
| Exp. Delivery Date | date |  |  |
| Gate Pass No | text |  | GP-0001 |
- সেকশন: New programme
- বাটন/অ্যাকশন: Add New, -- Select Supplier --, -- Select Store --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Issue Date* | date | ✔ |  |
| Exp. Delivery Date | date |  |  |
| Gate Pass No | text |  |  |
- খালি Save-এ মেসেজ: Issue Date is required.

#### Sub-Contract Pre-Treatment Challan

`/m/dyeing/sc-pre-treatment-challan`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan # | search |  | CLN-PT-… |
| Supplier | select |  | All |
| Search Every challan fabric left on for subcontract pre-treatment, and how many  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Programs, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Programs

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  | GP-0001 |
- সেকশন: New challan
- বাটন/অ্যাকশন: Add New, -- Select Supplier --, -- Select Store --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Challan Date* | date | ✔ |  |
| Gate Pass No | text |  |  |
- খালি Save-এ মেসেজ: Supplier is required.

#### Sub-Contract Pre-Treatment Receipt

`/m/dyeing/sc-pre-treatment-program-receive`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| S/C Party | select |  |  |
| Transfer Party | select |  |  |
| Challan # | search |  |  |
| Program # | search |  |  |
| Search Every return of subcontract pre-treated fabric, the challan it came back  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: MRR No, Issue Date, QC No, Challan No, Challan Date, Supplier, Transfer Party, Store, Program List, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, MRR No, Issue Date, QC No, Challan No, Challan Date, Supplier, Transfer Party, Store, Program List

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Issue Date* | date | ✔ |  |
| QC No | text |  | QC-0001 |
| Challan | select |  |  |
- সেকশন: New receive
- বাটন/অ্যাকশন: Add New, -- Select S/C Party --, -- Select S/C Transfer Party --, -- Select Store --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Issue Date* | date | ✔ |  |
| QC No | text |  |  |
| Challan | select |  |  |
- খালি Save-এ মেসেজ: S/C Party is required.

#### Sub-Contract Pre-Treatment Bill

`/m/dyeing/sc-pre-treatment-bill`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Party | select |  |  |
| Status | select |  | All, Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
| Search Every subcontract pre-treatment bill, its revision trail and where it sta | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Bill#, Ref.Bill#, Rev#, Rev.Descr, Supplier, Amount, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Date, Bill#, Ref.Bill#, Rev#, Rev.Descr, Supplier, Amount

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill#* | text | ✔ | 22647 |
| Date* | date | ✔ |  |
| Amount* | number | ✔ |  |
| Ref.Bill# | text |  | The bill this one revises |
| Rev# | number |  |  |
| Rev.Descr | textarea |  |  |
| Status* | select | ✔ | Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
- সেকশন: New bill
- বাটন/অ্যাকশন: Add New, -- Select --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Bill#* | text | ✔ |  |
| Date* | date | ✔ |  |
| Amount* | number | ✔ |  |
| Ref.Bill# | text |  |  |
| Rev# | number |  |  |
| Rev.Descr | textarea |  |  |
| Status* | select | ✔ | Draft, Waiting For Store Approval, Approved, Completed, Cancelled |
- খালি Save-এ মেসেজ: Amount is required.

#### Dye Batch from Finished Fabric

`/m/dyeing/dyeing-batch-src-f-fab`

**ট্যাব: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch Qty* | number | ✔ |  |
| Lot No | text |  |  |
| Machine | select |  |  |
| Buyer | select |  | LPP (LPP) |
- সেকশন: New finished-fabric batch
- বাটন/অ্যাকশন: Create Requisition, -- Select Colour --, -- Select Store --, Reset

**ডায়ালগ: Create Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch Qty* | number | ✔ |  |
| Lot No | text |  |  |
| Machine | select |  |  |
| Buyer | select |  | LPP (LPP) |
- খালি Save-এ মেসেজ: Color is required.

#### Order-Wise Grey Transfer from Batch Store

`/m/dyeing/order-wise-grey-fabric-trans-from-batch-store`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| From Order# | select |  |  |
| Trans Ref# | search |  | GF/OTR/… |
| Search Grey fabric moved between orders while it sits in the batch store, and wh | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Transfer#, Trans Type, Remarks, ACTION
- বাটন/অ্যাকশন: New, Clear, Export, Date, Transfer#, Trans Type, Remarks

**ট্যাব: New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Trans Type* | select | ✔ | Bulk To Bulk, Bulk To Sample, Sample To Bulk, Bulk To Left-Over |
| Buyer A/C | select |  | LPP (LPP) |
| From Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| To Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Status* | select | ✔ | 10-Draft, 30-Receive Requisition, 50-Submit Requisition, 90-Completed, 99-Cancelled |
| Remarks | textarea |  | B-26070197 > B-26071068 |
- সেকশন: New transfer
- বাটন/অ্যাকশন: New, Reset

**ডায়ালগ: New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Date* | date | ✔ |  |
| Trans Type* | select | ✔ | Bulk To Bulk, Bulk To Sample, Sample To Bulk, Bulk To Left-Over |
| Buyer A/C | select |  | LPP (LPP) |
| From Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| To Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Status* | select | ✔ | 10-Draft, 30-Receive Requisition, 50-Submit Requisition, 90-Completed, 99-Cancelled |
| Remarks | textarea |  |  |

#### Batch Colour Replacement Transfer

`/m/dyeing/dyeing-color-transfer-replace`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Transfer No | search |  |  |
| Transfer Date from | date |  |  |
| Transfer Date to | date |  |  |
| Batch No | search |  |  |
| Search Batches moved to a different shade before they ran, with the number each  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Batch No, New Batch No, Lot No, Batch Date, Batch Qty, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Batch No, New Batch No, Lot No, Batch Date, Batch Qty

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Batch No* | select | ✔ |  |
| Transfer Date* | date | ✔ |  |
| New Batch No* | text | ✔ |  |
| Lot No | text |  |  |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- সেকশন: New colour transfer
- বাটন/অ্যাকশন: Add New, -- Select Colour --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Batch No* | select | ✔ |  |
| Transfer Date* | date | ✔ |  |
| New Batch No* | text | ✔ |  |
| Lot No | text |  |  |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch Qty must be more than nothing.

**ডায়ালগ: New Batch No**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Transfer No | search |  |  |
| Transfer Date from | date |  |  |
| Transfer Date to | date |  |  |
| Batch No | search |  |  |
| Search Batches moved to a different shade before they ran, with the number each  | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |

#### Batch Transfer to Leftover

`/m/dyeing/batch-transfer-to-left-over`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Transfer No | search |  |  |
| Transfer Date from | date |  |  |
| Transfer Date to | date |  |  |
| Buyer A/C | select |  |  |
| Style/Order# | select |  |  |
| Color | select |  | All |
| Search Every release of fabric into left-over stock — why, from where, and how m | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Trnsfer No, Trnsfer Date, Reason, Source, Buyer, Style No, Order No, Color, Trns Qty, Remarks, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Trnsfer No, Trnsfer Date, Reason, Source, Buyer, Style No, Order No, Color, Trns Qty, Remarks

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Transfer Date* | date | ✔ |  |
| Reason* | select | ✔ | Batch End, Order Cancelled, Short Batch, Excess Knitting, Quality Hold |
| Source* | text | ✔ | Batch B-26080001 |
| Buyer | select |  | LPP (LPP) |
| Trns Qty* | number | ✔ |  |
| Status* | select | ✔ | Draft, Requested, Approved, Transferred, Cancelled |
| Remarks | textarea |  |  |
- সেকশন: New left-over transfer
- বাটন/অ্যাকশন: Add New, -- Select --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Transfer Date* | date | ✔ |  |
| Reason* | select | ✔ | Batch End, Order Cancelled, Short Batch, Excess Knitting, Quality Hold |
| Source* | text | ✔ |  |
| Buyer | select |  | LPP (LPP) |
| Trns Qty* | number | ✔ |  |
| Status* | select | ✔ | Draft, Requested, Approved, Transferred, Cancelled |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Trns Qty must be more than nothing.

#### Grey to Leftover Transfer

`/m/dyeing/gray-2-leftover-transfer-batch-end`

**ভিউ**
- সেকশন: Fabric Transfer to Leftover
- বাটন/অ্যাকশন: Revert, Make Transfer

#### Manual Dye Batch Entry

`/m/dyeing/manual-batch-for-dyeing`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Manual Batch Ref. No | search |  |  |
| Batch No | search |  |  |
| Batch Date from | date |  |  |
| Batch Date to | date |  |  |
| Search Batches keyed in by hand, with the reference each was raised against | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Ref. No, Ref Date, Buyer, Style No, Order No, Color, Batch No, Lot No, Batch Date, Batch Qty, ACTION
- বাটন/অ্যাকশন: Add New, Clear, Export, Ref. No, Ref Date, Buyer, Style No, Order No, Color, Batch No, Lot No, Batch Date, Batch Qty

**ট্যাব: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Manual Batch Ref. No* | text | ✔ | MB-0001 |
| Ref Date* | date | ✔ |  |
| Buyer | select |  | LPP (LPP) |
| Batch No* | text | ✔ | B-26080099 |
| Lot No | text |  |  |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- সেকশন: New manual batch
- বাটন/অ্যাকশন: Add New, -- Select --, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Manual Batch Ref. No* | text | ✔ |  |
| Ref Date* | date | ✔ |  |
| Buyer | select |  | LPP (LPP) |
| Batch No* | text | ✔ |  |
| Lot No | text |  |  |
| Batch Date* | date | ✔ |  |
| Batch Qty* | number | ✔ |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Batch Qty must be more than nothing.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Fabric Requirement 13/02:** Fabric Batch Plan (`fp_batch_plans` আর তার লাইন)। RELEASED হলে board এটা টেনে এনে batch card বানায়। প্ল্যানটা কত batch আর কত Qty, সেটাই ভাগের ভিত্তি।
- **Knit Production 15/04–15/05:** grey ledger (`GreyStore`)।
  - Grey Store to Batch Transfer রোলগুলো একটা batch no-তে তোলে (`knit_grey_lots.batch_no`)।
  - Issue Register এই lot-গুলো থেকেই ledger-এ `DYE_ISSUE` হিসেবে কাটে।
  - Grey to Leftover Knit-এর নিজের `BATCH_TO_LEFTOVER` movement লেখে, যাতে Knit-এর রেজিস্টারেও দেখা যায়।
- **Dye House 17/01:** dye machine (ক্ষমতা, KG/LBS, sample কিনা) আর colour group (Dyeing Rate: water/wash খরচ)।
- **Material Procurement:** supplier (S/C party)। **Merchandise:** buyer, order, colour।
- **Screen kit:** ১১টা resource। Brickland-এর in-place Edit (`?edit=<number>`), এক সারির নিজের ঘর (`rowFields`), পার্টি ধরে বদলানো তালিকা (`optionsFrom`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_26_000100_create_dye_batches.php`, ১৩টা টেবিল:
- **`dye_batch_plans`:** board।
  - code (ফাঁকা রাখলে PLN-YYMM#### বসে), name, plan_date, party (company)।
  - scope (BULK/SAMPLE), scale_hours (1–24), cell_width (৪–৬০%), window_from (Save view), remarks, active।
- **`dye_batches`:** batch card, `B-YYMM####`।
  - source: PLAN / MANUAL / FINISHED / SPLIT / COLOUR। সঙ্গে fp_batch_plan_id, parent_id, plan_id।
  - buyer/order/style, colour আর colour group, dyeing_method, machine, lot।
  - plan_qty আর card_qty, store (finished fabric-এর জন্য), manual ref no/date।
  - planned_start/end (board-এর wall-clock সময়)।
  - status: OPEN / REPLACED / CLOSED।
- **`dye_batch_items`:** কার্ড কী দিয়ে তৈরি।
  - kind: GREY / COLLAR_CUFF / TRIMS / FINISHED।
  - qty_kg, issue_kg, rolls, issue_rolls, pcs, issue_pcs, yds।
- **`dye_grey_indents`, `dye_grey_indent_lines`:** requisition।
  - GFR-YYMM####, req_type BULK/FINISHED, Dyeing Rate (colour group), proj_rate, receiving store।
  - লাইন কার্ড থেকে কপি হয়।
- **`dye_grey_issues`:** কোন grey lot থেকে কত ইস্যু হলো।
- **`dye_pt_programmes` (DSC-PT-), `dye_pt_challans` (CLN-PT-), `dye_pt_receipts` (MRR-PT-), `dye_pt_bills`।**
- **`dye_grey_order_transfers`:** GF/OTR/YYMM####, status 10/30/50/90/99।
- **`dye_colour_transfers`:** CTR-, নতুন কার্ডের নম্বর, lot, তারিখ আর qty।
- **`dye_leftover_transfers`:** BLT-।

## ৫. Workflow / Status ও Business Rules

- **Batch কোথা থেকে আসে** (ব্যবহারকারীর সিদ্ধান্ত, 2026-11-04):
  - নিয়মিত bulk batch আসে Fabric Batch Plan থেকে। RELEASED প্ল্যান board-এ "Pull" করলে প্রতিটা batch-এর জন্য একটা কার্ড তৈরি হয়।
  - কার্ড পায় প্ল্যানের Qty আর প্রতিটা fabric লাইনের সমান ভাগ। rounding-এর বাকিটা যায় শেষ কার্ডে।
  - একটা প্ল্যান একবারই কার্ডে ভাগ হয়। রিলিজ না হলে বা আগেই ভাগ হয়ে থাকলে আটকায়।
  - Manual, Finished Fabric, Split আর Colour Replacement-ও কার্ড বানায়। কোনো প্ল্যানে নেই এমন কার্ড board-এ আলাদাভাবে টেনে আনা যায়।
- **Board:**
  - সারিতে প্ল্যানের S/B-এর মেশিন (Bulk মানে প্রোডাকশন মেশিন, Sample মানে sample মেশিন)। কলাম scale ঘণ্টা ধরে, সংখ্যা ১০০ ÷ cell width।
  - কার্ড বাছুন, তারপর মেশিনের ঘণ্টায় ক্লিক করুন, শুরু আর কত ঘণ্টা দিয়ে Place।
  - একটা মেশিনে একবারে একটাই কার্ড চলে (সব প্ল্যান মিলিয়ে)। মেশিনের ক্ষমতার চেয়ে ভারী কার্ড আটকায় (LBS হলে কেজিতে বদলে তুলনা)।
  - Bar খুললে Clear। Move দিয়ে উইন্ডো সরে, Plan start দিয়ে প্ল্যানের দিনে ফেরে, Save view দিয়ে scale/width/উইন্ডো থেকে যায়।
  - URL-এ থাকে `?tab=board&plan=PLN-…`।
- **Indent:**
  - এক কার্ডের একটাই requisition, তার লাইন কার্ড থেকে কপি হয়।
  - Issue Register-এ Issue চাপলে কার্ডের batch no-তে থাকা batch-store grey lot দেখায়। টিক দিয়ে ইস্যু করলে grey ledger থেকে কাটে।
  - ওজন করা রোল পুরোটাই যায়। lot-এ যতটুকু আছে বা requisition যতটুকু চায়, তার বেশি যায় না। বন্ধ মাসে আটকায়।
- **Split:**
  - নতুন কার্ডে যায় plan qty, card qty, lot আর মেশিন। আসলটায় কিছু থাকতে হবে।
  - প্রতিটা item-এ আসলটায় যতটুকু আছে তার বেশি সরানো যায় না। অন্তত একটা লাইন লাগবে।
  - নতুন কার্ড লেখা আর আসলটা কমানো এক ট্রানজ্যাকশনে হয়।
- **Colour Replacement:**
  - নতুন কার্ড পায় নিজের নম্বর, lot, তারিখ আর qty। qty আসল কার্ডের বেশি হতে পারে না।
  - item ভাগ ধরে কপি হয়। পুরনো কার্ড REPLACED হয় আর মেশিন থেকে নামে।
  - কোনো ট্রান্সফার মোছা যায় না।
- **Finished Fabric:** কার্ড আর তার FINISHED requisition একসাথে তৈরি হয়। requisition যায় ফিনিশড ফেব্রিক স্টোরে।
- **SC Pre-Treatment:**
  - **Programme:** challan-এ উঠলে Challan No/Date কলাম ভরে, এরপর আর মোছা বা পার্টি বদলানো যায় না।
  - **Challan:** শুধু নিজের পার্টির, অন্য কোনো চালানে নেই এমন প্রোগ্রাম নেয়।
  - **Receipt:** challan-টা ওই পার্টিরই হতে হবে। Program List আসে challan থেকে।
  - **Bill:** একই পার্টির একই Bill# আর Rev# একবারই রাখা যায়। Ref.Bill# অবশ্যই ওই পার্টির একটা বিল হতে হবে।
- **Grey to Leftover:**
  - batch-store-এ batch no-সহ যত grey lot আছে সব দেখায়, সঙ্গে কার্ডের অবস্থা।
  - টিক, ওজন আর left-over store দিয়ে Make Transfer করলে store আর order ধরে Knit-এর received `BATCH_TO_LEFTOVER` movement তৈরি হয়। lot নামে batch ছাড়া।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.batch.view`: FACTORY_ADMIN, FACTORY_MANAGER আর STORE_MANAGER।
  - `dyehouse.batch.manage`: FACTORY_ADMIN আর FACTORY_MANAGER।
  - `dyehouse.batch.issue` (batch store-এর ইস্যু): FACTORY_ADMIN আর STORE_MANAGER। manage থাকলেও ইস্যু করা যায়।
- **API:**
  - `GET /dyehouse/batch/options`।
  - `GET /dyehouse/batch-plans/{code}/board`, `POST …/pull`, `POST …/bars`, `DELETE …/bars/{batch}`, `PUT …/view`।
  - `GET /dyehouse/batches`, `GET /dyehouse/batches/{no}`, `POST /dyehouse/batches/{no}/split`।
  - `GET|POST /dyehouse/grey-indents/{no}/issue`।
  - `GET|POST /dyehouse/batch-leftover`।
- **Screen-kit resource:** `dye-batch-plans`, `dye-manual-batches`, `dye-finished-batches`, `dye-grey-indents`, `dye-pt-programmes|challans|receipts|bills`, `dye-grey-order-transfers`, `dye-colour-transfers`, `dye-leftover-transfers`।
- **URL-এ ULID নেই:** সবকিছু চেনা হয় নম্বর বা কোড দিয়ে।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Dye House → BATCH (BULK), ১৪টা লিফ, Brickland-এর slug-এ।
- **Dye Batch Plan:**
  - তিনটা ট্যাব: Plan List, Batch Plan Board (ডিফল্ট), New Plan।
  - board-এ পাশে মেশিন আর ক্ষমতা, ওপরে সময়। bar-এ batch no, কালার আর কেজি।
  - নিচে "Waiting to be laid out" সারি আর "Pull batches onto this plan"।
- **Bulk Grey Fabric Indent:** Records আর Create Requisition। **Issue Register:** ফিল্টার আর গ্রিড, প্রতিটা সারিতে Issue। Issue চাপলে requisition-এর লাইন, lot, ইস্যুর ইতিহাস আর Save issue দেখায়।
- **Batch Card Splitting:**
  - বাঁয়ে Batch Plan তালিকা, সার্চসহ, পাতায় ১৫টা।
  - ডানে Present/New ঘর।
  - তিনটা কার্ড: Gery fab(Knit Fab), Collar Cuff আর Trims, প্রতিটায় Add, Cancel আর লাইনের গ্রিড।
  - নিচে Clear আর Save split।
- **বাকি ১০টা স্ক্রিন:**
  - Records ট্যাবে Brickland-এর ফিল্টার আর কলাম।
  - Add New / New / Create Requisition ট্যাবে ফর্ম, Brickland-এর হিন্ট আর বার্তাসহ।
  - Edit একই পাতায় খোলে ("Edit X", Update X)।
- **Grey to Leftover:** টিক দেওয়ার মতো lot-এর গ্রিড, Left-over Store, Revert আর Make Transfer।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১৪টার ১৪টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_26_000100_create_dye_batches.php`
- `app/Modules/DyeHouse/Models/` — `DyeBatchPlan`, `DyeBatch`, `DyeBatchItem`, `DyeGreyIndent`, `DyeGreyIndentLine`, `DyeGreyIssue`, `DyePtProgramme`, `DyePtChallan`, `DyePtReceipt`, `DyePtBill`, `DyeGreyOrderTransfer`, `DyeColourTransfer`, `DyeLeftoverTransfer`
- `app/Modules/DyeHouse/Support/DyeBatches.php` (নম্বর, নাম, Fabric Batch Plan থেকে কার্ড)
- `app/Modules/DyeHouse/Resources/` — `DyeBatchResource` (ভিত্তি), `DyeBatchPlanResource`, `DyeManualBatchResource`, `DyeFinishedBatchResource`, `DyeGreyIndentResource`, `DyePtProgrammeResource`, `DyePtChallanResource`, `DyePtReceiptResource`, `DyePtBillResource`, `DyeGreyOrderTransferResource`, `DyeColourTransferResource`, `DyeLeftoverTransferResource`
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeBatchApiController.php`
- `app/Modules/DyeHouse/Routes/api.php` (বদলানো)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `batch` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/batch-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/batch-board.jsx`, `batch-split.jsx`, `grey-issue-register.jsx`, `grey-leftover.jsx`

**Test**
- `tests/Feature/DyeHouse/DyeHouseBatchTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseBatchTest.php`):** test2 DB-তে পাস, ৫টা টেস্ট, ১৭৩টা assertion।
- `test_cards_from_a_released_plan_laid_out_on_the_board`:
  - plan code PLN-26110001 নিজে বসে। Cell Width ৭০ দিলে Brickland-এর বার্তা আসে।
  - রিলিজ না হওয়া প্ল্যান আর দ্বিতীয়বার Pull আটকায়।
  - ৮৭৫ কেজি থেকে ২টা কার্ড (৪৩৭.৫ করে), লাইন ৩০০ + ১৩৭.৫।
  - ক্ষমতার বেশি (৫০০ lb = ২২৬.৮ কেজি), sample মেশিন আর overlap আটকায়।
  - উইন্ডো, Clear, Save view আর Plan List-এর সংখ্যা ঠিক আসে। ব্যাচ থাকা প্ল্যান মোছা যায় না।
- `test_cards_by_hand_off_finished_fabric_split_and_colour_replaced`:
  - Batch Qty ০ দিলে বার্তা আসে, batch no বড় হাতের অক্ষরে আর ট্রিম হয়ে বসে, একই নম্বর দুবার আটকায়।
  - finished fabric-এ B-নম্বর আর GFR requisition বসে।
  - split-এর নিয়ম আর ফল ঠিক আসে।
  - colour replacement: কার্ডের বেশি qty আটকায়, নতুন কার্ড COLOUR হয়, পুরনোটা REPLACED।
- `test_indent_issue_and_grey_to_leftover`:
  - indent-এর বার্তা আর ডুপ্লিকেট আটকানো, লাইন কপি।
  - lot-এর বেশি বা requisition-এর বেশি ইস্যু আটকায়। ledger-এ balance কমে।
  - Grey to Leftover-এ Knit-এর `BATCH_TO_LEFTOVER` (RECEIVED) movement হয়, lot নামে batch ছাড়া। একই স্টোরে আটকায়।
- `test_sub_contract_pre_treatment_papers`: programme, challan আর receipt-এর সংযোগ আর পার্টির নিয়ম, মোছা আটকানো, বিলের রিভিশন আর Ref.Bill#।
- `test_transfers_and_who_may_do_what`:
  - GF/OTR নম্বর। একই অর্ডারে ট্রান্সফার আটকায়।
  - BLT-এ অর্ডার থেকে buyer আর style আসে।
  - STORE_MANAGER দেখতে আর ইস্যু করতে পারে, লিখতে পারে না। FACTORY_MANAGER লিখতে পারে। TECHNICIAN কিছুই পায় না।

**Regression:** `MenuAccessTest` (৬টা টেস্ট, ৩৪টা assertion), `DyeHouseMasterTest` (৬টা টেস্ট, ৯৪টা assertion) আর `DyeHouseLabDipTest` (৩টা টেস্ট, ৯৩টা assertion) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/db01`–`db03`), dev অ্যাপে:**
- **Fabric Requirement:** BP-00001 RELEASED করা হয়েছে।
- **Dye Batch Plan:** PLN-26110001 তৈরি। board-এ Pull করে B-26110001 আর B-26110002 এসেছে।
  - B-26110001 DM-01-এ ০৪:০০–০৮:০০।
  - একই মেশিনে overlap-এর চেষ্টা আটকেছে।
  - B-26110002 DM-02-এ ০৮:০০–১৪:০০।
  - Plan List-এ ব্যাচ ২, ৮৭৫ কেজি, Laid Out ২।
- **Manual:** MB-0001 / B-26110010 (৩০০ কেজি)। Batch Qty ০-এর বার্তা এসেছে।
- **Knit-এর Grey→Batch:** দুটো রোল (২২.৯ আর ২২.৫ কেজি) B-26110010-এ তোলা হয়েছে।
- **Indent আর Issue:** GFR-26110001 (৩০০ কেজি)। ফাঁকা সেভে চারটা বার্তা এসেছে। Issue-তে ২২.৫ কেজির রোল ইস্যু হয়েছে, বাকি ২৭৭.৫।
- **Split:** B-26110010 থেকে B-26110011 (১০০ কেজি, DM-04)। আসলটা ২০০।
- **Grey to Leftover:** ২২.৯ কেজির রোল GAZ-FAB লেফটওভার স্টোরে গেছে।
- **Finished Fabric:** B-26110012 আর GFR-26110002।
- **Colour Replacement:** CTR → B-26110900 (Bottle Green, ৪০০ কেজি)। ৯০০ দিলে আটকেছে। B-26110002 এখন REPLACED।
- **Leftover:** BLT-26110001। **Order Transfer:** GF/OTR/26110001।
- **SC Pre-Treatment:** DSC-PT-26110001, CLN-PT-26110001 (programme টিক দিয়ে), MRR-PT-26110001 (Program List সহ), বিল 22647। বিলটা Edit করে Update-ও করা হয়েছে।
- **error:** ০। সব রেকর্ড dev DB-তে রাখা হয়েছে।
- **UI চেকে ধরা পড়া ছোট সমস্যা, ঠিক করা হয়েছে:** কালার ছাড়া কার্ডের bar-এর লেখার সামনে একটা বাড়তি "·" থাকত।

**প্রিন্ট:** Brickland-এর এই ১৪টা স্ক্রিনে চালু Print নেই (board-এর "Program" disabled)।

## ১০. খোলা প্রশ্ন

১. **Grey to Leftover-এর তালিকা:** Brickland বলে "fabric still on a finished batch"। কিন্তু batch শেষ হয় production-এ (17/07), যা এখনো তৈরি হয়নি। তাই এখন batch-store-এর সব batch lot দেখানো হয়, সঙ্গে কার্ডের অবস্থা। 17/07 তৈরি হলে শুধু শেষ হওয়া batch দেখানো হবে।

২. **Order-Wise Grey Transfer আর Batch Transfer to Leftover-এ লাইন:** Brickland-এ এই দুটো শুধু header (কোনো রোল বা লট লাইন নেই), আর grey ledger নড়ে না। আমরা হুবহু তাই রেখেছি। স্টক সত্যিই নড়ানো দরকার হলে Knit-এর Order-to-Order বা Batch to Leftover movement দিয়ে হবে, অথবা পরে লাইন যোগ করা যাবে।

৩. **SC Pre-Treatment-এর পরিমাণ:** Brickland-এর Programme, Challan আর Receipt-এ কোনো কেজি বা লস ঘর নেই ("with quantity and loss booked" লেখা থাকলেও)। তাই এখন শুধু কাগজের সংযোগ রাখা হয়েছে।

৪. **Plan Code:** Brickland-এর ঘরে লেখা "Left blank, the screen issues one", কিন্তু ফাঁকা রাখলে Brickland নিজেই আটকায়। আমরা লেখার উদ্দেশ্য মেনেছি: ফাঁকা রাখলে PLN-YYMM#### বসে।

৫. **Knit-এর Grey→Batch transfer:** এখনো যেকোনো batch no লিখতে দেয়। কার্ড নেই এমন নম্বর আটকানো হবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/db01`–`db05`):**
  - ১৪টা স্ক্রিনের Records আর New ফর্ম, বার্তা আর গ্রিড দেখা হয়েছে।
  - Brickland demo-তে কোনো batch নেই। একটা board plan তৈরি করা হয়েছে (BP-2611-01), তাতে ধরা পড়েছে "Plan Code is required." আর "Cell Width(%) must be between 4 and 60."।
  - board-এর "Program" আসলে একটা disabled Print বাটন। তাই batch-এর উৎস ব্যবহারকারীকে জিজ্ঞেস করা হয়েছিল।
- **Board-এর সময়:** ফ্লোরের wall-clock সময় হিসেবে রাখা হয় (app UTC, কোনো zone রূপান্তর নেই)।
- **Print:** Brickland-এ এই ১৪টা স্ক্রিনে চালু Print নেই। board-এর "Program" disabled।
