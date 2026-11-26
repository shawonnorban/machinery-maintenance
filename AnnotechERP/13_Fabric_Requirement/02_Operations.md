# Operations

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Fabric Requirement](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `13/02`

**⬅ ফিরে যান:** [Fabric Requirement](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Planning Constraint Ruleset | `/m/fabric-planning/planning-constraints-rules` | 4 | 17 | 1 |
| 2 | Fabric Batch Plan Entry | `/m/fabric-planning/batch-planning` | 3 | 30 | 1 |
| 3 | Factory Calendar & Holidays | `/m/fabric-planning/factory-holiday-model` | 3 | 6 | 1 |
| 4 | Dye Machine Capacity Board | `/m/fabric-planning/dyeing-machine-capacity-dashboard` | 3 | 9 | 1 |
| 5 | Batch Schedule Board | `/m/fabric-planning/batch-scheduling` | 3 | 7 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Planning Constraint Ruleset

`/m/fabric-planning/planning-constraints-rules`

**ট্যাব: Records 16**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Every measured area across the six constraint families, with its weightin | search |  | Search 16 records… |
| Filter by Status Group | search |  |  |
| Filter by SL# | search |  |  |
| Filter by Code | search |  |  |
| Filter by Parent | search |  |  |
| Filter by Plan Status Area | search |  |  |
| Filter by WT Factor | search |  |  |
| Filter by Status | search |  |  |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Status Group, SL#, Code, Parent, Plan Status Area, WT Factor, Status, ACTION
- বাটন/অ্যাকশন: Batch Plan Status Areas, Batch Plan Status Rules, New plan status area, Export, Status Group, SL#, Code, Parent, Plan Status Area, WT Factor, Status, Edit

**ট্যাব: Batch Plan Status Areas**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search status groups | search |  | Search… |
- সেকশন: Status Group, Plan Status Areas
- বাটন/অ্যাকশন: Batch Plan Status Areas, Batch Plan Status Rules, New plan status area, 1. Critical Date 3 area(s), 5 rule(s) 8, 2. Capacity 3 area(s), 5 rule(s) 8, 3. Lead Time 2 area(s), 5 rule(s) 7, 4. Critical Path 3 area(s), 5 rule(s) 8, 5. BP Load 2 area(s), 5 rule(s) 7, 6. BP Performance 3 area(s), 5 rule(s) 8

**ট্যাব: Batch Plan Status Rules**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search status groups | search |  | Search… |
- সেকশন: Status Group, Plan Status Rules
- বাটন/অ্যাকশন: Batch Plan Status Areas, Batch Plan Status Rules, New plan status area, 1. Critical Date 3 area(s), 5 rule(s) 8, 2. Capacity 3 area(s), 5 rule(s) 8, 3. Lead Time 2 area(s), 5 rule(s) 7, 4. Critical Path 3 area(s), 5 rule(s) 8, 5. BP Load 2 area(s), 5 rule(s) 7, 6. BP Performance 3 area(s), 5 rule(s) 8

**ট্যাব: New plan status area**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status Group* | select | ✔ | Select…, 1. Critical Date, 2. Capacity, 3. Lead Time, 4. Critical Path, 5. BP Load, 6. BP Performance |
| SL#* | number | ✔ |  |
| Code* | text | ✔ |  |
| Plan Status Area* | text | ✔ |  |
| WT Factor* | number | ✔ |  |
| Is Active? | checkbox |  |  |
- সেকশন: New plan status area
- বাটন/অ্যাকশন: Batch Plan Status Areas, Batch Plan Status Rules, New plan status area, Select…, Reset, Save plan status area

**ডায়ালগ: New plan status area**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Status Group* | select | ✔ | Select…, 1. Critical Date, 2. Capacity, 3. Lead Time, 4. Critical Path, 5. BP Load, 6. BP Performance |
| SL#* | number | ✔ |  |
| Code* | text | ✔ |  |
| Plan Status Area* | text | ✔ |  |
| WT Factor* | number | ✔ |  |
| Is Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Status Group is required.

#### Fabric Batch Plan Entry

`/m/fabric-planning/batch-planning`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Batch Planning, New batch plan

**ট্যাব: Batch Planning**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Existing batch plan | select |  |  |
| BP Week* | select | ✔ | Select… |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| W.Style/Job# | text |  |  |
| Order # | text |  |  |
| Order Cat | text |  |  |
| Color Type | select |  | Select…, Solid, Melange, Yarn Dyed, All Over Print |
| Dye Method* | select | ✔ | Select…, Exhaust Dyeing, Cold Pad Batch, Continuous Dyeing, Pigment Dyeing |
| M/C No | select |  | Select… |
| Shipment Date | date |  |  |
| B.Confirm Date | date |  |  |
| Production Hr | number |  |  |
| Pending BP Qty | number |  |  |
| #Of Batch* | number | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Planned, Scheduled, Released, Cancelled |
- সেকশন: Batch Planning, Fabric Details
- বাটন/অ্যাকশন: Batch Planning, New batch plan, Select…, Add fabric line, Clear, Save batch plan

**ট্যাব: New batch plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| BP Week* | select | ✔ | Select… |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Color Type | select |  | Select…, Solid, Melange, Yarn Dyed, All Over Print |
| Dye Method* | select | ✔ | Select…, Exhaust Dyeing, Cold Pad Batch, Continuous Dyeing, Pigment Dyeing |
| M/C No | select |  | Select… |
| W.Style/Job# | text |  |  |
| Order # | text |  |  |
| Order Cat | text |  |  |
| Shipment Date | date |  |  |
| Booking Confirm Date | date |  |  |
| Production Hr | number |  |  |
| Pending BP Qty | number |  |  |
| #Of Batch* | number | ✔ |  |
| BP Qty* | number | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Planned, Scheduled, Released, Cancelled |
- সেকশন: New batch plan
- বাটন/অ্যাকশন: Batch Planning, New batch plan, Select…, Reset, Save batch plan

**ডায়ালগ: New batch plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| BP Week* | select | ✔ | Select… |
| Buyer A/C | select |  | Select…, LPP (LPP) |
| Color Type | select |  | Select…, Solid, Melange, Yarn Dyed, All Over Print |
| Dye Method* | select | ✔ | Select…, Exhaust Dyeing, Cold Pad Batch, Continuous Dyeing, Pigment Dyeing |
| M/C No | select |  | Select… |
| W.Style/Job# | text |  |  |
| Order # | text |  |  |
| Order Cat | text |  |  |
| Shipment Date | date |  |  |
| Booking Confirm Date | date |  |  |
| Production Hr | number |  |  |
| Pending BP Qty | number |  |  |
| #Of Batch* | number | ✔ |  |
| BP Qty* | number | ✔ |  |
| Status* | select | ✔ | Select…, Draft, Planned, Scheduled, Released, Cancelled |
- খালি Save-এ মেসেজ: BP Qty must be greater than zero.

#### Factory Calendar & Holidays

`/m/fabric-planning/factory-holiday-model`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Date & Holyday Type, New factory holiday

**ট্যাব: Date & Holyday Type**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search sections | search |  | Search… |
- সেকশন: Section, Closed Days
- বাটন/অ্যাকশন: Date & Holyday Type, New factory holiday, Accounts 0 closed day(s) 0, Admin 0 closed day(s) 0, Cutting 0 closed day(s) 0, Embroidery 0 closed day(s) 0, Finishing 0 closed day(s) 0, IE & Planning 0 closed day(s) 0, Knitting & Dyeing 0 closed day(s) 0, Maintenance 0 closed day(s) 0, Packing 0 closed day(s) 0, Pattern Making 0 closed day(s) 0, Production Staff 0 closed day(s) 0, Quality 0 closed day(s) 0, Sample 0 closed day(s) 0, Security 0 closed day(s) 0, Sewing 0 closed day(s) 0, Store 0 closed day(s) 0, Store Loader 0 closed day(s) 0

**ট্যাব: New factory holiday**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office* | select | ✔ | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Date* | date | ✔ |  |
| Holiday Type* | select | ✔ | Select…, Weekly Off, Govt Holiday, Company Holiday |
| Name | text |  |  |
- সেকশন: New factory holiday
- বাটন/অ্যাকশন: Date & Holyday Type, New factory holiday, Select…, Reset, Save factory holiday

**ডায়ালগ: New factory holiday**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Company* | select | ✔ | Select…, Brickland Composite Ltd. (BCL) |
| Office* | select | ✔ | Select…, Brickland Composite Ltd. Head Office (BR-0001) |
| Date* | date | ✔ |  |
| Holiday Type* | select | ✔ | Select…, Weekly Off, Govt Holiday, Company Holiday |
| Name | text |  |  |
- খালি Save-এ মেসেজ: Company is required.

#### Dye Machine Capacity Board

`/m/fabric-planning/dyeing-machine-capacity-dashboard`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Machines, New machine capacity

**ট্যাব: Machines**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search weeks | search |  | Search… |
- সেকশন: Week, Machine Capacity
- বাটন/অ্যাকশন: Machines, New machine capacity

**ট্যাব: New machine capacity**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Machine* | select | ✔ | Select… |
| Week* | select | ✔ | Select… |
| Capacity 100%* | number | ✔ |  |
| W.days* | number | ✔ |  |
| HoliDays* | number | ✔ |  |
| Max Load(%)* | number | ✔ |  |
| Capacity Hour* | number | ✔ |  |
| Avg Batch Quantity | number |  |  |
- সেকশন: New machine capacity
- বাটন/অ্যাকশন: Machines, New machine capacity, Reset, Save machine capacity

**ডায়ালগ: New machine capacity**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Machine* | select | ✔ | Select… |
| Week* | select | ✔ | Select… |
| Capacity 100%* | number | ✔ |  |
| W.days* | number | ✔ |  |
| HoliDays* | number | ✔ |  |
| Max Load(%)* | number | ✔ |  |
| Capacity Hour* | number | ✔ |  |
| Avg Batch Quantity | number |  |  |
- খালি Save-এ মেসেজ: Capacity 100% must be greater than zero.

#### Batch Schedule Board

`/m/fabric-planning/batch-scheduling`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Board, New Plan

**ট্যাব: Board**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search plans | search |  | Search… |
- সেকশন: Search Plan, Scheduled Batches
- বাটন/অ্যাকশন: Board, New Plan

**ট্যাব: New Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Plan Name* | text | ✔ |  |
| Company/Party | select |  | Select…, Brickland Composite Ltd. (BCL) |
| S/B* | select | ✔ | Select…, Shipment, Booking |
| Scale* | select | ✔ | Select…, Day, Week, Month |
| Cell Width(%)* | number | ✔ |  |
| Active | checkbox |  |  |
- সেকশন: New schedule plan
- বাটন/অ্যাকশন: Board, New Plan, Reset

**ডায়ালগ: New Plan**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Plan Name* | text | ✔ |  |
| Company/Party | select |  | Select…, Brickland Composite Ltd. (BCL) |
| S/B* | select | ✔ | Select…, Shipment, Booking |
| Scale* | select | ✔ | Select…, Day, Week, Month |
| Cell Width(%)* | number | ✔ |  |
| Active | checkbox |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/FabricPlanning` (13/01-এর পরে), সব নতুন টেবিল `fp_` নামে।
- **Dye House থেকে নেওয়া:** ডাই মেশিন (M/C No, Machine) আর কালার গ্রুপ।
- **Merchandise Desk থেকে নেওয়া:** বায়ার আর রঙ (`colours`)।
- **Company Management থেকে নেওয়া:**
  - Brickland-এর "Office" মানে ইউনিট (`factories`)। Knitting-এও একই ব্যবহার।
  - Brickland-এর "Section" মানে বিভাগ (`departments`), URL-এ তার কোড।
- **সপ্তাহ:** Brickland-এ সপ্তাহের কোনো master নেই, BP Week-এর ড্রপডাউন খালি থাকে। এখানে সপ্তাহ ক্যালেন্ডার থেকেই আসে:
  - শনি থেকে শুক্র, দেখায় `2026-W44 · 24-Oct → 30-Oct`।
  - নম্বরটা সেই সপ্তাহের সোমবারের ISO নম্বর।
  - ৮ সপ্তাহ পেছন থেকে ২৬ সপ্তাহ সামনে পর্যন্ত, সাথে আগে ব্যবহার হওয়া যেকোনো সপ্তাহ।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_28_000100_create_fabric_planning_operations`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `fp_status_groups` | sl, code, name — ৬টা স্থির পরিবার | company + sl |
| `fp_status_areas` | group_id, parent_id (একই গ্রুপের এরিয়া), sl, code (তৈরির পরে স্থির), name, wt_factor, active | company + code |
| `fp_status_rules` | group_id, score_from, score_to, status_code (তৈরির পরে স্থির), status_desc, color_code, green_rank, sort_order | group + status_code |
| `fp_holidays` | section_id (ফাঁকা মানে সব সেকশন), factory_id, day, day_type (WEEKLY_OFF/GOVT_HOLIDAY/COMPANY_HOLIDAY), name | — (কোডে: ইউনিট + সেকশন + দিন) |
| `fp_machine_capacities` | dye_machine_id, week_start, capacity_kg, working_days, holidays, max_load_pct, capacity_hours, avg_batch_qty | machine + week |
| `fp_batch_plans` | bp_no (BP-00001), week_start, buyer_id, style_job, order_no, order_cat, colour_type, colour_id, colour_group_id, dyeing_method, dye_machine_id, shipment_date, booking_confirm_date, production_hours, pending_qty, batches, bp_qty, status (DRAFT/PLANNED/SCHEDULED/RELEASED/CANCELLED), created_by | company + bp_no |
| `fp_batch_plan_lines` | batch_plan_id, fabric, grey_req, bp_plus, bp_minus, bp_qty, sort_order | — |
| `fp_schedule_plans` | plan_no (SP-00001), name, party_id (ফাঁকা মানে সব পার্টি), basis (SHIPMENT/BOOKING), scale (DAY/WEEK/MONTH), cell_width, active | company + plan_no |
| `fp_schedule_batches` | schedule_plan_id, batch_plan_id, dye_machine_id, planned_start, planned_end, sequence | plan + batch |

## ৫. Workflow / Status ও Business Rules

- **Planning Constraint Ruleset:**
  - **শুরুর ডাটা:** প্রথমবার খুললে Brickland-এর ডাটা তৈরি হয়: ৬টা পরিবার (Critical Date … BP Performance), ১৬টা এরিয়া (CD-SHIP … BF-ONTIME, Brickland-এর ওজনসহ), আর প্রতিটা পরিবারে ৫টা ব্যান্ড (CRITICAL ০–২০ … EXCELLENT ৮০.০১–১০০, Brickland-এর রঙ আর green rank)। এরপর সব কোম্পানির নিজের।
  - **এরিয়া:** কোড বড় হাতে রাখা হয়, কোম্পানিতে unique, আর তৈরির পরে বদলায় না। প্যারেন্ট হলে একই গ্রুপের অন্য এরিয়া। যে এরিয়া অন্য এরিয়ার প্যারেন্ট, সেটা সরানো যায় না। গ্রুপ না দিলে Brickland-এর মেসেজ আসে: "Status Group is required."।
  - **ব্যান্ড:** "পর্যন্ত" কখনো "থেকে"-র কম হতে পারে না, আর একই গ্রুপে দুটো ব্যান্ড একে অপরের ওপর পড়তে পারে না ("This band overlaps CRITICAL.")। রঙ `#RRGGBB` আকারে, green rank ০ থেকে ১।
  - **Status পিল:** green rank থেকে আসে: <০.৫ Poor, <০.৭৫ Watch, বাকি Good (Brickland-এর ৫টা ব্যান্ডের সাথে মেলে)।
- **Fabric Batch Plan Entry:**
  - **Batch Planning ট্যাব:**
    - অন্তত একটা ফ্যাব্রিক লাইন লাগে (Brickland-এর মেসেজ)।
    - Colour Group বাধ্যতামূলক।
    - BP Qty হলো লাইনগুলোর যোগফল, হেডারে আলাদা সংখ্যা পাঠালেও সেটা নেওয়া হয় না।
    - লাইনের Balance = Grey Req − BP Qty, লিখতে হয় না।
  - **New batch plan ট্যাব:** লাইন ছাড়া, BP Qty হাতে লিখতে হয়। শূন্য দিলে Brickland-এর মেসেজ: "BP Qty must be greater than zero."।
  - **উভয় ট্যাবে:**
    - সেভ করলে BP নম্বর তৈরি হয়।
    - তারিখ যেটাই দেওয়া হোক, তার সপ্তাহের শনিবারে বসে।
    - বাধ্যতামূলক: BP Week, Dye Method, #Of Batch (≥১), Status।
- **Factory Calendar & Holidays:**
  - একটা ইউনিট, সেকশন আর দিনে একটাই সারি থাকে।
  - New factory holiday-এর Company ঘর মানে লগইন করা কোম্পানি, Brickland যেভাবে চায়। না দিলে "Company is required."।
  - সেকশন ছাড়া ছুটি মানে সব সেকশনের ছুটি।
  - কাজের দিন মানে যে দিনের কোনো সারি নেই।
- **Dye Machine Capacity Board:**
  - একটা মেশিন আর সপ্তাহে একটাই সারি থাকে।
  - Capacity 100% শূন্যের বেশি হতে হয় (Brickland-এর মেসেজ)।
  - কাজের দিন + ছুটি ≤ ৭।
  - সর্বোচ্চ লোড (কেজি) = Capacity × Max Load %।
  - **Machines ট্যাবের প্ল্যান করা ব্যাচ আর কেজি:** সেই সপ্তাহে সেই মেশিনে বাতিল-না-হওয়া ব্যাচ প্ল্যান থেকে আসে।
  - **খালি (কেজি) আর লোড %:** সর্বোচ্চ লোডের বিপরীতে হিসাব হয়। ১০০%-এর বেশি হলে লাল, ৮৫% থেকে হলুদ।
- **Batch Schedule Board:**
  - সেভ করলে SP নম্বর তৈরি হয়।
  - Cell Width শূন্যের বেশি হতে হয়।
  - **ব্যাচ বসানো:**
    - একটা ব্যাচ বোর্ডে একবারই থাকে। আবার বসালে নকল না হয়ে আগের সারিটাই নতুন স্লটে সরে যায়, Brickland-এর নিয়মে।
    - বাতিল ব্যাচ বসানো যায় না।
    - শেষের তারিখ শুরুর আগে হতে পারে না।
    - মেশিন না দিলে ব্যাচ প্ল্যানের মেশিনটাই ধরা হয়।
  - সারির ক্রমই চলার ক্রম (↑ ↓ দিয়ে বদলায়)।

## ৬. API ও পারমিশন

- **স্ক্রিন-কিটের resource:** `/api/v1/erp/{fp-status-areas|fp-holidays|fp-machine-capacities|fp-schedule-plans}`। এরিয়া আর বোর্ড মোছা যায় না (Brickland-এ শুধু Edit), ছুটি আর ক্যাপাসিটি মোছা যায়।
- **নিজস্ব endpoint** (`FpOperationsApiController`):
  - `GET /fabric-planning/options`: সপ্তাহ, বায়ার, রঙ, কালার গ্রুপ, মেশিন, ইউনিট, সেকশন, গ্রুপ, কোম্পানি।
  - `GET /fabric-planning/status-groups`
  - `GET|PUT /fabric-planning/status-groups/{sl}/areas`
  - `GET|PUT /fabric-planning/status-groups/{sl}/rules`
  - `GET /fabric-planning/sections`
  - `GET|PUT /fabric-planning/sections/{code}/days`
  - `GET /fabric-planning/capacity-weeks`
  - `GET /fabric-planning/capacity-weeks/{week}`
  - `GET|POST /fabric-planning/batch-plans`
  - `GET|PUT /fabric-planning/batch-plans/{bp_no}`
  - `GET|PUT /fabric-planning/schedule-plans/{plan_no}/batches`
  - PUT মানে তালিকায় যা আছে তাই থাকে: নতুন সারি যোগ হয়, তালিকায় না থাকা সারি মুছে যায়।
- **পারমিশন:** নতুন `fabricplanning.plan.view` আর `fabricplanning.plan.manage`। দুটোই পায় FACTORY_ADMIN আর FACTORY_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: PLANNING → Fabric Requirement → OPERATIONS-এ ৫টা স্ক্রিন, Brickland-এর ক্রমে (04–08)।
- প্রতিটা স্ক্রিনে Brickland-এর তিনটা ট্যাব: Records, মাঝের ট্যাব, আর New।
- **মাঝের ট্যাব:** বাঁদিকে গ্রুপ (`GroupedRecords`, URL-এ `?group=` নম্বর বা কোড), ডানদিকে তার editor:
  - ব্যান্ড আর এরিয়ার ঘর জায়গায় বদলায়। Add/Remove, Export (CSV), Revert আর Save আছে।
  - সেভের পরে বাঁদিকের সংখ্যাও হালনাগাদ হয়।
- **Planning Constraint Ruleset:**
  - Records: সব এরিয়া, Edit একই জায়গায় খোলে।
  - Batch Plan Status Areas আর Status Rules ট্যাব।
  - **Rules-এর grid:** রঙ বাছার ঘর, আর green rank অনুযায়ী Poor/Watch/Good পিল।
- **Fabric Batch Plan Entry:**
  - Records থেকে Edit চাপলে `?tab=planning&bp=BP-00001` খোলে।
  - **Batch Planning:** আগের প্ল্যান বাছা (URL বদলায়, রিলোড ছাড়া), ১৬টা ঘর, ফ্যাব্রিক লাইনের সারণী (Balance আর মোট), Clear আর Save।
  - New batch plan: EntryForm।
- **Factory Calendar & Holidays:** সেকশনের তালিকা আর বন্ধ দিনের grid। "Close a day" dialog-এ Company, Office, Date, Holyday Type আর Name।
- **Dye Machine Capacity Board:** সপ্তাহের তালিকা। ডানদিকে মেশিনের সারণী: সর্বোচ্চ লোড, প্ল্যান করা আর খালি কেজি, লোড-বার।
- **Batch Schedule Board:** বোর্ডের তালিকা ("All parties · WEEK · Shipment")। ব্যাচের grid-এ ক্রম, মেশিন, শুরু আর শেষের তারিখ, ↑ ↓, Remove। সাথে "Place batch on board" dialog।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/FabricPlanning/Database/Migrations/2026_12_28_000100_create_fabric_planning_operations.php`
- `app/Modules/FabricPlanning/Models/FpStatusGroup.php`
- `app/Modules/FabricPlanning/Models/FpStatusArea.php`
- `app/Modules/FabricPlanning/Models/FpStatusRule.php`
- `app/Modules/FabricPlanning/Models/FpHoliday.php`
- `app/Modules/FabricPlanning/Models/FpMachineCapacity.php`
- `app/Modules/FabricPlanning/Models/FpBatchPlan.php`
- `app/Modules/FabricPlanning/Models/FpBatchPlanLine.php`
- `app/Modules/FabricPlanning/Models/FpSchedulePlan.php`
- `app/Modules/FabricPlanning/Models/FpScheduleBatch.php`
- `app/Modules/FabricPlanning/Support/FpOps.php`
- `app/Modules/FabricPlanning/Resources/FabricOpsResource.php`
- `app/Modules/FabricPlanning/Resources/StatusAreaResource.php`
- `app/Modules/FabricPlanning/Resources/HolidayResource.php`
- `app/Modules/FabricPlanning/Resources/MachineCapacityResource.php`
- `app/Modules/FabricPlanning/Resources/SchedulePlanResource.php`
- `app/Modules/FabricPlanning/Http/Controllers/Api/FpOperationsApiController.php`
- `app/Modules/FabricPlanning/Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/fabric-requirement/[screen]/page.js`
- `frontend/src/app/(app)/fabric-requirement/[screen]/ops-screens.js`
- `frontend/src/app/(app)/fabric-requirement/[screen]/actions.js`
- `frontend/src/components/fabricplanning/ops-editors.jsx`
- `frontend/src/components/fabricplanning/batch-planner.jsx`
- `lang/en/fabricplanning.php`
- `lang/bn/fabricplanning.php`

**Tests**
- `tests/Feature/FabricPlanning/FabricPlanningOperationsTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`FabricPlanningOperationsTest` ২/২ (১০৩ assertion):**
  - **Ruleset:** ১৬টা এরিয়া আর ৫টা ব্যান্ড Brickland-এর মতো আসে।
    - গ্রুপ ছাড়া Brickland-এর মেসেজ আসে। কোড বড় হাতে হয় আর দুবার চলে না।
    - কোড বদলানো যায় না। অন্য গ্রুপের প্যারেন্ট চলে না।
    - প্যারেন্ট সরালে আটকায়। জায়গায় সেভ হয়।
    - ব্যান্ড ওভারল্যাপ করলে আর ভুল রঙে আটকায়। ৪টা ব্যান্ডে সেভ হয়।
  - **ছুটি:** Company ছাড়া Brickland-এর মেসেজ আসে। একই দিন দুবার চলে না। সেকশনের দিন সেভ, মোছা আর ভুল কোডে 404।
  - **Capacity:** শূন্য ক্যাপাসিটিতে মেসেজ আসে, ৮ দিনে আটকায়। সপ্তাহ শনিবারে বসে (W44)। সর্বোচ্চ লোড ১,৩৫০। একই সপ্তাহ দুবার চলে না।
  - **ব্যাচ প্ল্যান:** লাইন ছাড়া মেসেজ আসে, কালার গ্রুপ লাগে। BP-00001-এর পরিমাণ লাইনের যোগফল (৭৭৫), Balance ৫০ আর −৫। New ট্যাবে BP Qty লাগে (BP-00002)। হালনাগাদ হয়।
  - **Capacity board:** ৩টা ব্যাচ, ৬৫০ কেজি, খালি ৭০০, ৪৮%।
  - **বোর্ড:** SP-00001 তৈরি হয়। একই ব্যাচ দুবার চলে না, শেষ শুরুর আগে চলে না। ক্রম ঠিক থাকে আর মেশিন প্ল্যান থেকে আসে।
  - **পারমিশন:** স্টোর ম্যানেজার পায় না, ফ্যাক্টরি ম্যানেজার পায়।
- **পুরনো টেস্ট আবার:** FabricPlanningMasterTest, ApiGateCoverage, MenuAccess, RoleMatrix, AccessControlConfiguration সব পাস।
- **ব্রাউজার (dev, fu01–fu03, ডাটা রাখা আছে):**
  - **Ruleset:** Capacity গ্রুপে CP-FREE-এর ওজন ০.৬ করা আর CP-QUEUE যোগ (গ্রুপের সংখ্যা ৯ হয়)। New ট্যাবে BF-SHADE যোগ।
  - **ছুটি:** ১৬ ডিসেম্বর Victory Day, আর Dyeing Department-এ ০৬ নভেম্বর বন্ধ।
  - **Capacity আর ব্যাচ প্ল্যান:** DM-01-এর W44 ক্যাপাসিটি ১,৫০০ কেজি / ৯০%। BP-00001: LPP, SS27-TEE01, Dark, Exhaust, DM-01, ২টা লাইন, ৮৭৫ কেজি। Capacity board-এ দেখায় ২টা ব্যাচ, ৮৭৫ কেজি, খালি ৪৭৫, ৬৫%।
  - **বোর্ড:** SP-00001 "Week 44 dye floor"-এ BP-00001 বসানো।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **Ruleset-এর স্কোরিং:** এরিয়া আর ব্যান্ড শুধু সংজ্ঞা। কোন ব্যাচ প্ল্যানের কোন এরিয়ায় কত স্কোর, সেই হিসাবের স্ক্রিন Brickland-এ পাওয়া যায়নি। Dye House-এর ব্যাচ অংশ বা Reports (13/03) তৈরির সময় দেখা হবে।
- **ছুটি আর ক্যাপাসিটি:** Brickland বলে "ক্যাপাসিটি বোর্ড বন্ধ দিনগুলো সপ্তাহ থেকে বাদ দেয়", কিন্তু ফর্মে HoliDays ঘরটা হাতে লিখতে হয়। এখানেও হাতে লেখা। চাইলে পরে Knitting & Dyeing সেকশনের বন্ধ দিন থেকে ডিফল্ট বসানো যায়।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৫):**
- পাঁচটা স্ক্রিনই তৈরি, Brickland-এর ক্রমে (04–08)। Reports (09) হবে 13/03-এ।
- **শুরুর ডাটা:** Brickland-এর ruleset (৬টা গ্রুপ, ১৬টা এরিয়া, ৩০টা ব্যান্ড), প্রথমবার খোলার সময় তৈরি হয়। বাকি চারটা স্ক্রিন Brickland-এ খালি, এখানেও খালি শুরু হয়।
- **Brickland প্রোব (fr01–fr03):** লাইভ demo-তে একটা schedule বোর্ড তৈরি করা হয়েছে (SP-00001 "Annotech probe board")। ব্যাচ প্ল্যান বা ক্যাপাসিটি তৈরি করা যায়নি, কারণ সেখানে সপ্তাহ আর মেশিনের ড্রপডাউন খালি।
