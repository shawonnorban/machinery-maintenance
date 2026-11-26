# Yarn Store

> **স্ট্যাটাস:** ✅ সম্পন্ন (১৭টার ১৭টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/03`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Yarn Opening Entry | `/m/knitting/yarn-opening` | 1 | 11 | 0 |
| 2 | Yarn Dyeing Loss Adjustment | `/m/knitting/process-loss-adjustment-yd` | 2 | 11 | 1 |
| 3 | Yarn Dyeing Statement | `/m/knitting/knitting-yd-statement` | 1 | 4 | 0 |
| 4 | Yarn Store Opening Balance | `/m/knitting/opening-balance` | 2 | 25 | 1 |
| 5 | Daily Yarn Receipt | `/m/knitting/daily-receive` | 3 | 41 | 1 |
| 6 | Yarn Racking Board | `/m/knitting/yarn-stock-racking-board` | 2 | 27 | 0 |
| 7 | Yarn Issue against Indent | `/m/knitting/issue-against-requisition` | 3 | 30 | 1 |
| 8 | Yarn Issue Return against Indent | `/m/knitting/issue-return-against-requisition` | 2 | 27 | 1 |
| 9 | In-House Sub-Contract Yarn Receipt | `/m/knitting/yarn-receive-challan-sc-in-house` | 1 | 14 | 1 |
| 10 | Party-Wise Yarn Issue Challan | `/m/knitting/party-wise-yarn-issue-challan` | 2 | 26 | 1 |
| 11 | In-House Dyed Yarn Receipt | `/m/knitting/dyed-yarn-receive-in-house` | 2 | 16 | 0 |
| 12 | In-House Yarn Dye Re-Process | `/m/knitting/yd-reprocess-in-house-to-yd-factory` | 2 | 12 | 0 |
| 13 | Sub-Contract Yarn Dye Re-Process | `/m/knitting/yd-reprocess-sc-knitting-to-yd-factory` | 1 | 2 | 0 |
| 14 | Supplier Yarn Replacement Register | `/m/knitting/issue-yarn-replace-by-supplier-list` | 2 | 36 | 1 |
| 15 | Daily Yarn Receipt Return | `/m/knitting/daily-receive-return` | 2 | 16 | 0 |
| 16 | Yarn Store Month Closing | `/m/knitting/yarn-month-closing` | 1 | 1 | 0 |
| 17 | Yarn Stock Adjustment | `/m/knitting/yarn-adjustment` | 2 | 10 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Yarn Opening Entry

`/m/knitting/yarn-opening`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | -Select-, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn |
| Standing | select |  | Running, Closable, Finalized |
| Spin Type | select |  | -All-, Combed, Carded, Open End, Ring Spun, Vortex, Semi Combed |
| Cotton Type | select |  | -All-, Conventional, Organic, Bci, Recycled, Supima, Egyptian |
| Slub? | select |  | -All-, Yes, No |
| Mellange? | select |  | -All-, Yes, No |
| Solid Yarn? | select |  | -All-, Yes, No |
| Y/D Yarn? | select |  | -All-, Yes, No |
| Brand | select |  | -All- |
| Lot | text |  | -All- |
- সেকশন: Yarn Opening & Colosing Operation
- বাটন/অ্যাকশন: -- Month --, -All-, Search, Save, Finalise All

#### Yarn Dyeing Loss Adjustment

`/m/knitting/process-loss-adjustment-yd`

**ট্যাব: Process Loss Adjustment (Y/D) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Y/D Program# | select |  |  |
| Status | select |  | Draft, Approved, Cancelled |
- সেকশন: Process Loss Search
- বাটন/অ্যাকশন: Process Loss Adjustment (Y/D) 0, Process Loss Entry, -- Select --, Show, Add New, Reset

**ট্যাব: Process Loss Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing memo | select |  |  |
| Date* | date | ✔ |  |
| Y/D Program#* | select | ✔ |  |
| Remarks | textarea |  |  |
| Programme Shade | select |  |  |
| Lot # | text |  |  |
| Issued Qty | number |  |  |
| Received Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Process Loss Entry, Loss Lines
- বাটন/অ্যাকশন: Process Loss Adjustment (Y/D) 0, Process Loss Entry, -- Select --, Add Line, Cancel, --Select Col…, Back To List, Clear, Save memo

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing memo | select |  |  |
| Date* | date | ✔ |  |
| Y/D Program#* | select | ✔ |  |
| Remarks | textarea |  |  |
| Programme Shade | select |  |  |
| Lot # | text |  |  |
| Issued Qty | number |  |  |
| Received Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Date is required.

#### Yarn Dyeing Statement

`/m/knitting/knitting-yd-statement`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Y/D Program# | select |  |  |
| Program Status | select |  | Running, Completed, Closed, Cancelled |
- সেকশন: Knitting Y/D Statement
- বাটন/অ্যাকশন: -- Select Type --, -- Select Month --, -- Select --, Show, Reset

#### Yarn Store Opening Balance

`/m/knitting/opening-balance`

**ট্যাব: Yarn Opening Balance 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Receiving Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer A/C | select |  |  |
| Yarn Item | select |  |  |
| Source | select |  | Local, Import, S/C Party, In-house Dyed, Yarn Dyed (Y/D) |
| Lot # | text |  | Lot number |
| Is Buffer? | select |  | Yes, No |
| Finalized? | select |  | Yes, No |
- সেকশন: Opening Balance Search
- বাটন/অ্যাকশন: Yarn Opening Balance 0, Yarn Opening Balance Entry, -Select-, Show, Add New, Reset

**ট্যাব: Yarn Opening Balance Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing opening balance | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| OB Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Buyer A/C | select |  |  |
| Is Buffer? | checkbox |  |  |
| Remarks | textarea |  |  |
| Source | select |  | Local, Import, S/C Party, In-house Dyed, Yarn Dyed (Y/D) |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Pack Qty. | number |  |  |
| Qty/Pack | number |  |  |
| Total Qty. | number |  |  |
| Cost Price | number |  |  |
| No of Cone | number |  |  |
| Rcv. Date | date |  |  |
- সেকশন: Yarn Opening Balance Entry, Details Data
- বাটন/অ্যাকশন: Yarn Opening Balance 0, Yarn Opening Balance Entry, --Style--, -Select-, Save Line, Cancel, --Select Col…, -- Select Supplier --, --Pack UoM--, Kg, Back To List, Clear, Save opening balance

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing opening balance | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| OB Date* | date | ✔ |  |
| Currency* | select | ✔ | BDT — Bangladeshi Taka, CNY — Chinese Yuan, EUR — Euro, USD — US Dollar |
| Buyer A/C | select |  |  |
| Is Buffer? | checkbox |  |  |
| Remarks | textarea |  |  |
| Source | select |  | Local, Import, S/C Party, In-house Dyed, Yarn Dyed (Y/D) |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Pack Qty. | number |  |  |
| Qty/Pack | number |  |  |
| Total Qty. | number |  |  |
| Cost Price | number |  |  |
| No of Cone | number |  |  |
| Rcv. Date | date |  |  |
- খালি Save-এ মেসেজ: OB Date is required.

#### Daily Yarn Receipt

`/m/knitting/daily-receive`

**ট্যাব: Yarn Receive List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  |  |
| Job#/W.Style | text |  | -- Select -- |
| PR | select |  | -All- |
| PO | select |  | -All- |
| PI | select |  | -All- |
| Recv. Opt. | select |  | Receive Direct, Receive By PO, Receive By PI |
| Status | select |  | 55-Store Receive, 55-Store Verify, 55-Audit Verify, 55-Accounts Approve, Cancelled |
- সেকশন: MRR Search Parameter
- বাটন/অ্যাকশন: Yarn Receive List 0, Yarn Receive Entry, Returns & Exchanges, -Select-, Search, Add New, Reset

**ট্যাব: Yarn Receive Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing receipt | select |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Recv. Opt.* | text | ✔ |  |
| PO | select |  |  |
| PI | select |  |  |
| Recv.Ref# | text |  |  |
| Chalan No | text |  |  |
| Buyer A/C | select |  |  |
| Job#/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Pack Qty. | number |  |  |
| Qty/Pack | number |  |  |
| Recv Qty | number |  |  |
| Rate | number |  |  |
| Challan Qty | number |  |  |
| No of Cone | number |  |  |
| Remarks | text |  |  |
- সেকশন: Yarn Receive Entry, Receipt Lines
- বাটন/অ্যাকশন: Yarn Receive List 0, Yarn Receive Entry, Returns & Exchanges, -- Select Supplier --, -Select-, Add Line, Cancel, --Select Col…, --Pack UoM--, Kg, Back To List, Clear, Save receipt

**ট্যাব: Returns & Exchanges**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Return# | text |  |  |
| Against MRR | text |  |  |
| Supplier | text |  |  |
| Return Date* | date | ✔ |  |
| Type* | text | ✔ |  |
| Return Chalan Type* | select | ✔ | Select…, Returnable, Non-Returnable |
| Is Finalized? | checkbox |  |  |
| Reason | textarea |  |  |
| Receipt Line | select |  |  |
| Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Returns & Exchanges, Lines Going Back
- বাটন/অ্যাকশন: Yarn Receive List 0, Yarn Receive Entry, Returns & Exchanges, Add Line, Cancel, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing receipt | select |  |  |
| MRR Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Receiving Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Recv. Opt.* | text | ✔ |  |
| PO | select |  |  |
| PI | select |  |  |
| Recv.Ref# | text |  |  |
| Chalan No | text |  |  |
| Buyer A/C | select |  |  |
| Job#/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Pack Qty. | number |  |  |
| Qty/Pack | number |  |  |
| Recv Qty | number |  |  |
| Rate | number |  |  |
| Challan Qty | number |  |  |
| No of Cone | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: MRR Date is required.

#### Yarn Racking Board

`/m/knitting/yarn-stock-racking-board`

**ট্যাব: Yarn Racking Dashboard 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| Spin Type | select |  | -All-, Combed, Carded, Open End, Ring Spun, Vortex, Semi Combed |
| Cotton Type | select |  | -All-, Conventional, Organic, Bci, Recycled, Supima, Egyptian |
| Rack# | select |  | -All- |
| Slub? | select |  | -All-, Yes, No |
| Mellange? | select |  | -All-, Yes, No |
| Is Buffer? | select |  | -All-, Yes, No |
| Closed Order? | select |  | -All-, Yes, No |
| Solid Yarn? | select |  | -All-, Yes, No |
| Y/D Yarn? | select |  | -All-, Yes, No |
| Brand | select |  | -All- |
| Lot | text |  | -All- |
| Store | select |  | -All-, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn St |
| Buyer | select |  |  |
| Job#/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| MRR | select |  | -All- |
| PR | select |  | -All- |
| PO | select |  | -All- |
| PI | select |  | -All- |
- সেকশন: Yarn Search Parameter
- বাটন/অ্যাকশন: Yarn Racking Dashboard 0, Rack Assignment, -All-, -Select-, Search, Reset

**ট্যাব: Rack Assignment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Yarn Item | select |  | -All- |
| Brand | select |  | -All- |
| Lot | text |  | -All- |
| From Date | date |  |  |
| To Date | date |  |  |
| Rack Pending Only? | select |  | All, Yes, No |
- সেকশন: Rack Assignment
- বাটন/অ্যাকশন: Yarn Racking Dashboard 0, Rack Assignment, Search, Rack

#### Yarn Issue against Indent

`/m/knitting/issue-against-requisition`

**ট্যাব: Yarn Issue List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition No | text |  | Requisition No |
| Date | date |  |  |
| Action Status | select |  | Open, Partly Issued, Fully Issued, Closed, Cancelled |
| Buyer | select |  |  |
| W.Style/Job/Order No | text |  | --Style-- |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Yarn Issue List 0, Yarn Requisition Entry, Yarn Issue Entry, ---, --- Supplier List ---, Search, Add New, Reset

**ট্যাব: Yarn Requisition Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Buyer | select |  |  |
| Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Program# | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Req. Qty | number |  |  |
| KP Alloc | number |  |  |
- সেকশন: Yarn Requisition Entry, Requisition Lines
- বাটন/অ্যাকশন: Yarn Issue List 0, Yarn Requisition Entry, Yarn Issue Entry, -- Select Type --, --Style--, --- Supplier List ---, Add Line, Cancel, -- Count --, --Select Col…, Kg, Back To List, Clear, Save requisition

**ট্যাব: Yarn Issue Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue | select |  |  |
| Issue Date* | date | ✔ |  |
| Requisition No* | select | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Gate Pass No | text |  |  |
| Remarks | textarea |  |  |
| Requisition Line | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Issue Qty | number |  |  |
| No of Cone | number |  |  |
- সেকশন: Yarn Issue Entry, Issue Lines
- বাটন/অ্যাকশন: Yarn Issue List 0, Yarn Requisition Entry, Yarn Issue Entry, Add Line, Cancel, --Select Col…, Kg, Back To List, Clear, Save issue

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue | select |  |  |
| Issue Date* | date | ✔ |  |
| Requisition No* | select | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Gate Pass No | text |  |  |
| Remarks | textarea |  |  |
| Requisition Line | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Issue Qty | number |  |  |
| No of Cone | number |  |  |
- খালি Save-এ মেসেজ: Issue Date is required.

#### Yarn Issue Return against Indent

`/m/knitting/issue-return-against-requisition`

**ট্যাব: Yarn Return to Store List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
| Search Unused yarn coming back to the store — what the floor sent, what the stor | search |  | Search 0 records… |
| Filter by Challan No | search |  |  |
| Filter by Challan Date | search |  |  |
| Filter by Requisition Type | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store Name | search |  |  |
| Filter by Return By | search |  |  |
| Filter by Ret | search |  |  |
| Filter by Recv | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Requisition Type, Company, Store Name, Return By, Ret, Recv, ACTION
- বাটন/অ্যাকশন: Yarn Return to Store List 0, Yarn Return Entry, Clear, Add New, Export, Challan No, Challan Date, Requisition Type, Company, Store Name, Return By, Ret, Recv

**ট্যাব: Yarn Return Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Against Requisition | select |  |  |
| Return By* | text | ✔ |  |
| Remarks | textarea |  |  |
| Issue Line | select |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Ret Qty | number |  |  |
| Recv Qty | number |  |  |
| Remarks | text |  |  |
- সেকশন: Yarn Return Entry, Lines Coming Back
- বাটন/অ্যাকশন: Yarn Return to Store List 0, Yarn Return Entry, -- Select Type --, Add Line, Cancel, --Select Col…, Kg, Back To List, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing return challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Against Requisition | select |  |  |
| Return By* | text | ✔ |  |
| Remarks | textarea |  |  |
| Issue Line | select |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Ret Qty | number |  |  |
| Recv Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Challan Date is required.

#### In-House Sub-Contract Yarn Receipt

`/m/knitting/yarn-receive-challan-sc-in-house`

**ট্যাব: S/C Yarn Receive Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
| Search Yarn a sub-contract party has sent in for the mill to knit — the challan, | search |  | Search 0 records… |
| Filter by Challan# | search |  |  |
| Filter by Date | search |  |  |
| Filter by Program# | search |  |  |
| Filter by Order# | search |  |  |
| Filter by Style# | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Store | search |  |  |
| Filter by Qty | search |  |  |
| Filter by Remarks | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan#, Date, Program#, Order#, Style#, Supplier, Store, Qty, Remarks, ACTION
- বাটন/অ্যাকশন: S/C Yarn Receive Challan List 0, Clear, Add New, Export, Challan#, Date, Program#, Order#, Style#, Supplier, Store, Qty, Remarks

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search S/C Program List | search |  |  |
| Existing receive challan | select |  |  |
| Program# | text |  |  |
| Order# | text |  |  |
| Style# | text |  |  |
| Challan# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | text |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Qty | number |  |  |
| No of Cone | number |  |  |
- খালি Save-এ মেসেজ: Pick the S/C programme on the left first — a receive challan is always against one.

#### Party-Wise Yarn Issue Challan

`/m/knitting/party-wise-yarn-issue-challan`

**ট্যাব: Party Wise Yarn Issue Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
| Search Yarn sent out of the store to a sub-contract party — the challan, the par | search |  | Search 0 records… |
| Filter by Challan No | search |  |  |
| Filter by Challan Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Store | search |  |  |
| Filter by Gate Pass No | search |  |  |
| Filter by Qty | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Qty, ACTION
- বাটন/অ্যাকশন: Party Wise Yarn Issue Challan List 0, Party Wise Yarn Issue Challan Entry, Clear, Add New, Export, Challan No, Challan Date, Company, Supplier, Store, Gate Pass No, Qty

**ট্যাব: Party Wise Yarn Issue Challan Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing issue challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| No of Cone | number |  |  |
| Remarks | text |  |  |
- সেকশন: Party Wise Yarn Issue Challan Entry, Challan Lines
- বাটন/অ্যাকশন: Party Wise Yarn Issue Challan List 0, Party Wise Yarn Issue Challan Entry, -- Select Party --, Add Line, Cancel, --Select Col…, Kg, Back To List, Clear, Save issue challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing issue challan | select |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Yarn Store, Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Ya |
| Gate Pass No | text |  |  |
| Vehicle No | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| No of Cone | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Challan Date is required.

#### In-House Dyed Yarn Receipt

`/m/knitting/dyed-yarn-receive-in-house`

**ট্যাব: Y/D Program List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Program Ref# | text |  |  |
| Challan # | text |  |  |
| Receive Status | select |  | Draft, Confirmed, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Y/D Program List 0, Dyed Yarn Receive, -- Select --, Show, Reset

**ট্যাব: Dyed Yarn Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Receive List | search |  | Programme, buyer, order… |
| Existing dyed yarn receipt | select |  |  |
| Receive # | text |  | [Auto Gen] |
| Receive Date* | date | ✔ |  |
| Y/D Program #* | select | ✔ |  |
| Working Ref# | text |  | The floor's own reference for the delivery |
| Challan # | text |  |  |
| Challan DT | date |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Y/D Program #, Supplier Name, Order, Pending (Kg)
- সেকশন: Receive List, Dyed Yarn Receive, Key Information, Challan, Booked To, Dyed Yarn Details
- বাটন/অ্যাকশন: Y/D Program List 0, Dyed Yarn Receive, -- Select Party --, -- Select --, Add Shade, Back To List, Clear, Save dyed yarn receipt

#### In-House Yarn Dye Re-Process

`/m/knitting/yd-reprocess-in-house-to-yd-factory`

**ট্যাব: YD Re-process (In-house) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
| Challan# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: YD Re-process (In-house) 0, YD Re-process (In-house to YD Factory), -- Select --, Show, Reset

**ট্যাব: YD Re-process (In-house to YD Factory)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing re-process document | select |  |  |
| Ref # | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Bill# | text |  |  |
| Challan# | text |  |  |
| Chln DT | date |  |  |
| Y/D Program # | select |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- সেকশন: YD Re-process (In-house to YD Factory), Key Information, Paperwork, Booked To, Yarn Going Back
- বাটন/অ্যাকশন: YD Re-process (In-house) 0, YD Re-process (In-house to YD Factory), -- Select --, Add Yarn, Back To List, Clear, Save re-process document

#### Sub-Contract Yarn Dye Re-Process

`/m/knitting/yd-reprocess-sc-knitting-to-yd-factory`

**ট্যাব: YD Re-process (S/C Knitting) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
| Challan# | text |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: YD Re-process (S/C Knitting) 0, -- Select --, Show, Reset

#### Supplier Yarn Replacement Register

`/m/knitting/issue-yarn-replace-by-supplier-list`

**ট্যাব: Issue Yarn Replace by Supplier List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  | All |
| Return Type | select |  | All |
| Status | select |  | All, Draft Created, Submitted, Accepted, Cancelled |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Yarn sent back to spinners against a replacement claim — the lot, why it  | search |  | Search 0 records… |
| Filter by Challan No | search |  |  |
| Filter by Challan Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store Name | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Return Type | search |  |  |
| Filter by L/C | search |  |  |
| Filter by Lot | search |  |  |
| Filter by Against Claim | search |  |  |
| Filter by Carton/Bag | search |  |  |
| Filter by Qty (Kg) | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Against Claim, Carton/Bag, Qty (Kg), ACTION
- বাটন/অ্যাকশন: Issue Yarn Replace by Supplier List 0, Issue Yarn Replace by Supplier, Clear, Add New, Export, Challan No, Challan Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Against Claim, Carton/Bag, Qty (Kg)

**ট্যাব: Issue Yarn Replace by Supplier**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  | All |
| Return Type | select |  | All |
| Status | select |  | All, Draft Created, Submitted, Accepted, Cancelled |
| Challan Date from | date |  |  |
| Challan Date to | date |  |  |
| Search Yarn sent back to spinners against a replacement claim — the lot, why it  | search |  | Search 0 records… |
| Filter by Challan No | search |  |  |
| Filter by Challan Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store Name | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Return Type | search |  |  |
| Filter by L/C | search |  |  |
| Filter by Lot | search |  |  |
| Filter by Against Claim | search |  |  |
| Filter by Carton/Bag | search |  |  |
| Filter by Qty (Kg) | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Against Claim, Carton/Bag, Qty (Kg), ACTION
- বাটন/অ্যাকশন: Issue Yarn Replace by Supplier List 0, Issue Yarn Replace by Supplier, Clear, Add New, Export, Challan No, Challan Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Against Claim, Carton/Bag, Qty (Kg)

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing challan | select |  |  |
| Challan No | text |  |  |
| Challan Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Against Claim | select |  |  |
| Return Type* | select | ✔ | Select…, CLAIM BY YARN TEST, CLAIM BY PRODUCTION, SHORT SUPPLY, WRONG SUPPLY, DAMAGED IN TRANSIT |
| L/C | text |  |  |
| Lot | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Carton/Bag | number |  |  |
| Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A challan with no yarn on it sends the spinner an empty lorry.

#### Daily Yarn Receipt Return

`/m/knitting/daily-receive-return`

**ট্যাব: Yarn Receive Return List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  |  |
| MRR No | text |  |  |
| Return Chalan Type | select |  | Returnable, Non-Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Yarn Receive Return List 0, Yarn Receive Return, -- Select Supplier --, Search, Reset

**ট্যাব: Yarn Receive Return**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan No | text |  |  |
| MRR No | text |  |  |
| Return Chalan Type | select |  | Returnable, Non-Returnable |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Challan From Date | date |  |  |
| Challan To Date | date |  |  |
| Status | select |  | Draft, Issued, Accepted, Rejected, Cancelled |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Yarn Receive Return List 0, Yarn Receive Return, -- Select Supplier --, Search, Reset

#### Yarn Store Month Closing

`/m/knitting/yarn-month-closing`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Remarks | text |  |  |
- সেকশন: Parameters
- বাটন/অ্যাকশন: -- Select Month --, Show, Month Close, Show Exception, Reset

#### Yarn Stock Adjustment

`/m/knitting/yarn-adjustment`

**ট্যাব: Yarn Adjustment List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Approved, Cancelled |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Corrections to what the yarn store is holding — the document, where it ap | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Activity Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason, ACTION
- বাটন/অ্যাকশন: Yarn Adjustment List 0, Yarn Adjustment, Clear, Export, Activity Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason

**ট্যাব: Yarn Adjustment**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Draft, Approved, Cancelled |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Corrections to what the yarn store is holding — the document, where it ap | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Activity Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason, ACTION
- বাটন/অ্যাকশন: Yarn Adjustment List 0, Yarn Adjustment, Clear, Export, Activity Status, Adjustment No, Adjustment Date, Company, Store, Lines, Adjust Qty (Kg), Value, Reason
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/Knitting`, নতুন টেবিল `knit_yarn_` নামে।
- **15/02 থেকে নেওয়া (Yarn Procurement):**
  - Yarn Item = Fiber Composition, Count = Yarn Count Master, ২১টা রং, supplier, buyer, order, currency, UOM। সব আসে `YarnProcurement::options()` থেকে।
  - `YarnProcurement::nextNumber()` দিয়ে মাস ধরে নম্বর।
  - placed PO (`knit_yarn_pos`): Receive By PO-র উৎস। PO-র `received_qty` আর অবস্থা এখন এই মডিউল মিলিয়ে রাখে।
- **15/02-এর CS:** Present Stock এখন এই মডিউলের খাতা থেকে আসে (`YarnStore::stockOf`)।
- **Frontend:** `yarn-common.jsx` (PSelect, Field, StatusChip, QtyInput, LineButtons) আবার ব্যবহার করা হয়েছে। নতুন `useYs()` দিয়ে `ystore.*` শব্দ পড়া হয়।
- **ছাঁচ:**
  - Month Close আর Show Exception: Merchandising-এর stock closing (StockPeriods)।
  - Register আর ডেস্ক: 15/02-এর PO।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_03_000100_create_knitting_yarn_store`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_lots` | মজুদ লাইন: lot_key (unique), store_id, composition_id, yarn_count_id, colour, brand, lot_no (বড় হাতের), supplier_id, buyer_id, order_id, is_buffer, source, uom, rate, first_date |
| `knit_yarn_moves` | খাতা: lot_id, store_id, move_date, kind (OB / RECEIPT / RETURN / ADJUSTMENT / PERIOD_ADJ), doc_no, doc_line_id, qty (চিহ্নসহ), value |
| `knit_yarn_obs` + `knit_yarn_ob_lines` | Opening Balance: ob_no (YOB-…), ob_date, store, currency, buyer, order, is_buffer, finalized; লাইনে source, yarn, lot, supplier, pack_qty, qty_per_pack, pack_uom, total_qty, cost_price, cones, rcv_date |
| `knit_yarn_receipts` + `knit_yarn_receipt_lines` | MRR: mrr_no (YMRR-…), mrr_date, store, recv_opt (DIRECT/PO/PI), po_id, recv_ref, challan_no, supplier, buyer, order, status; লাইনে po_line_id, lot_id, yarn, lot_no, pack, recv_qty, rate, challan_qty, cones |
| `knit_yarn_returns` + `knit_yarn_return_lines` | Return challan: return_no (YRC-…), receipt_id, supplier, return_date, type (RETURN/EXCHANGE), challan_type (RETURNABLE/NON_RETURNABLE), finalized, reason; লাইনে receipt_line_id, qty |
| `knit_yarn_adjustments` + lines | adj_no (YADJ-…), adj_date, store, reason, status (ACTIVE/CANCELLED); লাইনে lot_id, qty (±) |
| `knit_yarn_racks` | lot_id, receipt_line_id, rack_no, qty, rack_date |
| `knit_yarn_period_lines` | store_id, period (2026-10), lot_id, adjustment, reason, finalized |
| `knit_yarn_month_closes` | period, closed_at, closed_by |

মাইগ্রেশন `2027_01_04_000100_create_knitting_yarn_issue` (সুতা বাইরে যাওয়া):

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_issue_reqs` + `knit_yarn_issue_req_lines` | Store requisition: req_no (YIR-…), req_date, req_type (২২টা ধরন), store, buyer, order, party, program_no, status (OPEN / PARTLY_ISSUED / FULLY_ISSUED / CLOSED / CANCELLED); লাইনে yarn, lot_no, uom, req_qty, kp_alloc, issued_qty |
| `knit_yarn_issues` + `knit_yarn_issue_lines` | Issue: issue_no (YIS-…), issue_date, req_id, store, gate_pass_no; লাইনে req_line_id, lot_id, qty, cones |
| `knit_yarn_issue_returns` + lines | Return to store: return_no (YIRT-…), return_date, req_type, store, req_id, return_by, status (DRAFT / ISSUED / ACCEPTED / REJECTED / CANCELLED); লাইনে issue_line_id (বা ফাঁকা), yarn, lot_no, ret_qty, recv_qty, lot_id |
| `knit_yarn_party_challans` + lines | Party challan: challan_no (YPC-…), challan_date, party_id, store, gate_pass_no, vehicle_no, status; লাইনে lot_id, qty, rate, cones |
| `knit_yarn_replacements` + lines | Replacement: challan_no (YRP-…), challan_date, store, supplier, claim_ref, return_type (৫টা), lc_no, lot_ref (বড় হাতের), status (DRAFT / SUBMITTED / ACCEPTED / CANCELLED); লাইনে lot_id, cartons, qty |

খাতায় নতুন kind: ISSUE (−), ISSUE_RETURN (+), PARTY_ISSUE (−), REPLACE (−)।

**মজুদ লাইন (lot) কীভাবে চেনা হয়:** store + yarn item + count + রং + brand + lot # + supplier + buyer (বা buffer)। প্রথমবার দেখা গেলে তৈরি হয়। প্রতিটা ডকুমেন্ট নিজের আগের পোস্টিং মুছে নতুন করে পোস্ট করে।

মাইগ্রেশন `2027_01_12_000100_create_knitting_yarn_dyeing` (Y/D প্রোগ্রাম আর তার revision [04_Knit_Production](04_Knit_Production.md)-এ):

| টেবিল | ফিল্ড |
|---|---|
| `knit_yd_receipts` + lines | receive_no (YDR-…), receive_date, program_id, supplier_id (প্রোগ্রামের ডাই হাউস), working_ref, challan_no, challan_date, store_id, status (DRAFT / CONFIRMED / CANCELLED), remarks; লাইনে program_line_id (শেড), lot_no (কোনের লেবেলের ডাইড লট, বড় হাতে), qty, uom, cones, lot_id (Confirm-এ যে মজুদ লাইনে উঠল), remarks |
| `knit_yd_loss_memos` + lines | memo_no (YDL-…), memo_date, program_id, supplier_id, status (DRAFT / APPROVED / CANCELLED), remarks; লাইনে program_line_id, colour_group, lot_no, issued_qty, received_qty, remarks |
| `knit_yd_reprocesses` + lines | ref_no (YRP-…), kind (IN_HOUSE / SUB_CONTRACT), ref_date, supplier_id (Y/D Factory), party_id (S/C নিটার), bill_no, challan_no, challan_date, program_id, store_id, status (DRAFT / ISSUED / ACCEPTED / REJECTED / CANCELLED), remarks; লাইনে lot_id (ইন-হাউসে মজুদ লাইন), composition_id + yarn_count_id, colour_group (Shade Band), lot_no, qty, uom, reason, remarks |
| `knit_sc_yarn_challans` + lines | challan_no (পার্টির নিজের, ফাঁকা হলে SYC-…; অনন্য), challan_date, receipt_id (S/C Programme Receipt), store_id, status (DRAFT / ISSUED / ACCEPTED / REJECTED / CANCELLED), remarks; লাইনে composition_id + yarn_count_id, colour_group, brand, lot_no, qty, uom, cones, lot_id (Accept-এ), remarks |

## ৫. Workflow / Status ও Business Rules

- **Yarn Store Opening Balance:**
  - হেডার আর লাইন একসাথে সেভ হয়। নম্বর YOB-2026100001।
  - Store, OB Date আর Currency বাধ্যতামূলক। প্রতিটা লাইনে Source লাগে।
  - **হিসাব:** Total Qty = Pack Qty × Qty/Pack (দুটোই থাকলে), TTL Value = Total Qty × Cost Price।
  - Buyer না দিলে লাইনটা buffer মজুদ।
  - **Finalize (register থেকে):** তখন খাতায় পোস্ট হয়। এরপর আর বদলানো বা মোছা যায় না।
  - Register লাইন ধরে দেখায়। Store, Buyer, Yarn, Source, Lot, Buffer আর Finalized দিয়ে খোঁজা যায়।
- **Daily Yarn Receipt (MRR):**
  - **Recv. Opt.:**
    - Receive Direct: supplier হাতে বাছতে হয়।
    - Receive By PO: placed PO বাছলে তার খোলা লাইন আসে (বাকি পরিমাণ আর রেট)। Supplier আসে PO থেকে।
    - Receive By PI: আটকায়, কারণ Commercial-এর PI এখনো তৈরি হয়নি।
  - PO-তে যত বাকি তার বেশি নেওয়া যায় না। মেসেজ: "Line 1 receives more than the order still owes (1000)."।
  - Pack Qty × Qty/Pack দিলে Recv Qty নিজে থেকে বসে। তারপরও বদলানো যায়।
  - **নম্বর আর পোস্টিং:** সেভ হলেই নম্বর (YMRR-…) পায় আর খাতায় ওঠে। সাথে PO-র received আর অবস্থা (Placed / Part Received / Received) মিলে যায়।
  - **অবস্থার ধারা (register-এর বোতাম):** Store Receive → Store Verify → Audit Verify → Accounts Approve।
  - শুধু Store Receive অবস্থায় সংশোধন বা Cancel করা যায়। Cancel করলে সুতা খাতা থেকে নামে, আর PO আবার খুলে যায়।
- **Returns & Exchanges:**
  - Register-এর Return বা Exchange বোতাম একটা challan তোলে (YRC-…)। তাতে রিসিটের প্রতিটা লাইনের যতটা এখনো হাতে আছে ততটা আসে।
  - Exchange হলে challan type Returnable, Return হলে Non-Returnable।
  - প্রতিটা লাইন যতটা যাচ্ছে ততটুকুতে ছাঁটতে হয়। শূন্য দিলে লাইনটা বাদ পড়ে।
  - **Is Finalized?:** challan ইস্যু হয়, সুতা খাতা থেকে নামে, PO-র received কমে। এরপর আর বদলায় না।
  - লাইনে যত আছে, বা লটে যত আছে, তার বেশি ফেরত দেওয়া যায় না।
- **Daily Yarn Receipt Return:** ফেরত challan-এর তালিকা। Supplier আর দুই তারিখ (দুই প্রান্তসহ) দিয়ে খোঁজা যায়। একটা খুললে একই challan ডেস্কে আসে।
- **Yarn Racking Board:**
  - **Dashboard:** প্রতিটা মজুদ লাইনের Receive, Return, Racked, Rack Pending, Balance আর কোন র‍্যাকে কত। উপরে মোটের chip থাকে।
  - Rack Pending = গ্রহণ (OB আর রিসিট) − ফেরত − র‍্যাকে রাখা। Transfer এখনো নেই।
  - **Rack Assignment:** store বাছা বাধ্যতামূলক। প্রতিটা রিসিট লাইন আলাদা সারিতে আসে। Rack# দিলে সেই সারি রাখা হয়। Rack Qty খালি রাখলে পুরো বাকি রাখা হয়। বাকির বেশি রাখা যায় না।
- **Yarn Opening Entry:**
  - একটা store আর একটা মাস। প্রতিটা মজুদ লাইনের Opening, Receive, Return, Adjusted, Issued আর Closing চলাচল থেকে হিসাব হয়।
  - শুধু Adjustment আর Reason of Adj লেখা যায়। Adjustment দিলে কারণ বাধ্যতামূলক।
  - **Save:** মাসের শেষ দিনে `YPA-<store>-<মাস>` নামে পোস্ট হয়, আগেরটার জায়গায়।
  - **Finalise All:** store-এর মাস স্বাক্ষরিত হয়, grid শুধু দেখার জন্য থাকে।
  - **Filter:** Standing (Running / Closable / Finalized), Count, Col. Group, Composition, Brand আর Lot।
- **Yarn Stock Adjustment:**
  - গণনার সংশোধন। store-এর লট বেছে ± পরিমাণ দিতে হয়। Reason বাধ্যতামূলক।
  - কোনো লট তার মজুদের নিচে যায় না। সেভ হলে নম্বর পায় (YADJ-…) আর পোস্ট হয়।
  - **List:** chip (Adjustments, Approved, Net), Status আর তারিখ দিয়ে filter, খোঁজা, CSV Export, পাতা ভাগ। Cancel করলে সংশোধন খাতা থেকে নামে।
- **Yarn Issue against Indent:**
  - **Yarn Issue List:** requisition-এর তালিকা।
    - Filter: Requisition No, Date, Requisition Type, Action Status, Buyer, W.Style/Job/Order, Supp/Party আর Yarn Count।
    - Chip: Requisitions, TTL Req. Qty, TTL Issue Qty, Balance Qty।
    - বোতাম: Issue, Edit, Close, Cancel।
  - **Yarn Requisition Entry:**
    - Requisition Type বাধ্যতামূলক (Brickland-এর ২২টা ধরন)। Program# ঐচ্ছিক।
    - লাইনে Yarn Item, Count, Color, Brand, Lot #, UOM, Req. Qty আর KP Alloc।
    - সুতা ইস্যু হয়ে গেলে লাইন আর লেখা যায় না। তখন Close করতে হয়, আর Cancel চলে শুধু কিছু বেরোনোর আগে।
  - **Yarn Issue Entry:**
    - শুধু খোলা requisition বাছা যায়। Store আসে requisition থেকে।
    - তালিকার Issue বোতামে চাপলে প্রতিটা বাকি লাইন নিজে থেকে বসে। লট একটা হলে সেটাই বাছা থাকে।
    - প্রতিটা লাইনে বাছতে হয় সেই সুতার একটা লট (যে লট থেকে আসলে বেরোচ্ছে)।
    - লাইনের চাহিদার বেশি, বা লটে যত আছে তার বেশি, ইস্যু করা যায় না।
    - সেভ করলেই পোস্ট হয়। সাথে requisition-এর Issued আর অবস্থা (Open / Partly / Fully Issued) মিলে যায়।
    - ফেরত জমা পড়ার পর ইস্যু আর বদলায় না ("সংশোধন হলো ফেরত, সম্পাদনা নয়")।
- **Yarn Issue Return against Indent:**
  - Against Requisition বাছলে তার ইস্যু লাইনগুলো আসে, প্রতিটার ফ্লোরে থাকা পরিমাণসহ।
  - Ret হলো পাঠানো পরিমাণ, Recv হলো স্টোর যা গুনেছে।
  - ইস্যু লাইন ছাড়া লাইনও নেওয়া হয়। সেটা কিছু জমা করে না, শুধু স্টোরের buffer লটে ওঠে।
  - **অবস্থার ধারা (register):** Draft → Issue → Accept / Reject, আর Cancel।
  - **Accept:** গোনা ওজন লটে ফেরে আর requisition-এ জমা পড়ে। ইস্যু লাইনে যত বেরিয়েছিল তার বেশি ফেরত নেওয়া যায় না।
  - শুধু Draft সংশোধন করা যায়।
- **Party-Wise Yarn Issue Challan:**
  - Party, store আর store-এর লট থেকে লাইন (Qty, Rate, No of Cone)।
  - **অবস্থার ধারা:** Draft-এ কিছু নড়ে না। Issue করলে সুতা লট থেকে নামে। Accept মানে পার্টি নিয়েছে। Reject বা Cancel করলে সুতা ফেরত ওঠে।
  - বাতিল চালান নথিতে থেকে যায়, মোছা হয় না।
- **Supplier Yarn Replacement Register:**
  - Key Information অংশে Challan No, Date আর Store।
  - Consignment অংশে Supplier, Against Claim, Return Type (৫টা), L/C আর Lot।
  - লাইন শুধু সেই spinner-এর নিজের লট থেকে (Carton/Bag, Qty)।
  - লাইন না দিলে Brickland-এর মেসেজ আসে: "A challan with no yarn on it sends the spinner an empty lorry."।
  - **অবস্থার ধারা:** সেভ করলে Draft হয়। Submit করলে সুতা লট থেকে নামে। Accept মানে spinner-এর স্বাক্ষর ফিরেছে। Cancel করলে সুতা ফেরত ওঠে।
- **তিনটা challan তালিকা (Brickland-এর kit তালিকা):**
  - Chip, Challan Date-এর পরিসর, Status (Replacement-এ Supplier আর Return Type-ও)।
  - খোঁজা, প্রতিটা কলামের নিচে filter, Add New, CSV Export, ১০/২৫/৫০/১০০ পাতা।
- **খাতার প্রভাব:**
  - Opening Entry-র Issued = ইস্যু + party challan − গৃহীত ফেরত।
  - Replacement ধরা হয় Return কলামে, আর Racking Board-এর Return-এও।
- **Yarn Store Month Closing:**
  - **Show Exception:** কিছু না বদলে দেখায় কী বন্ধ আটকাবে:
    - মাস শেষ হয়নি।
    - কোনো MRR এখনো Store Receive অবস্থায়।
    - কোনো OB finalize হয়নি।
    - কোনো ফেরত challan ইস্যু হয়নি।
    - যে store-এ মাসে সুতা চলেছে তার Opening Entry grid finalize হয়নি।
    - কোনো return-to-store গৃহীত, প্রত্যাখ্যাত বা বাতিল হয়নি।
    - কোনো party challan বা replacement এখনো Draft।
  - **Month Close:** একই পরীক্ষা আবার করে, পাস করলে মাস বন্ধ করে।
  - বন্ধ মাসে কোনো ডকুমেন্ট আর পোস্ট হয় না। মেসেজ: "September 2026 is closed — its yarn movement is locked."।

### Y/D আর S/C স্ক্রিন (২০২৬-১১-০১)

- **In-House Dyed Yarn Receipt:** বাঁয়ে Receive List (যে প্রোগ্রামের এখনো পাওয়া বাকি: Y/D Program #, Supplier Name, Order, Pending (Kg))। প্রোগ্রাম বাছলে প্রতিটা বাকি শেড তার Pending নিয়ে লাইনে আসে; Add Shade হাতে লাইন যোগ করে। প্রতিটা লাইন এই প্রোগ্রামেরই শেড হতে হবে, Lot # আবশ্যক। Closed / Cancelled প্রোগ্রামে কিছু নেওয়া হয় না।
  - সেভ = Draft, কিছু নড়ে না। তালিকার **Confirm** স্টকে তোলে: প্রতিটা লাইন এক মজুদ লাইনে (স্টোর, শেডের সুতা, রং = শেডের Color Group (না থাকলে "Yarn Dyed"), ডাইড লট, সাপ্লায়ার = ডাই হাউস, প্রোগ্রামের বায়ার আর অর্ডার; Source In-house Dyed বা Yarn Dyed (Y/D) প্রোগ্রামের Type অনুযায়ী), মূল্য = qty × শেডের ডাইং রেট। খাতার kind `YD_RECEIVE`।
  - **Cancel** Draft বা Confirmed থেকে। Confirmed বাতিলে স্টক নামে, কিন্তু কোনো লটের কোন স্টোর থেকে বেরিয়ে গেলে আটকায় ("lot … has already gone out of the store")। Confirmed প্রাপ্তি আর সম্পাদনা হয় না।
- **Yarn Dyeing Loss Adjustment:** মেমোর প্রতিটা লাইন প্রোগ্রামের শেড; শেড বাছলে Issued Qty = প্রোগ্রামের Issue-এর শেডভিত্তিক ভাগ (শেডের Qty অনুপাতে), Received Qty = শেডের Delivery, Color Group আর গ্রে লট বসে। Received কখনো Issued-এর বেশি নয়। Loss Qty = Issued − Received, Loss % = Loss / Issued। শুধু **Approve** প্রোগ্রামের Loss বদলায়; Cancel Draft বা Approved থেকে।
- **YD Re-process (In-house / S/C Knitting):** লাইনে কারণ আবশ্যক (off-shade, streaky, tippy)। Y/D Program # ঐচ্ছিক, কিন্তু দিলে স্ক্রিনের ধরনের প্রোগ্রাম হতে হবে (In-house স্ক্রিনে In-House প্রোগ্রাম)।
  - In-house: Yarn = বাছা স্টোরের মজুদ লাইন (সুতা, লট আর একক সেখান থেকে)। **Issue** স্টোর থেকে নামায় (kind `YD_REPROCESS`, মজুদের বেশি নয়); **Reject** বা Issued থেকে **Cancel** ফেরত তোলে; **Accept** শুধু অবস্থা।
  - S/C Knitting: Party* (যে নিটারের কাছ থেকে কোন উদ্ধার), Yarn = Yarn Item + Count, Lot হাতে। এই সুতা কখনো আমাদের স্টকে ছিল না, তাই কোনো পোস্টিং নেই।
  - Issued / Accepted ডকুমেন্ট প্রোগ্রামের Issue-তে যোগ হয় (সুতা আবার পার্টির কাছে গেল)।
- **In-House Sub-Contract Yarn Receipt:** বাঁয়ে S/C Program List (Confirmed / In Production S/C Programme Receipt, যার সুতা পাওয়া বাকি: Order#, Style#, Date, Program#, Supplier)। প্রোগ্রাম না বেছে সেভ করলে Brickland-এর মেসেজ "Pick the S/C programme on the left first — a receive challan is always against one."। Challan# পার্টির নিজের (ফাঁকা হলে SYC-…), "/" থাকতে পারে।
  - সেভ = Draft; **Issue** স্টোরের হাতে দেয়; স্টোরের **Accept** স্টকে তোলে (স্টোর, সুতা, Color Group, Brand, Lot, সাপ্লায়ার = S/C পার্টি, অর্ডার = রিসিপ্টের প্রথম অর্ডার, Source S/C Party; kind `SC_YARN_RECEIVE`); **Reject**; Draft / Issued থেকে Cancel।
  - তালিকার Pending = রিসিপ্টের সুতার Required − Accepted চালান।
- **Yarn Dyeing Statement:** প্রোগ্রাম ধরে Programme (Kg), Greige Issue, Dyed Recv, Process Loss আর With Party (= Issue − Recv − Loss; ঋণাত্মক লাল)। Filter: Shipment Month (অর্ডারের ship date), Buyer A/C, Style/Order#, Y/D Program#, Party/Supplier, Program Status; Show চাপলে সার্ভারে খোঁজে। Print আর Export।

## ৬. API ও পারমিশন

- **সাধারণ:** `GET /knitting/ystore/options`, `GET /knitting/ystore/lots?store=DHK-YRN`
- **OB:**
  - `GET|POST /knitting/ystore/obs`, `GET|PUT|DELETE …/obs/{OB#}`
  - `POST …/obs/{OB#}/finalize`
- **MRR:**
  - `GET /knitting/ystore/receipts/options`, `GET …/receipts/po/{PO#}`
  - `GET|POST …/receipts`, `GET|PUT …/receipts/{MRR#}`
  - `POST …/receipts/{MRR#}/{verify|audit|approve|cancel}`, `POST …/receipts/{MRR#}/return` (type RETURN/EXCHANGE)
- **Return:** `GET /knitting/ystore/returns`, `GET|PUT|DELETE …/returns/{YRC#}`
- **Racking:** `GET …/racks/board`, `GET …/racks/pending?store=`, `POST …/racks`
- **Opening Entry:** `GET|PUT …/period?store=&period=`, `POST …/period/finalize`
- **Month Close:** `GET …/closes`, `GET …/closes/exceptions?period=`, `POST …/closes`
- **Adjustment:** `GET|POST …/adjustments`, `GET …/adjustments/{ADJ#}`, `POST …/adjustments/{ADJ#}/cancel`
- **Issue against Indent:**
  - `GET /knitting/ystore/issue-reqs/options`
  - `GET|POST …/issue-reqs`, `GET|PUT …/issue-reqs/{YIR#}`
  - `GET …/issue-reqs/{YIR#}/source`, `POST …/issue-reqs/{YIR#}/{close|cancel}`
  - `POST …/issues`, `GET|PUT …/issues/{YIS#}`
- **Outward challan:**
  - `GET /knitting/ystore/outward/options`
  - `GET|POST …/issue-returns`, `GET …/issue-returns/source/{YIR#}`, `GET|PUT …/issue-returns/{YIRT#}`, `POST …/{issue|accept|reject|cancel}`
  - `GET|POST …/party-challans`, `GET|PUT …/party-challans/{YPC#}`, `POST …/{issue|accept|reject|cancel}`
  - `GET|POST …/replacements`, `GET|PUT …/replacements/{YRP#}`, `POST …/{submit|accept|cancel}`
- **URL-এ কী যায়:** ডকুমেন্ট যায় নম্বর দিয়ে (`?ref=YMRR-2026100001`), store যায় কোড দিয়ে, মাস যায় 2026-10 আকারে। কোনো ULID নয়।
- **পারমিশন:** নতুন `knitting.ystore.view` আর `knitting.ystore.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF কোনোটাই পায় না।
- **Y/D আর S/C:** `/knitting/ystore/yd/options`, `yd/receipts` (+ `/{YDR}`, `/{YDR}/confirm|cancel`), `yd/loss-memos` (+ `/{YDL}/approve|cancel`), `yd/reprocess/{in-house|sc-knitting}` (+ `/{YRP}/issue|accept|reject|cancel`), `yd/statement`, `sc-yarn-challans` (+ `/{challan}/issue|accept|reject|cancel`; challan নম্বরে "/" চলে বলে route-এ `where('no', '.+')`)। দেখা `knitting.ystore.view`, লেখা আর সব অবস্থা বদল `knitting.ystore.manage`।

## ৭. Frontend পেজ (লাইট থিম)

- **সাইডবার:** Knit Production → Yarn Store ফোল্ডার (Yarn Procurement-এর পরে), এগারোটা স্ক্রিন, Brickland-এর ক্রমে (Racking Board-এর পরে Issue, Issue Return, Party-Wise Challan, Replacement)।
- **কম্পোনেন্ট** (`components/knitting/`):
  - `ystore-receipt.jsx`: MrrList, MrrEntry, ReturnDesk, ReturnList।
  - `ystore-ob.jsx`: ObList, ObEntry, AdjList, AdjEntry।
  - `ystore-misc.jsx`: RackBoard, RackAssign, OpeningGrid, MonthClose।
  - `ystore-kit.jsx`: KitList (তিনটা challan তালিকার সাধারণ register), lotOptions।
  - `ystore-issue.jsx`: IssueReqList, ReqEntry, IssueEntry।
  - `ystore-outward.jsx`: IssueReturnList/Entry, PartyChallanList/Entry, ReplacementList/Entry।
- **ট্যাব:**
  - Opening Balance: Yarn Opening Balance আর Yarn Opening Balance Entry।
  - Daily Yarn Receipt: Yarn Receive List, Yarn Receive Entry আর Returns & Exchanges।
  - Racking Board: Yarn Racking Dashboard আর Rack Assignment।
  - Receipt Return: Yarn Receive Return List আর Yarn Receive Return।
  - Adjustment: Yarn Adjustment List আর Yarn Adjustment।
  - Issue against Indent: Yarn Issue List, Yarn Requisition Entry আর Yarn Issue Entry।
  - Issue Return: Yarn Return to Store List আর Yarn Return Entry।
  - Party-Wise: Party Wise Yarn Issue Challan List আর Entry।
  - Replacement: Issue Yarn Replace by Supplier List আর Issue Yarn Replace by Supplier।
  - Opening Entry আর Month Closing একটাই প্যানেল।
- **Receiving Store:** Brickland-এর মতো Yarn store নিয়ে খোলে।
- **লোড আর ট্যাব:** খোঁজা, সেভ আর অবস্থা বদলানো পেজ রিলোড ছাড়া হয়। ট্যাব বদলায় `?tab=` দিয়ে।
- **Print/PDF:** Brickland-এর এই এগারোটা স্ক্রিনে Print বা PDF বোতাম নেই। Adjustment আর তিনটা challan তালিকায় Export আছে, সেটা এখানে CSV।
- **Y/D আর S/C** (`ystore-yd.jsx`): পাঁচটা ডেস্ক এক ধাঁচে — তালিকা (Search, Show / Reset — Show না চাপলে কিছু ছাঁটে না; সারিতে অবস্থা বদলের বোতাম আর Print) আর ফর্ম (Existing … picker, Key Information / Paperwork / Booked To ভাগ)। Dyed Receive আর S/C Challan-এর ফর্মের বাঁয়ে প্রোগ্রামের তালিকা (পার্টি filter, খোঁজা, পাতা)। Statement একক পাতা।
  - প্রিন্ট (সবগুলো `lib/print-document`, preview দেখে যাচাই): Dyed Yarn Receive Challan, Process Loss Memo, Yarn Re-process Challan, S/C Yarn Receive Challan, Knitting Y/D Statement (landscape)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১১টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_03_000100_create_knitting_yarn_store.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_04_000100_create_knitting_yarn_issue.php`
- `app/Modules/Knitting/Models/YarnLot.php`, `YarnMove.php`, `YarnOpeningBalance.php`, `YarnOpeningBalanceLine.php`, `YarnReceipt.php`, `YarnReceiptLine.php`, `YarnReturn.php`, `YarnReturnLine.php`, `YarnAdjustment.php`, `YarnAdjustmentLine.php`, `YarnRack.php`, `YarnPeriodLine.php`, `YarnMonthClose.php`
- `app/Modules/Knitting/Models/YarnIssueReq.php`, `YarnIssueReqLine.php`, `YarnIssue.php`, `YarnIssueLine.php`, `YarnIssueReturn.php`, `YarnIssueReturnLine.php`, `YarnPartyChallan.php`, `YarnPartyChallanLine.php`, `YarnReplacement.php`, `YarnReplacementLine.php`
- `app/Modules/Knitting/Support/YarnStore.php`
- `app/Modules/Knitting/Support/YarnProcurement.php` (presentStock এখন খাতা থেকে)
- `app/Modules/Knitting/Http/Controllers/Api/YarnReceiptApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnStoreApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnIssueApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnOutwardApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_12_000100_create_knitting_yarn_dyeing.php`
- `app/Modules/Knitting/Models/KnitYdReceipt.php`, `KnitYdReceiptLine.php`, `KnitYdLossMemo.php`, `KnitYdLossLine.php`, `KnitYdReprocess.php`, `KnitYdReprocessLine.php`, `KnitScYarnChallan.php`, `KnitScYarnChallanLine.php`
- `app/Modules/Knitting/Support/YarnDyeing.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYdStoreApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitScYarnChallanApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYdProgramApiController.php` (Statement)
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/ystore-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js` (`ystoreAction`)
- `frontend/src/components/knitting/yarn-common.jsx` (`useYs`, নতুন status রং)
- `frontend/src/components/knitting/ystore-receipt.jsx`
- `frontend/src/components/knitting/ystore-ob.jsx`
- `frontend/src/components/knitting/ystore-misc.jsx`
- `frontend/src/components/knitting/ystore-kit.jsx`
- `frontend/src/components/knitting/ystore-issue.jsx`
- `frontend/src/components/knitting/ystore-outward.jsx`
- `frontend/src/components/knitting/ystore-yd.jsx`
- `frontend/src/components/knitting/prod-yd.jsx` (`useYd`, `TONE` ভাগ করা)

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php` (`ystore.*`, `yd.*`)

**Test**
- `tests/Feature/Knitting/KnittingYarnStoreTest.php`
- `tests/Feature/Knitting/KnittingYarnIssueTest.php`
- `tests/Feature/Knitting/KnittingYarnDyeingTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **বাকি:** নেই। ১৭টা স্ক্রিনই তৈরি।

## ৯. টেস্ট ও যাচাই

- **`KnittingYarnStoreTest` ১/১ (১১৭ assertion):**
  - **OB:** Currency ছাড়া আটকায়। পরিমাণ ছাড়া লাইন আটকায়। ১০ × ৫০ = ৫০০; finalize-এর আগে খাতা ফাঁকা থাকে, finalize-এর পরে ৫০০ ওঠে। Finalize-এর পর সম্পাদনা আটকায়। Register-এ ১,৫০০ মূল্য দেখায়।
  - **MRR by PO:**
    - PI আটকায়। PO-র বাকির বেশি নিলে Brickland-এর মেসেজ আসে।
    - ৬০০ নিলে PO Part Received, আরও ৪০০ নিলে Received।
    - দ্বিতীয়টা Cancel করলে খাতা ৬০০-তে নামে আর PO আবার Part Received হয়।
    - বাতিল রিসিট থেকে ফেরত তোলা আটকায়।
  - **Return:** ৬০০-র challan ৫০-এ ছাঁটা। লাইনের বেশি দিলে আটকায়। ইস্যু হলে খাতা আর PO received দুটোই ৫৫০। ইস্যুর পরে সম্পাদনা আটকায়। তালিকায় দেখায়।
  - **ডেস্কের পরীক্ষা:** ক্রম ভাঙলে মেসেজ আসে ("… is Store Receive, so it cannot be audit verified.")। Verify → Audit → Approve চলে। এরপর সংশোধন আটকায়।
  - **Adjustment:** মজুদের নিচে নামানো আটকায়। কারণ ছাড়া আটকায়। −২০ দিলে ৪৮০ হয়। Cancel করলে ৫০০-তে ফেরে। List-এ মূল্য দেখায়।
  - **Racking:** store ছাড়া আটকায়। বাকি ৫৫০। বাকির বেশি রাখা আটকায়। R-01-এ ৩০০ আর R-02-এ খালি পরিমাণে বাকি ২৫০ রাখা হয়। Board-এ racks দেখায়।
  - **Opening Entry grid:** Opening, Receive, Adjusted আর Closing ঠিক আসে। কারণ ছাড়া Adjustment আটকায়। +৫ দিলে closing ৪৮৫, আর YPA-ST-YRN-… হিসেবে পোস্ট হয়।
  - **Month Close:**
    - grid finalize না হলে GRID exception আসে, আর Close আটকায় (Brickland-এর মেসেজ)।
    - Finalise All-এর পর grid বদলায় না, exception থাকে না, মাস বন্ধ হয়।
    - আবার বন্ধ করা আটকায়। বন্ধ মাসে Adjustment আটকায়।
    - চলতি মাস দেখায় "UNFINISHED"।
  - **CS-এর Present Stock** খাতা থেকে ১,০৩৫ আসে। lots picker ঠিক।
  - **পারমিশন:** Line Chief ঢুকতে পারে না।
- **`KnittingYarnIssueTest` ১/১ (৯৯ assertion):**
  - **Requisition:** Type ছাড়া আটকায়। লাইন ছাড়া আটকায়। YIR-…0001, ৬৫০ চাহিদা।
  - **Source:** লাইন ১-এ L-1 আর L-2 আসে, লাইন ২-এ (অন্য count) কোনো লট আসে না।
  - **Issue:**
    - ৭০০ দিলে আটকায় ("… past what it asked for (600 left)")।
    - ভুল সুতার লট আটকায়।
    - ৪০০ দিলে লট ৬০০-তে নামে, requisition Partly Issued।
    - এরপর requisition সম্পাদনা আটকায়, Cancel-ও আটকায় ("… is Partly Issued, so it cannot be cancelled.")।
    - ইস্যু ৪৫০-এ সংশোধন হয়, পরে আরও ১৫০।
  - **Return:**
    - Return By ছাড়া আটকায়। Draft থেকে Accept আটকায়।
    - Issue → Accept-এর পর লট ৪২৮, আর MIX লট (buffer) ৫। লাইনের issued ৫৭২।
    - এরপর ফেরত আর ইস্যু দুটোর সম্পাদনাই আটকায়।
    - ৪২২-এর বেশি ফেরত Accept-এ আটকায়, তারপর Cancel।
  - **Close:** বন্ধ requisition-এ ইস্যু আটকায়।
  - **Party challan:**
    - লটের বেশি দিলে আটকায়। Draft-এ মজুদ নড়ে না।
    - Issue করলে লট ৩২৮, Reject করলে ৪২৮।
    - দ্বিতীয়টা Issue → Accept হয়। Accept-এর পর Cancel আটকায়।
  - **Replacement:**
    - লাইন ছাড়া Brickland-এর মেসেজ আসে। অন্য supplier-এর লট আটকায়।
    - lot_ref বড় হাতের হয় (L-2)।
    - Submit করলে লট ১৮০, Cancel করলে ২০০। আবার Submit → Accept।
  - **Grid:** L-1 Issued ৬২২, Closing ৩৭৮। L-2 Return ২০।
  - **Month close:** Draft party challan PARTY exception দেয়।
  - **পারমিশন:** Line Chief ঢুকতে পারে না।
- Knitting ফোল্ডার (৮টা টেস্ট) পুরো run-এ চালানো হয়েছে।
  - বাকি সব পাস করেছে। শুধু KnittingMasterTest-এ একবার "Rate limiter … not defined" এসেছিল, backend restart-এর সময়।
  - আলাদা চালালে KnittingMasterTest ৫/৫ পাস করে।
- **ব্রাউজার (dev, ys01_flow, ys01_flow2, ys01_grid; ডাটা রাখা আছে):**
  - **OB YOB-2026100001:** 100% Cotton 20/s Natural, Square, Lot OB-301, ১২ × ৪৫ = ৫৪০ kg @ ৩.১০। Finalized।
  - **MRR YMRR-2026100001:** YPO-2026120001-এর বিপরীতে, Chalan SQT-CH-5541। ৫০০ (১০ × ৫০) + ৩০০ (Lot GR-77)। PO এখন Part Received।
  - MRR Store Verify হয়েছে।
  - **Return YRC-2026100001:** ২০ kg, "Wet cones on two bags", ইস্যু হয়েছে। PO received এখন ৪৮০ + ৩০০।
  - **Rack:** A-01-এ ৩০০ আর B-04-এ ৩০০। Board-এ Rack Pending ১৮০।
  - **Adjustment YADJ-2026100001:** OB-301-এ −৫।
  - **Opening Entry:** Yarn store, 2026-10, Adjustment −১.৫ ("Cone weight on re-weigh")। Standing/Lot filter পরীক্ষা করা হয়েছে।
  - **Month Closing:** 2026-09-এর Show Exception দেখায় "nothing outstanding"। dev মাস বন্ধ করা হয়নি।
- **ব্রাউজার, দ্বিতীয় ভাগ (ys02_flow, ys02_flow2, ys02_flow3; ডাটা রাখা আছে):**
  - **YIR-2026100001:** Knitting - Bulk, LPP, KP-2026-014। দুই লাইন: 100% Cotton 30/1 Natural ২৫০ (KP Alloc ৫০) আর 60/40 24/1 Grey ১০০।
  - **YIS-2026100001:** তালিকার Issue বোতাম থেকে। লট নিজে থেকে বসেছে (SQ-3011 আর GR-77), Gate Pass GP-5501।
  - **YIRT-2026100001:** Ret ১২, Recv ১১.৫, "Floor 1 — Karim"। Issue → Accept হয়েছে। requisition এখন Partly Issued (২৩৮.৫ + ১০০)।
  - **YPC-2026100001:** Coats Bangladesh, GR-77 থেকে ৪০ kg @ ০.৩৫, ৩২ cone, DM-TA-11-2233। Issued।
  - **YRP-2026100001:** Square Textiles, CLAIM BY YARN TEST, LC-2026-118, Lot SQ-3011 (ছোট হাতে লেখা হলেও বড় হাতে বসেছে), ২ কার্টন, ২৫ kg। Submitted।
  - **Opening Entry 2026-10:**
    - SQ-3011: Return ৪৫ (২০ + replacement ২৫), Issued ২৩৮.৫।
    - GR-77: Issued ১৪০ (১০০ + party ৪০)।

- **Y/D আর S/C (২০২৬-১১-০১):** `KnittingYarnDyeingTest` — ১টা টেস্ট, ৯৭টা assertion, test2 DB-তে পাস।
  - Dyed receipt: অন্য প্রোগ্রামের শেড আটকায়; Draft-এ Delivery ০; Confirm-এ লট D-NV-1-এর ৫৮ kg স্টোরে (Source INHOUSE_DYED, রং Dark Shade, প্রোগ্রামের বায়ার); Confirmed আর সেভ হয় না; শেডের Pending আর Issue-এর ভাগ ঠিক।
  - Loss memo: খালি তারিখে "Date is required."; Received > Issued আটকায়; Draft-এ Statement বদলায় না, Approve-এ Loss ৩ আর With Party ০ (Shipment Month, Buyer আর Status filter সহ)।
  - Re-process: কারণ আবশ্যক; ভুল ধরনের প্রোগ্রাম আটকায়; In-house Issue-এ লট ৫৮ → ৪৮ আর প্রোগ্রামের Issue ১১০, Reject-এ ফেরত; Issued ডকুমেন্ট সম্পাদনা আটকায়; লট বেরিয়ে গেলে receipt বাতিল আটকায়; S/C-তে Party আবশ্যক, Accept-এ কোনো স্টক নেই; ভুল route-এ (in-house দিয়ে S/C নম্বর) 404।
  - S/C Yarn Challan: প্রোগ্রাম ছাড়া Brickland-এর মেসেজ; "AK/CH-1" নম্বর, একই নম্বর দ্বিতীয়বার আটকায়, ফাঁকায় SYC-…; Draft থেকে Accept আটকায়; Issue-এ স্টক নেই, Accept-এ ১৫০ kg (S/C Party); Pending ৫০।
- **নিজে ব্রাউজারে যাচাই (dev, ডাটা রাখা আছে; প্রোগ্রাম YDP-2026110001 — [04](04_Knit_Production.md)):**
  - **YDR-2026110001:** SQ/DY-1101, Navy Blue ৫৮ kg (D-NV-2611, ৪৮ কোন) + Navy/White Stripe ৩৯ kg (D-NW-2611, ৩২ কোন)। বাঁয়ের তালিকা থেকে প্রোগ্রাম বাছলে লাইন Pending নিয়ে এল; তালিকা থেকে Confirm।
  - **YDL-2026110001:** Navy ৬০/৫৮, Navy/White ৪০/৩৯ → Loss ৩ (৩.০০%), Approved। খালি ফর্মে সেভ: "Y/D Program # is required."।
  - **YRP-2026110001** (In-house): RP-1101, D-NV-2611-এর ৫ kg "Streaky - run again", Issued। **YRP-2026110002** (S/C): Coats Bangladesh-এর কাছ থেকে AK-77-এর ৬ kg "Tippy", Draft।
  - **AKC/CH-0091:** SQT-KP-0091-এর বিপরীতে Square-এর SQ-5501, ১৫০ kg, ১২০ কোন; Issue → Accept।
  - Statement: YDP-2026110001-এ Issue ১০০, Recv ৯৭, Loss ৩, With Party ০ (re-process-এর আগে)।
  - পাঁচটা প্রিন্টের preview দেখা হয়েছে; Loss Memo-তে Lot # কলাম ভাঙছিল আর Loss % দুই রকম ঘরে দেখাচ্ছিল — ঠিক করা।

## ১০. খোলা প্রশ্ন

- **Receive By PI:** Commercial-এর PI তৈরি না হওয়া পর্যন্ত আটকানো। মেসেজ: "No proforma invoice is on record yet — receive by PO or direct."।
- **Opening Entry-র কিছু filter:** Brickland-এর Spin Type, Cotton Type, Slub?, Mellange?, Solid Yarn? আর Y/D Yarn? রাখা হয়নি। আমাদের Yarn Count master-এ এই বৈশিষ্ট্যগুলো নেই। লাগলে count master-এ যোগ করতে হবে।
- **Transfer:** Rack Pending-এর সূত্রে Brickland "Trns Recv. − Trns Sent" ধরে। store transfer তৈরি হলে যোগ হবে।
- **Brickland-এ ডাটা দিয়ে প্রবাহ চালানো যায়নি:** demo-তে Yarn Item নেই, তাই কোনো রিসিট বা OB সেভ করা যায়নি। Return, Exchange, desk check আর month close-এর ধারা বানানো হয়েছে Brickland-এর স্ক্রিন নোট থেকে ("A challan is raised from the register's Return or Exchange button…", "The four desk checks — store, audit and accounts — are taken on the register…", "Show Exception lists what would stop the close…")।
- **Requisition-এর "Job/W.Style" আর "Order":** Brickland-এ দুটো আলাদা select, দুটোই অর্ডার বোঝায়। এখানে একটাই, Job/W.Style (অর্ডারের "style · order#" লেবেলসহ)।
- **Against Claim:** claim আসে Yarn Test থেকে (এখনো তৈরি হয়নি)। তাই select-এ শুধু "-- No claim --"।
- **Issue / Party / Replacement-এর লাইন:**
  - Brickland-এ Yarn Item, Brand আর Lot # আলাদা ঘরে লেখা হয়।
  - এখানে store-এর লট (মজুদ লাইন) বাছা হয়, তাতে yarn, brand, lot আর মজুদ একসাথে দেখায়। খাতা লট ধরেই চলে।
- **Cat. ফিল্ড:** Brickland-এর MRR আর OB-তে "Cat." / "Category" (store item category) আছে। এখানে আইটেম সবসময় সুতা, তাই রাখা হয়নি।
- **S/C Yarn Receipt-এর তালিকা:** Brickland-এর S/C Program List-এ কোন প্রোগ্রাম আসে দেখা যায়নি (ফাঁকা)। এখানে Confirmed / In Production রিসিপ্ট, যার সুতার Required পুরো পাওয়া হয়নি। যে রিসিপ্টে সুতার লাইন নেই, সেটা তালিকায় আসে না।
- **Dyed receipt-এর Confirm কোথায়:** Brickland বলে "Confirm it on the Y/D Program List"; সেই ট্যাবের তালিকায় তাই প্রাপ্তিগুলো, সারিতে Confirm। Brickland-এ তালিকা ফাঁকা থাকায় কলাম দেখা যায়নি; এখানে প্রাপ্তির ফিল্ড থেকে বানানো।
- **Loss memo-র Issued Qty:** স্টোরের ইস্যু শেড ধরে হয় না (রিকুইজিশনের লাইনে শেড নেই), তাই শুরুতে প্রোগ্রামের Issue শেডের Qty অনুপাতে ভাগ করে বসে; হাতে বদলানো যায়।
- **Statement-এর Category:** Brickland-এর "-- Select Type --" আসলে store item category-র তালিকা (Yarn, Fabric, Button…)। এখানে সব সুতা, তাই রাখা হয়নি।

## ১১. As-built নোট

- **15/04-এর সাথে ভাগ (২০২৬-১০-৩১):** রিকুইজিশন (`knit_yarn_issue_reqs`) আর Yarn Issue Return challan এখন 15/04-এর General Yarn Issue Indent আর Yarn Return to Yarn Store থেকেও লেখা হয় — একই টেবিল, একই API। সেই endpoint-গুলো এখন `knitting.ystore.*` অথবা `knitting.production.*` মানে; ইস্যু আর return-এর Accept / Reject আগের মতো শুধু স্টোরের। দেখুন [04_Knit_Production.md](04_Knit_Production.md)।

**আংশিক (২০২৬-১০-২৭):** ১১টা স্ক্রিন তৈরি (প্রথম ভাগে ৭টা, দ্বিতীয় ভাগে Issue, Issue Return, Party-Wise Challan আর Replacement)।

- **Brickland প্রোব:**
  - ys01: প্রথম সাতটা স্ক্রিনের ফর্ম, ট্যাব, বোতাম আর নোট।
  - ys02/ys03: Issue, Return, Party, Replacement। সাথে Requisition Type-এর ২২টা অপশন, পার্টি আর রঙের তালিকা।
- **খাতা:** সব ডকুমেন্ট `knit_yarn_moves`-এ পোস্ট করে। এর ফলে:
  - Opening Entry-র grid, Racking Board, lot picker আর 15/02-এর CS-এর Present Stock একই খাতা পড়ে।
  - PO-র Part Received / Received অবস্থা রিসিট আর ফেরত মিলিয়ে চলে।
- **Month lock:** `YarnStore::assertOpen`। বন্ধ মাসে OB finalize, MRR সেভ/cancel, ফেরত ইস্যু, Adjustment আর grid সেভ সবই আটকায়।
- **Wildcard লাইনের ক্রম:** সব ystore controller এখন `YarnStore::inOrder()` দিয়ে validate-এর পরে লাইন পাঠানো ক্রমে সাজায় (ksort)। আগে দুই লাইনের রিসিট বা OB-তে ক্রম বদলে যেতে পারত।
