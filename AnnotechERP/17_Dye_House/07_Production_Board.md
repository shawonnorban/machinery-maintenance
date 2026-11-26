# Production Board

> **স্ট্যাটাস:** ✅ সম্পন্ন (৮টার ৮টা স্ক্রিন)
> **মডিউল:** [Dye House](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `17/07`

**⬅ ফিরে যান:** [Dye House](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Bulk Batch Production | `/m/dyeing/batch-production` | 1 | 27 | 1 |
| 2 | Machine Wash Output | `/m/dyeing/machine-wash-production` | 1 | 9 | 1 |
| 3 | Manual Daily Bulk Production | `/m/dyeing/daily-batch-production-for-bulk-manual` | 1 | 0 | 0 |
| 4 | Direct Fabric Booking Entry | `/m/dyeing/direct-fabric-booking` | 2 | 19 | 1 |
| 5 | General Fabric Booking Entry | `/m/dyeing/general-fabric-booking` | 2 | 17 | 1 |
| 6 | Trims Dyeing Programme | `/m/dyeing/dyeing-program-for-trims` | 1 | 11 | 1 |
| 7 | Check Roll Parameter Setup | `/m/dyeing/check-roll-parameter` | 1 | 0 | 0 |
| 8 | Dyeing & Finishing Closing | `/m/dyeing/dyeing-and-finishing-close` | 1 | 8 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Bulk Batch Production

`/m/dyeing/batch-production`

**ট্যাব: Batch Production**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Batch Plan | search |  | Scan Barcode / search Batch#… |
| Existing production | select |  |  |
| Batch No | text |  | Scan Barcode |
| Production Date* | date | ✔ |  |
| Req No | select |  |  |
| Req Date | text |  |  |
| Lot | text |  |  |
| Weights | number |  |  |
| M/C No* | select | ✔ |  |
| Model/Combo* | text | ✔ |  |
| Color | text |  |  |
| Color Group | text |  |  |
| Buyer | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Lab-dip #* | text | ✔ |  |
| D Method | text |  |  |
| Liqur Ratio* | text | ✔ | 1:8 |
| Is By-Pass Check Roll? Skips the QC gate. A re-dye of goods already inspected do | checkbox |  |  |
| Scouring Whether the run included a scouring step before the dye bath. | checkbox |  |  |
| Remarks | textarea |  |  |
| M/C No | select |  |  |
| Load Time | datetime-local |  |  |
| Unload Time | datetime-local |  |  |
| Chk Roll Dt | datetime-local |  |  |
| Type | text |  | Dyeing |
| Status | select |  | Select…, Loaded, Running, Unloaded, Aborted |
- গ্রিড কলাম: Batch No, Lot, Qty(Kg)
- সেকশন: Batch Plan, Batch Production, Production, Batch Info, Floor, Batch History
- বাটন/অ্যাকশন: Batch Production, Add Load, Cancel, Back To List, Clear, Save production

**ডায়ালগ: Add Load**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Search Batch Plan | search |  |  |
| Existing production | select |  |  |
| Batch No | text |  |  |
| Production Date* | date | ✔ |  |
| Req No | select |  |  |
| Req Date | text |  |  |
| Lot | text |  |  |
| Weights | number |  |  |
| M/C No* | select | ✔ |  |
| Model/Combo* | text | ✔ |  |
| Color | text |  |  |
| Color Group | text |  |  |
| Buyer | text |  |  |
| Style # | text |  |  |
| Order # | text |  |  |
| Lab-dip #* | text | ✔ |  |
| D Method | text |  |  |
| Liqur Ratio* | text | ✔ |  |
| Is By-Pass Check Roll? Skips the QC gate. A re-dye of goods already inspected do | checkbox |  |  |
| Scouring Whether the run included a scouring step before the dye bath. | checkbox |  |  |
| Remarks | textarea |  |  |
| M/C No | select |  |  |
| Load Time | datetime-local |  |  |
| Unload Time | datetime-local |  |  |
| Chk Roll Dt | datetime-local |  |  |
| Type | text |  |  |
| Status | select |  | Select…, Loaded, Running, Unloaded, Aborted |
- খালি Save-এ মেসেজ: Find the batch first — pick one on the left.

#### Machine Wash Output

`/m/dyeing/machine-wash-production`

**ট্যাব: Machine Wash Production**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing wash production | select |  |  |
| Requisition No* | select | ✔ | Scan Requisition Barcode |
| Production Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Machine No* | select | ✔ |  |
| Remarks | textarea |  |  |
| KG | number |  |  |
| GM | number |  |  |
| MG | number |  |  |
- সেকশন: Machine Wash Production, Production, Requisition Info, Requisition Details
- বাটন/অ্যাকশন: Machine Wash Production, --Select Dept--, -- Select User --, Add Item, Cancel, -- Select Dyes/Chemical Item --, Clear, Save wash production

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing wash production | select |  |  |
| Requisition No* | select | ✔ | Scan Requisition Barcode |
| Production Date* | date | ✔ |  |
| Company* | select | ✔ | Brickland Composite Ltd. |
| Machine No* | select | ✔ |  |
| Remarks | textarea |  |  |
| KG | number |  |  |
| GM | number |  |  |
| MG | number |  |  |
- খালি Save-এ মেসেজ: Requisition No is required.

#### Manual Daily Bulk Production

`/m/dyeing/daily-batch-production-for-bulk-manual`

**ভিউ**
- সেকশন: Daily Bulk Batch Production
- বাটন/অ্যাকশন: Revert, Save Production

#### Direct Fabric Booking Entry

`/m/dyeing/direct-fabric-booking`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Prod. Type | select |  | All |
| Buyer A/C | select |  | All |
| Style/Ord# | search |  |  |
| Status | select |  | All, Draft, Submitted, Approved, Closed, Cancelled |
| Search Fabric booked straight against one order and sent to the floor, with what | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Req#, Req Type, Buyer A/C, Style/Ord#, Items, Qty(Kg), Remarks, ACTION
- বাটন/অ্যাকশন: Direct Fabric Booking, Clear, Add New, Export, Date, Req#, Req Type, Buyer A/C, Style/Ord#, Items, Qty(Kg), Remarks

**ট্যাব: Direct Fabric Booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing booking | select |  |  |
| Prod. Type* | select | ✔ | Bulk, Sample |
| Prog Date* | date | ✔ |  |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req Type | text |  | Dyeing |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Fabric Description | text |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Direct Fabric Booking, Key Information, Booking, Fabrics
- বাটন/অ্যাকশন: Direct Fabric Booking, -- Select --, Add Fabric, Cancel, -- Color --, Back To List, Clear, Save booking

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing booking | select |  |  |
| Prod. Type* | select | ✔ | Bulk, Sample |
| Prog Date* | date | ✔ |  |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req Type | text |  |  |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Fabric Description | text |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A booking with no fabric on it books nothing. Add at least one line.

#### General Fabric Booking Entry

`/m/dyeing/general-fabric-booking`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Prod. Type | select |  | All |
| Status | select |  | All, Draft, Submitted, Approved, Closed, Cancelled |
| Search The dye house's own booking sheet — fabric requested without naming a sin | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Req#, Req Type, Items, Qty(Kg), Remarks, ACTION
- বাটন/অ্যাকশন: General Fabric Booking, Clear, Add New, Export, Date, Req#, Req Type, Items, Qty(Kg), Remarks

**ট্যাব: General Fabric Booking**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing booking | select |  |  |
| Prod. Type* | select | ✔ | Bulk, Sample |
| Prog Date* | date | ✔ |  |
| Buyer A/C | select |  | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req Type | text |  | Dyeing |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Fabric Description | text |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: General Fabric Booking, Key Information, Booking, Fabrics
- বাটন/অ্যাকশন: General Fabric Booking, -- Select --, Add Fabric, Cancel, -- Color --, Back To List, Clear, Save booking

**ডায়ালগ: Add New**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing booking | select |  |  |
| Prod. Type* | select | ✔ | Bulk, Sample |
| Prog Date* | date | ✔ |  |
| Buyer A/C | select |  | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Req Type | text |  |  |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Fabric Description | text |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A booking with no fabric on it books nothing. Add at least one line.

#### Trims Dyeing Programme

`/m/dyeing/dyeing-program-for-trims`

**ট্যাব: Dyeing Program for Trims**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing trim programme | select |  |  |
| Prod. Type* | select | ✔ | Select…, Bulk, Sample |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Prog Date* | date | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Item | text |  | Neck tape 12mm |
| YDs | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- সেকশন: Dyeing Program for Trims, Key Information, Trims
- বাটন/অ্যাকশন: Dyeing Program for Trims, -- Select --, Add Trim, Cancel, -- Color --, Back To List, Clear, Save trim programme

**ডায়ালগ: Add Trim**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Existing trim programme | select |  |  |
| Prod. Type* | select | ✔ | Select…, Bulk, Sample |
| Buyer A/C* | select | ✔ | LPP (LPP) |
| Order# | select |  | LPP-2026-000007 — 1111, LPP-2026-000008 — 529, LPP-2026-000006 — 529, LPP-2026-000004 — 867MI, LPP-2026-000005 — 234234, |
| Prog Date* | date | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Submitted, Approved, Closed, Cancelled |
| Remarks | textarea |  |  |
| Item | text |  |  |
| YDs | number |  |  |
| Qty(Kg) | number |  |  |
| Remarks | text |  |  |
- খালি Save-এ মেসেজ: A programme with no trims on it dyes nothing.

#### Check Roll Parameter Setup

`/m/dyeing/check-roll-parameter`

**ভিউ**
> 59 of 74 Scaffold
- সেকশন: This screen is scaffolded, not built

#### Dyeing & Finishing Closing

`/m/dyeing/dyeing-and-finishing-close`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Type | select |  | All, Bulk, Sample |
| Buyer | select |  | All |
| Ship Month | select |  | All |
| Job#/W.Style | search |  |  |
| Order/Style# | search |  |  |
| Status | select |  | All, Running, Closed |
| Search Batches still open to dyeing and finishing, and what each was asked for | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Cat, Buyer A/C, Job#/W.Style/Order No, Order Type/Ship, Fab., Bk Rpt, Req Qty, ACTION
- বাটন/অ্যাকশন: Clear, Export, Cat, Buyer A/C, Job#/W.Style/Order No, Order Type/Ship, Fab., Bk Rpt, Req Qty
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **17/04-এর Batch Production:** `dye_productions` আর `dye_production_loads` টেবিল, `DyeProductionApiController` আর `BatchProduction` কম্পোনেন্ট। Bulk শিট এগুলোই ব্যবহার করে, শুধু `bulk` prop দিয়ে বাড়তি ঘর যোগ হয়।
- **17/05-এর M/C Wash রিকুইজিশন:** Machine Wash Output এর বিপরীতে লেখা হয়।
- **17/06-এর কেমিক্যাল লেজার:** ওয়াশ শিটে সাব-স্টোর আর সেন্ট্রাল স্টক এখান থেকে দেখায় (`ChemStore::itemBalance`)।
- **17/06-এর editor অংশ:** `Field`, `Group`, `EditorHead`, `EditorFoot`, `LinesCard`, `usePaper` আর `ChemRegister` এখন export করা। বুকিং আর ট্রিম editor এগুলোই ব্যবহার করে।
- **মাস্টার:** বায়ার, অর্ডার (স্টাইল · বায়ার), কালার, ডাই মেশিন, `departments`, কোম্পানির ইউজার আর `fabric_types`।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_01_30_000100_create_dye_production_board.php`:
- **`dye_productions`-এ নতুন ঘর:** bypass_check_roll আর scouring (boolean)। **`dye_production_loads`-এ:** check_roll_at।
- **`dye_wash_productions`:** requisition (ইউনিক, অর্থাৎ প্রতি রিকুইজিশনে একটা), production_date, company, department, machine, requisition_to (user), remarks। **লাইন:** item, kg, gm, mg, total_kg (= kg + gm/1000 + mg/1,000,000)।
- **`dye_fabric_bookings`:** kind DIRECT (DFB-YYYYMM####) বা GENERAL (GFB-…), prod_type BULK/SAMPLE, prog_date, buyer, order, req_type (ডিফল্ট Dyeing), status DRAFT/SUBMITTED/APPROVED/CLOSED/CANCELLED, remarks। **লাইন:** fabric, colour, qty_kg, remarks।
- **`dye_trim_programmes`:** TDP-YYYYMM####, prod_type, buyer, order, prog_date, status, remarks। **লাইন:** item, colour, yds, qty_kg, remarks।
- **`dye_check_roll_params`:** buyer আর fabric_type (ফাঁকা মানে সবার জন্য), gsm_tol_pct, width_tol_pct, shade_delta_e, shrink_length_pct, shrink_width_pct, remarks, active।
- **`dye_order_closings`:** order, scope (BULK/SAMPLE), remarks, closed_by। প্রতি অর্ডার আর scope-এ একটা।

## ৫. Workflow / Status ও Business Rules

- **Bulk Batch Production:**
  - বাঁ দিকের তালিকায় খোলা বাল্ক কার্ড থাকে। স্ক্যান বক্সে নম্বর লিখে Enter দিলে খোলে।
  - M/C No, Model/Combo আর Lab-dip # বাধ্যতামূলক (sample-এ নয়)। By-Pass Check Roll আর Scouring টিক, আর Remarks থাকে।
  - প্রতিটা লোডে Chk Roll Dt থাকে। প্রতি ব্যাচে দিনে একটা প্রোডাকশন। Duration দুই সময় থেকে হিসাব হয়।
  - Records ট্যাবে প্রতিটা প্রোডাকশন দেখায়: লোডের সংখ্যা, মোট সময় আর শেষ লোডের অবস্থা।
- **Manual Daily Bulk Production:** বাল্ক কার্ডের সব লোড এক গ্রিডে। শুধু লোড আর আনলোডের সময় লেখা যায়। আনলোড লোডের আগে হলে আটকায়। Save শুধু বদলানো সারি পাঠায়, Revert সব আগের মতো করে।
- **Machine Wash Output:**
  - শুধু M/C Wash রিকুইজিশন, যেটা বাতিল নয় আর যার প্রোডাকশন এখনো হয়নি।
  - রিকুইজিশন বাছলে তার মেশিন আর লাইন আগে থেকে বসে, আর নিচে Requisition Info দেখায়।
  - কোম্পানি, বিভাগ আর মেশিন বাধ্যতামূলক। প্রতিটা লাইনে মোট শূন্যের বেশি হতে হবে।
  - আবার সেভ করলে সেই প্রোডাকশনই বদলায়। প্রতি রিকুইজিশনে একটাই।
  - স্টক নড়ে না, কারণ ইস্যু হয় 10-Issue বা Chemical Store থেকে।
- **Fabric Booking আর Trims Programme:**
  - Direct বুকিং আর Trims-এ বায়ার আর স্টাইল লাগে, আর স্টাইল সেই বায়ারেরই হতে হবে। স্টাইল বাছলে বায়ার বসে।
  - General বুকিংয়ে অর্ডার লাগে না।
  - Status ডেস্ক ঠিক করে। Closed বা Cancelled হলে শুধু দেখা যায়।
  - বুকিংয়ের প্রতিটা লাইনে ফেব্রিক আর শূন্যের বেশি কেজি লাগে। ট্রিমের লাইনে গজ বা কেজি লাগে।
- **Check Roll Parameter:**
  - প্রতি বায়ার আর ফেব্রিকের ধরনে একটা প্যারামিটার। ফাঁকা রাখলে সবার জন্য।
  - GSM, প্রস্থ আর শেড বাধ্যতামূলক। শতাংশ ০–১০০, ΔE ০–২০।
- **Dyeing & Finishing Closing:**
  - অর্ডার আর scope ধরে একটা সারি: Cat, বায়ার, Job/W.Style/অর্ডার, অর্ডারের ধরন/শিপ, Fab. (ফেব্রিকের সংখ্যা), Bk Rpt (ডিরেক্ট বুকিংয়ের কেজি), Req Qty (ব্যাচের কেজি), ব্যাচের সংখ্যা, অবস্থা।
  - Close করলে অর্ডারের খোলা কার্ড CLOSED হয়, আর তাতে আর প্রোডাকশন লেখা যায় না (শিটেও না, Manual Daily গ্রিডেও না)।
  - কোনো লোড Loaded বা Running থাকলে আটকায়। একই অর্ডার দুবার ক্লোজ করা যায় না।

## ৬. API ও পারমিশন

- **নতুন পারমিশন:**
  - `dyehouse.production.view`, `dyehouse.production.manage` আর `dyehouse.production.close`: FACTORY_ADMIN আর FACTORY_MANAGER।
  - Bulk প্রোডাকশনের endpoint `dyehouse.batch.*` বা `dyehouse.production.*`, যেকোনোটা থাকলেই চলে।
- **Screen-kit resource:** `/api/v1/erp/dye-check-roll-params`
- **API:**
  - `GET /dyehouse/productions?scope=`, `GET|PUT /dyehouse/production-loads`
  - `POST /dyehouse/batches/{no}/production` (bulk-এর নিয়মসহ)
  - `GET /dyehouse/production-board/options`
  - `GET /dyehouse/wash-productions`, `GET|PUT /dyehouse/wash-productions/{reqNo}`
  - `GET|POST /dyehouse/fabric-bookings?kind=`, `GET|PUT /dyehouse/fabric-bookings/{no}`
  - `GET|POST /dyehouse/trim-programmes`, `GET|PUT /dyehouse/trim-programmes/{no}`
  - `GET|POST /dyehouse/df-closings`
- **মেনু:** Dye House-এর নতুন ফোল্ডার PRODUCTION BOARD, ৮টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `dye-house/[screen]`-এ, `prod-screens.js` থেকে। কম্পোনেন্ট `components/dyehouse/production-board.jsx` (`WashProductionEditor`, `ManualDailyGrid`, `ProgrammeEditor`) আর `batch-production.jsx` (`bulk` prop)।
- **Bulk Batch Production:** Records আর Batch Production ট্যাব। বাঁ দিকের তালিকার শিরোনাম "Batch Plan" (Brickland-এর মতো)।
- **Machine Wash Output, বুকিং আর Trims:** Records আর editor ট্যাব, "Existing …" select, সারিতে Edit (`?tab=entry&doc=…`)।
- **Manual Daily:** গ্রিড, বদলানো সারি হলুদ। আনলোড লোডের আগে হলে লাল "off before on" দেখায়। ২৫টা করে পাতা।
- **Check Roll Parameter:** Records আর Add New, Edit আর Delete একই পাতায়।
- **D&F Closing:** ফিল্টার (Type, Buyer, Ship Month, Job#/W.Style, Order/Style#, Status) আর গ্রিড। প্রতিটা সারিতে Close, নিশ্চিত করতে হয়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৮টার ৮টা স্ক্রিন):

**Backend**
- `app/Modules/DyeHouse/Database/Migrations/2027_01_30_000100_create_dye_production_board.php`
- `app/Modules/DyeHouse/Models/`: `DyeWashProduction`, `DyeWashProductionLine`, `DyeFabricBooking`, `DyeFabricBookingLine`, `DyeTrimProgramme`, `DyeTrimProgrammeLine`, `DyeCheckRollParam`, `DyeOrderClosing` (নতুন); `DyeProduction`, `DyeProductionLoad` (বদলানো)
- `app/Modules/DyeHouse/Http/Controllers/Api/DyeProductionBoardApiController.php` (নতুন); `DyeProductionApiController.php` (বদলানো: bulk-এর নিয়ম, index, loads, saveLoads, ক্লোজ হওয়া কার্ড আটকানো)
- `app/Modules/DyeHouse/Resources/DyeCheckRollParamResource.php`
- `app/Modules/DyeHouse/Routes/api.php`, `app/Modules/Erp/Support/ResourceRegistry.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/dyehouse.php`, `lang/bn/dyehouse.php` (বদলানো: `prod` গ্রুপ)

**Frontend**
- `frontend/src/app/(app)/dye-house/[screen]/prod-screens.js` (নতুন), `page.js`, `actions.js` (বদলানো)
- `frontend/src/components/dyehouse/production-board.jsx` (নতুন)
- `frontend/src/components/dyehouse/batch-production.jsx` (বদলানো: `bulk`), `chem-store.jsx` (বদলানো: অংশগুলো export, `ChemRegister`-এ rowKey আর `labelKey`)

**Test**
- `tests/Feature/DyeHouse/DyeHouseProductionBoardTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/DyeHouse/DyeHouseProductionBoardTest.php`):** test2 DB-তে পাস, ৬টা টেস্ট, ১৩৭টা assertion।
- `test_bulk_production_names_its_floor_fields_and_its_loads_are_timed_by_hand`:
  - M/C, Model/Combo আর Lab-dip না দিলে তিনটা বার্তা আসে।
  - By-Pass, Scouring আর Chk Roll Dt সেভ হয়। Records-এ ৫ঘ ৩০মি দেখায়।
  - Manual Daily-তে আনলোড লোডের আগে হলে আটকায়। নতুন সময় দিলে ৩৬০ মিনিট হয়।
- `test_machine_wash_output_records_the_dose_once_per_wash_requisition`:
  - Batch Program রিকুইজিশন আটকায়। শিটে সাব-স্টক ৪০ দেখায়।
  - বিভাগ না দিলে আর শূন্য ডোজে আটকায়।
  - ২ kg + ৪৫০ gm + ৫০০ mg = ২.৪৫০৫ kg। আবার সেভ করলে একই প্রোডাকশন বদলায়। এরপর অপশনের তালিকা থেকে রিকুইজিশনটা সরে যায়।
- `test_fabric_bookings_and_trim_programmes`:
  - Direct বুকিংয়ে বায়ার আর স্টাইল লাগে। অন্য বায়ারের স্টাইল দিলে আটকায়। লাইন না থাকলে বার্তা আসে।
  - নম্বর DFB-…0001 আর GFB-…0001। ক্লোজ হলে শুধু দেখা যায়।
  - ট্রিমে গজ বা কেজি লাগে। দুটো লাইনে মোট ২৩০০ গজ।
- `test_check_roll_parameters_one_per_buyer_and_fabric`: বাধ্যতামূলক ঘর, দুবার একই প্যারামিটার দিলে আটকায়, বায়ারভিত্তিক প্যারামিটার, ΔE সীমা।
- `test_closing_an_order_stops_its_production`:
  - গ্রিডে Req Qty ৩০০, Bk Rpt ৫০০ আর শিপের মাস দেখায়।
  - Running লোড থাকলে আটকায়। ভুল scope দিলে আটকায়।
  - আনলোডের পরে ক্লোজ হয়। দুবার ক্লোজ আটকায়।
  - ক্লোজের পরে শিট আর Manual Daily দুটোতেই প্রোডাকশন আটকায়। বাঁ দিকের তালিকা খালি হয়।
- `test_who_may_record_and_close`: FACTORY_MANAGER লিখতে পারে। STORE_MANAGER আর TECHNICIAN কিছুই পায় না।

**Regression:** `DyeHouseSampleTest` (৩টা, ৯৯টা) আর `MenuAccessTest` (৬টা, ৩৪টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/pb01`, `pb02`), dev অ্যাপে:**
- **Bulk:** B-26110011-এ প্রোডাকশন: DM-02, Body + Rib, LD-2611-004, 1:8, Scouring, Remarks। লোড ০৮:০০ থেকে ১৩:৩০, Chk Roll ১৪:০০, Unloaded। একটা খালি লোডও সেভ হয়েছে, কারণ Add Load চাপা হয়েছিল। Records-এ দেখা গেছে।
- **Manual Daily:** লোডের সময় ০৭:৩০ করা হয়েছে, আর DB-তে সেভ হয়েছে।
- **Machine Wash:** IR-DC-2026110003-এ Dyeing Department। রিকুইজিশনের Hydrogen ৬ kg নিজে বসেছে, তার সাথে Acetic ৫০০ gm যোগ করা হয়েছে। মোট ৬.৫ kg, Hydrogen-এর সাব-স্টক ২৮৮।
- **বুকিং আর প্রোগ্রাম:** DFB-2026110001 (SS27-TEE01, Black, ৪২০ kg, Submitted), GFB-2026110001 (Sample, ৩৫ kg), TDP-2026110001 (Neck tape, Black, ১৫০০ গজ / ৬.৫ kg)।
- **Check Roll Parameter:** সব বায়ার আর সব ফেব্রিকের জন্য: GSM ৫%, প্রস্থ ৩%, ΔE ১, সংকোচন ৫%।
- **D&F Closing:** LPP-2026-000001-এর Bulk আর Sample সারি দেখা গেছে। Close-এর প্রশ্ন খুলে "Keep" চাপা হয়েছে। dev-এর কোনো অর্ডার ক্লোজ করা হয়নি।
- **দেখার পরে ঠিক করা:**
  - নম্বরহীন সারিতে React key warning আসছিল। `ChemRegister` এখন refKey দিয়ে সারি চেনে।
  - Close-এর প্রশ্নে "LPP-2026-000001|BULK" দেখাচ্ছিল, এখন শুধু অর্ডার নম্বর দেখায়।
  - Bulk শিটের তালিকার শিরোনাম "Sample Batches" ছিল, এখন "Batch Plan"।
- **error:** ঠিক করার পরে ০। সব রেকর্ড dev-এ রাখা হয়েছে।

**প্রিন্ট:** Brickland-এর এই ৮টা স্ক্রিনে কোনো Print বাটন নেই।

## ১০. খোলা প্রশ্ন

১. **বুকিং আর প্রোগ্রামের নম্বর:** Brickland-এর demo-তে কোনো রেকর্ড নেই, তাই নম্বরের ফরম্যাট জানা যায়নি। আমরা DFB-, GFB- আর TDP- (সঙ্গে YYYYMM####) রেখেছি। আসল ফরম্যাট জানাবেন।

২. **D&F Closing-এর কলাম:** Fab., Bk Rpt আর Req Qty-র মানে ধরে নেওয়া হয়েছে: ফেব্রিকের সংখ্যা, ডিরেক্ট বুকিংয়ের কেজি আর ব্যাচের কেজি। ঠিক না হলে জানাবেন। ক্লোজ ফেরানোর (Reopen) কোনো উপায় রাখা হয়নি। দরকার হলে জানাবেন।

৩. **Machine Wash Output আর স্টক:** ওয়াশ প্রোডাকশন স্টক থেকে কিছু কমায় না, কারণ স্টক কমে 10-Issue বা Chemical Store-এর ইস্যু থেকে। প্রোডাকশনে দেওয়া পরিমাণ রিকুইজিশনের ইস্যু থেকে আলাদা হলে কী হবে (ফেরত বা বাড়তি ইস্যু), জানাবেন।

৪. **Check Roll Parameter-এর ঘর:** Brickland-এ স্ক্রিনটা Scaffold, শুধু বর্ণনা আছে ("GSM, width and shade tolerances")। আমরা বায়ার আর ফেব্রিকের ধরন ধরে GSM %, প্রস্থ %, শেড ΔE আর সংকোচন % রেখেছি। আর কোনো ঘর লাগলে জানাবেন। চেক রোলের QC (17/08) এই সীমা ব্যবহার করবে।

৫. **খালি লোড:** Batch History-তে মেশিন, সময় বা অবস্থা ছাড়া একটা লোডও সেভ হয়। এগুলো সেভের সময় বাদ দেওয়া হবে কিনা জানাবেন।

৬. **Production Board-এর পারমিশন কে পাবে:** এখন শুধু Factory Admin আর Factory Manager পায়। ফ্লোরের কোনো role (যেমন Line Chief) পেলে জানাবেন।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/pb01`):** ৮টা স্ক্রিনের ফর্ম, হিন্ট, পিকারের অপশন (বিভাগ, ইউজার, স্টাইল · বায়ার, কালার) দেখা হয়েছে। demo-তে কোনো রেকর্ড নেই।
- **Scaffold:** Check Roll Parameter Setup Brickland-এ বানানো হয়নি। আপনার সিদ্ধান্ত অনুযায়ী পুরোটা বানানো হয়েছে।
- **17/04-এর শিটই আবার ব্যবহার:** Bulk Batch Production আলাদা কোড নয়। একই কম্পোনেন্ট আর একই endpoint, শুধু `bulk` হলে নিয়ম আর ঘর বাড়ে।
