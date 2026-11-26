# Knit Production

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩২টার ৩২টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/04`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Bulk Fabric Production Order | `/m/knitting/fabric-production-order-bulk` | 3 | 6 | 0 |
| 2 | Flat Knit Grey Fabric Transfer | `/m/knitting/flat-knit-grey-transfer` | 2 | 17 | 1 |
| 3 | Yarn-Dyed Fabric Production Order | `/m/knitting/fabric-production-order-yd` | 3 | 6 | 0 |
| 4 | Grey Store to Batch Transfer | `/m/knitting/grey-fabric-transfer-grey-to-batch` | 2 | 16 | 1 |
| 5 | Sample Fabric Production Order | `/m/knitting/fabric-production-order-sample` | 3 | 6 | 0 |
| 6 | Flat Bed Rib Production Order | `/m/knitting/fabric-production-order-flat-bed-rib` | 3 | 6 | 0 |
| 7 | Manual Roll Production Entry | `/m/knitting/roll-production-manual` | 2 | 15 | 0 |
| 8 | Operator Roll Production Entry | `/m/knitting/roll-production-operator` | 2 | 2 | 0 |
| 9 | Bulk Solid Sub-Contract Knit Programme | `/m/knitting/issue-sc-knitting-program-bulk-solid` | 2 | 27 | 2 |
| 10 | Sample Solid Sub-Contract Knit Programme | `/m/knitting/issue-sc-knitting-program-sample-solid` | 1 | 10 | 1 |
| 11 | Yarn-Dyed Sub-Contract Knit Programme | `/m/knitting/issue-sc-knitting-program-party-yd` | 2 | 27 | 2 |
| 12 | Yarn Twisting Entry | `/m/knitting/yarn-twist` | 4 | 45 | 1 |
| 13 | Order-to-Order Grey Transfer | `/m/knitting/grey-fabric-transfer-order-to-order` | 2 | 26 | 1 |
| 14 | Grey Reuse from Leftover Store | `/m/knitting/grey-fabric-reuse-from-left-over-store` | 2 | 26 | 1 |
| 15 | Grey Store to Leftover Transfer | `/m/knitting/grey-fabric-transfer-grey-store-to-left-over` | 2 | 27 | 1 |
| 16 | Batch Store to Leftover Transfer | `/m/knitting/grey-fabric-transfer-batch-store-to-left-over` | 1 | 0 | 0 |
| 17 | General Yarn Issue Indent | `/m/knitting/general-requisition-for-yarn-issue` | 2 | 31 | 1 |
| 18 | Knit Machine Loading Plan | `/m/knitting/machine-loading-plan` | 1 | 8 | 0 |
| 19 | Yarn Return to Yarn Store | `/m/knitting/yarn-return-to-store` | 2 | 27 | 1 |
| 20 | Yarn Dyeing Programme Register | `/m/knitting/yd-program-list` | 2 | 19 | 1 |
| 21 | Sub-Contract Programme Receipt | `/m/knitting/receive-sc-program-from-party` | 1 | 34 | 2 |
| 22 | Daily Knit Fabric Output | `/m/knitting/daily-fabric-production` | 1 | 5 | 0 |
| 23 | Solid Sample Knit Plan | `/m/knitting/knit-plan-for-sample-solid` | 1 | 20 | 1 |
| 24 | Yarn-Dyed Sample Knit Plan | `/m/knitting/knit-plan-for-sample-yd` | 1 | 20 | 1 |
| 25 | Grey Roll Quality Inspection | `/m/knitting/grey-fab-roll-inspection` | 1 | 26 | 1 |
| 26 | Supplier Yarn Replacement Indent | `/m/knitting/requisition-for-yarn-replace-by-supplier` | 2 | 34 | 1 |
| 27 | Production Test Clearance | `/m/knitting/test-for-production` | 1 | 14 | 1 |
| 28 | Approval Without Testing | `/m/knitting/approval-without-test` | 1 | 3 | 0 |
| 29 | Direct Knit Fabric Booking | `/m/knitting/direct-fabric-booking` | 1 | 17 | 1 |
| 30 | Grey Fabric Racking Board | `/m/knitting/grey-fabric-racking-board` | 1 | 6 | 0 |
| 31 | Knit Plan & Yarn Test Indent | `/m/knitting/knit-plan-requisition-for-yarn-test` | 2 | 30 | 1 |
| 32 | Yarn Test Result Register | `/m/knitting/yarn-test-register` | 2 | 10 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Bulk Fabric Production Order

`/m/knitting/fabric-production-order-bulk`

**ট্যাব: Fabric Production Knitting Order (Bulk) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | text |  |  |
| Buyer | select |  |  |
| Order/Style# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status | select |  | Running, Completed, Hold, Closed, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order, -- Select Month --, --Style --, Show, Reset

**ট্যাব: Knit Programmes (KP)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Programmes
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

**ট্যাব: Knitcard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Knit Cards
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

#### Flat Knit Grey Fabric Transfer

`/m/knitting/flat-knit-grey-transfer`

**ট্যাব: Collar Cuff Transfer 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| From Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Trans Ref# | text |  |  |
| Status | select |  | Create Draft, Submit Requisition, Receive Requisition, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Collar Cuff Transfer 0, New Collar/Cuff Transfer, Show, Reset

**ট্যাব: New Collar/Cuff Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Transfer# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Category* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Type* | select | ✔ | Collar, Cuff, Collar & Cuff |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Trans Ref# | text |  | The receiving end's own reference |
| Remarks | textarea |  |  |
- সেকশন: New Collar/Cuff Transfer, Key Information, From / To, Reference, Rolls
- বাটন/অ্যাকশন: Collar Cuff Transfer 0, New Collar/Cuff Transfer, Add Roll, Back To List, Clear, Save transfer

**ডায়ালগ: New Collar/Cuff Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Transfer# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Category* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Type* | select | ✔ | Collar, Cuff, Collar & Cuff |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Trans Ref# | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A transfer with no rolls on it moves nothing.

#### Yarn-Dyed Fabric Production Order

`/m/knitting/fabric-production-order-yd`

**ট্যাব: Fabric Production Knitting Order (YD) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | text |  |  |
| Buyer | select |  |  |
| Order/Style# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status | select |  | Running, Completed, Hold, Closed, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Fabric Production Knitting Order (YD) 0, Knit Programmes (KP), Knitcard, Save Production Order, -- Select Month --, --Style --, Show, Reset

**ট্যাব: Knit Programmes (KP)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Programmes
- বাটন/অ্যাকশন: Fabric Production Knitting Order (YD) 0, Knit Programmes (KP), Knitcard, Save Production Order

**ট্যাব: Knitcard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Knit Cards
- বাটন/অ্যাকশন: Fabric Production Knitting Order (YD) 0, Knit Programmes (KP), Knitcard, Save Production Order

#### Grey Store to Batch Transfer

`/m/knitting/grey-fabric-transfer-grey-to-batch`

**ট্যাব: Grey Fabric Transfer (Store to Store) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Trans Ref# | text |  |  |
| Status | select |  | Create Draft, Submit Requisition, Receive Requisition, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Grey Fabric Transfer (Store to Store) 0, New Grey Transfer, Show, Reset

**ট্যাব: New Grey Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Transfer# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Category* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Batch* | select | ✔ |  |
| Trans Ref# | text |  | The receiving end's own reference |
| Remarks | textarea |  |  |
- সেকশন: New Grey Transfer, Key Information, From / To, Reference, Rolls
- বাটন/অ্যাকশন: Grey Fabric Transfer (Store to Store) 0, New Grey Transfer, Add Roll, Back To List, Clear, Save transfer

**ডায়ালগ: New Grey Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Transfer# | text |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Category* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store | select |  | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Batch* | select | ✔ |  |
| Trans Ref# | text |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A transfer with no rolls on it moves nothing.

#### Sample Fabric Production Order

`/m/knitting/fabric-production-order-sample`

**ট্যাব: Fabric Production Knitting Order (Sample) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | text |  |  |
| Buyer | select |  |  |
| Order/Style# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status | select |  | Running, Completed, Hold, Closed, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order, -- Select Month --, --Style --, Show, Reset

**ট্যাব: Knit Programmes (KP)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Programmes
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

**ট্যাব: Knitcard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Knit Cards
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

#### Flat Bed Rib Production Order

`/m/knitting/fabric-production-order-flat-bed-rib`

**ট্যাব: Fabric Production Knitting Order (Flat Bed RIB) 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | text |  |  |
| Buyer | select |  |  |
| Order/Style# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status | select |  | Running, Completed, Hold, Closed, Cancelled |
- সেকশন: Search
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order, -- Select Month --, --Style --, Show, Reset

**ট্যাব: Knit Programmes (KP)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Programmes
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

**ট্যাব: Knitcard**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Production Order, Knit Cards
- বাটন/অ্যাকশন: Knit Programmes (KP), Knitcard, Save Production Order

#### Manual Roll Production Entry

`/m/knitting/roll-production-manual`

**ট্যাব: Roll List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Job Card No | select |  |  |
| M/C | select |  |  |
| Movement Status | select |  | In Knitting, Grey Store, Batch Store, Left Over, Delivered |
| Quality Status | select |  | Pending, Passed, Hold, Rejected |
| From | date |  |  |
| To | date |  |  |
- সেকশন: Job Card
- বাটন/অ্যাকশন: Roll List 0, Roll Weight Entry, Go, Reset

**ট্যাব: Roll Weight Entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| M/C | select |  |  |
| Search Job Card Detail | search |  | Card, job, buyer, fabric… |
| Existing roll sheet | select |  |  |
| Job Card No* | select | ✔ |  |
| Buyer / Style / Order | text |  | Filled in from the card |
| Yarn Details | text |  |  |
| M/C Dia | text |  |  |
| Finish Dia | text |  |  |
| Gauge | text |  |  |
- গ্রিড কলাম: Job Card #, Buyer, Fab Type, Color, Left Qty
- সেকশন: Job Card Detail, Roll Weight Entry, Machine, Roll List
- বাটন/অ্যাকশন: Roll List 0, Roll Weight Entry, Add Roll, Back To List, Clear, Save roll sheet

#### Operator Roll Production Entry

`/m/knitting/roll-production-operator`

**ট্যাব: Grey Fabric Roll Production 0**
- বাটন/অ্যাকশন: Grey Fabric Roll Production 0, Tab Settings for Machine(s), Save Station

**ট্যাব: Tab Settings for Machine(s)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| station | select |  |  |
| Search stations | search |  | Search 0 stations… |
- সেকশন: Tab Settings for Machine(s), Search By, STATIONS, MACHINE LIST
- বাটন/অ্যাকশন: Grey Fabric Roll Production 0, Tab Settings for Machine(s), Save Station, Load, New

#### Bulk Solid Sub-Contract Knit Programme

`/m/knitting/issue-sc-knitting-program-bulk-solid`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prod.Category | select |  |  |
| Party | select |  |  |
| Buyer A/C | select |  |  |
| Job/Work Style | select |  |  |
| Program | search |  | Search Program |
| Status | select |  | Draft Created, Submitted for Approval, Approved, Rejected, Cancelled |
| PO Date from | date |  |  |
| PO Date to | date |  |  |
| Search Bulk solid-yarn knitting programmes issued to sub-contract knitters, and  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style, ACTION
- বাটন/অ্যাকশন: New S/C Knitting Program — Bulk (Solid), Clear, Add New, Export, Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style

**ট্যাব: New S/C Knitting Program — Bulk (Solid)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- সেকশন: New S/C Knitting Program — Bulk (Solid), Programme, Party, Booked Against, Programme Fabrics
- বাটন/অ্যাকশন: New S/C Knitting Program — Bulk (Solid), -- Party --, -- Select Type --, -- Style --, Add Fabric, Cancel, -- Color --, -- Group --, Select…, Back To List, Clear, Save programme

**ডায়ালগ: New S/C Knitting Program — Bulk (Solid)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no fabrics on it asks the party for nothing.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no fabrics on it asks the party for nothing.

#### Sample Solid Sub-Contract Knit Programme

`/m/knitting/issue-sc-knitting-program-sample-solid`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prod.Category | select |  |  |
| Party | select |  |  |
| Buyer A/C | select |  |  |
| Job/Work Style | select |  |  |
| Program | search |  | Search Program |
| Status | select |  | Draft Created, Submitted for Approval, Approved, Rejected, Cancelled |
| PO Date from | date |  |  |
| PO Date to | date |  |  |
| Search Sample solid-yarn knitting programmes issued to sub-contract knitters, an | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style, ACTION
- বাটন/অ্যাকশন: Clear, Add New, Export, Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Develop, Sample |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no fabrics on it asks the party for nothing.

#### Yarn-Dyed Sub-Contract Knit Programme

`/m/knitting/issue-sc-knitting-program-party-yd`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Prod.Category | select |  |  |
| Party | select |  |  |
| Buyer A/C | select |  |  |
| Job/Work Style | select |  |  |
| Program | search |  | Search Program |
| Status | select |  | Draft Created, Submitted for Approval, Approved, Rejected, Cancelled |
| PO Date from | date |  |  |
| PO Date to | date |  |  |
| Search Yarn-dyed knitting programmes issued out to a party, and where each has g | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style, ACTION
- বাটন/অ্যাকশন: New S/C Knitting Program — Party (Y/D), Clear, Add New, Export, Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow, Qty (Kg), Buyer A/C, Job/Work Style

**ট্যাব: New S/C Knitting Program — Party (Y/D)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- সেকশন: New S/C Knitting Program — Party (Y/D), Programme, Party, Booked Against, Programme Fabrics
- বাটন/অ্যাকশন: New S/C Knitting Program — Party (Y/D), -- Party --, -- Select Type --, -- Style --, Add Fabric, Cancel, -- Color --, -- Group --, Select…, Back To List, Clear, Save programme

**ডায়ালগ: New S/C Knitting Program — Party (Y/D)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no fabrics on it asks the party for nothing.

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| PO Date* | date | ✔ |  |
| Target Delivery Date | date |  |  |
| Type* | select | ✔ | Select…, Bulk, Short-Bulk |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order # | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style | text |  |  |
| Remarks | textarea |  |  |
| Fabric | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Qty(Kg) | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no fabrics on it asks the party for nothing.

#### Yarn Twisting Entry

`/m/knitting/yarn-twist`

**ট্যাব: Twist Program 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Supplier | select |  |  |
| Status | select |  | All, Draft, Submitted, Approved, Running, Closed, Cancelled |
| Program No | search |  | TWP-… |
| Search Yarn twisting programmes placed with outside twisters, with what each is  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Prg Date, Trgt Delv Date, Supplier, Grey (Kg), Received (Kg), Balance (Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Twist Program 0, Twist Program, Twist Yarn Receive, Yarn Twist Bill (S/C Out-house), Clear, Add New, Export, Program No, Prg Date, Trgt Delv Date, Supplier, Grey (Kg), Received (Kg), Balance (Kg), Remarks

**ট্যাব: Twist Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Supplier | select |  |  |
| Status | select |  | All, Draft, Submitted, Approved, Running, Closed, Cancelled |
| Program No | search |  | TWP-… |
| Search Yarn twisting programmes placed with outside twisters, with what each is  | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Program No, Prg Date, Trgt Delv Date, Supplier, Grey (Kg), Received (Kg), Balance (Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Twist Program 0, Twist Program, Twist Yarn Receive, Yarn Twist Bill (S/C Out-house), Clear, Add New, Export, Program No, Prg Date, Trgt Delv Date, Supplier, Grey (Kg), Received (Kg), Balance (Kg), Remarks

**ট্যাব: Twist Yarn Receive**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Twist Yarn Receive Challan List | search |  | Search Challan# / Gate Pass# / Program Ref# |
| Existing receive challan | select |  |  |
| Date* | date | ✔ |  |
| Program Ref#* | select | ✔ |  |
| Gate Pass# | text |  |  |
| Company* | select | ✔ | Select…, Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Finalized? Fixes the weights so a bill may be raised on them. A finalised challa | checkbox |  |  |
| Transfer? The twisted yarn has been taken onto the store's own stock. A separate | checkbox |  |  |
| Remarks | textarea |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Grey(Kg) | number |  |  |
| Receive(Kg) | number |  |  |
| Cones | number |  |  |
| Remarks | text |  |  |
- গ্রিড কলাম: Date, Challan#, Gate Pass#, Party, Program Ref#, G.Yarn Qty, Finalized?, Transfer?
- সেকশন: Twist Yarn Receive Challan List, Twist Yarn Receive, Challan, From / To, Standing, Challan Detail
- বাটন/অ্যাকশন: Twist Program 0, Twist Program, Twist Yarn Receive, Yarn Twist Bill (S/C Out-house), -- Select --, Select…, Add Line, Cancel, -- Group --, Clear, Save receive challan

**ট্যাব: Yarn Twist Bill (S/C Out-house)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Yarn Twist Bill List | search |  | Search Bill# / Party Bill# |
| Existing twist bill | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Program Ref# | select |  |  |
| Party Bill# | text |  |  |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Paid, Cancelled |
| Remarks | textarea |  |  |
| Challan# | select |  |  |
| Particulars | text |  |  |
| Yarn | select |  |  |
| Qty | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- গ্রিড কলাম: Date, Bill#, Supplier, Amount, Status
- সেকশন: Yarn Twist Bill List, Yarn Twist Bill (S/C Out-house), Bill Detail
- বাটন/অ্যাকশন: Twist Program 0, Twist Program, Twist Yarn Receive, Yarn Twist Bill (S/C Out-house), -- Select --, Add Line, Cancel, Select…, Clear, Save twist bill

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing twist programme | select |  |  |
| Prg Date* | date | ✔ |  |
| Trgt Delv Date | date |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Running, Closed, Cancelled |
| Remarks | textarea |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Grey(Kg) | number |  |  |
| TPI | number |  |  |
| Ply | number |  |  |
| Rate | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A twist programme with no yarn on it sends nothing out.

#### Order-to-Order Grey Transfer

`/m/knitting/grey-fabric-transfer-order-to-order`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  |  |
| Trans Ref# | search |  | Search… |
| Status | select |  | All, Create Draft, Submit Requisition, Receive Requisition, Cancelled |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Grey fabric moved from one order's account to another's, and where each t | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Transfer#, Trans Type, From Order#, To Order#, Buyer A/C, Trans Ref#, Rolls, Qty (Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Order wise Grey Fabric Transfer, Clear, Add New, Export, Date, Transfer#, Trans Type, From Order#, To Order#, Buyer A/C, Trans Ref#, Rolls, Qty (Kg), Remarks

**ট্যাব: Order wise Grey Fabric Transfer**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Order wise Grey Fabric Transfer, Transfer, From / To, Fabric Detail
- বাটন/অ্যাকশন: Order wise Grey Fabric Transfer, Add Fabric, Cancel, -- Color --, Back To List, Clear, Save transfer

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| To Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A transfer with no fabric on it moves nothing.

#### Grey Reuse from Leftover Store

`/m/knitting/grey-fabric-reuse-from-left-over-store`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  |  |
| Trans Ref# | search |  | Search… |
| Status | select |  | All, Create Draft, Submit Requisition, Receive Requisition, Cancelled |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Left-over grey pulled back into a live order instead of knitting new | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Transfer#, Trans Type, Style/Order#, From Store, Buyer A/C, Trans Ref#, Rolls, Qty (Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Grey Fabric Reuse from Left Over Store, Clear, Add New, Export, Date, Transfer#, Trans Type, Style/Order#, From Store, Buyer A/C, Trans Ref#, Rolls, Qty (Kg), Remarks

**ট্যাব: Grey Fabric Reuse from Left Over Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Style/Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Grey Fabric Reuse from Left Over Store, Transfer, From / To, Fabric Detail
- বাটন/অ্যাকশন: Grey Fabric Reuse from Left Over Store, Add Fabric, Cancel, -- Color --, Back To List, Clear, Save transfer

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Style/Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A transfer with no fabric on it moves nothing.

#### Grey Store to Leftover Transfer

`/m/knitting/grey-fabric-transfer-grey-store-to-left-over`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Style/Order# | select |  |  |
| Trans Ref# | search |  | Search… |
| Status | select |  | All, Create Draft, Submit Requisition, Receive Requisition, Cancelled |
| Date from | date |  |  |
| Date to | date |  |  |
| Search Unconsumed grey moved out of the grey and batch stores into left-over | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Transfer#, Trans Type, Style/Order#, From Store, To Store, Buyer A/C, Rolls, Qty (Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Grey Fabric Transfer to Left Over Store, Clear, Add New, Export, Date, Transfer#, Trans Type, Style/Order#, From Store, To Store, Buyer A/C, Rolls, Qty (Kg), Remarks

**ট্যাব: Grey Fabric Transfer to Left Over Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| Style/Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Grey Fabric Transfer to Left Over Store, Transfer, From / To, Fabric Detail
- বাটন/অ্যাকশন: Grey Fabric Transfer to Left Over Store, Add Fabric, Cancel, -- Color --, Back To List, Clear, Save transfer

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing transfer | select |  |  |
| Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Trans Type* | select | ✔ | Select…, Develop To Develop, Bulk To Bulk, Sample To Sample, Projection To Bulk |
| Trans Ref# | text |  |  |
| Buyer A/C | select |  |  |
| Style/Order#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| From Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| To Store* | select | ✔ | Cutting Sub-Store (ST-CUT), Finished Fabric Store (ST-FAB), GMT Sample Store (ST-SMP), Grey Fabric Store (ST-GRY), Knitt |
| Remarks | textarea |  |  |
| Roll | select |  |  |
| Roll # | text |  |  |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| Finish Dia | text |  |  |
| GSM | text |  |  |
| Rolls | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A transfer with no fabric on it moves nothing.

#### Batch Store to Leftover Transfer

`/m/knitting/grey-fabric-transfer-batch-store-to-left-over`

**ভিউ**
> 44 of 116 Scaffold
- সেকশন: This screen is scaffolded, not built

#### General Yarn Issue Indent

`/m/knitting/general-requisition-for-yarn-issue`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Requisition Date from | date |  |  |
| Requisition Date to | date |  |  |
| Requisition Type | select |  | All |
| Style No | select |  | All |
| Order No | select |  | All |
| Status | select |  | All, Open, Partly Issued, Fully Issued, Closed, Cancelled |
| Requisition No | search |  | Search… |
| Search Yarn requisitions raised off the knitting floor, with what has been issue | search |  | Search 0 records… |
| Filter by Style No | search |  |  |
| Filter by Order No | search |  |  |
| Filter by Requisition No | search |  |  |
| Filter by Requisition Date | search |  |  |
| Filter by Requisition Type | search |  |  |
| Filter by TTL Req. Qty | search |  |  |
| Filter by TTL Issue Qty | search |  |  |
| Filter by Balance Qty | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style No, Order No, Requisition No, Requisition Date, Requisition Type, TTL Req. Qty, TTL Issue Qty, Balance Qty, ACTION
- বাটন/অ্যাকশন: Yarn Issue Requisition, Clear, Add New, Export, Style No, Order No, Requisition No, Requisition Date, Requisition Type, TTL Req. Qty, TTL Issue Qty, Balance Qty

**ট্যাব: Yarn Issue Requisition**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition Date* | date | ✔ |  |
| Program No | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer A/C | select |  |  |
| Order No | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status* | select | ✔ | Select…, Open, Partly Issued, Fully Issued, Closed, Cancelled |
| Closed The store will issue nothing further against this requisition. A decision | checkbox |  |  |
| Remarks | textarea |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Req Qty | number |  |  |
- সেকশন: Yarn Issue Requisition, Requisition, From, Booked Against, Standing, Yarn Required
- বাটন/অ্যাকশন: Yarn Issue Requisition, -- Select Type --, -- Party --, -- Style --, Add Yarn, Cancel, -- Group --, Select…, Back To List, Clear, Save requisition

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition Date* | date | ✔ |  |
| Program No | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Buyer A/C | select |  |  |
| Order No | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Status* | select | ✔ | Select…, Open, Partly Issued, Fully Issued, Closed, Cancelled |
| Closed The store will issue nothing further against this requisition. A decision | checkbox |  |  |
| Remarks | textarea |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Req Qty | number |  |  |
- খালি Save-এ মেসেজ: A requisition with no yarn on it asks for nothing.

#### Knit Machine Loading Plan

`/m/knitting/machine-loading-plan`

**ট্যাব: Machine Loading Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | text |  | YYYY-MM-DD |
| Range | select |  | Range, 1W, 2W, 1M, 2M |
| Company # | select |  | All, Brickland Composite Ltd. |
| Floor | select |  | All, Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Dia | text |  | All |
| Machine # | select |  | All |
| Color | text |  | -- Select Color -- |
| KnitCard# | text |  | Search KnitCard#, Style#, Order |
- সেকশন: Machine Loading Plan, SEARCH, Pending Knit Card
- বাটন/অ্যাকশন: Planned Shifts 0, Machine Loading Plan, -- Select --, Search, Today

#### Yarn Return to Yarn Store

`/m/knitting/yarn-return-to-store`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Buyer | select |  |  |
| Program No | search |  | Search Program No… |
| Party Name | search |  | Search Party Name… |
| Job No/Style No | select |  |  |
| Requisition Type | select |  | All |
| Status | select |  | All, Draft, Issued, Accepted, Rejected, Cancelled |
| Search Yarn sent back to the store off the knitting floor, with what was returne | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Challan No, Challan Dt, Issue Ref#, Requisition Type, Company, Store Name, Return By, Ret, Recv, ACTION
- বাটন/অ্যাকশন: Yarn Return to Store, Clear, Add New, Export, Challan No, Challan Dt, Issue Ref#, Requisition Type, Company, Store Name, Return By, Ret, Recv

**ট্যাব: Yarn Return to Store**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Search Yarn Return to Store List | search |  | Search Challan No / Return By |
| Existing return challan | select |  |  |
| Challan Dt* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Against Requisition | select |  |  |
| Return By* | text | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Issued, Accepted, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Issue Line | select |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Ret | number |  |  |
| Recv | number |  |  |
| Remarks | text |  |  |
- গ্রিড কলাম: Challan No, Challan Dt, Store Name, Return By, Ret, Recv
- সেকশন: Yarn Return to Store List, Yarn Return to Store, Returned Yarn
- বাটন/অ্যাকশন: Yarn Return to Store, -- Select Type --, Add Line, Cancel, -- Group --, Select…, Clear, Save return challan

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Search Yarn Return to Store List | search |  |  |
| Existing return challan | select |  |  |
| Challan Dt* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Against Requisition | select |  |  |
| Return By* | text | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Issued, Accepted, Rejected, Cancelled |
| Remarks | textarea |  |  |
| Issue Line | select |  |  |
| Yarn | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Ret | number |  |  |
| Recv | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A return challan with no lines sends nothing back.

#### Yarn Dyeing Programme Register

`/m/knitting/yd-program-list`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Program # | search |  | Search Program # |
| Buyer A/C | select |  |  |
| Work Style/Job# | select |  |  |
| Party | select |  |  |
| Status | select |  | All, Running, Completed, Closed, Cancelled |
| Prg Date from | date |  |  |
| Prg Date to | date |  |  |
| Search Yarn-dyed programmes, with what has gone out greige, what has come back d | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Y/D Program #, Type, Rev#, Buyer A/C, Order (Style), Supplier Name, Issue, Delivery, Balance, Prg Date, ACTION
- বাটন/অ্যাকশন: Y/D Program Revisions, Clear, Add New, Export, Y/D Program #, Type, Rev#, Buyer A/C, Order (Style), Supplier Name, Issue, Delivery, Balance, Prg Date

**ট্যাব: Y/D Program Revisions**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing programme | select |  |  |
| Y/D Program #* | select | ✔ |  |
| Supplier Name | text |  |  |
| Buyer A/C | text |  |  |
| Order (Style) | text |  |  |
| Issue (Kg) | text |  |  |
| Delivery (Kg) | text |  |  |
| Rev# | number |  |  |
| Revised On | date |  |  |
| Reason | text |  |  |
- সেকশন: Y/D Program Revisions, Revision History
- বাটন/অ্যাকশন: Y/D Program Revisions, Add Revision, Cancel, Back To List, Clear, Save programme

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| Y/D Program #* | select | ✔ |  |
| Supplier Name | text |  |  |
| Buyer A/C | text |  |  |
| Order (Style) | text |  |  |
| Issue (Kg) | text |  |  |
| Delivery (Kg) | text |  |  |
| Rev# | number |  |  |
| Revised On | date |  |  |
| Reason | text |  |  |
- খালি Save-এ মেসেজ: Y/D Program # is required.

#### Sub-Contract Programme Receipt

`/m/knitting/receive-sc-program-from-party`

**ট্যাব: Knitting Sub-contract Program**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing programme | select |  |  |
| Program# | text |  | Issued on save |
| Auto? | checkbox |  |  |
| Prog Date* | date | ✔ |  |
| Yarn Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order Ref# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Party Ref# | text |  |  |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Color (as written) | text |  |  |
| F.Gsm | number |  |  |
| Qty | number |  |  |
| Finish Dia | number |  |  |
| Dia Unit | select |  | Select…, Inch, CM |
| Open / Tube | select |  | Select…, Open, Tube |
| YD Type | select |  | Select…, Solid, Auto Stripe, Feeder Stripe, Engineered Stripe, Melange |
| Feeder | number |  |  |
| Mc Dia | select |  | 26", 30", 32", 34", 36", 38" |
| Gauge | select |  | 14GG, 18GG, 20GG, 24GG, 28GG, 32GG |
| Rate Type | select |  | Select…, Tk/Kg, Tk/Lb, Tk/Dzn, Fixed |
| Prod. Rate | number |  |  |
| Start Date | date |  |  |
| End Date | date |  |  |
| Fab. Ord. SL | number |  |  |
| Remarks | text |  |  |
| Yarn | select |  |  |
| Fab.Part | text |  |  |
| Brand | select |  |  |
| Lot# | text |  |  |
| S.Len | number |  |  |
| Ela.Pct | number |  |  |
| Ratio.Qty | number |  |  |
| Rqd.Qty | number |  |  |
- সেকশন: Knitting Sub-contract Program, Key Information, Color Wise Fabric Detail, Yarn Against Each Fabric
- বাটন/অ্যাকশন: Sub-contract Program List 0, Knitting Sub-contract Program, -- Select --, Add To Grid, Cancel, --Select--, Select…, Add Yarn, -- Group --, Back To List, Clear, Save programme

**ডায়ালগ: Add To Grid**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| Program# | text |  |  |
| Auto? | checkbox |  |  |
| Prog Date* | date | ✔ |  |
| Yarn Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order Ref# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Party Ref# | text |  |  |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Color (as written) | text |  |  |
| F.Gsm | number |  |  |
| Qty | number |  |  |
| Finish Dia | number |  |  |
| Dia Unit | select |  | Select…, Inch, CM |
| Open / Tube | select |  | Select…, Open, Tube |
| YD Type | select |  | Select…, Solid, Auto Stripe, Feeder Stripe, Engineered Stripe, Melange |
| Feeder | number |  |  |
| Mc Dia | select |  | 26", 30", 32", 34", 36", 38" |
| Gauge | select |  | 14GG, 18GG, 20GG, 24GG, 28GG, 32GG |
| Rate Type | select |  | Select…, Tk/Kg, Tk/Lb, Tk/Dzn, Fixed |
| Prod. Rate | number |  |  |
| Start Date | date |  |  |
| End Date | date |  |  |
| Fab. Ord. SL | number |  |  |
| Remarks | text |  |  |
| Yarn | select |  |  |
| Fab.Part | text |  |  |
| Brand | select |  |  |
| Lot# | text |  |  |
| S.Len | number |  |  |
| Ela.Pct | number |  |  |
| Ratio.Qty | number |  |  |
| Rqd.Qty | number |  |  |
- খালি Save-এ মেসেজ: A programme with no fabric on it asks this mill to knit nothing.

**ডায়ালগ: Add Yarn**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing programme | select |  |  |
| Program# | text |  |  |
| Auto? | checkbox |  |  |
| Prog Date* | date | ✔ |  |
| Yarn Store* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order Ref# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Party Ref# | text |  |  |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Color (as written) | text |  |  |
| F.Gsm | number |  |  |
| Qty | number |  |  |
| Finish Dia | number |  |  |
| Dia Unit | select |  | Select…, Inch, CM |
| Open / Tube | select |  | Select…, Open, Tube |
| YD Type | select |  | Select…, Solid, Auto Stripe, Feeder Stripe, Engineered Stripe, Melange |
| Feeder | number |  |  |
| Mc Dia | select |  | 26", 30", 32", 34", 36", 38" |
| Gauge | select |  | 14GG, 18GG, 20GG, 24GG, 28GG, 32GG |
| Rate Type | select |  | Select…, Tk/Kg, Tk/Lb, Tk/Dzn, Fixed |
| Prod. Rate | number |  |  |
| Start Date | date |  |  |
| End Date | date |  |  |
| Fab. Ord. SL | number |  |  |
| Remarks | text |  |  |
| Yarn | select |  |  |
| Fab.Part | text |  |  |
| Brand | select |  |  |
| Lot# | text |  |  |
| S.Len | number |  |  |
| Ela.Pct | number |  |  |
| Ratio.Qty | number |  |  |
| Rqd.Qty | number |  |  |
- খালি Save-এ মেসেজ: A programme with no fabric on it asks this mill to knit nothing.

#### Daily Knit Fabric Output

`/m/knitting/daily-fabric-production`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From | date |  |  |
| To | date |  |  |
| Buyer | select |  |  |
| Job/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
- সেকশন: Daily Production List
- বাটন/অ্যাকশন: -- Select --, -- Color --, Search, Reset

#### Solid Sample Knit Plan

`/m/knitting/knit-plan-for-sample-solid`

**ট্যাব: Sample Yarn Requisition Ref# — Solid**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing knit plan | select |  |  |
| Requisition Ref# | text |  | Issued on save |
| Date* | date | ✔ |  |
| Buyer | select |  |  |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Style/Order (as written) | text |  |  |
| Fab. Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Colour (as written) | text |  |  |
| Yarn Details | text |  |  |
| Registered Yarn | select |  |  |
| Brand | select |  |  |
| Qty (Kg) | number |  |  |
| GSM | number |  |  |
| Fin. Dia | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Remarks | text |  |  |
- সেকশন: Sample Yarn Requisition Ref# — Solid, Knit Plan Info, Knit Plan List
- বাটন/অ্যাকশন: Sample Yarn Requisition List 0, Sample Yarn Requisition Ref# — Solid, -- Select --, New Order/Kp, Cancel, -- Style --, -- Colour --, -- Group --, Select…, Back To List, Clear, Save knit plan

**ডায়ালগ: New Order/Kp**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing knit plan | select |  |  |
| Requisition Ref# | text |  |  |
| Date* | date | ✔ |  |
| Buyer | select |  |  |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Style/Order (as written) | text |  |  |
| Fab. Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Colour (as written) | text |  |  |
| Yarn Details | text |  |  |
| Registered Yarn | select |  |  |
| Brand | select |  |  |
| Qty (Kg) | number |  |  |
| GSM | number |  |  |
| Fin. Dia | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A knit plan with no lines on it asks the store for nothing.

#### Yarn-Dyed Sample Knit Plan

`/m/knitting/knit-plan-for-sample-yd`

**ট্যাব: Sample Yarn Requisition Ref# — Y/D**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing knit plan | select |  |  |
| Requisition Ref# | text |  | Issued on save |
| Date* | date | ✔ |  |
| Buyer | select |  |  |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Style/Order (as written) | text |  |  |
| Fab. Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Colour (as written) | text |  |  |
| Yarn Details | text |  |  |
| Registered Yarn | select |  |  |
| Brand | select |  |  |
| Qty (Kg) | number |  |  |
| GSM | number |  |  |
| Fin. Dia | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Remarks | text |  |  |
- সেকশন: Sample Yarn Requisition Ref# — Y/D, Knit Plan Info, Knit Plan List
- বাটন/অ্যাকশন: Sample Yarn Requisition List 0, Sample Yarn Requisition Ref# — Y/D, -- Select --, New Order/Kp, Cancel, -- Style --, -- Colour --, -- Group --, Select…, Back To List, Clear, Save knit plan

**ডায়ালগ: New Order/Kp**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing knit plan | select |  |  |
| Requisition Ref# | text |  |  |
| Date* | date | ✔ |  |
| Buyer | select |  |  |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Style/Order (as written) | text |  |  |
| Fab. Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Colour (as written) | text |  |  |
| Yarn Details | text |  |  |
| Registered Yarn | select |  |  |
| Brand | select |  |  |
| Qty (Kg) | number |  |  |
| GSM | number |  |  |
| Fin. Dia | text |  |  |
| M/C Dia | text |  |  |
| Gauge | text |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A knit plan with no lines on it asks the store for nothing.

#### Grey Roll Quality Inspection

`/m/knitting/grey-fab-roll-inspection`

**ট্যাব: Roll Inspection**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| M/C | select |  |  |
| Search Roll List | search |  | Roll, card, buyer, colour… |
| Existing inspection sheet | select |  |  |
| Roll No* | select | ✔ |  |
| Wt | text |  |  |
| Job Card# | text |  |  |
| Operator | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Order | text |  |  |
| Fab Type | text |  |  |
| Color | text |  |  |
| Finish Dia | text |  |  |
| M/C | text |  |  |
| Dia x Gauge | text |  |  |
| F-GSM | text |  |  |
| Yarn Details | text |  |  |
| Inspection Date* | date | ✔ |  |
| Shift | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Deduct Weight | number |  |  |
| Grade for this roll is | select |  | A, B, C, D |
| Total Point for this roll is | text |  |  |
| Grade Point for this roll is | text |  |  |
| Comments | textarea |  |  |
| Count | number |  |  |
| Remarks | text |  |  |
- গ্রিড কলাম: Job Card# / Roll #, Buyer, Color, Shift, Wt
- সেকশন: Roll List, Roll Inspection, Roll, Roll Details, Grading, Defects Found
- বাটন/অ্যাকশন: Inspection List 0, Roll Inspection, -- Select --, Add Defect, Cancel, -- Select Defect --, Back To List, Clear, Save inspection sheet

**ডায়ালগ: Add Defect**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| M/C | select |  |  |
| Search Roll List | search |  |  |
| Existing inspection sheet | select |  |  |
| Roll No* | select | ✔ |  |
| Wt | text |  |  |
| Job Card# | text |  |  |
| Operator | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| Order | text |  |  |
| Fab Type | text |  |  |
| Color | text |  |  |
| Finish Dia | text |  |  |
| M/C | text |  |  |
| Dia x Gauge | text |  |  |
| F-GSM | text |  |  |
| Yarn Details | text |  |  |
| Inspection Date* | date | ✔ |  |
| Shift | select |  | 1 Shift (06 am) (SP001DS045) · 06:00–14:00, Car Allow 09:00 -18:00 (SP001GS005) · 08:00–17:00, Executive 08:00 AM-5:30 P |
| Deduct Weight | number |  |  |
| Grade for this roll is | select |  | A, B, C, D |
| Total Point for this roll is | text |  |  |
| Grade Point for this roll is | text |  |  |
| Comments | textarea |  |  |
| Count | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: Roll No is required.

#### Supplier Yarn Replacement Indent

`/m/knitting/requisition-for-yarn-replace-by-supplier`

**ট্যাব: Requisition for Yarn Replace by Supplier List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  | All |
| Return Type | select |  | All |
| Status | select |  | All, Draft, Submitted, Issued, Cancelled |
| Requisition Date from | date |  |  |
| Requisition Date to | date |  |  |
| Search Replacement claims raised on spinners — the lot, why it is going back, an | search |  | Search 0 records… |
| Filter by Requisition No | search |  |  |
| Filter by Requisition Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store Name | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Return Type | search |  |  |
| Filter by L/C | search |  |  |
| Filter by Lot | search |  |  |
| Filter by Lines | search |  |  |
| Filter by Qty (Kg) | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Requisition Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Lines, Qty (Kg), ACTION
- বাটন/অ্যাকশন: Requisition for Yarn Replace by Supplier, Clear, Add New, Export, Requisition No, Requisition Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Lines, Qty (Kg)

**ট্যাব: Requisition for Yarn Replace by Supplier**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Supplier | select |  | All |
| Return Type | select |  | All |
| Status | select |  | All, Draft, Submitted, Issued, Cancelled |
| Requisition Date from | date |  |  |
| Requisition Date to | date |  |  |
| Search Replacement claims raised on spinners — the lot, why it is going back, an | search |  | Search 0 records… |
| Filter by Requisition No | search |  |  |
| Filter by Requisition Date | search |  |  |
| Filter by Company | search |  |  |
| Filter by Store Name | search |  |  |
| Filter by Supplier | search |  |  |
| Filter by Return Type | search |  |  |
| Filter by L/C | search |  |  |
| Filter by Lot | search |  |  |
| Filter by Lines | search |  |  |
| Filter by Qty (Kg) | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requisition No, Requisition Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Lines, Qty (Kg), ACTION
- বাটন/অ্যাকশন: Requisition for Yarn Replace by Supplier, Clear, Add New, Export, Requisition No, Requisition Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Lines, Qty (Kg)

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing requisition | select |  |  |
| Requisition No | text |  |  |
| Requisition Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store Name* | select | ✔ | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Return Type* | select | ✔ | Select…, CLAIM BY YARN TEST, CLAIM BY PRODUCTION, SHORT SUPPLY, WRONG SUPPLY, DAMAGED IN TRANSIT |
| L/C | text |  |  |
| Lot | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| Lot # | text |  |  |
| Qty | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A claim with no yarn on it asks the spinner to replace nothing.

#### Production Test Clearance

`/m/knitting/test-for-production`

**ট্যাব: Yarn Test**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | Waiting for Issue, Issued, Tested, Cancelled |
| Search Requisition List | search |  | Test order, yarn, requester… |
| Existing test | select |  |  |
| Req. No* | select | ✔ |  |
| Yarn Description | text |  |  |
| Test Date* | date | ✔ |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| L/C # | text |  |  |
| Lot # | text |  |  |
| Color | text |  |  |
| Result | select |  | Select…, Pending, Passed, Failed, Conditional |
| Note | text |  |  |
- গ্রিড কলাম: Test Order No, Order Date, Req. By, Yarn Description, Lots
- সেকশন: Requisition List, Yarn Test, Search Data, Saving Data, Details Data
- বাটন/অ্যাকশন: Yarn Test List 0, Yarn Test, -- Select --, Add Lot, Cancel, -- Group --, -- Supplier --, Back To List, Clear, Save test

**ডায়ালগ: Add Lot**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Status | select |  | Waiting for Issue, Issued, Tested, Cancelled |
| Search Requisition List | search |  |  |
| Existing test | select |  |  |
| Req. No* | select | ✔ |  |
| Yarn Description | text |  |  |
| Test Date* | date | ✔ |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| L/C # | text |  |  |
| Lot # | text |  |  |
| Color | text |  |  |
| Result | select |  | Select…, Pending, Passed, Failed, Conditional |
| Note | text |  |  |
- খালি Save-এ মেসেজ: A test with no lots on it files no answer about any yarn.

#### Approval Without Testing

`/m/knitting/approval-without-test`

**ট্যাব: Yarn Approval Without Test**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Test Date | date |  |  |
| Yarn Brand | select |  |  |
| Lot # | text |  | -All- |
- সেকশন: Yarn Approval Without Test
- বাটন/অ্যাকশন: Approved Without Test 0, Yarn Approval Without Test, -- Select --, --Count--, Search, Save

#### Direct Knit Fabric Booking

`/m/knitting/direct-fabric-booking`

**ট্যাব: Direct Sample Fabric Booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing booking | select |  |  |
| Booking No | text |  | Issued on save |
| Buyer A/C* | select | ✔ |  |
| Style/Ord#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Prog Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| GSM | number |  |  |
| Finish Dia | number |  |  |
| Dia Unit | select |  | Select…, Inch, CM |
| Open / Tube | select |  | Select…, Open, Tube |
| Qty | number |  |  |
| Required On | date |  |  |
| Remarks | text |  |  |
- সেকশন: Direct Sample Fabric Booking, Key Information, Fabric Booked
- বাটন/অ্যাকশন: Direct Booking List 0, Direct Sample Fabric Booking, -- Select Type --, -- Select --, Add Fabric, Cancel, -- Color --, Select…, Back To List, Clear, Save booking

**ডায়ালগ: Add Fabric**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing booking | select |  |  |
| Booking No | text |  |  |
| Buyer A/C* | select | ✔ |  |
| Style/Ord#* | select | ✔ | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Prog Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Remarks | textarea |  |  |
| Fabric Type | select |  | S/J — Single Jersey, L S/J — Lycra Single Jersey, PIQ — Pique / Lacoste, 1x1 RIB — 1x1 Rib, 2x2 RIB — 2x2 Rib, INTLK — I |
| Fabric | text |  |  |
| Color (as written) | text |  |  |
| GSM | number |  |  |
| Finish Dia | number |  |  |
| Dia Unit | select |  | Select…, Inch, CM |
| Open / Tube | select |  | Select…, Open, Tube |
| Qty | number |  |  |
| Required On | date |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A booking with no fabric on it books nothing.

#### Grey Fabric Racking Board

`/m/knitting/grey-fabric-racking-board`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Spin Type | select |  | -All-, Combed, Carded, Open End, Ring Spun, Vortex, Semi Combed |
| Cotton Type | select |  | -All-, Conventional, Organic, Bci, Recycled, Supima, Egyptian |
| Brand | select |  | -All- |
| Lot | text |  | -All- |
| Buyer | select |  |  |
| Job#/W.Style | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
- সেকশন: Yarn Search Parameter
- বাটন/অ্যাকশন: -All-, Search, Reset

#### Knit Plan & Yarn Test Indent

`/m/knitting/knit-plan-requisition-for-yarn-test`

**ট্যাব: Knit Plan & Requisition for Yarn Test List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Waiting for Issue, Issued, Tested, Cancelled |
| Req. By | select |  | All |
| Attn. To | select |  | All |
| Order Date from | date |  |  |
| Order Date to | date |  |  |
| Search Test orders the lab has raised — what yarn is to be tested, who asked for | search |  | Search 0 records… |
| Filter by Test Order No | search |  |  |
| Filter by Order Date | search |  |  |
| Filter by Req. By | search |  |  |
| Filter by Attn. To | search |  |  |
| Filter by Yarn Description | search |  |  |
| Filter by Knit Card # | search |  |  |
| Filter by Lots | search |  |  |
| Filter by Results | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Test Order No, Order Date, Req. By, Attn. To, Yarn Description, Knit Card #, Lots, Results, ACTION
- বাটন/অ্যাকশন: Knit Plan & Requisition for Yarn Test, Clear, Add New, Export, Test Order No, Order Date, Req. By, Attn. To, Yarn Description, Knit Card #, Lots, Results

**ট্যাব: Knit Plan & Requisition for Yarn Test**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Waiting for Issue, Issued, Tested, Cancelled |
| Req. By | select |  | All |
| Attn. To | select |  | All |
| Order Date from | date |  |  |
| Order Date to | date |  |  |
| Search Test orders the lab has raised — what yarn is to be tested, who asked for | search |  | Search 0 records… |
| Filter by Test Order No | search |  |  |
| Filter by Order Date | search |  |  |
| Filter by Req. By | search |  |  |
| Filter by Attn. To | search |  |  |
| Filter by Yarn Description | search |  |  |
| Filter by Knit Card # | search |  |  |
| Filter by Lots | search |  |  |
| Filter by Results | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Test Order No, Order Date, Req. By, Attn. To, Yarn Description, Knit Card #, Lots, Results, ACTION
- বাটন/অ্যাকশন: Knit Plan & Requisition for Yarn Test, Clear, Add New, Export, Test Order No, Order Date, Req. By, Attn. To, Yarn Description, Knit Card #, Lots, Results

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing test order | select |  |  |
| Test Order No | text |  |  |
| Order Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Yarn Description* | text | ✔ |  |
| Knit Card # | text |  |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| L/C # | text |  |  |
| Lot # | text |  |  |
| Qty (Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A test order with no lots on it sends the lab nothing to look at.

#### Yarn Test Result Register

`/m/knitting/yarn-test-register`

**ট্যাব: Knit Yarn Test Register 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Brand | select |  |  |
| Lot # | text |  |  |
| Status | select |  | Pending, Passed, Failed, Conditional |
| From | date |  |  |
| To | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Knit Yarn Test Register 0, Yarn Test, -- Select Yarn Count --, Search, Add New, Reset

**ট্যাব: Yarn Test**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Brand | select |  |  |
| Lot # | text |  |  |
| Status | select |  | Pending, Passed, Failed, Conditional |
| From | date |  |  |
| To | date |  |  |
- সেকশন: Searching Data
- বাটন/অ্যাকশন: Knit Yarn Test Register 0, Yarn Test, -- Select Yarn Count --, Search, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Status | select |  | Waiting for Issue, Issued, Tested, Cancelled |
| Search Requisition List | search |  |  |
| Existing test | select |  |  |
| Req. No* | select | ✔ |  |
| Yarn Description | text |  |  |
| Test Date* | date | ✔ |  |
| Remarks | textarea |  |  |
| Yarn Item | select |  |  |
| Brand | select |  |  |
| L/C # | text |  |  |
| Lot # | text |  |  |
| Color | text |  |  |
| Result | select |  | Select…, Pending, Passed, Failed, Conditional |
| Note | text |  |  |
- খালি Save-এ মেসেজ: A test with no lots on it files no answer about any yarn.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/Knitting`, নতুন টেবিল `knit_` নামে।
- **অর্ডার আর বায়ার:** Merchandise-এর `merch_orders` (order_type, ship_date) আর `buyers`। অর্ডারের লেবেল Brickland-এর মতো: "770MR · LPP-2026-000060"।
- **Cat:** Identity-র lookup `FABRIC_BOOKING_CATEGORY` (FP01–FP12, Brickland-এর বুকিং ক্যাটাগরি)।
- **Master:** fabric_types, colours, floors, `shifts` (15/01-এর capacity plan যে shift ব্যবহার করে), `hr_employees` (অপারেটর আর QC), `mp_suppliers` (S/C ফ্যাক্টরি)।
- **15/01 থেকে নেওয়া:**
  - `knit_machines`: মেশিনের নম্বর, ডায়া, গেজ, RPM।
  - `knit_capacity_plans`: Daily Output-এর Target = capacity_per_hour × plan_hours।
