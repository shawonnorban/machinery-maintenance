# Production Plan

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Capacity Planning](00_Index.md) · **ফেজ ৪:** প্ল্যানিং ও IE · **কী:** `12/02`

**⬅ ফিরে যান:** [Capacity Planning](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Projections | `/m/planning/projections` | 1 | 2 | 0 |
| 2 | Master Plan | `/m/planning/master-plan` | 1 | 3 | 0 |
| 3 | Master Plan List | `/m/planning/master-plan-list` | 1 | 5 | 0 |
| 4 | Plan vs Actual & Forecast | `/m/planning/plan-vs-actual` | 1 | 4 | 0 |
| 5 | Revision Request | `/m/planning/revision-request` | 1 | 3 | 0 |
| 6 | Daily Plan | `/m/planning/daily-plan` | 1 | 1 | 0 |
| 7 | Daily Plan List | `/m/planning/daily-plan-list` | 1 | 6 | 0 |
| 8 | Line Capacity View | `/m/planning/line-capacity-view` | 1 | 2 | 0 |
| 9 | Gantt / Timeline | `/m/planning/plan-timeline` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Projections

`/m/planning/projections`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Year 2025 2026 2027 2028 | select |  | 2025, 2026, 2027, 2028 |
| Search orders | text |  | Search… |
- গ্রিড কলাম: ORDER NO, BUYER, PO, STYLE, QTY (PCS), SHIPMENT DATE, STATUS, ACTION
- সেকশন: Projections — October 2026
- বাটন/অ্যাকশন: January, February, March, April, May, June, July, August, September, October 2, November 3, December, LPP-2026-000003, LPP-2026-000006

#### Master Plan

`/m/planning/master-plan`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| 1 Year 2025 2026 2027 2028 | select |  | 2025, 2026, 2027, 2028 |
| Shipment Month January February March April May June July August September Octob | select |  | January, February, March, April, May, June, July, August, September, October, November, December |
| Search plans | text |  | Search… |
- গ্রিড কলাম: PLAN NO, ORDER NO, BUYER, STYLE, QTY (PCS), SHIPMENT, LINES, SEWING, PCS / DAY, STATUS
- সেকশন: Master Plan List
- বাটন/অ্যাকশন: Select a confirmed order…, All, Draft, Awaiting, Locked, Revised

#### Master Plan List

`/m/planning/master-plan-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All, Locked |
| Shipment from | date |  |  |
| Shipment to | date |  |  |
| Search Every master plan by order, version and status, with its lines and sewing | search |  | Search 3 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Plan No, Ver, Order No, Buyer, Style, Qty (PCS), Lines, Daily Cap., Sewing Start, Sewing End, Shipment, Status, PLAN
- বাটন/অ্যাকশন: Clear, Export, Plan No, Ver, Order No, Buyer, Style, Qty (PCS), Lines, Daily Cap., Sewing Start, Sewing End, Shipment, Status

#### Plan vs Actual & Forecast

`/m/planning/plan-vs-actual`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search plans | text |  | Search… |
| From | date |  |  |
| To | date |  |  |
| Line All lines 02 13 14 LINE01 | select |  | All lines, 02, 13, 14, LINE01 |
- গ্রিড কলাম: ORDER, LINES, ORDER QTY, PRODUCED, REMAINING, ORIGINAL COMPLETION, CURRENT FORECAST, SHIPMENT, VARIANCE, EXTRA CAPACITY, STATUS
- গ্রিড কলাম: DATE, LINE, ORDER, PLAN TARGET, ACTUAL OUTPUT, VARIANCE, BALANCE QTY, REMARKS
- সেকশন: Original Plan → Daily Progress → Current Forecast, Plan vs Actual (Daily Progress)
- বাটন/অ্যাকশন: Locked plans 3, At Risk 0, Delayed 1, On Track 2, Ahead 0, Completed 0, Export

#### Revision Request

`/m/planning/revision-request`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Status | select |  | All |
| Search Requests to change a locked master plan, and what the planning manager de | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Requested, Plan No, Order No, Reason, Requested By, Status, Decided, Decision Note, Revised Version, ACTION
- বাটন/অ্যাকশন: Clear, Export, Requested, Plan No, Order No, Reason, Requested By, Status, Decided, Decision Note, Revised Version

#### Daily Plan

`/m/planning/daily-plan`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Select Date* | date | ✔ |  |
- গ্রিড কলাম: ORDER, LINE, MANPOWER, MACHINE, HOURS, EFFICIENCY, CAPACITY, TARGET, ACTUAL, STATUS
- সেকশন: Daily Plan Dashboard — 09-Oct-2026

#### Daily Plan List

`/m/planning/daily-plan-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date from | date |  |  |
| Date to | date |  |  |
| Line | select |  | All, 02, LINE01 |
| Status | select |  | All, Planned |
| Search Saved daily plans by day, order and line, with target, output and remarks | search |  | Search 2 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Order No, Master Plan, Line, Manpower, Machine, Hours, Efficiency, SMV, Daily Cap., Master Target, Daily Target, Typed Output, Status, Remarks, DAY
- বাটন/অ্যাকশন: Clear, Export, Date, Order No, Master Plan, Line, Manpower, Machine, Hours, Efficiency, SMV, Daily Cap., Master Target, Daily Target, Typed Output, Status, Remarks

#### Line Capacity View

`/m/planning/line-capacity-view`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Year 2025 2026 2027 2028 | select |  | 2025, 2026, 2027, 2028 |
| Month January February March April May June July August September October Novemb | select |  | January, February, March, April, May, June, July, August, September, October, November, December |
- গ্রিড কলাম: LINE, 1 Th, 2 Fr, 3 Sa, 4 Su, 5 Mo, 6 Tu, 7 We, 8 Th, 9 Fr, 10 Sa, 11 Su, 12 Mo, 13 Tu, 14 We, 15 Th, 16 Fr, 17 Sa, 18 Su, 19 Mo, 20 Tu, 21 We, 22 Th, 23 Fr, 24 Sa, 25 Su, 26 Mo, 27 Tu, 28 We, 29 Th, 30 Fr, 31 Sa, DAYS, UTIL., 13 FAC-001 › FLR-001 · 33 MP · 10 h · 65%
- সেকশন: Line Capacity — October 2026
- বাটন/অ্যাকশন: Utilisation %, Planned pieces

#### Gantt / Timeline

`/m/planning/plan-timeline`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Year 2025 2026 2027 2028 | select |  | 2025, 2026, 2027, 2028 |
| Month January February March April May June July August September October Novemb | select |  | January, February, March, April, May, June, July, August, September, October, November, December |
| Plans Locked and drafts Locked only Drafts only | select |  | Locked and drafts, Locked only, Drafts only |
| Search plans | text |  | Order, buyer, style, line… |
- সেকশন: Gantt / Timeline — October 2026
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- একই মডিউল `app/Modules/Planning` (12/01-এর মাস্টার ডাটার পরে), সব নতুন টেবিল `planning_` নামে।
- **12/01 থেকে নেওয়া:**
  - planning line-এর স্ট্যান্ডার্ড জনবল, ঘণ্টা আর এফিসিয়েন্সি।
  - Factory Calendar-এর কাজের দিন আর ছুটি।
  - SMV / SAM Master।
  - Other Setup-এর লিড টাইম।
- **Merchandise Desk থেকে নেওয়া:** নিশ্চিত অর্ডার (`merch_orders`), বায়ার, স্টাইল নং আর শিপমেন্টের তারিখ। SMV না পেলে স্টাইলের আইটেমের `smv_marketing` যোগফল।
- **HR & Payroll থেকে নেওয়া:** দৈনিক প্ল্যানের শিফট আসে HR-এর shift master (`hr_shifts`) থেকে। এটা Brickland-এর মতো: প্রতিটা লাইনে একই ST-MOR/GEN/EVE/NGT তালিকা, আর শিফট বাছলে তার কাজের ঘণ্টা বসে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_27_000100_create_planning_production_plans`:

| টেবিল | ফিল্ড | unique |
|---|---|---|
| `planning_master_plans` | plan_no, version, order_id, status (DRAFT/LOCKED/REVISED), smv, smv_source, sam, sam_source, direction (BACKWARD/FORWARD), forward_from, scenario, order_qty, allocated_qty, daily_capacity, required_days, sewing_start, sewing_end, ship_date, remarks, created_by, confirmed_by, confirmed_at | company + plan_no + version |
| `planning_master_plan_lines` | master_plan_id, planning_line_id, manpower, machines, hours, efficiency, hourly_capacity, daily_capacity, allocated_qty, sewing_start, sewing_end, days, sort_order | — |
| `planning_master_plan_days` | master_plan_id, planning_line_id, day, capacity, target | — |
| `planning_master_plan_activities` | master_plan_id, activity, duration, start_date, end_date, based_on, sort_order | — |
| `planning_revision_requests` | master_plan_id, reason, status (PENDING/APPROVED/REJECTED), requested_by, decided_by, decided_at, decision_note, revised_plan_id | — |
| `planning_plan_events` | plan_no, version, action, summary, user_id, created_at (প্রতিটা প্ল্যানের audit trail, ভার্সন মুছলেও থাকে) | — |
| `planning_daily_plans` | plan_date, order_id, master_plan_id, planning_line_id, shift_id (→ `hr_shifts`), manpower, machines, hours, efficiency, smv, daily_capacity, master_target, daily_target, typed_output, status (PLANNED/CONFIRMED), remarks, created_by | date + order + line |

## ৫. Workflow / Status ও Business Rules

- **ক্যাপাসিটি:**
  - ঘণ্টায় = floor(জনবল × ৬০ × এফিসিয়েন্সি ÷ ১০০ ÷ SMV)।
  - দিনে = floor(ঘণ্টায় × ঘণ্টা)।
  - Brickland-এর সংখ্যা মেলে: ৩৩ জন, ১০ ঘণ্টা, ৬৫%, SMV ৮.৫ হলে ১৫১ আর ১,৫১০।
- **SMV:** প্রথমে SMV / SAM Master (স্টাইল নং ধরে), না পেলে স্টাইলের আইটেমের SMV যোগফল। দুটোর কোনোটাই না থাকলে, বা শিপমেন্টের তারিখ না থাকলে, প্ল্যান করা যায় না।
- **Projections:**
  - শিপমেন্ট মাস ধরে নিশ্চিত অর্ডার (CONFIRMED বা IN_PRODUCTION)।
  - প্ল্যানের অবস্থা: Pending, Draft বা Locked। কোনো অর্ডারের লক করা ভার্সন থাকলে সেটাই দেখায়, তার ওপরে নতুন খসড়া খোলা থাকলেও।
- **Master Plan:**
  - পরিমাণ লাইনগুলোর মধ্যে ক্যাপাসিটির অনুপাতে ভাগ হয়, শেষ লাইন বাকিটা নেয়।
  - **Backward:** শেষ সেলাইয়ের দিন (শিপমেন্ট − ১) থেকে পেছনে, খালি কাজের দিনে, শুরু আগামীকাল থেকে।
  - **Forward:** বাছা তারিখ থেকে।
  - অন্য অর্ডারের লক করা প্ল্যানের দিন খালি ধরা হয় না।
  - দিনের টার্গেট পুরো ক্যাপাসিটি, শেষ দিনে বাকিটা।
  - **কাজের তারিখ (লিড টাইম থেকে):** কাটিং শেষ হয় সেলাই শুরুর দিন। ইন-হাউস কাটিংয়ের আগের দিন শেষ, বুকিং ইন-হাউসের আগের দিন শেষ। ফিনিশিং = সেলাই শেষ + ১। প্যাকিং শুরু হয় ফিনিশিং শেষের দিন। শিপমেন্ট অর্ডারের তারিখে।
  - **খসড়া থেকে লক:**
    - Save as Draft দিলে নম্বর হয় `MP-{শিপমেন্ট YYYYMM}{চলমান নং}` V1।
    - Confirm & Lock দিলে দিনগুলো আটকে যায়, আর আগের লক করা ভার্সন REVISED হয়।
    - লক করা প্ল্যান বদলায় না।
  - **সংশোধন:**
    - প্রক্রিয়া: অনুরোধ (কারণ বাধ্যতামূলক) → অনুমোদন → V+1 খসড়া, আগের ভার্সন থেকে কপি করা।
    - নতুন খসড়া লক না হওয়া পর্যন্ত আগের লক করা ভার্সনই চালু থাকে।
    - প্রত্যাখ্যানে নোট বাধ্যতামূলক।
- **Daily Plan:**
  - শুধু লক করা মাস্টার প্ল্যানের অর্ডার।
  - **দিনের লাইন:** সেভ করা থাকলে সেগুলো, না হলে মাস্টার প্ল্যানে সেই দিন যে লাইনগুলো আছে। জনবল, মেশিন, ঘণ্টা আর এফিসিয়েন্সি আসে মাস্টার প্ল্যানের লাইন থেকে, সেখানে না থাকলে লাইন মাস্টার থেকে।
  - **শিফট** বাছলে তার কাজের ঘণ্টা বসে। Brickland-এর মতো: ST-GEN বাছলে ১০ থেকে ৮ ঘণ্টা, আর ক্যাপাসিটি ১,৫১০ থেকে ১,২০৮।
  - **দৈনিক টার্গেট:**
    - হাতে না লেখা পর্যন্ত টার্গেট হয় min(মাস্টার টার্গেট, ক্যাপাসিটি)। মাস্টারে না থাকা লাইনে টার্গেট = ক্যাপাসিটি।
    - মাস্টারের সাথে পার্থক্য নিচে দেখায় (যেমন "−302 vs master")।
  - **Add line / reset / remove:** Add line দিয়ে লাইন যোগ করা যায়। ↺ দিয়ে লাইন মাস্টারে ফেরানো যায়, ✕ দিয়ে দিন থেকে সরানো যায়। সেভ করলে তালিকায় না থাকা PLANNED সারি মুছে যায়।
  - **আউটপুট** শুধু দিনটা এলে লেখা যায়, আগে লিখলে ভ্যালিডেশন আসে।
  - **Confirm Day:**
    - সেভ না করে চলে না।
    - নিশ্চিত দিনের রিসোর্স বন্ধ থাকে, শুধু আউটপুট আর মন্তব্য বদলায়।
  - মাস্টার প্ল্যান নিজে কখনো বদলায় না।
- **বর্তমান পূর্বাভাস** (Master Plan, Daily Plan, Plan vs Actual):
  - তৈরি = লেখা আউটপুটের যোগফল।
  - **আজ পর্যন্ত প্ল্যান:** আজ পর্যন্ত মাস্টার টার্গেটের যোগফল।
  - **সময়সীমা পর্যন্ত ক্যাপাসিটি:** আজ থেকে মাস্টার প্ল্যানের দিনের ক্যাপাসিটি। দৈনিক প্ল্যান থাকা দিনে তার টার্গেট। Brickland-এর মতো সেভের পরে ১০,০২৬ থেকে ৯,৭২৪ হয়।
  - **শেষের তারিখ:** বাকি পরিমাণ ÷ প্ল্যানের দৈনিক ক্যাপাসিটি, কাজের দিনে। আজকের আউটপুট লেখা হয়ে গেলে গোনা শুরু হয় আগামীকাল থেকে।
  - **অবস্থা:** COMPLETED / AT_RISK (শেষ সেলাইয়ের দিন পেরোয়) / DELAYED / NOT_STARTED / AHEAD / ON_TRACK।
- **Plan vs Actual:**
  - টাইলে NOT_STARTED অর্ডারকে On Track ধরা হয়। Brickland-ও তাই করে: ৩টা লক = ১ At Risk + ১ Delayed + ১ On Track (যেটা Not Started)।
  - **দৈনিক অগ্রগতি:** মাস্টার দিন আর দৈনিক প্ল্যান মিলিয়ে, লাইন আর দিন ধরে। Balance গোনা হয় window-এর আগের উৎপাদন থেকে। ডিফল্ট window আজ থেকে পেছনের ৭ দিন।
- **Line Capacity View:**
  - **হাতে আছে:** লাইন মাস্টারের জনবল × ঘণ্টা × ৬০ × এফিসিয়েন্সি, কারখানার কাজের দিনে।
  - **প্ল্যান করা:** টার্গেট × SMV। দৈনিক প্ল্যান থাকলে সেটা, না হলে লক করা মাস্টার প্ল্যান।
  - **ঘরের অবস্থা:** Holiday / Free / Part loaded / Full (≥১০০%) / Over capacity (মিনিট > হাতে আছে)।
  - Brickland-এর অক্টোবরের ১৭,৯১৪ ঘণ্টা একই সূত্রে মেলে।
- **Gantt / Timeline:** মাসে কোনো কাজ আছে এমন প্রতিটা চালু প্ল্যান (লক বা খসড়া)। মাসের বাইরে শুরু বা শেষ হলে ◂/▸ চিহ্ন, পুরো বাইরে থাকলে "ended …"/"starts …"। আজকের তারিখে ড্যাশ দাগ।

## ৬. API ও পারমিশন

- **Master Plan** (`PlanningPlanApiController`):
  - `GET /planning/projections`
  - `GET /planning/master-plans/orders`
  - `GET /planning/master-plans/order/{order}`
  - `POST /planning/master-plans/calculate`
  - `GET|POST /planning/master-plans`
  - `GET /planning/master-plans/excel`
  - `GET /planning/master-plans/{plan}?v=`
  - `DELETE /planning/master-plans/{plan}/draft`
  - `POST /planning/master-plans/{plan}/confirm`
  - `POST /planning/master-plans/{plan}/revisions`
  - `GET /planning/revision-requests`
  - `POST /planning/revision-requests/{id}/approve|reject`
- **Daily Plan** (`PlanningDailyApiController`):
  - `GET /planning/daily-plans/orders`
  - `GET /planning/daily-plans/day?date=&order=`
  - `POST /planning/daily-plans`
  - `POST /planning/daily-plans/confirm`
  - `GET /planning/daily-plans?from&to&line&status`
  - `GET /planning/plan-vs-actual?from&to&line&q`
  - `GET /planning/plan-vs-actual/excel`
- **বোর্ড** (`PlanningBoardApiController`):
  - `GET /planning/line-capacity?year&month`
  - `GET /planning/timeline?year&month&plans&q`
- অর্ডার আর প্ল্যান সবসময় নম্বর দিয়ে খোলে, ULID দিয়ে নয়।
- **পারমিশন:**
  - `planning.plan.view`
  - `planning.plan.manage` (প্ল্যান, দৈনিক প্ল্যান)
  - `planning.plan.approve` (সংশোধনের সিদ্ধান্ত)
  - FACTORY_ADMIN আর FACTORY_MANAGER তিনটাই পায়, LINE_CHIEF শুধু view।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: PLANNING → Capacity Planning → PRODUCTION PLAN-এ ৯টা স্ক্রিন, Brickland-এর ক্রমে।
- **Production Plan-এর ধাপের বার:** এখন ৬টা ধাপই লিংক হয় (Factory Calendar → Plan vs Actual)।
- **Projections:** বছর, মাসের তালিকা (সংখ্যাসহ), ৪টা টাইল, খোঁজা, অর্ডার দেখার modal, Open / Create Plan।
- **Master Plan:**
  - সিনারিও, লাইন বাছাই, ইনপুট, সাথে সাথে ক্যাপাসিটি, Calculate, ফলাফলের ট্যাব।
  - Save, Confirm আর Delete-এর dialog।
  - লক করা প্ল্যান: পূর্বাভাস, ৭টা ট্যাব, revision।
  - URL `?order=` বা `?plan=&v=`।
- **Master Plan List / Revision Request:** ফিল্টার, Export (xlsx), Approve / Reject।
- **Daily Plan:**
  - তারিখ আর অর্ডার বাছাই (URL `?date=&order=`, রিলোড ছাড়া)।
  - লাইনের সারণী: শিফট, ইনপুট, ক্যাপাসিটি, মাস্টার আর দৈনিক টার্গেট, আসল, অবস্থা, ↺ ✕।
  - মোট, Add line, Confirm Day, Save Daily Plan।
  - বর্তমান পূর্বাভাস, দিনের Dashboard।
- **Daily Plan List:** তারিখ থেকে–পর্যন্ত, লাইন আর অবস্থার ফিল্টার। Brickland-এর records grid (sort, পাতা, Export, Refresh)। Open চাপলে দিনটা খোলে।
- **Plan vs Actual & Forecast:**
  - ৬টা টাইল।
  - অর্ডারের সারণী: অগ্রগতির বার, পূর্বাভাস, পার্থক্য, বাড়তি ক্যাপাসিটি। সাথে খোঁজা আর Export।
  - দৈনিক অগ্রগতি: From / To / Line।
- **Line Capacity View:** বছর / মাস, Utilisation % / Planned pieces, ৪টা টাইল, লাইন × দিনের গ্রিড (DAYS আর UTIL. কলামসহ), legend।
- **Gantt / Timeline:** বছর, মাস, প্ল্যানের ধরন, খোঁজা। একই দিনের মাথার নিচে প্রতিটা প্ল্যান, প্রতিটার নামের সারি আর কাজের বার।
- **শেয়ার করা অংশ** (`plan-common.jsx`):
  - `ForecastTiles` (Master Plan আর Daily Plan দুই জায়গায়)।
  - `GanttHead` / `GanttBars` / `ganttDays` (প্ল্যানের Gantt আর Timeline)।
  - `capacity`, `keepUrl`, `firstError`।
- **কিট:** `RecordsGrid`-এর কলামে ঐচ্ছিক `format` (সংখ্যায় হাজারের কমা, sort আর CSV কাঁচা সংখ্যায়)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/Planning/Database/Migrations/2026_12_27_000100_create_planning_production_plans.php`
- `app/Modules/Planning/Models/PlanningMasterPlan.php`
- `app/Modules/Planning/Models/PlanningMasterPlanLine.php`
- `app/Modules/Planning/Models/PlanningMasterPlanDay.php`
- `app/Modules/Planning/Models/PlanningMasterPlanActivity.php`
- `app/Modules/Planning/Models/PlanningRevisionRequest.php`
- `app/Modules/Planning/Models/PlanningPlanEvent.php`
- `app/Modules/Planning/Models/PlanningDailyPlan.php`
- `app/Modules/Planning/Support/PlanningEngine.php`
- `app/Modules/Planning/Http/Controllers/Api/PlanningPlanApiController.php`
- `app/Modules/Planning/Http/Controllers/Api/PlanningDailyApiController.php`
- `app/Modules/Planning/Http/Controllers/Api/PlanningBoardApiController.php`
- `app/Modules/Planning/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`

**Frontend**
- `frontend/src/app/(app)/capacity-planning/[screen]/page.js`
- `frontend/src/app/(app)/capacity-planning/[screen]/screens.js`
- `frontend/src/app/(app)/capacity-planning/[screen]/actions.js`
- `frontend/src/app/api/capacity-planning/excel/[kind]/route.js`
- `frontend/src/components/planning/plan-common.jsx`
- `frontend/src/components/planning/projections.jsx`
- `frontend/src/components/planning/master-plan.jsx`
- `frontend/src/components/planning/plan-lists.jsx`
- `frontend/src/components/planning/daily-plan.jsx`
- `frontend/src/components/planning/plan-vs-actual.jsx`
- `frontend/src/components/planning/line-capacity.jsx`
- `frontend/src/components/planning/plan-timeline.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `lang/en/planning.php`
- `lang/bn/planning.php`

**Tests**
- `tests/Feature/Planning/PlanningMasterPlanTest.php`
- `tests/Feature/Planning/PlanningDailyPlanTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`PlanningMasterPlanTest` ২/২ (৬৫ assertion):**
  - প্রবাহ: Projections, অর্ডার খোলা, Calculate, Save Draft, Confirm & Lock, অন্য অর্ডারের খালি দিন কমে যাওয়া, সংশোধন → V2, V2 লক → V1 REVISED, Excel।
  - Brickland-এর সংখ্যা মেলে: ১৫১ / ১,৫১০ / ৩,৫৭৮ আর ২২৯ / ১,৮৩২ / ৪,৩৪২, সেলাই ৫–৮ নভেম্বর।
  - খসড়া মোছা আর Forward প্ল্যান।
- **`PlanningDailyPlanTest` ৩/৩ (৭৫ assertion):**
  - **দিন খোলা:** শুধু লক করা অর্ডার আসে। দিনের লাইন, ক্যাপাসিটি আর মাস্টার টার্গেট আসে। শিফটের লেবেল আর ঘণ্টা আসে।
  - **সেভ:** লাইন চিফ সেভ করতে পারে না। ভবিষ্যতের দিনে আউটপুট লেখা যায় না। ST-GEN দিলে ১,২০৮ আর পূর্বাভাস ৯,৭২৪। লাইন সরালে তার সারি মুছে যায়।
  - **List আর Confirm:** List ফিল্টার কাজ করে। নিশ্চিত দিনে রিসোর্স বদলায় না, মন্তব্য বদলায়।
  - **আউটপুট:** দিনে আউটপুট লিখলে পূর্বাভাস আর Plan vs Actual-এর অগ্রগতি, পার্থক্য আর Balance ঠিক আসে (window-এর আগের উৎপাদনসহ)। Excel চলে।
  - **Line Capacity:** ১৭,৯১৪ ঘণ্টা হাতে, ১,১২২ ঘণ্টা প্ল্যান করা, ৬%, ৭২ / ০। ঘর ১০০ Full আর ৩৭ Part। দৈনিক প্ল্যান সেভ করলে ৮০ Part আর ১০৯ Over।
  - **Timeline:** মাস, প্ল্যানের ধরন আর খোঁজা কাজ করে।
- **পুরনো টেস্ট আবার:** PlanningMasterTest, AccessControlConfiguration, ApiGateCoverage, MenuAccess, RoleMatrix সব পাস।
- **ব্রাউজার (dev, plu01–plu07, ডাটা রাখা আছে):**
  - **Master Plan:** MP-2026120001 V1 লক করা আর V2 খসড়া। Projections-এ "Master Plan Locked V1" আর "Plan locked 1" দেখায়।
  - **Daily Plan:** ১৯ ডিসেম্বর, LINE01-এ ST-GEN আর টার্গেট ১,৫০০, LINE05 ৮২৭। সেভ, toast, Dashboard, Confirm Day। Daily Plan List-এ ২টা সারি, Open চাপলে দিনটা খোলে।
  - **Plan vs Actual:** টাইল, সারি, ডিসেম্বরের অগ্রগতি, Excel ডাউনলোড।
  - **Line Capacity:** ডিসেম্বরে LINE01 ৭১%, LINE05 ৪১%, Planned pieces ১,৫০০ / ৮২৭। মাস বদলালে রিলোড হয় না।
  - **Timeline:** V1 লক আর V2 খসড়া দেখায়, "Drafts only" ফিল্টার কাজ করে, খোঁজা কাজ করে। Master Plan-এর Gantt ট্যাবও কাজ করে।
  - কনসোলে error নেই।

## ১০. খোলা প্রশ্ন

- **সেলাই স্ক্যান:**
  - Brickland-এ লেখা আউটপুট না থাকলে "From scans" দেখায়, আর আসল আউটপুট আসে লাইনের সেলাই স্ক্যান থেকে।
  - এখানে এখনো সেলাই উৎপাদন মডিউল নেই (Garments Production), তাই আউটপুট শুধু দৈনিক প্ল্যানে লেখা সংখ্যা। খালি থাকলে "From scans" দেখায়।
  - Production Line-এর floor line ম্যাপিং আগে থেকেই আছে। সেলাই মডিউল তৈরি হলে স্ক্যান থেকে আউটপুট পড়া যোগ হবে।
- **Planning board:** Line Capacity View-এ Brickland বলে "planning board"-ও পড়া হয়। Brickland-এ আলাদা কোনো planning board স্ক্রিন পাওয়া যায়নি, তাই দৈনিক প্ল্যান আর লক করা মাস্টার প্ল্যান পড়া হয়।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১০-২৫):**
- নয়টা স্ক্রিনই তৈরি, তিন ভাগে:
  - Projections, Master Plan, Master Plan List, Revision Request।
  - Daily Plan, Daily Plan List, Plan vs Actual & Forecast।
  - Line Capacity View, Gantt / Timeline।
- **দৈনিক প্ল্যানের শিফট:** প্রথমে planning line-এর নিজস্ব শিফট ধরা হয়েছিল। Brickland-এ সব লাইনে HR-এর একই শিফট তালিকা দেখায়, তাই `shift_id` এখন `hr_shifts`-এ যুক্ত।
- **Daily Plan List-এর Export:** Brickland-এর records grid-এর মতোই, grid থেকে CSV। আলাদা backend Excel নেই।
- **পূর্বাভাসে দুটো উন্নতি:**
  - আজকের আউটপুট লেখা হয়ে গেলে শেষের তারিখ আগামীকাল থেকে গোনা হয়, নইলে আজকের দিন দুবার ধরা হতো।
  - দৈনিক প্ল্যান থাকা দিনে তার টার্গেট ধরা হয়, Brickland-এর নোট অনুযায়ী।
- `ui.mjs`-এ নতুন `choose` ধাপ (native `<select>` তার option-এর লেখা দিয়ে বাছা)।
