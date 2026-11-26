# Collar/Cuff

> **স্ট্যাটাস:** ✅ সম্পন্ন (৬টার ৬টা স্ক্রিন)
> **মডিউল:** [Knit Production](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `15/06`

**⬅ ফিরে যান:** [Knit Production](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Collar & Cuff Output Entry | `/m/knitting/collar-cuff-production` | 3 | 20 | 1 |
| 2 | Out-House Collar & Cuff Programme | `/m/knitting/collar-cuff-program-sc-out-house` | 2 | 13 | 1 |
| 3 | Solid Collar & Cuff Yarn Indent | `/m/knitting/yarn-req-collar-cuff-solid` | 2 | 12 | 1 |
| 4 | Yarn-Dyed Collar & Cuff Indent | `/m/knitting/yarn-req-collar-cuff-yd` | 2 | 12 | 1 |
| 5 | Bulk Collar & Cuff Store Receipt | `/m/knitting/collar-cuff-trans-recv-store-bulk` | 1 | 5 | 1 |
| 6 | Sample Collar & Cuff Store Receipt | `/m/knitting/collar-cuff-trans-recv-store-sample` | 1 | 5 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Collar & Cuff Output Entry

`/m/knitting/collar-cuff-production`

**ট্যাব: Style List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk, Sample, Develop |
| Buyer A/C | select |  |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Item | select |  | Collar, Cuff, Placket, Neck Rib, Tape |
| With closed requirements | checkbox |  |  |
- সেকশন: Search
- বাটন/অ্যাকশন: Style List 0, Collar & Cuff Production, New flat knit requirement, -- Select Month --, --Style --, Search, Reset

**ট্যাব: Collar & Cuff Production**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requirements | search |  | Search… |
- সেকশন: Flat Knit Requirement, Production
- বাটন/অ্যাকশন: Style List 0, Collar & Cuff Production, New flat knit requirement

**ট্যাব: New flat knit requirement**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bulk | radio |  |  |
| Sample | radio |  |  |
| Develop | radio |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style# | text |  | As production books it |
| Item* | select | ✔ | Select…, Collar, Cuff, Placket, Neck Rib, Tape |
| Description* | text | ✔ | e.g. 1x1 rib collar, 2 stripe tipping |
| Size | text |  |  |
| Gauge | text |  |  |
| Req In-house | number |  |  |
| Req S/C Qty | number |  |  |
| Remarks | textarea |  |  |
- সেকশন: New flat knit requirement
- বাটন/অ্যাকশন: Style List 0, Collar & Cuff Production, New flat knit requirement, -- Select --, --Style --, -- Select Month --, Reset, Save flat knit requirement

**ডায়ালগ: New flat knit requirement**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Bulk | radio |  |  |
| Sample | radio |  |  |
| Develop | radio |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Job/Work Style# | text |  |  |
| Item* | select | ✔ | Select…, Collar, Cuff, Placket, Neck Rib, Tape |
| Description* | text | ✔ |  |
| Size | text |  |  |
| Gauge | text |  |  |
| Req In-house | number |  |  |
| Req S/C Qty | number |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A requirement has to ask for something. Fill in Req In-house, Req S/C Qty, or both.

#### Out-House Collar & Cuff Programme

`/m/knitting/collar-cuff-program-sc-out-house`

**ট্যাব: Collar Cuff Program List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Program No | text |  | Search Program# |
| Program Status | select |  | Draft, Submitted, Approved, In Production, Closed, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Collar Cuff Program
- বাটন/অ্যাকশন: Collar Cuff Program List 0, Collar Cuff Program (S/C Out-house), -- Select --, Show, Add New, Reset

**ট্যাব: Collar Cuff Program (S/C Out-house)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing collar cuff programme | select |  |  |
| Program# | text |  | [Auto Gen] |
| Date* | date | ✔ |  |
| Delivery Date | date |  |  |
| CPI | text |  | e.g. 12 or 12/14 |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
- সেকশন: Collar Cuff Program (S/C Out-house), Program Information, Against, Notes, Program Details
- বাটন/অ্যাকশন: Collar Cuff Program List 0, Collar Cuff Program (S/C Out-house), -- Select --, --Style --, Add Part, Back To List, Clear, Save collar cuff programme

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing collar cuff programme | select |  |  |
| Program# | text |  |  |
| Date* | date | ✔ |  |
| Delivery Date | date |  |  |
| CPI | text |  |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Buyer A/C | select |  |  |
| Order# | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A programme with no parts on it places no work. Add at least one line.

#### Solid Collar & Cuff Yarn Indent

`/m/knitting/yarn-req-collar-cuff-solid`

**ট্যাব: Yarn Requisition For Collar And/Or Cuff**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search New Order/Kp | search |  | Order, job, work style… |
| Existing requisition | select |  |  |
| Ref# | text |  | [Auto Gen] |
| Requisition Date* | date | ✔ |  |
| Knit Plan# | text |  | As the plan is referenced |
| Collar Cuff Program# | select |  |  |
| Style/Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Job/Work Style#, Order#, Parts, Req (pcs), Load Plan
- সেকশন: New Order/Kp, Yarn Requisition For Collar And/Or Cuff, Knit Plan Info, Against, Draw From, Yarn List for Requisition
- বাটন/অ্যাকশন: Yarn Requisition List 0, Yarn Requisition For Collar And/Or Cuff, Issue Against Requisition — Solid, --Style --, -- Select --, Add Yarn, Back To List, Clear, Save requisition

**ট্যাব: Issue Against Requisition — Solid**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requisitions | search |  | Search… |
- সেকশন: Approved Requisition, Yarn Issued
- বাটন/অ্যাকশন: Yarn Requisition List 0, Yarn Requisition For Collar And/Or Cuff, Issue Against Requisition — Solid

**ডায়ালগ: Add Yarn**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search New Order/Kp | search |  |  |
| Existing requisition | select |  |  |
| Ref# | text |  |  |
| Requisition Date* | date | ✔ |  |
| Knit Plan# | text |  |  |
| Collar Cuff Program# | select |  |  |
| Style/Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Yarn Details is required.

#### Yarn-Dyed Collar & Cuff Indent

`/m/knitting/yarn-req-collar-cuff-yd`

**ট্যাব: Yarn Requisition For Collar And/Or Cuff (Y/D)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search New Order/Kp | search |  | Order, job, work style… |
| Existing requisition | select |  |  |
| Ref# | text |  | [Auto Gen] |
| Requisition Date* | date | ✔ |  |
| Knit Plan# | text |  | As the plan is referenced |
| Collar Cuff Program# | select |  |  |
| Style/Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- গ্রিড কলাম: Job/Work Style#, Order#, Parts, Req (pcs), Load Plan
- সেকশন: New Order/Kp, Yarn Requisition For Collar And/Or Cuff (Y/D), Knit Plan Info, Against, Draw From, Yarn List for Requisition
- বাটন/অ্যাকশন: Yarn Requisition List 0, Issue Against Requisition — Y/D, --Style --, -- Select --, Add Yarn, Back To List, Clear, Save requisition

**ট্যাব: Issue Against Requisition — Y/D**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search requisitions | search |  | Search… |
- সেকশন: Approved Requisition, Yarn Issued
- বাটন/অ্যাকশন: Yarn Requisition List 0, Issue Against Requisition — Y/D

**ডায়ালগ: Add Yarn**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer A/C | select |  |  |
| Search New Order/Kp | search |  |  |
| Existing requisition | select |  |  |
| Ref# | text |  |  |
| Requisition Date* | date | ✔ |  |
| Knit Plan# | text |  |  |
| Collar Cuff Program# | select |  |  |
| Style/Order | select |  | 529 · LPP-2026-000008, 1111 · LPP-2026-000007, 529 · LPP-2026-000006, 234234 · LPP-2026-000005, 867MI · LPP-2026-000004, |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Yarn Store (ST |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Yarn Details is required.

#### Bulk Collar & Cuff Store Receipt

`/m/knitting/collar-cuff-trans-recv-store-bulk`

**ট্যাব: Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan# | text |  | Search Challan# |
| Challan Type | select |  | Internal Transfer Challan, Delivery Challan, Return Challan, S/C Receive Challan |
| Status | select |  | Draft, Trn/Delv Challan Issued, Trn/Delv Challan Rcvd at Store, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Collar Cuff Transfer/Recieve at fabric Store (Bulk)
- বাটন/অ্যাকশন: Challan List 0, -- Select --, Show, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Part | select |  | Collar, Cuff, Placket, Neck Rib, Tape |
| Search Open Flat Knit Requirement | search |  |  |
| Existing challan | select |  |  |
| Challan# | text |  |  |
| Challan Type* | select | ✔ | Select…, Internal Transfer Challan, Delivery Challan, Return Challan, S/C Receive Challan |
| Chln Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Vehicle# | text |  |  |
| From Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Finished Fabri |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A challan with no parts on it moves nothing. Pick an order on the left, or add a line.

#### Sample Collar & Cuff Store Receipt

`/m/knitting/collar-cuff-trans-recv-store-sample`

**ট্যাব: Challan List 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Challan# | text |  | Search Challan# |
| Challan Type | select |  | Internal Transfer Challan, Delivery Challan, Return Challan, S/C Receive Challan |
| Status | select |  | Draft, Trn/Delv Challan Issued, Trn/Delv Challan Rcvd at Store, Cancelled |
| From Date | date |  |  |
| To Date | date |  |  |
- সেকশন: Collar Cuff Transfer/Recieve at fabric Store (Sample)
- বাটন/অ্যাকশন: Challan List 0, -- Select --, Show, Add New, Reset

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Part | select |  | Collar, Cuff, Placket, Neck Rib, Tape |
| Search Open Flat Knit Requirement | search |  |  |
| Existing challan | select |  |  |
| Challan# | text |  |  |
| Challan Type* | select | ✔ | Select…, Internal Transfer Challan, Delivery Challan, Return Challan, S/C Receive Challan |
| Chln Date* | date | ✔ |  |
| Gate Pass# | text |  |  |
| Vehicle# | text |  |  |
| From Floor | select |  | Cuting Floor — BCL, Finishing — BCL, Finishing — BCL, Packing — BCL, SWEING — BCL |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Store | select |  | Cutting Sub-Store (ST-CUT), GMT Sample Store (ST-SMP), Knitting Floor (ST-KNT), Packaging Store (ST-PKG), Finished Fabri |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: A challan with no parts on it moves nothing. Pick an order on the left, or add a line.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `KnitProduction::options()`: buyer, order, Prod. Category (FABRIC_BOOKING_CATEGORY lookup), fabric type, colour, floor, machine, shift, employee (operator), party। সবকিছু মাস্টার থেকে আসে।
- `YarnProcurement::options()`: store, yarn item (fiber composition), count।
- `YarnStore`: `lots(store)` স্টক লাইন আর balance দেয়, `post()` / `balance()` / `assertOpen()` দিয়ে ledger চলে। indent-এর issue এখানেই post হয়, kind `CC_YARN_ISSUE`।
- `YarnStore::REQ_TYPES` আর `knitting.ystore.req_type.*` লেবেল থেকে Requisition Type আসে, কপি করা হয়নি।
- `UomCodes`: UoM সবসময় মাস্টার থেকে।
- ফ্রন্টএন্ডের shared অংশ:
  - ystore-yd: `useShowFilters`, `useMoves`, `ListShell`, `MoveButton`, `FormHead`, `FormFoot`, `RemoveCell`।
  - prod-sc: `ScTable`।
  - prod-yd: `TONE`। এতে OPEN, IN_PRODUCTION, PARTLY_ISSUED যোগ করা হয়েছে।
  - `lib/print-document`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_18_000100_create_knitting_collar_cuff.php`।

**`knit_cc_requirements` (CCR-YYYYMM####): Flat Knit Requirement**
- req_type: BULK / SAMPLE / DEVELOP
- category: Prod. Category-র code
- buyer_id, order_id, work_style_no
- ship_month: YYYY-MM
- item: COLLAR / CUFF / PLACKET / NECK_RIB / TAPE
- description, colour_id, size_desc, gauge
- req_in_house, req_sc, uom (ডিফল্ট PCS)
- remarks
- status: OPEN / CLOSED, সাথে closed_at

**`knit_cc_productions`: Output Entry-র লাইন**
- requirement_id, prod_date
- source: IN_HOUSE / SC_OUT_HOUSE
- floor_id, machine_id, shift_id, operator_id (hr_employees)
- program_id: S/C Program#
- qty, reject_qty, remarks

**`knit_cc_programs` + `knit_cc_program_lines` (CCP-…): Out-House Programme**
- হেডার: program_date, delivery_date, party_id (mp_suppliers), cpi, buyer_id / order_id / work_style_no, remarks
- status: DRAFT / SUBMITTED / APPROVED / IN_PRODUCTION / CLOSED / CANCELLED, সাথে approved_at/by
- লাইন: item, description, colour_id, size_desc, gauge, qty, rate, amount (qty × rate), uom, remarks
- received কোথাও লেখা হয় না: স্টোরে গৃহীত S/C Receive challan-এর লাইন থেকে join করে আসে।

**`knit_cc_yarn_reqs` + `knit_cc_yarn_req_lines` (CYS- সলিড / CYD- ওয়াই/ডি): Yarn Indent**
- হেডার: kind SOLID / YD, req_date, knit_plan_no, program_id, buyer_id / order_id / work_style_no, store_id, req_type, remarks
- status: DRAFT / SUBMITTED / APPROVED / PARTLY_ISSUED / ISSUED / CLOSED / CANCELLED
- লাইন:
  - requirement_id (Load Plan থেকে আসে), buyer_id, order_id, fabric_type_id, colour_id
  - composition_id (Yarn Details, বাধ্যতামূলক), yarn_count_id, brand
  - lot_no: ওয়াই/ডি-তে বাধ্যতামূলক, বড় হাতের অক্ষরে রাখা হয়
  - req_qty, uom (ডিফল্ট KG), remarks

**`knit_cc_yarn_issues`: স্টোরের প্রতিটা release**
- req_id, req_line_id
- issue_no: `CYS-…/n`, অর্থাৎ requisition নম্বরের পরে কততম release
- issue_date, lot_id (yarn stock line), qty

**`knit_cc_challans` + `knit_cc_challan_lines` (CCB- বাল্ক / CCS- স্যাম্পল): Store Receipt**
- হেডার: kind, challan_type (INTERNAL_TRANSFER / DELIVERY / RETURN / SC_RECEIVE), challan_date, gate_pass_no, vehicle_no, from_floor_id, store_id, remarks
- status: DRAFT / ISSUED / RECEIVED / CANCELLED
- rcv_date: Receive at Store-এর দিন বসে।
- লাইন: requirement_id, item, description, colour_id, size_desc, order_id, qty, uom, remarks, program_line_id (শুধু S/C Receive-এ)

## ৫. Workflow / Status ও Business Rules

**Flat Knit Requirement**
- Req In-house আর Req S/C-র অন্তত একটা শূন্যের বেশি হতে হবে। Brickland-এর মেসেজটাই হুবহু দেখায়।
- Order বাছলে buyer, Job/Work Style# আর Shipment মাস নিজে থেকে বসে।
- Style List:
  - Type ফিল্টার ডিফল্ট Bulk।
  - বন্ধ requirement তখনই দেখায় যখন "With closed requirements" টিক দেওয়া।
  - সারিতে Close আর Edit বোতাম।
- বন্ধ requirement-এ আর কোনো বদল বা উৎপাদন নেওয়া হয় না।

**Collar & Cuff Production (ডেস্ক)**
- বামে খোলা requirement-এর তালিকা। একটা বাছলে ডানে তার লাইনগুলো আসে।
- Add Production ডায়ালগ:
  - ঘর: Date, Source, Floor, Machine, Shift, Operator, S/C Program#, Qty, Reject, Remarks।
  - সাথে সাথেই লাইন সেভ করে, Brickland-এর মতো।
- গ্রিডে লাইনগুলো সরাসরি বদলানো যায়। তারপর Save, বা Revert দিয়ে আগের অবস্থায় ফেরা।
- Remove দিয়ে লাইন মোছা যায়। Export দিলে CSV নামে।
- S/C Program# শুধু S/C Out-house উৎসের লাইনে দেওয়া যায়, আর প্রোগ্রামটাকে চালু (Approved বা In Production) থাকতে হবে।
- রিজেক্ট আলাদা থাকে, ভালো পিস থেকে বাদ দেওয়া হয় না।
- requirement-এর হিসাব:
  - Prod.In-house আর Prod. S/C আলাদা।
  - Open to Store = মোট উৎপাদন − চালু চালানে যা গেছে।
- যে পিস ইতিমধ্যে চালানে উঠেছে, তার নিচে উৎপাদন নামানো যায় না। Save বা Remove দুটোতেই আটকায়।

**Out-House Programme**
- অন্তত একটা পার্ট লাগে, আর প্রতিটা পার্টে পিস থাকতে হবে।
- ধাপ:
  - Draft → Submit → Approve, অথবা Submitted থেকে Send Back করলে আবার Draft।
  - Approved → Start → In Production → Close।
- Cancel করা যায় Draft, Submitted বা Approved অবস্থায়, যতক্ষণ কোনো চালানে পিস ওঠেনি।
- Received আসে স্টোরে গৃহীত S/C Receive লাইন থেকে।

**Store Receipt (বাল্ক / স্যাম্পল)**
- কোন requirement কোন স্ক্রিনে:
  - বাল্ক স্ক্রিনে BULK requirement।
  - স্যাম্পল স্ক্রিনে SAMPLE আর DEVELOP।
- বামে Open Flat Knit Requirement প্যানেল:
  - অর্ডার ধরে বাকি পরিমাণ দেখায়, Part দিয়ে ফিল্টার করা যায়।
  - Include Parts চাপলে বাকি পরিমাণসহ লাইন বসে যায়।
- কোনো লাইনে requirement-এর বাকির চেয়ে বেশি দেওয়া যায় না।
- Program Part দেওয়া যায় শুধু S/C Receive Challan-এ।
- ধাপ:
  - Draft → Issue → Receive at Store। এতে rcv_date আজকের দিন হয়।
  - Re-open এক ধাপ পেছনে যায়: Issued থেকে Draft, Received থেকে Issued (rcv_date মুছে যায়)।
  - Cancel করা যায় Draft বা Issued অবস্থায়।
- Receive করার সময় দেখা হয়, প্রোগ্রামের পার্টের পিস ছাড়িয়ে যাচ্ছে কিনা।

**Yarn Indent (সলিড / ওয়াই/ডি)**
- বামে New Order/Kp: যে অর্ডারের খোলা পার্ট আছে। Load Plan চাপলে:
  - অর্ডার, buyer আর Knit Plan# বসে।
  - প্রতিটা পার্টের জন্য একটা লাইন আসে, রং আর মন্তব্যসহ।
- Yarn Details বাধ্যতামূলক। ওয়াই/ডি লাইনে Lot#-ও বাধ্যতামূলক।
- ধাপ: Draft → Submit → Approve। এরপর issue চলতে থাকলে Partly Issued, তারপর Issued।
- Close করা যায় Approved বা Partly Issued অবস্থায়।
- Cancel করা যায় শুধু যদি কিছুই ইস্যু না হয়ে থাকে।
- Issue Against Requisition:
  - প্রতিটা লাইনের জন্য indent-এর স্টোরের একটা মিলে যাওয়া স্টক লাইন বাছতে হয় (item, count আর lot মিলতে হবে)।
  - ইস্যু লাইনের বাকি বা লটের স্টক ছাড়াতে পারে না।
  - বন্ধ মাসে ইস্যু করা যায় না।
  - প্রতিটা release `CYS-…/n` নামে yarn ledger-এ বিয়োগ হয়।

## ৬. API ও পারমিশন

সব route `/api/v1/knitting/prod/cc/...`-এর নিচে, মোট ২৯টা।

| কাজ | পথ |
|---|---|
| পিকার | `options` |
| requirement | `requirements` (index/store), `requirements/{no}` (show/update), `requirements/{no}/close` |
| production ডেস্ক | `requirements/{no}/productions` (GET / POST / PUT), `requirements/{no}/productions/{id}` (DELETE) |
| প্রোগ্রাম | `programs`, `programs/{no}`, `programs/{no}/{submit\|approve\|send-back\|start\|close\|cancel}` |
| challan | `challans/{bulk\|sample}`, `challans/{kind}/open?challan=`, `challans/{kind}/{no}`, `challans/{kind}/{no}/{issue\|receive\|reopen\|cancel}` |
| yarn indent | `yarn/plans`, `yarn/lots?store=`, `yarn/{solid\|yd}`, `yarn/{kind}/{no}`, `yarn/{kind}/{no}/issue`, `yarn/{kind}/{no}/{submit\|approve\|close\|cancel}` |

**পারমিশন**
- requirement, production, প্রোগ্রাম আর indent:
  - দেখা: `knitting.production.view`
  - বদলানো: `knitting.production.manage`
- Store Receipt: `knitting.fstore.view/manage` বা production-এর পারমিশন।
- Indent-এর issue: `knitting.ystore.manage` বা `knitting.production.manage`।

**মেনু**
- Knit Production-এর নিজস্ব ফোল্ডার `KNITTING.COLLAR_CUFF`, Fabric Store-এর পরে, Brickland-এর COLLAR/CUFF-এর মতো। আগে ভুল করে Fabric Store-এর ভেতরে বসানো ছিল, পরে ঠিক করা হয়েছে।
- তাতে ৬টা স্ক্রিন: CC_OUTPUT, CC_PROGRAM, CC_YARN_SOLID, CC_YARN_YD, CC_STORE_BULK, CC_STORE_SAMPLE।

## ৭. Frontend পেজ (লাইট থিম)

- কম্পোনেন্ট: `frontend/src/components/knitting/prod-cc.jsx`।
- স্ক্রিন রেজিস্ট্রি: `cc-screens.js`। page.js-এ `cc` translator যোগ হয়েছে।
- **Collar & Cuff Output Entry**: তিনটা ট্যাব।
  - Style List: ফিল্টার আর ScTable।
  - Collar & Cuff Production: বামে requirement, ডানে হিসাবের কার্ড আর inline-edit গ্রিড, সাথে Add Production modal।
  - New flat knit requirement: Edit করলে `?tab=requirement&ref=CCR-…`।
- **Programme**: List আর Entry।
  - Entry-তে অংশগুলো: Program Information, Against, Notes, Program Details গ্রিড।
  - ফুটারে Back To List, Clear, Save।
- **Yarn Indent**: List, Entry আর Issue Against Requisition ট্যাব।
  - Entry-র বাঁ পাশে New Order/Kp, তাতে Load Plan বোতাম।
  - Issue ট্যাবের বাঁ পাশে Approved Requisition, ডানে লাইনভিত্তিক Stock Line আর Issue Qty, নিচে Yarn Issued ইতিহাস।
- **Store Receipt**: List আর Entry।
  - Entry-র বাঁ পাশে Open Flat Knit Requirement, Part ফিল্টার আর Include Parts সহ।
- প্রিন্ট:
  - প্রোগ্রাম: Parts placed, Received, Rate, Amount, আর পার্টির সম্মতির সই।
  - চালান (View): Issued By / Carried By / Security / Received By।
  - Indent: যে তালিকায় Print আছে।
- radio গ্রুপ `RadioField`-এ বসানো (`<div role="radiogroup">`)। `<label>`-এর ভেতরে radio রাখলে সব ক্লিক প্রথম radio-তে চলে যেত, UI চেকে এটাই ধরা পড়েছিল।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৬টার ৬টা স্ক্রিন):

**Backend**
- `app/Modules/Knitting/Database/Migrations/2027_01_18_000100_create_knitting_collar_cuff.php`
- `app/Modules/Knitting/Models/KnitCcRequirement.php`, `KnitCcProduction.php`, `KnitCcProgram.php`, `KnitCcProgramLine.php`, `KnitCcYarnReq.php`, `KnitCcYarnReqLine.php`, `KnitCcYarnIssue.php`, `KnitCcChallan.php`, `KnitCcChallanLine.php`
- `app/Modules/Knitting/Support/KnitCollarCuff.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitCcApiController.php`
- `app/Modules/Knitting/Http/Controllers/Api/KnitCcYarnApiController.php`
- `app/Modules/Knitting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/knitting/prod-cc.jsx`
- `frontend/src/components/knitting/prod-yd.jsx` (TONE)
- `frontend/src/app/(app)/knit-production/[screen]/cc-screens.js`
- `frontend/src/app/(app)/knit-production/[screen]/page.js`

**Lang**
- `lang/en/knitting.php`, `lang/bn/knitting.php`: `cc.*` গ্রুপ, আর `prod.screens` / `prod.descriptions`-এ ৬টা স্ক্রিন

**Test**
- `tests/Feature/Knitting/KnittingCollarCuffTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `KnittingCollarCuffTest`, ২টা টেস্ট, ১০০টা assertion, test2 DB-তে পাস।
- **requirement থেকে স্টোর পর্যন্ত:**
  - পরিমাণ ছাড়া requirement আটকায়।
  - production ডেস্কে add আর batch save হয়। ভুল S/C প্রোগ্রাম আটকায়।
  - Open-এর বেশি চালান আটকায়। স্যাম্পল স্ক্রিনে বাল্ক requirement আটকায়।
  - চালানে ওঠা পিসের নিচে উৎপাদন নামানো আটকায়।
  - চালান Issue → Receive (rcv_date বসে), তারপর Re-open।
  - প্রোগ্রাম: Draft থেকে Start আটকায়। Submit → Send Back → Submit → Approve।
  - S/C Receive-এ Program Part দিলে received হয় ২০। পিস ঢোকার পর প্রোগ্রাম Cancel আটকায়। এরপর Start।
  - রেজিস্টারের হিসাব মেলে। Close করার পর উৎপাদন নেওয়া আর open প্যানেলে দেখানো বন্ধ।
- **yarn indent:**
  - Load Plan ঠিক অর্ডার আর পার্ট আনে।
  - Yarn Details না থাকলে আর ওয়াই/ডি-তে lot না থাকলে আটকায়।
  - Draft অবস্থায় issue আটকায়।
  - অন্য lot, বা লাইনের বেশি ইস্যু আটকায়।
  - ১০ kg ইস্যুতে Partly Issued হয়। ledger-এ −১০ kg (value −৪০), আর lot balance ২০।
  - Partly Issued অবস্থায় Cancel আটকায়। বাকি ১৫ ইস্যুর পরে Issued।

**MenuAccessTest:** ৬টা টেস্ট পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/cc01`–`cc08`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| CCR-2026110001 | Collar, SS27-TEE01; উৎপাদন ইন-হাউস ১২০ (রিজেক্ট ৩), S/C ৪০ (CCP-2026110001-এ) | Open |
| CCR-2026110002 | Cuff, ৩০০ পিস | Open |
| CCR-2026110003 | Placket, স্যাম্পল, ৩৬ পিস উৎপাদন | Open |
| CCP-2026110001 | Coats Bangladesh, CPI 12/14, Collar ১০০ × ৩.৫ | Approved |
| CCB-2026110001 | Internal Transfer, ১১০ পিস | Rcvd at Store |
| CCS-2026110001 | Delivery Challan, ৩৬ পিস | Issued |
| CYS-2026110001 | ২ লাইন ৩৫ kg; SQ-5501 থেকে ১২ kg ইস্যু | Partly Issued |
| CYD-2026110001 | DY-NB-2611, ৫ kg; পুরোটা ইস্যু | Issued |

**প্রিন্ট:** প্রোগ্রাম, বাল্ক চালান, সলিড আর ওয়াই/ডি indent, চারটারই preview দেখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **Yarn issue কোথা থেকে বিয়োগ হবে:** Brickland-এ "store যা ছেড়েছে তা টাইপ করুন" লেখা, কিন্তু কোন স্টক লাইন থেকে সেটা বলা নেই। এখানে প্রতিটা release indent-এর স্টোরের একটা মিলে যাওয়া স্টক লাইন থেকে yarn ledger-এ বিয়োগ হয় (`CC_YARN_ISSUE`), যাতে স্টক সত্যি থাকে।

২. **Store Receipt আর স্টক:** গ্রহণ করা চালান কোনো stock ledger-এ (যেমন grey store) পিস তোলে না। শুধু requirement-এর delivery আর প্রোগ্রামের received-এ গোনা হয়। কলার/কাফ স্টক রিপোর্ট চাইলে চালান থেকে হিসাব হবে।

৩. **Develop requirement কোন স্ক্রিনে:** স্যাম্পল স্ক্রিনে রাখা হয়েছে, বাল্কে নয়। Brickland-এ পরিষ্কার নয়।

৪. **Production লাইনের Status কলাম:** Brickland-এ এই কলামে উৎসের ব্যাজ (In-house / S/C) দেখায়, এখানেও তাই রাখা হয়েছে।

৫. **বন্ধ requirement আবার খোলা:** Brickland-এ বন্ধ সারির বোতাম দেখা যায়নি, তাই Re-open রাখা হয়নি।

৬. **Indent তালিকার বোতাম:** Brickland-এ তালিকা খালি ছিল, তাই Submit / Approve / Close / Cancel / Print অনুমান করে রাখা হয়েছে। Send Back রাখা হয়নি।

৭. **Requisition Type:** yarn store-এর মাস্টার তালিকা থেকে আসে। "Collar and/or Cuff (Bulk)" ছাড়া আলাদা ওয়াই/ডি ধরন সেখানে নেই।

## ১১. As-built নোট

- Brickland probe (`erp_crawler/plans/cc01`–`cc11`, demo-তে লিখে দেখা):
  - একটা requirement, তার production লাইন, একটা প্রোগ্রাম আর একটা challan তৈরি করে পুরো প্রবাহ দেখা হয়েছে: Submit → Approve → Start/Close, আর Issue → Receive at Store → Re-open।
  - প্রিন্ট পাতা দুটো: `/print/knit-collar-cuff-program/…` আর `/print/knit-collar-cuff-challan/…`।
  - indent সেভ হয়নি, কারণ demo-তে Yarn Details-এর তালিকা ফাঁকা।
- radio গ্রুপ প্রথমে `Field`-এর `<label>`-এর ভেতরে ছিল। ফলে Source আর Type-এর সব ক্লিক প্রথম radio-তে যাচ্ছিল। এখন `RadioField`-এ।
- next dev-এ stale কোড আর `/api/auth/login` 404 এসেছিল। frontend-next-cache volume মুছে আবার তোলায় ঠিক হয়েছে।