- **নম্বর:** `YarnProcurement::nextNumber()` দিয়ে মাস ধরে।
- **S/C:** পার্টি `mp_suppliers` থেকে, Color Group `YarnProcurement::COLOURS` (২১টা), UOM `UomCodes` (master), Mc Dia আর Gauge 15/01-এর `knit_ref_entries` (DIA, GG)। Receipt-এর সুতা বাছা হয় 15/03-এর `YarnStore::lots()` থেকে, বাছা Yarn Store-এ যা আছে।
- **Yarn test:** Req. Type 15/03-এর `YarnStore::REQ_TYPES` (Brickland-এর একই ২২টা, শুরুতে Store Requisition for Yarn Test); Req. By, Attn. To আর Test By `hr_employees`; Yarn Item আর Brand `YarnStore::lots()` থেকে (স্টোর বাছা থাকলে সেই স্টোরের লট); Approval Without Testing-এর লট `YarnStore::lots(null, true)` — যা স্টোরে আছে।
- **ফ্লোরের সুতার কাগজ:** General Yarn Issue Indent আর Yarn Return to Yarn Store কোনো নতুন টেবিল নয় — 15/03-এর `knit_yarn_issue_reqs` আর `knit_yarn_issue_returns`, একই API (`/knitting/ystore/issue-reqs…`, `/knitting/ystore/issue-returns…`)। Return-এর তালিকা আর ফর্ম 15/03-এর `IssueReturnList` / `IssueReturnEntry`, নিজের route আর ধাপ নিয়ে। Replacement Indent-এর Return Type 15/03-এর `YarnReplacement::TYPES`।
- **Sample আর Direct Booking:** স্টাইল Merchandise-এর `merch_styles` (style_ref — description, বায়ার ধরে), অর্ডার `merch_orders` (style_id সহ); নিবন্ধিত সুতা স্টোরের লট থেকে (আগের মতো lot picker)। Sample plan জমা হলে 15/03-এর `knit_yarn_issue_reqs`-এ রিকুইজিশন ওঠে।
- **Frontend:** `yarn-common.jsx` (PSelect, Field, StatusChip, QtyInput), `Modal`, `ConfirmDialog`, `lib/print-document`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_05_000100_create_knitting_production`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_prod_orders` | po_no (FPO-…), order_kind (BULK / YD / SAMPLE / FLAT_BED_RIB), order_id, buyer_id (অর্ডার থেকে), category_code (FP…), ship_month, fabric_type_id, fabric_desc (Fab.), colour_id, colour_name, finish_dia, gsm, item_image, booking_ref (Bk Rpt), req_qty, adjusted_qty (±), saving_target_qty, yarn_stock_qty, status (RUNNING / COMPLETED / HOLD / CLOSED / CANCELLED), remarks |
| `knit_programs` | kp_no (KP-…), prod_order_id, program_date, qty, floor_id, remarks, status (OPEN / RUNNING / COMPLETED / CLOSED / CANCELLED) |
| `knit_cards` | kc_no (KC-…), program_id, prod_order_id, card_date, assigned_qty, colour_name, machine_dia, finish_dia, gauge, floor_id, yarn_details, machine_id (শেষ রোলের মেশিন), status |
| `knit_roll_sheets` | sheet_no (RS-…), card_id |
| `knit_rolls` | sheet_id, card_id, roll_no (কার্ড ধরে ১, ২, ৩), roll_code ("KC-2026100001/1"), roll_at, shift_id, operator_id, sc_party_id, machine_id, weight, deducted, movement (IN_KNITTING / GREY_STORE / BATCH_STORE / LEFT_OVER / DELIVERED), quality (PENDING / PASSED / HOLD / REJECTED), label_info, comments |
| `knit_roll_inspections` + lines | qci_no (QCI-…), roll_id (প্রতি রোলে একটা শিট), inspected_on, shift_id, qc_by, deduct_weight, grade_override, total_points, grade_point, grade, comments, status (DRAFT / PASSED / HOLD / REJECTED); লাইনে defect, points_each (কপি করা), count, remarks |

মাইগ্রেশন `2027_01_06_000100_create_knitting_subcontract`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_sc_programs` + lines | program_no (SCP-…), kind (BULK_SOLID / SAMPLE_SOLID / PARTY_YD), program_type (Bulk / Short-Bulk; Sample-এ Develop / Sample), po_date, target_date, party_id, category_code, buyer_id, style_ref, order_id, work_style_no, remarks, status (DRAFT / SUBMITTED / APPROVED / REJECTED / CANCELLED), decision_note, decided_at, decided_by; লাইনে fabric, colour_id, colour_group, machine_dia, gauge, finish_dia, gsm, qty, rate, uom, received_qty (15/05-এর রিসিভ ডেস্ক লিখবে), remarks |
| `knit_sc_receipts` | program_no (SCR-… বা পার্টির নিজের নম্বর), auto_number, prog_date, party_id, yarn_store_id, remarks, status (DRAFT / CONFIRMED / IN_PRODUCTION / CLOSED / CANCELLED) |
| `knit_sc_receipt_fabrics` | sl (Fab. Ord. SL, প্রোগ্রামে অনন্য), order_id, party_ref, fabric_type_id, colour_id, colour_name, composition_id, gsm, qty, uom, finish_dia, dia_unit (INCH / CM), width_type (OPEN / TUBE), yd_type (Solid, Auto/Feeder/Engineered Stripe, Melange), feeder, mc_dia_id, gauge_id, rate_type (Tk/Kg, Tk/Lb, Tk/Dzn, Fixed), rate, start_date, end_date, remarks |
| `knit_sc_receipt_yarns` | sl (কোন ফেব্রিক সিরিয়ালের নিচে), composition_id + yarn_count_id (Yarn), fab_part, colour_group, brand, lot_no, stitch_length, elastane_pct, ratio_qty, required_qty, remarks |

মাইগ্রেশন `2027_01_07_000100_create_knitting_yarn_test`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_test_orders` + lines | order_no (YTO-…), order_date, req_type, store_id, requested_by, attn_to, yarn_description, knit_card_no, remarks, status (WAITING_ISSUE / ISSUED / TESTED / CANCELLED), issued_at; লাইনে composition_id + yarn_count_id (Yarn Item), colour_group, brand, supplier_id, lc_no, lot_no, qty, uom, remarks |
| `knit_yarn_tests` | test_no (YTS-…), order_id (অর্ডারে একটাই), test_date, test_by, remarks |
| `knit_yarn_test_results` | রেজিস্টার: basis (LAB / WAIVED), test_id (LAB-এ), test_date, test_by, composition_id, yarn_count_id, colour_group, brand, supplier_id, lc_no, lot_no, colour, result (PENDING / PASSED / FAILED / CONDITIONAL), note, yarn_lot_id (WAIVED-এ স্টোরের লট) |

মাইগ্রেশন `2027_01_08_000100_create_knitting_yarn_replace_reqs`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_yarn_replace_reqs` + lines | req_no (YRR-…), req_date, store_id, supplier_id, return_type (CLAIM BY YARN TEST, CLAIM BY PRODUCTION, SHORT SUPPLY, WRONG SUPPLY, DAMAGED IN TRANSIT), lc_no, lot_ref (বড় হাতের), remarks, status (DRAFT / SUBMITTED / ISSUED / CANCELLED); লাইনে composition_id + yarn_count_id, colour_group, brand, lot_no (বড় হাতের), qty, uom, remarks |

মাইগ্রেশন `2027_01_09_000100_create_knitting_sample_plans_and_direct_bookings`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_sample_plans` + lines | plan_no (SYR-S-… / SYR-Y-…), kind (SOLID / YD), plan_date, buyer_id, store_id, req_type, remarks, status (DRAFT / REQ_SUBMITTED / CANCELLED; ISSUED আর CLOSED রিকুইজিশন থেকে পড়া), issue_req_id; লাইনে buyer_id, style_id, order_id, style_text, fabric_type_id, colour_id, colour_name, yarn_details, composition_id + yarn_count_id (নিবন্ধিত সুতা), colour_group, brand, qty, uom, gsm, finish_dia, machine_dia, gauge, remarks |
| `knit_direct_bookings` + lines | booking_no (DFB-…), category_code (FP…), buyer_id, style_id, order_id, prog_date, remarks, status (DRAFT / SUBMITTED / APPROVED / CANCELLED), decided_at, decided_by; লাইনে fabric_type_id, fabric, colour_id, colour_name, gsm, finish_dia, dia_unit, width_type, qty, uom, required_on, remarks |

মাইগ্রেশন `2027_01_10_000100_create_knitting_yarn_twist`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_twist_programs` + lines | program_no (TWP-…), program_date, target_date, supplier_id, store_id, status (DRAFT / SUBMITTED / APPROVED / RUNNING / CLOSED / CANCELLED — ফর্মে দেওয়া), remarks; লাইনে composition_id + yarn_count_id, colour_group, brand, lot_no, grey_qty, tpi, ply, rate, uom, received_qty (গ্রহণ চালান থেকে), remarks |
| `knit_twist_receives` + lines | challan_no (TWR-…), receive_date, program_id, gate_pass_no, supplier_id (প্রোগ্রাম থেকে), store_id, finalized, transferred, remarks; লাইনে program_line_id, colour_group, brand, lot_no, grey_qty, receive_qty, cones, uom, remarks |
| `knit_twist_bills` + lines | bill_no (TWB-…), bill_date, supplier_id, program_id, party_bill_no, status (DRAFT / SUBMITTED / APPROVED / PAID / CANCELLED), remarks; লাইনে receive_id, particulars, composition_id + yarn_count_id, qty, rate, amount (qty × rate, সার্ভারে), uom, remarks |

মাইগ্রেশন `2027_01_11_000100_create_knitting_machine_loads_and_stations`:

| টেবিল | ফিল্ড |
|---|---|
| `knit_machine_loads` | plan_date, shift_id, machine_id, card_id, plan_qty — (দিন, শিফট, মেশিন) প্রতি একটাই সারি |
| `knit_stations` + machines | code (বড় হাতের, অনন্য), name, operator_id, active; মেশিনের তালিকা sort_order সহ (স্টেশন প্রথমটায় খোলে) |

**Defect:** Brickland-এর ২৫টা ত্রুটি আর তাদের পয়েন্ট (Hole/Crack ৪, Slub ১ …)। এগুলো `KnitProduction::DEFECTS`-এ কোডে রাখা, গ্রুপসহ (Structural, Yarn, Machine, Stain)।

মাইগ্রেশন `2027_01_12_000100_create_knitting_yarn_dyeing` (Register আর 15/03-এর Y/D স্ক্রিনগুলো একসাথে; 15/03-এর টেবিল [03_Yarn_Store](03_Yarn_Store.md)-এ):

| টেবিল | ফিল্ড |
|---|---|
| `knit_yd_programs` + lines | program_no (YDP-…), program_date, yd_type (IN_HOUSE / SUB_CONTRACT — Brickland-এর Type), supplier_id (ডাই হাউস), buyer_id, order_id, delivery_date, rev_no, status (RUNNING / COMPLETED / CLOSED / CANCELLED), remarks; লাইনে (শেড) colour_id + colour_name (রঙের master থেকে), colour_group, composition_id + yarn_count_id (গ্রে সুতা), lot_no (গ্রে লট), qty, uom, rate (ডাইং রেট), remarks |
| `knit_yd_revisions` | program_id, rev_no (প্রোগ্রামে অনন্য, ক্রমানুসারে), revised_on, reason, created_by |

মাইগ্রেশন `2027_01_14_000100_extend_knitting_grey_transfers` (15/05-এর grey খাতার ওপর — [05_Fabric_Store](05_Fabric_Store.md)):

| টেবিল | কী যোগ হলো |
|---|---|
| `knit_grey_lots` | batch_no — ডাই ব্যাচে রাখা লাইন নিজের (key-তে `|B:…`; পুরনো key বদলায় না) |
| `knit_grey_transfers` | kind (STORE / COLLAR_CUFF / TO_BATCH / ORDER / REUSE / TO_LEFTOVER / BATCH_TO_LEFTOVER), cc_type (COLLAR / CUFF / COLLAR_CUFF), to_order_id, batch_no; from_store_id আর to_store_id এখন ঐচ্ছিক |
| `knit_grey_transfer_lines` | roll_id (collar / cuff — ফ্লোরের রোল, তখনো খাতায় নেই), fabric, colour_id, colour_name; lot_id এখন ঐচ্ছিক |

## ৫. Workflow / Status ও Business Rules

- **Fabric Production Order (Bulk, Yarn-Dyed, Sample, Flat Bed Rib):** চার স্ক্রিনের UI একই, তফাত শুধু ধরনে।
  - **Register:**
    - Filter: Type, Buyer A/C, Ship Month, Job#/W.Style, Order/Style#, Status (শুরুতে Running)।
    - কলাম: Cat, Buyer, Job#/W.Style/Order No, Order Type/Ship, Fab., Color, Bk Rpt, Req Qty, KP Made, KP Blnc, KC Done, KC Blnc, Adjusted, YarnStk, Rolled, Saving Target, Status।
    - হিসাব: Req Qty = চাহিদা ± সমন্বয়, KP Blnc = Req − KP Made, KC Blnc = KP Made − KC Done, Rolled = রোলের net কেজি।
    - বোতাম: Complete, Hold, Resume, Knitcard, Edit, Copy আর CSV Export।
  - **Save Production Order:**
    - তিন ধাপ: Order (Job#/W.Style/Order No*, Style, Buyer, Company*, Cat, Ship Month) → Fabric (Fabric Type, Fab.*, Color, Color (as written), Finish Dia, GSM, Item Image, Bk Rpt) → Quantities (Req Qty*, Adjusted ±, Saving Target, YarnStk, Status*, Remarks)।
    - Continue / Back দিয়ে ধাপ বদলায়; Save, Add New, Back To List।
    - চাহিদা ইতিমধ্যে ছাড়া প্রোগ্রামের নিচে নামানো যায় না।
  - **Knit Programmes (KP):**
    - বাঁয়ে অর্ডারের তালিকা: খোঁজা যায়, প্রতিটায় "Req · KP blnc · KC blnc" দেখায়।
    - ডানে প্রোগ্রামের grid। Date, Qty, Status আর Remarks সেখানেই বদলানো যায়; Save করলে একসাথে সংরক্ষণ, Revert করলে বাতিল।
    - **Release Programme** dialog-এ Date*, Qty*, Floor আর Remarks।
    - অর্ডারের বাকির বেশি ছাড়া যায় না ("A programme cannot release more than the order still owes (430 Kg)")। শুধু Running অর্ডারে ছাড়া যায়।
    - কার্ড আছে এমন প্রোগ্রাম মোছা বা বাতিল করা যায় না, আর কার্ডে যাওয়া পরিমাণের নিচে নামানো যায় না। বাতিল করলে ওজন অর্ডারে ফেরত যায়।
  - **Knitcard:**
    - একই বিন্যাস। **Cut Knit Card** dialog-এ Date*, Programme (KP)*, Assigned Qty*, Color, M/C Dia, Finish Dia, Gauge, Floor, Yarn Details।
    - প্রোগ্রামে যত বাকি তার বেশি কাটা যায় না। প্রথম কার্ড কাটলে প্রোগ্রাম Running হয়।
    - রোল আছে এমন কার্ড বাতিল বা মোছা যায় না।
- **Manual Roll Production Entry:**
  - **Roll List:**
    - Filter: Job Card, M/C, Movement, Quality, From, To।
    - বোতাম: Pass QC, Reject, To Grey Store (একবারই; বাতিল রোল যায় না), Print Label (Grey Fabric Roll Tag)।
  - **Roll Weight Entry:**
    - বাঁয়ে খোলা কার্ডের তালিকা (Job Card #, Buyer, Fab Type, Color, Left Qty), M/C দিয়ে ছাঁকা আর খোঁজা যায়।
    - ডানে কার্ডের বিবরণ: Buyer / Style / Order, Yarn Details, M/C Dia, Finish Dia, Gauge, আর Assigned / Rolled / Left।
    - লাইন প্রতি রোল: Roll No (খালি রাখলে নিজে থেকে বসে), Date, Shift, Operator, S/C Factory, M/C, Roll Weight, Deducted, Movement, Quality, Comments।
    - নিচে Weight (Kg) আর NoOfRoll; Action Status "Weighed at the machine"।
  - **নিয়ম:**
    - রোল নম্বর কার্ড ধরে চলে, আর একই নম্বর আরেকটা শিটে বসানো যায় না।
    - Deducted ওজনের বেশি হতে পারে না।
    - ইন্সপেকশন হওয়া রোল শিট থেকে সরানো যায় না।
    - প্রথম রোলে কার্ড Running হয়; কার্ডের M/C হয় শেষ রোলের মেশিন।
- **Daily Knit Fabric Output:**
  - প্রতিটা সারি = দিন × কার্ড × মেশিন × শিফট।
  - কলাম: Date, Floor, Type, Buyer, Job#/W.Style, Order#, Color, Fabric, Fin DIA, Fin GSM, M/C#, RPM, MC DIA, Shift, All Shift Prod (কার্ডের পুরো দিন), Target, Total Prod (ওই শিফট), Balance (কার্ডের বরাদ্দ − সেদিন পর্যন্ত রোল)।
  - Target আসে মেশিনের capacity plan থেকে; plan না থাকলে "—"।
  - Filter: From, To, Buyer, Job/W.Style, Cat, Color, Floor।
- **Grey Roll Quality Inspection:**
  - **Inspection List:**
    - Filter: From, To, Status, Grade, M/C।
    - কলাম: Inspection#, Job Card#/Roll #, Date, Shift, QC Name, M/C, Roll Wt, Deduct, Net, Defects, Total Point, Grade Point, Grade, Status।
    - বোতাম: Pass, Hold, Reject, Print।
  - **Roll Inspection:**
    - বাঁয়ে রোলের তালিকা (M/C দিয়ে ছাঁকা)। রোল বাছলে বিবরণ ভরে: Wt, Job Card#, Operator, Buyer, Style, Order, Fab Type, Color, Finish Dia, M/C, Dia x Gauge, F-GSM, Yarn Details।
    - ফর্মে Inspection Date*, Shift, QC Name, Deduct Weight, Grade override আর Comments। Defect, Count আর Remarks দিয়ে Add Defect।
  - **হিসাব:**
    - Total Point = Σ (পয়েন্ট × সংখ্যা)।
    - Grade Point = Total Point × ১০০ ÷ (রোলের net − ইন্সপেকশনের কর্তন)।
    - Grade: A ≤ ২০, B ≤ ৪০, C ≤ ৬০, D তার বেশি। হাতে দেওয়া গ্রেড থাকলে সেটাই।
    - ত্রুটিহীন রোল গ্রেড A শিট।
  - **নিয়ম:** প্রতি রোলে একটাই শিট। শুধু Draft সংশোধন হয়। Pass, Hold আর Reject রোলের quality-তেও লেখে। Hold থেকে Pass বা Reject করা যায়।
- **Bulk Solid / Sample Solid / Yarn-Dyed Sub-Contract Knit Programme:** তিন স্ক্রিনের UI একই; Type-এর অপশন আলাদা (Bulk আর Y/D-তে Bulk / Short-Bulk, Sample-এ Develop / Sample)।
  - **Records:**
    - Filter: Prod.Category, Party, Buyer A/C, Job/Work Style, Program (খোঁজা), Status, PO Date from–to। "x of y shown", Clear, Add New।
    - কলাম: Type, Program #, Supplier Name, PO Date, IsTrnsf?, WorkFlow (পরের ধাপ: Submit / Approve), Qty (Kg), Buyer A/C, Job/Work Style, Status।
    - বোতাম: Program আর Challan (প্রিন্ট) সবসময়। Draft-এ Submit আর Delete; Rejected-এ Submit; Submitted-এ Approve আর Reject। প্রতিটা আগে confirm dialog-এ জিজ্ঞেস করে (Brickland-এর লেখা)।
  - **New S/C Knitting Program:**
    - Programme: Existing programme (বাছলে লোড হয়), PO Date*, Target Delivery Date, Type*, Company*।
    - Party: Party*, Prod.Category।
    - Booked Against: Buyer A/C, Style, Order # (Buyer আর Style দিয়ে ছাঁকা), Job/Work Style (অর্ডার বাছলে সেখান থেকে; না থাকলে হাতে)। তারপর Remarks।
    - Programme Fabrics: Fabric, Color, Color Group, M/C Dia, Gauge, Finish Dia, GSM, Qty(Kg), Rate, UOM, Remarks → Add Fabric। grid-এ প্রতিটা ঘর সেখানেই বদলানো যায়, Remove, নিচে Total।
    - বোতাম: Back To List, Clear, Save programme।
  - **নিয়ম:**
    - অন্তত একটা লাইন লাগবে ("A programme with no fabrics on it asks the party for nothing.")। প্রতিটা লাইনে Fabric, Qty > 0 আর UOM।
    - শুধু Draft আর Rejected সংশোধন হয়। Submit করলে লাইন স্থির; Approve করলে পার্টির কাছে ছাড়া। Reject হলে ডেস্কে ফেরত, ঠিক করে আবার Submit।
    - শুধু কখনো জমা না হওয়া Draft মোছা যায়।
    - নম্বর SCP-YYYYMM#### — তিন স্ক্রিন একই ধারায়।
- **Sub-Contract Programme Receipt:** পেজ খোলে ফর্মে (Brickland-এর মতো)।
  - **Sub-contract Program List:**
    - Program Search: Party, Yarn Store, Status, From, To → Search, Add New, Reset।
    - চিপ: Programmes আর Fabric (Kg)। তারপর খোঁজা, Add New, Export, প্রতিটা কলামের নিচে filter।
    - কলাম: Program#, Prog Date, Party, Yarn Store, Company, Fabrics, Qty (Kg), Yarn Rqd (Kg), Remarks, Status।
    - বোতাম: Draft-এ Confirm আর Cancel; Confirmed-এ Start Production, Close, Cancel; In Production-এ Close, Cancel; সবসময় Print।
  - **Knitting Sub-contract Program:**
    - Key Information: Existing programme, Program# + Auto? (টিক তুললে পার্টির নিজের নম্বর লেখা যায়), Prog Date*, Party*, Yarn Store*, Company*, Remarks।
    - Color Wise Fabric Detail: Order Ref#, Party Ref#, Fabric Type, Color, Color (as written), Fib.Comp., F.Gsm, Qty, UOM, Finish Dia, Dia Unit, Open / Tube, YD Type, Feeder, Mc Dia, Gauge, Rate Type, Prod. Rate, Start Date, End Date, Fab. Ord. SL, Remarks → Add To Grid। grid: SL, Order Ref#, Color, Fab.Type, Fib.Composition, Fin.Dia, F.GSM, Qty, Start, End, Remove।
    - Yarn Against Each Fabric: Fab. Ord. SL, Yarn (বাছা স্টোরের লট থেকে), Fab.Part, Col.Grp, Brand (ওই সুতার লটের ব্র্যান্ড), Lot#, S.Len, Ela.Pct, Ratio.Qty, Rqd.Qty, Remarks → Add Yarn। Ratio দিলে Rqd = ফেব্রিকের Qty × Ratio ÷ ১০০ নিজে থেকে বসে।
  - **নিয়ম:**
    - অন্তত একটা ফেব্রিক লাগবে ("A programme with no fabric on it asks this mill to knit nothing.")। প্রতিটা লাইনে Qty > 0 আর UOM।
    - Fab. Ord. SL খালি রাখলে প্রোগ্রামের পরের খালি নম্বর বসে; একই নম্বর দুবার চলে না। সুতার সারি এমন সিরিয়ালেই ঝোলে যা কোনো ফেব্রিক লাইনে আছে; সুতা আছে এমন ফেব্রিক সরানো যায় না।
    - সুতা ঐচ্ছিক।
    - Auto? টিক থাকলে SCR-YYYYMM####; না থাকলে পার্টির নম্বর (অক্ষর, সংখ্যা, স্পেস, ডট, ড্যাশ), কোম্পানিতে একবারই।
    - শুধু Draft সংশোধন হয়। Confirm → Start Production → Close; Closed বা Cancelled হলে আর কিছু নয়।

- **Knit Plan & Yarn Test Indent:**
  - **List:** Filter: Status, Req. By, Attn. To, Order Date from–to; "x of y shown", Clear, Add New। খোঁজা, Export, প্রতিটা কলামের নিচে filter।
    - কলাম: Test Order No, Order Date, Req. By, Attn. To, Yarn Description, Knit Card #, Lots, Results (অবস্থা আর "1 Passed · 1 Conditional")।
    - বোতাম: Knit Card # (প্রিন্ট) সবসময়; Waiting for Issue-এ Issue; Waiting বা Issued-এ Cancel। Issue আর Cancel আগে জিজ্ঞেস করে।
  - **ফর্ম:** Existing test order; Key Information (Test Order No — সেভে, Order Date*, Company*, Req. Type, Store); People (Req. By, Attn. To); Yarn (Yarn Description*, Knit Card #, Remarks)।
    - লট: Yarn Item, Color Group, Brand, Supplier, L/C #, Lot #, Qty (Kg), UOM, Remarks → Add Yarn। grid, Remove, Total।
    - বোতাম: Back To List, Clear, Save test order।
  - **নিয়ম:** অন্তত একটা লট ("A test order with no lots on it sends the lab nothing to look at.")। প্রতিটা লাইনে Lot #। সেভে Waiting for Issue। শুধু Waiting আর Issued সংশোধন হয়; Tested হলে আর বদলায় না, বাতিলও হয় না।
- **Yarn Test (Yarn Test Result Register আর Production Test Clearance-এর ফর্ম, একই):**
  - বাঁয়ে Requisition List: Status, খোঁজা (test order, yarn, requester), টেবিল (Test Order No, Order Date, Req. By, Yarn Description, Lots), পাতা। সারিতে ক্লিক করলে অর্ডারের লট grid-এ আসে, প্রতিটা Pending।
  - ডানে: Existing test; Search Data (Req. Type — Req. No-র তালিকা ছাঁকে, Req. No*, Yarn Description); Saving Data (Test Date*, Test By*); Remarks।
  - Details Data: Yarn Item, Color Group, Brand, Supplier, L/C #, Lot #, Color, Result, Note → Add Lot। grid-এ Color, Result আর Note সেখানেই বদলানো যায়।
  - **নিয়ম:**
    - অর্ডার Issued (বা আগেই Tested) হতে হবে; নইলে ":no is Waiting for Issue — the store has not issued its yarn to the lab."।
    - অন্তত একটা লট ("A test with no lots on it files no answer about any yarn.")। প্রতিটা লাইনে Lot # আর Result।
    - সেভে প্রতি লটে রেজিস্টারে একটা LAB সারি, অর্ডার Tested। একই অর্ডারে আবার জমা দিলে আগের সারিগুলো বদলে যায় (নম্বর একই থাকে), যাতে এক লটের দুটো উত্তর না থাকে। যে অর্ডারের টেস্ট আছে সেটা বাছলে টেস্টটাই লোড হয়।
- **Register তালিকা (তিন স্ক্রিন):** Searching Data: Yarn Count, Brand, Lot #, Status (Pending, Passed, Failed, Conditional), From, To → Search, Add New, Reset। কলাম: Basis (শুধু Register-এ), Test # / Req. No, Test Date, Yarn, Brand, Supplier, L/C #, Lot #, Color, Result, Note, Test By।
  - Yarn Test Result Register: দুই ধরনের সারিই (Lab আর Without Test)।
  - Production Test Clearance: শুধু Lab; পেজ খোলে Yarn Test ফর্মে।
  - Approval Without Testing: শুধু Without Test, Note কলামের নাম "Note / Authority"; পেজ খোলে Yarn Approval Without Test-এ।
- **Yarn Approval Without Test:**
  - Test Date, Test By (কর্তৃত্ব: "The authority the release is made on…"), Yarn Brand, Yarn Count, Lot # → Search।
  - টেবিল: Release (টিক), Yarn, Brand, Lot #, Supplier, Store, Balance (Kg), Already Answered ("Passed · Lab · 31 Oct 2026"), Note। উপরে "n lots"। নিচে Brickland-এর লেখা আর Save।
  - **নিয়ম:** শুধু স্টোরে থাকা লট (balance > ০)। অন্তত একটা টিক ("Tick at least one lot to release.")। Test By আবশ্যক। প্রতিটা টিক দেওয়া লট একটা WAIVED সারি, ফলাফল Passed। একই লট আবার ছাড়লে আগের ছাড় বদলে যায়।
  - **লট চেনা:** Lot # + Yarn (composition আর count)। Lab সারিতে Yarn না থাকলে শুধু Lot # দিয়ে মেলে।

- **General Yarn Issue Indent:** 15/03-এর স্টোর যে রিকুইজিশনের বিপরীতে ইস্যু করে, ফ্লোর সেটাই এখান থেকে তোলে।
  - **Records:** Filter: Requisition Date from–to, Requisition Type, Style No, Order No, Status, Requisition No; "x of y shown", Clear, Add New। খোঁজা, Export।
    - কলাম: Style No, Order No, Requisition No (অবস্থাসহ), Requisition Date, Requisition Type, TTL Req. Qty, TTL Issue Qty, Balance Qty।
    - বোতাম: Close (Open বা Partly Issued), Cancel (Open, কিছু ইস্যু না হলে)। দুটোই আগে জিজ্ঞেস করে।
  - **Yarn Issue Requisition:** Existing requisition; Requisition (Requisition Date*, Requisition Type*, Program No, Company*, Store* — শুরুতে yarn store); From (Party); Booked Against (Buyer A/C, Style No, Order No — বাছলে Buyer আর Style ভরে); Standing (Status — ইস্যু থেকে হিসাব হয়, হাতে নয়; Closed টিক); Remarks।
    - Yarn Required: Yarn (স্টোরের লট থেকে), Color Group, Brand, Lot #, Req Qty, UOM → Add Yarn। grid-এ Issued আর Balance, Total।
  - **নিয়ম:** 15/03-এর নিয়মই (ধরন আর অন্তত একটা লাইন; প্রতি লাইনে সুতা আর Req Qty > ০; কিছু ইস্যু হলে লাইন স্থির)। Closed টিক দিয়ে Save করলে লাইন সেভের পর রিকুইজিশন বন্ধ হয়।
- **Yarn Return to Yarn Store:** 15/03-এর Yarn Issue Return-এর challan-ই। তালিকা আর ফর্ম একই; ফ্লোর Issue (পাঠানো) আর Cancel করে। Accept আর Reject — যাতে সুতা স্টকে ফেরে — শুধু স্টোরের অনুমতিতে দেখায় আর চলে।
- **Supplier Yarn Replacement Indent:**
  - **List:** Filter: Supplier, Return Type, Status, Requisition Date from–to; "x of y shown", Clear, Add New। খোঁজা, Export।
    - কলাম: Requisition No (অবস্থাসহ), Requisition Date, Company, Store Name, Supplier, Return Type, L/C, Lot, Lines, Qty (Kg)।
    - বোতাম: Draft-এ Submit; Submitted-এ Issue; Draft বা Submitted-এ Cancel। প্রতিটা আগে জিজ্ঞেস করে।
  - **ফর্ম:** Existing requisition; Key Information (Requisition No — সেভে, Requisition Date*, Company*, Store Name*); Claim (Supplier*, Return Type*, L/C, Lot); Remarks। Yarn Going Back: Yarn Item, Color Group, Brand, Lot #, Qty, UOM, Remarks → Add Yarn; grid, Total।
  - **নিয়ম:** অন্তত একটা লাইন ("A claim with no yarn on it asks the spinner to replace nothing.")। প্রতি লাইনে Lot #, Qty > ০, UOM। Lot আর Lot # বড় হাতের। শুধু Draft সংশোধন হয়। Issued মানে সুতা বেরিয়ে গেছে — আসল স্টক বের হয় 15/03-এর Issue Yarn Replace by Supplier challan দিয়ে।

- **Solid / Yarn-Dyed Sample Knit Plan:** দুই স্ক্রিনের UI একই; প্রতিটা নিজের ধরনের প্ল্যানই দেখায়। পেজ খোলে ফর্মে।
  - **Sample Yarn Requisition List:** Knit Plan Search (Buyer, Store, Status, From, To → Search, Add New, Reset); চিপ Plans আর Yarn asked (Kg); খোঁজা, Export, প্রতি কলামের নিচে filter।
    - কলাম: Requisition Ref#, Date, Buyer, Store, Plan Lines, Yarn (Kg), Requisition No, Remarks, Status।
    - বোতাম: Draft-এ Submit Requisition; Draft বা Requisition Submitted-এ Cancel; সবসময় Print। দুটোই আগে জিজ্ঞেস করে (Brickland-এর লেখা)।
  - **ফর্ম:** Existing knit plan; Knit Plan Info (Requisition Ref# — সেভে, Date*, Buyer, Store, Requisition Type, Company*), Remarks।
    - Knit Plan List: Buyer, Style, Order (Buyer আর Style দিয়ে ছাঁকা; অর্ডার বাছলে Buyer আর Style ভরে), Style/Order (as written), Fab. Type, Colour, Colour (as written), Yarn Details, Registered Yarn, Col.Grp, Brand, Qty (Kg), UOM, GSM, Fin. Dia, M/C Dia, Gauge, Remarks → New Order/Kp। grid, Total।
  - **নিয়ম:** অন্তত একটা লাইন ("A knit plan with no lines on it asks the store for nothing.")। প্রতি লাইনে Qty > ০ আর UOM। শুধু Draft সংশোধন হয়।
    - **Submit Requisition:** Store, Requisition Type আর প্রতি লাইনের নিবন্ধিত সুতা লাগবে ("Line :n has no registered yarn…")। তখন 15/03-এ YIR-… রিকুইজিশন ওঠে (ধরন, স্টোর, বায়ার, Program No = প্ল্যান নম্বর; লাইনে সুতা, Col.Grp, Brand, UOM, Req Qty), প্ল্যান Requisition Submitted আর স্থির।
    - এরপর অবস্থা রিকুইজিশনের: Partly / Fully Issued → Issued, Closed → Closed। Cancel করলে প্ল্যান তুলে নেওয়া হয়; রিকুইজিশন স্টোরেই থাকে।
  - **Print:** "Sample Yarn Requisition — Solid / Y/D" — Date, Buyer, Store, Requisition Type, Company, Status, Requisition No, Total Yarn; Knit Plan List (Buyer, Style/Order, Fab. Type, Colour, Yarn Details, Registered Yarn, GSM, Fin. Dia, Qty, Total); Sample Room, Store In-charge, Approved By।
- **Direct Knit Fabric Booking:** পেজ খোলে ফর্মে।
  - **Direct Booking List:** Booking Search (Prod. Type, Buyer A/C, Status, From, To → Search, Add New, Reset); চিপ Bookings আর Qty (Kg); খোঁজা, Export, কলাম-filter।
    - কলাম: Booking No, Prog Date, Prod. Type, Buyer A/C, Style, Style/Ord#, Fabrics, Qty (Kg), Remarks, Status।
    - বোতাম: Draft-এ Submit (জিজ্ঞেস করে না, Brickland-এর মতো); Submitted-এ Approve (জিজ্ঞেস করে); Draft বা Submitted-এ Cancel; সবসময় Print।
  - **ফর্ম:** Existing booking; Key Information (Booking No — সেভে, Prod. Type*, Buyer A/C*, Style, Style/Ord#*, Prog Date*, Company*), Remarks। Fabric Booked: Fabric Type, Fabric, Color, Color (as written), GSM, Finish Dia, Dia Unit, Open / Tube, Qty, UOM, Required On, Remarks → Add Fabric; grid, Total।
  - **নিয়ম:** অন্তত একটা লাইন ("A booking with no fabric on it books nothing.")। প্রতি লাইনে Fabric Type অথবা লিখিত Fabric, Qty > ০, UOM। অর্ডার বায়ারেরই হতে হবে। শুধু Draft সংশোধন হয়; Approve করলে ফেব্রিক স্টোরে ছাড়া, লাইন স্থির।
  - **Print:** "Direct Sample Fabric Booking" — Prog Date, Prod. Type, Buyer A/C, Style, Order, Job #, Company, Status, Total Qty; Fabric Booked (Fabric Type, Fabric, Color, GSM, Finish Dia "64.00 Inch (Open)", Qty, Required On, Remarks, Total); Booked By, Fabric Store, Approved By।

- **Yarn Twisting Entry:** চার ট্যাব — Twist Program (তালিকা), Twist Program (ফর্ম), Twist Yarn Receive, Yarn Twist Bill (S/C Out-house)।
  - **তালিকা:** Filter: Date from–to, Supplier, Status, Program No (TWP-…); "x of y shown", Clear, Add New; খোঁজা, Export। কলাম: Program No (অবস্থাসহ), Prg Date, Trgt Delv Date, Supplier, Grey (Kg), Received (Kg), Balance (Kg), Remarks; বোতাম Print।
  - **Twist Program:** Existing twist programme; Prg Date*, Trgt Delv Date, Company*, Supplier*, Store (hint), Status* (ফর্মে বাছা), Remarks। Yarn to Twist: Yarn (স্টোরের লট থেকে), Color Group, Brand, Lot #, Grey(Kg), TPI, Ply, Rate, UOM, Remarks → Add Yarn; grid-এ Received, Total।
    - **নিয়ম:** অন্তত একটা লাইন; প্রতি লাইনে Grey > ০ আর UOM। কোনো গ্রহণ হয়ে গেলে সুতার লাইন আর বদলায় না (মাথার অংশ — অবস্থা, তারিখ, মন্তব্য — বদলায়), সাপ্লায়ারও না।
  - **Twist Yarn Receive:** বাঁয়ে Twist Yarn Receive Challan List (Party, খোঁজা Challan# / Gate Pass# / Program Ref#; Date, Challan#, Gate Pass#, Party, Program Ref#, G.Yarn Qty, Finalized?, Transfer?), সারিতে ক্লিক করলে লোড হয়।
    - ডানে: Existing receive challan; Challan (Date*, Program Ref#*, Gate Pass#); From / To (Party — প্রোগ্রাম থেকে, Company*, Store* — প্রোগ্রামের স্টোর); Standing (Finalized?, Transfer?); Remarks। Challan Detail: Yarn (প্রোগ্রামের লাইন — বাছলে Color Group, Brand, Lot, UOM ভরে), Grey(Kg), Receive(Kg), Cones, UOM, Remarks → Add Line; grid-এ Loss (Grey − Receive), Total।
    - **নিয়ম:** নতুন চালান শুধু Draft / Cancelled / Closed নয় এমন প্রোগ্রামে। কোনো লাইনের মোট Grey প্রোগ্রাম লাইনে পাঠানোর বেশি নয় ("(… Kg)" বাকি দেখায়); Receive Grey-এর বেশি নয় ("twisting does not add weight")। প্রোগ্রাম লাইনের Received সব চালান থেকে হিসাব হয়। Finalized হলে লাইন আর ওজন স্থির; তখনো Transfer? দেওয়া যায়।
  - **Yarn Twist Bill:** বাঁয়ে Yarn Twist Bill List (Party, খোঁজা Bill# / Party Bill#; Date, Bill#, Supplier, Amount, Status)।
    - ডানে: Existing twist bill; Date*, Company*, Supplier*, Program Ref# (সেই সাপ্লায়ারের), Party Bill# (hint), Status*, Remarks। Bill Detail: Challan# (সেই সাপ্লায়ারের চূড়ান্ত চালান; বাছলে Qty = গৃহীত কেজি), Particulars, Yarn, Qty, Rate, UOM, Remarks → Add Line; grid-এ Amount, Total।
    - **নিয়ম:** অন্তত একটা লাইন। চালান হলে সেটা এই সাপ্লায়ারের, চূড়ান্ত, আর অন্য (বাতিল নয় এমন) বিলে নেই; চালান না থাকলে Particulars-এ সমন্বয়টা কী তা লিখতে হবে। Amount = Qty × Rate সার্ভারে। Approved বা Paid হলে বিল আর বদলায় না।
  - **Print:** "Twist Program" — Prg Date, Trgt Delv Date, Company, Supplier, Address, Store, Status; Yarn to Twist (#, Yarn, Color Group, Brand, Lot #, TPI, Ply, Grey(Kg), Received, Balance, Rate, Value, Total); Prepared By, Store In-Charge, Party's Acceptance।

- **Knit Machine Loading Plan:** পেজ খোলে Machine Loading Plan-এ।
  - **Planned Shifts:** Floor, Machine #, Shift, KnitCard# filter; কলাম Date, Shift, Machine, Dia x Gauge, Floor, KnitCard#, Job / Style, Color, Plan (Kg), Produced (Kg)।
  - **Machine Loading Plan:** SEARCH — From (hint), Range (১ সপ্তাহ, ২ সপ্তাহ, ১ মাস, ২ মাস), Company #, Floor, Dia, Machine #, Cat, Color, KnitCard# → Search, Today।
    - গ্রিড: বাঁয়ে মেশিন (নম্বর, ডায়া x গেজ), উপরে দিন আর তার নিচে শিফট। ঘরে কার্ড, রং আর Plan / Produced। ঘরে ক্লিক করলে dialog: KnitCard# (চলমান কার্ড, Cat / Color / KnitCard# দিয়ে ছাঁকা), Plan (Kg) — ফাঁকা থাকলে মেশিনের ক্যাপাসিটি প্ল্যান; Plan, Clear (ভরা ঘরে), Cancel।
    - নিচে Pending Knit Card: যে চলমান কার্ডের কোনো শিফট বুক হয়নি — KnitCard#, Job / Style, Fabric, Color, M/C Dia x GG, Assigned, Rolled, Left।
  - **নিয়ম:** শুধু Open / Running কার্ড। এক ঘরে একটাই কার্ড — আবার পরিকল্পনা করলে আগেরটা বদলায়; Clear করলে শিফট ফেরত, কার্ড আবার Pending-এ (অন্য কোনো শিফটে বুক না থাকলে)। Plan (Kg) = ক্যাপাসিটি প্রতি ঘণ্টা × প্ল্যান ঘণ্টা (Daily Output-এর Target-এর একই নিয়ম)। Produced = ওই দিন, শিফট, মেশিন আর কার্ডের রোলের নিট কেজি।
- **Operator Roll Production Entry:** তিন ট্যাব।
  - **Grey Fabric Roll Production:** স্টেশন বাছলে তার প্রতিটা মেশিন একটা ট্যাব (রিলোড ছাড়া)। মেশিনের কার্ড: এই শিফটে লোডিং প্ল্যানে বুক করা কার্ড, না থাকলে এই মেশিনে শেষ চলমান কার্ড, তাও না থাকলে অপারেটর বাছেন। কার্ডের বিবরণ আর Assigned / Rolled / Left।
    - রোল: Roll Weight*, Deducted, Shift (শুরুতে এখনকার শিফট), Operator (শুরুতে স্টেশনের অপারেটর), Comments → Save Roll। নিচে আজ এই মেশিনের রোল।
  - **Tab Settings for Machine(s):** বাঁয়ে Search By (স্টেশন, Load, New) আর STATIONS তালিকা (খোঁজা); ডানে MACHINE LIST — টিক দিয়ে Save Station।
  - **Save Station:** Existing station, Station Code*, Station Name*, Operator, Active?। এটা মেশিনের তালিকা বদলায় না।
  - **নিয়ম:**
    - কোড বড় হাতের আর অনন্য।
    - মেশিনের তালিকায় অন্তত একটা টিক লাগবে।
    - রোল শুধু স্টেশনের মেশিনে আর চলমান কার্ডে।
    - কর্তন ওজনের বেশি নয়।
    - রোল বসে কার্ডের আজকের roll sheet-এ (না থাকলে নতুন RS-…), কার্ডের শেষ রোলের পরের নম্বরে — Manual Roll Production Entry-র মতোই।
    - কার্ড Running হয়, আর কার্ডের M/C হয় এই মেশিন।

### Yarn Dyeing Programme Register

- **প্রোগ্রাম তৈরি হয় এখানেই** (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১১-০১): Brickland-এ Y/D প্রোগ্রাম তৈরির কোনো স্ক্রিন নেই, তাই Register-এ তৃতীয় ট্যাব "Y/D Program" যোগ হয়েছে। Records-এর Add New আর নম্বরে ক্লিক এই ফর্ম খোলে। Brickland-এর দুটো ট্যাব (Records, Y/D Program Revisions) হুবহু।
- **ফর্ম:** Prg Date*, Type*, Party* (ডাই হাউস), Buyer A/C, Order (Style), Delivery Date, Status*, Remarks; শেডের লাইন (Colour*, Color Group, Yarn Item + Count, Greige Lot #, Qty*, UoM*, Dyeing Rate)। অর্ডার বাছলে বায়ার বসে; অন্য বায়ারের অর্ডার নেয় না। অন্তত একটা শেড লাগে।
- **শেডের লক:** যে শেডের বিপরীতে dyed receipt বা loss memo আছে, সেটা সরানো যায় না (সংশোধন করা যায়)। লাইন id ধরে আপডেট হয়, তাই receipt-এর লিংক ভাঙে না।
- **হিসাব (`YarnDyeing::figures`):**
  - Issue = 15/03-এর রিকুইজিশন (Req. Type "Yarn Dye (Y/D)", Program# = YDP নম্বর)-এর issued_qty (ফেরত বাদে) + Issued / Accepted re-process ডকুমেন্ট।
  - Delivery = Confirmed dyed receipt। Loss = Approved process-loss memo (Issued − Received)।
  - Balance (With Party) = Issue − Delivery − Loss। Pending (শেড) = শেডের Qty − Delivery।
- **Revision:** Y/D Program# বাছলে Supplier, Buyer, Order, Issue, Delivery দেখায়; Rev# পরের নম্বর (সার্ভারে row lock দিয়ে)। Revised On প্রোগ্রামের তারিখের আগে নয়; Reason আবশ্যক। খালি সেভে "Y/D Program # is required." (Brickland-এর মেসেজ)।

### Grey চলাচল আর Racking Board (২০২৬-১১-০১)

- **এক কাগজ, সাতটা kind** (`KnitGreyMoveApiController`; Store-to-Store 15/05-এর):
  - **Flat Knit Grey Fabric Transfer (CCT-…):** Trans Category*, Trans Type* (Collar / Cuff / Collar & Cuff), From Order#*, To Order#, From Store, To Store*। Rolls-এ (Add Roll) From Order-এর যে রোল এখনো নিটিং ফ্লোরে (Rejected নয়, অন্য খোলা কাগজে নেই, স্টোরে নেওয়া নয়); রোল বাছলে ফেব্রিক, শেড আর নিট ওজন বসে, ওজন আর রং বদলানো যায়। Receive-এ To Store-এ kind COLLAR_CUFF লাইন, part = Collar/Cuff, অর্ডার = To Order (না থাকলে From Order); রোল GREY_STORE। এই রোল খোলা থাকতে Store Receipt-এ স্ক্যান আটকায়।
  - **Grey Store to Batch Transfer (GBT-…):** Trans Category*, From Order#*, To Order#, From Store, To Batch*। শুধু স্টোরের ওজন করা রোল (ব্যাচে নেই এমন)। Submit-এ রোল পুরোটা নামে; Receive-এ একই স্টোরে ব্যাচ নম্বরসহ লাইনে ব্যাচের গোনা ওজন ওঠে; রোল BATCH_STORE।
  - **Order-to-Order Grey Transfer (OOT-…):** Trans Type*, From Order#*, To Order#* (আলাদা)। From Order-এর যেকোনো স্টোরের লাইন; যেখানে আছে সেখানেই To Order-এর হিসাবে ওঠে।
  - **Grey Reuse from Leftover Store (GRU-…):** Trans Type*, From Store*, Style/Order#* (যে অর্ডারে ফিরছে)। From Store-এর যেকোনো লাইন; একই স্টোরে সেই অর্ডারে ওঠে; রোল GREY_STORE।
  - **Grey Store to Leftover (GLO-…) / Batch Store to Leftover (BLO-…):** Trans Type*, Style/Order#*, From Store*, To Store*। দ্বিতীয়টায় শুধু ব্যাচে থাকা লাইন; To Store-এ ব্যাচ ছাড়া লাইনে ওঠে; রোল LEFT_OVER। Batch Store to Leftover Brickland-এ scaffold — নিয়মমতো পুরোটা বানানো।
  - সব kind-এ: সেভ = Create Draft; Submit Requisition খাতা থেকে নামায় (ওজন করা রোল পুরোটা; Collar/Cuff-এ কিছু নামে না — রোল তখনো খাতায় নেই); Receive Requisition নতুন লাইনে তোলে আর রোলের মুভমেন্ট বদলায়; Draft / Submitted থেকে Cancel। একই রোল দুই খোলা কাগজে নয়।
- **Grey Fabric Racking Board:** Brickland-এর filter হুবহু (Count, Col. Group, Composition, Spin Type, Cotton Type, Brand, Lot, Buyer, Job#/W.Style)। গ্রে রোলের সুতা তার নিট কার্ডের yarn details-এ লেখা, তাই সুতার filter-গুলো সেই লেখার সাথে মেলে; Lot রোল নম্বরের সাথেও। ফল স্টোর আর র‍্যাক ধরে দলবদ্ধ (র‍্যাক নেই এমনগুলো শেষে), প্রতিটা দলে রোল আর ওজনের যোগফল।

## ৬. API ও পারমিশন

- **Production Order:**
  - `GET /knitting/prod/options`, `GET /knitting/prod/orders?kind=&buyer=&ship_month=&style=&order=&status=`
  - `POST …/orders`, `GET|PUT …/orders/{FPO#}`, `POST …/orders/{FPO#}/{complete|hold|resume}`
  - `GET|POST|PUT …/orders/{FPO#}/programs`, `DELETE …/programs/{KP#}`
  - `GET|POST|PUT …/orders/{FPO#}/cards`, `DELETE …/cards/{KC#}`
- **Roll:**
  - `GET /knitting/prod/rolls/options`, `GET …/rolls?card=&machine=&movement=&quality=&from=&to=`
  - `POST …/rolls/{pass|reject|grey}` (body-তে roll code)
  - `GET …/rolls/card/{KC#}`, `POST …/roll-sheets`, `GET|PUT …/roll-sheets/{RS#}`
- **Output:** `GET /knitting/prod/output?from=&to=&buyer=&order=&category=&colour=&floor=`
- **Inspection:**
  - `GET /knitting/prod/inspections/options`, `GET …/inspections?from=&to=&status=&grade=&machine=`
  - `POST …/inspections`, `GET|PUT …/inspections/{QCI#}`, `POST …/inspections/{QCI#}/{pass|hold|reject}`
- **Sub-Contract Programme** (`{kind}` = BULK_SOLID / SAMPLE_SOLID / PARTY_YD):
  - `GET /knitting/prod/sc/options` (দুই S/C ডেস্কের সব অপশন)
  - `GET|POST /knitting/prod/sc/{kind}`, `GET|PUT|DELETE …/sc/{kind}/{SCP#}`, `POST …/sc/{kind}/{SCP#}/{submit|approve|reject}` (reject-এ ঐচ্ছিক note)
- **Programme Receipt:**
  - `GET /knitting/prod/sc-receipts?party=&store=&status=&from=&to=`, `POST …/sc-receipts`
  - `GET …/sc-receipts/store-yarns?store=` (স্টোরের লট, Yarn আর Brand-এর জন্য)
  - `GET|PUT …/sc-receipts/{Program#}`, `POST …/sc-receipts/{Program#}/{confirm|start|close|cancel}`
- **Yarn test:**
  - `GET /knitting/prod/yarn-test/options` (চার স্ক্রিনের সব অপশন, স্টোরের লট, অর্ডার আর টেস্টের তালিকা)
  - `GET|POST …/yarn-test/orders`, `GET|PUT …/orders/{YTO#}`, `POST …/orders/{YTO#}/{issue|cancel}`
  - `POST …/yarn-test/tests` (অর্ডারে টেস্ট থাকলে সেটাই বদলায়), `GET|PUT …/tests/{YTS#}`
  - `GET …/yarn-test/results?basis=&count=&brand=&lot=&status=&from=&to=`
  - `GET …/yarn-test/waiver-lots?brand=&count=&lot=`, `POST …/yarn-test/waivers`
- **ফ্লোরের সুতা:**
  - General Indent আর Return 15/03-এর endpoint-ই ব্যবহার করে। সেগুলো এখন `knitting.ystore.*` **অথবা** `knitting.production.*` মানে (নতুন `ApiController::allowAny()`)। স্টোরের ইস্যু (`issue-reqs/{no}/source`, `issues…`) আর return-এর accept / reject শুধু `knitting.ystore.manage`।
  - issue-reqs-এর options-এ এখন `lots` আর `yarn_names`; তালিকার সারিতে `style_no`, `order_no`, `order_id`। YarnProcurement-এর অর্ডারের অপশনে `style` আর `order_no`।
  - Replacement Indent: `GET /knitting/prod/replace-reqs/options`, `GET|POST …/replace-reqs`, `GET|PUT …/replace-reqs/{YRR#}`, `POST …/replace-reqs/{YRR#}/{submit|issue|cancel}`।
- **Sample plan:** `GET /knitting/prod/sample-plans/options`, `GET|POST …/sample-plans/{SOLID|YD}?buyer=&store=&status=&from=&to=`, `GET|PUT …/sample-plans/{kind}/{SYR#}`, `POST …/sample-plans/{kind}/{SYR#}/{submit|cancel}`।
- **Direct booking:** `GET /knitting/prod/direct-bookings/options`, `GET|POST …/direct-bookings?category=&buyer=&status=&from=&to=`, `GET|PUT …/direct-bookings/{DFB#}`, `POST …/direct-bookings/{DFB#}/{submit|approve|cancel}`।
- **Yarn twist:** `GET /knitting/prod/twist/options`; `GET|POST …/twist/programs`, `GET|PUT …/twist/programs/{TWP#}`; `GET|POST …/twist/receives`, `GET|PUT …/twist/receives/{TWR#}`; `GET|POST …/twist/bills`, `GET|PUT …/twist/bills/{TWB#}`।
- **Machine loading:** `GET /knitting/prod/machine-loads/options`, `GET …/machine-loads/grid?from=&range=&floor=&dia=&machine=`, `GET …/machine-loads`, `POST …/machine-loads` (একটা ঘর পরিকল্পনা), `POST …/machine-loads/clear`।
- **Station:** `GET /knitting/prod/stations/options`, `POST …/stations`, `GET|PUT …/stations/{code}`, `POST …/stations/{code}/rolls`।
- **ভাগ করা কোড:** `KnitProduction::cardPanel()` আর `targetFor()` এখন Support-এ (আগে KnitRollApiController-এর private); রোল ডেস্ক, লোডিং প্ল্যান আর স্টেশন — তিনটাই এগুলো ব্যবহার করে।
- **URL-এ কী যায়:** ডকুমেন্ট নম্বর দিয়ে (`?ref=FPO-…`, `?order=FPO-…`, `?ref=RS-…`, `?ref=QCI-…`, `?ref=SCP-…`, `?ref=SCR-…`, `?ref=YTO-…`, `?ref=YTS-…`, `?ref=YIR-…`, `?ref=YIRT-…`, `?ref=YRR-…`, `?ref=SYR-…`, `?ref=DFB-…`, `?ref=TWP-…`, `?tab=receive&ref=TWR-…`, `?tab=bill&ref=TWB-…`, স্টেশন `?station=ST-…`), কোনো ULID নয়।
- **পারমিশন:** নতুন `knitting.production.view` আর `knitting.production.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER। LINE_CHIEF কোনোটাই পায় না।
- **Y/D Programme:** `GET|POST /knitting/prod/yd/programs`, `GET|PUT /knitting/prod/yd/programs/{YDP}`, `POST /knitting/prod/yd/revisions`, `GET /knitting/prod/yd/options` (15/03-এর `/knitting/ystore/yd/options`-এর সাথে একই)। দেখা `knitting.production.view` অথবা `knitting.ystore.view`; লেখা `knitting.production.manage`। মেনুতে ystore.view-ও দেওয়া, কারণ স্টোর এই প্রোগ্রামের বিপরীতে কাজ করে।
- **`allowAny` → `allowEither`:** আগের ভাগে base ApiController-এ যোগ করা `allowAny()` চারটা পুরনো কন্ট্রোলারের (GatePass ×2, GeneralStore Requisition, Floor Requisition) নিজস্ব private `allowAny()`-এর সাথে সংঘর্ষ করছিল। ফলে ওই কন্ট্রোলারগুলো fatal error দিত (`route:list`-ও ভাঙত)। base-এর মেথডের নাম এখন `allowEither()`। অন্য মডিউলে হাত দেওয়া হয়নি।
- **Grey চলাচল:** `/knitting/fstore/moves/{collar-cuff|to-batch|order|reuse|to-leftover|batch-leftover}` (+ `/{no}`, `/{no}/submit|receive|cancel`), `floor-rolls?order=`, `lots?store=&order=&rolls=&batch=only|none`, `racks?…`। দেখা `knitting.fstore.view` অথবা `knitting.production.view`; লেখা `knitting.fstore.manage` অথবা `knitting.production.manage`।
- **Route default-এর ফাঁদ:** Store-to-Store-এর পুরনো `/transfers/{no}` রুটে `->defaults('kind', 'store')` দিলে Laravel default-টা প্যারামিটারের পেছনে বসায় আর কন্ট্রোলারে ক্রম ধরে পাঠায় — `kind`-এ নম্বর চলে যায় (404)। তাই store-এর জন্য আলাদা `storeShow/storeSave/storeTransition`।

## ৭. Frontend পেজ (লাইট থিম)

- **সাইডবার:** Knit Production → Production ফোল্ডার (Yarn Store-এর পরে), চব্বিশটা স্ক্রিন। General Indent আর Return স্টোরের লোকও দেখে (`knitting.ystore.view`)।
- **কম্পোনেন্ট** (`components/knitting/`):
  - `prod-order.jsx`: PoRegister, PoEntry (তিন ধাপ), Desk (KP আর Knitcard, অর্ডারের তালিকা + inline grid + dialog)।
  - `prod-roll.jsx`: RollList (Print Label), RollEntry, DailyOutput।
  - `prod-qc.jsx`: InspectionList (Print), InspectionEntry।
  - `prod-sc.jsx`: ScRegister, ScEntry, আর ScTable (দুই S/C রেজিস্টারের টেবিল: খোঁজা, Export, কলাম-filter, পাতা)।
  - `prod-scr.jsx`: ScrList, ScrEntry।
  - `prod-yt.jsx`: YtoList (Knit Card # প্রিন্ট), YtoEntry, আর ভাগের জিনিস (useYt, লটের picker)।
  - `prod-ytest.jsx`: YtTest (Requisition List + ফর্ম), YtResults (তিন স্ক্রিনের তালিকা, basis ধরে), YtWaiver।
  - ScTable-এ ACTION কলাম এখন ঐচ্ছিক (রেজিস্টারে কোনো বোতাম নেই)।
  - `prod-gy.jsx`: GyList, GyEntry (15/03-এর `ystoreAction` দিয়ে)।
  - `prod-rr.jsx`: RrList, RrEntry।
  - `prod-sk.jsx`: SkList (Print), SkEntry।
  - `prod-db.jsx`: DbList (Print), DbEntry।
  - `prod-tw.jsx`: TwList (Print), TwProgram, TwReceive, TwBill, আর SideList (বাঁয়ের চালান/বিল তালিকা)।
  - `prod-ml.jsx`: MlList, MlPlan (গ্রিড, dialog, Pending Knit Card)।
  - `prod-op.jsx`: OpProduction, OpSettings, OpStation।
  - Checkbox যেখানে `<label>`-এর ভেতরে ছিল (General Indent-এর Closed, Twist-এর Finalized / Transfer), একবার ক্লিকে দুবার টগল হচ্ছিল; `<div>` করা হয়েছে।
  - `ystore-outward.jsx`-এর `IssueReturnList` / `IssueReturnEntry`-তে `base` আর `steps` prop — 15/04-এর route-এ ফ্লোরের ধাপ নিয়ে।
- **ট্যাব:**
  - Production Order: Fabric Production Knitting Order (ধরন), Knit Programmes (KP), Knitcard, Save Production Order।
  - Roll: Roll List আর Roll Weight Entry।
  - Inspection: Inspection List আর Roll Inspection।
  - Daily Output-এর কোনো ট্যাব নেই।
  - S/C Programme: Records আর New S/C Knitting Program — Bulk (Solid) / Sample (Solid) / Party (Y/D)।
  - Receipt: Sub-contract Program List আর Knitting Sub-contract Program (খোলে এটাতেই)।
  - Yarn test: Knit Plan & Requisition for Yarn Test List / Knit Plan & Requisition for Yarn Test; Knit Yarn Test Register / Yarn Test; Yarn Test List / Yarn Test (খোলে ফর্মে); Approved Without Test / Yarn Approval Without Test (খোলে এটাতেই)।
  - General Indent: Records / Yarn Issue Requisition; Return: Records / Yarn Return to Store; Replacement: Requisition for Yarn Replace by Supplier List / Requisition for Yarn Replace by Supplier।
  - Sample plan: Sample Yarn Requisition List / Sample Yarn Requisition Ref# — Solid (বা — Y/D), খোলে ফর্মে; Direct Booking: Direct Booking List / Direct Sample Fabric Booking, খোলে ফর্মে।
  - Yarn Twisting: Twist Program (তালিকা), Twist Program, Twist Yarn Receive, Yarn Twist Bill (S/C Out-house)।
  - Machine Loading: Planned Shifts / Machine Loading Plan (খোলে এটাতেই); Operator: Grey Fabric Roll Production / Tab Settings for Machine(s) / Save Station।
- **লোড:** খোঁজা, সেভ, অবস্থা বদলানো আর অর্ডার বাছাই পেজ রিলোড ছাড়া হয়।
- **Print:**
  - Roll List-এর Print Label: "Grey Fabric Roll Tag" — বিবরণ, ওজনের টেবিল, অপারেটর আর মানের স্বাক্ষর।
  - Inspection List-এর Print: "Grey Fabric Roll Inspection" — সব তথ্য, ত্রুটির টেবিল (গ্রুপ, সংখ্যা, প্রতিটার পয়েন্ট, পয়েন্ট), Inspector আর QC In-charge।
  - S/C Programme-এর Program: "S/C Knitting Programme — Bulk (Solid)" ইত্যাদি — PO Date, Company, Party, Address, Program Type, Prod. Category, Buyer, Style, Job/Work Style, Order #, Target Delivery, Yarn Transferred, Status; "Fabric to be knitted" টেবিল (#, Fabric, Colour, Construction "M.Dia 30 · 24G · F.Dia 72 · 160 GSM", UoM, Qty, Received, Balance, Rate, Value, Total); Prepared By, Approved By, Party's Acceptance।
  - S/C Programme-এর Challan: "S/C Knitting Delivery Challan" — একই তথ্য, "Going out on this challan" (Qty, Already Received, Balance); Store In-Charge, Security, Driver, Received By।
  - Receipt-এর Print: "Knitting Sub-contract Program" — Prog Date, Party, Address, Yarn Store, Company, Status, Fabric Lines, Total Qty; Color Wise Fabric Detail (Fin.Dia "70.00 Inch (Open)", Mc Dia x GG, Rate "14.00 Tk/Kg" …) আর Yarn Against Each Fabric, দুটোতেই Total; Prepared By, Store In-charge, Party।
  - Test order-এর Knit Card #: "Knit Plan & Requisition for Yarn Test" — Order Date, Company, Req. Type, Store, Req. By, Attn. To, Yarn Description, Knit Card #, Status; "Lots going to the lab" (Yarn Item, Color Group, Brand, Supplier, L/C #, Lot #, Qty, UOM, Result, Remarks, Total); Requested By, Store In-Charge, Lab In-Charge।
  - সবগুলো `lib/print-document` দিয়ে (landscape যেখানে কলাম বেশি), preview দেখে যাচাই করা।
- **Yarn Dyeing Programme Register** (`prod-yd.jsx`): Records (Program #, Buyer A/C, Work Style/Job#, Party, Status, Prg Date; সারিতে Print আর Revise), Y/D Program Revisions (Add Revision / Cancel, Revision History, Save programme), আর Y/D Program ফর্ম। প্রিন্ট "Y/D Programme" (landscape): শেড, Delivered, Pending, Value, revision-এর তালিকা।
- **Grey চলাচল** (`prod-grey.jsx`): এক `MoveList` আর এক `MoveEntry`, kind-এর config অনুযায়ী ঘর আর লাইন। Collar/Cuff আর Batch-এ Rolls (সারি ধরে, Add Roll), বাকিগুলোয় Fabric Detail (Add Fabric / Cancel)। Collar/Cuff আর Batch-এর তালিকা Show চাপলে ছাঁকে, বাকিগুলো লিখতে লিখতে (Clear)। লাইনের picker-এর উৎস একসাথে দুবার আনলে শুধু শেষেরটা ধরা হয়; From Store আবশ্যক এমন স্ক্রিনে স্টোর না বাছলে কিছু আনা হয় না; খোলা draft-এর উৎস server-এ আনা।
  - প্রিন্ট (preview দেখে যাচাই): Collar / Cuff Transfer Challan, Grey to Batch …, Order-to-Order …, Grey Reuse …, Grey to Leftover …, Batch to Leftover Transfer Challan (একই নকশা, kind-এর শিরোনাম)।
- **Grey Fabric Racking Board** (`GreyRacks`)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩২টার ৩২টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_05_000100_create_knitting_production.php`
- `app/Modules/Knitting/Models/KnitProdOrder.php`, `KnitProgram.php`, `KnitCard.php`, `KnitRollSheet.php`, `KnitRoll.php`, `KnitRollInspection.php`, `KnitRollInspectionLine.php`
- `app/Modules/Knitting/Support/KnitProduction.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitProdOrderApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitRollApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitInspectionApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_06_000100_create_knitting_subcontract.php`
- `app/Modules/Knitting/Models/KnitScProgram.php`, `KnitScProgramLine.php`, `KnitScReceipt.php`, `KnitScReceiptFabric.php`, `KnitScReceiptYarn.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitScProgramApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitScReceiptApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_07_000100_create_knitting_yarn_test.php`
- `app/Modules/Knitting/Models/KnitYarnTestOrder.php`, `KnitYarnTestOrderLine.php`, `KnitYarnTest.php`, `KnitYarnTestResult.php`
- `app/Modules/Knitting/Support/YarnTesting.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYarnTestOrderApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYarnTestApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_08_000100_create_knitting_yarn_replace_reqs.php`
- `app/Modules/Knitting/Models/KnitYarnReplaceReq.php`, `KnitYarnReplaceReqLine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYarnReplaceReqApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/YarnIssueApiController.php`, `YarnOutwardApiController.php` (allowAny, options, তালিকার Style/Order)
- `app/Modules/Knitting/Support/YarnProcurement.php` (অর্ডারের `style`, `order_no`)
- `app/Shared/Http/Api/ApiController.php` (`allowAny()`)
- `app/Modules/Knitting/Database/Migrations/2027_01_09_000100_create_knitting_sample_plans_and_direct_bookings.php`
- `app/Modules/Knitting/Models/KnitSamplePlan.php`, `KnitSamplePlanLine.php`, `KnitDirectBooking.php`, `KnitDirectBookingLine.php`
- `app/Modules/Knitting/Support/KnitSamples.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitSamplePlanApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitDirectBookingApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_10_000100_create_knitting_yarn_twist.php`
- `app/Modules/Knitting/Models/KnitTwistProgram.php`, `KnitTwistProgramLine.php`, `KnitTwistReceive.php`, `KnitTwistReceiveLine.php`, `KnitTwistBill.php`, `KnitTwistBillLine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitTwistApiController.php`
- `app/Modules/Knitting/Database/Migrations/2027_01_11_000100_create_knitting_machine_loads_and_stations.php`
- `app/Modules/Knitting/Models/KnitMachineLoad.php`, `KnitStation.php`, `KnitStationMachine.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitMachineLoadApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitStationApiController.php`
- `app/Modules/Knitting/Support/KnitProduction.php` (`cardPanel()`, `targetFor()`)
- `app/Modules/Knitting/Database/Migrations/2027_01_12_000100_create_knitting_yarn_dyeing.php`
- `app/Modules/Knitting/Models/KnitYdProgram.php`, `KnitYdProgramLine.php`, `KnitYdRevision.php`
- `app/Modules/Knitting/Support/YarnDyeing.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitYdProgramApiController.php`
- `app/Shared/Http/Api/ApiController.php` (`allowAny()` → `allowEither()`)
- `app/Modules/Knitting/Database/Migrations/2027_01_14_000100_extend_knitting_grey_transfers.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitGreyMoveApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitGreyStoreApiController.php` (`racks`, স্ক্যানে খোলা কাগজের রোল আটকানো)
- `app/Modules/Knitting/Support/GreyStore.php` (batch_no, `lotIn` overrides)
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/knit-production/[screen]/page.js`
- `frontend/src/app/(app)/knit-production/[screen]/prod-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/actions.js` (`prodAction`)
- `frontend/src/app/(app)/knit-production/[screen]/screens.js` (`DEFAULT_TABS`: Receipt, Production Test Clearance, Approval Without Testing, দুই Sample Knit Plan, Direct Booking আর Machine Loading Plan খোলে ফর্মে/গ্রিডে)
- `frontend/src/components/knitting/prod-order.jsx`
- `frontend/src/components/knitting/prod-roll.jsx`
- `frontend/src/components/knitting/prod-qc.jsx`
- `frontend/src/components/knitting/prod-sc.jsx`
- `frontend/src/components/knitting/prod-scr.jsx`
- `frontend/src/components/knitting/prod-yt.jsx`
- `frontend/src/components/knitting/prod-ytest.jsx`
- `frontend/src/components/knitting/prod-gy.jsx`
- `frontend/src/components/knitting/prod-rr.jsx`
- `frontend/src/components/knitting/prod-sk.jsx`
- `frontend/src/components/knitting/prod-db.jsx`
- `frontend/src/components/knitting/prod-tw.jsx`
- `frontend/src/components/knitting/prod-ml.jsx`
- `frontend/src/components/knitting/prod-op.jsx`
- `frontend/src/components/knitting/prod-yd.jsx`
- `frontend/src/components/knitting/prod-grey.jsx`
- `frontend/src/components/knitting/ystore-outward.jsx` (`base`, `steps`)

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php` (`prod.*`, `sc.*`, `yt.*`, `gy.*`, `rr.*`, `sk.*`, `db.*`, `tw.*`, `ml.*`, `op.*`, `yd.*`, `gt.*`)

**Test**
- `tests/Feature/Knitting/KnittingProductionTest.php`
- `tests/Feature/Knitting/KnittingSubContractTest.php`
- `tests/Feature/Knitting/KnittingYarnTestTest.php`
- `tests/Feature/Knitting/KnittingYarnIndentTest.php`
- `tests/Feature/Knitting/KnittingSampleBookingTest.php`
- `tests/Feature/Knitting/KnittingYarnTwistTest.php`
- `tests/Feature/Knitting/KnittingMachineLoadTest.php`
- `tests/Feature/Knitting/KnittingYarnDyeingTest.php`
- `tests/Feature/Knitting/KnittingGreyStoreTest.php` (`test_grey_movements_and_racking_board`)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **বাকি:** নেই — ৩২টা স্ক্রিনই তৈরি।

## ৯. টেস্ট ও যাচাই

- **`KnittingProductionTest` ১/১ (৯৪ assertion):**
  - **Options:** Cat-এ FP02 আছে। অর্ডারের লেবেল "770MR · LPP-2026-000060"।
  - **Order:** Fab. ছাড়া Brickland-এর মেসেজ আসে। ৮৫০ − ২০ = ৮৩০; FPO-…0001, Bulk Booking, BULK_NEW।
  - **KP:**
    - ৪০০ ছাড়া হয়। এরপর ৫০০ আটকায় ("(430 Kg)")। আরও ৩০০ ছাড়া হয়।
    - চাহিদা ৬০০-তে নামানো আটকায় (৭০০ ইতিমধ্যে ছাড়া)।
  - **KC:**
    - ১৫০ কাটা হয়; রং আর ফিনিশ ডায়া অর্ডার থেকে আসে। এরপর ৩০০ আটকায় ("(250 Kg)"), ২৫০ কাটা হয়।
    - কার্ড আছে এমন KP মোছা বা কার্ডের নিচে নামানো আটকায়। খালি KP বাতিল করলে KP Made ৪০০, Blnc ৪৩০।
  - **Hold:** স্থগিত অর্ডারে নতুন প্রোগ্রাম আটকায়; Resume।
  - **Roll:**
    - কর্তন ওজনের বেশি হলে আটকায়। RS-…0001-এ /1 আর /2; Rolled ৪৪, Left ১০৬। কার্ড Running হয়, M/C বসে।
    - শিট সংশোধন হয়। নেওয়া নম্বর আটকায়। খালি নম্বরে /3 বসে।
    - Grey store-এ একবারই পাঠানো যায়। বাতিল রোল যায় না।
    - Register-এ Rolled ৬৪.৬। রোল আছে এমন কার্ড মোছা বা বাতিল আটকায়।
  - **Inspection:**
    - Slub ×৩ + Hole ×১ = ৭ পয়েন্ট, ২২.৩ কেজিতে ৩১.৩৯, গ্রেড B। লাইনে পয়েন্ট আর গ্রুপ কপি হয়।
    - একই রোলে দ্বিতীয় শিট আটকায়।
    - Hole ×৩, ২১.৭ কেজিতে ৫৫.৩০, গ্রেড C। হাতে A দিলে A থাকে।
    - ত্রুটিহীন রোল গ্রেড A।
    - Pass হলে রোল PASSED; তারপর সংশোধন আটকায়। Hold → Reject হলে রোল REJECTED।
    - ইন্সপেকশন হওয়া রোল শিট থেকে সরানো আটকায়।
  - **Output:** ৬৪.৬ / ৬৪.৬, Target ৮.৫ × ২২ = ১৮৭, Balance ৮৫.৪, MC-01।
  - **পারমিশন:** Line Chief ঢুকতে পারে না।
- **`KnittingSubContractTest` ২/২ (৮৩ assertion):**
  - **Programme:**
    - Options-এ Bulk-এর Type দুটো (Bulk, Short-Bulk), পার্টির লেবেল "Arafah Knit Composite Ltd (SUP-10001)"।
    - লাইন ছাড়া Brickland-এর মেসেজ আসে; ভুল Type আর Qty ০ আটকায়।
    - SCP-…0001 Draft: অর্ডার থেকে Buyer LPP আর Job/Work Style 770MR, পার্টির ঠিকানা, রং "Navy (NV-01)"।
    - শুধু নিজের স্ক্রিনের রেজিস্টারে দেখায়; Y/D-র URL-এ 404।
    - দুই লাইন পাঠানোর ক্রমে থাকে। Draft-এ Approve আটকায়; Submit-এর পর সংশোধন ("… is Submitted for Approval — its lines can no longer be rewritten.") আর মোছা আটকায়।
    - Reject (note সহ) → রেট ১২.৫ করে সংশোধন → আবার Submit → Approve; note মুছে যায়, decided_by বসে।
    - Y/D-তে অর্ডার ছাড়া হাতে Job/Work Style দিয়ে SCP-…0002; Draft মোছা যায়।
  - **Receipt:**
    - পার্টির লট (Square, P-1) store-yarns-এ আসে।
    - ফেব্রিক ছাড়া Brickland-এর মেসেজ। Auto? ছাড়া নম্বর না দিলে, আর "/" থাকলে আটকায়।
    - সিরিয়াল ৯-এ সুতা আটকায় ("Yarn row 1 hangs off serial 9, which no fabric line carries.")। একই SL দুবার আটকায়।
    - SL ৫, খালি, খালি → ৫, ৬, ৭; Qty ২৭০০, Yarn Rqd ১২৫০; সুতা "100% Cotton 30/1"; Mc Dia 30", Gauge 24GG।
    - পার্টির নম্বর "AKC-PRG 0091" বসে; দ্বিতীয়বার আটকায়।
    - Register-এ Fabrics ৩। Confirm-এর আগে Start আটকায়; Confirm-এর পর সংশোধন আটকায়; Start → Close; Closed-এ Cancel আটকায়। অন্যটা Cancel হয়, Status filter কাজ করে।
- **`KnittingProductionTest` আবার চালানো হয়েছে:** ১/১ (৯৪)।
- **`KnittingYarnTestTest` ১/১ (৫৮ assertion):**
  - Options-এ দুই কর্মী আর স্টোরের দুই লট (SQ-3011, SQ-3012)।
  - লট ছাড়া Brickland-এর মেসেজ; Yarn Description ছাড়া আটকায়। YTO-…0001 Waiting for Issue, Req. Type YARN_TEST, Req. By / Attn. To-র নাম, Yarn "100% Cotton 30/1", Supplier।
  - Issue-এর আগে টেস্ট আটকায় ("… is Waiting for Issue — the store has not issued its yarn to the lab.")। Issue → Lots "SQ-3011, SQ-3012"; দ্বিতীয়বার Issue আটকায়।
  - লট ছাড়া টেস্ট আটকায়। YTS-…0001: Passed + Pending; অর্ডার Tested, Results "1 Passed · 1 Pending"; সংশোধন আর Cancel আটকায়।
  - আবার জমা: একই YTS নম্বর, Passed + Failed, রেজিস্টারে মোট ২টা সারিই; অর্ডারের লাইনে ফলাফল দেখায়।
  - Waiver lots: SQ-3012-এ "Failed · Lab", SQ-3011-এর balance ৫০০; lot filter কাজ করে। টিক ছাড়া আর Test By ছাড়া আটকায়। SQ-3012 ছাড়া হয় (WAIVED, Passed); Already Answered "Passed · Without Test"। আবার ছাড়লে আগেরটা বদলায় (Manik Raton, "Corrected authority")।
  - Register: মোট ৩; LAB + Failed filter; count + lot filter।
  - দ্বিতীয় অর্ডার YTO-…0002 Cancel; তার বিপরীতে টেস্ট আটকায়।
- **`KnittingSubContractTest` আবার চালানো হয়েছে:** ২/২ (৮৩)।
- **`KnittingYarnIndentTest` ২/২ (৫২ assertion):**
  - **ফ্লোর আর স্টোর:** শুধু production অনুমতির একটা role (KNIT_FLOOR)। ফ্লোর options পড়ে (লট L-1 · Square, অর্ডারের Style 770MR)। ফ্লোরের তোলা YIR-…0001 স্টোরের তালিকায় Style 770MR, Order LPP-2026-000060, ৪০ / ০ সহ আসে।
  - ফ্লোরের কাছে issue source আর issue ডেস্ক বন্ধ (403); স্টোর ২৫ কেজি ইস্যু করে।
  - ফ্লোর return তোলে আর Issue করে; ফ্লোরের Accept 403, স্টোরের Accept চলে। ফ্লোর রিকুইজিশন Close করে।
  - **Replacement:** Return Type-এ CLAIM_YARN_TEST। লাইন ছাড়া Brickland-এর মেসেজ; Return Type ছাড়া আর Qty ০ আটকায়। YRR-…0001 Draft, Lot আর Lot # "SQ-3011" (বড় হাতের, ফাঁকা ছাঁটা), সুতা "100% Cotton 30/1", Supplier।
  - দুই লট পাঠানোর ক্রমে। Draft-এ Issue আটকায়; Submit-এর পর সংশোধন আটকায় ("… is Submitted — only a draft can be rewritten.")। Issue → ২ লাইন, ১৫০ কেজি; তারপর Cancel আটকায়।
  - দ্বিতীয় ক্লেইম Draft-এ Cancel; তালিকায় দুটোই।
- **`KnittingYarnIssueTest` (15/03) আবার চালানো হয়েছে:** ১/১ (৯৯)।
- **`KnittingMachineLoadTest` ১/১ (৪৫ assertion):**
  - FPO → KP → দুটো KC, MC-01 মেশিন, এখন চলমান একটা শিফট, ক্যাপাসিটি ৮ × ১২।
  - গ্রিড: ৭ দিন, MC-01, দুটো কার্ডই Pending।
  - ভুল কার্ড আটকায়। পরিকল্পনা: Plan ৯৬ (ক্যাপাসিটি থেকে), একটা কার্ড Pending থেকে সরে। আবার পরিকল্পনা (৬০ কেজি) আগেরটা বদলায়, এক ঘরে একটাই সারি। Clear করলে সারি মুছে যায়।
  - স্টেশন: মেশিন ছাড়া টিক আটকায়; "st-01" → ST-01; একই কোড আবার আটকায়। মেশিন যুক্ত করলে মেশিনের কার্ড = পরিকল্পিত কার্ড, planned, এখনকার শিফট। স্টেশন ফর্ম মেশিনের তালিকা বদলায় না।
  - রোল: কর্তন বেশি হলে আটকায়, স্টেশনের বাইরের মেশিন আটকায়। /1 আর /2, একটাই roll sheet; কার্ড Running, M/C বসে, রোলে শিফট বসে।
  - লোডিং প্ল্যানে Produced ৪৩.৮ (তালিকা আর গ্রিড দুটোতেই); রোল তালিকাতেও স্টেশনের দুই রোল।
- **`KnittingProductionTest` আবার চালানো হয়েছে** (cardPanel / targetFor সরানোর পর): ১/১ (৯৪)।
- **`KnittingYarnTwistTest` ১/১ (৫২ assertion):**
  - লাইন ছাড়া আর Status ছাড়া আটকায়। TWP-…0001 Draft, দুই লাইন, "100% Cotton 30/1", TPI ১৮, Ply ২।
  - Draft প্রোগ্রামে গ্রহণ আটকায়। গ্রহণের আগে সংশোধনে লাইন নতুন করে লেখা হয় (Running)।
  - ১২০ কেজি Grey আটকায় ("(100 Kg)"), Receive > Grey আটকায়। TWR-…0001 (৬০ / ৫৮.৫); তারপর ৫০ আটকায় ("(40 Kg)"); TWR-…0002 দুই লাইনে।
  - প্রোগ্রাম: Grey ১৪০, Received ১৩৬.৭, Balance ৩.৩। লাইন বদলানো আটকায়; Closed করা যায়।
  - চালান Finalized; তারপর ওজন বদলায় না, Transfer দেওয়া যায়।
  - বিল: লাইন ছাড়া, অচূড়ান্ত চালান, অন্য সাপ্লায়ার, বিবরণহীন সমন্বয় আটকায়। TWB-…0001: ৫৮.৫ × ৩৫ = ২০৪৭.৫ + ৪৫০ = ২৪৯৭.৫। একই চালান দ্বিতীয় বিলে আটকায়। Approved-এর পর সংশোধন আটকায়।
- **`KnittingSampleBookingTest` ২/২ (৫৮ assertion):**
  - **Sample plan:** options-এ অর্ডারের লেবেল "770MR · LPP-2026-000060"। লাইন ছাড়া Brickland-এর মেসেজ। SYR-S-…0001 Draft; লাইনে অর্ডার থেকে Buyer LPP; Y/D-র তালিকা ফাঁকা।
  - Store, ধরন আর নিবন্ধিত সুতা ছাড়া Submit আটকায় (তিনটা ভুলই একসাথে)। সব দিয়ে Submit → Requisition Submitted, YIR-…0001; তারপর সংশোধন আটকায়।
  - রিকুইজিশনে KNIT_SAMPLE, স্টোর, Program No = প্ল্যান নম্বর, সুতা, Natural, ৮ কেজি, OPEN।
  - স্টোর Partly Issued করলে প্ল্যান Issued (status filter-এও); তখন Cancel আটকায়। Closed করলে প্ল্যান Closed।
  - Y/D-র নিজের নম্বর SYR-Y-…0001; Draft Cancel।
  - **Direct booking:** লাইন ছাড়া, বায়ার ছাড়া, ফেব্রিক ছাড়া আর অন্য বায়ারের অর্ডারে আটকায়। DFB-…0001: Sample Booking, LPP, "770MR / LPP-2026-000060", Job 770MR।
  - Draft-এ Approve আটকায়; Submit; তারপর সংশোধন আটকায়; Approve (decided_by বসে); তারপর Cancel আটকায়। দ্বিতীয়টা Cancel; Status আর Prod. Type filter।
- **ব্রাউজার (dev, kp01 আর kp02; ডাটা রাখা আছে):**
  - **Bulk order:** LPP SS27-TEE01 · S/J 30/1 Combed 160 GSM · Black, Bulk Booking, ৮৫০ − ২০, YarnStk ১২০।
  - **KP-2026100001:** ৪০০ কেজি, Ground Floor।
  - **KC-2026100001:** ১৫০ কেজি, 30 x 24, lot SQ-3011।
  - Register দেখায় ৮৩০ / ৪০০ / ৪৩০ / ১৫০ / ২৫০ — Brickland-এ বানানো একই অর্ডারের সংখ্যার সাথে হুবহু।
  - **RS-2026100001:** KM-01, Day shift, Mohammad Sayem। রোল ২২.৫ আর ২১.৮ (কর্তন ০.৩)।
  - **QCI-2026100001:** /1, কর্তন ০.২, Slub ×৩ + Hole ×১ → ৭ / ৩১.৩৯ / B।
  - Print Label আর Inspection Print-এর preview দেখে যাচাই করা হয়েছে।
  - Daily Output দেখায় ২৯ Oct, Ground Floor, KM-01।
- **ব্রাউজার S/C (dev, sc01–sc04; ডাটা রাখা আছে):**
  - **SCP-2026100001** (Bulk): Square Textiles, Sub-Contract Booking (FP04), অর্ডার LPP-2026-000001 (Buyer LPP, Style SS27-TEE01 নিজে থেকে)। লাইন: S/J 30/1 Combed · Navy Blue · 30/24/72/160 · ৫০০ কেজি @ ১৪, আর 1x1 Rib ৮০ কেজি @ ১৬। খালি Save-এ "Type is required. · Party is required."। Submit → Approve, register-এ WorkFlow Submit → Approve → —।
  - **SCP-2026100002** (Sample, Develop): H&M, হাতে Job/Work Style HM-DEV-2611, Pique ২৫ কেজি; Submitted।
  - **SCP-2026100003** (Y/D, Short-Bulk): Y/D Stripe S/J ২৬০ কেজি, Dark; Draft।
  - **SCR-2026100001:** Square, Yarn store (DHK-YRN); SL ১ (LSJ, Navy Blue, 95/5, 70 Inch Open, 30"/24GG, ১২০০ কেজি @ ১৪ Tk/Kg) আর SL ২ (৩০০ কেজি); Confirm → In Production।
  - **SQT-KP-0091** (পার্টির নম্বর, Auto? তোলা): Interlock Black ৬৫০ কেজি, SL ৩; সুতা 100% Cotton 30/1 Natural, Body, lot SQ-3011, S.Len ৩.১, Ratio ১০০ → Rqd ৬৫০ নিজে থেকে; Confirmed।
  - Program, Challan আর Receipt Print-এর preview দেখে যাচাই করা হয়েছে।
- **ব্রাউজার Yarn test (dev, yt01–yt04; ডাটা রাখা আছে):**
  - **YTO-2026100001:** Yarn store (DHK-YRN), Req. By Poritosh Chandra Das, Attn. To Shawonoor Rahman, "30/1 Combed Cotton — new spinner trial before bulk", Knit Card KC-2026100001; লট SQ-3011 (100% Cotton 30/1, Natural, Square Textiles, LC-0045/26, ২ কেজি) আর GR-77 (১.৫ কেজি)। খালি Save-এ "Yarn Description is required."। Issue → Issued; Knit Card # প্রিন্টের preview দেখা হয়েছে।
  - **YTS-2026100001** (Production Test Clearance): Requisition List-এর সারিতে ক্লিক করে দুই লট Pending হিসেবে এল; Test By Mohammad Sayem; SQ-3011 Passed (Natural), GR-77 Conditional ("Hairiness slightly high — knit on 28GG only")। অর্ডার Tested, Results "1 Passed · 1 Conditional"।
  - **Approval Without Test:** Shawonoor Rahman-এর কর্তৃত্বে OB-301 (Bonded store, ৫৩৫ কেজি) ছাড়া, নোট "Merchandiser approved on buyer's email — dark shade only"। Already Answered-এ SQ-3011 Passed · Lab, GR-77 Conditional · Lab, OB-301 Passed · Without Test।
  - Register-এ তিনটা সারি: একটা Without Test, দুটো Lab।
- **ব্রাউজার ফ্লোরের সুতা (dev, gy01–gy02; ডাটা রাখা আছে):**
  - **YIR-2026100002** (General Indent): Store Requisition for Knitting - Development, Program TRIAL-KC-7, Yarn store, অর্ডার LPP-2026-000001 (Buyer LPP আর Style SS27-TEE01 নিজে থেকে); 100% Cotton 30/1 · Natural · SQ-3011 · ১২ কেজি। খালি Save-এ ধরন আর লাইনের মেসেজ। তালিকায় 15/03-এর YIR-2026100001 (Partly Issued, ৩৫০ / ৩৩৮.৫ / ১১.৫)-ও দেখায় — একই কাগজ।
  - **YIRT-2026100002** (Return): YIR-2026100001-এর বিপরীতে Floor 2 — Hasan, ৩ / ৩ কেজি; Issue করা, স্টোরের Accept-এর অপেক্ষায়।
  - **YRR-2026100001** (Replacement): Square Textiles, CLAIM BY YARN TEST, LC-0045/26, Lot GR-77 (হাতে "gr-77" লেখা), 60% Cotton 40% Polyester 24/1 · Grey · ৪০ কেজি; Submitted।
- **ব্রাউজার Sample আর Direct Booking (dev, sk01–sk02; ডাটা রাখা আছে):**
  - **SYR-S-2026100001:** LPP, Yarn store, Store Requisition for Knitting - Sample; লাইন ১: LPP-2026-000001 · Interlock · Navy Blue · "30/1 Combed Cotton, compact" · 100% Cotton 30/1 · Natural · ৮ কেজি · 220 / 64 / 34 / 24; লাইন ২: SS27-DEV-04 (লেখা) · ৫ কেজি। খালি Save-এ Brickland-এর মেসেজ। Submit Requisition → YIR-2026100003, Requisition Submitted।
  - **SYR-Y-2026100001:** Y/D, H&M stripe dev, Lycra Single Jersey, ৬ কেজি; Draft (নিবন্ধিত সুতা নেই, তাই জমা হবে না)।
  - **DFB-2026100001:** Sample Booking, LPP-2026-000001, Interlock 30/1 Combed 220 GSM · Black · 64 Inch Open · ৩০ কেজি · ১২ Nov; Submit → Approve।
  - দুটো প্রিন্টের preview দেখে যাচাই করা হয়েছে।
- **ব্রাউজার Yarn Twisting (dev, tw01–tw02; ডাটা রাখা আছে):**
  - **TWP-2026100001:** Square Textiles, Yarn store, Running, ১০ Nov; 100% Cotton 30/1 · Natural · SQ-3011 · ৫০ কেজি · TPI ১৮ · Ply ২ · ৩৫, আর 60% Cotton 40% Polyester 24/1 · GR-77 · ৩০ কেজি। খালি Save-এ "Supplier is required."।
  - **TWR-2026100001:** GP-778, Cotton লাইনের বিপরীতে Grey ৩০ / Receive ২৯.৪ / ২৪ কোন (Loss ০.৬); Finalized। প্রোগ্রামে Received ২৯.৪, Balance ৫০.৬।
  - **TWB-2026100001:** SQ/TW/0045, TWR-2026100001 · ২৯.৪ × ৩৫ = ১০২৯ আর "Carriage both ways, agreed" ৩০০ → ১৩২৯; Draft।
  - Twist Program প্রিন্টের preview দেখে যাচাই করা হয়েছে।
- **ব্রাউজার Machine Loading আর Operator (dev, ml01–ml02; ডাটা রাখা আছে):**
  - KM-01-এর আজকের Day shift-এ KC-2026100001 (Black) পরিকল্পিত — Plan ২২৫ কেজি; Pending খালি।
  - **ST-A** "Knit line A (KM-01, KM-02)", অপারেটর Mohammad Sayem; Tab Settings-এ KM-01 আর KM-02 টিক।
  - স্টেশনে KM-01 ট্যাবে রোল ২৩.১ (কর্তন ০.২) → KC-2026100001/3, নিট ২২.৯, Day shift; Planned Shifts-এ Produced ২২.৯।
  - গ্রিডের দিনের শিরোনাম সার্ভার আর ব্রাউজারে ভিন্নভাবে ফরম্যাট হওয়ায় hydration error আসছিল; নিজস্ব ফরম্যাটে ঠিক করা, এখন console-এ ভুল নেই।

- **Yarn dyeing ভাগ (২০২৬-১১-০১):** `KnittingYarnDyeingTest` — ১টা টেস্ট, ৯৭টা assertion, test2 DB-তে পাস (Register আর 15/03-এর ছয় স্ক্রিন একসাথে; বিস্তারিত [03_Yarn_Store](03_Yarn_Store.md))। Register-এর অংশ: শেড ছাড়া সেভ আটকায়, অর্ডার থেকে বায়ার, YDP-… নম্বর, revision ১ → ২ ক্রমে আর প্রোগ্রামের আগের তারিখে আটকায়, Yarn Dye রিকুইজিশনের ইস্যু Issue-তে আসে, প্রাপ্তি হওয়া শেড সরানো আটকায়।
  - পুরো `tests/Feature/Knitting` ফোল্ডার, GatePass, GeneralStore Requisition আর দুটো Floor Requisition টেস্ট আবার চালানো হয়েছে (allowEither-এর জন্য)।
- **নিজে ব্রাউজারে যাচাই (Y/D, dev, ডাটা রাখা আছে):**
  - **YDP-2026110001:** Square Textiles, LPP / SS27-TEE01, In-House; শেড Navy Blue ৬০ kg (Dark Shade, রেট ১২০) আর Navy/White Stripe ৪০ kg (Light Shade, রেট ৯০), গ্রে লট SQ-3011। Revision ১: "Buyer revised the navy shade card (LPP lab dip v2)"।
  - **YIR-2026110001** (Yarn Dye, Program# YDP-2026110001) থেকে SQ-3011-এর ১০০ kg ইস্যু → Register-এ Issue ১০০।
  - বাকি ধাপ (dyed receipt, loss memo, re-process) শেষে Register: Issue ১০৫, Delivery ৯৭, Balance ৫।
  - প্রোগ্রামের প্রিন্ট preview দেখে যাচাই করা।

- **Grey চলাচল (২০২৬-১১-০১):** `KnittingGreyStoreTest::test_grey_movements_and_racking_board` — নতুন test method; পুরো ফাইল ২টা টেস্ট, ১৬০টা assertion, পাস। MenuAccessTest আবার পাস।
  - Collar/Cuff: Trans Type আর লাইন ছাড়া আটকায়; অন্য অর্ডারের রোল আটকায়; floor-rolls; একই রোল দ্বিতীয় কাগজে আর Store Receipt-এ স্ক্যান আটকায়; Receive-এ COLLAR_CUFF লাইন ৫.২, part Collar, রোল GREY_STORE; ভুল kind-এর route-এ 404।
  - Batch: To Batch আবশ্যক; রোল ছাড়া লাইন আটকায়; Submit-এ রোল ০, Receive-এ B-77 ব্যাচে ২২.০, রোল BATCH_STORE।
  - Order-to-Order: একই অর্ডার আটকায়; ৬০ / ৪০ একই স্টোরে।
  - Leftover → Reuse: অর্ডার আবশ্যক; ২০ লেফটওভারে, তার ১৫ অন্য অর্ডারে ফেরত।
  - Batch → Leftover: ব্যাচের নয় এমন লাইন আটকায়; রোল পুরোটা (২২.০, ১ রোল), ব্যাচ নম্বর ছাড়া লাইনে, রোল LEFT_OVER।
  - Racking Board: র‍্যাক, সুতার filter (Vortex → ফাঁকা), Lot = কার্ডের সুতার লট।
- **নিজে ব্রাউজারে যাচাই (Grey চলাচল, dev, ডাটা রাখা আছে):**
  - **CCT-2026110001** Collar, LPP / SS27-TEE01, KC-2026100001/3 (২২.৯) → Fabric store, GRY-CC-1101। খালি সেভে মেসেজ দেখা।
  - **GBT-2026110001** KC-2026100001/2 → ব্যাচ B-1101, গোনা ওজন ২১.৩।
  - **OOT-2026110001** Rib-এর ১০ kg LPP-2026-000001 → FLOW STYLE-01 (MER-REQ-311)।
  - **GLO-2026110001** Rib-এর ১২ kg Fabric store → Main Store; **GRU-2026110001** তার ৫ kg Main Store-এ FLOW STYLE-01-এ ফেরত।
  - **BLO-2026110001** B-1101-এর রোল KC-2026100001/2 → Main Store।
  - সব Submit → Receive। Racking Board-এ ১০টা লাইন স্টোর ধরে, "Vortex"-এ ০।
  - প্রিন্টের preview দেখা; Collar/Batch/Order-এর প্রিন্টে অর্ডারের লেবেল "From Order#" করা হয়েছে।

## ১০. খোলা প্রশ্ন

- **Grade-এর সীমা:** Brickland-এর দুটো বিন্দু দেখা গেছে (৩১.৩৯ → B, ৫৫.৮১ → C)। A ≤ ২০, B ≤ ৪০, C ≤ ৬০, D > ৬০ ধরা হয়েছে, যা দুটোর সাথেই মেলে। Brickland-এ পুরনো শিট খোলার সময় তাদের পেজ ভেঙে যাওয়ায় সীমা আর যাচাই করা যায়নি।
- **Defect-এর গ্রুপ:** Brickland-এর প্রিন্টে শুধু Hole/Crack-এর গ্রুপ (Structural) দেখা গেছে। বাকিগুলোর গ্রুপ (Yarn, Machine, Stain) আমাদের ধারণা।
- **"To Hole" বোতাম:** Brickland-এর ইন্সপেকশন তালিকায় Pass, Hold, Reject-এর পাশে "To Hole" নামে একটা বোতাম আছে। নামটা অর্থহীন (সম্ভবত "To Hold"-এর ভুল), আর Hold আলাদাই আছে, তাই রাখা হয়নি।
- **Job#/W.Style আর Style:** Brickland-এর ফর্মে Style আলাদা select। এখানে অর্ডার থেকে Style আর Buyer নিজে থেকে দেখায়।
- **Label Info:** Brickland-এর Roll List-এ কলামটা আছে, কিন্তু কোথাও লেখার ঘর নেই। এখানে কলাম রাখা আছে ("—")।
- **Shift:** Brickland রোল আর ইন্সপেকশনে HR work schedule দেখায়। এখানে 15/01-এর capacity plan-এর `shifts` নেওয়া হয়েছে, যাতে Daily Output-এর Target শিফট ধরে মেলে।
- **S/C নম্বর:** Brickland প্রোগ্রামে "SCP-26100001" (YYMM) আর রিসিপ্টে "SCR-2026100001" (YYYYMM) লেখে। এখানে দুটোই আমাদের নিয়মে PREFIX-YYYYMM####।
- **S/C-তে Cancelled:** Brickland-এর প্রোগ্রাম রেজিস্টারের Status filter-এ Cancelled আছে, কিন্তু কোনো বোতাম নেই। এখানেও তাই: অবস্থা আছে, বোতাম নেই।
- **IsTrnsf? আর Received:** সুতা ট্রান্সফার আর S/C রিসিভ ডেস্ক (15/05) এখনো নেই, তাই IsTrnsf? সবসময় No আর Received ০।
- **Receipt-এর Yarn:** Brickland-এর Yarn আর Brand select শুরুতে বন্ধ থাকে। এখানে Yarn Store বাছলে সেই স্টোরের লট থেকে Yarn (composition + count) আর Brand আসে; Lot# হাতে লেখা। Fab. Ord. SL Brickland-এ সংখ্যার ঘর, এখানে grid-এ থাকা সিরিয়াল থেকে select, যাতে ভুল সিরিয়ালে সুতা না বসে।
- **Yarn test-এ Brickland-এর demo ফাঁকা:** Brickland-এ Yarn Item select সবসময় বন্ধ আর খালি (তাদের yarn item তালিকা ভরা হয়নি), তাই সেখানে কোনো test order তোলা যায়নি; বাকি তিন স্ক্রিনেও ডাটা নেই। ফর্ম, লেবেল আর লেখা Brickland থেকে হুবহু; Yarn Item আর Brand এখানে স্টোরের লট থেকে।
- **Test order-এর বোতাম:** Brickland-এর তালিকায় কোনো সারি না থাকায় বোতাম দেখা যায়নি। তাদের লেখা ("The store issues against it from the register", "The register's Knit Card # button prints it") থেকে Knit Card #, Issue আর Cancel রাখা হয়েছে। Issue শুধু অবস্থা বদলায় — সুতা আসলে বের হয় 15/03-এর Store Requisition (Req. Type Yarn Test) দিয়ে।
- **Register-এর কলাম আর Basis:** Brickland-এ সারি না থাকায় কলাম দেখা যায়নি; এখানে লাইনের ফিল্ড থেকে বানানো। Brickland বলে "The Basis column says which" — Basis কলাম রাখা হয়েছে।
- **Waiver-এর ফলাফল:** টেস্ট ছাড়া ছাড় "Passed" হিসেবে রাখা হয়েছে, যাতে Status filter (Pending, Passed, Failed, Conditional)-এ মেলে।
- **ফ্লোরের আর স্টোরের একই কাগজ:** Brickland-এ General Yarn Issue Indent আর Issue Against Requisition-এর (এবং দুটো Return স্ক্রিনের) তালিকা ফাঁকা, তাই একই টেবিল কিনা দেখা যায়নি। ফিল্ড আর অবস্থা হুবহু এক, আর স্টোর রিকুইজিশনের বিপরীতেই ইস্যু করে — তাই এখানে এক কাগজ, দুই দিক থেকে। Brickland-এর General Indent-এর খালি-Save মেসেজ ("A requisition with no yarn on it asks for nothing.") আলাদা, এখানে 15/03-এর মেসেজই আসে।
- **General Indent-এর Status:** Brickland-এ Status* select আছে। অবস্থা স্টোরের ইস্যু থেকে হিসাব হয়, তাই এখানে শুধু দেখায়; হাতে নেওয়া সিদ্ধান্ত Closed টিক।
- **তালিকার বোতাম:** তিন তালিকাই Brickland-এ ফাঁকা, বোতাম দেখা যায়নি। General Indent-এ Close আর Cancel (15/03-এর মতো, Issue বাদে); Replacement-এ Submit, Issue আর Cancel — Brickland-এর লেখা "Submit and Issue are on the register" থেকে।
- **Sample plan-এর Registered Yarn:** Brickland-এ এই select বন্ধ আর খালি, তাই তাদের demo-তে Submit Requisition কাজ করে না (কোনো মেসেজ ছাড়াই Draft থাকে)। এখানে নিবন্ধিত সুতা স্টোরের লট থেকে, আর জমা হলে সত্যিই 15/03-এর রিকুইজিশন ওঠে। Issued আর Closed রিকুইজিশন থেকে পড়া — কারণ "Any requisition already raised against it stays with the store and has to be closed there"।
- **Direct Booking-এর Buyer A/C:** Brickland-এ ঘরটা বন্ধ আর খালি (তবু * চিহ্ন), তাদের demo-তে বায়ার ছাড়াই সেভ হয়। এখানে Buyer A/C সত্যিই আবশ্যক, অর্ডার বাছলে নিজে থেকে ভরে, আর অর্ডার সেই বায়ারের হতে হবে।
- **Direct Booking-এর Submit:** Brickland-এ Submit জিজ্ঞেস করে না, Approve করে — হুবহু রাখা হয়েছে।
- **Yarn Twisting-এ Brickland-এর demo ফাঁকা:** Yarn select সবসময় বন্ধ, তাই কোনো প্রোগ্রাম সেভ হয় না; তালিকার বোতাম দেখা যায়নি। এখানে প্রোগ্রামের তালিকায় শুধু Print (খোলা হয় নম্বরে ক্লিক করে); অবস্থা ফর্মে বাছা হয়, Brickland-এর ফর্মের মতো।
- **Transfer? আর স্টক:** "The twisted yarn has been taken onto the store's own stock" — এখানে আপাতত শুধু চিহ্ন, স্টকে পোস্ট হয় না। প্রোগ্রামের সুতা স্টোরের নির্দিষ্ট লট থেকে বেরোয় না (লাইনে শুধু Lot # লেখা), তাই ফেরত টুইস্ট সুতা যোগ করলে স্টক দুবার গোনা হতো। সিঙ্গেল সুতা বের হওয়া আর টুইস্ট সুতা ঢোকা দুটোই স্টকে আনতে হলে প্রোগ্রাম লাইনকে লটের সাথে জুড়তে হবে — ব্যবহারকারীর সিদ্ধান্ত লাগবে।
- **গ্রহণের সীমা:** Brickland বলে "what the party was sent is what they were sent"; এখানে কোনো প্রোগ্রাম লাইনের বিপরীতে মোট Grey পাঠানোর বেশি হতে পারে না, আর Receive Grey-এর বেশি নয়।
- **Machine Loading আর Operator-এ Brickland-এর demo ফাঁকা:** কোনো স্টেশন বা পরিকল্পিত শিফট নেই, তাই গ্রিড বা স্টেশনের ভেতর দেখা যায়নি। লেবেল আর লেখা Brickland থেকে; গ্রিড আর dialog তাদের লেখা ("Click a cell to plan it, or clear it…", "one card per shift") থেকে বানানো।
- **শিফট কলাম:** গ্রিডে কোম্পানির সব শিফট কলাম হয়। dev-এ দুটো "Day shift" আছে (দুই ফ্যাক্টরির), তাই দুটো DAY কলাম দেখায়; ফ্লোর বা ফ্যাক্টরি ধরে শিফট ছাঁকা লাগলে পরে যোগ করা যায়।
- **Grey Fabric Racking Board:** Brickland-এর স্ক্রিনে সুতার racking-এর filter (Count, Composition, Spin/Cotton Type, Brand, Lot) আর cones-per-kg-এর লেখা। এখানে filter হুবহু, কিন্তু মেলানো হয় রোলের নিট কার্ডের yarn details লেখার সাথে (গ্রে রোলের নিজের কোনো সুতা-ফিল্ড নেই)। রোল র‍্যাকে যায় Store Receipt-এ; অন্য স্টোরে গেলে র‍্যাক থাকে না, সেখানে র‍্যাক বসানোর স্ক্রিন Brickland-এ দেখা যায়নি।
- **To Batch:** Brickland-এ ডাইং মডিউলের চলমান ব্যাচ থেকে select ("Only batches that have not finished are offered")। ডাইং মডিউল এখনো নেই, তাই এখানে ব্যাচ নম্বর লেখার ঘর; ব্যাচের লাইন একই স্টোরে ব্যাচ নম্বরসহ আলাদা। ডাইং তৈরি হলে select-এ বদলাবে।
- **Collar/Cuff-এর রোল:** Brickland-এর demo-তে picker-এ সাধারণ নিটিং রোলও আসে, তাই এখানেও From Order-এর ফ্লোরের যেকোনো রোল (শুধু Flat Bed RIB নয়)।
- **Collar/Cuff-এর অর্ডারের লেবেল:** Brickland-এর তালিকার কলাম দেখা যায়নি (ফাঁকা); এখানে ফর্মের ঘর থেকে।
- **Y/D প্রোগ্রাম কোথায় তৈরি হয়:** Brickland-এ তৈরির স্ক্রিন নেই (Register শুধু revision রাখে, আর Party Y/D S/C programme Y/D তালিকায় আসে না)। ব্যবহারকারী Register-এই তৈরি বেছে নিয়েছেন (২০২৬-১১-০১)। Brickland Party (Y/D) S/C programme-এ "SCP-YD-…" উপসর্গ দেয়; আমাদের S/C programme সব ধরনেই SCP-…। পরে মেলানো যেতে পারে।
- **Brickland probe:** ২৯ অক্টোবর কিছুক্ষণ এই কম্পিউটারের browser থেকে Brickland-এ connection reset আসছিল, পরে নিজে থেকে ঠিক হয়ে যায়। Brickland demo-তে probe-এর জন্য একটা Bulk order, KP-2026100001, KC-2026100001, দুটো রোল আর দুটো ইন্সপেকশন (QCI-2026100001/2) তৈরি করা হয়েছে।

## ১১. As-built নোট

**আংশিক (২০২৬-১১-০১):** ৩২টার ২৪টা স্ক্রিন — চারটা Fabric Production Order, Manual Roll Production Entry, Daily Knit Fabric Output, Grey Roll Quality Inspection, তিনটা Sub-Contract Knit Programme, Sub-Contract Programme Receipt, Knit Plan & Yarn Test Indent, Yarn Test Result Register, Production Test Clearance, Approval Without Testing, General Yarn Issue Indent, Yarn Return to Yarn Store, Supplier Yarn Replacement Indent, Solid / Y/D Sample Knit Plan, Direct Knit Fabric Booking, Yarn Twisting Entry, Knit Machine Loading Plan, Operator Roll Production Entry।

- **Brickland probe (kp01–kp16):** register, তিন ধাপের ফর্ম, KP আর Knitcard-এর grid আর dialog, Roll Weight Entry-র লাইন, Roll List, Inspection-এর ফর্ম, তালিকা আর দুটো প্রিন্ট। সাথে Category-র ১২টা আর Defect-এর ২৫টা অপশন।
- **ধারা:** অর্ডার → KP → KC → রোল → ইন্সপেকশন। রেজিস্টারের প্রতিটা সংখ্যা নিচের ধাপগুলোর যোগফল, কোথাও হাতে লেখা নয়।
- **S/C ভাগ (sc01–sc11 probe):** তিন প্রোগ্রাম স্ক্রিনের register, ফর্ম, confirm dialog আর দুটো প্রিন্ট; Receipt-এর ফর্ম, তালিকা, dialog আর প্রিন্ট। Brickland demo-তে SCP-26100001 (Approved) আর SCR-2026100001 (In Production) তৈরি করা হয়েছে।
- **Yarn test ভাগ (yt01–yt04 probe):** চার স্ক্রিনের ফর্ম, তালিকা, লেবেল, লেখা আর picker-এর অপশন (Req. Type-এর ২২টা, কর্মী, স্টোর, Color Group, Supplier)। Brickland-এ সেভ সম্ভব হয়নি (Yarn Item বন্ধ), তাই সেখানে কোনো ডাটা তৈরি হয়নি।
- **ফ্লোরের সুতার ভাগ (gy01 probe):** General Indent আর Replacement-এর ফর্ম, লেবেল আর লেখা; ১৫/০৩-এর দুই স্ক্রিনের সাথে তুলনা। Brickland-এর সব তালিকা ফাঁকা।
- **Sample আর Direct Booking ভাগ (sk01–sk06 probe):** ফর্ম, তালিকা, dialog আর দুটো প্রিন্ট। Brickland demo-তে SYR-S-2026100001 (Draft) আর DFB-2026100001 (Approved) তৈরি করা হয়েছে।
- **Yarn Twisting ভাগ (tw01–tw02 probe):** চার ট্যাবের ফর্ম, লেবেল, লেখা আর অপশন। Yarn select বন্ধ থাকায় Brickland-এ কিছু সেভ হয়নি।
- **মেশিন আর অপারেটর ভাগ (ml01 probe):** Loading Plan, Operator, Racking Board আর Y/D Register-এর লেবেল ও লেখা। Racking Board আর Y/D Register পিছিয়ে রাখা হয়েছে (খোলা প্রশ্ন দেখুন)।
- **Yarn dyeing ভাগ (yd01–yd05 probe, ২০২৬-১১-০১):** Register-এর দুই ট্যাব, Dyed Receive, Loss, Re-process (দুটো), S/C Yarn Receive Challan আর Statement-এর ফর্ম, লেবেল, লেখা আর অপশন (Shade Band = Color Group-এর তালিকা)। Brickland demo-তে সব তালিকা ফাঁকা; তাদের Statement-এর Category filter আসলে store item category, তাই রাখা হয়নি (এখানে সব সুতা)। Brickland demo-তে আগেই SCP-YD-26110001 (Party Y/D, Approved) তৈরি করা হয়েছিল।
- **Grey চলাচল ভাগ (gt01 probe, ২০২৬-১১-০১):** Flat Knit, Grey to Batch, Order-to-Order, Reuse, Leftover-এর ফর্মের লেখা, Rolls-এর কলাম (Roll, Fabric, Color, # Roll, Qty (Kg), Remarks) আর Racking Board-এর ফল। Brickland demo-তে সব তালিকা ফাঁকা, কিছু তৈরি করা হয়নি।
